---
title: Import and export
description: Add CSV and Excel export and import to a Resource, with column mapping, validation, hooks and queued runs.
order: 2
---

# Import and export

`ExportAction` writes the chosen rows to a CSV or XLSX file. `ImportAction` reads a CSV or XLSX file into a model through a short flow: upload, map the columns, check, import. Both are ordinary actions: you return them from a Resource's `actions()` method.

Both need an ability on the model's policy: `export` for `ExportAction` and `import` for `ImportAction`. A policy without the method denies. See [Policies](../security/policies.md).

```php
public function export(User $user): bool
{
    return $user->isAdmin();
}

public function import(User $user): bool
{
    return $user->isAdmin();
}
```

## Export

Add the action to the Resource:

```php
use Lathe\Actions\Export\ExportAction;
use Lathe\Http\Requests\LatheRequest;

public function actions(LatheRequest $request): array
{
    return [
        ExportAction::make(),
    ];
}
```

The modal asks for a **Filename** and a **Type** (CSV or Excel). By default the file holds the columns of the Resource's table, with their labels as the header.

Rows come from the same query as the table. In "select all matching" mode the export respects the search, filters, tab and trashed scope, and the Resource's `indexQuery()`. A user never exports a row the table hides. With ticked rows, only those rows are exported. An empty selection answers 422.

Cells are plain text, so a value that starts with `=` never runs as a spreadsheet formula. Dates use `Y-m-d H:i:s` in the user's time zone, and booleans read Yes or No. An XLSX file holds at most 1,048,575 data rows. A larger export must use CSV.

### Choose the columns

`withFormat()` replaces the default columns. The closure receives one model and returns an array of header => value:

```php
ExportAction::make()->withFormat(fn ($user) => [
    'Name' => $user->name,
    'Email' => $user->email,
    'Role' => $user->role->name,
    'Registered' => $user->created_at,
])
```

### Change the query

`withQuery()` runs last on the query. It receives the builder and the modal's `ActionFields`. Return the builder, or nothing to keep it as it is:

```php
ExportAction::make()
    ->withQuery(fn ($query, $fields) => $query->with(['role', 'department']))
```

### Defaults for the modal

The modal always has a Filename and a Type. Set what they start with:

```php
ExportAction::make()
    ->nameable(fn ($request) => 'users-'.now()->format('Y-m-d'))
    ->withTypeSelector('xlsx')
```

`withFields()` adds your own fields to the modal. Read them in `withQuery()`:

```php
use Lathe\Field\Field;

ExportAction::make()
    ->withFields(fn ($request) => [
        Field::boolean('Include archived', 'include_archived'),
    ])
    ->withQuery(fn ($query, $fields) => $fields->include_archived ? $query->withTrashed() : $query)
```

### Small and large exports

Up to `limits.bulk_inline_max` rows (500), Lathe writes the file during the request, and the browser downloads it at once.

Above that, Lathe queues the export. The user sees "Export started." and a progress toast. When the file is ready, the toast offers a Download button, and a notification with the same button arrives in the bell. A queued export needs a queue worker. See [Deployment](../getting-started/deployment.md).

`queueable()` is optional. It only sets where the queued job runs:

```php
ExportAction::make()->queueable(
    connection: 'redis',
    queue: 'exports',
    tries: 3,
    timeout: 600,
    chunkSize: 1000,
)
```

`chunkSize` is both the number of rows read at a time and how often the progress count updates.

### Where files go

Set the disk and folder in `config/lathe.php`:

```php
'exports' => [
    'disk' => env('LATHE_EXPORT_DISK', 'local'),
    'directory' => 'lathe-exports',
    'chunk_size' => 500,
    'prune_after_hours' => 24,
],
```

Lathe saves the file as `{directory}/{ulid}-{name}.{type}`, so two users never overwrite each other. The download name is `{name}.{type}`. The download link is signed, works for the user who started the export only, and expires after `prune_after_hours`. `lathe:prune` deletes the record and the file after that time. See [Configuration](../getting-started/configuration.md).

An export records no audit rows by default.

## Import

Register an `ImportAction` and describe the columns it accepts. `ImportAction::withColumns()` takes the columns as one array, or as separate arguments. `ImportAction::withModel()` names the model the rows go into.

```php
use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;

public function actions(LatheRequest $request): array
{
    return [
        ImportAction::make()
            ->withModel(\App\Models\User::class)
            ->withColumns([
                ImportColumn::make('name')->rules('required', 'string', 'max:255'),
                ImportColumn::make('email')->rules('required', 'email', 'unique:users,email'),
                ImportColumn::make('role'),
            ]),
    ];
}
```

- `ImportAction::withColumns(ImportColumn|array $columns, ImportColumn ...$more)` sets the columns a file may fill.
- `ImportAction::withModel(string $class)` sets the target model. Call `forResource(static::class)` instead, as the workbench does, to use the Resource's own model. With neither, the action throws a `LogicException` that names the fix.

### The four steps

1. **Upload.** The user picks a CSV or XLSX file. It uploads in chunks, up to `imports.max_file_size_mb` (50 MB). Lathe reads only the header and five preview rows. Other file types are refused. An empty file shows "The file is empty and has no headers."
2. **Map.** The modal lists each header with a menu of your columns. Lathe pre-selects the best match. The user can pick **Skip** for a header. Continue stays off while a column with a `required` rule has no header.
3. **Check.** The button names how many rows the file holds, such as "Check 2,400 rows". A dry run reads the whole file, validates it, and writes nothing. It reports how many rows would be created, updated, skipped or fail, shows the first 20 errors, and links a CSV of the failed rows.
4. **Import.** The same job runs for real. It always runs in the queue, so it needs a queue worker. See [Deployment](../getting-started/deployment.md).

The user sees a progress toast and a run page at `/lathe/runs/import/{id}`. When the job ends, a notification gives the counts: created, updated, skipped and failed. If rows failed, it links a CSV of those rows with the errors in a `_errors` column.

An import you started belongs to you. Another user's import id answers 404.

### Columns

An `ImportColumn` names a model attribute:

```php
ImportColumn::make('email_address')->label('Email')
```

The label defaults to the headline of the name. The mapping step matches headers to columns by name first, then by label, with a small tolerance for typos. A column the file lacks is absent from the row and is never written as `null`. `ignoreAutoMapping()` keeps a column out of the automatic match, so the user must map it.

#### Rules and messages

`rules()` takes the same rule strings as Laravel. `ImportColumn::messages()` replaces the message of a rule. The array is keyed by rule name:

```php
ImportColumn::make('email')
    ->rules('required', 'email')
    ->messages([
        'required' => 'The email column cannot be empty.',
        'email' => 'The email must be a valid address.',
    ])
```

Behind the scenes Lathe turns each pair into `email.required` for the validator.

#### Nullable columns

An empty cell stays an empty string unless the column is `nullable()`:

```php
ImportColumn::make('phone')->nullable()
```

A nullable column turns an empty cell into `null` before casting. Mark every optional column that way if the database column should hold `NULL`.

#### Casting

A cast turns the text of a cell into a value:

```php
ImportColumn::make('is_active')->boolean()
ImportColumn::make('price')->numeric(decimalPlaces: 2)
ImportColumn::make('birthday')->date('Y-m-d')
ImportColumn::make('department_id')->relationship('department', resolveBy: 'name')
ImportColumn::make('status')->castUsing(fn ($value) => strtolower($value) === 'yes' ? 'active' : 'inactive')
```

| Cast | Result |
|---|---|
| `boolean()` | `1`, `true`, `yes`, `on` and `y` (any case) become `true`. Everything else becomes `false`. |
| `numeric(?int $decimalPlaces)` | Keeps digits, `.` and `-`, so a currency symbol and a thousands separator fall away. A whole number becomes an integer. A cell with no digits becomes `null`. |
| `date(?string $format)` | Parses the cell as a date in the user's time zone and stores it in UTC. Without a format Lathe guesses. A cell that does not parse fails the row with "Invalid date". |
| `relationship($name, $resolveBy = 'id')` | Looks up the related record by that column and stores its key. A cell with no match becomes `null`, and your rules decide whether that is an error. Lathe does one lookup per chunk. |
| `castUsing(Closure)` | Runs your closure on the cleaned value, after any other cast. |

Before every cast, Lathe removes a byte-order mark, trims the text and makes line endings consistent.

### Update or skip duplicates

`upsertBy()` updates the row whose column matches and creates the others. A key that appears twice in the file updates the row from the first time:

```php
ImportAction::make()
    ->withModel(\App\Models\User::class)
    ->upsertBy('email')
    ->withColumns([
        ImportColumn::make('name'),
        ImportColumn::make('email')->rules('required', 'email'),
    ])
```

`skipDuplicates()` leaves alone any row whose value is already in the table or earlier in the file:

```php
->skipDuplicates('email')
```

Lathe looks up the keys of one chunk at a time, so a large table does not slow the import down. Rows save through the model, and the model's `$fillable` decides which attributes a row may set. The Resource's own create hooks do not run.

### Hooks

Six hooks run for each row, in this order: `beforeRow`, then the casts, then `beforeValidate`, validation, `afterValidate`, the duplicate check, `beforeSave`, the save, `afterSave` and `afterRow`. Each takes a closure that receives the row as an array and the row number. The header is row 1, so data starts at 2.

```php
ImportAction::make()
    ->withModel(\App\Models\User::class)
    ->withColumns([/* ... */])
    ->beforeRow(fn (array $row, int $rowNumber) => $row['email'] === '' ? false : $row)
    ->beforeValidate(function (array $row, int $rowNumber) {
        $row['email'] = strtolower($row['email']);

        return $row;
    })
    ->beforeSave(function (array $row, int $rowNumber) {
        $row['password'] = bcrypt('change-me');

        return $row;
    })
    ->afterSave(fn (array $row, int $rowNumber) => logger("Row {$rowNumber} imported."))
```

`beforeRow` can return `false` to skip the row. A hook that returns an array replaces the row for the steps after it. A hook that returns nothing leaves the row as it was. The check step stops before `beforeSave`, so a dry run never runs your save hooks.

### More fields in the modal

`withFields()` adds fields to the import modal, as it does for an export:

```php
->withFields(fn ($request) => [
    Field::select('Default role', 'default_role')->options(['admin', 'editor']),
])
```

### Queue and files

`queueable()` sets the connection, queue, tries, timeout and chunk size of the import job, as on an export:

```php
ImportAction::make()->queueable(queue: 'imports', timeout: 600, chunkSize: 500)
```

Configure the disk, folder, size limit and pruning in `config/lathe.php`:

```php
'imports' => [
    'disk' => env('LATHE_IMPORT_DISK', 'local'),
    'directory' => 'lathe-imports',
    'chunk_size' => 500,
    'max_file_size_mb' => 50,
    'prune_after_hours' => 24,
],
```

`lathe:prune` deletes old imports, their failed rows, the uploaded file and the error file.

### Failed rows and resume

A row that fails validation or throws is recorded with its number and its errors in `lathe_failed_import_rows`. After the job, Lathe writes them to a CSV with the original columns and an `_errors` column. The run page and the notification link it. The link is signed and expires with `prune_after_hours`.

Lathe saves a checkpoint after each chunk. If the job crashes, the run page offers **Resume**, which continues after the last saved chunk. No row is written twice. A queue retry resumes the same way.

## Test

Run exports and imports through the Resource tester, as any action. See [Testing](../your-first-resource/testing.md). When you test that an export leaves out another tenant's row, also assert that the acting user's own row is in the file. Otherwise the test passes on an empty file and proves nothing.

## Complete example

```php
use Lathe\Actions\Export\ExportAction;
use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;
use Lathe\Http\Requests\LatheRequest;

public function actions(LatheRequest $request): array
{
    return [
        ExportAction::make()
            ->withFormat(fn ($contact) => [
                'Name' => $contact->name,
                'Email' => $contact->email,
                'Company' => $contact->company->name,
                'Created' => $contact->created_at,
            ])
            ->withQuery(fn ($query) => $query->with('company'))
            ->nameable('contacts')
            ->withTypeSelector('csv'),

        ImportAction::make()
            ->withModel(\App\Models\Contact::class)
            ->upsertBy('email')
            ->withColumns([
                ImportColumn::make('name')->rules('required', 'string', 'max:255'),
                ImportColumn::make('email')->rules('required', 'email'),
                ImportColumn::make('company_id')
                    ->label('Company')
                    ->relationship('company', resolveBy: 'name'),
                ImportColumn::make('is_vip')->boolean()->nullable(),
            ])
            ->beforeSave(function (array $row) {
                $row['imported_at'] = now();

                return $row;
            }),
    ];
}
```

## What you see

On a Resource with these actions, the Actions menu lists Export and Import. Export opens a modal with a filename and a type, then downloads the file or shows a progress toast that turns into a Download button. Import opens a file picker, then a mapping screen with your headers, then a report of rows that would be created, updated or fail. After you confirm, a progress toast follows the job, and a notification in the bell gives the final counts.

<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lathe\Actions\Import\FailedImportRow;
use Lathe\Actions\Import\Import;
use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;
use Lathe\Actions\Import\ImportComplete;
use Lathe\Actions\Import\ProcessImport;
use Lathe\Auth\ResourceAuthorization;
use Lathe\Support\JobContext;
use Lathe\Tests\Fixtures\Models\UlidThing;
use Lathe\Tests\Fixtures\Models\UuidThing;
use Workbench\App\Lathe\Actions\ImportProducts;
use Workbench\App\Lathe\Resources\Product as ProductResource;
use Workbench\App\Models\Customer;
use Workbench\App\Models\Order;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
    Gate::define('viewLathe', fn () => true);
    Storage::fake('local');
    $this->admin = User::where('email', 'admin@lathephp.com')->firstOrFail();
    Cache::store('array')->flush();
});

/**
 * Store CSV text as an uploaded import file and make its Import row for the admin.
 *
 * @param  array<int, string|null>  $map  header index => column name
 * @param  class-string  $model
 */
function uploadedImport(string $csv, array $map, string $model = Product::class): Import
{
    $file = 'lathe-imports/'.Str::ulid().'.csv';
    Storage::disk('local')->put($file, $csv);

    return Import::create([
        'user_id' => (string) test()->admin->id,
        'file_disk' => 'local',
        'file_name' => $file,
        'original_name' => 'products.csv',
        'model_class' => $model,
        'column_map' => $map,
    ]);
}

function fixtureCsv(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 3)."/workbench/fixtures/{$name}");
}

function adminContext(): JobContext
{
    return new JobContext(test()->admin->id, 'web', 'en', 'UTC');
}

/**
 * Append an entry to the list the hooks write to.
 */
function hookLog(string $entry): void
{
    Cache::store('array')->put('hooks', [...hookEntries(), $entry]);
}

/**
 * @return list<string>
 */
function hookEntries(): array
{
    $entries = Cache::store('array')->get('hooks', []);

    return is_array($entries) ? array_values($entries) : [];
}

/**
 * @return array{created_rows: int, updated_rows: int, skipped_rows: int, failed_rows: int}
 */
function importCounts(Import $import): array
{
    $import->refresh();

    return [
        'created_rows' => $import->created_rows,
        'updated_rows' => $import->updated_rows,
        'skipped_rows' => $import->skipped_rows,
        'failed_rows' => $import->failed_rows,
    ];
}

/**
 * The CSV text of many products: SKU-A0001 onwards.
 */
function manyProductsCsv(int $rows): string
{
    $lines = ['Name,SKU,Price'];

    for ($i = 1; $i <= $rows; $i++) {
        $lines[] = sprintf('Product %d,SKU-A%04d,%d.50', $i, $i, $i);
    }

    return implode("\n", $lines)."\n";
}

$productsMap = [0 => 'name', 1 => 'sku', 2 => 'price'];

it('lets admin import products and denies the editor', function () {
    $authorization = app(ResourceAuthorization::class);
    $ability = ImportProducts::make()->ability();

    expect($authorization->allows($this->admin, ProductResource::class, $ability))->toBeTrue()
        ->and($authorization->allows(User::where('email', 'editor@lathephp.com')->firstOrFail(), ProductResource::class, $ability))->toBeFalse();
});

it('dry runs products.csv: 2 would be created, 1 updated, nothing written', function () use ($productsMap) {
    $import = uploadedImport(fixtureCsv('products.csv'), $productsMap);
    $action = ImportProducts::make()
        ->beforeSave(function (array $row) {
            hookLog('beforeSave');

            return $row;
        })
        ->afterSave(fn () => hookLog('afterSave'))
        ->afterRow(fn () => hookLog('afterRow'));
    $before = Product::where('sku', 'SKU-00001')->value('name');

    $action->start($import, 'dry_run', adminContext());

    expect(importCounts($import))->toBe(['created_rows' => 2, 'updated_rows' => 1, 'skipped_rows' => 0, 'failed_rows' => 0])
        ->and($import->status)->toBe('completed')
        ->and($import->mode)->toBe('dry_run')
        ->and($import->total_rows)->toBe(3)
        ->and($import->error_file)->toBeNull()
        ->and(Product::count())->toBe(500)
        ->and(Product::where('sku', 'SKU-00001')->value('name'))->toBe($before)
        ->and(hookEntries())->toBe([]);
});

it('dry runs products-bad.csv into an error CSV naming row 4', function () use ($productsMap) {
    Notification::fake();
    $import = uploadedImport(fixtureCsv('products-bad.csv'), $productsMap);

    ImportProducts::make()->start($import, 'dry_run', adminContext());

    expect(importCounts($import))->toBe(['created_rows' => 2, 'updated_rows' => 0, 'skipped_rows' => 0, 'failed_rows' => 1])
        ->and($import->error_file)->toBe("lathe-imports/errors-{$import->id}.csv")
        ->and(FailedImportRow::where('import_id', $import->id)->get(['row_number', 'data', 'errors'])->toArray())->toMatchStoredJson([[
            'row_number' => 4,
            'data' => ['name' => 'Broken Price', 'sku' => 'SKU-90013', 'price' => 'abc'],
            'errors' => ['price' => ['The price field must be a number.']],
        ]])
        ->and(Storage::disk('local')->get($import->error_file))->toBe(
            "\u{FEFF}_row_number,name,sku,price,_errors\n4,\"Broken Price\",SKU-90013,abc,\"price: The price field must be a number.\"\n",
        )
        ->and(Product::count())->toBe(500);

    Notification::assertNothingSent();
});

it('imports products.csv and tells the admin', function () use ($productsMap) {
    Notification::fake();
    $import = uploadedImport(fixtureCsv('products.csv'), $productsMap);

    ImportProducts::make()->start($import, 'import', adminContext());

    $updated = Product::where('sku', 'SKU-00001')->firstOrFail();

    expect(importCounts($import))->toBe(['created_rows' => 2, 'updated_rows' => 1, 'skipped_rows' => 0, 'failed_rows' => 0])
        ->and($import->status)->toBe('completed')
        ->and($import->completed_at)->not->toBeNull()
        ->and(Product::count())->toBe(502)
        ->and([$updated->name, $updated->price_cents])->toBe(['Updated Product One', 1999])
        ->and(Product::where('sku', 'SKU-90002')->value('price_cents'))->toBe(3400);

    Notification::assertSentTo($this->admin, ImportComplete::class, fn (ImportComplete $notification) => $notification->toArray() === [
        'icon' => 'check-circle',
        'message' => 'Your import has completed.',
        'type' => 'success',
        'body' => '2 created, 1 updated, 0 skipped, 0 failed.',
    ]);
});

it('quotes a failed cell that a spreadsheet would run as a formula in the error CSV', function () use ($productsMap) {
    Notification::fake();
    $import = uploadedImport("Name,SKU,Price\n=HYPERLINK(\"http://x\"),SKU-90021,abc\n", $productsMap);

    ImportProducts::make()->start($import, 'dry_run', adminContext());

    expect(Storage::disk('local')->get((string) $import->fresh()?->error_file))->toContain("\"'=HYPERLINK(\"\"http://x\"\")\"");
});

it('marks an import failed and notifies when the queue gives up on the job without a throw', function () use ($productsMap) {
    Notification::fake();
    $import = uploadedImport(fixtureCsv('products.csv'), $productsMap);
    $import->update(['status' => 'processing', 'mode' => 'import']);

    (new ProcessImport($import, ImportProducts::make(), adminContext()))->failed(new RuntimeException('The job ran too long.'));

    expect($import->fresh()?->status)->toBe('failed')
        ->and($import->fresh()?->exception)->toBe('The job ran too long.');
    Notification::assertSentTimes(ImportComplete::class, 1);
});

it('imports with notifications off and keeps the result for the run page', function () use ($productsMap) {
    Notification::fake();
    config(['lathe.notifications.enabled' => false]);
    $import = uploadedImport(fixtureCsv('products.csv'), $productsMap);

    ImportProducts::make()->start($import, 'import', adminContext());

    expect($import->fresh()?->status)->toBe('completed')
        ->and(importCounts($import))->toBe(['created_rows' => 2, 'updated_rows' => 1, 'skipped_rows' => 0, 'failed_rows' => 0])
        ->and(Product::count())->toBe(502);
    Notification::assertNothingSent();
});

it('imports products-bad.csv with a warning and a Download Failed Rows button', function () use ($productsMap) {
    Notification::fake();
    $import = uploadedImport(fixtureCsv('products-bad.csv'), $productsMap);

    ImportProducts::make()->start($import, 'import', adminContext());

    expect(Product::count())->toBe(502)
        ->and(Product::where('sku', 'SKU-90013')->exists())->toBeFalse();

    Notification::assertSentTo($this->admin, ImportComplete::class, function (ImportComplete $notification) use ($import) {
        $array = $notification->toArray();
        $action = $array['actions'][0];

        return $array['type'] === 'warning'
            && $array['message'] === 'Your import has completed.'
            && $array['body'] === '2 created, 0 updated, 0 skipped, 1 failed.'
            && count($array['actions']) === 1
            && [$action['text'], $action['style'], $action['openInNewTab']] === ['Download Failed Rows', 'button', true]
            && str_contains($action['url'], "/lathe/api/imports/{$import->id}/download-errors?expires=");
    });
});

/**
 * A hook that logs its name and the row number and keeps the row.
 */
function loggingHook(string $name): Closure
{
    return function (array $row, int $rowNumber) use ($name) {
        hookLog("{$name}:{$rowNumber}");

        return $name === 'beforeRow' ? true : null;
    };
}

it('runs the hooks of a row in order', function () {
    $import = uploadedImport("SKU,Name\nSKU-00002,Renamed\n", [0 => 'sku', 1 => 'name']);
    $hook = loggingHook(...);

    ImportAction::make()->withModel(Product::class)
        ->withColumns(ImportColumn::make('sku'), ImportColumn::make('name'))
        ->upsertBy('sku')
        ->beforeRow($hook('beforeRow'))->beforeValidate($hook('beforeValidate'))->afterValidate($hook('afterValidate'))
        ->beforeSave($hook('beforeSave'))->afterSave($hook('afterSave'))->afterRow($hook('afterRow'))
        ->start($import, 'import', adminContext());

    expect(hookEntries())->toBe(['beforeRow:2', 'beforeValidate:2', 'afterValidate:2', 'beforeSave:2', 'afterSave:2', 'afterRow:2'])
        ->and(Product::where('sku', 'SKU-00002')->value('name'))->toBe('Renamed');
});

it('skips a row when beforeRow answers false', function () use ($productsMap) {
    $import = uploadedImport(fixtureCsv('products.csv'), $productsMap);

    ImportProducts::make()->beforeRow(fn (array $row) => $row['sku'] !== 'SKU-90001')
        ->start($import, 'import', adminContext());

    expect(importCounts($import))->toBe(['created_rows' => 1, 'updated_rows' => 1, 'skipped_rows' => 1, 'failed_rows' => 0])
        ->and(Product::where('sku', 'SKU-90001')->exists())->toBeFalse();
});

test('import_hooks_tolerate_absent_keys', function () {
    $import = uploadedImport("SKU,Name,Price\nSKU-00003,Kept Price,5\n", [0 => 'sku', 1 => 'name', 2 => 'price']);
    $price = Product::where('sku', 'SKU-00003')->value('price_cents');

    ImportProducts::make()
        ->withColumns(ImportColumn::make('name'), ImportColumn::make('sku'), ImportColumn::make('price')->numeric(2))
        ->beforeRow(function (array $row) {
            unset($row['price']);

            return $row;
        })
        ->afterRow(fn (array $row) => hookLog(implode(',', array_keys($row))))
        ->start($import, 'import', adminContext());

    expect(importCounts($import))->toBe(['created_rows' => 0, 'updated_rows' => 1, 'skipped_rows' => 0, 'failed_rows' => 0])
        ->and(hookEntries())->toBe(['sku,name'])
        ->and(Product::where('sku', 'SKU-00003')->value('price_cents'))->toBe($price)
        ->and(Product::where('sku', 'SKU-00003')->value('name'))->toBe('Kept Price');
})->group('regression');

test('import_leaves_unmapped_columns_untouched', function () {
    $import = uploadedImport("SKU,Name,Price\nSKU-00004,Ignored Name,7.25\n", [0 => 'sku', 1 => null, 2 => 'price']);
    $name = Product::where('sku', 'SKU-00004')->value('name');

    ImportProducts::make()
        ->withColumns(ImportColumn::make('name'), ImportColumn::make('sku'), ImportColumn::make('price')->numeric(2))
        ->beforeValidate(function (array $row) {
            hookLog(implode(',', array_keys($row)));

            return $row;
        })
        ->start($import, 'import', adminContext());

    expect(hookEntries())->toBe(['sku,price'])
        ->and(Product::where('sku', 'SKU-00004')->value('name'))->toBe($name)
        ->and(Product::where('sku', 'SKU-00004')->value('price_cents'))->toBe(725);
})->group('regression');

it('never writes a column outside the model fillable list', function () {
    $import = uploadedImport("Name,SKU,Price,Reorder\nNew Thing,SKU-90100,3,9\n", [0 => 'name', 1 => 'sku', 2 => 'price', 3 => 'reorder_level']);

    ImportProducts::make()
        ->withColumns(ImportColumn::make('name'), ImportColumn::make('sku'), ImportColumn::make('price')->numeric(2), ImportColumn::make('reorder_level')->numeric())
        ->start($import, 'import', adminContext());

    expect(importCounts($import)['created_rows'])->toBe(1)
        ->and(DB::table('products')->where('sku', 'SKU-90100')->value('reorder_level'))->toBe(5);
});

it('counts a present key as skipped with skipDuplicates', function () use ($productsMap) {
    $import = uploadedImport(fixtureCsv('products.csv'), $productsMap);
    $name = Product::where('sku', 'SKU-00001')->value('name');

    ImportAction::make()->withModel(Product::class)
        ->withColumns(ImportColumn::make('name'), ImportColumn::make('sku'), ImportColumn::make('price')->numeric(2))
        ->skipDuplicates('sku')
        ->beforeSave(fn (array $row) => ['name' => $row['name'], 'sku' => $row['sku'], 'price_cents' => (int) round($row['price'] * 100), 'category_id' => 1])
        ->start($import, 'import', adminContext());

    expect(importCounts($import))->toBe(['created_rows' => 2, 'updated_rows' => 0, 'skipped_rows' => 1, 'failed_rows' => 0])
        ->and(Product::where('sku', 'SKU-00001')->value('name'))->toBe($name);
});

it('updates a key seen earlier in the same file', function (string $mode) {
    $import = uploadedImport("Name,SKU,Price\nFirst,SKU-90200,1\nSecond,SKU-90200,2\n", [0 => 'name', 1 => 'sku', 2 => 'price']);

    ImportProducts::make()->start($import, $mode, adminContext());

    expect(importCounts($import))->toBe(['created_rows' => 1, 'updated_rows' => 1, 'skipped_rows' => 0, 'failed_rows' => 0])
        ->and(Product::where('sku', 'SKU-90200')->value('name'))->toBe($mode === 'import' ? 'Second' : null);
})->with(['import', 'dry_run']);

test('import_lookup_chunks_stay_under_bind_limit', function () {
    config(['lathe.imports.chunk_size' => 500]);
    $emails = Customer::orderBy('id')->limit(3)->pluck('email')->all();
    $lines = ['Number,Customer'];

    for ($i = 1; $i <= 1200; $i++) {
        $lines[] = sprintf('N%05d,%s', $i, $emails[$i % 3]);
    }

    $import = uploadedImport(implode("\n", $lines)."\n", [0 => 'number', 1 => 'customer_id'], Order::class);
    $lookups = 0;
    DB::listen(function (QueryExecuted $query) use (&$lookups) {
        $lookups += preg_match('/from ["`]?customers["`]? where/i', $query->sql) === 1 ? 1 : 0;
    });

    ImportAction::make()->withModel(Order::class)
        ->withColumns(ImportColumn::make('number'), ImportColumn::make('customer_id')->relationship('customer', 'email')->rules('required'))
        ->start($import, 'dry_run', adminContext());

    expect($lookups)->toBe(3)
        ->and(importCounts($import))->toBe(['created_rows' => 1200, 'updated_rows' => 0, 'skipped_rows' => 0, 'failed_rows' => 0]);
})->group('regression');

it('fails a row whose relationship value is missing by the column rules', function () {
    $import = uploadedImport("Number,Customer\nN1,nobody@example.com\n", [0 => 'number', 1 => 'customer_id'], Order::class);

    ImportAction::make()->withModel(Order::class)
        ->withColumns(ImportColumn::make('number'), ImportColumn::make('customer_id')->relationship('customer', 'email')->rules('required'))
        ->start($import, 'dry_run', adminContext());

    expect(FailedImportRow::where('import_id', $import->id)->value('errors'))->toBe(['customer_id' => ['The customer id field is required.']]);
});

/**
 * Make the checkpoint save past the first chunk throw once, as a worker killed mid-run would stop.
 */
function killAfterFirstChunk(): void
{
    Cache::store('array')->put('killed', false);
    Import::updating(function (Import $import) {
        if ($import->last_processed_row > 501 && Cache::store('array')->get('killed') === false) {
            Cache::store('array')->put('killed', true);

            throw new RuntimeException('Worker stopped.');
        }
    });
}

it('fails when killed after chunk 1 and resumes where it stopped', function (string $how) {
    config(['lathe.imports.chunk_size' => 500]);
    killAfterFirstChunk();
    $import = uploadedImport(manyProductsCsv(1200), [0 => 'name', 1 => 'sku', 2 => 'price']);
    $action = ImportProducts::make();

    expect(fn () => $action->start($import, 'import', adminContext()))->toThrow(RuntimeException::class, 'Worker stopped.');

    $import->refresh();
    expect([$import->status, $import->exception, $import->last_processed_row, $import->created_rows])
        ->toBe(['failed', 'Worker stopped.', 501, 500])
        ->and(Product::count())->toBe(1000);

    if ($how === 'resume') {
        $this->actingAs($this->admin)->postJson("/lathe/api/imports/{$import->id}/resume")->assertStatus(202);
    } else {
        (new ProcessImport($import->fresh() ?? $import, $action, adminContext()))->handle();
    }

    $import->refresh();
    expect([$import->status, $import->last_processed_row, $import->processed_rows, $import->created_rows, $import->updated_rows])
        ->toBe(['completed', 1201, 1200, 1200, 0])
        ->and(Product::count())->toBe(1700)
        ->and(Product::where('sku', 'like', 'SKU-A%')->count())->toBe(1200)
        ->and(Product::where('sku', 'SKU-A1200')->value('price_cents'))->toBe(120050);
})->with(['resume', 'retry']);

it('sends an error notification with Resume when the job fails', function () {
    Notification::fake();
    killAfterFirstChunk();
    config(['lathe.imports.chunk_size' => 500]);
    $import = uploadedImport(manyProductsCsv(600), [0 => 'name', 1 => 'sku', 2 => 'price']);

    expect(fn () => ImportProducts::make()->start($import, 'import', adminContext()))->toThrow(RuntimeException::class);

    Notification::assertSentTo($this->admin, ImportComplete::class, function (ImportComplete $notification) use ($import) {
        $array = $notification->toArray();

        return $array['type'] === 'error'
            && $array['message'] === 'Your import failed.'
            && $array['body'] === 'Worker stopped.'
            && array_column($array['actions'], 'text') === ['Resume']
            && str_ends_with($array['actions'][0]['url'], "/lathe/runs/import/{$import->id}");
    });
});

it('imports into int, UUID and ULID keyed tables', function (?string $model, string $type) {
    $model ??= Product::class;

    if ($type !== 'int') {
        Schema::create((new $model)->getTable(), function (Blueprint $table) use ($type) {
            $table->{$type}('id')->primary();
            $table->string('name')->unique();
            $table->integer('size')->nullable();
            $table->timestamps();
        });
        $model::create(['name' => 'Old', 'size' => 1]);
        $import = uploadedImport("Name,Size\nOld,2\nNew,3\n", [0 => 'name', 1 => 'size'], $model);
        $columns = [ImportColumn::make('name'), ImportColumn::make('size')->numeric()];
        $key = 'name';
    } else {
        $import = uploadedImport(fixtureCsv('products.csv'), [0 => 'name', 1 => 'sku', 2 => 'price']);
        $columns = [];
        $key = 'sku';
    }

    $action = $type === 'int' ? ImportProducts::make() : ImportAction::make()->withModel($model)->withColumns(...$columns)->upsertBy($key);
    $action->start($import, 'import', adminContext());

    expect(importCounts($import)['failed_rows'])->toBe(0)
        ->and(importCounts($import)['updated_rows'])->toBe(1)
        ->and(importCounts($import)['created_rows'])->toBe($type === 'int' ? 2 : 1);
})->with([
    'int' => [null, 'int'],
    'uuid' => [UuidThing::class, 'uuid'],
    'ulid' => [UlidThing::class, 'ulid'],
]);

it('prunes an import older than prune_after_hours with its rows and files', function () use ($productsMap) {
    config(['lathe.imports.prune_after_hours' => 24]);
    $this->travel(-25)->hours();
    $old = uploadedImport(fixtureCsv('products-bad.csv'), $productsMap);
    ImportProducts::make()->start($old, 'dry_run', adminContext());
    $this->travelBack();
    $this->travel(-23)->hours();
    $recent = uploadedImport(fixtureCsv('products-bad.csv'), $productsMap);
    ImportProducts::make()->start($recent, 'dry_run', adminContext());
    $this->travelBack();
    $old->refresh();
    $recent->refresh();

    $this->artisan('model:prune', ['--model' => [Import::class]])->assertSuccessful();

    expect(Import::find($old->id))->toBeNull()
        ->and(FailedImportRow::where('import_id', $old->id)->count())->toBe(0)
        ->and(Storage::disk('local')->exists($old->file_name))->toBeFalse()
        ->and(Storage::disk('local')->exists((string) $old->error_file))->toBeFalse()
        ->and(Import::find($recent->id))->not->toBeNull()
        ->and(FailedImportRow::where('import_id', $recent->id)->count())->toBe(1)
        ->and(Storage::disk('local')->exists($recent->file_name))->toBeTrue()
        ->and(Storage::disk('local')->exists((string) $recent->error_file))->toBeTrue();
});

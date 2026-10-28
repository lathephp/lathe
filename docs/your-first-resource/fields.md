---
title: Fields
description: Define the columns and inputs of a Resource with fields, from the built-in types to computed, dependent and custom fields.
order: 3
---

# Fields

A field is one value of a record. The same field draws a column on the index, a row on the detail page and an input on the create and edit forms. A Resource returns its fields from `fields()`:

```php
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

public function fields(LatheRequest $request): array
{
    return [
        Field::id(),
        Field::text('Name')->required()->sortable(),
        Field::email('Email')->required(),
        Field::currency('Price', 'price_cents')->asMinorUnits(),
    ];
}
```

Every field is built by a static factory on `Field`. The first argument is the label. The second is the attribute to read. When you leave it out, Lathe uses the snake case of the label, so `Field::text('Reorder level')` reads `reorder_level`. A field is frozen once `fields()` returns it, and a modifier called later throws.

> Note: Relationship fields (`belongsTo`, `hasMany`, `morphTo` and the rest) are covered in [Relationships](relationships.md). Rules and messages are covered in [Validation](validation.md).

## Where a field shows

A field shows in six places: the index, the detail page, the create form, the update form, the preview modal and the peek popover. Each has a pair of methods that take a boolean or a closure.

```php
Field::text('Notes')->hideFromIndex();
Field::text('Internal code')->showOnDetail()->hideWhenCreating();
Field::text('Summary')->showWhenPeeking();
Field::text('Reference')->onlyOnForms();
```

| Show | Hide | Shortcut |
|---|---|---|
| `showOnIndex()` | `hideFromIndex()` | `onlyOnIndex()` |
| `showOnDetail()` | `hideFromDetail()` | `onlyOnDetail()` |
| `showOnCreating()` | `hideWhenCreating()` | `onlyOnForms()`, `exceptOnForms()` |
| `showOnUpdating()` | `hideWhenUpdating()` | |
| `showOnPreview()` | `hideFromPreview()` | `onlyOnPreview()` |
| `showWhenPeeking()` | `hideWhenPeeking()` | |

`canSee()` hides a field from users who fail a check, and `canSeeWhen()` does the same with a policy ability:

```php
Field::make('Created', 'created_at')
    ->onlyOnDetail()
    ->canSee(fn (LatheRequest $request) => $request->user()->isAdmin());

Field::text('Salary')->canSeeWhen('viewSalary');
```

## Computed fields

Pass a closure as the attribute to show a value that is not a column. The closure takes the model. A computed field is read-only, so it is off the forms and cannot be sorted.

```php
Field::text('Full name', fn ($user) => "{$user->first_name} {$user->last_name}");
```

`Field::make($label, $attribute, $resolver)` builds a plain field. Its third argument formats the value for display:

```php
Field::make('Placed', 'placed_at', fn ($order) => $order->placed_at->toDayDateTimeString());
```

## Default values

`default()` sets the starting value of the create form. A closure takes the request.

```php
Field::select('Status')->options(['draft', 'published'])->default('draft');
Field::text('Owner')->default(fn (LatheRequest $request) => $request->user()->name);
```

A boolean field starts as `false`.

## Placeholder, help and autocomplete

```php
Field::text('Handle')
    ->placeholder('@name')
    ->help('Letters and numbers only.')
    ->autocomplete('username');

Field::text('Coupon')->withoutAutoCompletion();
```

`autocomplete()` takes an HTML token, a list of tokens, `true` (`on`) or `false` (`off`). `help()` takes a closure too. Lathe strips the markup from help text and shows it as plain text.

## Hydration

Four hooks change how a value travels. Use them when a cast is not enough.

| Method | What it changes |
|---|---|
| `resolveUsing(fn ($record, $attribute))` | How the value is read from the record. |
| `displayUsing(fn ($value, $attribute))` | The text on the index, detail, preview and peek. Forms still get the raw value. |
| `fillUsing(fn ($request, $model, $attribute, $requestAttribute))` | How the request value is written to the model. It may return a closure that runs after the save. |
| `nullValues(array\|Closure $values)` | Which submitted values save as `null`. |

```php
Field::text('Name')->displayUsing(fn ($value) => strtoupper($value));
Field::text('Slug')->fillUsing(fn ($request, $model, $attribute) => $model->{$attribute} = str($request->input($attribute))->slug());
```

`Field::nullValues()` sets the inputs that save as `null` on a nullable field. The default is `['']`. Lathe compares each submitted value with `===`. You can pass a list, or a closure that gets the value and returns a boolean:

```php
Field::number('Discount')->nullable()->nullValues(['', 0]);
Field::text('Nickname')->nullable()->nullValues(fn ($value) => trim((string) $value) === '');
```

Lathe skips `fillUsing` and the normal write when the field is immutable and the record exists.

## Sorting

`sortable()` makes the index column sortable. A computed field cannot be sorted. `sortableUriKey()` changes the key the URL uses.

```php
Field::text('Name')->sortable();
Field::text('Title')->sortable()->sortableUriKey('by-title');
```

## Validation messages and attribute names

The rules of a field are covered in [Validation](validation.md). Two methods change the wording of an error:

```php
Field::email('Email')
    ->rules('required', 'email')
    ->messages(['email' => 'Enter a real email address.'])
    ->validationAttribute('work email');
```

## Layout modifiers

These methods change how a field sits on a page.

```php
Field::textarea('Bio')->stacked();
Field::code('Config')->fullWidth();
Field::text('City')->columnSpan(2);
Field::number('Total')->textAlign('right')->indexWidth(120);
Field::text('Note')->wrapping();
```

| Method | Effect |
|---|---|
| `stacked()` | Puts the label above the value. |
| `fullWidth()` | Spans the whole row. |
| `columnSpan(?int $span)` | Spans that many columns of a grid section. |
| `textAlign('left'\|'center'\|'right')` | Aligns the value. |
| `indexWidth(int $pixels)` | Sets the width of the index column. |
| `wrapping()` | Wraps a long index cell onto at most five lines. The default is one line with an ellipsis. |
| `borderless()`, `compact()` | Removes the border, or tightens the spacing. |
| `withSection('attribute')` | Puts the field in the section with that attribute. |
| `helpWidth(int\|string $width)` | Sets the width of the help text. |

## Inline editing on the index

`inline()` turns a cell into an input, so a user changes a value without opening the form.

```php
Field::text('Name')->inline();
Field::select('Status')->options(['draft', 'published'])->inline();
```

Boolean, select, multi-select, text, textarea, number, belongsTo and morphTo fields support it. Any other type throws when `fields()` builds. A computed field, a read-only field and a field that is not on the index stay plain.

A user needs both the `update` and the `inlineEdit` abilities on the record. The server runs the field's rules before it saves. A field that is hidden or read-only for that record answers 403.

## Field types

The factories below cover every built-in type. Each section lists the options that type adds.

### Text, textarea, email, URL and color

```php
Field::text('Name')->maxlength(120)->copyable()->suggestions(['Alice', 'Bob']);
Field::textarea('Bio')->rows(6)->alwaysShow();
Field::email();
Field::url('Website');
Field::color('Brand colour');
```

`Field::text()` also has `enforceMaxlength()`, which stops typing past the limit. The server rule applies either way. A textarea collapses a value over ten lines. `alwaysShow()` keeps it open, and `shouldShow(Closure)` decides per record.

`asHtml()` shows the value as HTML after Lathe cleans it, and `asEncodedHtml()` shows the markup as text. `translatable(['en', 'fr'])` stores one value per locale in a JSON column with an `array` cast.

`Field::email()` sets the `email` autocomplete token. `Field::url()` renders a link on the index and the detail page.

### Number and currency

```php
Field::number('Reorder level')->min(0)->max(10)->step(1);
Field::currency('Price', 'price_cents')->currencyCode('USD')->asMinorUnits();
```

Number and currency fields add the `numeric`, `min` and `max` rules on the server. A currency field reads the default code from the `currency` key of `config/lathe.php`, uses the app locale and takes its step from the currency. `asMinorUnits()` means the column holds cents, while the form shows `12.50`. `locale()`, `symbol()` and `asMajorUnits()` adjust the display.

### Date and date time

```php
Field::date('Born');
Field::dateTime('Placed', 'placed_at')->min('2020-01-01');
```

A date field needs a `date` cast on the model. A date time field shows the user's time zone and stores UTC. `min()`, `max()` and `step()` become input attributes and the `after_or_equal` and `before_or_equal` rules.

### Boolean and boolean group

```php
Field::boolean('Active');
Field::boolean('Archived')->values('yes', 'no')->asCheckbox();
Field::booleanGroup('Permissions')->options(['read', 'write'])->hideFalseValues();
```

A boolean field is a switch by default. `values($true, $false)`, `trueValue()` and `falseValue()` set what the column stores. A nullable boolean keeps `null` as a third state. A boolean group stores a JSON object such as `{"read": true}`. `hideFalseValues()` and `hideTrueValues()` trim the display, and `noValueText()` sets the text for an empty group.

### Select, multi-select, country and timezone

```php
Field::select('Status')->options(['draft', 'published'])->default('draft');
Field::select('Role')->options(Role::class)->searchable();
Field::multiSelect('Skills')->options(['php', 'react'])->withSelectedTags();
Field::country();
Field::timezone();
```

`options()` takes a list, a map of value to label, a closure, or a backed enum class. An enum may define `label()`, `color()` and `icon()`, and Lathe uses them. Wrap options in `OptionGroup::make($label, $options)` to group them. A select with more than 1,000 options throws, so use a relationship field, or `remoteOptions(Closure $query, string $column = 'name', ?int $inlineUpTo = null)`, for long lists.

`displayUsingLabels()` shows the label instead of the value. `searchable()` adds a search box. The submitted value must be one of the options. `Field::country()` stores the two-letter ISO code and needs the `intl` extension.

`meter(except: [...])` shows a select as a status meter. Its options in declared order, minus those in `except`, are the ticks. A value fills the ticks up to its place, and a value in `except` shows every tick empty. A meter needs 2 to 5 ticks and inline options, or `fields()` throws a `LogicException` that names the field. A select without `meter()` keeps the badge.

```php
Field::select('Status')->options(['pending', 'paid', 'shipped', 'cancelled'])->meter(except: ['cancelled']);
```

A select can depend on another field. See [Dependent fields](#dependent-fields).

### Badge and status

```php
Field::badge('Stage')
    ->map(['new' => 'info', 'won' => 'success', 'lost' => 'danger'])
    ->labels(['new' => 'Fresh'])
    ->withIcons();
Field::status('Import')->loadingWhen(['running'])->failedWhen(['failed']);
```

A badge maps each value to a type: `info`, `success`, `warning` or `danger`. An unmapped value is `info`. Comparison is strict, so `0.4` does not match `0`. Label order is `labelUsing()`, then `nullLabel()`, then `labels()`, then the enum label, then the humanized value.

`BadgeField::addTypes()` adds custom type names to the ones already set, where `types()` replaces them. A badge of a custom type renders `data-lathe-badge-type="<name>"`, and your CSS styles it. See [Frontend](../extending/frontend.md).

```php
Field::badge('Temperature')
    ->addTypes(['hot', 'cold'])
    ->map(['summer' => 'hot', 'winter' => 'cold']);
```

A status field shows a spinner for the `loadingWhen` values, a failed state for the `failedWhen` values and a finished state otherwise. Badge, status, icon, sparkline, stack and line fields are index and detail only.

### Icon and sparkline

```php
Field::icon('Plan')->map(['free' => 'user', 'team' => 'users'])->colors(['team' => 'success'])->fallback('circle');
Field::sparkline('Orders')->data(fn ($records) => $records->mapWithKeys(fn ($r) => [$r->getKey() => [1, 4, 2]])->all())->asBarChart();
```

Icon names are Lucide names in kebab case. `data()` takes a closure that runs once per page and returns a series per record key, a trend widget, or an iterable that every row shares. `height()` and `width()` size the chart in pixels.

### ID and hidden

```php
Field::id();
Field::hidden('Source', 'source')->default('web');
```

`Field::id()` is sortable and off the forms. A record key travels as a string, so large and UUID keys stay exact. A hidden field submits its value and shows no control.

### Heading

```php
Field::heading('Billing details');
```

A heading is a line of text between fields on a form. The label is the content, and Lathe cleans any HTML in it.

### Password and password confirmation

```php
Field::password()->onlyOnForms()->creationRules('required', 'confirmed')->updateRules('nullable', 'confirmed');
Field::passwordConfirmation('Confirm password');
```

Lathe hashes a non-empty password on save. An empty value keeps the stored hash, and the field never sends the hash to the browser. The confirmation field is never written to the model. Pair it with the `confirmed` rule on the password field.

### Token

```php
Field::token('API key')->mask(4);
```

`mask(int $start, ?int $length = null, string $char = '*')` hides part of the value on the index, detail and preview. The form carries the raw value in a masked input with a reveal toggle and a copy button.

### Code

```php
Field::code('Settings')->json();
Field::code('Snippet')->language('php')->height(300);
```

`json()` sets the language to JSON and stores the decoded value in an `array` or `json` cast. Invalid JSON is a validation error. `height()` takes pixels, `fullHeight()` fills the container and `autoHeight()` grows with the content. `editorOptions(array $options)` merges into the editor config.

### Markdown

```php
Field::markdown('Body')->preset('commonmark')->withFiles();
```

A markdown field stores Markdown text. Presets are `default` (CommonMark with tables, strike-through, autolinks and task lists), `commonmark` and `zero` (paragraphs, breaks and links). Lathe strips raw HTML and unsafe links before it shows the preview. `withFiles()` lets the editor upload images. The disk comes from the field, or from `storage_disk` in [Configuration](../getting-started/configuration.md), and never from the request.

### Tiptap

```php
Field::tiptap('Body')->withFiles();
```

A Tiptap field stores HTML. Lathe cleans it on save and again on display, and removes anything outside a fixed allow-list of tags and attributes.

### Slug

```php
Field::slug('Slug')->from('title')->separator('-')->showCustomizeButton();
```

On a create form the slug follows the source field until the user types in it. The server saves `Str::slug()` of the value. With `showCustomizeButton()` an update form locks the slug until the user clicks Customize.

### KeyValue

```php
Field::keyValue('Meta')
    ->keyLabel('Header')
    ->valueLabel('Content')
    ->actionText('Add header')
    ->disableAddingRows();
```

A key-value field stores a JSON object. Keys must be unique and filled. `disableDeletingRows()` and `noValueText()` are also available.

`KeyValueField::disableEditingKeys()` locks the keys in the form. Locked keys still save. It takes a boolean or a closure that gets the request:

```php
Field::keyValue('Settings')->disableEditingKeys(fn (LatheRequest $request) => ! $request->user()->isAdmin());
```

### Stack and line

```php
Field::stack('Customer', 'customer', [
    Field::line('Name')->asHeading(),
    Field::line('Email')->asSmall(),
]);
```

A stack puts several lines in one index cell. Each line reads the same record. A line has four styles: `asHeading()`, `asSubTitle()`, `asSmall()` and `asBase()`. A closure in the list of lines becomes a line of its own.

`LineField::extraClasses()` adds CSS class names to the line as given. Your own CSS supplies any class Lathe does not ship. It takes a string or a list:

```php
Field::line('Stage')->asSmall()->extraClasses(['uppercase', 'tracking-wide']);
```

### Repeater

```php
Field::repeater('Specifications')
    ->fields([
        Field::text('Name')->required(),
        Field::text('Value')->required(),
    ])
    ->min(1)
    ->max(20)
    ->sortable()
    ->confirmRemoval()
    ->addButtonText('Add spec')
    ->rowLabel('name', 'Spec #{{index}}');
```

A repeater edits a list of rows that share the same sub-fields. It is hidden from the index.

Storage:

- `asJson()` is the default. The rows save as an array in the column, which needs an `array` or `json` cast.
- `asHasMany('items', 'sku', 'position')` saves the rows as records of a relationship. The second argument matches existing rows, and the third writes the row order.
- `simple($field)` takes one sub-field and stores a flat list.
- `repeatables([...])` builds blocks of different shapes. Each row has a `type` and the fields of that block.

Row controls: `sortable()`, `min()`, `max()`, `collapsible()`, `collapsed()`, `cloneable()`, `itemNumbers()`, `addable()`, `deletable()`, `confirmRemoval()`, `defaultRows()`, `grid()`, `rowLabel()`, `addButtonText()` and `distinct()`. A submission with more rows than `max()` returns 422.

`RepeaterField::beforeFill()` shapes the rows once after they load, before they reach the form. Its sibling `beforeSave()` shapes them before they are stored. Each closure takes an array of rows and returns one:

```php
Field::repeater('Links')
    ->fields([Field::text('Label'), Field::url('Url')])
    ->beforeFill(fn (array $rows): array => collect($rows)->sortBy('label')->values()->all());
```

### File, image, audio and avatar

```php
Field::file('Contract')->disk('s3')->path('contracts')->acceptedTypes('pdf')->maxSize(10);
Field::image('Cover')->rounded()->aspect('16/9')->maxWidth(300);
Field::audio('Podcast')->preload('metadata');
Field::avatar('Avatar')->storeOriginalName('avatar_name')->storeSize('avatar_size');
```

The browser uploads a file in chunks and the form carries a reference to it. Lathe takes the disk and the directory from the field, never from the request. It checks the size and the real content type again when the upload completes. `disk()`, `path()`, `maxSize($mb)`, `acceptedTypes()`, `storeAs()`, `deletable()`, `prunable()`, `disableDownload()`, `storeOriginalName()` and `storeSize()` configure a file. The `store`, `delete`, `preview`, `thumbnail` and `download` methods take a closure that replaces the default. A new file replaces the old one, and Lathe deletes the old file after the save succeeds.

`Field::avatar()` is an image field that is round, centered and offers no download.

### Gravatar and UI-Avatar

```php
Field::gravatar('Avatar', 'email');
Field::uiAvatar('Avatar', 'name')->bold()->backgroundColor('#0ea5e9');
```

Both fields are display only. A UI-Avatar field draws the initials of the source attribute and makes no network call.

A Gravatar field hashes the lowercased email with SHA-256 on the server and builds the image URL. The server makes no call to Gravatar. Instead the browser sends an email hash to Gravatar when it loads the picture, so Gravatar sees the hash and the visitor's address. With no email, the field shows initials.

Lathe adds `https://www.gravatar.com` to the `img-src` of the content security policy only when a registered Resource has a Gravatar field. Without one, the policy stays strict.

### Tag and relationship fields

`Field::tag()` and the relationship factories belong to [Relationships](relationships.md).

## Field utility injection

Most closures on a field are called by parameter name first, then by type, then through the container. Name the parameters you need, in any order.

| Parameter name | Value |
|---|---|
| `record`, `resource`, `model` | The model. It is `null` on create. |
| `value`, `state` | The field value. |
| `attribute`, `attr`, `column` | The resolved attribute. |
| `request` | The `LatheRequest`. |
| `field`, `component`, `self` | The field. |
| `context`, `operation` | The `FieldContext`. |

A type hint works too: `Model`, `LatheRequest`, `FieldContext` or the field class. Any other class comes from the container. Lathe reads each closure once per request, not once per row.

```php
Field::text('Label')->default(fn (LatheRequest $request) => $request->user()->name);
Field::text('Code')->readonly(fn ($record) => $record !== null);
```

`readonly()`, `required()`, `immutable()` and `default()` all take closures this way.

## Readonly, immutable, required and nullable

```php
Field::text('SKU')->immutable();
Field::text('Ref')->readonly();
Field::text('Name')->required();
Field::text('Nickname')->nullable();
```

- `readonly()` shows a disabled input. Lathe skips the field on save and in validation.
- `immutable()` allows the value on create. On update the input is read-only, and Lathe does not fill it.
- `required()` adds the `required` rule and marks the label.
- `nullable()` adds the `nullable` rule and keeps `null`.

## Dependent fields

A field can react to other fields on the form. The conditions run in the browser, and the server checks them again on save, so a hidden field is not filled and its rules are skipped.

```php
use Lathe\Field\Condition;

$company = Condition::field('type')->eq('company');

Field::text('Company name')->visibleWhen($company)->requiredWhen($company);
Field::text('Reference')->readonlyWhen(Condition::field('status')->in(['paid', 'shipped']));
```

A condition compares one field with `eq()`, `neq()`, `in()`, `notIn()`, `gt()`, `gte()`, `lt()`, `lte()`, `filled()` or `blank()`. `Condition::allOf()` and `Condition::anyOf()` combine them.

`optionsFrom($attribute, $source)` changes the options of a select with the value of another field. An array maps each value to a list. A closure runs on the server and returns at most 100 options.

```php
Field::select('State')->nullable()->optionsFrom('country', [
    'US' => ['California', 'New York', 'Texas'],
    'BD' => ['Chittagong', 'Dhaka', 'Khulna'],
]);
```

For anything else, `dependsOn(array|string $attributes, Closure $callback)` asks the server. The browser waits 300 ms after a change, then calls the closure with a patch, the request and the form data. `dependsOnCreating()` and `dependsOnUpdating()` limit it to one form.

```php
Field::number('Total')->dependsOn(['quantity', 'price'], function (FieldPatch $field, LatheRequest $request, FormData $formData) {
    $field->setValue($formData->get('quantity') * $formData->get('price'));
});
```

A patch has `show()`, `hide()`, `setValue()`, `options()`, `readonly()` and `required()`. `FormData` has `get()`, `has()`, `boolean()`, `file()`, `all()`, `resource()` and `request()`. A patch never overwrites a value the user typed. A cascade stops at depth five, and a closure that throws keeps the previous state and shows a retry toast.

## Filterable fields

`filterable()` builds a filter for the field and adds it to the index.

```php
Field::currency('Total', 'total_cents')->asMinorUnits()->filterable(scale: 100);
Field::select('Status')->options(['draft', 'published'])->filterable();
```

A closure with the signature `fn (LatheRequest $request, Builder $query, mixed $value, ?string $attribute)` applies the filter itself, which a computed field needs. Pass `options` to make it a select. See [Filters](../querying/filters.md). To total a column, see [Column summaries](column-summaries.md).

## Extending fields

`php artisan lathe:field StarRating` writes a field class. `--extends` names the type to extend, and `--custom` also writes a React component.

```php
use Lathe\Field\Field;

class StarRating extends Field
{
    protected string $component = 'star-rating';
}
```

Use it like any other field: `StarRating::make('Rating')->rules('nullable', 'integer', 'between:1,5')`. Add a React component with `--custom` when the browser needs its own input. See [Frontend](../extending/frontend.md) and [Extension API](../extending/extension-api.md).

## What you see

Each field you add appears as a column on the index, a row on the detail page and an input on the forms, unless you hid it. Computed fields show on the index and detail only. A dependent field appears or hides as the user changes the form, and a Gravatar field draws a round picture or initials.

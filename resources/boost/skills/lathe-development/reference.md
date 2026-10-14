# Lathe reference

Deep reference for [SKILL.md](SKILL.md): field modifiers, relation fields, actions, filters, lenses, widgets, dashboards and the testing recipe. When a method here is not enough, read the class in `vendor/lathe/lathe/src`.

## Field modifiers

Chain these on any field. A field is frozen once `fields()` returns it, so call every modifier inside `fields()`.

| Group | Methods |
|---|---|
| Rules | `rules(...)` (variadic), `creationRules(...)`, `updateRules(...)`, `required()`, `nullable()`, `messages([...])`, `validationAttribute('...')` |
| Values | `default($value or fn)`, `placeholder()`, `help()`, `autocomplete()`, `nullValues([...] or fn)` |
| State | `readonly()`, `immutable()` (writable on create only), `required()` and `default()` also take closures |
| Hydration | `resolveUsing(fn ($record, $attribute))`, `displayUsing(fn ($value, $attribute))`, `fillUsing(fn ($request, $model, $attribute, $requestAttribute))` |
| Index | `sortable()`, `sortableUriKey()`, `inline()`, `filterable()`, `summarize(Summary::sum(), ...)`, `indexWidth()`, `textAlign()`, `wrapping()` |
| Layout | `stacked()`, `fullWidth()`, `columnSpan(2)`, `borderless()`, `compact()`, `withSection('key')`, `helpWidth()` |
| Show | `showOnIndex`/`hideFromIndex`/`onlyOnIndex`, `showOnDetail`/`hideFromDetail`/`onlyOnDetail`, `showOnCreating`/`hideWhenCreating`, `showOnUpdating`/`hideWhenUpdating`, `onlyOnForms`/`exceptOnForms`, `showOnPreview`/`hideFromPreview`, `showWhenPeeking`/`hideWhenPeeking` |
| Access | `canSee(fn (LatheRequest $request) => bool)`, `canSeeWhen('ability')` |
| Conditions | `visibleWhen()`, `requiredWhen()`, `readonlyWhen()`, `optionsFrom($attribute, $source)`, `dependsOn($attributes, fn)` |

```php
<?php

use Lathe\Field\Condition;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

$company = Condition::field('type')->eq('company');

Field::text('Company name')->visibleWhen($company)->requiredWhen($company);
Field::text('Reference')->readonlyWhen(Condition::field('status')->in(['paid', 'shipped']));
Field::text('Salary')->canSeeWhen('viewSalary');
Field::text('Owner')->default(fn (LatheRequest $request) => $request->user()->name);
Field::text('Slug')->fillUsing(fn ($request, $model, $attribute) => $model->{$attribute} = str($request->input($attribute))->slug());
```

Conditions compare one field with `eq`, `neq`, `in`, `notIn`, `gt`, `gte`, `lt`, `lte`, `filled` or `blank`. `Condition::allOf()` and `Condition::anyOf()` combine them. A hidden field is skipped on save and in validation.

**`dependsOn` is the server-side fallback.** The browser waits 300 ms after a change and calls the closure with a `Lathe\Field\FieldPatch`, the request and a `Lathe\Field\FormData`. A patch has `show()`, `hide()`, `setValue()`, `options()`, `readonly()` and `required()`. A patch never overwrites a value the user typed.

**Closure parameters** are resolved by name first (`record`, `model`, `value`, `attribute`, `request`, `field`, `context`), then by type (`Model`, `LatheRequest`, `FieldContext`, the field class), then through the container.

**Fields that are index and detail only:** badge, status, icon, sparkline, stack and line. A repeater, hasMany and the other panel relations are hidden from the index.

### Per-type options

| Field | Options |
|---|---|
| `text` | `maxlength()`, `enforceMaxlength()`, `copyable()`, `suggestions([...])`, `asHtml()`, `translatable(['en', 'fr'])` |
| `textarea` | `rows()`, `alwaysShow()` |
| `number` | `min()`, `max()`, `step()` |
| `currency` | `currencyCode('USD')`, `asMinorUnits()` (the column holds cents) |
| `date`, `dateTime` | `min()`, `max()`, `step()`. A date needs a `date` cast on the model |
| `boolean` | `values($true, $false)`, `asCheckbox()` |
| `select`, `multiSelect` | `options(...)`, `displayUsingLabels()`, `searchable()`, `withSelectedTags()` |
| `badge` | `map([...])`, `labels([...])`, `types([...])`, `addTypes([...])`, `withIcons()` |
| `status` | `loadingWhen([...])`, `failedWhen([...])` |
| `code` | `json()`, `language('php')`, `height()` |
| `markdown`, `tiptap` | `withFiles()`, `preset('commonmark')` |
| `slug` | `from('title')`, `separator()`, `showCustomizeButton()` |
| `keyValue` | `keyLabel()`, `valueLabel()`, `disableAddingRows()`, `disableEditingKeys()` |
| `repeater` | `fields([...])`, `min()`, `max()`, `sortable()`, `asJson()`, `asHasMany('items', 'sku', 'position')`, `beforeFill()`, `beforeSave()` |
| `file`, `image` | `disk()`, `path()`, `acceptedTypes()`, `maxSize()` in MB, `rounded()`, `aspect('16/9')` |
| `password` | hashed on save. An empty value keeps the stored hash |

## Relation fields

The arguments are `(label, relationship method, related Resource class)`. All three after the label are optional.

```php
<?php

Field::belongsTo('Author', 'author', User::class)->searchable()->withSubtitles()->creatable()->modalSize('xl');
Field::hasMany('Comments', 'comments', Comment::class)->collapsible()->perPageOptions([10, 25])->withCount();
Field::belongsToMany('Tags', 'tags', Tag::class);
Field::morphTo('Commentable');
```

- Panel relations (`hasMany`, `belongsToMany`, the morph ones) draw a sub-table on the detail page with its own search, filters, sort and paging. `hasOne` and `morphOne` draw an inline block on the forms, with input keys prefixed by the relation name (`address.city`).
- `hasOneOfMany`, `hasOneThrough` and `hasManyThrough` are read-only.
- `searchable()` turns the picker into a server-side search. The page size comes from the related Resource's `pickerResults()` (25), and a request cannot change it.
- Narrow the choices with a field's `relatableQuery(fn ($request, Builder $query) => ...)`, or a static `relatableTags(LatheRequest, Builder, Field)` on the parent resource, named `relatable` plus the plural related model class.
- `creatable()` needs `create` on the related resource and `add` on the parent. The pivot of a `belongsToMany` is edited with pivot fields and pivot actions.
- A policy method decides who may add, attach and detach. A missing method denies.

## Actions in depth

```php
<?php

namespace App\Lathe\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Http\Requests\LatheRequest;

class GenerateReport extends Action implements ShouldQueue
{
    public static int $chunkCount = 100;

    public ?string $queue = 'reports';

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        // A queued chunk gets all its models in one call.
        return ActionResult::message('Report started.')->withRedirect('/lathe/resources/reports');
    }

    public function failed(\Throwable $e, mixed $model): void
    {
        logger()->warning("Row {$model->getKey()} failed: {$e->getMessage()}");
    }
}
```

**Results.** `ActionResult::message($text)`, `danger($text)`, `redirect($url, $newTab = false)`, `openInNewTab($url)`, `visit($path)`, `download($name, $url)`, `modal($component, $data)`, `emit($event, $data)`, `deleted()`. A URL must be relative, `http` or `https`. A result holds one effect: chain `withMessage()`, `withDangerMessage()`, `withRedirect()`, `withDownload()`, `withModal()`, `withEvent()` or `withDeleted()` to set the second part.

**Where it shows.** Default: the index and the detail page. `onlyOnIndex()`, `onlyOnDetail()`, `exceptOnIndex()`, `exceptOnDetail()`, `showInline()`, `onlyInline()`, `exceptInline()`. Menu look: `group('Communication')`, `icon('mail')`, `description()`, `pinned()`, `size('4xl')`, `fullscreen()`.

**Selection.** `standalone()` runs once with an empty collection. `sole()` needs exactly one row. Otherwise at least one row, or the answer is 422 "Select at least one row." A user who ticks "select all matching" sends the filters, and Lathe reads the rows in chunks.

**Per-model handlers.** Lathe calls `handleForOrders`, then `handleForOrder`, then `handle`, picked from the model's class name. `failed()` follows the same naming. `handleResult(ActionFields, array $results)` combines the results of a multi-row run, and `afterValidation()` adds a check across fields.

**Confirmation.** `confirmable('Send to :count users?')` (or a closure that gets the count), `confirmText()`, `confirmButtonText()`, `cancelButtonText()`, `withoutConfirmation()`. A `DestructiveAction` always confirms. An action with a form always opens its modal.

**Permissions.** `canSee()`, `canSeeWhen('ability')`, `canRun(fn ($request, $model) => bool)`, `canRunWhen(fn ($model) => bool)`. Without a callback Lathe asks the model's policy for `runAction`, and `runDestructiveAction` for a destructive one. A policy without the method denies.

**Queueing.** `ShouldQueue` queues a run. So does a selection over `limits.bulk_inline_max` (500). Lathe splits the selection into chunks of 200 (`$chunkCount`) and runs one job per chunk, each in one transaction. A queued action cannot have a file field. Queued code does not see the request: the job carries the user, guard, locale and time zone. `Lathe\Actions\BatchableAction` adds `then`, `catch` and `finally` callbacks to the batch. `markAsFinished($model)` and `markAsFailed($model, $e)` set the audit row of one model.

**Short actions.** `Action::using('Clear cache', fn (ActionFields $fields, Collection $models) => ActionResult::message('Done.'))`, and `Action::redirect()`, `visit()`, `openInNewTab()`, `downloadURL()`, `modal()`, `danger()`, `noop()`. A closure action cannot be queued.

**Import and export.** Add `ExportAction::make()` (`Lathe\Actions\Export\ExportAction`) or `ImportAction::make()` (`Lathe\Actions\Import\ImportAction`) to `actions()`. The policy needs `export` and `import` methods. `withFormat()` shapes the exported columns, and `ImportAction::withColumns()` declares the import columns.

## Filters in depth

- Base classes: `Lathe\Filters\Filter` (select, `options()` is `label => value`, `multiple()` for a list), `BooleanFilter` (`$value` maps each option to true or false), `DateFilter` (`from`, `to` as `Y-m-d`), `RangeFilter` (`min`, `max`, `integers()` for integer columns).
- `name()` is the headline of the class name, `key()` its kebab form. Override `key()` when one class is used twice with different constructor arguments. `default()` gives the starting value.
- Lathe calls `apply()` only when the user chose a value. A remote filter (searchable, or more than 100 options) does not load its options up front.
- A field with `->filterable()` builds a filter for you. Pass `scale: 100` for a cents column, or a closure `fn (LatheRequest $request, Builder $query, mixed $value, ?string $attribute)` for a computed field.

## Lenses in depth

- A lens is a named view with its own query, columns, filters and sort, at `/lathe/resources/{resource}/lens/{lens}`. `fields()` and the static `query()` are required.
- `query()` receives the resource's scoped query. Keep the model key in any `select()`.
- `static::withFilters($request, $query)` and `static::withOrdering($request, $query, fn ($q) => $q->orderByDesc('revenue'))` apply the active filters and the chosen sort. Calling them inside `query()` makes Lathe skip its own pass, which matters when the query wraps a subquery. The third argument of `withOrdering()` is the default order.
- `countQuery()` can return a cheaper query, a known count, or `null`. `name()` and `uriKey()` can be overridden. `Lens::searchableColumns()` reads its search columns (set with the `Search` attribute).

## Widgets

Generate with `php artisan lathe:widget Name --type=value|trend|partition|progress|table|custom|bar|pie|radar|radial|help`. A widget extends a base class in `src/Widgets/` and returns its data from `calculate(LatheRequest)`. The card appears at once and its number loads in a second request.

```php
<?php

namespace App\Lathe\Widgets;

use App\Models\Order;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ValueWidget;

class Revenue extends ValueWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->sum($request, Order::class, 'total_cents', 'placed_at')
            ->transform(fn (int|float $cents) => round($cents / 100))
            ->dollars();
    }
}
```

| Type | Base class | Helpers and result |
|---|---|---|
| Value | `ValueWidget` | `count`, `sum`, `average`, `min`, `max` return a `ValueResult`: `previous()`, `prefix()`, `suffix()`, `dollars()`, `format('a')`, `transform()`, `allowZeroResult()` |
| Trend | `TrendWidget` | `countByDays`, `sumByHours` and the like. `aggregateQuery(Model::class)->count()->byWeeks(12)->trend()` for other units (`byMinutes`, `byHours`, `byWeeks`, `byYears`). At most 366 points |
| Partition | `PartitionWidget` | `count($request, Model::class, 'column')` groups, largest first. Only the 20 largest groups show |
| Bar, Pie | `BarWidget`, `PieWidget` | the same grouping helpers. `horizontal()`, `stacked()`, `innerRadius(60)`, `withSeries()` |
| Progress | `ProgressWidget` | `count($request, Model::class, fn (Builder $q) => $q->where(...))`, or `new ProgressResult($value, $target)` |
| Radar, Radial | `RadarWidget`, `RadialWidget` | grouping helpers, or `$this->result(...)` by hand |
| Table | `TableWidget` | `columns()` and `rows()` returning `MetricTableRow::make()->title()->subtitle()`. At most 100 rows |
| Help | `HelpWidget` | `HelpWidget::make('Title')->content('Plain text')`. No query |
| Custom | `CustomWidget` | a React component, see [custom-extensions.md](custom-extensions.md) |

Put widgets on a page by returning instances from `widgets()` on a dashboard, a resource or a lens: `Revenue::make()->halfWidth()`. A key may appear once per list.

- **Options on every widget:** `width(CardWidth::Half)`, `fullWidth()`, `halfWidth()`, `doubleHeight()`, `help('...')`, `refreshEvery(60)` (5 seconds at least), `refreshWhenActionsRun()`, `refreshWhenFiltersChange()`, `cacheFor(300)` (0 turns the cache off), `cacheAcrossUsers()`, `cacheTags([...])`, `ranges()`, `precision(2)`, `onlyOnDetail()`, `canSee()`, `canSeeWhen()`.
- **Caching.** Results are cached per user for 60 seconds (`limits.metrics_ttl`). A panel write to a table clears the cards that read it. The aggregate helpers report the tables they read. If you read tables in your own query, list them with `cacheTags()`.
- **Row scoping.** On a resource, `count()`, `sum()` and the other helpers apply the resource's query, but only for the resource's own model. In a hand-written query call `$this->applyResourceScope($query)`, or `requireResourceScope()` to throw instead of counting every row on a dashboard.
- **Authorization.** A widget on a resource needs the policy's `viewWidget($user, string $widgetKey)` to return true, as well as `viewAny` (index) or `view` (detail).
- On the detail page the record key is `$request->query('resourceId')`.

## Dashboards

`php artisan lathe:dashboard Name` writes a class that extends `Lathe\Dashboards\Dashboard` and returns widgets from `widgets()`. `lathe:install` writes `Main`, the home page at `/lathe`. Register others in `registerDashboards()`:

```php
protected function registerDashboards(): void
{
    Lathe::registerDashboards(UserInsights::make()->description('User growth.')->showRefreshButton());
}
```

Name comes from the class (`UserInsights` is "User Insights"), the URL from the kebab class name. Override `name()` and the static `uriKey()` to change them. Two dashboards must not share a key. A dashboard has no host resource, so nothing is scoped there. Scope your own queries.

## Notifications

`LatheNotification::make()->success()->message('Done')->body('...')->action('View', Lathe::path().'/resources/orders')` goes through Laravel's `database` channel. Other states: `error()`, `warning()`, `info()`. The user model needs `Notifiable`. The bell navigates with the URL as given, so a relative URL resolves against the current page and 404s. Always build it with `Lathe::path()`.

## Testing recipe

Lathe ships a test kit in `src/Testing` that sends the same JSON requests the pages send, so a test runs the route, policy, query, validation and response together. `LatheTest::resource()` takes a Resource class or its URI key (`posts` for `Post`, `order-items` for `OrderItem`) and returns a tester. Each method returns Laravel's `TestResponse`.

```php
<?php

use App\Lathe\Resources\Post;
use App\Models\Post as PostModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Lathe\Testing\LatheTest;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('viewLathe', fn () => true);

    LatheTest::actingAs(User::factory()->create());
});

it('lists the posts', function () {
    PostModel::factory()->count(3)->create();

    LatheTest::resource(Post::class)
        ->index()
        ->assertOk()
        ->assertJsonCount(3, 'table.rows');
});

it('refuses a post without a title', function () {
    LatheTest::resource(Post::class)
        ->store([])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('runs an action on selected rows', function () {
    $post = PostModel::factory()->create();

    LatheTest::resource('posts')
        ->runAction('publish-post', ['mode' => 'ids', 'ids' => [$post->getKey()]], ['publish_at' => '2026-01-01'])
        ->assertOk();
});
```

**Two traps.** The `viewLathe` gate applies in **every environment**, `testing` included, and the generated gate admits nobody. Redefine it in the test as above. And `LatheTest::actingAs()` signs in on the panel guard, so use it in place of Laravel's `$this->actingAs()`. A 403 in a test usually means the test user lacks a policy method.

| Tester method | Sends |
|---|---|
| `index(array $query = [])` | the index. The query takes `q`, `sort`, `per_page`, `filter` |
| `store(array $data)`, `update($id, $data)`, `delete($id)` | a create, an update, a delete |
| `forceDelete($id)`, `restore($id)` | a permanent delete, a restore |
| `creationFields()`, `updateFields($id)`, `replicateFields($id)`, `peek($id)`, `preview($id)` | forms and cards |
| `batchDelete($selection)`, `batchForceDelete(...)`, `batchRestore(...)` | a batch. The selection is `['mode' => 'ids', 'ids' => [...]]`, an empty list is a 422 |
| `actions($display)`, `actionForm($action)` | the visible actions and one action's form |
| `runAction($action, $selection, $fields = [])` | a run. With an `UploadedFile` it sends multipart. `postAction()` always sends JSON |
| `widgets($params)`, `widget($key, $params)` | widgets. Params go out as the query string (`range`, `timezone`, `filters`, `resourceId`) |
| `forLens($lens)` | the same calls through a lens |
| `relation($name, $id)` | a `RelationTester`: `index`, `attach($relatedId, $pivot)`, `detach`, `updatePivot`, `pivotFields` |

Dashboards: `LatheTest::dashboards()` and `LatheTest::dashboard('main')->show()`, `->widgets()`, `->widget('revenue', ['range' => 90])`.

The `Lathe\Testing\InteractsWithLathe` trait (add it with `uses()`) gives a test class `registerResources(...$classes)`, `flushLathe()` (call it in `afterEach`), `latheRequest()`, `assertQueryCount($n, fn)` to catch an N+1 and `assertScoped($model, fn)` to check that a multi-tenant query keeps its scope column. To test a plugin on its own, extend `Lathe\Testing\TestCase`. It builds on Orchestra Testbench and defines the gate to admit every signed-in user.

Assert the thing a user sees: a row count with `assertJsonCount(n, 'table.rows')`, a validation key with `assertJsonValidationErrors`, a result with `assertJsonPath('result.message', 'Published.')`, and the database after the call. A row hidden by `indexQuery()` is missing from `table.rows`.

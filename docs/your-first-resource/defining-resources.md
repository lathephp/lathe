---
title: Defining resources
description: Define a Resource for an Eloquent model, register it, and set how its index, forms and sidebar entry behave.
order: 1
---

# Defining resources

A Resource connects one Eloquent model to the panel. It lists the model's fields, and Lathe builds the index table, the detail page and the create and edit forms from them.

## Generate a Resource

Run the generator with the Resource name:

```bash
php artisan lathe:resource Post
```

This writes `app/Lathe/Resources/Post.php` and, when the model has none, `app/Policies/PostPolicy.php`. If the model does not exist yet, the command warns you. Create it before you open the Resource.

> Note: Eight names are reserved and the command refuses them: Action, Dashboard, Field, Filter, Lens, Plugin, Resource and Widget.

The generated class is the smallest working Resource:

```php
<?php

namespace App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class Post extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition->model(\App\Models\Post::class)->title('name');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name'),
        ];
    }
}
```

`configure()` describes the Resource: its model, its title and its settings. `fields()` lists what the user sees and edits. A fuller version adds a title, search columns, a relation and a date:

```php
protected function configure(ResourceDefinition $definition): ResourceDefinition
{
    return $definition
        ->model(\App\Models\Post::class)
        ->title('title')
        ->searchable(['id', 'title']);
}

public function fields(LatheRequest $request): array
{
    return [
        Field::id()->sortable(),
        Field::text('Title')->sortable()->required(),
        Field::textarea('Excerpt')->hideFromIndex(),
        Field::belongsTo('Author'),
        Field::dateTime('Published at', 'published_at')->sortable()->nullable(),
    ];
}
```

See [Fields](fields.md) for every field type and [Relationships](relationships.md) for `Field::belongsTo()` and its siblings.

### Model

Every Resource needs a model. Lathe reads the table, the key and the timestamps from it.

```php
return $definition->model(\App\Models\Post::class);
```

A Resource without a model stops the boot with an error that names the class.

### Title and subtitle

The title is the text that stands for a record in search results, relation pickers and breadcrumbs. The subtitle is a second line. Pass a column name or a closure that receives the model:

```php
return $definition
    ->model(\App\Models\User::class)
    ->title(fn ($user) => $user->first_name.' '.$user->last_name)
    ->subtitle('email');
```

Without a title, Lathe uses the `id` column.

## Meta and additional information

`withMeta()` attaches values to a Resource instance, and `meta()` reads them. Call `withMeta()` in the constructor to set them for every request. Override the static `additionalInformation()` to compute values from the request. Lathe sends both with the Resource's part of every page, as `resource.meta` and `resource.extra`, so a custom page or a Resource plugin can read them:

```php
use Lathe\Http\Requests\LatheRequest;

public function __construct(?Model $model = null)
{
    parent::__construct($model);

    $this->withMeta(['tier' => 'gold']);
}

public static function additionalInformation(LatheRequest $request): array
{
    return ['viewer' => $request->user()?->email];
}
```

`additionalInformation()` returns `[]` by default. In a request handler, `$request->resourceInstance()` gives the Resource the route names, or `null`. `$request->newResource()` gives a new instance and answers 404 when there is none.

## Register Resources

Lathe finds every Resource in `app/Lathe/Resources` the first time the panel needs one. Your `app/Providers/LatheServiceProvider.php`, which `lathe:install` writes, does this in its `registerResources()` method. To register classes by hand instead, call `Lathe::registerResources()`:

```php
use App\Lathe\Resources\Post;
use App\Lathe\Resources\User;
use Lathe\Lathe;

protected function registerResources(): void
{
    Lathe::registerResources(User::class, Post::class);
}
```

To read another folder, call `Lathe::discoverResources()` with the directory and, if it cannot be guessed from `app_path()`, the namespace:

```php
Lathe::discoverResources(app_path('Admin/Resources'), 'App\\Admin\\Resources');
```

On a production server, run `php artisan lathe:cache` when you deploy. It stores the Resource list in one file, so a request does not scan the folder. See [Commands](../getting-started/commands.md).

Two Resources cannot share a URI key. Two Resources can share a model only when all but one call `->canonical(false)`. The canonical Resource is the one that relation fields and search results link to.

## The sidebar entry

Each Resource gets a sidebar entry from its class name. Change the entry with the definition or with attributes. Each setting below has both forms.

```php
use Lathe\Attributes\Resource\Group;
use Lathe\Attributes\Resource\Icon;
use Lathe\Attributes\Resource\NavigationLabel;

#[Group('Content')]
#[Icon('FileText')]
#[NavigationLabel('Blog posts')]
class Post extends Resource
{
    // ...
}
```

- `Group` puts Resources with the same name under one heading.
- `Icon` takes the name of a Lucide icon component, as a string.
- `NavigationLabel` replaces the label, which is the plural of the class name.
- `DisplayInNavigation(false)` hides the entry. The routes stay, so users can still open the Resource by its URL.

To replace the whole entry, override the static `menu()` method. It returns a `MenuItem`, or `null` to keep the default:

```php
use Lathe\Http\Requests\LatheRequest;
use Lathe\Menu\MenuItem;

public static function menu(LatheRequest $request): ?MenuItem
{
    return MenuItem::resource(static::class)->badge('New', 'info');
}
```

## The index table

### Style

The table style is `Default` or `Tight`. Vertical column borders are off by default.

```php
use Lathe\Attributes\Resource\ShowColumnBorders;
use Lathe\Attributes\Resource\TableStyle;
use Lathe\Enums\TableStyle as TableStyleEnum;

#[TableStyle(TableStyleEnum::Tight)]
#[ShowColumnBorders]
class Post extends Resource
{
    // ...
}
```

### Click action

A click on a row opens the detail page. Change that with `ClickAction`, or `->clickAction()` in the definition:

```php
use Lathe\Enums\ClickAction;

return $definition
    ->model(\App\Models\Task::class)
    ->clickAction(ClickAction::Preview);
```

| Case | What a click does |
|---|---|
| `Detail` | Opens the detail page. This is the default. |
| `Edit` | Opens the edit form. |
| `Select` | Ticks the row checkbox. |
| `Preview` | Opens a preview modal on the index page. |
| `Ignore` | Does nothing. |

For `Preview`, override `fieldsForPreview()` to choose the fields in the modal. The same pattern works for `fieldsForIndex()`, `fieldsForDetail()`, `fieldsForCreate()`, `fieldsForUpdate()` and `fieldsForPeeking()`: each one takes a `LatheRequest` and returns a list of fields.

### Pagination

`perPage()` sets the page size choices. The first value is the default, and each value is from 1 to the limit in `config/lathe.php` (100 by default).

```php
return $definition->model(\App\Models\Post::class)->perPage([25, 50, 100]);
```

`paginationType()` takes `PaginationType::Links` (page numbers), `Simple` (previous and next) or `LoadMore`. To set the page size of a relation panel, call `perPageOptions()` on the relation field. See [Relationships](relationships.md).

### Search

`searchable()` names the columns the search box looks in. A plain string matches the start of the value. Pass a `SearchableColumn` object for another strategy, such as `SearchableRelation` to search a related model's column:

```php
use Lathe\Search\SearchableRelation;

return $definition
    ->model(\App\Models\Order::class)
    ->searchable(['number', new SearchableRelation('customer', 'name')])
    ->globallySearchable();
```

`globallySearchable()` also puts the Resource in the panel's global search. `debounce()` sets how many seconds the box waits after the last key before it queries. The default is `0.5`.

To read the columns back, for example in a test, call `Resource::searchableColumns()`. It returns what the definition declared:

```php
$columns = Post::make()->searchableColumns();
// ['id', 'title']
```

### Polling

`polling()` reloads the index every given number of seconds. `showPollingToggle()` adds a switch so users can turn it off.

```php
return $definition->model(\App\Models\Order::class)->polling(10)->showPollingToggle();
```

### Soft deletes, saved tabs and borders

- Lathe detects the `SoftDeletes` trait on the model and adds a filter for trashed rows. Call `softDeletes(false)` to turn that off.
- `savedTabs()` lets users save their current filters and columns as named tabs.
- `borderless()` removes the dividers between fields on forms and detail pages.

## Eager loading

List the relations your fields read in `with()`, so Lathe loads them with the rows instead of one query per row:

```php
return $definition->model(\App\Models\Post::class)->with(['author', 'categories']);
```

## Default values for new records

`Resource::defaultAttributes()` returns the attributes a new model starts with, before any field fills it. A field's own default wins over it for the same key.

```php
use Lathe\Http\Requests\LatheRequest;

public static function defaultAttributes(LatheRequest $request): array
{
    return [
        'status' => 'draft',
        'author_id' => $request->user()?->getKey(),
    ];
}
```

Lathe shows these values in the create form and applies them when a record is stored.

## Replicate a record

Users can copy a record from its detail page. `replicateExcept()` lists the attributes the copy leaves empty. Lathe also leaves out every file column, so a copy never points at the original's files.

```php
public static function replicateExcept(): array
{
    return ['slug', 'published_at'];
}
```

## Lifecycle hooks

Override these static methods to run code around a write. Each receives the `LatheRequest` and the model:

- `beforeCreate()` and `afterCreate()`
- `beforeUpdate()` and `afterUpdate()`
- `beforeDelete()` and `afterDelete()`
- `beforeRestore()` and `afterRestore()`
- `beforeForceDelete()` and `afterForceDelete()`

```php
use Illuminate\Database\Eloquent\Model;

public static function afterCreate(LatheRequest $request, Model $model): void
{
    // Runs after the record is saved.
}
```

The `before` hooks run inside the write's database transaction. To add validation after the field rules pass, see [Validation](validation.md).

## Redirects

After a create or an update, the panel opens the record's detail page. After a delete, it opens the index. Override these static methods to send the user elsewhere:

```php
use Lathe\Resources\Resource;

public static function redirectAfterCreate(LatheRequest $request, Resource $resource): string
{
    return static::uriKey();
}
```

`redirectAfterUpdate()` takes the same arguments. `redirectAfterDelete()` takes only the request.

## Conflicts

When two users edit one record, the second save could overwrite the first. Lathe refuses a save if the record changed since the form loaded, and tells the user. This is on by default. Turn it off with `->trafficCop(false)` or the `TrafficCop` attribute.

## Query hooks

Each hook is a static method that receives the request and a query builder, and returns the builder. Use them to hide rows from users.

| Method | Narrows |
|---|---|
| `indexQuery()` | The index rows, counts and exports. |
| `detailQuery()` | The rows a detail page, delete or restore can reach. |
| `editQuery()` | The rows the edit form and update can reach. |
| `searchQuery()` | Global search. Defaults to `indexQuery()`. |
| `widgetQuery()` | The rows a widget reads. Defaults to `indexQuery()`. |
| `relatableQuery()` | The records a relation picker offers. |
| `replicateQuery()` | The records a copy can start from. |
| `scoutQuery()` | A Laravel Scout search. It receives the Scout builder. |

```php
use Illuminate\Database\Eloquent\Builder;

public static function indexQuery(LatheRequest $request, Builder $query): Builder
{
    return $query->where('published', true);
}
```

Because `searchQuery()` and `widgetQuery()` follow `indexQuery()`, a Resource that hides rows on the index hides them in search and in widgets too. Override either one to widen it.

## Authorization

Policies decide what each user may do with a Resource. A missing policy denies access. See [Policies](../security/policies.md).

## Attributes and the builder

Every setting on this page can be written as a PHP attribute or as a call on the definition. The definition starts from the class attributes, and your calls in `configure()` replace them for the same setting.

```php
use Lathe\Attributes\Resource\TableStyle;
use Lathe\Enums\TableStyle as TableStyleEnum;

#[TableStyle(TableStyleEnum::Tight)]
class Post extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition
            ->model(\App\Models\Post::class)
            ->title('title')
            ->searchable(['id', 'title']);
    }
}
```

Here the table style comes from the attribute and the rest from `configure()`. Pick one style for each Resource. Use `configure()` when you need a closure or a condition, because an attribute argument must be a constant. Attributes are not inherited from a parent Resource class.

These definition methods have no section above:

| Method | Effect |
|---|---|
| `parent(string $parent, string $relationship)` | Makes the Resource a child of a relation, and sets its breadcrumbs. |
| `canonical(bool)` | Whether relation fields and search link to this Resource for its model. |
| `canSee(Closure)` | Hides the Resource from requests the closure refuses. |
| `defaultGroup(string)` | The row grouping applied when the URL names none. |
| `globalSearchResults(int)` | The most rows this Resource adds to global search. |
| `globalSearchSubtitle(string\|Closure)` | The line under a result's title in global search, in place of the subtitle. |
| `scoutSearchResults(int)` | The most keys a Scout search reads. |
| `useScout(?bool)` | Forces Scout on or off. `null` detects it. |
| `maxPrimaryKeySize(int)` | The largest integer key the key search accepts. |
| `pickerResults(int)` | The page size of relation pickers. |

## What you see

After you run `php artisan lathe:resource Post`, add the fields and open `/lathe`. The sidebar has a Posts entry in the group and with the icon you set. It opens an index table with the columns from `fields()`, a search box on the columns you named, and a Create button. A click on a row does what you chose in `clickAction()`.

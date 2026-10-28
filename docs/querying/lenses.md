---
title: Lenses
description: Give a Resource alternate views with their own query, columns, filters, search and widgets.
order: 5
---

# Lenses

A lens is a named view of a Resource with its own query, columns, filters and sort. A "Most valuable users" lens can join the orders table and show a revenue column that the plain users table never loads.

A lens reads through the Resource's `indexQuery()`. It can narrow that query or add to it, but it never shows a row the Resource's index hides.

## Create a lens

```bash
php artisan lathe:lens MostValuableUsers
```

This writes `app/Lathe/Lenses/MostValuableUsers.php`. Add `--plugin=vendor/name` to write it into a plugin package. See [Commands](../getting-started/commands.md).

A lens needs two methods: `fields()` for the columns and a static `query()` for the data.

```php
namespace App\Lathe\Lenses;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;

class MostValuableUsers extends Lens
{
    public function fields(LatheRequest $request): array
    {
        return [
            Field::make('Name')->sortable(),
            Field::make('Revenue', 'revenue')->sortable(),
        ];
    }

    public static function query(LatheRequest $request, Builder $query): Builder
    {
        return $query
            ->select('users.*')
            ->selectRaw('sum(orders.total_cents) / 100 as revenue')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->groupBy('users.id');
    }
}
```

`query()` receives the Resource's scoped query and returns the query to read. Keep the model's key in the `select()`. Lathe needs it to select rows and to run actions on them.

A column is sortable only when its field is marked `sortable()`.

## Register a lens

Return the lens from the Resource's `lenses()` method:

```php
use App\Lathe\Lenses\MostValuableUsers;
use Lathe\Http\Requests\LatheRequest;

public function lenses(LatheRequest $request): array
{
    return [
        new MostValuableUsers,
    ];
}
```

The views rail lists the lens. Each lens opens at `/lathe/resources/{resource}/lens/{lens}`. To link a lens from the sidebar, use `MenuItem::lens('users', 'most-valuable-users')`. See [Menus](../organizing/menus.md).

## Name and key

`name()` is the headline of the class name, and `uriKey()` is its kebab form (`most-valuable-users`). The static `label()` and `singularLabel()` follow the class name too. Override any of them to change what the user sees or what the URL says:

```php
public function name(): string
{
    return 'Top customers';
}

public function uriKey(): string
{
    return 'top-customers';
}
```

## Filters and ordering inside `query()`

Lathe applies the request's filters and sort to the query that `query()` returns. When `query()` wraps a subquery, that is too late. Call the two helpers inside `query()` and Lathe skips its own pass:

```php
public static function query(LatheRequest $request, Builder $query): Builder
{
    return static::withOrdering(
        $request,
        static::withFilters($request, $query->select('users.*')),
        fn (Builder $q) => $q->orderByDesc('revenue'),
    );
}
```

`withFilters()` applies the lens's active filters. `withOrdering()` applies the sort the user chose. Its third argument orders the rows when the user has chosen no sort. A click on a column header replaces that default.

## Count

Lathe derives the row count from `query()`. Override `countQuery()` when a cheaper query gives the same count. Return a query to count, a number you already know, or `null` to keep the default:

```php
public static function countQuery(LatheRequest $request, Builder $query): Builder|int|null
{
    return $query->select('users.id');
}
```

## Search

A lens can have its own search, which the filter bar uses. The `#[Search]` attribute lists the columns. It takes one array:

```php
use Lathe\Attributes\Lens\Search;

#[Search(['name', 'email'])]
class MostValuableUsers extends Lens
{
    // ...
}
```

`Lens::searchableColumns()` returns those columns as a list of strings and `SearchableColumn` objects. A bare string is a prefix match, as on a Resource. Override the method when a lens needs a different strategy per column. It receives no arguments:

```php
use Lathe\Search\LikeSearch;
use Lathe\Search\SearchableExact;

public function searchableColumns(): array
{
    return ['name', LikeSearch::contains('email'), new SearchableExact('sku')];
}
```

The lens search adds the primary key match, as the Resource's search does. See [Search](search.md) for every strategy.

## Filters

A lens has its own filters, separate from the Resource's. Return them from `filters()`:

```php
public function filters(LatheRequest $request): array
{
    return [new UserType];
}
```

See [Filters](filters.md). A lens filter that fails `canSee()` is hidden and refused if applied.

## Actions

A lens does not declare actions. Its rows run the Resource's actions through the same endpoints, so the user ticks rows and picks an action as on the index. See [Actions](../taking-action/actions.md).

## Widgets

Return cards from `widgets()` to show them above the lens table:

```php
public function widgets(LatheRequest $request): array
{
    return [new TotalRevenue];
}
```

See [Widgets](../organizing/widgets.md).

## Pagination

A lens uses the Resource's rows-per-page options. The `#[PerPage]` attribute sets its own. The first value is the default:

```php
use Lathe\Attributes\Lens\PerPage;

#[PerPage([10, 25, 50])]
class MostValuableUsers extends Lens
{
    // ...
}
```

## Polling

`#[Polling]` reloads the lens table every so many seconds. Without an argument, it reloads every 15 seconds. `#[ShowPollingToggle]` adds a toolbar button that starts and stops it.

```php
use Lathe\Attributes\Lens\Polling;
use Lathe\Attributes\Lens\ShowPollingToggle;

#[Polling(10)]
#[ShowPollingToggle]
class LiveOrders extends Lens
{
    // ...
}
```

## Who can see a lens

`canSee()` hides a lens from some users. Every lens endpoint answers 403 for a lens the user may not see.

```php
public function lenses(LatheRequest $request): array
{
    return [
        (new MostValuableUsers)->canSee(fn ($request) => $request->user()->isAdmin()),
    ];
}
```

A policy can also decide with a `viewLens` method that receives the user and the lens key. A policy without that method allows every lens. See [Policies](../security/policies.md).

## What you see

The views rail lists the lenses below the Resource's tabs and saved views, and shows no count for them. Below 1100 px it folds into the Views menu. Choosing a lens opens its table at the lens address, with its own columns, its own [filter bar](filter-bar.md) and cards, and the same [workspace](workspace.md) keys. Sorting, filtering and paging work as on the index, and the rows are always a subset of what the index shows.

---
title: Filters
description: Write filters, register them on a Resource, and let fields generate filters for you.
order: 2
---

# Filters

A filter narrows the rows of a Resource's table. The user picks a value in the filter panel, and Lathe applies the filter's `apply()` method to the query.

Before you write a filter class, check whether a [filterable field](#filterable-fields) covers the need. Most columns do not need custom code.

## Create a filter

`lathe:filter` writes a filter class to `app/Lathe/Filters`:

```bash
php artisan lathe:filter UserType
php artisan lathe:filter PriceRange --type=range
```

`--type` is `select` (the default), `boolean`, `date` or `range`. `--custom` writes a filter that draws its own React component (see [Custom components](#custom-components)).

Every filter extends `Lathe\Filters\Filter` or one of its subclasses and implements `apply()`:

```php
public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
```

Lathe calls `apply()` only when the user has chosen a value. The shape of `$value` depends on the filter type.

## Select filters

A select filter shows one dropdown. `options()` returns the choices as `label => value`. Lathe passes the chosen value to `apply()`.

```php
namespace App\Lathe\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;

class UserType extends Filter
{
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where($query->qualifyColumn('type'), $value);
    }

    public function options(LatheRequest $request): array
    {
        return [
            'Administrator' => 'admin',
            'Editor' => 'editor',
            'Subscriber' => 'subscriber',
        ];
    }
}
```

Use `qualifyColumn()` so the filter still works when the Resource's `indexQuery()` joins another table.

Call `multiple()` to let the user pick several options. `$value` is then a list.

## Boolean filters

A boolean filter shows a checkbox per option. Extend `Lathe\Filters\BooleanFilter`. `$value` maps each option to `true` or `false`:

```php
use Lathe\Filters\BooleanFilter;

class UserFeatures extends BooleanFilter
{
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value['is_admin'] ?? false) {
            $query->where('is_admin', true);
        }

        if ($value['is_verified'] ?? false) {
            $query->whereNotNull('email_verified_at');
        }

        return $query;
    }

    public function options(LatheRequest $request): array
    {
        return [
            'Admins' => 'is_admin',
            'Verified' => 'is_verified',
        ];
    }
}
```

## Date filters

A date filter picks a range of days. Extend `Lathe\Filters\DateFilter`. `$value` holds `from` and `to` as `Y-m-d` strings. Either side is `null` when the user leaves it empty. Lathe refuses a date such as `2026-02-30`.

```php
use Lathe\Filters\DateFilter;

class CreatedBetween extends DateFilter
{
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value['from'] !== null) {
            $query->whereDate('created_at', '>=', $value['from']);
        }

        if ($value['to'] !== null) {
            $query->whereDate('created_at', '<=', $value['to']);
        }

        return $query;
    }
}
```

## Range filters

A range filter shows a minimum and a maximum input. Extend `Lathe\Filters\RangeFilter`. `$value` holds `min` and `max` as numbers, or `null` for an empty side.

```php
use Lathe\Filters\RangeFilter;

class PriceRange extends RangeFilter
{
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value['min'] !== null) {
            $query->where('price', '>=', $value['min']);
        }

        if ($value['max'] !== null) {
            $query->where('price', '<=', $value['max']);
        }

        return $query;
    }
}
```

For an integer column, call `integers()` on the filter. It refuses `10.5` and `1e20`, which PostgreSQL would reject with a server error.

## Register filters

Return the filters from the Resource's `filters()` method:

```php
use App\Lathe\Filters\CreatedBetween;
use App\Lathe\Filters\UserType;
use Lathe\Http\Requests\LatheRequest;

public function filters(LatheRequest $request): array
{
    return [
        new UserType,
        new CreatedBetween,
    ];
}
```

A [lens](lenses.md) has its own `filters()` method, separate from the Resource's.

## Name, key and default

Lathe builds the filter's title from the class name: `UserType` becomes "User Type". Override `name()` to change it. The `key()` is the kebab-case class name, `user-type`, and appears in the URL. Override it when two instances of one class need different keys.

```php
public function name(): string
{
    return 'Account type';
}

public function key(): string
{
    return 'account-type';
}
```

`default()` returns the value a filter starts with. It returns `null` unless you override it:

```php
public function default(): mixed
{
    return 'admin';
}
```

## Reuse one filter class

A constructor argument lets one class filter several columns. Give each instance its own key:

```php
class TimestampBetween extends DateFilter
{
    public function __construct(private readonly string $column)
    {
        //
    }

    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query
            ->when($value['from'] !== null, fn (Builder $q) => $q->whereDate($this->column, '>=', $value['from']))
            ->when($value['to'] !== null, fn (Builder $q) => $q->whereDate($this->column, '<=', $value['to']));
    }

    public function key(): string
    {
        return 'between-'.$this->column;
    }

    public function name(): string
    {
        return str($this->column)->headline()->toString();
    }
}
```

```php
public function filters(LatheRequest $request): array
{
    return [new TimestampBetween('created_at'), new TimestampBetween('verified_at')];
}
```

## Where and when a filter applies

Filters apply when the user presses Apply in the panel. Call `immediate()` to apply each change at once. `placement()` chooses where the control shows: `toolbar`, `panel` (the default) or `inline`.

```php
(new UserType)->immediate()->placement('toolbar')
```

## Large option lists

A select filter loads its options inline until the list holds more than `limits.filter_remote_threshold` (100) entries. After that, the filter becomes a remote picker. The picker types ahead and loads one page at a time through `searchOptions()`. A Resource changes the threshold with `filterRemoteThreshold()`.

Force a remote picker with `searchable()` or the `#[Searchable]` attribute. A closure decides per request:

```php
use Lathe\Attributes\Filter\Searchable;

#[Searchable]
class Country extends Filter
{
    // ...
}

(new Country)->searchable(fn ($request) => $request->user()->isAdmin())
```

The default `searchOptions()` reads `options()` and matches the start of each label. A remote filter over a big table should override it with a query, and `acceptsRemoteValue()` to refuse a value its column cannot hold. The workbench's `OrderCustomer` filter does both.

## Filterable fields

Any field can generate a filter on the Resource's table. Call `filterable()` on the field:

```php
Field::number('Quantity')->filterable(),

Field::select('Status')
    ->options(['pending', 'paid', 'shipped'])
    ->filterable(),

Field::currency('Total', 'total_cents')->asMinorUnits()->filterable(scale: 100),
```

The signature is `filterable(bool|Closure $filterable = true, ?array $options = null, int $scale = 1)`.

- Without `$options`, the field gets a range filter with a minimum and a maximum.
- With `$options`, it gets a select filter. Pass a list of values, or `value => label`.
- `scale` lets the inputs take the shown amount while the URL and the query keep the stored value. Use `100` for cents.
- A `belongsTo`, `belongsToMany` or `morphTo` field gets a relation filter. It lists related records and goes remote after `limits.filter_remote_threshold` rows. A `morphTo` filter lists its types.

Generated filters have the key `field_{attribute}`.

A closure takes over the query. A computed field needs one, because Lathe cannot guess a column. Without a closure, `filterable()` on a computed field throws a `LogicException`.

```php
use Illuminate\Database\Eloquent\Builder;

Field::text('Full name')->filterable(
    fn (LatheRequest $request, Builder $query, mixed $value, ?string $attribute) => $query
        ->where('first_name', 'like', $value.'%')
        ->orWhere('last_name', 'like', $value.'%'),
)
```

## Who can see a filter

`canSee()` hides a filter from some users. A request that applies a hidden filter answers 422.

```php
public function filters(LatheRequest $request): array
{
    return [
        (new UserType)->canSee(fn ($request) => $request->user()->isAdmin()),
    ];
}
```

## How filters reach the server

The panel writes the filters into the URL in plain form, one parameter per filter key:

```text
?filter[status]=paid
?filter[tag][]=a&filter[tag][]=b
?filter[total][min]=10&filter[total][max]=50
?filter[placed][from]=2026-01-01
```

Lathe checks every value before `apply()` runs. An unknown key, a hidden filter or a value of the wrong shape answers 422. Saved tabs, exports and bulk selections read the same parameters, so a filter works everywhere the table does.

## Custom components

`--custom` writes a filter class and a React component:

```bash
php artisan lathe:filter Sentiment --custom
```

The class returns the name of the component from `component()`. The generated component sits in `resources/js/lathe/filters/Sentiment.tsx` and registers itself with `filter()` from `@lathephp/core`:

```php
public function component(): string
{
    return 'sentiment-filter';
}
```

```tsx
import { filter, type FilterComponentProps } from '@lathephp/core';

function Sentiment({ filter: definition, value, onChange }: FilterComponentProps) {
  return (
    <input
      type="text"
      aria-label={definition.name}
      value={typeof value === 'string' ? value : ''}
      onChange={(event) => {
        onChange(event.target.value);
      }}
    />
  );
}

filter('sentiment-filter', Sentiment);
```

The component receives the filter definition and the current `value`. It calls `onChange` with the new value. A filter that extends `Filter` takes a string. When `options()` lists choices, Lathe refuses any other value before it calls `apply()`.

## What you see

The index toolbar has a Filters button. It opens a panel with one control per filter, such as a dropdown, checkboxes, a date range or a minimum and maximum. After you press Apply, the table shows only the matching rows and the address bar carries the choice, for example `?filter[status]=paid`. Reload the page and the same rows return.

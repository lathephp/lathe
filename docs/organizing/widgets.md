---
title: Widgets
description: Show numbers and charts on dashboards, resource pages and lenses with the eleven widget types and the AggregateQuery builder.
order: 2
---

# Widgets

A widget is one card that shows a number, a chart or a short table. You put widgets on [dashboards](dashboards.md), on a Resource's index and detail pages, and on a lens. Lathe has eleven widget types: value, trend, partition, bar, pie, progress, radar, radial, table, help and custom.

## Define a widget

Generate a widget with `lathe:widget`. The `--type` option picks the base class and defaults to `value`:

```bash
php artisan lathe:widget NewUsers
php artisan lathe:widget UsersPerDay --type=trend
```

Each command writes a class in `app/Lathe/Widgets`. A widget extends one of the base classes in `Lathe\Widgets` and returns its data from `calculate()`:

```php
<?php

namespace App\Lathe\Widgets;

use App\Models\User;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ValueWidget;

class NewUsers extends ValueWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, User::class);
    }
}
```

The panel shows the card at once and fetches its number in a second request, so a slow card does not hold back the others. The card title comes from the class name. Override `name()` to change it.

## Put widgets on a page

A dashboard returns widgets from `widgets()`. So does a Resource and a lens:

```php
use App\Lathe\Widgets\NewUsers;
use App\Lathe\Widgets\UsersPerDay;
use Lathe\Http\Requests\LatheRequest;

public function widgets(LatheRequest $request): array
{
    return [
        NewUsers::make(),
        UsersPerDay::make()->fullWidth(),
    ];
}
```

On a Resource the cards sit above the index table. A key may appear only once in one list.

### Widgets on the detail page

Call `onlyOnDetail()` to show a widget on the Resource detail page and not on the index. The attribute does the same. Inside `calculate()`, `$request->query('resourceId')` holds the key of the record on the page:

```php
use Lathe\Attributes\Widget\OnlyOnDetail;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Widgets\ValueWidget;

#[OnlyOnDetail]
class PostComments extends ValueWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Comment::where('post_id', $request->query('resourceId')));
    }
}
```

A detail page has no filters. So `onlyOnDetail()` and `refreshWhenFiltersChange()` cannot be set on the same widget, and Lathe throws a `LogicException` if you try.

## Authorization

Hide a widget from some users with `canSee()` or `canSeeWhen()`:

```php
NewUsers::make()->canSee(fn ($request) => $request->user()->is_admin),
Revenue::make()->canSeeWhen('viewFinance'),
```

A widget on a Resource needs more. The user must pass the Resource's `viewAny` check for the index, or `view` for the detail page. The Resource's policy must also have a `viewWidget($user, string $widgetKey)` method that returns `true`. A policy without that method hides every widget on its Resource. Dashboards do not use the policy.

## Row scoping

If a Resource limits which rows a user may see, the widgets on that Resource follow the same limit. The aggregate helpers (`count()`, `sum()` and the others) apply it for you, but only to queries over the Resource's own model.

A dashboard has no host Resource, so nothing is scoped there. When you write your own query inside `calculate()`, call the scope yourself:

```php
public function calculate(LatheRequest $request): mixed
{
    $orders = $this->applyResourceScope(Order::query()->where('status', 'paid'));

    return $this->count($request, $orders);
}
```

`applyResourceScope()` changes nothing on a dashboard. Use `requireResourceScope()` when a card must never count rows the user may not see. It throws a `LogicException` on a dashboard instead of counting every row.

## Widget options

Every widget type shares these options.

### Width and height

A page is a grid of twelve columns. The default width is one third, which is four columns. Pick another with `width()` and a `CardWidth` case, or use a shortcut:

```php
use Lathe\Enums\CardWidth;

NewUsers::make()->width(CardWidth::Half),
UsersPerDay::make()->fullWidth(),
Revenue::make()->halfWidth(),
```

| Case | Columns |
|------|---------|
| `CardWidth::Quarter` | 3 |
| `CardWidth::Third` | 4 (default) |
| `CardWidth::Half` | 6 |
| `CardWidth::TwoThirds` | 8 |
| `CardWidth::ThreeQuarters` | 9 |
| `CardWidth::Full` | 12 |

On a narrow screen every card takes the full row. The `#[Width(CardWidth::Half)]` attribute sets the width on the class.

Most cards are 160 pixels tall. `doubleHeight()` makes a card 320 pixels tall. Pie, radar and radial cards are double height already. Table, help and custom cards grow to fit their content.

### Help text

`help()` adds a help icon to the card. The text opens in a tooltip. `helpWidth()` sets the tooltip width in pixels, 250 by default:

```php
Revenue::make()
    ->help('Order totals in dollars, before refunds.')
    ->helpWidth(300),
```

### Chart tooltips

Chart cards show a tooltip when you hover a point. `tooltip()` takes a `Tooltip` object that changes how it looks:

```php
use Lathe\Widgets\Tooltip;

UsersPerDay::make()->tooltip(
    Tooltip::make()->indicator('line')->valueSuffix(' users')->showTotal(),
),
```

`Tooltip` also has `hideLabel()`, `hideIndicator()`, `label()` and `valuePrefix()`.

### Refresh

`refreshEvery()` reloads the card on a timer. The value is whole seconds, and 5 is the smallest:

```php
use Lathe\Attributes\Widget\RefreshEvery;

OrdersPerDay::make()->refreshEvery(60),

#[RefreshEvery(60)]
class OrdersPerDay extends TrendWidget
{
    // ...
}
```

A card can also reload when something happens on the page:

```php
OrdersPerDay::make()->refreshWhenActionsRun(),
OrdersPerDay::make()->refreshWhenFiltersChange(),
```

`refreshWhenActionsRun()` reloads the card after an action finishes. `refreshWhenFiltersChange()` reloads it when the user changes a filter on the index. The attributes `#[RefreshWhenActionsRun]` and `#[RefreshWhenFiltersChange]` do the same. A dashboard with `#[ShowRefreshButton]` reloads all its cards with one click.

### Caching

Lathe caches each card's result for 60 seconds. Change the default with `limits.metrics_ttl` in `config/lathe.php`. A single widget can override it. Override `cacheFor()`, or use the attribute. Both take seconds, and 0 turns the cache off:

```php
use Lathe\Attributes\Widget\CacheFor;

#[CacheFor(300)]
class Revenue extends ValueWidget
{
    // ...
}
```

The cache is kept per user, so a card scoped by tenant never shows one user the numbers of another. If a card shows every user the same numbers, call `cacheAcrossUsers()` to share one entry.

A panel write to a table (a create, update, delete, restore, attach, detach, action or import) clears the cards that read that table, so numbers do not go stale. The aggregate helpers tell the cache which tables they read. If you read tables in your own query, name them with `cacheTags()`:

```php
NewUsers::make()->cacheTags(['users', 'subscriptions']),
```

A cache store without tag support falls back to the time limit alone.

### Ranges

Value and trend cards show a range selector on a Resource page. On a dashboard the header's range picker drives every card instead (see [Dashboards](dashboards.md#the-range)). Override `ranges()` to offer your own choices. The array maps a key to a label:

```php
public function ranges(): array
{
    return [
        7 => 'Last 7 days',
        30 => 'Last 30 days',
        ValueWidget::MTD => 'Month to date',
        ValueWidget::YTD => 'Year to date',
    ];
}
```

On a value card, a number key means the last N days, compared with the N days before. The constants `TODAY`, `YESTERDAY`, `THIS_WEEK`, `MTD`, `QTD`, `YTD` and `ALL` compare against the matching earlier period. `defaultRange()` picks the choice that shows first. Return an empty array to hide the selector. The selected key reaches your code as `$request->query('range')`.

### The card menu

On a dashboard, each card that loads its value from the server has a ⋯ menu; a `CustomWidget` draws its own content and has none. Refresh is always in the menu and reloads the card. Open records and Download CSV show only when they apply, as the next two sections say.

### Open records

The menu offers Open records when the card has a link. When every aggregate helper the card ran reads the same model, and a Resource has that model, the link is that Resource's index, shown only to a user who may list it. Set your own link with `link()`, shown as given:

```php
NewUsers::make()->link('/lathe/resources/users?filter[status]=new'),
```

### Download as CSV

On a dashboard, the menu of a trend, bar, partition, pie, radar or table card offers Download CSV: the rows or series the card shows, for the range it shows. `GET api/dashboards/{dashboard}/widgets/{widget}/csv` answers the file, named `<widget>-<YYYY-MM-DD>.csv`; any other card answers 404. Numbers are plain, without separators, prefix or suffix, and dates read `YYYY-MM-DD`.

The header names the columns the helper read: a trend reads `date,orders` for a count of orders, and a bar `status,total_cents` for a sum of `total_cents` by `status`. A result you build by hand reads `date,value` or `label,value`. A radar writes one row per axis and one column per dataset; a table writes its column keys, or `title,subtitle` for `MetricTableRow` rows.

## Value widgets

A `ValueWidget` shows one number, the number for the period before it, and the percentage change. The helpers `count()`, `sum()`, `average()`, `min()` and `max()` read the selected range for you. They take the request, a model class (or a query builder), the column and, as an optional last argument, the date column:

```php
use App\Models\Order;
use Lathe\Enums\Icon;
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

The date column defaults to the model's created-at column. `icon(Icon::ShoppingCart)` draws an icon beside the number.

The helpers return a `ValueResult`. To build one by hand, use `new ValueResult($current, $previous)`. Its methods:

| Method | Effect |
|--------|--------|
| `previous($value, $label)` | Sets the earlier value, with an optional label |
| `prefix()`, `suffix()`, `dollars()`, `euros()`, `currency()` | Text around the number |
| `format('a')`, `format('.2')` | Compact notation, or fixed fraction digits |
| `transform(callable)` | Changes the number before it shows |
| `allowZeroResult()` | Shows 0 instead of "No Data" |
| `copyable()` | Adds a copy button |
| `lowerIsBetter()` | Shows a rise in red and a fall in green, for numbers such as cancelled orders |
| `tooltipFormat(string)` | Sets the format of the hover tooltip |

The card shows every digit on a desktop and a short form, such as $1.9M, on a phone. Under the number it shows the change as a badge and then the earlier value, as "vs $1,852,565.92".

`precision(2)` on the widget rounds the value on the server. A second argument picks the rounding mode, such as `PHP_ROUND_HALF_EVEN`.

## Trend widgets

A `TrendWidget` draws one number per time bucket as an area chart. The helpers are named for the function and the bucket: `countByDays()`, `sumByDays()`, `averageByDays()`, `maxByDays()` and `minByDays()`. Each has versions for minutes, hours, weeks and months. The selected range is the number of buckets. Override `ranges()` so the labels say what the buckets are:

```php
class OrdersPerHour extends TrendWidget
{
    public function ranges(): array
    {
        return [24 => 'Last 24 hours', 72 => 'Last 72 hours'];
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->countByHours($request, Order::class, null, 'placed_at')
            ->showSumValue();
    }
}
```

`showSumValue()` makes the headline number the sum of all points. `showLatestValue()` makes it the last point. The default is the last point.

### Trend units

The helpers on the widget do not cover every unit. For those, build the numbers with `AggregateQuery`, whose `by*()` methods set the bucket size and the number of buckets. Four of them are worth knowing, since the trend helpers do not have them all:

| Method | Bucket |
|--------|--------|
| `byMinutes(int $count)` | One minute |
| `byHours(int $count)` | One clock hour |
| `byWeeks(int $count)` | One week, starting on Monday |
| `byYears(int $count)` | One calendar year |

`byDays()` and `byMonths()` work the same way. For any other `TrendUnit`, such as `Quarter`, call `by(TrendUnit::Quarter, 8)`.

Sign-ups for the last 12 weeks:

```php
use App\Models\User;
use Lathe\Widgets\TrendWidget;

class SignupsPerWeek extends TrendWidget
{
    public function ranges(): array
    {
        return [];
    }

    public function calculate(LatheRequest $request): mixed
    {
        return $this->aggregateQuery(User::class)->count()->byWeeks(12)->trend();
    }
}
```

Order totals, in cents, for the last 5 years, one point per year:

```php
public function calculate(LatheRequest $request): mixed
{
    return $this->aggregateQuery(Order::class)
        ->sum('total_cents')
        ->dateColumn('placed_at')
        ->byYears(5)
        ->trend();
}
```

`byMinutes()` and `byHours()` suit a live card. Pair them with `refreshEvery()`:

```php
public function calculate(LatheRequest $request): mixed
{
    return $this->aggregateQuery(Event::class)->count()->byMinutes(60)->trend();
}
```

A trend holds at most 366 points, and a larger count throws a `LogicException`. A bucket with no rows shows 0. Buckets are cut in the user's time zone.

### Chart appearance

These methods shape the chart:

| Method | Effect |
|--------|--------|
| `lineOnly()` | A line with no filled area |
| `stacked()`, `stackedExpand()` | Stack several series, or stack them to 100 percent |
| `curveType('monotone')` | One of `monotone`, `linear`, `step` or `natural` |
| `showAxes()` | Draws the axes |
| `showDots()`, `dotStyle()`, `dotColors()` | Draws dots on each point |
| `showLabels()`, `labelKey()` | Writes a label on each point |
| `twelveHourTime()` | Writes hours as 2 PM |

For several lines in one chart, give the result a `withSeries()` array that maps a series name to its points. `labels()` sets a text label for each point.

## Partition widgets

A `PartitionWidget` splits a total into segments and draws them as a ring. The helpers `count()`, `sum()`, `average()`, `max()` and `min()` group by a column. The groups come back largest first, and `label()` renames them:

```php
class CustomersByCountry extends PartitionWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Customer::class, 'country')
            ->label(fn (string $country) => strtoupper($country));
    }
}
```

A null group shows as "N/A". Only the 20 largest groups show. For a count or a sum, the rest merge into "Other". `colors()` maps a group to a colour. `asBar()` on the widget draws a stacked bar in place of the ring, and `showLabels()` writes the labels. Build the result yourself with `$this->result($segments)`.

## Bar widgets

A `BarWidget` draws one bar per group. It has the same helpers as the partition widget, and `count()` takes the group column as its third argument:

```php
class OrdersByStatus extends BarWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Order::class, 'status')
            ->label(fn (string $status) => ucfirst($status));
    }
}
```

The result has `horizontal()`, `stacked()`, `colors()`, `negativeColor()` and `withSeries()` for grouped bars. On the widget, `showLabels()` writes the values above the bars, `insideLabels()` writes them inside, and `activeIndex()` highlights one bar.

## Pie widgets

A `PieWidget` draws a pie. It has the same grouping helpers as the bar widget. The result's `innerRadius()` turns the pie into a donut, `label()` renames the slices, and `withSeries()` draws concentric rings:

```php
class ProductsByCategory extends PieWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Product::class, 'category_id')
            ->label(fn (string $id) => Category::find($id)?->name ?? $id)
            ->innerRadius(60);
    }
}
```

On the widget, `showTotal()` writes the total in the middle, `showInlineLabel()`, `showCustomLabel()` and `showLabelList()` pick a label style, `separatorNone()` removes the gap between slices, and `activeDonut()` thickens a hovered slice.

## Progress widgets

A `ProgressWidget` shows how far a number has come toward a target. Its `count()` and `sum()` helpers take a callable that narrows the finished rows. The target defaults to the total of all rows:

```php
use Illuminate\Database\Eloquent\Builder;

class PaidOrders extends ProgressWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Order::class, fn (Builder $query) => $query->where('status', 'paid'));
    }
}
```

To set the numbers yourself, return `new ProgressResult($value, $target)`. The bar uses the destructive colour below 33 percent, the warning colour below 66 percent and the success colour above. For a number that should stay low, such as open bugs, call `avoid()` on the result. The colours reverse and the caption reads "Lower is better". The result also has `prefix()`, `suffix()`, `dollars()`, `euros()`, `format()` and `transform()`.

## Radar widgets

A `RadarWidget` compares values across several axes. It has the same grouping helpers, which draw one axis per group. For several datasets, build the result by hand with `$this->result($axes, $datasets)`:

```php
public function calculate(LatheRequest $request): mixed
{
    return $this->result(
        [['key' => 'mon', 'label' => 'Monday'], ['key' => 'tue', 'label' => 'Tuesday']],
        [['name' => 'Sales', 'data' => [120, 90]]],
    )->dots()->gridType('circle');
}
```

The result also has `fillOpacity()`, `lineOnly()`, `gridNone()`, `gridFilled()`, `radialLines()`, `showLegend()` and `polarRadius()`.

## Radial widgets

A `RadialWidget` draws a ring that fills toward a target. Its `count()` and `sum()` helpers work like the progress helpers:

```php
class OrderCompletion extends RadialWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, Order::class, fn (Builder $query) => $query->where('status', 'shipped'));
    }
}
```

Build one by hand with `$this->result($value, $target)`. The result has `startAngle()`, `endAngle()`, `showTrack()`, `color()`, `label()`, `avoid()` and `transform()`. For several rings, pass `segments()` a list of `label`, `value`, `target` and `color` entries. `stacked()` stacks them in one ring.

## Table widgets

A `TableWidget` shows a short list of rows. Return the columns from `columns()` and the rows from `rows()`. Rows built as `MetricTableRow` objects need no columns, so the example below returns an empty list. Each column is an array with a `key`, a `label` and an optional `align` of `left`, `center` or `right`. A row is an array keyed by column, or a `MetricTableRow`:

```php
use Lathe\Enums\Icon;
use Lathe\Widgets\MetricTableRow;
use Lathe\Widgets\TableWidget;

class TopCustomers extends TableWidget
{
    public function columns(): array
    {
        return [];
    }

    public function rows(LatheRequest $request): array
    {
        return Customer::withSum('orders', 'total_cents')
            ->orderByDesc('orders_sum_total_cents')
            ->limit(5)
            ->get()
            ->map(fn (Customer $customer) => MetricTableRow::make()
                ->icon(Icon::User)
                ->title($customer->name)
                ->subtitle('$'.number_format($customer->orders_sum_total_cents / 100, 2)))
            ->all();
    }
}
```

A `MetricTableRow` has `icon()`, `iconClass()`, `title()`, `subtitle()` and `actions()`. Lathe sends at most 100 rows. `emptyText()` sets the message for an empty table. A table reads tables with its own query, so name them with `cacheTags(['customers', 'orders'])` to keep the cache fresh.

## Help widgets

A `HelpWidget` shows a block of plain text and runs no query. Line breaks are kept, and HTML is never parsed:

```php
use Lathe\Widgets\HelpWidget;

HelpWidget::make('Reading this page')
    ->content("Revenue counts paid orders only.\nRefunds show next month.")
    ->fullWidth(),
```

## Custom widgets

A `CustomWidget` draws a React component of your own. Register the component in your frontend module (see [Frontend](../extending/frontend.md)), then name it with `withComponent()`:

```php
use Lathe\Widgets\CustomWidget;

CustomWidget::make('Clock')->withComponent('app-clock'),
```

A custom card makes no value request. Use `withMeta()` to hand data to the component. `height('dynamic')`, `dynamicHeight()` and `fixedHeight()` control its height, and `fullWidth()` also switches to dynamic height.

## AggregateQuery

Every helper above sits on `Lathe\Widgets\AggregateQuery`. Use it when a helper does not fit. Each terminal call runs one query:

```php
use Lathe\Widgets\AggregateQuery;

$result = AggregateQuery::for(Order::class)
    ->sum('total_cents')
    ->dateColumn('placed_at')
    ->byDays(30)
    ->query(fn ($orders) => $orders->where('status', 'paid'))
    ->trend();
```

| Step | Methods |
|------|---------|
| Function | `count()`, `sum()`, `average()`, `min()`, `max()` |
| Period | `dateColumn()`, `timezone()`, `by(TrendUnit, int)`, `byMinutes()`, `byHours()`, `byDays()`, `byWeeks()`, `byMonths()`, `byYears()` |
| Rows | `query(callable)` narrows the rows or joins other tables |
| Groups | `groupBy()` and `colors()` |
| Result | `value()`, `valueBetween()`, `trend()`, `bar()`, `partition()` |

`value()` returns the aggregate over the last period and the one before it. `valueBetween($start, $end, $previousStart, $previousEnd)` does the same for dates you pick. `bar()` and `partition()` need `groupBy()` first, and Lathe throws a `LogicException` without it.

Inside a widget, call `$this->aggregateQuery(Model::class)` in place of `AggregateQuery::for()`. It applies the Resource's row scope and tells the cache which tables the card reads.

## Follow the index filters

A widget on a Resource index can follow the filters the user has set. Call `applyFilters()` on your query inside `calculate()`, and add `refreshWhenFiltersChange()` so the card reloads when a filter changes:

```php
#[RefreshWhenFiltersChange]
class FilteredOrders extends ValueWidget
{
    public function calculate(LatheRequest $request): mixed
    {
        return $this->count($request, $this->applyFilters($request, Order::query()), null, 'placed_at');
    }
}
```

`applyFilters()` reads the same `filter[key]` values the index uses. On a dashboard it changes nothing.

## What you see

After you add widgets, a page shows a grid of cards. Each card first shows a loading skeleton, then fills in with its number or chart. Value cards show a change badge with an arrow, and cards with a range selector update when you pick another range. A card that fails to load shows an error with a Retry button.

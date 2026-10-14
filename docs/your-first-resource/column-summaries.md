---
title: Column summaries
description: Show sums, averages, counts and ranges in a footer row under the index table.
order: 6
---

# Column summaries

A column summary shows one total under a column of the index table: a sum, an average, a count, a minimum, a maximum or a range. You attach it to a field with `summarize()`, as you do `sortable()`.

## Add a summary

```php
use Lathe\Field\Field;
use Lathe\Summaries\Summary;

Field::currency('Amount')
    ->sortable()
    ->summarize(Summary::sum()),
```

`summarize()` takes any number of summaries. Each one gets its own line in the column's footer cell:

```php
Field::currency('Amount')
    ->summarize(Summary::sum(), Summary::average(), Summary::max()),
```

> Note: A summary needs a database column. A computed field has no column to total. A field whose attribute is `relation.column` is totalled on the related column when the relation is a `belongsTo` or a `hasMany`.

## Summary types

Lathe computes each summary in SQL over the rows that match the current view.

| Constructor | Total | Default label |
|---|---|---|
| `Summary::sum()` | The sum of the column | Sum |
| `Summary::average()` | The average | Average |
| `Summary::count()` | The number of matching rows | Count |
| `Summary::min()` | The smallest value | Min |
| `Summary::max()` | The largest value | Max |
| `Summary::range()` | The smallest and largest value, shown as `min - max` | Range |

## What a summary covers

The totals follow the table. They respect the search, the filters, the active tab and the trashed state, so they always describe the rows the user is looking at. Changing the page does not change the total for all records.

The footer has up to two rows:

- **This page** totals the rows on the screen. Lathe adds it only when the table has more than one page. It adds no query.
- **All records** totals every matching row.

On a single page, the footer has only the All records row.

For a very large result, Lathe stops running the total query. When the matching rows are more than the Resource's count cap (10,000 by default, set by `limits.count_cap` in `config/lathe.php` or by `countStrategy()` on the definition), the footer shows the page totals and the message "Narrow the filters to total all rows."

## Set the label

A summary is labeled with its type. Change the text with `label()`, or remove the label with `hideLabel()`:

```php
Field::currency('Amount')
    ->summarize(
        Summary::sum()->label('Total'),
        Summary::average()->label('Average invoice'),
    ),

Field::number('Quantity')
    ->summarize(Summary::sum()->hideLabel()),
```

## Format the value

Lathe shows a total as a localized number. Whole numbers have no decimals and other numbers have two. Pass a closure to `format()` for something else. It receives the computed value and returns the text:

```php
$money = fn (int|float|string $value) => '$'.number_format((float) $value, 2);

Field::currency('Amount')
    ->summarize(
        Summary::sum()->label('Total')->format($money),
        Summary::max()->label('Largest')->format($money),
    ),
```

A column that stores cents needs the division in the closure:

```php
Field::currency('Total', 'total_cents')
    ->asMinorUnits()
    ->summarize(Summary::sum()->format(fn (int|float|string $cents) => number_format((float) $cents / 100, 2, '.', ''))),
```

## Limit the rows of one summary

To total only some of the rows, pass a closure to `query()`. It receives the query builder:

```php
use Illuminate\Database\Eloquent\Builder;

Field::number('Hours')
    ->summarize(
        Summary::sum()->label('All hours'),
        Summary::sum()
            ->query(fn (Builder $query) => $query->where('billable', true))
            ->label('Billable'),
        Summary::count()->label('Entries'),
    ),
```

> Note: A summary with its own query runs as a separate query and shows only in the All records row. It has no This page value and no group subtotal. Lathe runs all the other summaries together in one query.

## Subtotals for groups

When the table is grouped, each group ends with a row of subtotals for every summary you declared. The subtotal covers the whole group, even if the group continues on the next page. Lathe hides the row when the group runs off the bottom of the page and when the group is collapsed.

```php
use Lathe\Groups\Group;
use Lathe\Http\Requests\LatheRequest;

public function groups(LatheRequest $request): array
{
    return [
        Group::make('status'),
    ];
}
```

With this grouping and `Summary::sum()` on the amount column, each status ends with its own amount total. The footer still shows the total of every record. See [Row grouping](../querying/row-grouping.md).

## What you see

Open the index table of a Resource with a summarized column. The footer under the table shows the labeled totals for the column. With more than one page, you see a "This page" row and an "All records" row. Change a filter or the search, and the "All records" total changes with it.

---
title: Row grouping
description: Group the rows of a Resource's table under headers, with a subtotal row for each group.
order: 5
---

# Row grouping

Row grouping prints a header row before each set of rows that share a value, such as an order status or a day. Declare the groupings a Resource offers, and the toolbar gets a **Group** menu.

## Declare groups

Return `Group` objects from the Resource's `groups()` method:

```php
use Lathe\Groups\Group;
use Lathe\Http\Requests\LatheRequest;

public function groups(LatheRequest $request): array
{
    return [
        Group::make('status'),
        Group::make('placed_at', 'placed')->date()->label('Placed on'),
    ];
}
```

The first argument of `Group::make()` is the column. The second is an id for the group. It defaults to the column. Give an id when two groups use one column, or when you want a short value in the URL.

A group may use any column of the model, even one the table does not show. To group by a column of a `belongsTo` relation, use dot notation: `Group::make('customer.country')`. Any other relation would repeat a row once per related row, so Lathe refuses it when it builds the Resource.

Groups work on the Resource's own index. Lenses and relation panels do not group.

Choosing a group sorts the table by that column first. Any sort the user picks applies inside each group.

## Group by default

Call `defaultGroup()` on the definition to group the table when the page loads. Pass the group's id:

```php
use Lathe\Resources\ResourceDefinition;

protected function configure(ResourceDefinition $definition): ResourceDefinition
{
    return $definition
        ->model(\App\Models\TimeEntry::class)
        ->defaultGroup('billable');
}
```

The user can pick another group or choose **None** to clear grouping.

## Labels and titles

The **Group** menu names a group after its id, as a headline. Change it with `label()`:

```php
Group::make('issued_at')->label('Issued on')
```

A header shows the value of the column. A `null` value shows a dash. Dates show as `Y-m-d`, and enums show their value.

`titleUsing()` formats the title. The closure receives the first model of the group, so you can read any attribute:

```php
use App\Models\Invoice;

Group::make('status')->titleUsing(fn (Invoice $invoice) => ucfirst($invoice->status)),

Group::make('billable')->titleUsing(fn (Invoice $invoice) => $invoice->billable ? 'Billable' : 'Not billable'),
```

`titlePrefixedWithLabel()` puts the label in front of every title, as in `Status: paid`:

```php
Group::make('status')->titlePrefixedWithLabel()
```

## Descriptions

`description()` adds a line of smaller text under each header. Its closure also receives the first model of the group:

```php
Group::make('placed_at', 'placed')
    ->date()
    ->titlePrefixedWithLabel()
    ->description(fn (Order $order) => $order->placed_at->format('l'))
```

## Date groups

Call `date()` on a date or datetime column to group by calendar day instead of the exact value:

```php
Group::make('created_at')->date()
```

For a datetime column, Lathe uses the day in the user's own time zone. A row at 23:30 UTC on a Monday lands under Tuesday for a user in Tokyo. Subtotals use the same day boundaries, so a header and its subtotal agree.

## Collapsible groups

`collapsible()` lets the user fold a group from its header:

```php
Group::make('status')->collapsible()
```

> Note: The table pages on the server. Folding a group hides the rows of the current page only, not every row of that group.

## Direction

A group sorts ascending. `orderDirection()` changes the default:

```php
Group::make('created_at')->date()->orderDirection('desc')
```

The menu lists each group twice, as ascending and as descending. The choice appears in the URL as `?group=status:desc`.

## Subtotals

Grouping works with [column summaries](../your-first-resource/column-summaries.md). When a field declares a summary, every group gets a subtotal row, and the table footer still shows the total of all rows. No extra setup is needed:

```php
use Lathe\Summaries\Summary;

Field::currency('Total', 'total_cents')
    ->asMinorUnits()
    ->summarize(Summary::sum()->label('Revenue')),
```

Lathe reads the subtotals of all groups on the page with one query. A subtotal row is hidden while its group is folded, and when the group runs past the bottom of the page.

## How grouping works

Lathe does not run a `GROUP BY` for the rows. It sorts by the group column and prints a header whenever the value changes. A group that crosses a page boundary prints its header again on the next page.

Only the groups you declare work. `?group=` with any other id answers 422. `?group=_none` turns off a default group for that request.

## What you see

The index toolbar has a **Group** menu that lists **None** and each of your groups, ascending and descending. Pick one and the table gains a header row before each set of rows, with the title and the optional description. If a field has a summary, a subtotal row closes every group. If you made the group collapsible, a click on its header folds it.

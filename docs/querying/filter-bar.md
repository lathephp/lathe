---
title: Filter bar
description: Filter and search a Resource list from one bar with key:value tokens, and read the chips it draws.
order: 2
---

# Filter bar

A Resource index and its [lenses](lenses.md) filter and search from one bar above the table. You type tokens, press Enter, and each token becomes a chip. The chips write the same `filter[...]` keys that [filters](filters.md) read, so a saved view, an export and a shared link all see them.

Relation tables keep their own search box and filter panel.

## The grammar

A token is a filter's token, a colon and a value. The token is the filter's `key()`. A [filterable field](filters.md#filterable-fields) goes by its label in snake case, so Total is `total`.

```text
status:pending
status:pending,paid
total:>1500
total:10..50
customer:"Ann Taylor"
```

- A value that holds a space goes in double quotes.
- Words with no colon are a search, as the search box was.
- A Resource with soft deletes also takes `trashed:with` and `trashed:only`.

What a value looks like depends on the filter:

| Filter | Value |
|---|---|
| Select | An option, by value or label: `status:pending`. |
| Multiple select | Options separated by commas: `status:pending,paid`. |
| Range | A number, `>n`, `<n` or `a..b`: `total:>1500`, `total:10..50`. |
| Date | `YYYY-MM-DD`, `>date`, `<date` or `a..b`. |
| Boolean with one option | `yes` or `no`. |
| Relation | A related record's name: `customer:"Ann Taylor"`. When the related table is large enough to search on the server, the start of the name is enough; the bar looks it up and adds the chip. A small one lists its records, and the name must match one in full. |
| Text | The text. |

A list of values needs the filter to be `->multiple()`. A select filter without it takes one value, and `status:pending,paid` shows a red chip:

```php
public function filters(LatheRequest $request): array
{
    return [(new OrderStatus)->multiple()];
}
```

`multiple()` is a method on `Lathe\Filters\Filter`. See [Select filters](filters.md#select-filters).

## Chips

Press Enter and the token turns into a chip, and the table updates. A chip change sends one visit 300 ms after the last. Plain words search once the Resource's search debounce has passed.

A token that cannot apply shows as a red chip with its reason, such as "No filter named statuz". The rows do not change. Other reasons are an unknown option, a value of the wrong shape and more than one value for a filter that is not multiple.

Reload the page or share the link and the same chips come back from the URL.

## Keys

| Key | What it does |
|---|---|
| Enter | Turns the text into chips. In the list, picks the highlighted item. |
| Backspace | In the empty input, removes the last chip. |
| Left arrow | In the empty input, moves to the chips. |
| `/` | Focuses the bar from anywhere on the list. |
| Up, Down | Move through the list. |
| Esc | Closes the list. |

## The filter list

A click on the bar lists the Resource's filters. Type the start of a name or a key, such as `sta`, and the list narrows. Choose a filter and its key goes into the input, and the list shows its values. A relation lists its options from a search. A range, date or text filter shows its grammar. Choose a value and the chip is added.

A Resource that cannot search names the bar after itself, such as "Filter orders", and takes tokens only.

## What you see

Above the table is one input with a magnifier. Type `status:pending,paid` and press Enter: a chip reads "Status: Pending, Paid" and the table shows those orders. Type `statuz:paid` and the chip is red and says "No filter named statuz". Click the input with nothing typed and a list of the Resource's filters opens.

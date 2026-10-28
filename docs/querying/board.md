---
title: Board
description: Show a Resource list as a Board with one column of cards per option of a select field, and move cards between columns.
order: 9
---

# Board

Call `board()` with the key of an index select field to let users see the list as a Board, one column of cards per option:

```php
return $definition
    ->model(\App\Models\Order::class)
    ->board('status');
```

A Table/Board switch then sits beside the [filter bar](filter-bar.md). The columns are the field's options in the order you declared them, with a last No value column when the field is `nullable()`. Each column shows 25 cards in the list's sort, with Show more for the next 25, and a count capped like the list's.

A card shows the same slots as a phone card, so `phoneCard()` chooses its title, badge, value and meta. Each column is headed by the option's [status meter](../your-first-resource/status-meters.md) or badge and its count.

The Board follows the filter bar, the search and the open view, and Lathe remembers the layout each user chose per Resource. `?layout=board` in the URL opens the Board, and `?layout=table` opens the table.

## Move a card

Drag a card to another column, press Shift+Left or Shift+Right on it, or use its Move to menu. The move saves the field as its inline edit does. The user needs the `update` and `inlineEdit` abilities, and the stale check applies. Both counts change, and a refused move goes back with the reason.

Ctrl/Cmd+Z, or the Undo button of the toast, takes a move back. See [Workspace](workspace.md#undo).

The arrows move between cards. Space or a click opens the [preview panel](preview-panel.md). The Board selects nothing.

## Rules for the field

The field must be an `inline()` select with at most 12 inline options. Any other field stops the Resource from booting with a `LogicException` that names it. A subclass that drops the field calls `->board(null)`.

A lens and a phone show no Board.

## Index the board field

The Board reads each column in the list's sort, one page at a time. Index the board field together with your sort column, for example `$table->index(['status', 'created_at'])`, so each column's page and count stay fast on a large table.

## What you see

A Table/Board switch sits beside the filter bar. Choose Board and the list becomes columns, one for each status, each with a count and up to 25 cards. Drag a card to another column and the counts change. Reload and the Board is still chosen.

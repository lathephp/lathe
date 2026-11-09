---
title: Upgrade guide
description: The API and behaviour changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the [changelog](changelog.md). Its API changes are the entries marked `[api]`, which change the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract. Its behaviour changes are the other entries under Changed, which change what the panel does for your users.

These changes happen between 1.0.0-beta.10 and 1.0.0-beta.11. Check each API change against your code before you update, and tell your users about the behaviour changes.

## From 1.0.0-beta.10 to 1.0.0-beta.11

### API changes

- No public API changed since 1.0.0-beta.10: the classes and methods of the API snapshot, the artisan commands, the config keys and the route names are as they were, and a list's JSON only gains `table.titleColumn`, so an app updates with nothing to change in its code. Three English strings are new, for an app that translates the panel; none was renamed or removed: `Filter` (the list's Filter button), `Change :field` (the spoken name of a cell that edits inline, as "Status: Paid. Change status") and `Waiting for a queue worker. Is one running?` (the import wizard and a queued run's toast).

### Behaviour changes

- The toolbar's Actions menu lists only standalone actions while no row is ticked, and shows no button when the Resource has none; ticking a row lists every action as before. This holds on a Resource's list, on a lens, on the Board, which ticks nothing, and in a relation list's pivot Actions menu. The Delete menu already showed only for ticked rows.
- On a list that edits inline, the view and edit links and the row menu at a row's end open on the first click. A link inside a data cell, such as a related record's name, still moves the cell cursor on the first click and opens on the second. This holds on a Resource's list, on a lens and on a relation list.
- A cell that edits inline shows a small chevron at its value's end, inside the badge or the meter of a select drawn as one, while the pointer is over the cell or the cell cursor is on it, on a Resource's list, on a lens and on a relation list. On a list and a lens a screen reader names the cell "Status: Paid. Change status". A read-only cell shows none.
- A lens page lists the Resource's tabs and the user's saved views in its views rail, before the lenses, with the lens marked current and the tab badges counted as on the list. A tab or a saved view chosen there opens the Resource's list.
- A lens's footer totals the lens's own fields that call `summarize()`, and a lens with none of them draws no footer. The footer showed the Resource's skeleton and never filled.
- A list or lens with filters shows a Filter button beside the filter bar. It opens the filter bar's list of filters, as a click in the bar does, and counts the active filters, as "Filter 2". A relation list keeps its Filters popover.
- From 1100 px the views rail of a list or a lens folds into the toolbar's Views menu whenever the table's columns would not fit beside it, so the columns read whole without scrolling sideways, as on Orders at 1280 px with the sidebar open; a list whose columns fit keeps the rail open.
- The cards of a dashboard, of a Resource's list, of a lens and of a record page read their values from the widget endpoints through the panel's query cache, which keeps a value fresh for 60 s: going back to a page within 60 s shows its cards at once with no request, and after 60 s shows the kept values while it fetches them again. Refresh, a range change, a run action and a filter change still fetch. A dashboard page no longer sends each card's value as a deferred prop, so it asks for them after it paints, one request per card. The exported `WidgetGrid` and `WidgetCard` read through the same cache, so the cards of a plugin's custom page do too.
- A record page shows only the cards marked `onlyOnDetail()`, as the widget docs describe; the other cards of the Resource show above its list and no longer repeat on each record. `WidgetList::visible($request, true)` returns only the detail-only cards, so `GET .../widgets?resourceId=`, `useWidgetList` with a `resourceId` and the testing kit's `widgets(['resourceId' => ...])` list only them. A card meant for both places is listed above the list only; mark a card `onlyOnDetail()` to keep it on the record page.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

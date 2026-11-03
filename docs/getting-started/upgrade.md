---
title: Upgrade guide
description: The API and behaviour changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the [changelog](changelog.md). Its API changes are the entries marked `[api]`, which change the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract. Its behaviour changes are the other entries under Changed, which change what the panel does for your users.

These changes happen between 1.0.0-beta.6 and 1.0.0-beta.7. Check each API change against your code before you update, and tell your users about the behaviour changes.

## From 1.0.0-beta.6 to 1.0.0-beta.7

### API changes

- New English strings since 1.0.0-beta.6, for an app that translates the panel; none was renamed or removed: `Waiting`, `Running`, `Finished` and `Failed` (the Action Events list's Action Status filter), `:action: changed :fields` (a History entry of an action run, as "Cancel order: changed Status") and `This record is attached more than once.` (Edit pivot or Detach on a record attached twice to a pivot table with no id column).
- A value card's number range counts calendar days in the user's time zone, today and the N - 1 days before it, compared with the N calendar days before those, so its total matches a trend card's for the same range. It counted the last N times 24 hours back from now, so at 7 days an order placed late on the eighth day ago counted in the card and not in the trend. `TODAY`, `YESTERDAY`, `THIS_WEEK`, `MTD`, `QTD`, `YTD` and `ALL` keep their windows, and `AggregateQuery::value()` is unchanged.
- A date filter can name the column it narrows with `DateFilter::column()`, null by default. When it names one, the Open records link of a dashboard card dated by that column opens the list with the filter set from the card's first day to its last, `filter[key][from]` and `filter[key][to]`, so the list holds the rows the card counted. This works for a value card with a number range and a trend by days, weeks or months; a trend by minutes or hours, a named range such as `MTD`, a card dated by another column and a card with its own `link()` keep their links.
- A range filter takes strict sides, `filter[key][gt]` and `filter[key][lt]`, compared with `>` and `<`; `min` and `max` stay inclusive, so an existing link or saved view keeps its meaning. A filter `filterable()` generates applies them on the column as stored, so a cents column compares `gt=150000` with 150000 cents. `RangeFilter::normalize()` returns `gt` and `lt` beside `min` and `max` only when the request sends them, and only for a filter whose `$strict` property is true: a filter `filterable()` generates sets it, unless it has a `filterable(fn ...)` callback, which keeps receiving `min` and `max` only. A `RangeFilter` subclass takes them once it sets `protected bool $strict = true` and reads them in `apply()`; until then it answers 422 to `gt` or `lt`, and the filter bar writes its `>n` and `<n` as `min` and `max`, chips "≥" and "≤" as before. Each range filter's definition in `filters.available` carries `strict`.
- A value card that averages, or takes the minimum or maximum, says "No earlier data to compare with." when the days before its range hold no rows, as a count or a sum card does; Average order at 12 months showed no line. The `previous` of such a card's response is 0 where it was null. A card at `ALL` still has a null `previous`, and `AggregateQuery::value()` and `valueBetween()` still return null for an average of no rows.
- An action run's events show the values the action changed on each record under Original and Changes, where both were always empty. The records are read again once per 200 after the run; the update timestamp, hidden attributes and password or token attributes are left out, and a record the action did not change keeps both empty. A record's History card names the fields such a run changed, as "Cancel order: changed Status": the `title` of an `action-ran` entry from `GET .../history/latest` is that text where it was the action's name.
- An action whose `handle()` returns `ActionResult::danger()`, inline, standalone or in a queued chunk, leaves its action events Failed, with the message under Exception, where they read Finished: the `status` of those `lathe_action_events` rows is `failed` and their `exception` the message. The response is unchanged: HTTP 200 with the danger toast, and the row counts as run, not failed.
- A `belongsToMany` or `morphToMany` field with `withCount()` shows the related count in its index column, loaded in one query for the page; the column was empty. `BelongsToManyField::resolve()` returns that count as an int when the record carries `<relation>_count`, and the cell's `value` is the count where it was null.
- Edit pivot, its form and Detach work on a `belongsToMany` or `morphToMany` whose pivot table has no id column, where they answered 404. A relation panel's row carries `pivot_id` only when the pivot table has an id column; the server finds a row of a pivot table with none by its related key and ignores any `pivot_id`, and a record attached to it twice answers 422 on `related_id` with "This record is attached more than once." and changes nothing.

### Behaviour changes

- In the filter bar, `total:>1500` and `total:<1500` leave out an order of exactly 1,500, and their chips read "Total: > 1,500" and "Total: < 1,500"; `total:1000..1500` and a bare `total:1500` still include their ends. A date filter's `>` and `<` still include the date.
- On a dashboard, a card with no `ranges()` of its own receives the header's range as `$request->query('range')`, so a bar, partition, pie or progress card that reads it follows the picker; it received no range and showed whatever its code fell back to, with nothing on the card to say so. A card with `ranges()` is unchanged.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

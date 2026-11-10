---
title: Upgrade guide
description: The API and behaviour changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the [changelog](changelog.md). Its API changes are the entries marked `[api]`, which change the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract. Its behaviour changes are the other entries under Changed, which change what the panel does for your users.

These changes happen between 1.0.0-beta.11 and 1.0.0-beta.12. Check each API change against your code before you update, and tell your users about the behaviour changes.

## From 1.0.0-beta.11 to 1.0.0-beta.12

### API changes

- No public API changed since 1.0.0-beta.11: the classes and methods of the API snapshot, the artisan commands, the config keys and the route names are as they were, and the filters endpoint's JSON only gains each filter's `token` and `kind`, as the list's filters already carry, so an app updates with nothing to change in its code. One English string is gone, for an app that translates the panel; none is new or renamed: `Filters`, the relation list's button that opened its filter panel, which the list's filter bar and its Filter button replace.

### Behaviour changes

- A relation list on a record page searches and filters with the list's filter bar: one input for search and filters, a chip for each filter, and the Filter button with its count, in place of its search box and its Filters button and panel. Each relation tab keeps its own search and chips while the user stays on the page, so they stay while another tab is open and a reload clears them; the page URL never holds them, and a relation list has no saved views. A relation tab whose Resource has no filters and no search shows no filter bar. The `/` key no longer focuses a relation list's search; it focuses the filter bar on a Resource's list and on a lens.
- The filter bar's list of filters opens as a 320 px popover under the text cursor, so it follows the chips as they grow, and stays inside the bar when the cursor is near the bar's end, in a right-to-left panel too; it was as wide as the bar. On a phone it is the bottom sheet, as before. This holds on a Resource's list, on a lens and on a relation list.
- A group heading on a select field reads the option's label, as "Status: Pending", where it read the stored value, "Status: pending". A `titleUsing()` closure, date groups, relation columns and fields with no options keep their headings, and the group's key stays the stored value.
- When the server refuses an inline select's save, the editor comes back with its list closed and the cell's old value, so the message reads whole, where the open list covered it and the cell kept the refused value; Escape leaves the cell. A text or number cell keeps the refused draft to fix. This holds on a Resource's list, on a lens and on a relation list.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

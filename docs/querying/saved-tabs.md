---
title: Saved tabs
description: Let each user save a table's filters, search and sort as a named tab they can return to.
order: 4
---

# Saved tabs

A saved tab keeps the state of a Resource's table under a name: its filters, its search term, its sort and its trashed scope. The tab sits beside any tabs you define in code, so a view the user builds once is a click away on every later visit.

Saved tabs belong to one user and one Resource. Nobody sees another user's tabs, and a request for a tab that belongs to someone else answers 404.

## Turn saved tabs on

Saved tabs are off until a Resource opts in. Call `savedTabs()` on the definition:

```php
use Lathe\Resources\ResourceDefinition;

protected function configure(ResourceDefinition $definition): ResourceDefinition
{
    return $definition
        ->model(\App\Models\Post::class)
        ->savedTabs();
}
```

The attribute does the same:

```php
use Lathe\Attributes\Resource\SavedTabs;

#[SavedTabs]
class Post extends Resource
{
    // ...
}
```

Pass `false` to either one to turn the feature off for that Resource, for example `->savedTabs(false)` or `#[SavedTabs(false)]`.

Two things must be true: `saved_tabs.enabled` is `true` in `config/lathe.php`, and the Resource opts in. Otherwise every saved-tab endpoint answers 403. Saved tabs also need a signed-in user.

If the Resource defines no tabs of its own, Lathe adds an **All** tab with a count, so the user always has a plain view to go back to.

## What a tab stores

When the user saves a tab, Lathe stores:

- the active filters,
- the search term (up to 500 characters),
- the sort column and direction,
- the trashed scope: live rows only (the default), with trashed rows, or only trashed rows,
- whether to show a count badge, and its colour.

The name may have up to 100 characters.

Opening a saved tab applies the stored filters and search as the base of the query. The stored sort is a fallback. When the URL already names a sort, the URL wins.

## Count badges and colours

A tab can show how many rows match its filters and search. Lathe counts when the page loads, so the badge always reflects the current data. The user can colour the badge `success`, `danger` or `warning`.

## Order

The user drags a tab by its handle to reorder tabs. Alt+Left and Alt+Right move a focused tab with the keyboard. Lathe saves the order and uses it on the next visit.

## Limit

A user may save a limited number of tabs per Resource. The eleventh tab, with the default limit, answers 422 with "Maximum of 10 saved tabs per resource". Set the limit in `config/lathe.php`:

```php
'saved_tabs' => [
    'enabled' => true,
    'max_per_resource' => 10,
],
```

- `enabled` turns saved tabs on or off for the whole panel.
- `max_per_resource` is the most tabs one user may keep for one Resource.

See [Configuration](../getting-started/configuration.md) for the other keys.

## When the Resource changes

A saved tab refers to filters and sort columns by name. When you remove a filter or make a column unsortable, an old tab may name something that is gone. Lathe keeps a fingerprint of the Resource's filter keys, filter types and sortable columns with each tab.

When the fingerprint no longer matches, Lathe applies the parts that are still valid and skips the rest. The tab shows a "View updated" notice with a Reset button. Reset drops the dead parts and saves the tab again.

## What you see

On a Resource that opted in, the tab bar above the table offers a way to save the current view. Name it, choose whether to show a count and pick a badge colour, and a new tab appears beside the others. Click it and the table returns to the saved filters, search and sort. Drag the tab to move it. Delete or rename it from its menu.

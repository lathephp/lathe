---
title: Preview panel
description: Show a record beside the list, and choose which fields the panel shows.
order: 8
---

# Preview panel

The preview panel shows a record beside the list, so the user can read it without leaving the page. Every Resource has one.

## Open it

- Space on a cell opens the panel on that cell's row. Space again closes it.
- `ClickAction::Preview` makes a click on the title cell, and Enter on a cell that cannot be edited, open the panel too. See [Click action](../your-first-resource/defining-resources.md#click-action).

```php
return $definition
    ->model(\App\Models\Task::class)
    ->clickAction(ClickAction::Preview);
```

The URL carries the row as `?preview=`, so a link opens the list with the panel already showing that record. The panel also shows the record's actions and what the user may do with it.

Each user's panel is open or closed per Resource, and Lathe keeps that on the server. `PUT api/resources/{resource}/preview-panel` saves it with an `open` flag. A list the user left with the panel open opens it again on the first row. On a phone it stays closed until the user asks for a record.

While the panel is open, the views rail folds into the Views menu. See [Workspace](workspace.md).

## Choose the fields

Override `fieldsForPreview()` on the Resource to list the fields:

```php
public function fieldsForPreview(LatheRequest $request): array
{
    return [
        Field::text('Number'),
        Field::select('Status'),
        Field::currency('Total', 'total_cents')->asMinorUnits(),
    ];
}
```

When it returns an empty list, the panel shows the record's detail fields, without the relation lists. A BelongsTo or MorphTo field keeps its name.

Three methods on a field fine-tune it:

- `showOnPreview()` adds the field to the panel.
- `onlyOnPreview()` shows the field in the panel and nowhere else.
- `hideFromPreview()` drops the field from the panel.

## What you see

Press Space on a row and a panel opens at the right of the list. It shows the record's fields and its actions. The address bar gains `?preview=` with the record's id. Move the cursor to another row and the panel follows. Reload the page and the panel is still open.

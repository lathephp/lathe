---
title: Workspace
description: Work in a Resource list with the keyboard, edit cells in place, select rows and undo, using the views rail, the filter bar and the preview panel.
order: 1
---

# Workspace

A Resource index is a workspace. Every Resource list, [lens](lenses.md) and relation table is a grid with one tab stop and a cell cursor. Tab moves focus into the table once and out of it once. Inside, the keys move the cursor from cell to cell.

A click on a cell moves the cursor there. It does not open the row. What opens the row is `clickAction`, and it applies in two places: Enter on a cell that cannot be edited, and a click on the title cell.

## The parts of the page

- The **views rail** sits at the card's left. It lists the Resource's tabs, the user's [saved views](saved-tabs.md) and the lenses. Below 1100 px, or while the preview panel is open, it folds into a Views menu at the start of the toolbar.
- The [filter bar](filter-bar.md) sits above the table. It searches and filters from one input.
- The [preview panel](preview-panel.md) opens beside the list and shows the record under the cursor.
- For a Resource with `->board()`, a Table/Board switch sits beside the filter bar. See [Board](board.md).

## Click action

`clickAction()` names what Enter does on a cell that cannot be edited, and what a click on the title cell does. The default is `ClickAction::Detail`. The cases are on the [Resource page](../your-first-resource/defining-resources.md#click-action).

Enter on the checkbox cell ticks the row. Enter on the row's action cell moves focus to its first button.

## Keys

The keys work while the cursor is on a cell. They stay out of the way while a text input, a menu or a dialog has the focus. Press `?` on a list to see the same list in a sheet. The Keyboard shortcuts item in the user menu opens it too.

### Move

| Key | What it does |
|---|---|
| Arrow keys | Move the cursor one cell. |
| Home | Go to the row's first cell. |
| End | Go to the row's last cell. |
| Page Up | Move up 25 rows. |
| Page Down | Move down 25 rows. |

The Up arrow on the first row moves the cursor to the header. There the arrows move along the header, Down goes back to the rows, and Enter presses the header's sort, its select-all or its Columns button.

### Edit

A cell of a field you marked `inline()` shows its value until you open it.

| Key | What it does |
|---|---|
| Enter | Opens the cell's control. |
| F2 | Opens the cell's control. |
| Double-click | Opens the cell's control. |
| Tab | Saves the cell and moves right. |
| Esc | Cancels the edit. |

A value the server refuses stays open with the server's message. A save that finds the record changed by someone else opens the conflict dialog.

### Select

| Key | What it does |
|---|---|
| X | Selects the row under the cursor, or clears it. |
| Shift with Up or Down | Moves the cursor and extends the selection. |
| Shift+click | Selects the rows between the last ticked row and the clicked one. |
| Ctrl/Cmd+A | Selects the page. |
| Ctrl/Cmd+Shift+A | Selects all matching rows. |
| Esc | Clears the selection. |

Ctrl is for Windows and Linux, Cmd for a Mac. The selection feeds the bulk [actions](../taking-action/actions.md).

### Open

| Key | What it does |
|---|---|
| Enter | Opens the row through the click action, on a cell that cannot be edited. |
| Space | Opens or closes the [preview panel](preview-panel.md) on the row. On the checkbox cell, Space ticks the row. |
| `?` | Shows the sheet of keys. |

## Undo

Ctrl/Cmd+Z undoes the last cell edit made in the tab. The toast that follows a save has an Undo button that does the same. A move of a card on the [Board](board.md) is undone the same way.

Undo covers cell edits and Board moves only. Running an action empties the history. So do a reload and a visit to another page.

## What you see

A list shows one cell with a focus ring. Click another cell and the ring moves there. The arrow keys move it too, and Page Down jumps 25 rows. Press Enter on a status cell and its control opens in place. Press Tab and the value saves, with a toast that offers Undo. Press X and the row ticks. Press `?` and a sheet lists the keys, grouped as Move, Edit, Select, Undo and Open.

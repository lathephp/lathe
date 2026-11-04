---
title: Upgrade guide
description: The API and behaviour changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the [changelog](changelog.md). Its API changes are the entries marked `[api]`, which change the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract. Its behaviour changes are the other entries under Changed, which change what the panel does for your users.

These changes happen between 1.0.0-beta.7 and 1.0.0-beta.9. Check each API change against your code before you update, and tell your users about the behaviour changes.

## From 1.0.0-beta.7 to 1.0.0-beta.9

### API changes

- `WidgetCard` takes its columns from the width of the nearest container, not the window's, so a custom page that puts cards in a grid of its own makes that grid a container (Tailwind's `@container`), as `WidgetGrid` does; in a grid that is not one, every card keeps its phone layout at any width.
- No English string is new, renamed or removed since 1.0.0-beta.7, so an app that translates the panel has nothing to add.
- A plugin can limit its settings to some of the users who may use it with `canSeeSettings(Closure $callback)` or `canSeeSettingsWhen(string $ability, mixed $arguments = [])`. The rule gates `GET` and `PUT` on the plugin's settings API, its settings page and the automatic Settings link, each answering `403` or leaving the link out for a user it refuses. It only narrows `canSee()`: a user `canSee()` denies is refused whatever the settings rule says, and without a settings rule a plugin's settings follow its `authorize()`, as before. A plugin with neither a settings rule nor `canSee()` or `canSeeWhen()`, and no `authorize()` of its own, lets every panel user read and change its settings. A `menu()` list is kept as written, even an item named Settings, and `lathe:plugins` lists the plugin as an admin sees it.

### Behaviour changes

- The cards of a dashboard, of a Resource's list and of its record page take their layout from the width of their grid, not the window's: beside the sidebar, from 768 px up to about 1,070 px the cards sit as on a phone, value and progress cards of a third or less two across and the rest across the row, where four quarter cards were squeezed to 101 px at 768 px and cut every title and number. A collapsed sidebar gets the 12 columns sooner.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

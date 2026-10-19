---
title: Upgrade guide
description: The public API changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the entries marked `[api]` in the [changelog](changelog.md). It lists only changes to the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract.

These public API changes happen between 1.0.0-beta.3 and 1.0.0-beta.4. Check each one against your code before you update.

## From 1.0.0-beta.3 to 1.0.0-beta.4

- A `shell.header` replacement gets the page's `breadcrumbs` and a `pageBarRef` for the phone's page title and main action, now that the default top bar carries both; without a page bar, a page keeps its title and main action on a phone.
- The `@lathephp/ui` Button gains the `destructive-solid` variant for a confirm dialog's button, and `destructive` turns outlined in the nova style; Badge gains `success`, `warning` and `info`. Every style draws them.
- `ProgressResult::tone()` keeps a progress bar in one colour (primary, info, success, warning or destructive) at any percentage, for a share that measures a state rather than a goal, and `ProgressResult::label()` replaces the words after the share. The workbench's Shipped card uses both.
- Changed English strings since 1.0.0-beta.3, for an app that translates the panel; move each override to its new key: `Create & Add Another` reads `Create and add another`, and `Other` reads `:count more` (the rest row of a pie drawn as bars, such as "15 more"). `Create :resource` keeps its key and fills in the resource name in sentence case, so the create form's title, crumb and button read "Create order"; a translation that starts with the name, or a language that capitalises nouns, writes `:Resource`.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

---
title: Upgrade guide
description: The API and behaviour changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the [changelog](changelog.md). Its API changes are the entries marked `[api]`, which change the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract. Its behaviour changes are the other entries under Changed, which change what the panel does for your users.

These changes happen between 1.0.0-beta.5 and 1.0.0-beta.6. Check each API change against your code before you update, and tell your users about the behaviour changes.

## From 1.0.0-beta.5 to 1.0.0-beta.6

### API changes

- The panel home (`/lathe`) sends a user who may not see the main dashboard to the first dashboard they may see, else to the first sidebar page they may open, and answers 403 only when they may open nothing. Sign-in, the brand link and starting or stopping an impersonation land there too. `DashboardController::__invoke()` returns `Response|RedirectResponse`.

### Behaviour changes

- Export and import download links are relative signed URLs checked with `signed:relative`, so a link the queue worker built works at whatever address the panel is opened on, not only at `APP_URL`. A link issued before the upgrade stops working; the next status poll or a new export gives a fresh one.
- In a `dependsOn()` callback, `FormData::get()` returns a `belongsTo` field's key, not the picker's `{value, display}` array, so `Product::find($formData->get('product'))` finds the record. A callback that read `['value']` reads the key directly.
- Value and trend cards offer the dashboard's ranges by default, 7 days, 30 days and 12 months (they were 30, 60 and 365 days, and 30, 60 and 90 days), and still start at 30 days, so a dashboard set to 7 days or 12 months no longer shows "Last 30 days" on its cards. A card with its own `ranges()` is unchanged.
- `TrendResult::transform()` changes the trend's points and series as well as its headline, so `->transform(fn ($cents) => $cents / 100)` plots dollars.
- Extension CSS built through the Lathe Vite plugin imports Tailwind's theme as a reference: `@import "tailwindcss"` defines no `:root` variable that could override the panel's (an app extension turned the panel's Archivo into the system font), and with no `source()` of its own it scans the extension's folder: in an app the boot file's folder (`resources/js/lathe`), not the whole app, and in a plugin its whole folder. `@lathephp/core/tokens.css` is a reference theme too. Hand-written CSS in an extension that read one of Tailwind's default variables, such as `var(--color-red-500)`, must define it.
- `Field::heading()` and `Field::hidden()` are compact, so a form draws no label row and no Optional mark for them.
- An action's `->icon('mail')`, a kebab-case Lucide name, draws the same icon as `->icon('Mail')`. `Field::icon()` and a badge's `withIcons()` given a name the field icon set does not draw log one browser console warning that names it; the fields docs list the 27 names the set draws.
- A column's `indexWidth()` holds: its header and cells are that wide at least and at most, where the cells' 320 px cap could widen it before.
- A list with no inline-editable cell, such as Action Events, follows a link in a cell, such as a related record's name, on the first click and still moves the cell cursor there. A list that edits inline keeps the beta.5 rule: the first click moves the cursor and the link opens once its cell holds it.
- The trashed-only view of a soft-deleting list (`trashed:only`) draws no Soft Deleted badge, since every row in it is deleted; a view that mixes live and deleted rows keeps it.
- An inline save, a Board move included, drops the saving user's cached sidebar badge of that Resource, so a badge such as a count of pending orders shows the change on the next read instead of after its TTL.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

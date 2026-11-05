---
title: Upgrade guide
description: The API and behaviour changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the [changelog](changelog.md). Its API changes are the entries marked `[api]`, which change the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract. Its behaviour changes are the other entries under Changed, which change what the panel does for your users.

These changes happen between 1.0.0-beta.9 and 1.0.0-beta.10. Check each API change against your code before you update, and tell your users about the behaviour changes.

## From 1.0.0-beta.9 to 1.0.0-beta.10

### API changes

- No public API changed and no English string is new, renamed or removed since 1.0.0-beta.9, so an app updates with nothing to change in its code or its translations.

### Behaviour changes

- The top bar's search shows as its icon button, as on a phone, whenever the top bar is narrower than about 760 px: in a window narrower than about 1,016 px with the sidebar open, or about 808 px with it collapsed. It showed the search box from 768 px up. Ctrl+K and ⌘K open search as before.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

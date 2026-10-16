---
title: Upgrade guide
description: The public API changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the entries marked `[api]` in the [changelog](changelog.md). It lists only changes to the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract.

1.0.0-beta.2 changes no public API since 1.0.0-beta.1. Run `composer update lathe/lathe` and you are done.

## What you see

After `composer update lathe/lathe` and the changes above, `php artisan lathe:doctor` ends with `0 FAIL`.

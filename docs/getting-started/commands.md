---
title: Commands
description: Every lathe:* artisan command, its arguments and options.
order: 6
---

# Commands

Every Lathe command starts with `lathe:`. Run `php artisan list lathe` to see them, and `php artisan help <command>` for the full signature. None of them calls the network except `lathe:preset`, which downloads a preset from ui.shadcn.com.

## Set up and check

| Command | What it does |
|---|---|
| `lathe:install` | Publishes `config/lathe.php` and the migrations, writes `app/Providers/LatheServiceProvider.php` (and adds it to `bootstrap/providers.php`), the User Resource, the User policy and the Main dashboard, then runs the migrations. An existing file is left alone. It ends with the next two steps. |
| `lathe:user` | Creates a user who can sign in to the panel. `--name`, `--email` and `--password` skip the prompts, so it also runs with no interaction. |
| `lathe:doctor` | Checks the setup and prints one `OK`, `WARN` or `FAIL` line per check, then the totals. It exits 1 when any check fails. It checks the environment, `config/lathe.php`, the database, the prune schedule, each Resource, each plugin, the custom components and the published assets. |
| `lathe:uninstall` | Removes the Lathe service provider, the published config and the copied assets. Your Resources stay. |
| `lathe:cache` | Caches the Resource manifest, so discovery does not scan `app/Lathe` on each request. Run it on deploy. |
| `lathe:clear` | Removes the cached Resource manifest. |

## Generate classes

Each generator writes from a stub. `lathe:stubs` publishes the stubs to `stubs/lathe` so you can edit them; the class generators below then use your copies (`lathe:install` and the `lathe:plugin` package skeleton always use Lathe's own). `--force` overwrites an existing class. `--plugin=<vendor/package>` writes the class into a plugin package instead of the app.

| Command | What it writes |
|---|---|
| `lathe:resource <name>` | A Resource in `app/Lathe/Resources` and its policy. Options: `--plugin`, `--force`. |
| `lathe:action <name>` | An action. Options: `--plugin`, `--force`. |
| `lathe:filter <name>` | A filter. `--type` is `select` (default), `boolean`, `date` or `range`; `--custom` also writes a React component. |
| `lathe:lens <name>` | A lens. Options: `--plugin`, `--force`. |
| `lathe:widget <name>` | A widget. `--type` is `value` (default), `trend`, `partition`, `progress`, `table`, `custom`, `bar`, `pie`, `radar`, `radial` or `help`. |
| `lathe:field <name>` | A field class. `--extends` names the field type to extend (default `field`); `--custom` also writes a React component. |
| `lathe:dashboard <name>` | A dashboard. Options: `--plugin`, `--force`. |
| `lathe:page <name>` | A standalone page and its React component. |
| `lathe:resource-plugin <name>` | A resource plugin and its React component. Options: `--plugin`, `--force`. |
| `lathe:plugin <name>` | A plugin package (`acme/price-tracker`) with its PHP and React halves, or with `--inline` an in-app plugin class and React page. `--skip-composer` does not offer to run `composer update`. |
| `lathe:stubs` | Publishes the generator stubs to `stubs/lathe` for editing. |

## Plugins, assets and theme

| Command | What it does |
|---|---|
| `lathe:plugins` | Lists the registered plugins. `--diagnose` re-checks each plugin's requirements, version ranges and module manifest, and exits 1 on a gap. |
| `lathe:assets` | Copies the scripts and styles that plugins register to the public folder. `--force` replaces copies that exist. |
| `lathe:preset <preset>` | Applies a shadcn preset code (such as `a5ONTE`) to the theme keys of `config/lathe.php`. `--interactive` asks for each parameter, `--show` prints the current theme without changing it, `--reset` sets every colour override back to `null`. This is the only command that uses the network, and only in the terminal. |
| `lathe:translate <language>` | Copies the English catalogue to `lang/vendor/lathe/<language>.json` for translating. `--force` replaces an existing file. |

## Housekeeping

| Command | What it does |
|---|---|
| `lathe:prune` | Deletes old action events, exports, imports, draft uploads and finished bulk runs, using the `*.prune_*` keys of the [configuration](configuration.md). Lathe schedules it daily for you. |
| `lathe:prune-action-events` | Deletes action events only. `--days` deletes events older than that many days; `--keep` keeps that many newest events per record. |

## For contributors and the demo

| Command | What it does |
|---|---|
| `lathe:api-check` | Compares the public PHP and JavaScript API with the snapshot in `api/`. `--write` rewrites the snapshot. Lathe's CI runs it on every change. |
| `lathe:demo-seed` | Exists only in the demo app and the workbench: wipes the demo tables and seeds the store again. `--orders` sets the number of orders (default 10,000). |

## What you see

`php artisan list lathe` prints each command above with its one-line description. `php artisan lathe:doctor` on a fresh install prints a list of `OK` lines and ends with `0 FAIL`.

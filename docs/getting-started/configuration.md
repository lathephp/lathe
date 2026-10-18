---
title: Configuration
description: Every key of config/lathe.php, its default and what it changes.
order: 5
---

# Configuration

`config/lathe.php` holds every host-level setting of the panel. `php artisan lathe:install` publishes it. Lathe merges its own defaults under your file, so your copy only needs the keys you change.

Lathe checks the file when the app boots. A bad value stops the boot with a message that names the key, the file and the fix:

```text
config/lathe.php: pagination.per_page_options: every value must be an integer from 1 to 100; got 500. Set limits.max_per_page higher or remove 500.
```

`php artisan lathe:doctor` lists every problem at once. An unknown key is a warning, never an error.

Every limit below is a default. A Resource can set its own value in its definition (see [Defining resources](../your-first-resource/defining-resources.md)).

## Environment variables

Only these variables are read: `LATHE_APP_NAME`, `LATHE_DOMAIN_NAME`, `LATHE_GUARD`, `LATHE_PASSWORDS`, `LATHE_STORAGE_DISK`, `LATHE_EXPORT_DISK`, `LATHE_IMPORT_DISK`, `LATHE_FORTIFY_FEATURES`, `LATHE_IMPERSONATION_STARTED` and `LATHE_IMPERSONATION_STOPPED`.

## Panel and routing

| Key | Default | Effect |
|---|---|---|
| `name` | `LATHE_APP_NAME`, then `APP_NAME`, then `'Lathe'` | The sidebar title and the page `<title>`. |
| `version` | `null` | The version label in the sidebar. `null` shows the package version, a string replaces it, `false` hides it. |
| `brand.logo` | `null` | A path or URL of an image. The sidebar shows it in place of the first-letter tile, with the name beside it. `null` keeps the tile. |
| `brand.logo_dark` | `null` | A path or URL of the logo for the dark theme. `null` shows `brand.logo` in both themes. |
| `domain` | `LATHE_DOMAIN_NAME` (`null`) | When set, every page, auth and API route registers on that domain only. Other hosts get 404. |
| `path` | `'/lathe'` | The URI prefix of the panel. JSON endpoints live under `{path}/api`. |
| `guard` | `LATHE_GUARD` (`null`) | The auth guard of the panel. `null` uses the app's default guard. |
| `passwords` | `LATHE_PASSWORDS` (`null`) | The password broker for password resets. |
| `middleware` | `['web', HandleInertiaRequests::class]` | Middleware of the page routes. |
| `api_middleware` | `['lathe', Authenticate::class, Authorize::class]` | Middleware of the JSON routes. |
| `initial_path` | `null` | Where a user lands after signing in. `null` means `path`. |
| `currency` | `'USD'` | The default currency of currency fields. |

## Pagination

| Key | Default | Effect |
|---|---|---|
| `pagination.type` | `'links'` | `links` (numbered pages), `simple` (previous and next) or `load-more`. |
| `pagination.per_page` | `25` | The default page size. |
| `pagination.per_page_options` | `[25, 50, 100]` | The choices of the page-size selector. Each must be at most `limits.max_per_page`. |

## Limits

| Key | Default | Effect |
|---|---|---|
| `limits.max_per_page` | `100` | The largest page a request may ask for. There is no "show all". |
| `limits.count_cap` | `10000` | The total count stops here. The list reads one row more than the cap and shows "10,000+" when there are more. |
| `limits.picker_results` | `25` | Rows per page of a relation picker. |
| `limits.search_term_chars` | `100` | Longest search term, in characters. |
| `limits.search_term_words` | `8` | Most words in a search term. |
| `limits.filter_remote_threshold` | `100` | A filter with more options than this loads them as you type. |
| `limits.bulk_inline_max` | `500` | A bulk action on more rows than this runs as a queued batch. |
| `limits.id_list_max` | `1000` | The most ids one request may send. A longer list gets 422. |
| `limits.metrics_ttl` | `60` | Seconds a widget value stays cached. |
| `limits.repeater_rows` | `200` | The most rows a repeater field accepts. |
| `limits.key_value_rows` | `200` | The most rows a key-value field accepts. |
| `limits.key_length` | `255` | The longest key a key-value field accepts. |

See [Limits and performance](limits-and-performance.md) for why each number is what it is.

## Uploads and storage

| Key | Default | Effect |
|---|---|---|
| `uploads.max_file_size_mb` | `10` | The largest file a file field accepts, checked on the server and in the browser. A field's `->maxSize()` overrides it. |
| `uploads.max_image_size_mb` | `5` | The same for image fields. |
| `uploads.chunk_size` | `5242880` | Bytes per chunk of a chunked upload. |
| `uploads.pending_hours` | `24` | Hours a draft upload that was never saved to a record is kept before `lathe:prune` deletes it. |
| `storage_disk` | `LATHE_STORAGE_DISK` (`'public'`) | The disk for uploads when a field names none. It is never taken from the request. |

## Search, notifications, saved tabs and shortcuts

| Key | Default | Effect |
|---|---|---|
| `global_search.enabled` | `true` | Turns the global search palette on or off. |
| `global_search.debounce` | `0.5` | Seconds to wait after typing before searching. |
| `global_search.results` | `5` | Results shown per Resource. |
| `notifications.enabled` | `true` | Shows the notification bell. |
| `notifications.polling_interval` | `7` | Seconds between checks for new notifications. |
| `notifications.show_unread_count` | `false` | Shows the unread number on the bell. |
| `saved_tabs.enabled` | `true` | Lets users save a list view as a tab. |
| `saved_tabs.max_per_resource` | `10` | The most saved tabs per user and Resource. |
| `keyboard_shortcuts.enabled` | `true` | Turns the built-in shortcuts and their header hint on or off. |
| `breadcrumbs.enabled` | `true` | Renders breadcrumbs above each page. |

## Accounts

| Key | Default | Effect |
|---|---|---|
| `fortify.features` | `LATHE_FORTIFY_FEATURES` (`[]`) | The Fortify features to turn on, as a comma list in the variable, such as `update-passwords,two-factor-authentication,email-verification`. The array may also hold `Features::...()` calls. See [Authentication](../security/authentication.md). |
| `fortify.username` | `null` | The column users sign in with. `null` uses Fortify's default. |
| `fortify.email` | `null` | The email column for password resets. |
| `impersonation.started` | `LATHE_IMPERSONATION_STARTED` (`'/'`) | Where the browser goes after impersonation starts. |
| `impersonation.stopped` | `LATHE_IMPERSONATION_STOPPED` (`'/'`) | Where it goes after impersonation stops. |

## Audit log, exports and imports

| Key | Default | Effect |
|---|---|---|
| `action_events.enabled` | `true` | Records each action run in the audit log. |
| `action_events.prune_after_days` | `30` | `lathe:prune` deletes events older than this. |
| `action_events.prune_per_actionable` | `null` | When an integer, `lathe:prune` keeps only that many newest events per record. |
| `exports.disk` | `LATHE_EXPORT_DISK` (`'local'`) | The disk export files are written to. |
| `exports.directory` | `'lathe-exports'` | The folder on that disk. |
| `exports.chunk_size` | `500` | Rows per read chunk and per progress update. |
| `exports.prune_after_hours` | `24` | `lathe:prune` deletes export files older than this. |
| `imports.disk` | `LATHE_IMPORT_DISK` (`'local'`) | The disk uploaded import files are kept on. |
| `imports.directory` | `'lathe-imports'` | The folder on that disk. |
| `imports.chunk_size` | `500` | Rows per chunk and per progress update. |
| `imports.max_file_size_mb` | `50` | The largest import file accepted. |
| `imports.prune_after_hours` | `24` | `lathe:prune` deletes import files older than this. |

## Appearance

| Key | Default | Effect |
|---|---|---|
| `theme.colors` | every token `null` | 40 colour tokens (`background`, `primary`, `sidebar`, `chart-1` and so on). Each is any CSS colour string, or `['light' => ..., 'dark' => ...]`. `null` keeps the style's colour. |
| `theme.appearance` | `'system'` | `light`, `dark` or `system`. |
| `theme.radius` | `'0.625rem'` | The base corner radius. |
| `theme.style` | `'nova'` | One of the 8 styles: `nova`, `vega`, `lyra`, `maia`, `mira`, `luma`, `rhea`, `sera`. Only the chosen style's stylesheet loads. |
| `theme.font` | `null` | `{family, fallback, files}`. `lathe:preset` writes it. |
| `layout.sidebar.collapsible` | `'icon'` | `icon`, `offcanvas` or `none`. |
| `layout.sidebar.default_open` | `true` | Whether the sidebar starts open. |
| `layout.sidebar.width` | `'16rem'` | The open sidebar's width. |
| `layout.sidebar.width_icon` | `'3rem'` | The width when collapsed to icons. |
| `layout.header.sticky` | `true` | Keeps the header in view while scrolling. |
| `layout.header.search` | `true` | Shows the search box in the header. |
| `layout.content.width` | `'full'` | `full` or `contained`. |
| `table.density` | `'comfortable'` | `compact`, `comfortable` or `spacious` row height. |
| `table.striped` | `false` | Zebra rows on every table. |

See [Layout](../organizing/layout.md) for themes, dark mode and right-to-left.

## What you see

Set `'table' => ['density' => 'compact']` in `config/lathe.php` and reload a list: the rows are shorter. Set `'path' => '/admin'`: the panel opens at `/admin`, and `/lathe` answers 404.

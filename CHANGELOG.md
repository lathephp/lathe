# Changelog

All notable changes to Lathe are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and Lathe follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The Composer package `lathe/lathe` and the npm packages `@lathephp/core`, `@lathephp/ui` and `@lathephp/vite-plugin` share one version number. An entry marked `[api]` changes the public API; the [upgrade guide](docs/getting-started/upgrade.md) lists them between two releases.

## [Unreleased]

## [1.0.0-beta.3] - Unreleased

### Added

- [api] The testing kit's `DashboardTester` gains `csv()`, `saveLayout()` and `resetLayout()` for the new dashboard routes.
- [api] Each user can save their own layout of a dashboard (order, size and hidden cards) and reset it to the layout in code, through `PUT` and `DELETE` on `api/dashboards/{dashboard}/layout`. A card removed from code drops out of a saved layout, and a new one shows at the end. `Dashboard::customizable(false)` turns it off for a dashboard, and the dashboard payload gains `customizable`, `customized` and a `hiddenWidgets` prop.
- [api] One range picker in a dashboard's header drives every widget on it. `Dashboard::ranges()` sets its options, 7 days, 30 days and 12 months by default, and the page reads the picked one from `?range=`. A widget that does not offer the picked range shows its default range, and its value response names that range as `fallbackRange`; `Widget::defaultRangeKey()` says which range that is. The workbench's Sales dashboard gains Orders per day and Orders by status. On the page the picker is a segmented control beside "Updated ... ago" and Refresh, keeps the pick in the URL, and each card drops its own range dropdown, names the range it fell back to, dims its last value while it reloads and says "<title> could not load" with Try again when it fails.
- [api] A dashboard's trend, bar, partition, pie, radar and table cards download as CSV from `GET api/dashboards/{dashboard}/widgets/{widget}/csv`, with plain numbers and `YYYY-MM-DD` dates; a widget's descriptor gains `csv`. `Widget::link()` sets where a card's Open records goes, and a card whose aggregates read one model links to that model's Resource index for a user who may list it, as `link` in the value response. The bar, partition, pie and radar helpers now apply the host Resource's row scope and tell the cache which tables they read.
- [api] `SelectField::badges()` shows each value as a badge in a tone: neutral, primary, info, success, warning or danger. The workbench's order statuses use it.
- [api] The `@lathephp/ui/popover` and `@lathephp/ui/select` subpaths, so a page can load the popover or the select without the whole kit.
- [api] The config keys `brand.logo` and `brand.logo_dark` put your logo at the top of the sidebar, with a separate image for the dark theme.
- [api] Each user's column choices on a resource index (shown, hidden, order, pinned) are saved on the server in the new `lathe_user_preferences` table, through `PUT` and `DELETE` on `api/resources/{resource}/columns`. A column removed from code drops out of a saved choice, and a new one shows at the end. Run `php artisan migrate` after upgrading.
- [api] `GET api/resources/{resource}/{resourceId}/history/latest` answers a record's newest five action events (who, what and when) for its History card, behind the same `viewAny` of the action log as the Action Events panel.
- [api] A record page's header shows a badge beside the title and one meta line. `ResourceDefinition::recordHeader(badge:, meta:)` names their detail fields: the badge defaults to the first field with badges, and a named meta field shows as "<Label> <value>" in place of the subtitle.
- [api] `ResourceDefinition::phoneCard(title:, badge:, value:, meta:)` names the index columns a row's card shows below 768 px; a slot left out is picked from the index (the record's title, the first badge, the first number and the first relation). `SelectField::hasBadges()` says whether a select shows badges.
- Index filters apply 300 ms after the last change, with no Apply button. The Filter button opens a popover with the live match count in its foot, shows how many filters are set, and every set filter shows as a chip with a remove button under the toolbar. `Filter::labels()` names the values a remote filter holds for its chip; a filter over a large table overrides it with a lookup of those values.
- Sidebar links and a table row's first cell fetch their page after a 75 ms hover and keep it 30 seconds; any write to the resource drops it. A click shows the next page at once: the sidebar item turns active on press, and if the data has not come after 100 ms the page shows its title and a skeleton of its layout.

### Changed

- [api] An action's dialog is titled by its confirm text, such as "Mark 3 orders as shipped?", or by the action's name when it has none, and its primary button repeats the action's name. Without `size()` a dialog is 400 px for a confirm and 520 px for an action with fields; the metadata's `size` is then `small` or `medium` where it was `2xl`.
- [api] `:title` in an action's confirm text becomes the record's title when it runs on one record, such as "Cancel order ORD-79186507?": the form route `api/resources/{resource}/actions/{action}/form` takes `resourceId` (404 when the detail query cannot find it) and `Action::confirmTextFor()` takes the title as a second argument. The workbench's Cancel order keeps the order with "Keep order".
- A new notification only lights the bell, with a dot in the brand colour or the count with `show_unread_count`, and never raises a toast. The panel groups notifications under Today and Earlier, shows at most two buttons on each, and using a button marks the notification read.
- [api] The import dialog shows its steps, File, Map columns, Check and Import, opens 720 px wide, and its check button names the file's rows, such as "Check 2,400 rows"; the upload step's `import:headers-detected` data gains `total_rows`. The edit-conflict dialog's buttons read "Load their version" and "Save mine over theirs".
- [api] `Action::requirePassword()` asks for the user's password before a run unless it was confirmed within `auth.password_timeout`: the run answers 423, and the open dialog shows "Confirm your password" in place of its content, then runs the action after a correct password, so two dialogs never stack. The workbench's Merge tags uses it. `setPasswordConfirmer()` on the panel's internal object now returns the confirmer it replaces.
- [api] `ResourceDefinition::globalSearchSubtitle()` sets the line under a result in the search palette without changing the record's subtitle; the workbench's orders show "Prof. Warren Shields II · Oct 17, 2026 · $1,234.00" and list three per search. Each Resource group in the `api/search` answer gains `count` (`{value, exact, capped, estimated}`, or null when the group shows every match), counted up to 1,000 only when the group has more matches than it shows, and the palette ends such a group with "Show all 20 orders", which opens the index searched for the term.
- [api] At most three toasts show; older ones fold into "N more" and come back as the shown ones close. Queued bulk runs, exports and imports show in that one stack at the bottom right instead of their own panel at the bottom left: a running task's toast stays and updates in place, a plain result closes after 5 seconds, and a result with failures stays, such as "2,388 of 2,400 products imported" with "Download failed rows" under it. `ToastMessage` gains optional `body` and `dismissible`, and an import's status JSON gains `resource_label`.
- On a phone the search palette fills the screen with a Cancel button, and every dialog is a bottom sheet whose buttons stack 44 px tall with the action on top. On a wider screen the palette is 640 px. The palette marks the words of the term in each result, and a term the server refuses (too long, too many words) shows the server's reason instead of Retry.
- [api] Changed English strings since 1.0.0-beta.2, for an app that translates the panel; move each override to its new key: `Run Action` is gone (the primary button shows the action's name); `Mark all read` reads `Mark all as read`; `Override` reads `Save mine over theirs` and the conflict dialog's `Refresh` reads `Load their version` (the dashboard keeps `Refresh`); `Someone else saved this record after you opened it. Refresh to load their changes, or override to save yours over them.` reads `Someone else saved this record after you opened it. Load their version, or save yours over theirs.`; `Validate` reads `Check :count rows`; `Validate the file before importing. Nothing is saved.` reads `Check the file before importing. Nothing is saved.`; `Validating the file…` reads `Checking the file…`; `Download Failed Rows` reads `Download failed rows`; `Runs` is gone (queued runs show in the toast stack); the password dialog's `Confirm password` reads `Confirm your password` (field labels keep `Confirm password`); `Showing :from to :to of :count` reads `:from–:to of :count`; `Showing :from to :to` reads `:from–:to`; `Next` reads `Next page`; `Previous` reads `Previous page`; `All :count rows on this page are selected.` is gone (the selection bar reads `:count selected` and `All :count selected`); `All matching rows selected (:count)` reads `All matching rows selected`; `Select all matching rows (:count)` reads `Select all matching rows`; `Apply` is gone (filters apply as they change).
- A customizable dashboard has a Customize button. In Customize mode a user moves cards by dragging their handle or with the keyboard, sets a card's size or hides it from its ⋯ menu, adds hidden cards back, then saves the layout for their account, cancels or resets to the default.
- Each dashboard card has a ⋯ menu with Refresh, Open records when the card links to records the user may list, and Download CSV for chart and table cards.
- [api] Dashboard cards in one row share the tallest card's height, and `height()` is now a minimum. A value card shows every digit on a desktop and a short form on a phone, the change as a signed badge, then "vs" and the earlier value; with no earlier data it says so. `ValueResult::lowerIsBetter()` shows a rise in the destructive colour and a fall in the success colour. The grid gap is 20 px, and below 768 px value cards sit two across.
- A pie widget with more than six slices draws as bars: the five largest against the largest, then the rest added up as one Other row.
- [api] The default theme is Lathe's copper design in light and dark. Apps that set `theme.colors` keep their colours. New public tokens: `brand`, `focus`, `skeleton`, `scrim`, `chart-other`, `sidebar-hover`, the tints `primary-subtle`, `success-subtle`, `warning-subtle`, `info-subtle` and `destructive-subtle` with their text colours `primary-subtle-foreground`, `success-subtle-foreground`, `warning-subtle-foreground`, `info-subtle-foreground` and `destructive-subtle-foreground`, and `shadow-xs` to `shadow-lg`. The existing `link` and `link-hover` tokens are copper where they were blue.
- [api] The 16 new colour tokens are `theme.colors` keys and `Lathe\Theme\Color` cases. When an app sets any colour of its own, every token it did not set keeps its value from before copper, so the app keeps its look; `brand` and `focus` take `primary` at 3:1 on the page, and each `-subtle` pair is a tint of its colour with text at 4.5:1.
- [api] Toasts render with Base UI Toast. A toast stays 5 seconds, its timer pauses on hover and focus, and an error stays until it is dismissed. The `@lathephp/ui/sonner` subpath is removed; `@lathephp/ui/toast` replaces it, and sonner is no longer a dependency. `ToastMessage` gains a `timeout` field.
- [api] Date-times read on the 24-hour clock, such as `Oct 17, 2026, 23:12`, where they read `11:12 PM` before.
- A resource index puts its title on a row of its own above one table card: the tab band (saved views and lenses, each lens with its icon), a toolbar row with the search open at 260 px, and the table. Ticking rows turns the toolbar row into the selection bar ("3 selected", "Select all 10,000", the actions, Clear). Rows show View and Edit on hover, and the row menu keeps Delete last. The pager shows First, Previous, page numbers, Next and Last, with the range "1–25 of 10,000"; Last hides when the count is capped. An inline edit's toast offers Undo for 5 seconds. Below 768 px rows are cards and Filter opens a bottom sheet. The lens drop-down is gone.
- Each user can show, hide, reorder and pin the index columns from the picker at the header's right end; pinned columns stay in view when the table scrolls sideways.
- Value and progress widgets show their number at once instead of counting up to it. Tab counts and the table's summary footer hold their space while they load and fade in.
- [api] The user menu's "Log out" reads "Sign out"; an app that translates the panel moves its `Log out` string to `Sign out`. The menu's trigger shows the email under the name.
- [api] A creatable relation field offers `Create customer "Hanna Vos"` at the foot of its search results. Choosing it opens the related create form in a 480 px sheet from the right with the typed text in the related Resource's title field, and saving selects the new record. The field's payload gains `createTitle`, the related title attribute, or null when the title is a closure.
- [api] A record's detail page has two columns. Its to-many relations are tabs in the main column, each with its count when the field has `withCount()`, followed by a card per wide field; the right column holds a Details card with the other fields, a card per belongs-to, morph-to or has-one record, and a History card with the newest five action events and Show all. The header's badge and meta fields and the title field are left out of Details. Sections, panel limits and field tab groups now shape forms only. The detail payload gains `titleKey`, `counts` and `history`, and the detail page runs one more query, the relation counts.
- A create or edit form is centred at 880 px with labels above the fields, its first field full width and short fields in pairs from 1024 px. Cancel and the save buttons sit in a footer stuck to the bottom of the window, which shows "Unsaved changes" while the form holds unsaved values; leaving the page then asks first.
- A failed save scrolls to a summary that links each field needing a change, in the error colour, and focuses the first; a field's error clears as soon as it changes. A readonly field shows on a muted ground with a dashed border, and any other disabled control is dimmed.
- A field shown by `visibleWhen()` sits on its own row under its parent, on a muted ground, with the reason, such as "Shown because Type is Company".

### Deprecated

- [api] `RelationField::modalSize()` is still accepted but no longer sizes anything: the create sheet is always 480 px wide.

### Removed

- [api] A dashboard card no longer has a range dropdown of its own: the one range picker in the dashboard's header sets every card's range, from `Dashboard::ranges()`. A widget's own `ranges()` now only says which of those ranges it can show.

### Fixed

- `lathe:doctor` checks the `lathe_user_preferences` table, so an app that updated without running `php artisan migrate` gets a FAIL there instead of 0 FAIL and a broken index.
- On a phone, the open menu has a Close menu button and closes on Escape, and the user menu closes on a click outside it.
- A record's breadcrumb names it by its title instead of `#id`; the id stays only when the title is empty.
- When a queued bulk action, delete or restore ends, the index it ran on reloads its rows in place.

## [1.0.0-beta.2] - 2026-10-16

### Fixed

- The npm packages name their GitHub repository, so npm accepts their provenance. 1.0.0-beta.1 never reached npm, so use 1.0.0-beta.2.

## [1.0.0-beta.1] - 2026-10-16

The first public release of Lathe, an admin panel builder for Laravel on Inertia, React and shadcn/ui. MIT licensed.

### Added

- Resources: discovery from `app/Lathe/Resources`, the index with search, filters, sorts, lenses, saved tabs, column summaries and row grouping, capped counts that stay fast on tables with millions of rows, detail and form pages, inline edits and peeks.
- Fields: text, number, date, choice, file, image, rich text, Markdown, code and key-value fields and more, with validation, visibility rules, computed values and utility injection, relation fields with pickers, pivots and repeaters, and chunked uploads.
- Actions: single, bulk and queued actions with progress, CSV and Excel export and import with a dry run, and an audit log of every run.
- Dashboards: value, trend, progress, table, help, custom, bar, pie, partition, radar and radial widgets, on dashboards, Resources and lenses.
- Customising: menus, standalone pages, plugins with settings, resource plugins, 8 theme styles with dark mode and right-to-left, keyboard shortcuts and a command palette.
- Accounts: sign-in, password reset, email verification, two-factor authentication, password confirmation and impersonation through Laravel Fortify; policies that deny by default.
- Tooling: `lathe:install`, `lathe:doctor`, the `lathe:*` generators, a testing kit, a Laravel Boost skill and the public API snapshot checked by `lathe:api-check`.

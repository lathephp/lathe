# Changelog

All notable changes to Lathe are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and Lathe follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html). The Composer package `lathe/lathe` and the npm packages `@lathephp/core`, `@lathephp/ui` and `@lathephp/vite-plugin` share one version number. An entry marked `[api]` changes the public API; the [upgrade guide](docs/getting-started/upgrade.md) lists them between two releases.

## [Unreleased]

### Added

- [api] The `@lathephp/ui/popover` and `@lathephp/ui/select` subpaths, so a page can load the popover or the select without the whole kit.
- The config keys `brand.logo` and `brand.logo_dark` put your logo at the top of the sidebar, with a separate image for the dark theme.

### Changed

- [api] The default theme is Lathe's copper design in light and dark. Apps that set `theme.colors` keep their colours. New public tokens: `brand`, `focus`, `skeleton`, `scrim`, `chart-other`, `sidebar-hover`, a `-subtle` and `-subtle-foreground` pair for `primary`, `success`, `warning`, `info` and `destructive`, and `shadow-xs` to `shadow-lg`.
- [api] The 16 new colour tokens are `theme.colors` keys and `Lathe\Theme\Color` cases. When an app sets its own colours, each new token it did not set follows them: `brand`, `focus` and `link` take `primary`, and each `-subtle` pair is a tint of its colour with text at 4.5:1.

### Fixed

- A record's breadcrumb names it by its title instead of `#id`; the id stays only when the title is empty.

## [1.0.0-beta.2] - Unreleased

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

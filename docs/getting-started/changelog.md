---
title: Changelog
description: Where to find what changed in each Lathe release, and how releases are numbered.
order: 7
---

# Changelog

Every change to Lathe is listed in `CHANGELOG.md` at the root of the [lathephp/lathe](https://github.com/lathephp/lathe/blob/main/CHANGELOG.md) repository, and each release on GitHub carries its section of it as release notes.

## How versions work

- Lathe follows Semantic Versioning. The Composer package and the three npm packages (`@lathephp/core`, `@lathephp/ui`, `@lathephp/vite-plugin`) always share one version number.
- An entry marked `[api]` changes the public API. The [upgrade guide](upgrade.md) lists them between two releases, with the other entries under Changed as behaviour changes.
- Every change of behaviour, including a changed default, has an entry.

## 1.0.0-beta.1

The first public release: an admin panel builder for Laravel on Inertia, React and shadcn/ui, under the MIT licence. It holds Resources with search, filters, lenses, saved tabs and capped counts for tables with millions of rows; forms and detail pages with many field types and relations; single, bulk and queued actions, exports and imports with an audit log; dashboards with eleven widget types; menus, pages, plugins and eight theme styles; and accounts with two-factor authentication and impersonation.

`1.0.0` follows after four weeks with no issue that forces an API change. An issue that does force one is fixed in the next beta, and the four weeks start again.

## What you see

`composer show lathe/lathe` prints the version you have installed; its section in `CHANGELOG.md` says what it holds.

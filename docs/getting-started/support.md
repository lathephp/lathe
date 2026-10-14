---
title: Support
description: The platforms Lathe runs on, which versions get fixes, and how to report a security problem.
order: 9
---

# Support

## Platforms

Lathe 1.x runs on:

- PHP 8.4+
- Laravel 13
- MySQL 8.4+
- MariaDB 10.11+
- PostgreSQL 15+
- SQLite

Every combination above runs in Lathe's CI on each change. A minimum version rises only in a new major version of Lathe.

## Versions and fixes

Lathe follows Semantic Versioning, for the Composer package and the npm packages together. The first public release is `1.0.0-beta.1`; `1.0.0` follows after four weeks with no issue that forces an API change.

- The latest major version gets bug fixes. A confirmed bug is fixed in the next patch release.
- The previous major version gets security fixes for 12 months after the next major version ships.
- A deprecated API keeps working for at least one minor version and is removed only in the next major version.

Every change of behaviour is listed in the [changelog](changelog.md), and the [upgrade guide](upgrade.md) lists the API changes between two releases.

## Reporting a security problem

Do not open a public issue. Use GitHub private vulnerability reporting on the lathephp/lathe repository, or email security@lathephp.com. A confirmed critical vulnerability is fixed and released within 7 days. The full policy is in `SECURITY.md` in the repository.

## Getting help

Ask questions and report bugs in the GitHub issues of lathephp/lathe. Include your PHP, Laravel and Lathe versions and the output of `php artisan lathe:doctor`.

## What you see

`php artisan lathe:doctor` prints your PHP and Laravel versions among its checks, so you can compare them with the list above.

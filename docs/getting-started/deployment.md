---
title: Deployment
description: "What a production server needs for Lathe: a queue worker, the scheduler, the caches and a check."
order: 3
---

# Deployment

Lathe deploys like any Laravel app. Its built JavaScript and CSS ship inside the package, so a deploy needs no Node and no build step for the panel itself.

## Queue worker

Lathe runs long work in the queue: a bulk action over more than `limits.bulk_inline_max` rows (500 by default), every import, queued exports and the notifications they send. Without a worker these stay at "queued".

Run at least one worker and restart it on each deploy:

```bash
php artisan queue:work --tries=1
php artisan queue:restart   # in your deploy script
```

Use a process monitor such as Supervisor or a systemd unit to keep it running. The database queue driver works; Redis is faster for many users. Bulk runs use job batches, so the `job_batches` table must exist (`php artisan make:queue-batches-table` on a new app).

## Scheduler

Lathe schedules `lathe:prune` daily for you. It deletes old action events, export and import files, draft uploads and finished bulk runs, following the `*.prune_*` keys in [Configuration](configuration.md). Run Laravel's scheduler as usual:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Caches

`php artisan optimize` also runs `lathe:cache`, which stores the list of Resources so discovery does not scan `app/Lathe` on each request. `php artisan optimize:clear` runs `lathe:clear`. Run `optimize` in your deploy script after the code is in place.

## Assets

The panel's assets are served from the package with a content hash in their address, so browsers fetch new ones after an update and keep them cached otherwise. If a plugin registers its own scripts or styles, copy them on deploy:

```bash
php artisan lathe:assets --force
```

## A deploy script

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan lathe:assets --force
php artisan queue:restart
php artisan lathe:doctor
```

`lathe:doctor` exits 1 when a check fails, so it can stop a deploy.

## Production checklist

- `APP_DEBUG=false`, and the `viewLathe` gate lists only the people who should get in (see [Installation](installation.md#allow-the-user-in)).
- A queue worker and the scheduler run.
- `php artisan lathe:doctor` ends with `0 FAIL`.
- Uploads go to a disk you back up (`storage_disk`, `exports.disk`, `imports.disk`).
- Large tables keep their sorted and searched columns indexed (see [Limits and performance](limits-and-performance.md)).

Lathe makes no outbound call from the panel: no licence check, no telemetry, no update check.

## What you see

After the deploy script, `lathe:doctor` prints its checks and ends with `0 FAIL`, and a bulk action over 500 rows shows a progress toast that reaches 100%.

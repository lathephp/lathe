# Live demo runbook

How to put `demo.lathephp.com` on its own server, check its guard rails, reset it and
redeploy it. Run every command as root on the demo server unless a step says otherwise.

The demo server is separate from the benchmark server: 2 dedicated vCPUs, 8 GB RAM,
80 GB NVMe, Ubuntu 24.04, amd64 or arm64.

No secret is written here or in the repository. `deploy.sh` generates the database password
and Laravel's `APP_KEY` on the server and keeps both in `/srv/lathe-demo/.env`
(mode 640, group www-data). The demo's sign-in, admin@lathephp.com with the password
`password`, is public on purpose: the login screen prints it.

## What runs where

| Part | Where |
| --- | --- |
| Laravel 13 app with the workbench copied over | `/srv/lathe-demo` |
| Snapshot the reset restores | `/var/lib/lathe-demo/snapshot.sql.zst` |
| MySQL 8.4 (generic build, see below) | `/opt/mysql`, data in `/var/lib/mysql`, config `/etc/mysql/my.cnf` |
| Caddy with the rate-limit module | `/usr/local/bin/caddy`, config `/etc/caddy/Caddyfile` |
| PHP-FPM limits | `/etc/php/8.4/fpm/conf.d/99-lathe-demo.ini` |
| Downloads kept for a redeploy | `/var/cache/lathe-demo` |
| systemd units | `lathe-demo-mysql`, `lathe-demo-caddy`, `lathe-demo-queue`, `lathe-demo-schedule`, `lathe-demo-reset.timer`, plus Ubuntu's `php8.4-fpm` |

Two choices made by the script:

- **MySQL 8.4 comes from Oracle's generic Linux build** (`mysql-8.4.11-linux-glibc2.28-<arch>.tar.xz`
  from cdn.mysql.com), not from apt. MySQL's APT repository has no arm64 packages for
  Ubuntu 24.04, and one install path for both architectures is the path the rehearsal tested.
  It gets no apt security updates: to move to a newer 8.4 release, change `mysql_version` in
  `deploy.sh` and redeploy.
- **Caddy comes from caddyserver.com's download API** with the plugin
  `github.com/mholt/caddy-ratelimit`, since stock Caddy has no rate limiter. The binary is
  kept in `/var/cache/lathe-demo/caddy-<arch>`; delete that file before a redeploy to get a
  newer Caddy.

## 1. Check the server

```sh
nproc            # prints 2
free -g          # the Mem: total column reads 7 or 8
dig +short demo.lathephp.com   # run on your own machine; prints the server's address
```

DNS must point at the server before step 3, because Caddy asks Let's Encrypt for the
certificate when it starts.

## 2. Get the release

```sh
apt-get update && apt-get install -y git tmux
git clone https://github.com/lathephp/lathe.git /opt/lathe-src
cd /opt/lathe-src
git checkout v1.0.0-beta.1     # the tag of the release the demo shows
```

`deploy.sh` copies the workbench from this checkout and installs `lathe/lathe` and
`lathe/permission` from Packagist with the constraint `^1.0@beta`. Both packages must be on
Packagist first. If `lathe/permission` gets a version outside `^1.0@beta`, change
`permission=` at the top of `deploy.sh`.

## 3. Deploy

The first deploy seeds 10,000,000 orders, so run it inside tmux to survive a dropped SSH
session:

```sh
tmux new -s demo
bash scripts/demo/deploy.sh
```

It prints one `==` line per step, then the step's own output:

```
== install PHP 8.4, zstd and the tools
PHP 8.4.x
== install Composer
Composer version 2.x ...
== install Caddy with github.com/mholt/caddy-ratelimit
v2.x.x h1:...
== install MySQL 8.4.11
== configure and start MySQL
mysql  Ver 8.4.11 for Linux on x86_64 (MySQL Community Server - GPL)
== create a Laravel 13 app in /srv/lathe-demo
== require lathe/lathe:^1.0@beta and lathe/permission:^1.0@beta
lathe/lathe * 1.0.0-beta.1
== copy the workbench over the app
== write the demo's .env and config/lathe.php
== create the database and its user
== migrate
   (one DONE line per migration)
== seed 10000000 orders
Seeded 10,000,000 orders in N s.
== write the snapshot
reset: wrote /var/lib/lathe-demo/snapshot.sql.zst (size)
== start PHP-FPM, Caddy, the queue worker, the scheduler and the reset timer
the demo is up at https://demo.lathephp.com/lathe; sign in as admin@lathephp.com with the password: password
```

A failure prints `deploy: <reason>` and stops with a non-zero exit code. The script can be
run again after the cause is fixed.

**Seed time.** `lathe:demo-seed --orders=10000000` took **557.6 s** on the build machine
(the measured figure, not an estimate). The demo server has 2 vCPUs, so it may take longer; the seed prints its
own elapsed time. The MySQL download (about 830 MB), the snapshot dump and the compression
come on top of that.

Options, for reference: `--orders N` seeds another count, `--site ADDRESS` serves another
address (`:80` is plain HTTP with no certificate), `--buffer-pool SIZE` sets InnoDB's buffer
pool (default 4G), `--archive DIR` installs the packages from local composer archives, and
`--no-systemd` starts the services as plain processes (both for the rehearsal).

## 4. Check the guard rails

Run these on the server unless they say "from your machine".

| Guard rail | How to check | Expected |
| --- | --- | --- |
| Login screen answers | from your machine: `curl -s -o /dev/null -w '%{http_code}\n' https://demo.lathephp.com/lathe/login` | `200` |
| Sign-in printed on the login screen | open https://demo.lathephp.com/lathe/login in a browser | under the form: "Sign in as admin@lathephp.com with the password password." |
| 10 million orders | sign in, open Orders | footer "Showing 1 to 25 of 10,000,000" (or "10,000+" while the count is estimated) |
| Mail goes to the log | `grep ^MAIL_MAILER /srv/lathe-demo/.env` | `MAIL_MAILER=log` |
| Upload, import and export limits | `cd /srv/lathe-demo && runuser -u www-data -- env HOME=/tmp php artisan tinker --execute 'echo implode(" ", [config("lathe.uploads.max_file_size_mb"), config("lathe.uploads.max_image_size_mb"), config("lathe.imports.max_file_size_mb"), config("lathe.exports.prune_after_hours")]);'` | `1 1 1 1` |
| Request bodies over 1 MiB refused | from your machine: `head -c 1572864 /dev/urandom \| curl -s -o /dev/null -w '%{http_code}\n' -X PUT --data-binary @- https://demo.lathephp.com/lathe/api/uploads/x/chunks/0` | `413` |
| A 1.5 MB image refused | in the panel, upload a 1.5 MB cover on a Post | "The size field must not be greater than 1048576." |
| 60 requests a minute per address | from your machine, once the steps above are a minute old: `for i in $(seq 61); do curl -s -o /dev/null -w '%{http_code} ' https://demo.lathephp.com/up; done; echo` | sixty `200`, then `429` |
| PHP-FPM limits | `php-fpm8.4 -i \| grep -E '^(memory_limit\|max_execution_time\|upload_max_filesize\|post_max_size) '` | `256M`, `30`, `1M`, `1M` |
| Services up | `systemctl is-active lathe-demo-mysql lathe-demo-caddy php8.4-fpm lathe-demo-queue lathe-demo-schedule` | five `active` lines |
| Reset scheduled | `systemctl list-timers lathe-demo-reset.timer` | next run at 03:00 UTC |
| No analytics, no outbound call | `grep -ciE 'analytics\|gtag\|plausible' /srv/lathe-demo/.env` and view the login page source | `0`, and no script or font from another host |

The rate limit counts the page and API requests from one address; the panel's built files
(`/lathe/assets`, `/lathe/modules`, `/lathe/styles`, `/vendor`) do not count. A dashboard view
still uses one request per widget. Each check in the table uses some too: wait a minute before
the rate-limit check.

## 5. The nightly reset

`lathe-demo-reset.timer` starts `lathe-demo-reset.service` at 03:00 UTC every day. It runs
`/usr/local/bin/lathe-demo-reset` (a copy of `scripts/demo/reset.sh`) as www-data:

1. `php artisan down`: every page answers 503 with the maintenance screen.
2. `php artisan db:wipe --force`, then `zstd -dc snapshot.sql.zst | mysql lathe_demo`, then
   it removes the files visitors left: everything in `storage/app/public` (uploads) and the
   `lathe-uploads`, `lathe-exports` and `lathe-imports` folders in `storage/app/private`.
3. `php artisan migrate --force`: a snapshot taken before a redeploy gets that release's new
   migrations back.
4. `php artisan up`.

It should finish well within 30 minutes. To run it now and read what it printed:

```sh
systemctl start lathe-demo-reset.service    # returns when the reset is done
journalctl -u lathe-demo-reset.service -n 20
```

The journal shows `Application is now in maintenance mode.`, then
`Dropped all tables successfully.`, then `reset: restored lathe_demo from /var/lib/lathe-demo/snapshot.sql.zst in N s`, then
`reset: removed the files visitors uploaded, exported and imported`, then the migrate
output (`Nothing to migrate.` unless the snapshot is older than the release), then
`Application is now live.`

If the restore or the migrate fails, the script stops with the app still down, so no
visitor sees a half-restored store. Read the journal, fix the cause, then run `systemctl start
lathe-demo-reset.service` again; `cd /srv/lathe-demo && runuser -u www-data -- php artisan up`
brings the app back without a restore.

## 6. Redeploy

To ship a new release:

```sh
cd /opt/lathe-src
git fetch --tags && git checkout v1.0.0-beta.2   # the new tag
bash scripts/demo/deploy.sh
```

A redeploy updates `lathe/lathe` and `lathe/permission`, copies the workbench again, runs
new migrations, restarts the queue worker and the scheduler, and keeps the database, the
`.env` and the snapshot. It prints `keeping the database and the snapshot at ...` instead
of seeding.

The nightly reset runs the new release's migrations on the restored snapshot. When a
release changes what the demo seeds, the snapshot no longer matches: delete it and
redeploy, which seeds again and writes a new one (about 10 minutes, see the seed time
above):

```sh
rm /var/lib/lathe-demo/snapshot.sql.zst
bash scripts/demo/deploy.sh
```

The copy adds and replaces files but removes none: a file deleted from the workbench stays
in `/srv/lathe-demo/app`. To build the app from scratch, remove its folder and redeploy:

```sh
rm -rf /srv/lathe-demo
bash scripts/demo/deploy.sh
```

The new `.env` gets a new `APP_KEY` and a new database password, and the script sets that
password on the MySQL user. The database and the snapshot stay; everyone signed in is
signed out.

## Rehearsal

`bash scripts/demo/rehearse.sh` on a machine with podman runs this deploy in an Ubuntu 24.04
container at 1,000 orders and checks the guard rails above. It ends with `rc=0` when every
check passes.

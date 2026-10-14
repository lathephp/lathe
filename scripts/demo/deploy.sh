#!/usr/bin/env bash
# Builds the live demo on a fresh Ubuntu 24.04 server. Run it as root from a checkout of
# lathe/lathe at the release tag the demo shows, or before the release at the commit pack.sh packed:
#   bash scripts/demo/deploy.sh [--orders N] [--site ADDRESS] [--archive DIR] [--buffer-pool SIZE] [--no-systemd]
# It installs PHP 8.4 FPM, Caddy with the rate-limit module, MySQL 8.4 and zstd; creates a
# Laravel 13 app in /srv/lathe-demo; requires lathe/lathe and lathe/permission, from
# Packagist or, before they are published there, from pack.sh's archives; copies the
# workbench's app, database, routes and built extensions over it; and runs one queue worker,
# the scheduler and the 03:00 UTC reset. The first run seeds --orders orders (default
# 10,000,000) and writes the snapshot the reset restores. A later run updates the code and
# keeps the database, the .env and the snapshot.
#   --site         Caddy's site address (default demo.lathephp.com; :80 serves plain HTTP)
#   --archive      a folder of composer archives from pack.sh to require the packages from
#   --buffer-pool  MySQL's InnoDB buffer pool (default 4G, half of the server's 8 GB)
#   --no-systemd   start the services as plain processes, for a container without systemd
set -euo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
demo=$repo/scripts/demo
app=/srv/lathe-demo
state=/var/lib/lathe-demo
snapshot=$state/snapshot.sql.zst
cache=/var/cache/lathe-demo
mysql_version=8.4.11
laravel='^13.0'
lathe='^1.0@beta'
permission='^1.0@beta'

orders=10000000
site=demo.lathephp.com
archive=
buffer_pool=4G
systemd=1

step() { echo "== $*"; }
fail() { echo "deploy: $*" >&2; exit 1; }

while (($#)); do
    case $1 in
        --orders) orders=${2:?--orders needs a number}; shift 2 ;;
        --site) site=${2:?--site needs an address}; shift 2 ;;
        --archive) archive=${2:?--archive needs a folder}; shift 2 ;;
        --buffer-pool) buffer_pool=${2:?--buffer-pool needs a size}; shift 2 ;;
        --no-systemd) systemd=0; shift ;;
        *) fail "unknown option $1" ;;
    esac
done

[[ $orders =~ ^[1-9][0-9]*$ ]] || fail "--orders must be a whole number of 1 or more"
[[ $EUID -eq 0 ]] || fail "run it as root"
# shellcheck source=/dev/null
. /etc/os-release
[[ $ID == ubuntu && $VERSION_ID == 24.04 ]] || fail "it needs Ubuntu 24.04, found $PRETTY_NAME"

case $(dpkg --print-architecture) in
    amd64) arch=amd64 mysql_arch=x86_64 ;;
    arm64) arch=arm64 mysql_arch=aarch64 ;;
    *) fail "no MySQL 8.4 build for $(dpkg --print-architecture)" ;;
esac

if [[ $site == :* ]]; then app_url=http://localhost; else app_url=https://$site; fi

export DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1
mkdir -p "$cache"

as_app() { runuser -u www-data -- "$@"; }
artisan() { (cd "$app" && as_app php artisan "$@"); }

# The last NAME= line of the app's .env.
env_value() { sed -n "s/^$1=//p" "$app/.env" | tail -n 1; }

# Sets NAME=value in the app's .env, over a commented-out line when the skeleton has one.
# awk reads both from the environment, so a backslash in the value is written as it is.
set_env() {
    K=$1 V=$2 awk '
        BEGIN { k = ENVIRON["K"]; v = ENVIRON["V"] }
        !done && ($0 ~ "^" k "=" || $0 ~ "^# *" k "=") { print k "=" v; done = 1; next }
        { print }
        END { if (!done) print k "=" v }
    ' "$app/.env" >"$app/.env.new"
    mv "$app/.env.new" "$app/.env"
}

# Starts a long-running artisan command as www-data unless it already runs (no systemd only).
start_worker() {
    pgrep -f "artisan $1" >/dev/null && return
    (cd "$app" && setsid runuser -u www-data -- php artisan "$@" >>"/var/log/lathe-demo-$1.log" 2>&1 </dev/null &)
}

step "install PHP 8.4, zstd and the tools"
apt-get update -qq
apt-get install -y -qq --no-install-recommends ca-certificates curl gnupg unzip xz-utils zstd openssl libaio1t64 libnuma1 libncurses6 >/dev/null
if [[ ! -f /etc/apt/sources.list.d/ondrej-php.list ]]; then
    # Ubuntu 24.04 ships PHP 8.3; Lathe needs 8.4, which the ondrej/php archive builds for amd64 and arm64.
    curl -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C' \
        | gpg --dearmor --yes -o /usr/share/keyrings/ondrej-php.gpg
    echo "deb [signed-by=/usr/share/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
        >/etc/apt/sources.list.d/ondrej-php.list
    apt-get update -qq
fi
apt-get install -y -qq --no-install-recommends php8.4-cli php8.4-fpm php8.4-mysql php8.4-intl php8.4-mbstring \
    php8.4-xml php8.4-zip php8.4-curl php8.4-bcmath php8.4-gd >/dev/null
php -r 'echo "PHP ", PHP_VERSION, PHP_EOL;'

if ! command -v composer >/dev/null; then
    step "install Composer"
    curl -fsSL https://getcomposer.org/installer -o "$cache/composer-setup.php"
    [[ $(curl -fsSL https://composer.github.io/installer.sig) == "$(php -r "echo hash_file('sha384', '$cache/composer-setup.php');")" ]] \
        || fail "the Composer installer does not match its published checksum"
    php "$cache/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm "$cache/composer-setup.php"
fi
composer --version

# grep reads the whole list, so caddy never writes into a closed pipe.
if ! /usr/local/bin/caddy list-modules 2>/dev/null | grep -x http.handlers.rate_limit >/dev/null; then
    step "install Caddy with github.com/mholt/caddy-ratelimit"
    # caddyserver.com builds Caddy with the listed plugins on request; stock Caddy has no rate limiter.
    if [[ ! -s $cache/caddy-$arch ]]; then
        curl -fsSL -o "$cache/caddy-$arch.partial" "https://caddyserver.com/api/download?os=linux&arch=$arch&p=github.com%2Fmholt%2Fcaddy-ratelimit"
        mv "$cache/caddy-$arch.partial" "$cache/caddy-$arch"
    fi
    install -m 0755 "$cache/caddy-$arch" /usr/local/bin/caddy
fi
/usr/local/bin/caddy list-modules | grep -x http.handlers.rate_limit >/dev/null || fail "Caddy has no rate_limit module"
/usr/local/bin/caddy version

name=mysql-$mysql_version-linux-glibc2.28-$mysql_arch
if [[ ! -x /opt/$name/bin/mysqld ]]; then
    step "install MySQL $mysql_version"
    # MySQL's APT repository has no arm64 build for Ubuntu 24.04, so every server takes the generic build.
    if [[ ! -s $cache/$name.tar.xz ]]; then
        curl -fsSL -o "$cache/$name.tar.xz.partial" "https://cdn.mysql.com/Downloads/MySQL-8.4/$name.tar.xz" \
            || curl -fsSL -o "$cache/$name.tar.xz.partial" "https://downloads.mysql.com/archives/get/p/23/file/$name.tar.xz"
        mv "$cache/$name.tar.xz.partial" "$cache/$name.tar.xz"
    fi
    tar -xJf "$cache/$name.tar.xz" -C /opt --exclude="$name/bin/mysqld-debug" --exclude="$name/lib/plugin/debug" \
        --exclude="$name/include" --exclude="$name/man" --exclude="$name/docs"
fi
ln -sfn "/opt/$name" /opt/mysql
for tool in mysql mysqld mysqldump mysqladmin; do ln -sf "/opt/mysql/bin/$tool" "/usr/local/bin/$tool"; done
# Ubuntu 24.04 renamed libaio.so.1 to libaio.so.1t64; the generic build asks for the old name.
libaio=$(find /usr/lib -name libaio.so.1t64 -print -quit)
[[ -n $libaio ]] && ln -sf "$libaio" "$(dirname "$libaio")/libaio.so.1"
missing=$(ldd /opt/mysql/bin/mysqld /opt/mysql/bin/mysql /opt/mysql/bin/mysqldump | grep 'not found' || true)
[[ -z $missing ]] || fail "MySQL is missing libraries: $missing"

step "configure and start MySQL"
id mysql >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin mysql
mkdir -p /etc/mysql /var/lib/mysql /var/log/mysql /run/mysqld
chown mysql:mysql /var/lib/mysql /var/log/mysql /run/mysqld
chmod 750 /var/lib/mysql
cat >/etc/mysql/my.cnf <<EOF
[mysqld]
user = mysql
basedir = /opt/mysql
datadir = /var/lib/mysql
socket = /run/mysqld/mysqld.sock
pid-file = /run/mysqld/mysqld.pid
log-error = /var/log/mysql/error.log
bind-address = 127.0.0.1
mysqlx = OFF
# The demo has one server and restores from its own snapshot, so it keeps no binary log.
disable-log-bin
performance_schema = OFF
innodb_buffer_pool_size = $buffer_pool
plugin-load-add = auth_socket.so

[client]
socket = /run/mysqld/mysqld.sock
EOF
if [[ -z $(ls -A /var/lib/mysql) ]]; then
    mysqld --defaults-file=/etc/mysql/my.cnf --initialize-insecure
fi
if ((systemd)); then
    install -m 0644 "$demo/systemd/lathe-demo-mysql.service" /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable --now lathe-demo-mysql.service
elif ! mysqladmin ping --silent >/dev/null 2>&1; then
    mysqld --defaults-file=/etc/mysql/my.cnf --daemonize >/dev/null
fi
for _ in $(seq 60); do mysqladmin ping --silent >/dev/null 2>&1 && break; sleep 1; done
mysqladmin ping --silent >/dev/null || fail "MySQL did not start; see /var/log/mysql/error.log"
# root signs in through its Unix account only, so root has no password to keep.
mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket"
mysql --version

if [[ ! -f $app/artisan ]]; then
    step "create a Laravel 13 app in $app"
    composer create-project "laravel/laravel:$laravel" "$app" --prefer-dist -q
fi
cd "$app"

step "require lathe/lathe:$lathe and lathe/permission:$permission"
if [[ -n $archive ]]; then
    composer config repositories.lathe "{\"type\": \"artifact\", \"url\": \"$archive\"}"
    # A new pack keeps the version number, and Composer keeps a version it has installed.
    composer remove lathe/lathe lathe/permission --no-scripts -q
else
    # A deploy before the release left the archives' repository in place; Packagist takes over.
    composer config --unset repositories.lathe
fi
# Without scripts, so package discovery runs once the workbench's providers are in place.
composer require "lathe/lathe:$lathe" "lathe/permission:$permission" -W --no-scripts -q
composer show lathe/lathe | sed -n 's/^versions *: /lathe\/lathe /p'

step "copy the workbench over the app"
cp -r "$repo/workbench/app/." app/
cp -r "$repo/workbench/database/." database/
cp -r "$repo/workbench/routes/." routes/
mkdir -p public/vendor/lathe
rm -rf public/vendor/lathe/extensions
cp -r "$repo/workbench/public/vendor/lathe/extensions" public/vendor/lathe/
cat >bootstrap/providers.php <<'EOF'
<?php

return [
    App\Providers\AppServiceProvider::class,
    Workbench\App\Providers\WorkbenchServiceProvider::class,
    Workbench\App\Providers\LatheServiceProvider::class,
];
EOF
# The workbench keeps its Workbench\ namespaces, so the copied classes load from the app's folders.
# shellcheck disable=SC2016 # PHP code, not shell
php -r '
    $json = json_decode(file_get_contents("composer.json"), true);
    $json["autoload"]["psr-4"] = [
        ...$json["autoload"]["psr-4"],
        "Workbench\\App\\" => "app/",
        "Workbench\\Database\\Factories\\" => "database/factories/",
        "Workbench\\Database\\Seeders\\" => "database/seeders/",
    ];
    file_put_contents("composer.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
'
composer dump-autoload -q

step "write the demo's .env and config/lathe.php"
password=$(env_value DB_PASSWORD)
# Generated here on the first run and kept in .env after, so no password lives in the repository.
[[ -n $password ]] || password=$(openssl rand -hex 24)
set_env APP_NAME '"Lathe Demo"'
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$app_url"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE lathe_demo
set_env DB_USERNAME lathe_demo
set_env DB_PASSWORD "$password"
set_env MAIL_MAILER log
set_env QUEUE_CONNECTION database
set_env SESSION_DRIVER file
set_env CACHE_STORE file
set_env AUTH_MODEL 'Workbench\App\Models\User'
set_env LATHE_DEMO true
set_env LATHE_IMPERSONATION_STARTED /lathe
# Every visitor shares the printed sign-in, so no account screen may change a password or
# turn on two-factor and lock the others out.
set_env LATHE_FORTIFY_FEATURES email-verification
[[ -n $(env_value APP_KEY) ]] || php artisan key:generate --force -q

php artisan vendor:publish --tag=lathe-config --force -q
# Uploads and imports are capped at 1 MB and exports are pruned after an hour.
# shellcheck disable=SC2016 # PHP code, not shell
php -r '
    $file = "config/lathe.php";
    $config = file_get_contents($file);
    $patterns = [
        "/(\x27uploads\x27 => \[\s*\x27max_file_size_mb\x27 => )\d+/",
        "/(\x27max_image_size_mb\x27 => )\d+/",
        "/(\x27exports\x27 => \[[^\]]*?\x27prune_after_hours\x27 => )\d+/",
        "/(\x27imports\x27 => \[[^\]]*?\x27max_file_size_mb\x27 => )\d+/",
    ];
    foreach ($patterns as $pattern) {
        $config = preg_replace($pattern, "\${1}1", $config, -1, $count);
        $count === 1 || exit("config/lathe.php has no single match for $pattern\n");
    }
    file_put_contents($file, $config);
'

step "create the database and its user"
mysql -uroot <<EOF
CREATE DATABASE IF NOT EXISTS lathe_demo;
CREATE USER IF NOT EXISTS 'lathe_demo'@'127.0.0.1' IDENTIFIED BY '$password';
ALTER USER 'lathe_demo'@'127.0.0.1' IDENTIFIED BY '$password';
GRANT ALL ON lathe_demo.* TO 'lathe_demo'@'127.0.0.1';
EOF

chown -R www-data:www-data storage bootstrap/cache
chmod 640 .env
chgrp www-data .env
[[ -L public/storage ]] || php artisan storage:link -q

step "migrate"
artisan migrate --force

mkdir -p "$state"
chown www-data:www-data "$state"
install -m 0755 "$demo/reset.sh" /usr/local/bin/lathe-demo-reset
if [[ ! -f $snapshot ]]; then
    step "seed $orders orders"
    # The seed holds more than FPM's 256 MB; the CLI has no memory limit of its own on Ubuntu.
    artisan lathe:demo-seed --orders="$orders"
    step "write the snapshot"
    as_app env LATHE_DEMO_APP="$app" LATHE_DEMO_SNAPSHOT="$snapshot" /usr/local/bin/lathe-demo-reset snapshot
else
    echo "keeping the database and the snapshot at $snapshot"
fi

step "start PHP-FPM, Caddy, the queue worker, the scheduler and the reset timer"
install -m 0644 "$demo/php.ini" /etc/php/8.4/fpm/conf.d/99-lathe-demo.ini
mkdir -p /etc/caddy
sed "s|@SITE@|$site|" "$demo/Caddyfile" >/etc/caddy/Caddyfile
if ! validation=$(/usr/local/bin/caddy validate --config /etc/caddy/Caddyfile 2>&1); then
    echo "$validation" >&2
    fail "Caddy refused /etc/caddy/Caddyfile"
fi
if ((systemd)); then
    id caddy >/dev/null 2>&1 || useradd --system --home-dir /var/lib/caddy --create-home --shell /usr/sbin/nologin caddy
    # Caddy talks to PHP-FPM over its socket, which the www-data group may use.
    usermod -aG www-data caddy
    for unit in lathe-demo-caddy.service lathe-demo-queue.service lathe-demo-schedule.service lathe-demo-reset.service lathe-demo-reset.timer; do
        install -m 0644 "$demo/systemd/$unit" /etc/systemd/system/
    done
    systemctl daemon-reload
    systemctl restart php8.4-fpm.service
    # A Caddy that already ran reloads the new Caddyfile; a first start reads it anyway.
    caddy_was_active=0
    systemctl is-active --quiet lathe-demo-caddy.service && caddy_was_active=1
    systemctl enable --now lathe-demo-caddy.service lathe-demo-queue.service lathe-demo-schedule.service lathe-demo-reset.timer
    if ((caddy_was_active)); then systemctl reload lathe-demo-caddy.service; fi
    # Workers that ran the old code stop after their job and systemd starts them again.
    artisan queue:restart
    systemctl restart lathe-demo-schedule.service
else
    mkdir -p /run/php
    if [[ -f /run/php/php8.4-fpm.pid ]] && kill -0 "$(cat /run/php/php8.4-fpm.pid)" 2>/dev/null; then
        kill -USR2 "$(cat /run/php/php8.4-fpm.pid)"
    else
        php-fpm8.4
    fi
    if pgrep -x caddy >/dev/null; then
        /usr/local/bin/caddy reload --config /etc/caddy/Caddyfile --force
    else
        /usr/local/bin/caddy start --config /etc/caddy/Caddyfile >/var/log/lathe-demo-caddy.log 2>&1
    fi
    start_worker queue:work --tries=1 --max-time=3600
    start_worker schedule:work
    echo "no systemd: the 03:00 UTC reset is not scheduled; run lathe-demo-reset by hand"
fi

echo "the demo is up at $app_url/lathe; sign in as admin@lathephp.com with the password: password"

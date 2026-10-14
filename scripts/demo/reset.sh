#!/usr/bin/env bash
# Writes the live demo's database snapshot, and puts the database back from it every night.
#   reset.sh                take the app down, restore the snapshot, migrate, bring the app up
#   reset.sh <step>...      run only the named steps: snapshot, down, restore, migrate, up
# The restore also removes the files visitors uploaded, exported and imported.
# LATHE_DEMO_APP is the app folder (default /srv/lathe-demo); its .env names the database.
# LATHE_DEMO_SNAPSHOT is the snapshot (default /var/lib/lathe-demo/snapshot.sql.zst).
# A failed restore exits non-zero and leaves the app down, so no visitor sees a half-restored store.
set -euo pipefail

app=${LATHE_DEMO_APP:-/srv/lathe-demo}
snapshot=${LATHE_DEMO_SNAPSHOT:-/var/lib/lathe-demo/snapshot.sql.zst}

fail() { echo "reset: $*" >&2; exit 1; }

[[ -f $app/.env ]] || fail "no .env in $app"

# The last NAME= line of the app's .env, without surrounding double quotes.
env_value() { sed -n "s/^$1=//p" "$app/.env" | tail -n 1 | sed 's/^"\(.*\)"$/\1/'; }

database=$(env_value DB_DATABASE)
connect=(--host="$(env_value DB_HOST)" --port="$(env_value DB_PORT)" --user="$(env_value DB_USERNAME)")
# The password goes through the environment, so it never shows in the process list.
MYSQL_PWD=$(env_value DB_PASSWORD)
export MYSQL_PWD

snapshot_step() {
    mkdir -p "$(dirname "$snapshot")"
    mysqldump "${connect[@]}" --single-transaction --quick --no-tablespaces "$database" \
        | zstd -q -T0 -f -o "$snapshot.partial"
    mv "$snapshot.partial" "$snapshot"
    echo "reset: wrote $snapshot ($(du -h "$snapshot" | cut -f1))"
}

down_step() {
    (cd "$app" && php artisan down)
}

restore_step() {
    [[ -f $snapshot ]] || fail "no snapshot at $snapshot"
    local started=$SECONDS
    # The dump recreates only the tables it holds. A table a later release added would stay
    # without its migrations row and stop the migrate step, so every table goes first.
    (cd "$app" && php artisan db:wipe --force)
    zstd -q -dc "$snapshot" | mysql "${connect[@]}" "$database"
    echo "reset: restored $database from $snapshot in $((SECONDS - started)) s"
    # The public disk holds only visitors' uploads, and the local disk keeps upload chunks,
    # exports and imports in these folders. The leading dot files stay.
    rm -rf "$app"/storage/app/public/* "$app"/storage/app/private/lathe-{uploads,exports,imports}
    echo "reset: removed the files visitors uploaded, exported and imported"
}

# The snapshot may predate the release that is deployed, so its new migrations run again.
migrate_step() {
    (cd "$app" && php artisan migrate --force)
}

up_step() {
    (cd "$app" && php artisan up)
}

steps=("$@")
((${#steps[@]})) || steps=(down restore migrate up)

for step in "${steps[@]}"; do
    case $step in
        snapshot | down | restore | migrate | up) "${step}_step" ;;
        *) fail "unknown step $step; use snapshot, down, restore, migrate or up" ;;
    esac
done

#!/usr/bin/env bash
# One timing run: restore the snapshot scripts/snapshot.sh made into the timing database,
# check its row counts and orders total against <snapshot>.counts, warm the buffer pool
# with one scan, then run the Pest budget group on the mysql lane and the browser budgets
# against the workbench served on that database. The whole run stops at 30 minutes. It prints restore=<seconds> and ends with rc=<code>; rc=124 is the timeout.
#   bash scripts/timing-run.sh <snapshot.sql.zst>
# LATHE_BENCH_DB_NAME names another database (default lathe_test_bench). That database
# must exist and be migrated: the snapshot holds the demo tables only.
set -uo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
snapshot=${1:?usage: timing-run.sh <snapshot.sql.zst>}
database=${LATHE_BENCH_DB_NAME:-lathe_test_bench}
tables=(users customers categories tags products orders order_items posts comments)

in_mysql() {
    podman exec -i -e MYSQL_PWD=lathe_secret lathe-mysql84 "$@"
}

# The same "name value" lines snapshot.sh writes.
counts() {
    local sql="" table

    for table in "${tables[@]}"; do
        sql+="select '$table', count(*) from $table; "
    done

    in_mysql mysql -ulathe -N -B -e "${sql}select 'sum_total_cents', coalesce(sum(total_cents), 0) from orders" "$database"
}

run() {
    local started

    if [[ ! -f $snapshot || ! -f $snapshot.counts ]]; then
        echo "need $snapshot and $snapshot.counts; make them with scripts/snapshot.sh" >&2
        return 1
    fi

    started=$(date +%s)
    zstd -dc "$snapshot" | in_mysql mysql -ulathe "$database" || return 1
    echo "restore=$(($(date +%s) - started))"

    if ! diff <(counts) "$snapshot.counts"; then
        echo "the restored counts differ from $snapshot.counts" >&2
        return 1
    fi

    # One warming scan: the table, then the index the list sorts on.
    in_mysql mysql -ulathe -e 'select count(*) from orders; select count(*) from orders force index (orders_placed_at_index) where placed_at is not null' \
        "$database" >/dev/null || return 1

    eval "$(bash "$repo/scripts/lanes.sh" env mysql)" || return 1
    cd "$repo" || return 1
    LATHE_BENCH_DATABASE=$database php vendor/bin/pest --group=budget
    local pest=$?

    # The script serves the workbench itself. testbench.yaml pins that server to the SQLite
    # workbench (10,000 orders), so the browser budgets measure render and input, not the
    # 10 million rows; workbench:build makes that database when it is missing. Both run
    # without the lane's DB_* variables: with them, the build seeds MySQL and the served
    # SQLite workbench has no user to sign in.
    local sqlite=(env -u DB_CONNECTION -u DB_HOST -u DB_PORT -u DB_DATABASE -u DB_USERNAME -u DB_PASSWORD)
    "${sqlite[@]}" php vendor/bin/testbench workbench:build >/dev/null || return 1
    "${sqlite[@]}" node scripts/bench-browser.mjs
    local browser=$?

    return $((pest != 0 ? pest : browser))
}

if [[ ${2:-} == --inner ]]; then
    run
    exit
fi

# Build agents running beside the timing run skew its numbers.
root=$(dirname "$(git -C "$repo" rev-parse --path-format=absolute --git-common-dir)")
if compgen -G "$root/.claude/worktrees/*/" >/dev/null; then
    echo "warning: $root/.claude/worktrees has folders; an agent may be running beside this timing run" >&2
fi

# Every budget test of this run appends to one result file.
export LATHE_BENCH_RUN=${LATHE_BENCH_RUN:-$(date +%Y%m%d-%H%M%S)}
timeout 1800 bash "${BASH_SOURCE[0]}" "$snapshot" --inner
rc=$?
echo "rc=$rc"
exit $rc

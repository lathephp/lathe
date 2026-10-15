#!/usr/bin/env bash
# Runs the benchmark group (query counts and payload bytes at the default 10,000 orders) on
# SQLite and on Lathe's MySQL, MariaDB and PostgreSQL containers, all into one result file.
# Stands in for the pull-request lane while GitHub Actions is off.
#   bash scripts/bench-lanes.sh
# It uses its own database, lathe_test_benchq. Prints one rc= line per lane and exits 1
# when any failed.
set -uo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
database=lathe_test_benchq
export LATHE_BENCH_RUN="bench-$(date +%Y%m%d-%H%M%S)"
failed=0

cd "$repo" || exit 1

create_database() {
    case $1 in
        mysql) podman exec -e MYSQL_PWD=lathe_secret lathe-mysql84 mysql -ulathe -e "create database if not exists $database" ;;
        mariadb) podman exec -e MYSQL_PWD=lathe_secret lathe-mariadb1011 mariadb -ulathe -e "create database if not exists $database" ;;
        pgsql) podman exec lathe-pg15 psql -U lathe -d postgres -tc "select 1 from pg_database where datname = '$database'" | grep -q 1 \
            || podman exec lathe-pg15 createdb -U lathe "$database" ;;
    esac
}

for lane in sqlite mysql mariadb pgsql; do
    (
        if [[ $lane != sqlite ]]; then
            bash scripts/lanes.sh up "$lane" >/dev/null || exit 1
            create_database "$lane" >/dev/null || exit 1
            eval "$(bash scripts/lanes.sh env "$lane")"
            export DB_DATABASE=$database
        fi

        timeout 30m php vendor/bin/pest --group=benchmark --colors=never tests/Benchmark/QueryCountTest.php
    )
    rc=$?
    echo "$lane rc=$rc"
    [[ $rc -ne 0 ]] && failed=1
done

echo "results: ${LATHE_BENCH_RESULTS:-${TMPDIR:-/tmp}/lathe-bench}/$LATHE_BENCH_RUN.json"
exit $failed

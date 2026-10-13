#!/usr/bin/env bash
# Dumps the demo tables of the timing database into one zstd file, and writes each table's
# row count and SUM(total_cents) of orders beside it in <out>.counts. scripts/timing-run.sh
# restores the file and refuses a restore whose counts differ.
#   bash scripts/snapshot.sh <out.sql.zst>
# LATHE_BENCH_DB_NAME names another database (default lathe_test_bench).
set -euo pipefail

out=${1:?usage: snapshot.sh <out.sql.zst>}
database=${LATHE_BENCH_DB_NAME:-lathe_test_bench}
tables=(users customers categories tags products orders order_items posts comments)

# MYSQL_PWD keeps the password warning off stderr, where real errors go.
in_mysql() {
    podman exec -i -e MYSQL_PWD=lathe_secret lathe-mysql84 "$@"
}

# One "name value" line per table, then the orders total.
counts() {
    local sql="" table

    for table in "${tables[@]}"; do
        sql+="select '$table', count(*) from $table; "
    done

    in_mysql mysql -ulathe -N -B -e "${sql}select 'sum_total_cents', coalesce(sum(total_cents), 0) from orders" "$database"
}

in_mysql mysqldump -ulathe --single-transaction --no-tablespaces "$database" "${tables[@]}" | zstd -T0 -3 -q -f -o "$out"
counts >"$out.counts"
echo "wrote $out and $out.counts"

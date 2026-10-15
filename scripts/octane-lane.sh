#!/usr/bin/env bash
# The Octane lane, on this machine: the workbench under Octane with 2 FrankenPHP workers
# on Lathe's MySQL 8.4, checked against the same workbench under `testbench serve`, which
# runs each request in a fresh PHP process. Both run in the lathe-octane image.
#   bash scripts/octane-lane.sh
# It seeds its own database, lathe_test_octane, runs tests/Octane/OctaneLaneTest.php on
# the host, removes both containers and ends with rc=<code>. Logs go to mktemp -d.
set -uo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
image=lathe-octane
database=lathe_test_octane
octane_port=8711
golden_port=8713
logs=$(mktemp -d -t lathe-octane.XXXXXX)
containers=(lathe-octane-lane lathe-octane-golden)

cleanup() {
    podman rm -f "${containers[@]}" >/dev/null 2>&1
}

trap cleanup EXIT

# The repo is mounted at its own path: the Testbench skeleton links its vendor by absolute path.
in_image() {
    podman run --network=host --userns=keep-id --security-opt label=disable -e HOME=/tmp \
        -e DB_CONNECTION=mysql -e DB_HOST=127.0.0.1 -e DB_PORT=33384 -e DB_DATABASE=$database \
        -e DB_USERNAME=lathe -e DB_PASSWORD=lathe_secret \
        -v "$repo:$repo" -w "$repo" "$@"
}

# wait_for <port> <container>: the login page answers, or the container's log is shown.
wait_for() {
    local tries=60

    while ((tries-- > 0)); do
        curl -sf -o /dev/null "http://127.0.0.1:$1/lathe/login" && return 0
        sleep 1
    done

    echo "$2 did not answer on port $1" >&2
    podman logs "$2" >&2
    return 1
}

run() {
    cleanup
    podman build -q -t $image -f "$repo/scripts/octane.Containerfile" "$repo/scripts" >"$logs/build.log" || return 1
    bash "$repo/scripts/lanes.sh" up mysql >/dev/null || return 1
    podman exec -e MYSQL_PWD=lathe_secret lathe-mysql84 mysql -h127.0.0.1 -ulathe \
        -e "drop database if exists $database; create database $database" || return 1
    in_image --rm $image php vendor/bin/testbench migrate:fresh --force >"$logs/migrate.log" || return 1
    in_image --rm $image php vendor/bin/testbench lathe:demo-seed --orders=10000 || return 1

    in_image -d --name lathe-octane-golden $image \
        php vendor/bin/testbench serve --host=127.0.0.1 --port=$golden_port >/dev/null || return 1
    wait_for $golden_port lathe-octane-golden || return 1

    # Octane recycles a worker after 500 requests by default, which would hide a leak.
    in_image -d --name lathe-octane-lane -e LATHE_OCTANE_LANE=1 $image \
        php vendor/bin/testbench octane:frankenphp --host=127.0.0.1 --port=$octane_port \
        --admin-port=$((octane_port + 1)) --workers=2 --max-requests=100000 >/dev/null || return 1
    wait_for $octane_port lathe-octane-lane || return 1

    LATHE_OCTANE_URL=http://127.0.0.1:$octane_port LATHE_GOLDEN_URL=http://127.0.0.1:$golden_port \
        php vendor/bin/pest --colors=never "$repo/tests/Octane/OctaneLaneTest.php"
    local rc=$?

    podman logs lathe-octane-lane >"$logs/octane.log" 2>&1
    echo "logs: $logs"
    return $rc
}

cd "$repo" || exit 1
run
rc=$?
echo "rc=$rc"
exit $rc

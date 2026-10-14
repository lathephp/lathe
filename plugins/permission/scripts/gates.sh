#!/usr/bin/env bash
# Runs the package gates on every runtime the plugin supports and prints one rc= line each:
#   host PHP and the php:8.4 image (lathe-php84, built by scripts/matrix.sh at the repo root),
#   each on the highest versions and after composer update --prefer-lowest (Spatie 7.x).
# The host-highest lane also runs PHPStan level 10, Pint and 100% type coverage.
# A container run past 30 minutes is stopped and prints rc=124. The script ends by restoring the
# host-highest install, and exits 1 when any rc was not 0.
#   bash plugins/permission/scripts/gates.sh
set -uo pipefail

plugin=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
# The plugin installs the core through a path repository two folders up, so the container mounts the whole repo.
repo=$(cd "$plugin/../.." && pwd)
image=lathe-php84:$(sha256sum "$repo/scripts/php84.Containerfile" | cut -c1-12)
composer_cache=$(composer config --global cache-dir 2>/dev/null || echo "$(mktemp -d)/composer-cache")
failed=0

report() {
    printf '%s rc=%d\n' "$1" "$2"
    ((${2} != 0)) && failed=1
    return 0
}

# in_php84 <command...> runs the command in the plugin folder inside the image.
in_php84() {
    timeout 30m podman run --rm --network=host --userns=keep-id --security-opt label=disable \
        -e HOME=/tmp -e COMPOSER_CACHE_DIR=/composer-cache \
        -v "$repo:/app" -v "$composer_cache:/composer-cache" -w /app/plugins/permission "$image" "$@"
}

# run <host|php84> <command...> runs the command on the host or in the image.
run() {
    local where=$1
    shift
    if [[ $where == host ]]; then
        "$@"
    else
        in_php84 "$@"
    fi
}

# gate <name> <host|php84> <command...> runs one command and prints its rc.
gate() {
    local name=$1 rc
    shift
    run "$@"
    rc=$?
    report "$name" "$rc"
}

# lane <name> <host|php84> <update flags...> installs the lane's versions, prints the Spatie version
# and runs composer test.
lane() {
    local name=$1 where=$2 rc
    shift 2
    echo "lane=$name"
    run "$where" composer update --no-interaction --prefer-dist -q "$@"
    rc=$?
    if ((rc != 0)); then
        report "lane=$name install" "$rc"
        return
    fi
    run "$where" composer show spatie/laravel-permission | grep -E '^versions'
    gate "lane=$name" "$where" composer test
}

mkdir -p "$composer_cache"
if ! podman image exists "$image"; then
    podman build -t "$image" -f "$repo/scripts/php84.Containerfile" "$repo/scripts" >/dev/null 2>&1 \
        || { echo "could not build $image" >&2; exit 1; }
fi

cd "$plugin" || exit 1

lane php8.5-highest host
gate gate=phpstan host composer analyse
gate gate=pint host composer lint
gate gate=types host composer types
lane php8.5-lowest host --prefer-lowest --prefer-stable
lane php8.4-highest php84
lane php8.4-lowest php84 --prefer-lowest --prefer-stable

echo "restoring the host-highest install"
gate restore host composer update --no-interaction --prefer-dist -q

exit $failed

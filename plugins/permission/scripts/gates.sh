#!/usr/bin/env bash
# Runs the package gates on the host PHP and prints one rc= line each: the highest versions and
# composer update --prefer-lowest (Spatie 7.x). The highest lane also runs PHPStan level 10, Pint
# and 100% type coverage. PHP 8.4 runs in the plugin's own CI. The script ends by restoring the
# highest install, and exits 1 when any rc was not 0.
#   bash plugins/permission/scripts/gates.sh
set -uo pipefail

plugin=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
failed=0

report() {
    printf '%s rc=%d\n' "$1" "$2"
    ((${2} != 0)) && failed=1
    return 0
}

# gate <name> <command...> runs one command and prints its rc.
gate() {
    local name=$1 rc
    shift
    "$@"
    rc=$?
    report "$name" "$rc"
}

# lane <name> <update flags...> installs the lane's versions, prints the Spatie version
# and runs composer test.
lane() {
    local name=$1 rc
    shift
    echo "lane=$name"
    composer update --no-interaction --prefer-dist -q "$@"
    rc=$?
    if ((rc != 0)); then
        report "lane=$name install" "$rc"
        return
    fi
    composer show spatie/laravel-permission | grep -E '^versions'
    gate "lane=$name" composer test
}

cd "$plugin" || exit 1

lane highest
gate gate=phpstan composer analyse
gate gate=pint composer lint
gate gate=types composer types
lane lowest --prefer-lowest --prefer-stable

echo "restoring the highest install"
gate restore composer update --no-interaction --prefer-dist -q

exit $failed

#!/usr/bin/env bash
# Runs the PHP suite on every cell of the CI matrix, on this machine:
#   PHP on the host and PHP 8.4 in the lathe-php84 image, each on SQLite, MySQL 8.4,
#   MariaDB 10.11 and PostgreSQL 15, then PHP 8.4 after composer update --prefer-lowest
#   on SQLite and MySQL 8.4.
# It starts one database container at a time (scripts/lanes.sh), prints one rc= line per
# cell and the total wall time, and exits 1 when any cell failed. A cell that runs past
# 30 minutes is stopped and prints rc=124. Logs and the prefer-lowest copy go to mktemp -d.
#   bash scripts/matrix.sh
set -uo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
# Tagged with the Containerfile hash, so an edit to it builds a fresh image.
image=lathe-php84:$(sha256sum "$repo/scripts/php84.Containerfile" | cut -c1-12)
scratch=$(mktemp -d -t lathe-matrix.XXXXXX)
lowest=$scratch/lowest
composer_cache=$(composer config --global cache-dir 2>/dev/null || echo "$scratch/composer-cache")
started=$(date +%s)
failed=0

# composer test's PHP step. Each group needs its own flag: a comma list excludes nothing.
pest=(php vendor/bin/pest --parallel --colors=never
    --exclude-group=browser --exclude-group=benchmark --exclude-group=budget --exclude-group=scaffold)

trap 'rm -rf "$lowest"' EXIT

# Prints the lane's DB_* variables as KEY=value words, or nothing for SQLite.
lane_env() {
    [[ $1 == sqlite ]] && return
    local line
    line=$(bash "$repo/scripts/lanes.sh" env "$1") || return 1
    echo "${line#export }"
}

# in_php84 <tree> <lane> <command...> runs the command in the PHP 8.4 image, stopped at 30 minutes.
# The image has no Node, like the CI php job, so its cells leave the frontend group out.
in_php84() {
    local tree=$1 lane=$2
    shift 2
    local vars=() var

    for var in $(lane_env "$lane"); do
        vars+=(-e "$var")
    done

    timeout 30m podman run --rm --network=host --userns=keep-id --security-opt label=disable \
        -e HOME=/tmp -e COMPOSER_CACHE_DIR=/composer-cache "${vars[@]}" \
        -v "$tree:/app" -v "$composer_cache:/composer-cache" -w /app "$image" "$@"
}

# cell <name> <host|php84|lowest> <lane>
cell() {
    local name=$1 where=$2 lane=$3 log="$scratch/$1.log" from rc
    local args=()

    [[ $lane != sqlite ]] && args+=(--processes=4)
    from=$(date +%s)

    case $where in
        host)
            # shellcheck disable=SC2046
            (cd "$repo" && env $(lane_env "$lane") timeout 30m "${pest[@]}" "${args[@]}") >"$log" 2>&1
            ;;
        php84)
            in_php84 "$repo" "$lane" "${pest[@]}" --exclude-group=frontend "${args[@]}" >"$log" 2>&1
            ;;
        lowest)
            if [[ ! -f $lowest/vendor/autoload.php ]]; then
                echo "the prefer-lowest install failed, see $scratch/lowest-install.log" >"$log"
                false
            else
                in_php84 "$lowest" "$lane" "${pest[@]}" --exclude-group=frontend "${args[@]}" >"$log" 2>&1
            fi
            ;;
    esac

    rc=$?
    ((rc != 0)) && failed=1
    printf '%-24s rc=%d  %ds  %s\n' "$name" "$rc" "$(($(date +%s) - from))" "$log"
}

# Every cell of a lane fails with the lane's rc when its container does not start.
lane_down() {
    local rc=$1 name
    shift
    failed=1

    for name in "$@"; do
        printf '%-24s rc=%d  0s  lanes.sh up failed\n' "$name" "$rc"
    done
}

if ! podman image exists "$image"; then
    podman build -t "$image" -f "$repo/scripts/php84.Containerfile" "$repo/scripts" >"$scratch/image.log" 2>&1 \
        || { echo "could not build $image, see $scratch/image.log" >&2; exit 1; }
fi

host_php=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo "logs: $scratch"

# The prefer-lowest copy holds this tree's files with its own vendor folder. It keeps .git, so
# Composer reads the root version as on main, and dist/ and the workbench extensions
# (workbench/public/vendor), which lathe:doctor checks; only the root vendor/ is left out.
rsync -a --exclude /vendor --exclude node_modules --exclude .phpunit.cache \
    "$repo/" "$lowest/"
mkdir -p "$composer_cache"
in_php84 "$lowest" sqlite composer update --prefer-lowest --prefer-stable --no-interaction --prefer-dist \
    >"$scratch/lowest-install.log" 2>&1

cell "php$host_php-sqlite" host sqlite
cell php8.4-sqlite php84 sqlite
cell php8.4-lowest-sqlite lowest sqlite

for lane in mysql mariadb pgsql; do
    cells=("php$host_php-$lane" "php8.4-$lane")
    [[ $lane == mysql ]] && cells+=(php8.4-lowest-mysql)

    if ! bash "$repo/scripts/lanes.sh" up "$lane" >"$scratch/lanes-$lane.log" 2>&1; then
        lane_down 1 "${cells[@]}"
        bash "$repo/scripts/lanes.sh" down "$lane"
        continue
    fi

    cell "php$host_php-$lane" host "$lane"
    cell "php8.4-$lane" php84 "$lane"
    [[ $lane == mysql ]] && cell php8.4-lowest-mysql lowest mysql
    bash "$repo/scripts/lanes.sh" down "$lane"
done

echo "total: $(($(date +%s) - started))s"
exit $failed

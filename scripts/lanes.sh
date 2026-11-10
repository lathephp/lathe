#!/usr/bin/env bash
# Starts Lathe's own database containers for the multi-driver test lanes.
#   lanes.sh up [mysql|mariadb|pgsql] | down [mysql|mariadb|pgsql] | env <mysql|mariadb|pgsql>
#   lanes.sh changed <mysql|mariadb|pgsql|octane>
# With no lane name, up and down act on all three. changed runs the test files that differ
# from origin/main, committed, uncommitted or untracked, on that lane in a database of the run's own,
# so a milestone meets every driver before CI.
# changed octane runs scripts/octane-lane.sh when a file under workbench/ or tests/Octane differs.
# These containers are Lathe's. Never reuse another project's database container.
set -uo pipefail

database=lathe_test
user=lathe
password=lathe_secret

# lane name image port inside-port
lanes=(
    "mysql lathe-mysql84 docker.io/library/mysql:8.4 33384 3306"
    "mariadb lathe-mariadb1011 docker.io/library/mariadb:10.11 33311 3306"
    "pgsql lathe-pg15 docker.io/library/postgres:15 54315 5432"
)

start() {
    local name=$1 image=$2 port=$3 inner=$4

    if podman container exists "$name"; then
        podman start "$name" >/dev/null
        return
    fi

    case $name in
        lathe-pg15)
            podman run -d --name "$name" -p "127.0.0.1:$port:$inner" \
                -e POSTGRES_DB=$database -e POSTGRES_USER=$user -e POSTGRES_PASSWORD=$password \
                "$image" >/dev/null
            ;;
        lathe-mariadb1011)
            podman run -d --name "$name" -p "127.0.0.1:$port:$inner" \
                -e MARIADB_DATABASE=$database -e MARIADB_USER=$user -e MARIADB_PASSWORD=$password \
                -e MARIADB_ROOT_PASSWORD=$password \
                "$image" >/dev/null
            ;;
        *)
            # The timing runs measure MySQL inside this limit, a 3 GB cap with a 2 GB buffer pool.
            # MySQL 8.4 keeps binary logs by default, and the bench lanes filled 48 GB with them.
            podman run -d --name "$name" -p "127.0.0.1:$port:$inner" --memory=3g \
                -e MYSQL_DATABASE=$database -e MYSQL_USER=$user -e MYSQL_PASSWORD=$password \
                -e MYSQL_ROOT_PASSWORD=$password \
                "$image" --innodb-buffer-pool-size=2G --skip-log-bin >/dev/null
            ;;
    esac
}

# Over TCP: on Actions the server's socket path is not the one the client looks for.
ready() {
    local name=$1 tries=90 error=

    while ((tries-- > 0)); do
        case $name in
            lathe-pg15)
                error=$(podman exec "$name" pg_isready -U $user -d $database 2>&1) && return 0
                ;;
            lathe-mariadb1011)
                error=$(podman exec "$name" mariadb -h127.0.0.1 -u$user -p$password -e 'select 1' $database 2>&1) && return 0
                ;;
            *)
                error=$(podman exec "$name" mysql -h127.0.0.1 -u$user -p$password -e 'select 1' $database 2>&1) && return 0
                ;;
        esac
        sleep 2
    done

    echo "$name did not accept connections in time; the last check said: $error" >&2
    podman ps -a --filter "name=$name" --format '{{.Names}} {{.Status}}' >&2
    podman logs --tail 30 "$name" >&2
    return 1
}

# Parallel tests give each process its own database, lathe_test_<n>, so the lathe
# user may create and drop those. The PostgreSQL user owns the server already.
grant() {
    local name=$1 sql="grant all on \`${database}\\_%\`.* to '$user'@'%'"

    case $name in
        lathe-mariadb1011) podman exec "$name" mariadb -h127.0.0.1 -uroot -p$password -e "$sql" ;;
        lathe-mysql84) podman exec "$name" mysql -h127.0.0.1 -uroot -p$password -e "$sql" 2>/dev/null ;;
    esac
}

# Runs one statement on a lane's server as the lathe user.
own() {
    case $1 in
        pgsql) podman exec lathe-pg15 psql -q -U $user -d $database -c "$2" ;;
        mariadb) podman exec lathe-mariadb1011 mariadb -h127.0.0.1 -u$user -p$password -e "$2" ;;
        mysql) podman exec lathe-mysql84 mysql -h127.0.0.1 -u$user -p$password -e "$2" 2>/dev/null ;;
    esac
}

# Prints the lanes the second argument picks, or all of them.
selected() {
    local lane

    for lane in "${lanes[@]}"; do
        [[ -z ${1:-} || $lane == "$1 "* ]] && echo "$lane"
    done
}

if [[ ${1:-} == up || ${1:-} == down ]] && [[ -n ${2:-} ]] && [[ -z $(selected "$2") ]]; then
    echo "unknown lane: $2" >&2
    exit 2
fi

case ${1:-} in
    up)
        while read -r _ name image port inner; do
            start "$name" "$image" "$port" "$inner" || exit 1
        done < <(selected "${2:-}")
        while read -r _ name _; do
            ready "$name" || exit 1
            grant "$name" || exit 1
            echo "$name ready"
        done < <(selected "${2:-}")
        ;;
    down)
        while read -r _ name _; do
            podman container exists "$name" && podman stop "$name" >/dev/null
        done < <(selected "${2:-}")
        exit 0
        ;;
    env)
        case ${2:-} in
            mysql) driver=mysql port=33384 ;;
            mariadb) driver=mariadb port=33311 ;;
            pgsql) driver=pgsql port=54315 ;;
            *) echo "usage: lanes.sh env <mysql|mariadb|pgsql>" >&2; exit 2 ;;
        esac
        echo "export DB_CONNECTION=$driver DB_HOST=127.0.0.1 DB_PORT=$port DB_DATABASE=$database DB_USERNAME=$user DB_PASSWORD=$password"
        ;;
    changed)
        [[ ${2:-} == octane || ( -n ${2:-} && -n $(selected "$2") ) ]] || { echo "usage: lanes.sh changed <mysql|mariadb|pgsql|octane>" >&2; exit 2; }
        cd "$(dirname "$0")/.." || exit 1
        if [[ $2 == octane ]]; then
            if [[ -z $({ git diff --name-only origin/main -- workbench tests/Octane; git ls-files --others --exclude-standard -- workbench tests/Octane; }) ]]; then
                echo "no workbench or tests/Octane file differs from origin/main"
                exit 0
            fi
            exec bash scripts/octane-lane.sh
        fi
        mapfile -t files < <({ git diff --name-only --diff-filter=d origin/main -- 'tests/*Test.php'; git ls-files --others --exclude-standard -- 'tests/*Test.php'; } | sort -u)
        if ((${#files[@]} == 0)); then
            echo "no test file differs from origin/main"
            exit 0
        fi
        bash scripts/lanes.sh up "$2" || exit 1
        eval "$(bash scripts/lanes.sh env "$2")"
        # A database of this run's own, so two sessions on one lane never share lathe_test.
        DB_DATABASE=${database}_run$$
        own "$2" "create database $DB_DATABASE" || exit 1
        trap 'own "$2" "drop database if exists $DB_DATABASE"' EXIT
        php vendor/bin/pest --exclude-group=benchmark --exclude-group=scaffold "${files[@]}"
        ;;
    *)
        echo "usage: lanes.sh up [mysql|mariadb|pgsql] | down [mysql|mariadb|pgsql] | env <mysql|mariadb|pgsql> | changed <mysql|mariadb|pgsql|octane>" >&2
        exit 2
        ;;
esac

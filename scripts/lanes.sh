#!/usr/bin/env bash
# Starts Lathe's own database containers for the multi-driver test lanes.
#   lanes.sh up | down | env <mysql|mariadb|pgsql>
# These containers are Lathe's. Never reuse another project's database container.
set -uo pipefail

database=lathe_test
user=lathe
password=lathe_secret

# name image port inside-port
lanes=(
    "lathe-mysql84 docker.io/library/mysql:8.4 33384 3306"
    "lathe-mariadb1011 docker.io/library/mariadb:10.11 33311 3306"
    "lathe-pg15 docker.io/library/postgres:15 54315 5432"
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
            podman run -d --name "$name" -p "127.0.0.1:$port:$inner" \
                -e MYSQL_DATABASE=$database -e MYSQL_USER=$user -e MYSQL_PASSWORD=$password \
                -e MYSQL_ROOT_PASSWORD=$password \
                "$image" >/dev/null
            ;;
    esac
}

ready() {
    local name=$1 tries=90

    while ((tries-- > 0)); do
        case $name in
            lathe-pg15)
                podman exec "$name" pg_isready -U $user -d $database >/dev/null 2>&1 && return 0
                ;;
            lathe-mariadb1011)
                podman exec "$name" mariadb -u$user -p$password -e 'select 1' $database >/dev/null 2>&1 && return 0
                ;;
            *)
                podman exec "$name" mysql -u$user -p$password -e 'select 1' $database >/dev/null 2>&1 && return 0
                ;;
        esac
        sleep 2
    done

    echo "$name did not accept connections in time" >&2
    return 1
}

case ${1:-} in
    up)
        for lane in "${lanes[@]}"; do
            read -r name image port inner <<<"$lane"
            start "$name" "$image" "$port" "$inner" || exit 1
        done
        for lane in "${lanes[@]}"; do
            read -r name _ <<<"$lane"
            ready "$name" || exit 1
            echo "$name ready"
        done
        ;;
    down)
        for lane in "${lanes[@]}"; do
            read -r name _ <<<"$lane"
            podman container exists "$name" && podman stop "$name" >/dev/null
        done
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
    *)
        echo "usage: lanes.sh up | down | env <mysql|mariadb|pgsql>" >&2
        exit 2
        ;;
esac

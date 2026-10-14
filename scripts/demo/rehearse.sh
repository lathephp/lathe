#!/usr/bin/env bash
# Rehearses the live demo's deploy in a podman container at 1,000 orders.
#   bash scripts/demo/rehearse.sh
# It packs the current tree with pack.sh as composer archives of lathe/lathe and
# lathe/permission, builds the Ubuntu 24.04 image in scripts/demo/Containerfile, runs
# deploy.sh inside with --archive, --orders 1000 and --no-systemd, then checks each guard
# rail through Caddy. Scratch files, the MySQL download and the Composer cache stay in
# ~/.cache/lathe-demo; the container and image are removed at the end. Prints each check
# and rc=, and exits non-zero when one fails.
set -uo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
scratch=$HOME/.cache/lathe-demo
name=lathe-demo-rehearsal
image=localhost/lathe-demo-rehearsal

step() { echo "== $*"; }
fail() { echo "rehearse: $*" >&2; echo "rc=1"; exit 1; }

cleanup() {
    podman rm -f -t 0 "$name" >/dev/null 2>&1
    podman rmi -f "$image" >/dev/null 2>&1
}
trap cleanup EXIT

cleanup

step "pack lathe/lathe and lathe/permission from the current tree"
rm -rf "$scratch/src"
mkdir -p "$scratch/src" "$scratch/archives" "$scratch/cache" "$scratch/composer"
bash "$repo/scripts/demo/pack.sh" "$scratch/archives" || fail "pack.sh failed"
# deploy.sh copies the workbench from the tree it runs in, so the container gets the tree too.
git -C "$repo" ls-files -z -co --exclude-standard \
    | tar -C "$repo" --null --ignore-failed-read -T - -cf - \
    | tar -C "$scratch/src" -xf - || fail "could not copy the tree"

step "build the image and start the container"
podman build -q -t "$image" -f "$repo/scripts/demo/Containerfile" "$repo/scripts/demo" >/dev/null || fail "podman build failed"
podman run -d --init --name "$name" --memory=3g \
    -v "$scratch/src:/src:ro,Z" \
    -v "$scratch/archives:/archives:ro,Z" \
    -v "$scratch/cache:/var/cache/lathe-demo:Z" \
    -v "$scratch/composer:/root/.cache/composer:Z" \
    "$image" >/dev/null || fail "podman run failed"

step "deploy.sh --archive /archives --orders 1000 --site :80 --buffer-pool 256M --no-systemd"
podman exec "$name" bash /src/scripts/demo/deploy.sh --archive /archives --orders 1000 --site :80 --buffer-pool 256M --no-systemd \
    || fail "deploy.sh failed"

step "check the guard rails"
podman exec -i "$name" bash -s <<'CHECKS'
set -uo pipefail
base=http://127.0.0.1
jar=/root/jar
failures=0

check() {
    local label=$1
    shift
    if "$@"; then echo "ok    $label"; else echo "FAIL  $label"; failures=$((failures + 1)); fi
}

xsrf() { awk '$6 == "XSRF-TOKEN" { print $7 }' "$jar" | php -r 'echo urldecode(trim(stream_get_contents(STDIN)));'; }
api() { curl -s -b "$jar" -c "$jar" -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf)" "$@"; }
json() { php -r '$p = json_decode(stream_get_contents(STDIN), true); echo '"$1"';'; }

code=$(curl -s -o /root/login.html -w '%{http_code}' -c "$jar" "$base/lathe/login")
echo "      GET /lathe/login: $code; payload: $(grep -o 'demoLogin.\{0,110\}' /root/login.html | head -n 1)"
check "/lathe/login answers 200" test "$code" = 200
check "the login page payload holds admin@lathephp.com and password" grep -q 'demoLogin.*admin@lathephp\.com.*password' /root/login.html
external=$(grep -oE 'https?://[^"&<> ]+' /root/login.html | grep -v '^http://localhost' | sort -u)
echo "      URLs on the login page outside the app: ${external:-none}"
check "the login page loads nothing from another host" test -z "$external"

code=$(api -o /root/signin.json -w '%{http_code}' -H 'Content-Type: application/json' \
    -d '{"email":"admin@lathephp.com","password":"password"}' "$base/lathe/login")
check "admin@lathephp.com signs in with password ($code)" test "$code" = 200 -o "$code" = 204
read -r rows per_page < <(api "$base/lathe/api/resources/orders" | json 'count($p["table"]["rows"] ?? []), " ", $p["state"]["perPage"] ?? 0')
total=$(api "$base/lathe/api/resources/orders/count" | json '$p["value"] ?? 0')
echo "      Orders: Showing 1 to $rows of $(php -r "echo number_format($total);") (perPage $per_page)"
check "Orders shows 25 rows of 1,000" test "$rows/$per_page/$total" = 25/25/1000

admin=$(mysql -N lathe_demo -e "select id from users where email = 'admin@lathephp.com'")
code=$(api -o /root/password.json -w '%{http_code}' -X PUT -H 'Content-Type: application/json' \
    -d '{"name":"Admin User","email":"admin@lathephp.com","password":"taken-over","password_confirmation":"taken-over"}' \
    "$base/lathe/api/resources/users/$admin")
echo "      PUT a new password on admin@lathephp.com (id $admin) through the Users API: $code $(cat /root/password.json)"
check "changing admin's password through the API is refused with 403" test "$code" = 403
account=$(api -o /dev/null -w '%{http_code}' -X PUT -H 'Content-Type: application/json' \
    -d '{"current_password":"password","password":"taken-over","password_confirmation":"taken-over"}' "$base/lathe/user-security/password")
account+=" $(api -o /dev/null -w '%{http_code}' -X POST "$base/lathe/user/two-factor-authentication")"
echo "      PUT /lathe/user-security/password, POST /lathe/user/two-factor-authentication: $account"
check "the account screens can neither change a password nor turn on two-factor" test "$account" = "404 404"

cd /srv/lathe-demo || exit 1
settings=$(runuser -u www-data -- env HOME=/tmp php artisan tinker --execute 'echo implode(" ", [config("mail.default"), config("lathe.uploads.max_file_size_mb"), config("lathe.uploads.max_image_size_mb"), config("lathe.imports.max_file_size_mb"), config("lathe.exports.prune_after_hours")]);')
echo "      mail, file MB, image MB, import MB, export prune hours: $settings"
check "mail is logged, uploads are capped at 1 MB and exports pruned after 1 hour" test "$settings" = "log 1 1 1 1"
ini=$(php-fpm8.4 -i | grep -E '^(memory_limit|max_execution_time|upload_max_filesize|post_max_size) ' | sed 's/ => .* => / /' | sort | tr '\n' ' ')
echo "      PHP-FPM: $ini"
check "PHP-FPM runs with memory_limit 256M and max_execution_time 30" test "$ini" = "max_execution_time 30 memory_limit 256M post_max_size 1M upload_max_filesize 1M "

code=$(api -o /root/cover.json -w '%{http_code}' -H 'Content-Type: application/json' \
    -d '{"name":"big.jpg","size":1572864,"mime":"image/jpeg"}' "$base/lathe/api/resources/posts/uploads/cover")
echo "      a 1.5 MB Post cover: $code $(cat /root/cover.json)"
check "a 1.5 MB image upload is refused with 422" test "$code" = 422
# The avatar field allows 5 MB itself, so its start passes and Caddy refuses the 1.5 MB body.
upload=$(api -H 'Content-Type: application/json' -d '{"name":"big.png","size":1572864,"mime":"image/png"}' \
    "$base/lathe/api/resources/users/uploads/avatar" | json '$p["upload_id"] ?? "none"')
head -c 1572864 /dev/urandom >/root/big.bin
code=$(api -o /root/chunk.txt -w '%{http_code}' -X PUT -H 'Content-Type: application/octet-stream' \
    --data-binary @/root/big.bin "$base/lathe/api/uploads/$upload/chunks/0")
echo "      a 1.5 MB avatar chunk for upload $upload: $code"
check "a 1.5 MB upload body is refused with 413" test "$code" = 413

mysql lathe_demo -e 'delete from orders where id <= 10'
echo "      orders before the reset: $(mysql -N lathe_demo -e 'select count(*) from orders')"
# The reset is polled through the container's own address, which Caddy counts apart from 127.0.0.1.
address=$(hostname -I | awk '{ print $1 }')
runuser -u www-data -- lathe-demo-reset >/root/reset.log 2>&1 &
reset=$!
during=
while kill -0 "$reset" 2>/dev/null; do
    code=$(curl -s -o /root/during.html -w '%{http_code}' "http://$address/lathe/login")
    # Under /lathe the maintenance screen is the panel's error page for status 503.
    if [[ $code == 503 ]] && grep -q '"component":"Error","props":{"status":503' /root/during.html; then during=503; fi
    sleep 0.2
done
wait "$reset"
status=$?
sed 's/^/      /' /root/reset.log
echo "      /lathe/login during the reset: ${during:-never 503}"
check "the app answers 503 with the maintenance screen during the reset" test "$during" = 503
total=$(api "$base/lathe/api/resources/orders/count" | json '$p["value"] ?? 0')
echo "      orders after the reset: $total"
check "reset.sh brings it back with 1,000 orders" test "$status/$total" = 0/1000

# Caddy counts each address apart; ::1 has made no request yet, so its window starts empty.
host='[::1]'
[[ -s /proc/net/if_inet6 ]] || { host=127.0.0.1; sleep 61; }
# A first visit fetches many built files at once; they must not use up the address's 60.
asset=$(curl -s "http://$address/lathe/login" | grep -o '/lathe/assets/[^"]*\.js' | head -1)
assets=$(for _ in $(seq 80); do curl -s -o /dev/null -w '%{http_code}\n' "http://$host$asset"; done | sort | uniq -c | tr -s ' ' | tr '\n' ',')
echo "      80 requests for $asset from $host:$assets"
check "built files do not count toward the limit" test "$assets" = " 80 200,"
codes=()
for _ in $(seq 61); do codes+=("$(curl -s -o /dev/null -w '%{http_code}' "http://$host/up")"); done
first=$(printf '%s\n' "${codes[@]:0:60}" | sort | uniq -c | tr -s ' ' | tr '\n' ',')
echo "      requests 1 to 60 from $host:$first request 61: ${codes[60]}"
check "the first 60 requests in a minute pass" test "$(printf '%s\n' "${codes[@]:0:60}" | grep -c 429)" = 0
check "the 61st request in a minute gets 429" test "${codes[60]}" = 429

echo "failures=$failures"
((failures == 0))
CHECKS
checks=$?

((checks == 0)) || fail "a check failed"
echo "rc=0"

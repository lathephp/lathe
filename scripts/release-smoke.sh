#!/usr/bin/env bash
# Installs the packed release into a clean Laravel app, the way a reader of the README does.
#   bash scripts/release-smoke.sh [workdir]
# It packs the package with `composer archive` (dist/ must be built) and each npm package with
# `pnpm pack`, creates a Laravel 13.35.0 app, requires lathe/lathe:^1.0@beta from the local
# archive, runs lathe:install and lathe:doctor, asks /lathe/login for a 200, checks every built
# asset the manifest names reached the app, then signs in and opens the Users list.
# The version is the newest CHANGELOG release heading, so a dry run on main smokes the
# release it is about to cut. Prints rc= and exits non-zero on the first failure.
set -uo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
work=${1:-}
if [[ -z $work ]]; then
    # A folder the script made for itself is removed when it ends.
    work=$(mktemp -d "${TMPDIR:-/tmp}/lathe-smoke.XXXXXX")
    owned=$work
    trap 'rm -rf "$owned"' EXIT
fi
laravel=13.35.0
port=${LATHE_SMOKE_PORT:-8977}
version=$(grep -m1 -oP '^## \[\K[0-9][^\]]*(?=\])' "$repo/CHANGELOG.md")

step() { echo "== $*"; }
fail() { echo "release-smoke: $*" >&2; echo "rc=1"; exit 1; }

[[ -n $version ]] || fail "no release heading in CHANGELOG.md"
[[ -f $repo/dist/manifest.json ]] || fail "dist/ is not built; run pnpm build"
mkdir -p "$work/archives" "$work/npm"

step "pack lathe/lathe $version"
(cd "$repo" && composer archive --format=zip --dir="$work/archives" --file="lathe-lathe-$version" -q) || fail "composer archive failed"
# An artifact repository reads the version from the archived composer.json.
php -r '
    [$zip, $version] = array_slice($argv, 1);
    $archive = new ZipArchive;
    $archive->open($zip) === true || exit(1);
    $json = json_decode($archive->getFromName("composer.json"), true);
    $json["version"] = $version;
    $archive->addFromString("composer.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $archive->close() || exit(1);
' "$work/archives/lathe-lathe-$version.zip" "$version" || fail "could not stamp the archive version"
grep -q "dist/manifest.json" <<<"$(unzip -l "$work/archives/lathe-lathe-$version.zip")" || fail "the archive has no dist/manifest.json"

step "pack the npm packages"
for package in "$repo"/packages/*/; do
    (cd "$package" && pnpm pack --pack-destination "$work/npm" >/dev/null) || fail "pnpm pack failed in $package"
done
for tarball in "$work"/npm/*.tgz; do
    grep -q '^package/LICENSE$' <<<"$(tar -tzf "$tarball")" || fail "$(basename "$tarball") has no LICENSE"
done

# The clean app reads its own .env, so drop app settings inherited from a test runner.
while IFS= read -r name; do unset "$name"; done < <(compgen -e | grep -E '^(APP_|DB_|CACHE_|SESSION_|QUEUE_|MAIL_|BCRYPT_|BROADCAST_|FILESYSTEM_|LOG_|TELESCOPE_|PULSE_|NIGHTWATCH_)')

step "create a Laravel $laravel app"
composer create-project laravel/laravel "$work/app" --no-interaction --prefer-dist -q || fail "create-project failed"
cd "$work/app" || fail "no app"
composer require "laravel/framework:$laravel" -W --no-interaction -q || fail "could not pin laravel/framework $laravel"
composer config repositories.lathe '{"type": "artifact", "url": "'"$work"'/archives"}' || fail "could not add the archive repository"

step "composer require lathe/lathe:^1.0@beta"
composer require 'lathe/lathe:^1.0@beta' --no-interaction -q || fail "composer require lathe/lathe:^1.0@beta failed"
installed=$(composer show lathe/lathe --format=json | php -r 'echo json_decode(stream_get_contents(STDIN), true)["versions"][0];')
[[ $installed == "$version" ]] || fail "installed $installed, expected $version"

step "php artisan lathe:install"
php artisan lathe:install --no-interaction || fail "lathe:install failed"

step "php artisan lathe:doctor"
php artisan lathe:doctor || fail "lathe:doctor failed"

step "GET /lathe/login"
php artisan serve --port="$port" >"$work/serve.log" 2>&1 &
server=$!
trap 'kill $server 2>/dev/null; [[ -n ${owned:-} ]] && rm -rf "$owned"' EXIT
code=000
for _ in $(seq 30); do
    code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/lathe/login") && [[ $code != 000 ]] && break
    sleep 1
done
[[ $code == 200 ]] || fail "/lathe/login answered $code"

step "every asset in dist/manifest.json is installed"
php -r '
    $dist = $argv[1];
    $manifest = json_decode((string) file_get_contents("$dist/manifest.json"), true);
    is_array($manifest) || exit(1);
    $missing = [];
    foreach ($manifest as $entry) {
        foreach ([$entry["file"] ?? null, ...($entry["css"] ?? []), ...($entry["assets"] ?? [])] as $file) {
            if (is_string($file) && ! is_file("$dist/$file")) {
                $missing[] = $file;
            }
        }
    }
    $missing === [] || exit(fwrite(STDERR, "missing: ".implode(", ", $missing)."\n") && 1);
' "$work/app/vendor/lathe/lathe/dist" || fail "the installed dist/ lacks files its manifest names"

step "sign in and open the Users list"
php artisan lathe:user --name=Smoke --email=smoke@example.com --password=smoke-password-1 --no-interaction >/dev/null || fail "lathe:user failed"
sed -i "s|^            //$|            'smoke@example.com',|" app/Providers/LatheServiceProvider.php
grep -q "'smoke@example.com'," app/Providers/LatheServiceProvider.php || fail "could not add the user to the viewLathe gate"
base="http://127.0.0.1:$port/lathe"
jar="$work/cookies.txt"
curl -s -c "$jar" -o /dev/null "$base/login"
token=$(awk '$6 == "XSRF-TOKEN" {print $7}' "$jar" | php -r 'echo urldecode(trim(stream_get_contents(STDIN)));')
code=$(curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -H "X-XSRF-TOKEN: $token" -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"email":"smoke@example.com","password":"smoke-password-1"}' "$base/login")
[[ $code == 200 || $code == 204 ]] || fail "signing in answered $code"
grep -qE 'Resources(\\)?/Index' <<<"$(curl -s -b "$jar" "$base/resources/users")" || fail "/lathe/resources/users is not the Resource index"
grep -q 'smoke@example.com' <<<"$(curl -s -b "$jar" -H 'Accept: application/json' "$base/api/resources/users")" || fail "the Users list does not hold the user"

echo "lathe/lathe $version installed, lathe:install and lathe:doctor passed, /lathe/login answered 200, the Users list opened"
echo "rc=0"

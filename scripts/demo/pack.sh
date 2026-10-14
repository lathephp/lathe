#!/usr/bin/env bash
# Packs lathe/lathe and lathe/permission from this checkout as composer archives, so
# deploy.sh --archive can install them before they are on Packagist:
#   bash scripts/demo/pack.sh <out-dir>
# Run it on your own machine from a checkout with dist/ built (pnpm build); a git worktree
# without dist/ takes the main checkout's. It writes lathe-lathe-<version>.zip and
# lathe-permission-<version>.zip into <out-dir>, the version being CHANGELOG.md's first
# release heading, and removes older ones there.
set -euo pipefail

repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
out=${1:?usage: pack.sh <out-dir>}
version=$(grep -m1 -oP '^## \[\K[0-9][^\]]*(?=\])' "$repo/CHANGELOG.md" || true)

fail() { echo "pack: $*" >&2; exit 1; }

# Writes the version into an archive's composer.json, which an artifact repository reads,
# and drops the path repositories that only resolve inside this checkout.
stamp() {
    # shellcheck disable=SC2016 # PHP code, not shell
    php -r '
        [$zip, $version] = array_slice($argv, 1);
        $archive = new ZipArchive;
        $archive->open($zip) === true || exit(1);
        $json = json_decode($archive->getFromName("composer.json"), true);
        $json["version"] = $version;
        unset($json["repositories"]);
        $archive->addFromString("composer.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $archive->close() || exit(1);
    ' "$1" "$version"
}

[[ -n $version ]] || fail "no release heading in CHANGELOG.md"
dist=$repo/dist
if [[ ! -f $dist/manifest.json ]]; then
    dist=$(dirname "$(git -C "$repo" rev-parse --path-format=absolute --git-common-dir)")/dist
fi
[[ -f $dist/manifest.json ]] || fail "dist/ is not built; run pnpm build in the main checkout"

mkdir -p "$out"
out=$(cd "$out" && pwd)
src=$(mktemp -d)
trap 'rm -rf "$src"' EXIT

# Tracked and new files as they stand, without what git ignores, plus the built dist/.
git -C "$repo" ls-files -z -co --exclude-standard \
    | tar -C "$repo" --null --ignore-failed-read -T - -cf - \
    | tar -C "$src" -xf - || fail "could not copy the tree"
cp -r "$dist" "$src/dist"

# An artifact repository offers every version in the folder, so only this one stays.
rm -f "$out"/lathe-lathe-*.zip "$out"/lathe-permission-*.zip
(cd "$src" && composer archive --format=zip --dir="$out" --file="lathe-lathe-$version" -q) \
    || fail "composer archive failed for lathe/lathe"
(cd "$src/plugins/permission" && composer archive --format=zip --dir="$out" --file="lathe-permission-$version" -q) \
    || fail "composer archive failed for lathe/permission"
for zip in "$out/lathe-lathe-$version.zip" "$out/lathe-permission-$version.zip"; do
    stamp "$zip" || fail "could not stamp $zip"
done
unzip -l "$out/lathe-lathe-$version.zip" | grep dist/manifest.json >/dev/null || fail "the archive has no dist/manifest.json"

echo "pack: wrote lathe-lathe-$version.zip and lathe-permission-$version.zip to $out"

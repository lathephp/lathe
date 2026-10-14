#!/usr/bin/env bash
# Writes the read-only split of lathe/permission into its own repository, as one new commit
# tagged v<version>. plugins/permission stays the source of truth; the split drops the
# monorepo's path repository and gate script. It pushes nothing.
#   bash scripts/split-permission.sh <version> <split-repo-dir>
# The split repo is created with `git init` when the folder does not exist.
set -euo pipefail

version=${1:?usage: split-permission.sh <version> <split-repo-dir>}
target=${2:?usage: split-permission.sh <version> <split-repo-dir>}
repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
source="$repo/plugins/permission"

[[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)\.[0-9]+)?$ ]] || { echo "split-permission: $version is not a SemVer version" >&2; exit 1; }

if [[ ! -d $target/.git ]]; then
    mkdir -p "$target"
    git -C "$target" init -q -b main
fi
git -C "$target" rev-parse -q --verify "refs/tags/v$version" >/dev/null && { echo "split-permission: tag v$version exists in $target" >&2; exit 1; }

# Replace the tree with the plugin's tracked files, minus the monorepo-only gate script.
git -C "$target" rm -rq --ignore-unmatch . >/dev/null
(cd "$repo" && git ls-files -z -- plugins/permission) | while IFS= read -r -d '' file; do
    relative=${file#plugins/permission/}
    [[ $relative == scripts/gates.sh ]] && continue
    mkdir -p "$target/$(dirname "$relative")"
    cp "$repo/$file" "$target/$relative"
done

# The split installs lathe/lathe from Packagist, not from the monorepo two folders up.
php -r '
    $file = $argv[1];
    $json = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    unset($json["repositories"]);
    file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
' "$target/composer.json"

git -C "$target" add -A
git -C "$target" commit -q -m "Lathe permission $version"
git -C "$target" tag -a "v$version" -m "lathe/permission $version"
echo "Tagged v$version in $target at $(git -C "$target" rev-parse --short HEAD). Nothing was pushed."

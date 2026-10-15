#!/usr/bin/env bash
# Cuts a release on a local branch and tag, and pushes nothing.
#   bash scripts/release.sh <version>
# From a clean, green main it: creates release/<version>; sets every package.json version;
# dates the CHANGELOG section; regenerates the upgrade guide; runs the build and force-adds dist/; writes
# THIRD-PARTY-LICENSES.md; commits; and tags the commit v<version>. main never gets dist/.
# Run `pnpm install --frozen-lockfile` and `composer install` first: the build and the licence
# list need them. LATHE_RELEASE_BUILD replaces the build command and LATHE_RELEASE_LICENSES_JSON feeds the
# licence list from a file instead of pnpm (both for the script's own test).
set -euo pipefail

version=${1:?usage: release.sh <version>}
tag="v$version"
branch="release/$version"
repo=$(git rev-parse --show-toplevel)
cd "$repo"

fail() { echo "release: $*" >&2; exit 1; }

[[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)\.[0-9]+)?$ ]] || fail "$version is not a SemVer version like 1.0.0 or 1.0.0-beta.1"
[[ $(git branch --show-current) == main ]] || fail "run it on main"
[[ -z $(git status --porcelain) ]] || fail "the tree is not clean"
git rev-parse -q --verify "refs/tags/$tag" >/dev/null && fail "tag $tag exists"
git rev-parse -q --verify "refs/heads/$branch" >/dev/null && fail "branch $branch exists"
grep -q "^## \[$version\] - Unreleased$" CHANGELOG.md || fail "CHANGELOG.md has no '## [$version] - Unreleased' section"

git switch -q -c "$branch"

for manifest in package.json packages/*/package.json; do
    node -e '
        const fs = require("node:fs");
        const [file, version] = process.argv.slice(1);
        const json = JSON.parse(fs.readFileSync(file, "utf8"));
        json.version = version;
        fs.writeFileSync(file, JSON.stringify(json, null, 2) + "\n");
    ' "$manifest" "$version"
done

sed -i "s/^## \[$version\] - Unreleased$/## [$version] - $(date -u +%Y-%m-%d)/" CHANGELOG.md

node scripts/upgrade-guide.mjs

eval "${LATHE_RELEASE_BUILD:-pnpm build}"
[[ -d dist ]] || fail "the build wrote no dist/"

if [[ -n ${LATHE_RELEASE_LICENSES_JSON:-} ]]; then
    node scripts/third-party-licenses.mjs --input "$LATHE_RELEASE_LICENSES_JSON"
else
    node scripts/third-party-licenses.mjs
fi

git add package.json packages/*/package.json CHANGELOG.md docs/getting-started/upgrade.md THIRD-PARTY-LICENSES.md
git add -f dist
git commit -q -m "Release $version"
git tag -a "$tag" -m "Lathe $version"

echo "Tagged $tag on $branch at $(git rev-parse --short HEAD). Nothing was pushed."
echo "To publish: git push origin $branch && git push origin $tag"

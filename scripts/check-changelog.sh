#!/usr/bin/env bash
# Fails a change that edits the package source without touching CHANGELOG.md,
# unless the pull request carries the label no-changelog.
#
# Usage: check-changelog.sh [base-ref]
#   base-ref         defaults to $LATHE_BASE_REF, then origin/main, then main
#   LATHE_PR_LABELS  space-separated labels of the pull request
set -uo pipefail

base=${1:-${LATHE_BASE_REF:-}}
if [ -z "$base" ]; then
    if git rev-parse --verify -q origin/main >/dev/null; then
        base=origin/main
    else
        base=main
    fi
fi

if ! changed=$(git diff --name-only "$base"...HEAD); then
    echo "check-changelog: cannot diff against $base" >&2
    exit 1
fi

if ! grep -qE '^(src/|packages/[^/]+/src/)' <<<"$changed"; then
    echo "check-changelog: no package source changed, nothing to record"
    exit 0
fi

for label in ${LATHE_PR_LABELS:-}; do
    if [ "$label" = no-changelog ]; then
        echo "check-changelog: label no-changelog is set, entry not required"
        exit 0
    fi
done

if grep -qx 'CHANGELOG.md' <<<"$changed"; then
    echo "check-changelog: CHANGELOG.md is updated"
    exit 0
fi

echo "check-changelog: the package source changed but CHANGELOG.md did not."
echo "Add an entry under Unreleased, or set the label no-changelog."
exit 1

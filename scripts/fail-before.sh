#!/usr/bin/env bash
# Proves a fix's new tests fail without the fix. It restores src/ and
# packages/*/src from the base, runs the test files the change added or
# changed and requires a failure, then runs them on the head and requires a pass.
#
# Usage: fail-before.sh [base-ref]
#   base-ref           defaults to $LATHE_BASE_REF, then origin/main, then main
#   LATHE_TEST_COMMAND test runner, given the test files (default vendor/bin/pest)
#
# Run it in a clean checkout: it rewrites the source folders and puts them back.
set -uo pipefail

base=${1:-${LATHE_BASE_REF:-}}
if [ -z "$base" ]; then
    if git rev-parse --verify -q origin/main >/dev/null; then
        base=origin/main
    else
        base=main
    fi
fi

read -ra runner <<<"${LATHE_TEST_COMMAND:-vendor/bin/pest}"

mapfile -t tests < <(git diff --name-only --diff-filter=AM "$base"...HEAD -- 'tests/*Test.php')
if [ ${#tests[@]} -eq 0 ]; then
    echo "fail-before: the change adds no test files, nothing to check"
    exit 0
fi

mapfile -t dirs < <({ git ls-tree -d --name-only "$base" -- src; git ls-tree -d --name-only "$base" packages/*/src 2>/dev/null; git ls-tree -d --name-only HEAD -- src; git ls-tree -d --name-only HEAD packages/*/src 2>/dev/null; } | sort -u)

restore_head() {
    for dir in "${dirs[@]}"; do
        git reset -q HEAD -- "$dir"
        git checkout -q -- "$dir" 2>/dev/null
        git clean -fdq -- "$dir"
    done
}
trap restore_head EXIT

for dir in "${dirs[@]}"; do
    rm -rf -- "$dir"
    git checkout -q "$base" -- "$dir" 2>/dev/null
done

echo "fail-before: running ${#tests[@]} test file(s) on the base source"
if "${runner[@]}" "${tests[@]}"; then
    echo "fail-before: the new tests also pass on the base source, so they do not pin the fix"
    exit 1
fi

restore_head

echo "fail-before: running the test files on the head"
if ! "${runner[@]}" "${tests[@]}"; then
    echo "fail-before: the new tests fail on the head"
    exit 1
fi

echo "fail-before: the new tests fail on the base and pass on the head"
exit 0

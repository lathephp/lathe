#!/usr/bin/env bash
# Fails on a physical Tailwind utility (ml-, mr-, pl-, pr-, left-, right-, text-left,
# text-right), which breaks right-to-left layouts. Use ms-, me-, ps-, pe-, start-, end-,
# text-start and text-end. Scans tracked files under resources/js, packages/*/src and
# resources/css. resources/css/styles is excluded: those sheets are ported from shadcn
# as they are and are replaced whole when the upstream sheets change.
set -uo pipefail

pattern='(?<![\w-])(-?(ml|mr|pl|pr|left|right)-(\d|\[|px\b|auto\b|full\b)|text-(left|right)\b)'

hits=$(git ls-files -z -- resources/js ':(glob)packages/*/src/**' resources/css ':!resources/css/styles' 2>/dev/null \
    | xargs -0 -r grep -HnP "$pattern" -- 2>/dev/null)

if [ -z "$hits" ]; then
    echo '0 hits'
    exit 0
fi

echo "$hits"
echo "$(echo "$hits" | wc -l) hits"
exit 1

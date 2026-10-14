#!/usr/bin/env bash
# Fails on code patterns the package bans. Scans tracked files under src,
# resources/js and packages/*/src. See the quality spec for why each is banned.
set -uo pipefail

hits=()

files() {
    git ls-files -z -- 'src' 'resources/js' ':(glob)packages/*/src/**' 2>/dev/null
}

check() {
    local label=$1 pattern=$2
    shift 2
    while IFS= read -r file; do
        hits+=("$label: $file")
    done < <(files | xargs -0 -r grep -lP "$pattern" -- "$@" 2>/dev/null)
}

sinks=docs/security/html-sinks.txt

check 'mass assignment opt-out' '\$guarded\s*=\s*\[\s*\]'
check 'eval' '(?<![\w>$])eval\s*\('
check 'unserialize' '(?<![\w>$:])unserialize\s*\('
check 'shell execution' '(?<![\w>$:])(shell_exec|passthru|proc_open|popen)\s*\('
check 'debug dump' '(?<![\w>$:])(dd|dump)\s*\('
check 'console logging' '\bconsole\.log\s*\('
check 'typescript suppression' '@ts-(ignore|nocheck)\b'
check 'final class' '^\s*final\s+(abstract\s+)?class\b'
check 'product name' "Laravel[ ]Lathe"
check 'strict types declaration' 'declare\s*\(\s*strict_types'

# Model queries open only inside ResourceQuery; Resource::query() itself is defined in Resource,
# and AggregateQuery opens the one query of a widget number.
while IFS= read -r file; do
    case $file in
        src/Query/*|src/Resources/Resource.php|src/Widgets/AggregateQuery.php) ;;
        *) hits+=("model query outside ResourceQuery: $file") ;;
    esac
done < <(files | xargs -0 -r grep -lP '\b[A-Z]\w*::query\s*\(|->newQuery\s*\(' -- 2>/dev/null)

# Raw HTML only where the sinks file lists the file and the reason.
while IFS= read -r file; do
    if [ ! -f "$sinks" ] || ! grep -qE "^${file}[[:space:]]" "$sinks"; then
        hits+=("html injection not listed in $sinks: $file")
    fi
done < <(files | xargs -0 -r grep -l 'dangerouslySetInnerHTML' -- 2>/dev/null)

if [ ${#hits[@]} -eq 0 ]; then
    echo '0 hits'
    exit 0
fi

printf '%s\n' "${hits[@]}"
echo "${#hits[@]} hits"
exit 1

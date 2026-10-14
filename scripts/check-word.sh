#!/usr/bin/env bash
# Keeps the private predecessor's name out of every public artifact.
# The word is built from a character class so this script never contains it.
set -uo pipefail

word='l[a]xa'
tickets='\b(L-0[0-9]{2}|B-0[0-9]|R-0[0-9]|CC-[0-9]{2}|SYS-[A-Z]|RES-[0-9]{2}|EXT-0[0-9]|MF-[0-9]{2})\b'
hits=()

# 1. Content of every tracked file.
while IFS= read -r file; do
    hits+=("content: $file")
done < <(git ls-files -z | xargs -0 -r grep -IilE "$word" -- 2>/dev/null)

# 2. File and folder names.
while IFS= read -r file; do
    hits+=("name: $file")
done < <(git ls-files | grep -iE "$word")

# 3. Built output, when a build exists.
if [ -d dist ]; then
    while IFS= read -r file; do
        hits+=("built: $file")
    done < <({ grep -rIilE "$word" dist; find dist | grep -iE "$word"; } | sort -u)
fi

# 4. Commit messages.
while IFS= read -r sha; do
    hits+=("commit message: $sha")
done < <(git log --all --format='%H %B' 2>/dev/null | grep -iE "$word" | cut -d' ' -f1)

# 5. Old ticket ids in code, tests and docs.
while IFS= read -r file; do
    hits+=("ticket id: $file")
done < <(git ls-files -z -- tests src packages docs | xargs -0 -r grep -IlP "$tickets" -- 2>/dev/null)

# 6. The product is never named as if it were part of Laravel.
while IFS= read -r file; do
    hits+=("branding: $file")
done < <(git ls-files -z | xargs -0 -r grep -IlE 'Laravel[[:space:]]+Lathe' -- 2>/dev/null)

if [ ${#hits[@]} -eq 0 ]; then
    echo '0 hits'
    exit 0
fi

printf '%s\n' "${hits[@]}"
echo "${#hits[@]} hits"
exit 1

#!/usr/bin/env bash
# Reports a failed scheduled run in one open issue with the given label: it comments on
# the oldest open one, or opens one. Matrix cells that fail at the same moment may each
# open an issue; each cell then keeps the oldest and closes its own as a duplicate.
#   open-issue.sh <label> <title> <body>
# Needs GH_TOKEN with issues: write, run inside the checkout.
set -euo pipefail

label=$1 title=$2 body=$3

oldest() {
    gh issue list --label "$label" --state open --json number --jq 'map(.number) | min // empty'
}

gh label create "$label" --force >/dev/null
existing=$(oldest)

if [[ -n $existing ]]; then
    gh issue comment "$existing" --body "$body"
    exit 0
fi

created=$(gh issue create --label "$label" --title "$title" --body "$body")
created=${created##*/}
kept=$(oldest)

if [[ $kept != "$created" ]]; then
    gh issue close "$created" --comment "Duplicate of #$kept."
    gh issue comment "$kept" --body "$body"
fi

#!/usr/bin/env bash
# Runs pnpm with its arguments, first making it resolve in shells that lack it
# (a fresh or background shell may not have the version manager's PATH).
set -uo pipefail

if ! command -v pnpm >/dev/null 2>&1 && command -v mise >/dev/null 2>&1; then
    eval "$(mise env -s bash)"
fi

if ! command -v pnpm >/dev/null 2>&1; then
    echo 'pnpm cannot be found. Install it (https://pnpm.io) or put it on PATH.' >&2
    exit 127
fi

exec pnpm "$@"

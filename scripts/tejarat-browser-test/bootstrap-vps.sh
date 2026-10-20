#!/usr/bin/env bash
# سازگاری با دستور قبلی — همان install-pnv-tejarat است.
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec bash "${DIR}/install-pnv-tejarat.sh" "$@"

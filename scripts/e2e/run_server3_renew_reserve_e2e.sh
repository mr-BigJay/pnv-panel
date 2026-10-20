#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STEP="${1:-}"

if [[ -z "$STEP" ]]; then
  echo "usage: $0 init|reserve|activate|status|clean" >&2
  exit 1
fi

: "${PNV_E2E_SUB_LINK:?set PNV_E2E_SUB_LINK}"
: "${PNV_E2E_ALLOWED_SUB_ID:?set PNV_E2E_ALLOWED_SUB_ID}"
: "${PNV_E2E_ALLOWED_EMAIL:?set PNV_E2E_ALLOWED_EMAIL}"
: "${PNV_XUI_CONFIG_SOURCE:?set PNV_XUI_CONFIG_SOURCE (read-only path to xui_servers.json)}"

export PNV_E2E_WORKDIR="${PNV_E2E_WORKDIR:-/tmp/pnv-e2e-renew-reserve}"
export PNV_E2E_USERNAME="${PNV_E2E_USERNAME:-pnv_e2e_user}"
export PNV_E2E_PLAN_GB="${PNV_E2E_PLAN_GB:-2}"
export PNV_E2E_PLAN_DAYS="${PNV_E2E_PLAN_DAYS:-7}"

exec php "$ROOT/scripts/e2e/server3_renew_reserve_e2e.php" --step="$STEP"

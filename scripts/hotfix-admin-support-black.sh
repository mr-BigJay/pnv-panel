#!/usr/bin/env bash
# Fix black/blank admin support (missing supportGetUserProfileSummary on main).
# Does NOT change nginx. Run on server:
#   cd /opt/pnv-panel && git pull && sudo ROOT=/var/www/html bash scripts/hotfix-admin-support-black.sh
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

for f in support_lib.php admin/support.php support_ui.css support_ui.js; do
  [[ -f "$SRC/$f" ]] || { echo "missing $SRC/$f"; exit 1; }
  cp "$SRC/$f" "$ROOT/$f"
  [[ "$f" == admin/* ]] && cp "$SRC/$f" "$ROOT/admin/$f"
done

cp "$SRC/admin/support.php" "$ROOT/admin/support.php"

if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/support_lib.php" >/dev/null
  php -l "$ROOT/admin/support.php" >/dev/null
fi

echo "OK: support_lib + admin/support.php (+ ui assets) updated under $ROOT"
echo "Test: /bigjay_controller/?page=support"

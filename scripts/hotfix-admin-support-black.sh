#!/usr/bin/env bash
# Fix black/blank admin support (fatal: supportGetUserProfileSummary on restored main).
# Does NOT touch nginx.
#   cd /opt/pnv-panel && git pull && sudo ROOT=/var/www/html bash scripts/hotfix-admin-support-black.sh
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

FILES=(
  support_lib.php
  support_ui.css
  support_ui.js
  admin/support.php
)

for f in "${FILES[@]}"; do
  [[ -f "$SRC/$f" ]] || { echo "missing $SRC/$f"; exit 1; }
  mkdir -p "$ROOT/$(dirname "$f")"
  cp "$SRC/$f" "$ROOT/$f"
  echo "  $f"
done

if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/support_lib.php" >/dev/null
  php -l "$ROOT/admin/support.php" >/dev/null
  echo "PHP syntax OK"
fi

echo "Done. Hard-refresh: /bigjay_controller/?page=support"

#!/usr/bin/env bash
set -euo pipefail

# Minimal rollback: only support-related files from origin/main.
# Use when full rollback is too heavy but support/admin index changes broke things.
#
#   cd /opt/pnv-panel && git fetch origin main
#   ROOT=/var/www/html bash scripts/rollback-support-files-only.sh

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

git -C "$SRC" fetch origin main

FILES=(
  support_lib.php
  support_ui.css
  support_ui.js
  admin/index.php
  admin/support.php
  admin/support-api.php
)

MAIN_SHA="$(git -C "$SRC" rev-parse --short origin/main)"
echo "Restore support files from origin/main ($MAIN_SHA) -> $ROOT"

for f in "${FILES[@]}"; do
  if ! git -C "$SRC" cat "origin/main:$f" > "$ROOT/$f" 2>/dev/null; then
    echo "  skip (not on main): $f" >&2
    continue
  fi
  echo "  $f"
done

rm -f "$ROOT/admin/support-check.php" "$ROOT/bigjay_controller/support-check.php" 2>/dev/null || true

echo "Done."

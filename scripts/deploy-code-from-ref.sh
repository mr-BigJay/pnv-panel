#!/usr/bin/env bash
set -euo pipefail

# Deploy a full consistent code tree from a git ref (branch/tag/SHA).
# Does NOT replace db/, invoices/, uploads/ (user data stays).
#
# Restore modern admin + support (same branch, no mixed old/new files):
#   cd /opt/pnv-panel
#   git fetch origin cursor/fix-support-admin-send-cbea
#   REF=origin/cursor/fix-support-admin-send-cbea ROOT=/var/www/html bash scripts/deploy-code-from-ref.sh
#
# Roll back to stable main:
#   REF=origin/main ROOT=/var/www/html bash scripts/deploy-code-from-ref.sh

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
REF="${REF:-origin/cursor/fix-support-admin-send-cbea}"
BACKUP="${BACKUP:-1}"

if ! git -C "$SRC" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "ERROR: SRC is not a git repo: $SRC" >&2
  exit 1
fi

echo ">> Fetch $REF in $SRC"
git -C "$SRC" fetch origin 2>/dev/null || true

if ! git -C "$SRC" rev-parse --verify "$REF" >/dev/null 2>&1; then
  echo "ERROR: unknown ref: $REF" >&2
  exit 1
fi

SHA="$(git -C "$SRC" rev-parse --short "$REF")"
echo ">> Deploy code under $ROOT from $REF ($SHA)"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

git -C "$SRC" archive "$REF" | tar -x -C "$TMP"

if [[ "$BACKUP" == "1" && -d "$ROOT" && "$(ls -A "$ROOT" 2>/dev/null || true)" != "" ]]; then
  BK="${ROOT}.deploy.${SHA}.$(date +%Y%m%d-%H%M%S)"
  echo ">> Backup current site -> $BK"
  cp -a "$ROOT" "$BK"
fi

mkdir -p "$ROOT"

echo ">> Rsync (db, invoices, uploads unchanged)..."

rsync -a --delete \
  --exclude 'db/' \
  --exclude 'invoices/' \
  --exclude 'uploads/' \
  --exclude 'temp/' \
  --exclude '.git/' \
  --exclude 'node_modules/' \
  "$TMP/" "$ROOT/"

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "$ROOT/db" "$ROOT/invoices" "$ROOT/uploads" "$ROOT/temp" 2>/dev/null || true
fi

if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/support_lib.php" >/dev/null 2>&1 && echo ">> PHP syntax OK (support_lib.php)"
fi

echo ""
echo "Done. Live code = $REF @ $SHA"
echo "Hard-refresh (Ctrl+Shift+R) or clear site data for panel.ticketin.ir"
echo "Admin support check (logged in): /bigjay_controller/support-check.php"

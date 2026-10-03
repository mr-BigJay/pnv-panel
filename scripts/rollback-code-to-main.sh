#!/usr/bin/env bash
set -euo pipefail

# Roll back deployed PHP/JS/CSS on the server to match origin/main.
# Does NOT overwrite db/, invoices/, uploads/ (user data stays).
#
# On server:
#   cd /opt/pnv-panel
#   git fetch origin main
#   ROOT=/var/www/html bash scripts/rollback-code-to-main.sh
#
# Optional: BACKUP=1 (default) copies whole ROOT before sync.

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKUP="${BACKUP:-1}"

if ! git -C "$SRC" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "ERROR: SRC is not a git repo: $SRC" >&2
  exit 1
fi

echo ">> Fetch origin/main in $SRC"
git -C "$SRC" fetch origin main

MAIN_SHA="$(git -C "$SRC" rev-parse --short origin/main)"
echo ">> Roll back code under $ROOT to origin/main ($MAIN_SHA)"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

git -C "$SRC" archive origin/main | tar -x -C "$TMP"

if [[ "$BACKUP" == "1" && -d "$ROOT" && "$(ls -A "$ROOT" 2>/dev/null || true)" != "" ]]; then
  BK="${ROOT}.rollback.${MAIN_SHA}.$(date +%Y%m%d-%H%M%S)"
  echo ">> Backup current site -> $BK"
  cp -a "$ROOT" "$BK"
fi

mkdir -p "$ROOT"

echo ">> Rsync code (keeping db, invoices, uploads)..."

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

echo ""
echo "Done. Site code now matches origin/main @ $MAIN_SHA"
echo "Data dirs (db, invoices, uploads) were not replaced."
echo "Hard-refresh admin and user panel in the browser."

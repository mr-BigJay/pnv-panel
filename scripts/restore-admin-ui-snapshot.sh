#!/usr/bin/env bash
# Restore admin UI from known-good git commits (no redesign, no DB content changes).
#
# File sources:
#   REF (default origin/cursor/restore-admin-ui-cbea):
#     support_lib.php, admin/support.php, admin/support-api.php, admin/index.php,
#     admin/payments.php, admin/renews.php, instant_pay_lib.php, plan_ui_lib.php
#   SUPPORT_UI_REF (default 71dce0d4186e2a16ddccec812b5ab54c9ed8cf93):
#     support_ui.css, support_ui.js
#
# Does NOT: modify db/*.json content, nginx, invoices/, env configs.
# Does: optional chown www-data on db/uploads for write access only.
#
#   sudo ROOT=/var/www/html bash scripts/restore-admin-ui-snapshot.sh
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
REF="${REF:-origin/cursor/restore-admin-ui-cbea}"
SUPPORT_UI_REF="${SUPPORT_UI_REF:-71dce0d4186e2a16ddccec812b5ab54c9ed8cf93}"
WEB_USER="${WEB_USER:-www-data}"

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo"; exit 1; }

if [[ ! -d "$SRC/.git" ]]; then
  echo "ERROR: git clone required at SRC=$SRC" >&2
  exit 1
fi

git -C "$SRC" fetch origin 2>/dev/null || true
git -C "$SRC" rev-parse --verify "$REF" >/dev/null
git -C "$SRC" rev-parse --verify "$SUPPORT_UI_REF" >/dev/null

REF_SHA="$(git -C "$SRC" rev-parse --short "$REF")"
UI_SHA="$(git -C "$SRC" rev-parse --short "$SUPPORT_UI_REF")"
MANIFEST="$ROOT/.pnv-admin-ui-manifest.txt"

echo ">> Admin UI snapshot"
echo "   REF (PHP): $REF ($REF_SHA)"
echo "   UI assets: $SUPPORT_UI_REF ($UI_SHA)"
echo "   ROOT: $ROOT"

REF_FILES=(
  support_lib.php
  admin/support.php
  admin/support-api.php
  admin/index.php
  admin/payments.php
  admin/renews.php
  instant_pay_lib.php
  plan_ui_lib.php
)

UI_FILES=(
  support_ui.css
  support_ui.js
)

: >"$MANIFEST"
{
  echo "timestamp=$(date -Iseconds)"
  echo "ref=$REF ($REF_SHA)"
  echo "support_ui_ref=$SUPPORT_UI_REF ($UI_SHA)"
  echo "root=$ROOT"
} >>"$MANIFEST"

install_one() {
  local ref="$1" path="$2"
  local tmp dest dir
  dest="$ROOT/$path"
  dir="$(dirname "$dest")"
  mkdir -p "$dir"
  tmp="$(mktemp)"
  if ! git -C "$SRC" show "$ref:$path" >"$tmp" 2>/dev/null; then
    rm -f "$tmp"
    echo "ERROR: missing $path at $ref" >&2
    exit 1
  fi
  cp "$tmp" "$dest"
  rm -f "$tmp"
  chmod 644 "$dest"
  echo "$path <- $ref" >>"$MANIFEST"
  echo "  OK $path <- $ref"
}

for f in "${REF_FILES[@]}"; do
  install_one "$REF" "$f"
done

for f in "${UI_FILES[@]}"; do
  install_one "$SUPPORT_UI_REF" "$f"
done

mkdir -p "$ROOT/db" "$ROOT/uploads/support"
if id "$WEB_USER" >/dev/null 2>&1; then
  chown "$WEB_USER:$WEB_USER" "$ROOT/db" "$ROOT/uploads" 2>/dev/null || true
  [[ -f "$ROOT/db/support.json" ]] && chown "$WEB_USER:$WEB_USER" "$ROOT/db/support.json" && chmod 664 "$ROOT/db/support.json"
fi

if command -v php >/dev/null 2>&1; then
  for f in "${REF_FILES[@]}"; do
    php -l "$ROOT/$f" >/dev/null
  done
  echo ">> PHP syntax OK (all restored PHP files)"
fi

chmod 644 "$MANIFEST"

echo ""
echo "Manifest: $MANIFEST"
echo "Done. Run: bash scripts/verify-admin-deploy.sh"

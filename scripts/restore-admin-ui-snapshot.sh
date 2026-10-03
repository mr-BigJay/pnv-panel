#!/usr/bin/env bash
# Restore admin UI from known-good git commits (no redesign).
# Requires local clone: /opt/pnv-panel
#
# UI sources (verified by diff):
#   Support Telegram-style: support_ui.css/js @ 71dce0d (unchanged on branch)
#   Support PHP: admin/support.php @ REF (71dce0d markup + send/chat fixes)
#   Payments/Renews 3 tabs + cards: admin/payments.php, renews.php @ REF (b931fb8+)
#
#   sudo REF=origin/cursor/fix-support-admin-send-cbea ROOT=/var/www/html bash scripts/restore-admin-ui-snapshot.sh
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
REF="${REF:-origin/cursor/fix-support-admin-send-cbea}"
SUPPORT_UI_REF="${SUPPORT_UI_REF:-71dce0d4186e2a16ddccec812b5ab54c9ed8cf93}"

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo"; exit 1; }

git -C "$SRC" fetch origin 2>/dev/null || true
git -C "$SRC" rev-parse --verify "$REF" >/dev/null
git -C "$SRC" rev-parse --verify "$SUPPORT_UI_REF" >/dev/null

echo ">> Admin UI snapshot"
echo "   REF (PHP/logic): $REF ($(git -C "$SRC" rev-parse --short "$REF"))"
echo "   SUPPORT_UI_REF (css/js): $(git -C "$SRC" rev-parse --short "$SUPPORT_UI_REF")"
echo "   ROOT: $ROOT"

# Backend + admin pages from integration branch
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

# Telegram-style assets pinned to layout-fix commit (identical to REF today; explicit for safety)
UI_FILES=(
  support_ui.css
  support_ui.js
)

for f in "${REF_FILES[@]}"; do
  if ! git -C "$SRC" cat "$REF:$f" >"$ROOT/$f" 2>/dev/null; then
    echo "ERROR: missing $f at $REF" >&2
    exit 1
  fi
  echo "  OK $f <- $REF"
done

for f in "${UI_FILES[@]}"; do
  git -C "$SRC" cat "$SUPPORT_UI_REF:$f" >"$ROOT/$f"
  echo "  OK $f <- $SUPPORT_UI_REF"
done

mkdir -p "$ROOT/admin" "$ROOT/db" "$ROOT/uploads/support"
chown -R www-data:www-data "$ROOT/db" "$ROOT/uploads" 2>/dev/null || true
chmod 664 "$ROOT/db/support.json" 2>/dev/null || true

if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/admin/support.php" >/dev/null
  php -l "$ROOT/admin/payments.php" >/dev/null
  php -l "$ROOT/support_lib.php" >/dev/null
  echo ">> PHP syntax OK"
fi

echo ""
echo "Done. Hard-refresh admin:"
echo "  /bigjay_controller/?page=support"
echo "  /bigjay_controller/?page=payments&view=pending"
echo "  /bigjay_controller/?page=renews&view=pending"

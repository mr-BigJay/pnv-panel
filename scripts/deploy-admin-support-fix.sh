#!/usr/bin/env bash
set -euo pipefail

# Admin support chat + send fix (PR #3 branch)
# Usage on server:
#   export DEPLOY_COMMIT=fa5ce41   # or newer on cursor/fix-support-admin-send-cbea / main after merge
#   ROOT=/var/www/html bash scripts/deploy-admin-support-fix.sh

ROOT="${ROOT:-/var/www/html}"
COMMIT="${DEPLOY_COMMIT:?Set DEPLOY_COMMIT to git SHA (git rev-parse --short HEAD)}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}"

echo "Deploy admin support fix -> $ROOT (commit $COMMIT)"

mkdir -p "$ROOT/admin" "$ROOT/db" "$ROOT/uploads/support"

for f in \
  support_lib.php \
  support_ui.css \
  support_ui.js \
  admin/index.php \
  admin/support.php \
  admin/support-api.php \
  admin/support-check.php
do
  echo "  $f"
  curl -fsSL "$BASE/$f" -o "$ROOT/$f"
done

if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/support_lib.php" >/dev/null
  php -l "$ROOT/admin/support.php" >/dev/null
  php -l "$ROOT/admin/index.php" >/dev/null
  echo "PHP syntax OK"
fi

echo ""
echo "Post-deploy (adjust user if not www-data):"
echo "  chown www-data:www-data $ROOT/db/support.json 2>/dev/null || true"
echo "  chmod 664 $ROOT/db/support.json 2>/dev/null || true"
echo "  chown -R www-data:www-data $ROOT/uploads/support"
echo ""
echo "Verify (while logged in as admin):"
echo "  /bigjay_controller/support-check.php"
echo "Open chat:"
echo "  /bigjay_controller/?page=support&user=USERNAME"

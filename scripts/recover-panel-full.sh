#!/usr/bin/env bash
# Full recovery: one consistent code tree + nginx bigjay + permissions + support data.
# Run on server3:
#   cd /opt/pnv-panel && git pull origin cursor/fix-support-admin-send-cbea
#   sudo ROOT=/var/www/html bash scripts/recover-panel-full.sh
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
REF="${REF:-origin/cursor/fix-support-admin-send-cbea}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

[[ "$(id -u)" -eq 0 ]] || { echo "Use: sudo bash $0"; exit 1; }

cd "$SRC"
git fetch origin 2>/dev/null || true

echo "========== 1/4 Code (single branch, db untouched) =========="
RSYNC_DELETE=0 REF="$REF" ROOT="$ROOT" SRC="$SRC" bash "$SCRIPT_DIR/deploy-code-from-ref.sh"

echo ""
echo "========== 2/4 Permissions + support.json (code already synced in step 1) =========="
SKIP_HOTFIX=1 ROOT="$ROOT" SRC="$SRC" bash "$SCRIPT_DIR/fix-server-permissions-and-admin.sh"

echo ""
echo "========== 3/4 Nginx bigjay_controller (if panel.ticketin.ir vhost exists) =========="
if [[ -f /etc/nginx/sites-enabled/panel.ticketin.ir ]]; then
  if ! grep -q "PNV_BIGJAY_PATCHED" /etc/nginx/sites-enabled/panel.ticketin.ir 2>/dev/null; then
    DOMAIN=panel.ticketin.ir ROOT="$ROOT" bash "$SCRIPT_DIR/fix-nginx-panel-admin.sh" || {
      echo "WARN: nginx patch failed — admin may need /bigjay_controller/ with trailing slash"
    }
  else
    echo "nginx bigjay patch already present"
    nginx -t && systemctl reload nginx
  fi
else
  echo "skip nginx (no panel.ticketin.ir vhost)"
fi

echo ""
echo "========== 4/4 Smoke checks =========="
sudo -u www-data test -r "$ROOT/bigjay_controller/index.php" && echo "read index.php: OK"
sudo -u www-data test -w "$ROOT/db/support.json" && echo "write support.json: OK"
[[ ! -f "$ROOT/bigjay_controller/dashboard.php" ]] && echo "no legacy dashboard.php: OK"

echo ""
echo "Recovery done."
echo "  Admin: https://panel.ticketin.ir/bigjay_controller/?page=support"
echo "  If list empty but tickets>0 in log above, hard-refresh browser."

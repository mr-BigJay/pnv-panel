#!/bin/bash
# Deploy admin dashboard split stats + bottom nav stack — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/admin-dashboard-split-stats-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy admin dashboard (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "admin/index.php"
  "admin/dashboard.php"
  "admin/admin_nav.php"
  "admin/auth.php"
  "date_lib.php"
  "pnv_date_bootstrap.php"
  "bigjay_controller/index.php"
  "bigjay_controller/plans.php"
  "bigjay_controller/users.php"
  "bigjay_controller/payments.php"
  "bigjay_controller/renews.php"
  "bigjay_controller/telegram.php"
  "bigjay_controller/bale.php"
  "bigjay_controller/downloads.php"
  "bigjay_controller/xui-servers.php"
  "bigjay_controller/support-api.php"
  "bigjay_controller/user-profile.php"
  "bigjay_controller/auth.php"
  "bigjay_controller/functions.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "Checks:"
grep -c "statsSplitGrid" "${ROOT}/admin/dashboard.php" || true
grep -c "adminBottomNav" "${ROOT}/admin/admin_nav.php" || true
grep -c "require dirname" "${ROOT}/bigjay_controller/index.php" || true
echo ""
echo "Done. Open: /bigjay_controller/ (NOT old sidebar-only copy)"
echo "Hard-refresh dashboard (Ctrl+Shift+R)."

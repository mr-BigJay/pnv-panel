#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-500-error-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy admin support UI ==="
echo "Branch: ${BR}"
echo "Target: ${ROOT}"

files=(
  "admin/index.php"
  "admin/admin_nav.php"
  "admin/support.php"
  "admin/support-api.php"
  "admin/support-users-api.php"
  "support_lib.php"
  "support_ui.css"
  "support_ui.js"
  "profile_lib.php"
  "date_lib.php"
  "pnv_date_bootstrap.php"
  "bigjay_controller/support-api.php"
  "bigjay_controller/support-users-api.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
done

php -l "${ROOT}/admin/support.php"
php -l "${ROOT}/support_lib.php"
php -l "${ROOT}/bigjay_controller/support-api.php"

echo ""
echo "Done. Open support and hard-refresh (Ctrl+F5):"
echo "https://panel.ticketin.ir/bigjay_controller/index.php?page=support"

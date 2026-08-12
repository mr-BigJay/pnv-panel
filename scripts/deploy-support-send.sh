#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-support-send-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy support stack (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "admin/index.php"
  "admin/support.php"
  "admin/support-api.php"
  "admin/support-users-api.php"
  "admin/support-diagnose.php"
  "support_lib.php"
  "support_ui.css"
  "support_ui.js"
  "profile_lib.php"
  "bigjay_controller/support-api.php"
  "bigjay_controller/support-users-api.php"
  "bigjay_controller/support-diagnose.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
done

php -l "${ROOT}/admin/support.php"
php -l "${ROOT}/support_lib.php"

echo ""
echo "Recovering support.json from backup if available..."
php -r '
require "'"${ROOT}"'/support_lib.php";
$file = "'"${ROOT}"'/db/support.json";
$before = count(supportLoad($file));
echo "Tickets now: {$before}\n";
'

echo ""
echo "Done."
echo "1) Hard refresh: Ctrl+F5"
echo "2) Open: https://panel.ticketin.ir/bigjay_controller/index.php?page=support"
echo "3) Diagnose: https://panel.ticketin.ir/bigjay_controller/support-diagnose.php"

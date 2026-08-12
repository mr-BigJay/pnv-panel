#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-support-send-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy admin bottom nav ==="

files=(
  "admin/admin_nav.php"
  "admin/index.php"
  "bigjay_controller/index.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
done

php -l "${ROOT}/admin/index.php"
php -l "${ROOT}/admin/admin_nav.php"

echo ""
echo "Verify in browser View Source: <!-- pnv-admin-nav-v4 -->"
echo "Open dashboard on mobile width — bottom bar: پیام‌ها | تمدید | خرید | بیشتر"

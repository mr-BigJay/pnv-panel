#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-500-error-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "Deploy admin 500 fixes from branch: ${BR}"
echo "Target root: ${ROOT}"

files=(
  "admin/downloads.php"
  "bigjay_controller/index.php"
  "bigjay_controller/auth.php"
  "bigjay_controller/functions.php"
  "bigjay_controller/sw-cleanup.js"
  "bigjay_controller/downloads.php"
)

for rel in "${files[@]}"; do
  url="${BASE}/${rel}"
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  if ! curl -fsSL "$url" -o "$dest"; then
    echo "WARNING: could not fetch ${rel} (404?) — skipping"
    rm -f "$dest"
  fi
done

echo ""
echo "PHP syntax check (downloads.php):"
php -l "${ROOT}/admin/downloads.php"

echo ""
echo "Done. Test: https://panel.ticketin.ir/bigjay_controller/"

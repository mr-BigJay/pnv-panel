#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-500-error-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Restore admin UI (payments cards + support) ==="
echo "Branch: ${BR}"
echo "Target: ${ROOT}"

files=(
  "admin/payments.php"
  "admin/support.php"
  "admin/support-api.php"
  "admin/support-users-api.php"
  "support_lib.php"
  "support_ui.css"
  "support_ui.js"
  "profile_lib.php"
  "date_lib.php"
  "pnv_date_bootstrap.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
done

php -l "${ROOT}/admin/payments.php"
php -l "${ROOT}/admin/support.php"
php -l "${ROOT}/support_lib.php"

echo ""
echo "Done. Hard-refresh admin panel (Ctrl+F5)."

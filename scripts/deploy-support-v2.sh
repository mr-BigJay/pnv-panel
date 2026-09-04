#!/bin/bash
# Deploy support v2 (API + PHP shell + built React assets) — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy support v2 (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "support_lib.php"
  "admin/support-api.php"
  "admin/support-v2.php"
  "admin/support-v2-diag.php"
  "admin/index.php"
  "admin/user-profile.php"
  "assets/support/admin/support-admin.js"
  "assets/support/admin/support-admin.css"
  "bigjay_controller/support-v2-diag.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}?v=$(date +%s)" -o "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "=== Verify ==="

php -l "${ROOT}/support_lib.php"
php -l "${ROOT}/admin/support-api.php"

if grep -q 'function supportTicketsListForApi' "${ROOT}/support_lib.php"; then
  echo "  OK supportTicketsListForApi in support_lib.php"
else
  echo "  FAIL supportTicketsListForApi missing!"
  exit 1
fi

if [[ ! -s "${ROOT}/assets/support/admin/support-admin.js" ]]; then
  echo "  FAIL support-admin.js is empty!"
  exit 1
fi

echo ""
echo "=== Ping API (no login) ==="
curl -fsSL "https://panel.ticketin.ir/bigjay_controller/support-api.php?action=ping" || \
curl -fsSL "${ROOT%/html}/html/bigjay_controller/support-api.php?action=ping" 2>/dev/null || \
echo "  (open manually: /bigjay_controller/support-api.php?action=ping)"

echo ""
echo ""
echo "Done. Hard-refresh:"
echo "  /bigjay_controller/?page=support"
echo ""
echo "If still broken, open while logged in:"
echo "  /bigjay_controller/support-v2-diag.php"

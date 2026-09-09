#!/bin/bash
# Deploy limited renew expiryTime fix (30/90 day plans)
set -euo pipefail

BR="${BR:-cursor/fix-renew-limited-expiry-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html/bigjay_controller}"

echo "=== Deploy limited renew expiry fix (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "xui_lib.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

echo ""
grep -q "xuiAdjustClientExpiryLegacy" "${ROOT}/xui_lib.php" && echo "  OK xuiAdjustClientExpiryLegacy"
grep -q "xuiResolvePlanDaysForRenew" "${ROOT}/xui_lib.php" && echo "  OK xuiResolvePlanDaysForRenew"
grep -q "bulkAdjust+updateExpiry" "${ROOT}/xui_lib.php" && echo "  OK bulkAdjust+updateExpiry fallback"

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data "${ROOT}/xui_lib.php" 2>/dev/null || true
fi

if command -v php >/dev/null 2>&1; then
  php -r 'if(function_exists("opcache_reset")){opcache_reset(); echo "  OPCache reset\n";}'
fi

echo ""
echo "Done. Limited renews now extend expiryTime (30/90 day plans)."

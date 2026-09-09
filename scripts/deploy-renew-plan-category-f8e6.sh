#!/bin/bash
# Deploy renew limited/unlimited plan category fix
set -euo pipefail

BR="${BR:-cursor/fix-renew-plan-category-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy renew plan category fix (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "xui_lib.php"
  "plan_ui_lib.php"
  "renew.php"
  "renew-sub-meta-api.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "Verify markers:"
grep -q "pnvResolveSubTimeCategoryFromPanel" "${ROOT}/plan_ui_lib.php" && echo "  OK panel category resolver"
grep -q "renew-sub-meta-api.php" "${ROOT}/renew.php" && echo "  OK renew live meta fetch"
grep -q "fetchSubTimeCategory" "${ROOT}/renew.php" && echo "  OK renew JS refresh"

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data "${files[@]/#/${ROOT}/}" 2>/dev/null || true
fi

if command -v php >/dev/null 2>&1; then
  php -r 'if(function_exists("opcache_reset")){opcache_reset(); echo "  OPCache reset\n";}'
fi

echo ""
echo "Done. Hard-refresh /renew.php (Ctrl+Shift+R)."

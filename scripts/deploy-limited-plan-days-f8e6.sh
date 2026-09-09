#!/bin/bash
# Deploy limited plan expiry days fix (buy + renew provisioning)
set -euo pipefail

BR="${BR:-cursor/fix-limited-plan-days-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy limited plan days fix (branch: ${BR}) ==="
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
grep -q "xuiResolvePlanDays" "${ROOT}/xui_lib.php" && echo "  OK xuiResolvePlanDays"
grep -q "xuiExtendExpiryMs" "${ROOT}/xui_lib.php" && echo "  OK xuiExtendExpiryMs"
grep -q "xuiCreateClient(\$server, \$email, \$gb, '', \$days)" "${ROOT}/xui_lib.php" && echo "  OK buy passes days to createClient"

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data "${ROOT}/xui_lib.php" 2>/dev/null || true
fi

if command -v php >/dev/null 2>&1; then
  php -r 'if(function_exists("opcache_reset")){opcache_reset(); echo "  OPCache reset\n";}'
fi

echo ""
echo "Done. New limited buys/renews will set expiryTime correctly."

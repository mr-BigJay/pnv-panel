#!/bin/bash
# Deploy reserved limited renew + reset renew logic
set -euo pipefail

BR="${BR:-cursor/reserved-limited-renew-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html/bigjay_controller}"

echo "=== Deploy reserved limited renew (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "reserved_renewal_lib.php"
  "xui_lib.php"
  "instant_pay_lib.php"
  "sub-usage-api.php"
  "subscriptions.php"
  "subscriptions_ui.css"
  "renew.php"
  "dashboard.php"
  "plan_step_ui.css"
  "scripts/activate-reserved-renewals.php"
  "scripts/activate-reserved-renewals-runner.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

echo ""
grep -q "reservedRenewalCreate" "${ROOT}/reserved_renewal_lib.php" && echo "  OK reservedRenewalCreate"
grep -q "xuiResetClientForRenew" "${ROOT}/xui_lib.php" && echo "  OK xuiResetClientForRenew"
grep -q "subReservedBox" "${ROOT}/subscriptions.php" && echo "  OK subscriptions reserved UI"

if id www-data >/dev/null 2>&1; then
  for rel in "${files[@]}"; do
    chown www-data:www-data "${ROOT}/${rel}" 2>/dev/null || true
  done
fi

if command -v php >/dev/null 2>&1; then
  php -r 'if(function_exists("opcache_reset")){opcache_reset(); echo "  OPCache reset\n";}'
fi

echo ""
echo "Cron suggestion (every minute):"
echo "  * * * * * ROOT=${ROOT} php ${ROOT}/scripts/activate-reserved-renewals.php >/dev/null 2>&1"
echo ""
echo "Done."

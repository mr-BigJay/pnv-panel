#!/bin/bash
# Deploy admin payment tab lists — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/payment-tabs-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy admin payment tabs (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "instant_pay_lib.php"
  "payment_list_ui.php"
  "dashboard.php"
  "dashboard_lib.php"
  "subscription_lib.php"
  "admin/index.php"
  "admin/payments.php"
  "admin/renews.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}?v=$(date +%s)" -o "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "=== Verify ==="

php -l "${ROOT}/instant_pay_lib.php"
php -l "${ROOT}/payment_list_ui.php"
php -l "${ROOT}/admin/payments.php"
php -l "${ROOT}/admin/renews.php"

if grep -q 'payAdminTabs' "${ROOT}/payment_list_ui.php"; then
  echo "  OK payment_list_ui.php loaded"
else
  echo "  FAIL payment_list_ui.php incomplete!"
  exit 1
fi

if grep -q 'paymentListRenderAdminTabs' "${ROOT}/admin/payments.php"; then
  echo "  OK admin/payments.php has tabs"
else
  echo "  FAIL admin/payments.php missing tabs!"
  exit 1
fi

if grep -q 'paymentListRenderAdminTabs' "${ROOT}/admin/renews.php"; then
  echo "  OK admin/renews.php has tabs"
else
  echo "  FAIL admin/renews.php missing tabs!"
  exit 1
fi

echo ""
echo "Done. Hard-refresh admin:"
echo "  /bigjay_controller/?page=payments&tab=approved"
echo "  /bigjay_controller/?page=renews&tab=approved"

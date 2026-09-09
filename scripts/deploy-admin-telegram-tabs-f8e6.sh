#!/bin/bash
# Deploy admin tab default (approved) + telegram notify on auto-confirm
set -euo pipefail

BR="${BR:-cursor/fix-admin-telegram-tabs-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html/bigjay_controller}"

echo "=== Deploy admin telegram/tabs fix (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "instant_pay_lib.php"
  "payment_list_ui.php"
  "admin/payments.php"
  "admin/renews.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

echo ""
grep -q "instantPayNotifyTelegramConfirmed" "${ROOT}/instant_pay_lib.php" && echo "  OK instantPayNotifyTelegramConfirmed"
grep -q "paymentListActiveTab('approved')" "${ROOT}/admin/payments.php" && echo "  OK payments default tab approved"
grep -q "paymentListActiveTab('approved')" "${ROOT}/admin/renews.php" && echo "  OK renews default tab approved"

if id www-data >/dev/null 2>&1; then
  for rel in "${files[@]}"; do
    chown www-data:www-data "${ROOT}/${rel}" 2>/dev/null || true
  done
fi

if command -v php >/dev/null 2>&1; then
  php -r 'if(function_exists("opcache_reset")){opcache_reset(); echo "  OPCache reset\n";}'
fi

echo ""
echo "Done. Auto-confirmed orders notify Telegram admins; lists default to تایید شده tab."

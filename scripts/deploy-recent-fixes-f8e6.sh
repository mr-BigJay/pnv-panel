#!/bin/bash
# Deploy bundled fixes (~2026-09-07): duplicate pay, admin tabs, users menu
set -euo pipefail

BR="${BR:-cursor/deploy-recent-fixes-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy recent fixes (branch: ${BR}) ==="
echo "Target: ${ROOT}"
echo ""

if [[ ! -d "$ROOT" ]]; then
  echo "ERROR: ROOT not found: $ROOT" >&2
  exit 1
fi

files=(
  "instant_pay_lib.php"
  "xui_lib.php"
  "payment_list_ui.php"
  "admin/payments.php"
  "admin/renews.php"
  "admin/index.php"
  "admin/users.php"
  "buy-list.php"
  "renew-list.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "Verify markers..."
grep -q 'instantPayWithApproveLock' "${ROOT}/instant_pay_lib.php" && echo "  OK duplicate-provision lock"
grep -q 'instantPayResolveDisplayTab' "${ROOT}/instant_pay_lib.php" && echo "  OK admin payment tabs"
grep -q 'positionUserMenu' "${ROOT}/admin/users.php" && echo "  OK users dropdown fixed menu"
grep -q 'data-payments-ui="cards"' "${ROOT}/admin/payments.php" && echo "  OK admin payments card UI"

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data "${files[@]/#/${ROOT}/}" 2>/dev/null || true
fi

if command -v php >/dev/null 2>&1; then
  php -r 'if(function_exists("opcache_reset")){opcache_reset(); echo "  OPCache reset\n";}'
fi

for svc in php8.3-fpm php8.2-fpm php-fpm; do
  if systemctl is-active --quiet "$svc" 2>/dev/null; then
    systemctl reload "$svc" && echo "  reloaded $svc" && break
  fi
done

echo ""
echo "Done. Hard-refresh admin pages (Ctrl+Shift+R)."

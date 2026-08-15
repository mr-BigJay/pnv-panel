#!/bin/bash
# Deploy admin purchases list (card UI) — run ON THE SERVER as root
set -euo pipefail

BR="${BR:-main}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy admin payments UI (branch: ${BR}) ==="
echo "Target root: ${ROOT}"

if [[ ! -d "$ROOT" ]]; then
  echo "ERROR: ROOT not found: $ROOT" >&2
  echo "Try: ROOT=/var/www/pnv-panel bash $0" >&2
  exit 1
fi

files=(
  "admin/payments.php"
  "instant_pay_lib.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

dest="${ROOT}/admin/payments.php"
if grep -q 'data-payments-ui="cards"' "$dest"; then
  echo "  VERIFY: card UI marker found"
else
  echo "ERROR: admin/payments.php still old (no data-payments-ui=cards)" >&2
  exit 1
fi

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data "${ROOT}/admin/payments.php" "${ROOT}/instant_pay_lib.php" 2>/dev/null || true
fi

# bust PHP opcache if possible
if command -v php >/dev/null 2>&1; then
  php -r 'if(function_exists("opcache_reset")){opcache_reset(); echo "  OPCache reset\n";} else {echo "  OPCache not active\n";}' || true
fi

for svc in php8.3-fpm php8.2-fpm php-fpm; do
  if systemctl is-active --quiet "$svc" 2>/dev/null; then
    systemctl reload "$svc" && echo "  reloaded $svc" && break
  fi
done

echo ""
echo "Done. Open admin → لیست خرید های جدید"
echo "You should see a green «cards» tag next to the title."
echo "Hard-refresh: Ctrl+Shift+R"

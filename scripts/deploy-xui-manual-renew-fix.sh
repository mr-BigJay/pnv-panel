#!/bin/bash
# Deploy 3x-ui manual renew approve fix (form updateClient + clients/update) — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/renew-recreate-xui-cbea}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html/bigjay_controller}"

echo "=== Deploy XUI manual renew fix (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "xui_lib.php"
  "renew_reserve_lib.php"
  "admin/renews.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  php -l "${dest}" >/dev/null
  echo "  OK ${rel}"
done

echo ""
echo "Verify markers (expect >=1 each):"
grep -c "xuiApiRequestForm" "${ROOT}/xui_lib.php" || true
grep -c "xuiInboundUpdateClientRequest" "${ROOT}/xui_lib.php" || true
grep -c "xuiProvisionErrorMessage" "${ROOT}/xui_lib.php" || true
echo ""
if command -v systemctl >/dev/null 2>&1; then
  for svc in php8.2-fpm php8.1-fpm php-fpm; do
    if systemctl is-active --quiet "${svc}" 2>/dev/null; then
      echo "Reloading ${svc} (opcache)..."
      systemctl reload "${svc}" || true
    fi
  done
fi
echo "Done. Retry admin renew approve; error text should list API paths if still failing."

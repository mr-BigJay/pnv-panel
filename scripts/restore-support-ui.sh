#!/bin/bash
# Restore support UI to main branch (known-good layout)
set -euo pipefail

BR="${BR:-main}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Restore support UI from ${BR} ==="
echo "Target: ${ROOT}"

files=(
  "support_ui.css"
  "support_ui.js"
  "support.php"
  "admin/support.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

# admin/index.php: restore support layout CSS while keeping other local edits is risky;
# use deploy branch version that matches main support CSS + payment tab fixes.
ADMIN_BR="${ADMIN_BR:-cursor/deploy-recent-fixes-f8e6}"
ADMIN_BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${ADMIN_BR}"
curl -fsSL "${ADMIN_BASE}/admin/index.php" -o "${ROOT}/admin/index.php.new"
mv "${ROOT}/admin/index.php.new" "${ROOT}/admin/index.php"
echo "  OK admin/index.php (from ${ADMIN_BR})"

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data \
    "${ROOT}/support_ui.css" \
    "${ROOT}/support_ui.js" \
    "${ROOT}/support.php" \
    "${ROOT}/admin/support.php" \
    "${ROOT}/admin/index.php" 2>/dev/null || true
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
echo "Done. Hard-refresh support pages (Ctrl+Shift+R)."

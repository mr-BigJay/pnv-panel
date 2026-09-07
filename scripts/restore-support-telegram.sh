#!/bin/bash
# Restore Telegram-style support UI (React v2) — run ON THE SERVER as root
set -euo pipefail

BR="${BR:-cursor/deploy-recent-fixes-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Restore Telegram support UI (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "support.php"
  "support_lib.php"
  "support-api.php"
  "admin/support-v2.php"
  "admin/support.php"
  "admin/support-v2-diag.php"
  "admin/support-api.php"
  "admin/admin_nav.php"
  "admin/index.php"
  "admin/user-profile.php"
  "assets/support/admin/support-admin.js"
  "assets/support/admin/support-admin.css"
  "bigjay_controller/support-v2-diag.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}.new"
  mv "${dest}.new" "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "Verify..."

grep -q 'support-v2-root' "${ROOT}/support.php" && echo "  OK user support uses React v2"
grep -q 'data-support-ui="v2"' "${ROOT}/admin/support-v2.php" && echo "  OK admin support-v2 shell"
grep -q 'supportV2RenderModuleScript' "${ROOT}/support_lib.php" && echo "  OK support v2 asset helpers"
grep -q 'pnvAdminInclude' "${ROOT}/admin/index.php" && grep -q 'support-v2.php' "${ROOT}/admin/index.php" && echo "  OK admin index loads support-v2"
grep -q 'tg-voice-player' "${ROOT}/assets/support/admin/support-admin.js" && echo "  OK Telegram UI bundle"

js_bytes=$(wc -c < "${ROOT}/assets/support/admin/support-admin.js" | tr -d ' ')
if [[ "${js_bytes}" -lt 100000 ]]; then
  echo "  FAIL support-admin.js too small (${js_bytes} bytes)" >&2
  exit 1
fi
echo "  support-admin.js: ${js_bytes} bytes"

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${ROOT}/assets/support" 2>/dev/null || true
  chown www-data:www-data "${ROOT}/support.php" "${ROOT}/support_lib.php" "${ROOT}/support-api.php" \
    "${ROOT}/admin/support-v2.php" "${ROOT}/admin/support-api.php" "${ROOT}/admin/index.php" \
    "${ROOT}/admin/admin_nav.php" 2>/dev/null || true
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
echo "Done. Hard-refresh:"
echo "  User: /support.php"
echo "  Admin: /bigjay_controller/?page=support"
echo ""
echo "Diag (logged in): /bigjay_controller/support-v2-diag.php"

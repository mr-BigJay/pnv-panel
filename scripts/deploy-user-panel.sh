#!/bin/bash
# Deploy user dashboard, renew, and support — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/fix-user-panel-regressions-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy user panel (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "dashboard.php"
  "user_bg.css"
  "profile_lib.php"
  "profile-api.php"
  "user_panel.css"
  "user_nav.css"
  "user_nav.php"
  "support.php"
  "support_lib.php"
  "support_ui.js"
  "support_ui.css"
  "support-api.php"
  "pnv_date_bootstrap.php"
  "date_lib.php"
  "renew.php"
  "renew-list.php"
  "subscriptions.php"
  "buy.php"
  "plan_ui_lib.php"
  "subscriptions_ui.css"
  "sub-usage-api.php"
  "sub_usage_lib.php"
  "plan_step_ui.css"
  "instant_pay_lib.php"
  "instant-pay-api.php"
  "coupon_lib.php"
  "coupon-api.php"
  "subscription_lib.php"
  "telegram_lib.php"
  "bank_lib.php"
  "xui_lib.php"
  "announcement-api.php"
  "campaign_lib.php"
  "fonts.css"
  "admin/index.php"
  "admin/support.php"
  "admin/support-api.php"
  "admin/support-users-api.php"
  "admin/user-profile.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

mkdir -p "${ROOT}/uploads/support" "${ROOT}/uploads/avatars"
mkdir -p "${ROOT}/db"
chmod 775 "${ROOT}/uploads/support" "${ROOT}/uploads/avatars" 2>/dev/null || true

for name in support discount_codes discount_code_usages dashboard_announcements dashboard_announcement_reads; do
  dest="${ROOT}/db/${name}.json"
  if [[ ! -f "$dest" ]]; then
    echo '[]' > "$dest"
    echo "  INIT db/${name}.json"
  fi
  chmod 664 "$dest" 2>/dev/null || true
done

chmod 775 "${ROOT}/db" 2>/dev/null || true

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${ROOT}/db" "${ROOT}/uploads/support" "${ROOT}/uploads/avatars" 2>/dev/null || true
  chown www-data:www-data "${ROOT}/db/"*.json 2>/dev/null || true
fi

echo ""
echo "Done. Test:"
echo "  /dashboard.php"
echo "  /support.php"
echo "  /bigjay_controller/?page=support"
echo "Hard-refresh (Ctrl+Shift+R)."

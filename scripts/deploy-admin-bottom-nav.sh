#!/bin/bash
# Deploy admin bottom navigation (داشبورد button + all pages) — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy admin bottom nav (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "admin/admin_nav.php"
  "admin/index.php"
  "admin/dashboard.php"
  "admin/users.php"
  "admin/backup.php"
  "admin/plans.php"
  "admin/telegram.php"
  "admin/bale.php"
  "admin/sms.php"
  "admin/xui-servers.php"
  "admin/downloads.php"
  "admin/campaign_ui.php"
  "admin/campaigns.php"
  "admin/campaign-discounts.php"
  "admin/campaign-announcements.php"
  "bigjay_controller/index.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "Checks:"
grep -c "adminBottomNavDashboard" "${ROOT}/admin/admin_nav.php" || true
grep -c "adminPageEnd" "${ROOT}/admin/index.php" || true
echo ""
echo "Done. Hard-refresh /bigjay_controller/ on mobile width."
echo "Bottom nav order (RTL): بیشتر | داشبورد | خرید | تمدید | پیام‌ها"

#!/bin/bash
# Deploy admin campaigns feature — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/admin-campaigns-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy campaigns (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "campaign_lib.php"
  "announcement-api.php"
  "coupon-api.php"
  "instant-pay-api.php"
  "instant_pay_lib.php"
  "dashboard.php"
  "admin/campaigns.php"
  "admin/campaign-discounts.php"
  "admin/campaign-announcements.php"
  "admin/admin_nav.php"
  "admin/index.php"
  "bigjay_controller/campaigns.php"
  "bigjay_controller/campaign-discounts.php"
  "bigjay_controller/campaign-announcements.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

for name in discount_codes discount_code_usages dashboard_announcements dashboard_announcement_reads; do
  dest="${ROOT}/db/${name}.json"
  if [[ ! -f "$dest" ]]; then
    echo '[]' > "$dest"
    echo "  INIT db/${name}.json"
  fi
done

echo ""
echo "Done. Admin: /bigjay_controller/campaigns.php"

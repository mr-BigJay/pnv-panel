#!/bin/bash
# Deploy renew unlimited/limited plan-type fix — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/fix-renew-unlimited-plan-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy renew plan-type fix (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "plan_ui_lib.php"
  "renew.php"
  "instant_pay_lib.php"
  "xui_lib.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "Verify markers:"
grep -c "xuiFetchSubUserinfoExpire" "${ROOT}/xui_lib.php" || true
grep -c "pnvValidateRenewPlanCategory" "${ROOT}/plan_ui_lib.php" || true
grep -c "pnvResolveSubTimeCategory" "${ROOT}/renew.php" || true
echo ""
echo "Done. Hard-refresh /renew.php and test unlimited sub renew."

#!/bin/bash
# Deploy renew plan-type fix (limited vs unlimited) — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/fix-renew-plan-type-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy renew plan-type fix (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "xui_lib.php"
  "plan_ui_lib.php"
  "renew.php"
  "instant_pay_lib.php"
)

for f in "${files[@]}"; do
  dir="$(dirname "${ROOT}/${f}")"
  mkdir -p "${dir}"
  curl -fsSL -o "${ROOT}/${f}.new" "${BASE}/${f}"
  mv "${ROOT}/${f}.new" "${ROOT}/${f}"
  echo "  OK ${f}"
done

echo ""
echo "Verify on server:"
grep -c "xuiParsePlanDays" "${ROOT}/xui_lib.php" || true
grep -c "pnvResolveSubTimeCategory" "${ROOT}/plan_ui_lib.php" || true
grep -c "pnvValidateRenewPlanCategory" "${ROOT}/instant_pay_lib.php" || true
grep -c "pnvResolveSubTimeCategory" "${ROOT}/renew.php" || true
echo ""
echo "Done. Hard-refresh renew.php (Ctrl+Shift+R) and test jbkbgxf — should lock نامحدود زمانی."

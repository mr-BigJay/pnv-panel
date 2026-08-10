#!/bin/bash
# Deploy buy/renew payment UI to panel.ticketin.ir (run ON THE SERVER as root/www-data)
set -euo pipefail

ROOT="${1:-/var/www/html}"
BRANCH="${2:-cursor/buy-plan-card-ui-b94c}"
REPO="${3:-https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BRANCH}}"

echo "Deploying to ${ROOT} from ${REPO}"

for f in buy.php renew.php plan_step_ui.css; do
  curl -fsSL -o "${ROOT}/${f}.new" "${REPO}/${f}"
  mv "${ROOT}/${f}.new" "${ROOT}/${f}"
  echo "  OK ${f}"
done

echo ""
echo "Verify hint text on server:"
grep -n "instantExactHint\|کارت به کارت" "${ROOT}/buy.php" | head -3 || true
grep -n "instantExactHint::after\|کارت به کارت" "${ROOT}/plan_step_ui.css" | head -3 || true
echo ""
echo "Done. Hard-refresh buy.php in browser (Ctrl+Shift+R)."

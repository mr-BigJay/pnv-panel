#!/usr/bin/env bash
# Deploy Bale instant-pay matcher into the live panel root.
# Usage: bash deploy-instant-pay.sh [COMMIT] [/var/www/html]

set -euo pipefail

COMMIT="${1:-2ce6812}"
ROOT="${2:-/var/www/html}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}"

if [[ ! -d "$ROOT" ]]; then
  echo "ERROR: panel root not found: $ROOT"
  echo "Pass the correct path as 2nd arg."
  exit 1
fi

cd "$ROOT"
echo "Deploying commit $COMMIT into $ROOT"

for f in bale_lib.php instant_pay_lib.php bale-webhook.php buy.php renew.php plan_step_ui.css; do
  echo "→ $f"
  curl -fsSL "$BASE/$f" -o "$f"
done

mkdir -p admin bigjay_controller
curl -fsSL "$BASE/admin/bale.php" -o admin/bale.php
curl -fsSL "$BASE/admin/bale.php" -o bigjay_controller/bale.php

echo
echo "Verify parser version (must be postbank-plus-v2):"
php -r 'require "bale_lib.php"; echo baleParserVersion(), "\n";'
curl -fsS "https://panel.ticketin.ir/bale-webhook.php" || true
echo
echo "Done. Start a NEW payment window, pay exact amount, forward @postbank_bot message to the Bale bot."

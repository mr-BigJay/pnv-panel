#!/usr/bin/env bash
# Deploy Bale instant-pay + PostBank auto-listener stack.
# Usage: bash deploy-instant-pay.sh [COMMIT] [/var/www/html]

set -euo pipefail

COMMIT="${1:-HEAD}"
ROOT="${2:-/var/www/html}"
REPO="${REPO:-https://raw.githubusercontent.com/mr-BigJay/pnv-panel}"

if [[ "$COMMIT" == "HEAD" ]]; then
  echo "ERROR: pass commit SHA as first argument"
  exit 1
fi

BASE="${REPO}/${COMMIT}"

if [[ ! -d "$ROOT" ]]; then
  echo "ERROR: panel root not found: $ROOT"
  exit 1
fi

cd "$ROOT"
echo "Deploying commit $COMMIT into $ROOT"

FILES=(
  bale_lib.php
  bale-webhook.php
  postbank-ingest.php
  instant_pay_lib.php
  instant-pay-api.php
  time_lib.php
  buy.php
  renew.php
  plan_step_ui.css
)

for f in "${FILES[@]}"; do
  echo "→ $f"
  curl -fsSL "$BASE/$f" -o "$f"
done

mkdir -p admin bigjay_controller db tools uploads
curl -fsSL "$BASE/admin/bale.php" -o admin/bale.php
curl -fsSL "$BASE/bigjay_controller/bale.php" -o bigjay_controller/bale.php
curl -fsSL "$BASE/tools/postbank_bale_listener.py" -o tools/postbank_bale_listener.py
curl -fsSL "$BASE/tools/requirements-postbank.txt" -o tools/requirements-postbank.txt
curl -fsSL "$BASE/tools/postbank-listener.service" -o tools/postbank-listener.service
chmod +x tools/postbank_bale_listener.py

mkdir -p db
touch db/bale_webhook.log
chmod 666 db/bale_webhook.log 2>/dev/null || true

echo
echo "Parser version:"
php -r 'require "bale_lib.php"; echo baleParserVersion(), "\n";'

echo "Ingest health:"
curl -fsS "https://panel.ticketin.ir/postbank-ingest.php" || true
echo

echo "Done."
echo "Next steps on server:"
echo "  1) Admin → بله → save + register webhook"
echo "  2) pip3 install -r tools/requirements-postbank.txt"
echo "  3) python3 tools/postbank_bale_listener.py --login --session db/bale_user_session.bale"
echo "  4) Save POSTBANK_INGEST_SECRET to db/postbank-listener.env"
echo "  5) systemctl enable --now postbank-listener"

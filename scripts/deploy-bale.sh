#!/bin/bash
# Deploy Bale instant-pay bot (webhook + admin settings) — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy Bale instant-pay bot (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "bale-webhook.php"
  "bale_lib.php"
  "postbank-ingest.php"
  "instant_pay_lib.php"
  "instant-pay-api.php"
  "admin/bale.php"
  "admin/diag-bale.php"
  "bigjay_controller/bale.php"
  "bigjay_controller/diag-bale.php"
  "tools/postbank_bale_listener.py"
  "tools/postbank-listener.service"
  "tools/requirements-postbank.txt"
  "tools/README-postbank-auto.md"
)
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

mkdir -p "${ROOT}/db"
touch "${ROOT}/db/bale_webhook.log" 2>/dev/null || true
chmod 664 "${ROOT}/db/bale_webhook.log" 2>/dev/null || true

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data "${ROOT}/db/bale_webhook.log" 2>/dev/null || true
fi

echo ""
echo "Done. Then in admin panel:"
echo "  1) /bigjay_controller/bale.php → save settings + register webhook"
echo "  2) Send /start to @Jay24x7Pusbank_bot and save chat id"
echo "  3) Auto: setup postbank-listener (see admin Bale page)"
echo "  4) Manual: forward a @postbank_bot deposit message to the bot"

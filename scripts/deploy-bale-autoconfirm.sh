#!/bin/bash
# Deploy Bale auto-confirm stack — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy Bale auto-confirm (${BR}) ==="

files=(
  "bale_lib.php"
  "bale-webhook.php"
  "postbank-ingest.php"
  "instant_pay_lib.php"
  "xui_lib.php"
  "tools/postbank_bale_listener.py"
  "admin/diag-bale.php"
  "bigjay_controller/diag-bale.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

touch "${ROOT}/db/bale_webhook.log" 2>/dev/null || true
chmod 664 "${ROOT}/db/bale_webhook.log" 2>/dev/null || true

echo ""
echo "Checks:"
curl -fsS "${ROOT%/}/postbank-ingest.php" | head -c 120 || true
echo ""
curl -fsS -X POST "${ROOT%/}/bale-webhook.php" -H 'Content-Type: application/json' \
  -d '{"message":{"chat":{"id":1,"type":"private"},"from":{"id":1},"text":"/start"}}' | head -c 200 || true
echo ""

if systemctl is-active postbank-listener >/dev/null 2>&1; then
  sudo systemctl restart postbank-listener
  echo "restarted postbank-listener"
fi

echo ""
echo "Done. Re-register webhook in /bigjay_controller/bale.php if needed."

#!/bin/bash
# Deploy support v2 (API + PHP shell + built React assets) — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy support v2 (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "support_lib.php"
  "admin/support-api.php"
  "admin/support-v2.php"
  "admin/index.php"
  "assets/support/admin/support-admin.js"
  "assets/support/admin/support-admin.css"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

echo ""
echo "Done. Hard-refresh:"
echo "  /bigjay_controller/?page=support-v2"
echo ""
echo "Optional API check (while logged in as admin in browser, copy Cookie header):"
echo "  curl -sS -b 'PHPSESSID=...' '${ROOT%/}/../..'  # use panel URL instead"
echo "  curl -sS -b \"\$COOKIE\" 'https://panel.ticketin.ir/bigjay_controller/support-api.php?action=tickets'"

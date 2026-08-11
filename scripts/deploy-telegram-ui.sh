#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/telegram-bottom-menu-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "Deploy Telegram UI from branch: ${BR}"
echo "Target root: ${ROOT}"

files=(
  "telegram_lib.php"
  "telegram_poll.php"
  "telegram_xui.php"
  "admin/telegram.php"
)

for rel in "${files[@]}"; do
  url="${BASE}/${rel}"
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  curl -fsSL "$url" -o "$dest"
done

if [[ -d "${ROOT}/bigjay_controller" ]]; then
  cp "${ROOT}/admin/telegram.php" "${ROOT}/bigjay_controller/telegram.php"
  echo "-> ${ROOT}/bigjay_controller/telegram.php (copy)"
fi

echo ""
echo "Installed TELEGRAM_UI_VERSION marker:"
grep -n "TELEGRAM_UI_VERSION" "${ROOT}/telegram_lib.php" | head -1 || true

echo ""
echo "Restarting poll worker..."
pkill -f "telegram_poll.php" 2>/dev/null || true
sleep 1
nohup php "${ROOT}/telegram_poll.php" --loop >> "${ROOT}/db/telegram-poll.log" 2>&1 &
sleep 1
pgrep -af "telegram_poll.php" || echo "WARNING: poll worker not running"

echo ""
echo "Done. In Telegram send /start — home text must include: Reply Keyboard (v5)"

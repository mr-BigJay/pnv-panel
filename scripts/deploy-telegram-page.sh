#!/bin/bash
# Deploy صفحه اتصال تلگرام + فایل‌های مرتبط — روی سرور اجرا کنید
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

say(){ echo -e "$*"; }

say ">> Deploy Telegram page (branch: ${BR})"
say ">> Target: ${ROOT}"

files=(
  "telegram.php"
  "telegram_ui.css"
  "form_validation_fa.js"
  "form_validation_fa.php"
  "dashboard.php"
  "profile-api.php"
  "telegram_user_lib.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  say "  OK ${rel}"
done

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data "${ROOT}/telegram.php" "${ROOT}/telegram_ui.css" 2>/dev/null || true
fi

say ""
say "Done. Hard-refresh: Ctrl+Shift+R"
say "Test: ${ROOT}/telegram.php"

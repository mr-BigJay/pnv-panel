#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -f .env ]; then
  cp .env.example .env
  echo "فایل .env ساخته شد. لطفاً SECRET_KEY_BASE و POSTGRES_PASSWORD را ویرایش کنید."
  exit 1
fi

if ! grep -q '^POSTGRES_PASSWORD=.\+' .env || grep -q 'change_this_postgres_password' .env; then
  echo "خطا: POSTGRES_PASSWORD در .env تنظیم نشده است."
  exit 1
fi

if ! grep -q '^SECRET_KEY_BASE=.\+' .env || grep -q 'replace_with_long_random_string' .env; then
  echo "خطا: SECRET_KEY_BASE در .env تنظیم نشده است."
  echo "بسازید: openssl rand -hex 64"
  exit 1
fi

docker compose pull
docker compose up -d postgres redis
sleep 8
docker compose run --rm chatwoot bundle exec rails db:chatwoot_prepare
docker compose up -d

echo ""
echo "Chatwoot روی پورت 3000 (localhost) بالا آمد."
echo "در .env باید باشد: FRONTEND_URL=https://panel.ticketin.ir"
echo "Nginx: مسیرهای /app/ /api/ /public/ /packs/ را به 127.0.0.1:3000 پروکسی کنید."
echo "پنل Chatwoot: https://panel.ticketin.ir/app/"
echo "سپس Website Inbox بسازید و Tokenها را در admin/chatwoot-settings.php وارد کنید."

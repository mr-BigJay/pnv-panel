#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -f .env ]; then
  cp .env.example .env
  echo "فایل .env ساخته شد. لطفاً SECRET_KEY_BASE و POSTGRES_PASSWORD را ویرایش کنید."
  exit 1
fi

docker compose pull
docker compose up -d postgres redis
sleep 8
docker compose run --rm chatwoot bundle exec rails db:chatwoot_prepare
docker compose up -d

echo ""
echo "Chatwoot روی پورت 3000 (localhost) بالا آمد."
echo "برای دسترسی عمومی، Nginx را به 127.0.0.1:3000 پروکسی کنید."
echo "سپس در Chatwoot یک Website Inbox بسازید و Tokenها را در admin/chatwoot-settings.php وارد کنید."

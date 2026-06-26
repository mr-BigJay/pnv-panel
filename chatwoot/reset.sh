#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"

echo "=== Chatwoot reset ==="

echo "[1/6] توقف کانتینرها..."
docker compose down --remove-orphans 2>/dev/null || true

echo "[2/6] بررسی منابع..."
free -h
df -h / | tail -1

RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
if [ "$RAM_MB" -lt 1800 ]; then
  echo ""
  echo "هشدار: RAM شما حدود ${RAM_MB}MB است."
  echo "Chatwoot معمولاً به حداقل 2GB نیاز دارد."
  echo "اگر باز هم هنگ کرد، Swap اضافه کنید (دستور پایین پیام) یا از مسنجر داخلی پنل استفاده کنید."
  echo ""
fi

if [ ! -f .env ]; then
  echo "خطا: فایل .env وجود ندارد."
  exit 1
fi

echo "[3/6] بالا آوردن Postgres و Redis..."
docker compose up -d postgres redis

echo "منتظر Postgres..."
for i in $(seq 1 30); do
  if docker compose exec -T postgres pg_isready -U chatwoot >/dev/null 2>&1; then
    echo "Postgres آماده است."
    break
  fi
  sleep 2
done

echo "[4/6] آماده‌سازی دیتابیس..."
docker compose run --rm chatwoot bundle exec rails db:chatwoot_prepare

echo "[5/6] بالا آوردن Chatwoot (بدون Sidekiq برای تست)..."
docker compose up -d chatwoot

echo "منتظر بالا آمدن Rails (حداکثر 2 دقیقه)..."
OK=0
for i in $(seq 1 24); do
  if curl -fsS -o /dev/null http://127.0.0.1:3000 2>/dev/null; then
    OK=1
    break
  fi
  echo "  ... ${i}x5 ثانیه"
  sleep 5
done

if [ "$OK" -eq 1 ]; then
  echo "Chatwoot پاسخ داد."
  docker compose up -d sidekiq
  echo ""
  echo "آدرس: https://panel.ticketin.ir/chat"
  exit 0
fi

echo ""
echo "Chatwoot هنوز بالا نیامد. لاگ:"
timeout 10 docker logs chatwoot-chatwoot-1 --tail 40 2>&1 || true
echo ""
echo "اگر RAM کم است:"
echo "  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile"
exit 1

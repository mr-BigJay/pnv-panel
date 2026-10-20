# Rollback

## فوری (بدون حذف Worker)

1. ادمین → تجارت: **enabled** خاموش یا **dry_run** روشن.
2. `systemctl stop tejarat-browser-worker` (یا توقف container).
3. تأیید دستی / بله همچنان فعال می‌ماند.

## بازگشت کد

```bash
cd /var/www/html
git checkout main -- tejarat_auto_verify_lib.php tejarat_worker_lib.php tejarat-worker-ingest.php admin/tejarat.php
# یا deploy نسخه قبلی release
```

## پاک‌سازی داده Worker (اختیاری)

```bash
rm -f /var/www/html/db/tejarat_worker_inbox.json /var/www/html/db/tejarat_worker_health.json
rm -rf /var/lib/pnv/tejarat-worker/playwright_storage.json
```

فایل‌های Production `payments.csv` و `instant_payments.json` را دست نزنید مگر backup گرفته باشید.

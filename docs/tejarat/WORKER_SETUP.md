# راه‌اندازی Worker تجارت روی Ubuntu (`/var/www/html`)

## پیش‌نیاز

- PHP PNV با `tejarat-worker-ingest.php` در دسترس HTTPS داخلی/عمومی
- Secret مشترک در `db/tejarat_verify.json` → `worker_ingest_secret` (از پنل **ادمین → تجارت**)
- **Dry Run = روشن** تا تأیید دستی شما

## نصب (venv)

```bash
cd /var/www/html/services/tejarat-browser-worker
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
playwright install chromium
```

## فایل محیط (`/etc/pnv/tejarat-worker.env`)

```bash
PNV_TEJARAT_INGEST_URL=https://YOUR_PANEL/tejarat-worker-ingest.php
PNV_TEJARAT_INGEST_SECRET=GENERATE_LONG_RANDOM_SECRET
TEJARAT_COLLECT_MODE=fixture
TEJARAT_POLL_INTERVAL_SECONDS=120
TEJARAT_BACKOFF_MAX_SECONDS=600
PNV_WORKER_STORAGE=/var/lib/pnv/tejarat-worker
TEJARAT_RUN_ONCE=1
```

برای تست ingest بدون بانک: `TEJARAT_FIXTURE_JSON=/var/lib/pnv/tejarat-worker/fixture.json`

## تست ایزوله VPS

ر.ک. **`ISOLATED_VPS_TEST.md`** — نصب در `/opt/tejarat-browser-test` بدون دست زدن به Production.

## ورود اولیه (live)

1. `TEJARAT_COLLECT_MODE=live`
2. `python -m worker.manual_login` با `TEJARAT_HEADLESS=false` (OTP/CAPTCHA را خودتان در مرورگر وارد کنید)
3. پس از ذخیره `playwright_storage.json` در `PNV_WORKER_STORAGE`، `TEJARAT_HEADLESS=true`
4. `TEJARAT_STMT_SELECTORS_JSON` را با DevTools پر کنید (ردیف گردش، مبلغ، مرجع)
5. در صورت نیاز منو: `TEJARAT_NAV_STEPS_JSON` (لیست `{ "click": "...", "wait_ms": 800 }`)

## Systemd

```bash
sudo cp /var/www/html/services/tejarat-browser-worker/systemd/tejarat-browser-worker.service.example /etc/systemd/system/tejarat-browser-worker.service
sudo systemctl daemon-reload
sudo systemctl enable --now tejarat-browser-worker
```

## Docker

```bash
cd /var/www/html/services/tejarat-browser-worker
docker build -t pnv-tejarat-worker .
docker run --env-file /etc/pnv/tejarat-worker.env -v /var/lib/pnv/tejarat-worker:/data/storage pnv-tejarat-worker
```

## Cron PHP (پنل)

```cron
* * * * * php /var/www/html/scripts/cron-tejarat-verify.php >> /var/log/pnv-tejarat-cron.log 2>&1
```

## Health Check

```bash
php /var/www/html/scripts/tejarat-health-check.php
echo exit=$?
```

## فعال‌سازی واقعی (پس از Dry Run)

1. ingest و cron چند روز بدون خطا
2. در ادمین: `dry_run` خاموش + `provider=browser_worker`
3. مانیتور `matches_confirmed` و `manual_review`

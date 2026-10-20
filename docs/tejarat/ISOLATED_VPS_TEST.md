# تست ایزوله روی VPS (`/opt/tejarat-browser-test`)

این مسیر **جایگزین `/var/www/html` نیست** — اسکریپت نصب به Production و تنظیمات PNV روی `/var/www/html` **دست نمی‌زند**.

روی VPS شما از قبل وجود دارد: Playwright، Chromium، `venv/`، `browsers/`، `vnc/`، Xvfb با **`DISPLAY=:91`**.

## پیش‌نیاز: شاخه PR #34 روی VPS

اسکریپت `setup-isolated.sh` **روی `main` نیست** — فقط روی `cursor/tejarat-browser-worker-cbea`.

```bash
cd ~/pnv-panel
git fetch origin cursor/tejarat-browser-worker-cbea
git checkout cursor/tejarat-browser-worker-cbea
git pull origin cursor/tejarat-browser-worker-cbea
test -f scripts/tejarat-browser-test/setup-isolated.sh && echo OK || echo "هنوز فایل نیست — مسیر repo را چک کنید"
```

اگر `~/pnv-panel` همان Production است، **checkout نکنید**؛ clone جدا بزنید:

```bash
git clone -b cursor/tejarat-browser-worker-cbea --depth 1 \
  https://github.com/mr-BigJay/pnv-panel.git ~/pnv-panel-tejarat
cd ~/pnv-panel-tejarat
```

## نصب امن (غیرمخرب، قابل تکرار)

از همان clone (نه از `/var/www/html`):

```bash
cd ~/pnv-panel-tejarat   # یا ~/pnv-panel بعد از checkout شاخه بالا

# مرحله ۱ — فقط sync کد + بکاپ؛ venv/browsers/vnc/session/db حفظ می‌شوند
sudo PNV_TEJARAT_USER=tejarat-browser \
     PNV_TEJARAT_DISPLAY=:91 \
     PNV_WORKER_STORAGE=/opt/tejarat-browser-test/session \
     bash scripts/tejarat-browser-test/setup-isolated.sh

# اگر unit یا env «تعارض» گزارش شد، diff را ببینید:
ls -la /opt/tejarat-browser-test/.setup-backups/*/

# مرحله ۲ — پس از بررسی بکاپ، اعمال systemd/env
sudo PNV_TEJARAT_USER=tejarat-browser \
     PNV_TEJARAT_DISPLAY=:91 \
     PNV_WORKER_STORAGE=/opt/tejarat-browser-test/session \
     PNV_TEJARAT_CONFIRM_SERVICES=1 \
     bash scripts/tejarat-browser-test/setup-isolated.sh
```

| محافظت‌شده در re-run | مسیر نمونه |
|----------------------|------------|
| Python venv | `/opt/tejarat-browser-test/venv/` |
| Chromium Playwright | `/opt/tejarat-browser-test/browsers/` |
| VNC/Xvfb | `/opt/tejarat-browser-test/vnc/` |
| نشست بانک + secret | `/opt/tejarat-browser-test/session/` (`playwright_storage.json`, `ingest.secret`) |
| تنظیمات verify | `db/tejarat_verify.json` (**`dry_run: true` دست‌نخورده**) |

کاربر پیش‌فرض: **`tejarat-browser`** — ورود دستی و Worker هر دو `PNV_WORKER_STORAGE=/opt/tejarat-browser-test/session`.

سرویس‌ها (فقط ingest تست روی localhost):

| Unit | نقش |
|------|-----|
| `pnv-tejarat-php-test` | PHP روی `127.0.0.1:8765` |
| `tejarat-browser-worker-test` | Worker → ingest (ExecStart=`run-worker.sh`) |

## Dry Run

1. کارت مقصد را در `db/tejarat_verify.json` بگذارید؛ **Dry Run در ادمین روشن بماند.**
2. `sudo -u tejarat-browser bash /opt/tejarat-browser-test/scripts/tejarat-browser-test/run-dry-run-e2e.sh`
3. ادمین ایزوله: tunnel به `127.0.0.1:8765/admin/tejarat.php`
4. تأیید دستی پرداخت در Payments/Renews Production **همچنان** برای قطع بانک / موارد مبهم فعال است.

## ورود دستی + دریافت واقعی

```bash
sudo -u tejarat-browser bash -lc '
  set -a; source /etc/pnv/tejarat-worker-test.env; set +a
  export TEJARAT_COLLECT_MODE=live
  export TEJARAT_HEADLESS=false
  export DISPLAY=:91
  cd /opt/tejarat-browser-test/services/tejarat-browser-worker
  export PYTHONPATH=/opt/tejarat-browser-test/services/tejarat-browser-worker
  /opt/tejarat-browser-test/venv/bin/python -m worker.manual_login
'
```

(selectors و `TEJARAT_DESTINATION_CARD` را در env اضافه کنید.)

پس از ذخیره نشست در `session/playwright_storage.json`:

```bash
sudo systemctl start tejarat-browser-worker-test
sudo journalctl -u tejarat-browser-worker-test -f
```

## Live روی Production

فقط پس از تأیید شما و چند روز Dry Run موفق — ر.ک. `ROLLBACK.md`. **`/var/www/html` بدون بکاپ merge نشود.**

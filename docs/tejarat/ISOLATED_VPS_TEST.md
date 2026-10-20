# تست ایزوله روی VPS (`/opt/tejarat-browser-test`)

این مسیر **جایگزین `/var/www/html` نیست** — برای Dry Run و دریافت واقعی گردش حساب قبل از فعال‌سازی Live روی Production است.

## نصب یک‌بار

```bash
cd /path/to/pnv-panel   # clone یا rsync از PR #34
sudo PNV_TEJARAT_USER=pnv bash scripts/tejarat-browser-test/setup-isolated.sh
sudo systemctl start tejarat-browser-worker-test
```

سرویس‌ها:

| Unit | نقش |
|------|-----|
| `pnv-tejarat-php-test` | PHP built-in روی `127.0.0.1:8765` (ingest + cron دستی) |
| `tejarat-browser-worker-test` | Worker Playwright → ingest |

Secret در `/var/lib/pnv/tejarat-worker-test/ingest.secret` و `/etc/pnv/tejarat-worker-test.env`.

## Dry Run (پیش از Live)

1. در `db/tejarat_verify.json` کارت مقصد واقعی را در `destination_cards` بگذارید (`dry_run: true` بماند).
2. `bash /opt/tejarat-browser-test/scripts/tejarat-browser-test/run-dry-run-e2e.sh`
3. ادمین ایزوله: `http://127.0.0.1:8765/admin/tejarat.php` (از طریق SSH tunnel) — وضعیت Worker و «تطبیق بدون صدور» را ببینید.
4. `php /opt/tejarat-browser-test/scripts/tejarat-health-check.php` باید exit 0 بدهد.

## ورود دستی + دریافت واقعی (بدون دور زدن OTP)

```bash
source /etc/pnv/tejarat-worker-test.env
export TEJARAT_COLLECT_MODE=live
export TEJARAT_HEADLESS=false
export DISPLAY=:99   # همراه Xvfb/VNC شما
export TEJARAT_DESTINATION_CARD=5859xxxxxxxxxxxx
export TEJARAT_STMT_SELECTORS_JSON='{"row":"...","reference_cell":"...","amount_cell":"...","date_cell":"...","time_cell":"..."}'
# اختیاری: کلیک تا صفحه گردش
export TEJARAT_NAV_STEPS_JSON='[{"click":"text=گردش حساب","wait_ms":1200}]'
export TEJARAT_LOGIN_PROBE_SELECTOR='input[type=password]'   # اگر لاگین دیده شد Worker login_required می‌دهد

cd /opt/tejarat-browser-test/services/tejarat-browser-worker
.venv/bin/python -m worker.manual_login
```

پس از ذخیره `playwright_storage.json`:

```bash
sudo sed -i 's/^TEJARAT_COLLECT_MODE=.*/TEJARAT_COLLECT_MODE=live/' /etc/pnv/tejarat-worker-test.env
echo 'TEJARAT_HEADLESS=true' | sudo tee -a /etc/pnv/tejarat-worker-test.env
sudo systemctl restart tejarat-browser-worker-test
```

هر چند دقیقه: `journalctl -u tejarat-browser-worker-test -f` — تعداد `txs=` و `session=logged_in`.

Cron تطبیق (همان root ایزوله):

```cron
* * * * * PNV_TEJARAT_VERIFY_CONFIG_PATH=/opt/tejarat-browser-test/db/tejarat_verify.json php /opt/tejarat-browser-test/scripts/cron-tejarat-verify.php >> /var/log/pnv-tejarat-test-cron.log 2>&1
```

## فعال‌سازی Live (فقط با تأیید شما)

1. چند روز Dry Run بدون خطا + حداقل یک تطبیق dry-run روی سفارش تست.
2. در ادمین: **Dry Run را خاموش کنید** (`dry_run: false`) — تأیید خودکار فقط برای واریزهای قطعی (مبلغ + کارت + زمان + شناسه + dedup) از مسیر `instantPayTryDepositMatch` انجام می‌شود.
3. **تأیید دستی ادمین** در Payments/Renews همچنان برای قطع بانک یا موارد مبهم (`manual_review`) باقی است.

Production `/var/www/html` را بدون بکاپ و اجازه صریح merge نکنید؛ پس از تأیید، فقط فایل‌های Tejarat + cron را overlay امن کنید (ر.ک. `docs/tejarat/ROLLBACK.md`).

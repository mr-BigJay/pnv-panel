# راه‌اندازی پرداخت خودکار تجارت — panel.ticketin.ir

جریان **مشابه پست‌بانک + بله** است؛ به‌جای پیام مسنجر، **Worker مرورگر** گردش `https://hb.tejaratbank.ir/account/log` را می‌خواند.

## آنچه من (Cloud Agent) انجام نمی‌دهم

- ورود با **رمز/OTP حساب شما** — فقط **شما** روی server3 با VNC (`DISPLAY=:91`) وارد hb.tejaratbank.ir می‌شوید.
- خاموش کردن **Dry Run** بدون تست — Live را خودتان در ادمین فعال می‌کنید.

## آنچه سیستم انجام می‌دهد

1. مشتری مبلغ encoded (Instant Pay) می‌سازد — مثل الان.  
2. Worker هر ~۲ دقیقه واریزهای **واریز/ credit** را می‌خواند → ingest پنل.  
3. Cron دقیقه‌ای `instantPayTryDepositMatch`: مبلغ + کارت تجارت + زمان + شناسه تراکنش.  
4. اگر قطعی بود و Dry Run خاموش: تأیید + provisioning + مرحله ۳ مشتری.  
5. اگر مبهم / قطع بانک: **تأیید دستی ادمین**.

---

## مرحله A — پنل + Worker (یک‌بار)

```bash
curl -fsSL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/tejarat-browser-worker-cbea/scripts/tejarat-browser-test/install-pnv-tejarat.sh" | \
  sudo PNV_PANEL_ROOT=/var/www/html/bigjay_controller \
       PNV_PUBLIC_URL=https://panel.ticketin.ir/bigjay_controller \
       PNV_TEJARAT_CONFIRM_SERVICES=1 \
       bash
```

Cron:

```cron
* * * * * php /var/www/html/bigjay_controller/scripts/cron-tejarat-verify.php >> /var/log/pnv-tejarat-cron.log 2>&1
```

ادمین → **تجارت**: فعال، Provider **Worker مرورگر**، **Dry Run روشن**، کارت(های) مقصد.

---

## اگر `Permission denied` روی venv یا `playwright not installed`

```bash
sudo bash /root/pnv-panel-tejarat/scripts/tejarat-browser-test/fix-permissions.sh
```

## مرحله B — ورود بانک (شما)

VNC را باز کنید، سپس **دسترسی X11 برای worker user**:

```bash
sudo DISPLAY=:91 bash /root/pnv-panel-tejarat/scripts/tejarat-browser-test/prepare-x11.sh
```

اعمال env پیشنهادی (PLAYWRIGHT_BROWSERS_PATH):

```bash
sudo PNV_TEJARAT_SOURCE=/root/pnv-panel-tejarat PNV_TEJARAT_CONFIRM_SERVICES=1 \
  bash /root/pnv-panel-tejarat/scripts/tejarat-browser-test/setup-isolated.sh
```

ورود دستی:

```bash
sudo -u tejarat-browser bash -lc '
  set -a; source /etc/pnv/tejarat-worker-test.env; set +a
  export TEJARAT_HEADLESS=false
  export DISPLAY=:91
  export TEJARAT_BANK_URL=https://hb.tejaratbank.ir/account/log
  export TEJARAT_STATEMENT_URL=https://hb.tejaratbank.ir/account/log
  cd /opt/tejarat-browser-test/services/tejarat-browser-worker
  export PYTHONPATH=$PWD
  /opt/tejarat-browser-test/venv/bin/python -m worker.manual_login
'
```

---

## مرحله C — Selector + تست Dry Run

```bash
sudo -u tejarat-browser bash /opt/tejarat-browser-test/services/tejarat-browser-worker/scripts/run-probe.sh
cat /opt/tejarat-browser-test/session/statement_probe.json
```

Selectorها را در `/etc/pnv/tejarat-worker-test.env` بگذارید (ر.ک. `SELECTORS.md`).

```bash
sudo systemctl restart tejarat-browser-worker-test
sudo journalctl -u tejarat-browser-worker-test -f
```

یک سفارش **waiting** با کارت تجارت بسازید؛ در ادمین→تجارت «Poll once» — باید `dry_run=1` و تطبیق ببینید.

---

## مرحله D — Live (بعد از تأیید خودتان)

1. چند تطبیق Dry Run موفق.  
2. ادمین → تجارت: **Dry Run خاموش**.  
3. Worker و cron را مانیتور کنید؛ در صورت `login_required` دوباره `manual_login`.

Secret ingest باید بین `db/tejarat_verify.json` پنل و `/etc/pnv/tejarat-worker-test.env` یکی باشد.

# Selectorهای گردش حساب تجارت (`hb.tejaratbank.ir/account/log`)

UI بانک از بیرون قابل scrape نیست؛ **روی server3 با VNC** بعد از ورود خودتان selectorها را ثبت کنید.

## ۱) ورود دستی (OTP/CAPTCHA با خودتان)

```bash
sudo -u tejarat-browser bash -lc '
  set -a; source /etc/pnv/tejarat-worker-test.env; set +a
  export TEJARAT_COLLECT_MODE=live
  export TEJARAT_HEADLESS=false
  export DISPLAY=:91
  export TEJARAT_BANK_URL=https://hb.tejaratbank.ir/account/log
  export TEJARAT_STATEMENT_URL=https://hb.tejaratbank.ir/account/log
  cd /opt/tejarat-browser-test/services/tejarat-browser-worker
  export PYTHONPATH=$PWD
  /opt/tejarat-browser-test/venv/bin/python -m worker.manual_login
'
```

نشست: `/opt/tejarat-browser-test/session/playwright_storage.json`

## ۲) Probe (بدون چاپ رمز/HTML کامل)

```bash
sudo -u tejarat-browser bash /opt/tejarat-browser-test/services/tejarat-browser-worker/scripts/run-probe.sh
```

خروجی: `session/statement_probe.json` + `session/statement_probe.png` — تعداد ردیف و نمونه متن برای تنظیم JSON.

## ۳) فیلدهای `TEJARAT_STMT_SELECTORS_JSON`

| کلید | نقش |
|------|-----|
| `row` | هر ردیف گردش (مثلاً `table tbody tr`) |
| `amount_cell` | سلول مبلغ واریز |
| `reference_cell` | شماره پیگیری / مرجع (برای dedup) |
| `date_cell` / `time_cell` | زمان واریز |
| `credit_cell` | اختیاری؛ باید شامل «واریز» باشد یا خالی بگذارید |

`TEJARAT_DESTINATION_CARD` = کارت مقصدی که در پنل برای «تجارت» به مشتری نشان می‌دهید.

`TEJARAT_LOGIN_PROBE_SELECTOR` = عنصری که **فقط روی صفحه لاگین** دیده می‌شود (مثلاً `#username`) تا Worker `login_required` بدهد.

## ۴) تطبیق مثل پست‌بانک

1. Worker واریزهای **credit** را به `tejarat-worker-ingest.php` می‌فرستد.  
2. Cron `cron-tejarat-verify.php` → `instantPayTryDepositMatch` (مبلغ + کارت + زمان + شناسه).  
3. **Dry Run روشن:** فقط تطبیق بدون صدور.  
4. **Live:** در ادمین → تجارت Dry Run خاموش (بعد از تست موفق).  
5. **تأیید دستی** در Payments/Renews برای قطع بانک / مبهم همچنان فعال است.

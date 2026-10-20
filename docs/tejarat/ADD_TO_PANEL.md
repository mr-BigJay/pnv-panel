# یک دستور — Worker + اضافه به پنل (server3)

دیگر «clone جدا / checkout» لازم نیست. اسکript **`install-pnv-tejarat.sh`** همه‌چیز را انجام می‌دهد:

1. کد PR #34 را در `/root/pnv-panel-tejarat` می‌گیرد  
2. Worker را در **`/opt/tejarat-browser-test`** نصب می‌کند (`venv` / `browsers` / `vnc` / `session` حفظ می‌شوند)  
3. اگر بگویید، فایل‌های Tejarat را با **بکاپ** روی پنل می‌گذارد  

---

## فقط Worker (پنل `/var/www/html` عوض نمی‌شود)

```bash
curl -fsSL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/tejarat-browser-worker-cbea/scripts/tejarat-browser-test/install-pnv-tejarat.sh" | sudo bash
```

Worker به ingest محلی `127.0.0.1:8765` وصل می‌شود (تست).

---

## Worker + اضافه به پنل Production (پیشنهادی)

**دامنه پنل خود را** جای `https://pnv.example.com` بگذارید:

```bash
curl -fsSL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/tejarat-browser-worker-cbea/scripts/tejarat-browser-test/install-pnv-tejarat.sh" | \
  sudo PNV_PANEL_ROOT=/var/www/html \
       PNV_PUBLIC_URL=https://pnv.example.com \
       bash
```

سپس سرویس‌ها (بعد از نگاه به بکاپ در `/opt/tejarat-browser-test/.setup-backups/`):

```bash
curl -fsSL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/tejarat-browser-worker-cbea/scripts/tejarat-browser-test/install-pnv-tejarat.sh" | \
  sudo PNV_TEJARAT_CONFIRM_SERVICES=1 \
       PNV_PANEL_ROOT=/var/www/html \
       PNV_PUBLIC_URL=https://pnv.example.com \
       bash
```

### بعد از نصب در پنل

| کار | جزئیات |
|-----|--------|
| **Cron** | `* * * * * php /var/www/html/scripts/cron-tejarat-verify.php >> /var/log/pnv-tejarat-cron.log 2>&1` |
| **ادمین** | منو → **تجارت** — Provider: Worker مرورگر، **Dry Run روشن**، کارت مقصد، secret از قبل sync می‌شود |
| **Worker** | `systemctl start tejarat-browser-worker-test` بعد از ورود دستی بانک |
| **ورود بانک** | `DISPLAY=:91` — ر.ک. `ISOLATED_VPS_TEST.md` |
| **تأیید دستی** | Payments/Renews مثل قبل — برای قطع بانک / مبهم |

بکاپ فایل‌های overwritten: `/var/www/html/.backups-tejarat-<timestamp>/`

برگشت: فایل‌ها را از همان پوشه بکاپ برگردانید + `docs/tejarat/ROLLBACK.md`

---

## Live

تا تأیید شما **`dry_run` در ادمین→تجارت خاموش نشود.**

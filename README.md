# pnv-panel

پنل کاربری و مدیریت اشتراک (PHP) — خرید، تمدید، پشتیبانی، اتصال 3x-ui.

**نسخه فعلی:** `v02.01.01`

## نصب / آپدیت یک‌خطی (Nginx)

نصب تازه یا **overwrite** روی نصب قبلی — کد عوض می‌شود، `db/` و `payments.csv` حفظ می‌شوند:

```bash
bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh)
```

با SSL (اختیاری):

```bash
DOMAIN=panel.example.com EMAIL=you@example.com bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh)
```

حالت سوالی (Finglish):

```bash
bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh) --ask
```

## پیش‌نیاز

- Ubuntu 20.04+ (یا Debian مشابه)
- دسترسی root
- Nginx + PHP-FPM 8.x (اسکریپت نصب خودکار نصب می‌کند)

## بعد از نصب

1. `db/xui_servers.json` — تنظیم سرورهای 3x-ui
2. SSL و دامنه (در حالت interactive از شما پرسیده می‌شود)
3. ورود ادمین: `/bigjay_controller/`
4. بک‌آپ/ایمپورت: Admin → **بک‌آپ** (با انتخاب بخش)

## Release در GitHub

Tag: `v02.01.01` — جزئیات در [RELEASE_v02.01.01.md](RELEASE_v02.01.01.md)

## به‌روزرسانی جزئی (بدون Release)

```bash
BR=cursor/fix-user-panel-regressions-b94c bash scripts/deploy-user-panel.sh
```

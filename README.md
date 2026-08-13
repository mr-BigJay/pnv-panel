# pnv-panel

پنل کاربری و مدیریت اشتراک (PHP) — خرید، تمدید، پشتیبانی، اتصال 3x-ui.

## نصب یک‌خطی (Ubuntu + Apache)

```bash
curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh | sudo bash
```

نسخه مشخص (Release):

```bash
curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh | sudo bash -s -- --version v01.1.0
```

مسیر دلخواه:

```bash
curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh | sudo ROOT=/var/www/html bash
```

## پیش‌نیاز

- Ubuntu 20.04+ (یا Debian مشابه)
- دسترسی root
- Apache + PHP 8.x (اسکریپت نصب خودکار نصب می‌کند)

## بعد از نصب

1. `db/xui_servers.json` — تنظیم سرورهای 3x-ui
2. SSL و دامنه در Apache
3. ورود ادمین: `/bigjay_controller/`

## Release در GitHub

1. تغییرات stable را به `main` merge کنید
2. Tag بزنید: `git tag -a v01.1.0 -m "Release v01.1.0"`
3. Push tag: `git push origin v01.1.0`
4. GitHub → Releases → **Draft a new release** → tag `v01.1.0`
5. توضیحات changelog + فایل `install.sh` را attach کنید (اختیاری)

کاربران نصب:

```bash
curl -fsSL https://github.com/mr-BigJay/pnv-panel/releases/download/v01.1.0/install.sh | sudo bash
```

## به‌روزرسانی جزئی (بدون Release)

```bash
BR=cursor/fix-user-panel-regressions-b94c bash scripts/deploy-user-panel.sh
```

# Release v02.01.01

## نصب یک‌خطی (Nginx — پیشنهادی)

```bash
bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/v02.01.01/scripts/install.sh)
```

## نصب بدون سوال (Finglish prompts off)

```bash
curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/v02.01.01/scripts/install.sh | sudo bash -s -- \
  --yes --web nginx --domain panel.example.com --email you@example.com --root /var/www/pnv-panel
```

## Apache

```bash
curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/v02.01.01/scripts/install.sh | sudo bash -s -- --web apache
```

## Import هنگام نصب

```bash
sudo bash -s -- --yes --import /root/pnv-backup.zip --root /var/www/pnv-panel < install.sh
```

---

## تغییرات اصلی

### پنل کاربر
- کندی «اشتراک من» — QR lazy، cache usage، sort جدید→قدیم
- میله زمان: «X روز از Y روز باقیمانده»
- UI باکس لینک + دکمه کپی
- Dashboard 500 fix (`dashboard_lib.php`)

### ادمین
- **بک‌آپ / ایمپورت** با انتخاب بخش (checkbox): کاربران، خریدها، پشتیبانی، ربات‌ها، 3x-ui، SMS، …
- پنل SMS
- `diag-dashboard.php` برای عیب‌یابی

### DevOps
- `scripts/install.sh` — Nginx + PHP-FPM، SSL (Certbot)، سوالات Finglish
- `scripts/nginx-pnv-panel.conf.example`

---

## بعد از نصب

1. `db/xui_servers.json` — سرورهای 3x-ui
2. `db/bale.json` / `db/telegram.json` — ربات‌ها
3. ورود ادمین: `/bigjay_controller/`
4. بک‌آپ: Admin → **بک‌آپ**

## Upgrade از نسخه قبل

```bash
bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/v02.01.01/scripts/install.sh) --yes --root /var/www/html --web nginx
```

فایل‌های داده (`db/`, `invoices/`) در نصب preserve می‌شوند.

# v01.1.0

اولین Release پایدار پنل PNV — شامل پنل کاربری، پنل ادمین، اتصال 3x-ui، تلگرام/بله، و UI مدرن.

## نصب / به‌روزرسانی

```bash
curl -fsSL https://github.com/mr-BigJay/pnv-panel/releases/download/v01.1.0/install.sh | sudo bash
```

یا از tag:

```bash
curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh | sudo bash -s -- --version v01.1.0
```

## پنل کاربری

- داشبورد گروه‌بندی‌شده با منوی تلگرام و آواتار پروفایل
- **اشتراک من**: UI جدید، نمایش نام Email پنل، به‌روزرسانی حجم/زمان هر ۱ دقیقه
- **خرید / تمدید**: UI چندمرحله‌ای جدید (Concept A)
- **پشتیبانی**: چت UI مدرن با ارسال فایل
- **دعوت دوستان**: صفحه Concept A با progress bar و سطوح پاداش
- پرداخت آنی (Instant Pay) و تطبیق خودکار تراکنش

## پنل ادمین

- داشبورد، کاربران، پرداخت‌ها، تمدیدها، پلن‌ها
- پشتیبانی چت، کمپین‌ها، کدهای تخفیف
- تنظیمات 3x-ui، تلگرام، بله
- تایید/رد خرید و تمدید از تلگرام با provision خودکار 3x-ui

## زیرساخت

- `scripts/install.sh` — نصب یک‌خطی Ubuntu + Apache
- `scripts/deploy-*.sh` — دیپلوی جزئی فایل‌ها
- `scripts/diag-sub-usage.php` — عیب‌یابی مصرف اشتراک از پنل
- تاریخ شمسی (تهران)، فونت Lalezar آفلاین

## بعد از نصب

1. `db/xui_servers.json` — سرورهای 3x-ui
2. `db/telegram.json` / `db/bale.json` — ربات‌ها (در صورت نیاز)
3. SSL و vhost Apache

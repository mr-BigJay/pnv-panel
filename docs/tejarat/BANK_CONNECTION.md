# اتصال بانک تجارت (PNV)

## وضعیت واقعی
این ماژول رابط وب‌بانک تجارت را **با ورود انسانی و فقط‌خواندن** پشتیبانی می‌کند، اما بدون نشست معتبر بانک و selectorهای تأییدشده، تراکنش واقعی دریافت نمی‌شود. این کد هیچ رمز، OTP، توکن نشست یا Cookie بانکی را برای پنل ارسال نمی‌کند. **فقط Merge کردن یا ذخیره تنظیمات به معنی اتصال به بانک نیست.**

پشتیبانی از API رسمی بانکداری باز (با قرارداد و مجوز ارائه‌دهنده) روش ترجیحی و پایدارتر است. خودکارکردن ورود با دورزدن OTP/CAPTCHA مجاز یا پیاده‌سازی نشده است.

## معماری
- منوی مدیر: «بانک‌ها» در `/bigjay_controller/index.php?page=banks` (یا مسیر نصب شما)
- دریافت‌کننده: `/bank-transaction-webhook.php` — POST و امضای HMAC-SHA256.
- تنظیمات و دفتر تراکنش‌ها: `/var/lib/pnv-panel/`، خارج از ریشه وب؛ دسترسی `0600`.
- ماژول تطبیق: مبلغ **دقیق ریالی** (شامل کد مبلغ اختصاصی پنل)، کارت تجارت مقصد، زمان ایجاد سفارش، تاریخ تراکنش، مرجع یکتا، و جلوگیری از مصرف مجدد. در وجود دو سفارشِ هم‌مبلغ یا وضعیت نامشخص، تأیید خودکار متوقف می‌شود.
- Worker مجزا: `services/tejarat-browser-worker`. نشست ذخیره‌شده فقط در حساب کاربری محدود خود Worker؛ بدون ارسال Cookie به PNV. هیچ عملیات انتقال وجه انجام نمی‌دهد.

## تنظیمات حساب تجارت در داشبورد

در منوی **بانک‌ها ← تجارت** دو بخش مستقل وجود دارد:
- اطلاعات حساب: نام کاربری و رمز ورود (اختیاری)، ذخیره جداگانه و رمزگذاری‌شده با Sodium secretbox؛ این اطلاعات هرگز در HTML پس از ذخیره، JSON سفارش‌ها، Webhook یا GitHub نمایش داده نمی‌شوند.
- تنظیمات تطبیق: شماره کارت مقصد، کلید امضای اتصال و حالت آزمایشی/زنده.

کلید رمزگذاری حساب پس از اجرای اسکریپت نصب فقط **روی سرور** در `/var/lib/pnv-panel/bank-credentials.key` ساخته می‌شود. فایل اطلاعات رمزگذاری‌شده در `/var/lib/pnv-panel/tejarat-credentials.enc.json` ذخیره می‌شود؛ هر دو خارج از مسیر عمومی وب با مجوزهای محدود. در صورت حذف کلید، بازیابی رمز ذخیره‌شده ممکن نیست. از کلید و فایل رمزگذاری‌شده در یک محل امن بکاپ بگیرید و در گیت منتشر نکنید.

**مهم:** این فیلدهای اطلاعات ورود فعلاً فقط مخزن امن تنظیمات‌اند؛ Worker موجود ورود اولیه و OTP بانک را توسط انسان در مرورگر رسمی انجام می‌دهد. ذخیره نام کاربری و رمز عبور به معنی ورود خودکار یا اتصال فعال نیست. در صورت عدم نیاز، این فیلدها را خالی بگذارید.

## تنظیمات اولیه Production
۱. فقط بعد از بازبینی و بکاپ، نصب گزینشی با:
```bash
sudo python3 scripts/deploy-bank-connection-safe.py --source /path/to/checked-out-branch
sudo python3 scripts/deploy-bank-connection-safe.py --source /path/to/checked-out-branch --apply
```
اگر ساختار پنل اختصاصی تغییر کرده، برنامه **متوقف** می‌شود. هرگز کل `admin/index.php` را از upstream جایگزین نکنید.

۲. در «بانک‌ها»، شماره کارت تجارت را وارد و «تولید کلید جدید» را بزنید. کلید را تنها در پیکربندی Worker امن ذخیره کنید. **Dry Run را روشن نگه دارید.**

۳. اتصال Worker: روی VPS با کاربر غیر-root و مرورگر قابل‌مشاهده وارد وب‌بانک شوید:
```bash
cd /path/to/pnv-panel/services/tejarat-browser-worker
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
python -m playwright install chromium
# با DISPLAY امن و VNC/دسترسی گرافیکی محافظت‌شده:
TEJARAT_HEADLESS=false python -m worker.manual_login
```
ورود اولیه، OTP و CAPTCHA را خودتان داخل صفحه رسمی `https://hb.tejaratbank.ir` انجام می‌دهید. Worker باید فقط به ردیف‌های واقعی گردش حساب دسترسی داشته باشد. نشست زیر مسیر `PNV_WORKER_STORAGE` با دسترسی انحصاری Worker ذخیره می‌شود.

۴. تنظیم‌های لازم Worker در محیط خصوصی:
```bash
TEJARAT_COLLECT_MODE=live
TEJARAT_HEADLESS=true
TEJARAT_DESTINATION_CARD=<16-DIGIT-TEJARAT-CARD>
TEJARAT_STATEMENT_URL=https://hb.tejaratbank.ir/account/log
TEJARAT_STMT_SELECTORS_JSON='{"row":"...","credit_cell":"...","reference_cell":"...","amount_cell":"...","date_cell":"...","time_cell":"..."}'
TEJARAT_LOGIN_PROBE_SELECTOR='...'
PNV_BANK_FEED_URL=https://YOUR-PANEL-DOMAIN/bank-transaction-webhook.php
PNV_BANK_FEED_SECRET=<GENERATED-SECRET>
TEJARAT_RUN_ONCE=1
```
**این selectorها صرفاً جایگاه تنظیم هستند؛ از داده‌های فعلی نمی‌توان selector معتبر صفحه واقعی را اثبات کرد.** لاگ HAR قبلی برای API تراکنش `/api/transaction/account` پاسخ HTTP 406 داده است؛ از آن لاگ برای برداشت Cookie یا اجرای درخواست خصوصی استفاده نکنید.

۵. تست یک دور (فقط‌خواندن؛ ابتدا Dry Run پنل):
```bash
cd /path/to/pnv-panel/services/tejarat-browser-worker
. .venv/bin/activate
python -m worker.main
```
وقتی یک واریز واقعی در حالت Dry Run دقیقاً با سفارش پنل تطبیق داده شد و خروجی نامعتبر/تکراری رد شد، فقط آن وقت می‌توان با تأیید مسئول بانک، حالت live را از UI فعال کرد. **بدون بانک و ورود واقعی نمی‌توان فعال بودن تطبیق Production را تأیید کرد.**

## فرمت ورودی برای رابط رسمی یا Worker مورداعتماد
بدنه JSON:
```json
{"transaction_id":"TJ-12345678","amount_rial":1499280,"destination_card":"5859831122334455","occurred_at":1792512000,"direction":"credit"}
```
هدرهای `X-PNV-Timestamp` (زمان فعلی UTC به Unix seconds) و `X-PNV-Signature` (حروف کوچک hex) الزامی‌اند. امضای HMAC-SHA256 روی `timestamp + "\\n" + raw_JSON_bytes` با کلید مشترک حساب می‌شود. `direction` باید دقیقاً `credit` باشد. سفارش باید یکتا و در بازه زمانی معتبر باشد. هیچ پرداختی با پیام غیرامضاشده، فاکتور تصویری یا شماره پیگیری بدون مدرک بانکی تأیید نمی‌شود.

## تست‌ها
```bash
php tests/bank_connection_test.php
cd services/tejarat-browser-worker
PYTHONPATH=. python -m unittest discover -s tests -p 'test_*.py'
```
تست‌ها Mock/isolated هستند و به بانک واقعی یا موجودی مشتری دست نمی‌زنند. در Production تا اثبات دسترسی بانک و یک واریز آزمایشی معتبر، وضعیت «متصل» نباید نمایش داده شود.

## ملاحظات عملیاتی
- Worker باید تحت حساب غیر-root محدود اجرا شود. فایل نشست و کلید مشترک را در مخزن عمومی نگذارید.
- PHP تنها پذیرنده تراکنش است؛ نه بانک‌رو درخواست ارسال می‌کند، نه OTP می‌خواند.
- هر واریز یکبار Claim می‌شود؛ پس از شکست نامشخص، وضعیت `manual_review` دارد و خودکار دوباره تأیید نمی‌شود.
- برای اتصال دائمی بهتر است از بانکداری باز یا صورت‌حساب رسمی استفاده شود؛ نشست وب‌بانک می‌تواند منقضی شود و باید ورود دستی تکرار شود.

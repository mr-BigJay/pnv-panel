# تست ایمن Worker/Cron رزرو تمدید روی 3x-ui واقعی

این سند برای **قبل از فعال‌کردن cron روی production** است. کد production (`/var/www/html`) را عوض نکنید؛ اسکریپت‌ها را از checkout شاخه PR (مثلاً `cursor/fix-manual-renew-xui-cbea`) اجرا کنید.

## Worker چه می‌کند؟

| لایه | فایل / مکانیزم | نقش |
|------|----------------|-----|
| Cron | `scripts/renew_reserve_cron.php` | قفل سراسری `renew_reserve_cron.lock`؛ حداکثر یک worker هم‌زمان |
| صف | `renewReserveProcessQueue()` | از `renewReserveQueueWorkItems()` حداکثر `PNV_RENEW_RESERVE_BATCH` (پیش‌فرض ۲۰) رزرو |
| هر رزرو | `renewReserveActivateOne()` | قفل per-id؛ وضعیت `reserved` → `activating` → verify روی API → `activated` |
| داده | `db/renew_reservations.json` | FIFO per `queue_key` (یک اشتراک = یک سر صف) |

### شرط «سررسید» (Due)

Worker فقط وقتی `xuiRenewAnalyzeRuntimeState()` مقدار `should_activate_now === true` بدهد اعمال می‌کند:

- اتمام **زمان** پلن فعلی، یا
- اتمام **حجم** (باقی‌مانده ≈ ۰)

در غیر این صورت `skipped` با `reason: not_due` و فقط `last_check_note` به‌روز می‌شود — **بدون تماس apply به XUI**.

### جلوگیری از تکرار

1. **Cron lock** — اجرای دوم هم‌زمان: `another worker is running` و خروج ۰.
2. **Lock per reservation** — دو process روی یک `id`: یکی `skipped` با `reason: locked`.
3. **وضعیت `activated`** — فراخوانی مجدد: `already: true` و **بدون** `xuiApplyRenewPlanAbsolute`.
4. **Verify بعد از apply** — اگر API موفق باشد ولی `expiryTime` / حجم با پلن رزرو یکی نباشد → `ambiguous` (retry کور اعمال دوباره نمی‌کند).
5. **`activating` + grace (۱۸۰ ثانیه)** — بعد از grace، `renewReserveRecoverStaleActivating`: اگر روی XUI already مطابق پلن باشد → `activated`؛ وگرنه بازگشت به `reserved` یا `ambiguous`.

### تفاوت با تأیید فوری (بدون صف)

برای رزروهای **صف‌شده**، worker همیشه پلن رزرو را با **`xuiApplyRenewPlanAbsolute`** (حجم = `plan_gb`، انقضا از `activation_anchor_ms`) اعمال می‌کند. این با «فعال‌سازی پلن جدید وقتی پلن قبلی تمام شد» هم‌خوان است، نه با `bulkAdjust` مسیر `immediate_unlimited` در تأیید دستی.

---

## پیش‌نیاز تست

1. **کلاینت اختصاصی تست** روی همان پنل 3x-ui (subId/email جدا؛ نه مشتری واقعی).
2. **Checkout جدا** از PR (نه deploy روی `/var/www/html` تا زمانی که تست تمام نشده).
3. **فایل‌های داده جدا** برای تست (تا `renew_reservations.json` production دست نخورد):

```bash
export PNV_RENEW_RESERVE_PATH=/var/lib/pnv-renew-test/renew_reservations.json
export PNV_LOCKS_DIR=/var/lib/pnv-renew-test/locks
mkdir -p /var/lib/pnv-renew-test/locks
# در صورت نیاز مسیر CSV/config staging:
# export PNV_PAYMENTS_CSV_PATH=...
# export PNV_XUI_CONFIG_PATH=...
```

4. **config XUI** همان سروری که کلاینت تست روی آن است (معمولاً کپی read-only از `db/xui_servers.json` کافی است).

---

## سناریو A — حجم تمام می‌شود (پلن زمان‌دار)

1. کلاینت تست با پلن کوچک (مثلاً ۱–۲ گیگ، ۷ روز) بسازید.
2. از پنل staging (یا دستی در JSON تست) یک رزرو با `status: reserved` برای همان sub/link ثبت کنید — یا یک تمدید را در staging تأیید کنید تا مسیر رزرو واقعی اجرا شود.
3. **Baseline (فقط خواندن):**

```bash
php scripts/renew_reserve_inspect.php --id=RESERVE_ID
```

فیلدهای مهم: `runtime.should_activate_now` (باید `false`)، `worker_would: skip_not_due`، `xui.client.total/remaining`.

4. ترافیک را تا نزدیک صفر مصرف کنید (یا در محیط lab حجم را در پنل به used=near-total ببرید — **فقط روی کلاینت تست**).
5. دوباره inspect — انتظار: `should_activate_now: true`، `worker_would: apply_absolute_plan_and_verify`.
6. **یک بار worker:**

```bash
php scripts/renew_reserve_cron.php | tee /tmp/renew-cron-run1.log
```

انتظار JSON: `"summary":{"activated":1,...}` (یا `failed` با پیام واضح).

7. **Verify روی پنل 3x-ui:** `totalGB` ≈ `plan_gb`، `expiryTime` ≈ anchor + `plan_days` (با تلورانس چند دقیقه).
8. inspect دوباره: `status: activated`، `verify_absolute_now: true`.
9. **Idempotency:**

```bash
php scripts/renew_reserve_cron.php | tee /tmp/renew-cron-run2.log
php scripts/renew_reserve_inspect.php --id=RESERVE_ID
```

انتظار: run2 بدون اعمال دوباره (`activated` در summary فقط اگر صف دیگری باشد؛ برای همین id وضعیت `activated` و حجم/انقضا ثابت).

10. **Concurrent cron (اختیاری):**

```bash
php scripts/renew_reserve_cron.php & php scripts/renew_reserve_cron.php & wait
```

یکی باید در stderr پیام `another worker is running` بدهد.

---

## سناریو B — اتمام زمان

مشابه A، ولی به‌جای مصرف حجم، تا **بعد از expiry** پلن فعلی صبر کنید (یا کلاینت تست با ۱ روز validity). قبل از due: `not_due`؛ بعد از due: یک run cron + verify انقضای جدید روی XUI.

---

## سناریو C — نامحدود زمانی + حجم باقی → رزرو بدون touch

1. کلاینت نامحدود زمانی با حجم باقی.
2. تمدید را رزرو کنید (staging).
3. inspect: `decision: reserve`، `worker_would: skip_not_due`.
4. cron را چند بار اجرا کنید — **نباید** حجم/انقضا روی XUI عوض شود (`summary.skipped` زیاد، `activated: 0`).

---

## چک‌لیست قبل از cron production

- [ ] فقط روی کلاینت تست end-to-end انجام شده
- [ ] `renew_reservations.json` production در تست دستکاری نشده (`PNV_RENEW_RESERVE_PATH` جدا بوده)
- [ ] بعد از due، یک run → verify پنل → run دوم → بدون تغییر دوباره
- [ ] لاگ cron: `>> /var/log/pnv-renew-reserve.log`
- [ ] cron هر دقیقه: `* * * * * php /path/to/scripts/renew_reserve_cron.php ...`

## Rollback

طبق `docs/RENEW_RESERVE_CRON.md`: حذف خط cron؛ رزروها در JSON می‌مانند برای بررسی دستی.

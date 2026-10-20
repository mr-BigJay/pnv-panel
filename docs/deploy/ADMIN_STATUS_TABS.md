# Admin status tabs (خرید / تمدید) — نصب امن روی Production

Production در `/var/www/html` سفارشی است و **مستقیم از Git checkout نمی‌شود**. این PR فقط نمایش/فیلتر/طبقه‌بندی را اصلاح می‌کند.

## فایل‌های این PR

| فایل | نقش |
|------|-----|
| `instant_pay_admin_tabs_lib.php` | **جدید** — `instantPayAdminRowListBucket()`, آرشیو `cancelled`, تفکیک cancelled/rejected |
| `instant_pay_lib.php` | بارگذاری اختیاری tabs lib؛ متای cancelled در `instantPayAdminRowStatusMeta` |
| `admin/payments.php` | پنج تب، شمارنده، فیلتر، آرشیو read-only |
| `admin/renews.php` | همان منطق برای «تمدید» |

## پیش‌نیاز

- بکاپ: `tar czf /root/pnv-admin-tabs-$(date +%Y%m%d).tgz -C /var/www/html admin/payments.php admin/renews.php instant_pay_lib.php`
- فایل جدید `instant_pay_admin_tabs_lib.php` را **اضافه** کنید (جایگزین کل فایل‌های قدیمی لازم نیست).

## روش ۱ — merge سه‌طرفه (توصیه‌شده)

روی سرور، برای هر فایل **موجود**:

```bash
export REPO=/path/to/pnv-panel-checkout   # clone موقت از branch PR
export WWW=/var/www/html
export FILE=admin/payments.php            # تکرار برای renews.php و instant_pay_lib.php

cp "$WWW/$FILE" "/tmp/$(basename $FILE).prod"
git -C "$REPO" show HEAD:"$FILE" > "/tmp/$(basename $FILE).pr"

git -C "$REPO" show origin/main:"$FILE" > "/tmp/$(basename $FILE).base"
merge-file -p "/tmp/$(basename $FILE).prod" \
  "/tmp/$(basename $FILE).base" \
  "/tmp/$(basename $FILE).pr" > "/tmp/$(basename $FILE).merged"

# diff را ببینید؛ در صورت تأیید:
# cp "/tmp/$(basename $FILE).merged" "$WWW/$FILE"
```

فایل جدید:

```bash
cp "$REPO/instant_pay_admin_tabs_lib.php" "$WWW/instant_pay_admin_tabs_lib.php"
```

`merge-file` سه نسخه را ترکیب می‌کند: **نسخه PR**، **Production فعلی**، و خروجی **ادغام‌شده** — تغییرات محلی Production حفظ می‌شوند مگر تعارض صریح.

## روش ۲ — patch از Git (با `--merge`)

```bash
git -C "$REPO" diff origin/main...HEAD -- \
  instant_pay_admin_tabs_lib.php \
  instant_pay_lib.php \
  admin/payments.php \
  admin/renews.php \
  > /root/admin-status-tabs.patch

cd /var/www/html
patch -p1 --merge < /root/admin-status-tabs.patch
# فایل‌های *.rej را دستی حل کنید
```

## اسکript کمکی

`scripts/deploy/admin_status_tabs_merge.sh` همان merge سه‌طرفه را برای چهار مسیر بالا اجرا می‌کند (خروجی در `/tmp/pnv-admin-tabs-merge/`).

## تأیید بعد از نصب

1. `php tests/instant_pay_admin_status_tabs_test.php` (روی checkout PR)
2. پنل: تب‌های پنج‌گانه در **لیست خرید** و **لیست تمدید**؛ شمارنده‌ها؛ تب **کنسل شده** فقط مشاهده
3. هیچ تأیید/تمدید خودکاری اجرا نشود — فقط UI

## خارج از scope

منطق پرداخت، Tejarat، 3x-ui، رزرو تمدید، حجم اشتراک — **دست نخورده**.

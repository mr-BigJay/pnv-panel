# پراکسی اختصاصی Xray برای بات تلگرام

سامانه، فقط درخواست‌های Telegram Bot API را از طریق SOCKS محلی تنظیم‌شده در
«مدیریت → تنظیمات بات تلگرام» ارسال می‌کند. درخواست‌های سایت، پرداخت، پنل
ادمین و کاربران از این پراکسی استفاده نمی‌کنند.

## نکته مهم

لینک `vless://` یک لینک اشتراک/کلاینت است و PHP نمی‌تواند آن را مستقیم به
عنوان پراکسی استفاده کند. باید روی **همان سرور پنل** یک Xray Client اجرا شود
که:

1. به VLESS خارجی وصل می‌شود؛
2. فقط روی `127.0.0.1` یک SOCKS5 باز می‌کند؛
3. آدرس آن، مثلاً `socks5h://127.0.0.1:10808`، در تنظیمات بات ثبت می‌شود.

هرگز SOCKS محلی را روی `0.0.0.0` باز نکنید؛ در غیر این صورت سرور شما یک پراکسی
عمومی می‌شود.

## نمونه ساختار Xray

فایل پیکربندی Xray باید یک inbound محلی و یک outbound VLESS داشته باشد. بخش
VLESS را براساس لینک و نسخه Xray خود از پنل/کلاینت Xray استخراج کنید:

```json
{
  "inbounds": [
    {
      "listen": "127.0.0.1",
      "port": 10808,
      "protocol": "socks",
      "settings": { "auth": "noauth", "udp": false }
    }
  ],
  "outbounds": [
    {
      "tag": "telegram-vless",
      "protocol": "vless",
      "settings": {
        "vnext": [
          {
            "address": "YOUR_PROXY_HOST",
            "port": 443,
            "users": [
              { "id": "YOUR_UUID", "encryption": "none" }
            ]
          }
        ]
      },
      "streamSettings": {
        "network": "xhttp",
        "security": "tls",
        "tlsSettings": {
          "serverName": "YOUR_SNI",
          "alpn": ["h2", "http/1.1", "h3"]
        },
        "xhttpSettings": {
          "path": "/video",
          "mode": "auto"
        }
      }
    }
  ]
}
```

برای لینک نمونه ارسالی، `address` برابر `srv.softwary.ir`، `port` برابر `443`
و `serverName` برابر `srv.avsoft.ir` است. مقادیر `extra` و fingerprint در
نسخه‌های مختلف Xray تفاوت دارند؛ بنابراین پیش از راه‌اندازی، خروجی JSON همان
نسخه Xray/کلاینت خود را استفاده کنید.

## بررسی اتصال

پس از بالا آمدن Xray:

```bash
curl --proxy socks5h://127.0.0.1:10808 https://api.telegram.org/
```

سپس در پنل ادمین، آدرس `socks5h://127.0.0.1:10808` را در فیلد پراکسی محلی ثبت
و دکمه «ارسال پیام آزمایشی و ثبت منوی بات» را بزنید.

برای افزونگی می‌توان چند Xray محلی با پورت‌های متفاوت (مثلاً 10808 و 10809)
اجرا و هر آدرس را در یک خط جداگانه در پنل ثبت کرد.

## فعال کردن پاسخ سریع دکمه‌های بات

اعلان‌های پیام جدید هنگام ثبت پیام کاربر همان لحظه ارسال می‌شوند. اما برای
عملکرد سریع دکمه‌های «مشاهده گفتگو / پاسخ / بازگشت»، به‌جای cron دقیقه‌ای از
سرویس دائمی استفاده کنید:

```bash
cp /var/www/html/telegram-proxy/telegram-bot.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now telegram-bot
systemctl status telegram-bot --no-pager
```

اگر قبلاً cron داشتید، آن را حذف یا غیرفعال کنید تا دو پردازش هم‌زمان ساخته نشود:

```bash
crontab -e
# خط telegram_poll.php را پاک کنید
```

این سرویس long-polling است و درخواست‌های Telegram API را فقط از SOCKS محلی
عبور می‌دهد. webhook استفاده نشده است.

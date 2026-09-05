# پرداخت آنی تمام‌اتوماتیک (بدون فوروارد دستی)

Listener پیام @postbank_bot را می‌خواند (push + **poll هر ۲۰ ثانیه**) و:
1. **اول** همان پیام را به @Jay24x7Pusbank_bot فوروارد می‌کند (مثل فوروارد دستی)
2. `postbank-ingest.php` را صدا می‌زند (تأیید خودکار)
3. اگر ingest مچ نکرد، webhook پنل هم امتحان می‌شود

`POSTBANK_POLL_SEC=20` در env (اختیاری)
3. اگر ingest خطای HTTP داد، `bale-webhook.php` هم امتحان می‌شود

**بدون سفارش باز** → ingest پاسخ `ignored` می‌دهد و فوروارد انجام نمی‌شود (بدون اسپم).

## env لازم (`db/postbank-listener.env`)

```
POSTBANK_INGEST_SECRET=...
POSTBANK_ADMIN_CHAT_ID=196365289
POSTBANK_WEBHOOK_URL=https://panel.ticketin.ir/bale-webhook.php
POSTBANK_FORWARD_BOT=Jay24x7Pusbank_bot
```

بعد: `systemctl restart postbank-listener`

## راه‌اندازی (یک‌بار)
روی سرور — **یک خط** (venv + systemd + deploy):

```bash
bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/telegram-user-bot-058b/scripts/setup-postbank-listener.sh)
```

یا دستی:

```bash
cd /var/www/html
apt install -y python3 python3-venv python3-pip
python3 -m venv tools/postbank-venv
tools/postbank-venv/bin/pip install aiobale-py aiohttp colorama
cp tools/postbank-listener.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now postbank-listener
```

## login بله (تعاملی — یک‌بار)
```bash
cd /var/www/html
sudo -u www-data tools/postbank-venv/bin/python tools/postbank_bale_listener.py \
  --login --session /var/www/html/db/bale_user_session.bale
systemctl restart postbank-listener
```

## تست
1. یک مهلت پرداخت در سایت بساز
2. همان مبلغ را کارت‌به‌کارت کن
3. به‌محض آمدن پیام پست‌بانک در بله، بدون فوروارد باید تأیید شود
4. لاگ: `journalctl -u postbank-listener -f`
5. یا `db/bale_webhook.log` خط `INGEST` / `INGEST_PAID`

## نکات
- سشن و secret را commit نکن
- اگر سشن باطل شد، دوباره `--login` بزن
- این روش unofficial است و وابسته به پایداری کلاینت داخلی بله است

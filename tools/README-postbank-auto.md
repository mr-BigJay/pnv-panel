# پرداخت آنی تمام‌اتوماتیک (بدون فوروارد)

## چرا ربات کافی نیست؟
Bot API بله فقط پیام‌هایی را می‌بیند که به خودِ بازو برسد.
چت خصوصی `@postbank_bot` با صاحب کارت برای بازو قابل‌خواندن نیست.

## راه‌حل
یک Listener با سشن **اکانت صاحب کارت** پیام واریز را می‌خواند و به
`postbank-ingest.php` می‌فرستد. از آن به بعد مچ/صدور مثل قبل خودکار است.

## راه‌اندازی (یک‌بار)
روی سرور:

```bash
cd /var/www/html
curl -fsSL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/HEAD/postbank-ingest.php" -o postbank-ingest.php
# + به‌روز کردن bale_lib.php / admin/bale.php / tools/*

pip3 install -r tools/requirements-postbank.txt

# لاگین تعاملی با همان شماره‌ای که اعلان پست‌بانک را می‌گیرد
python3 tools/postbank_bale_listener.py --login \
  --session /var/www/html/db/bale_user_session.bale

# secret را از صفحه ادمین → بله کپی کن
cat >/var/www/html/db/postbank-listener.env <<EOF
POSTBANK_INGEST_SECRET=PASTE_SECRET_HERE
EOF
chmod 600 /var/www/html/db/postbank-listener.env
chown www-data:www-data /var/www/html/db/bale_user_session.bale /var/www/html/db/postbank-listener.env

cp tools/postbank-listener.service /etc/systemd/system/postbank-listener.service
systemctl daemon-reload
systemctl enable --now postbank-listener
systemctl status postbank-listener --no-pager
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

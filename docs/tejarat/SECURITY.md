# امنیت — تجارت Worker + Ingest

- `worker_ingest_secret` فقط روی سرور در `db/tejarat_verify.json`؛ **هرگز** در Git commit نکنید.
- Worker فقط **POST** به `tejarat-worker-ingest.php` با هدر `X-PNV-Tejarat-Worker-Signature` (HMAC-SHA256 روی body).
- رمز بانک، OTP، کوکی و HTML صفحه login در لاگ Worker چاپ **نمی‌شود** (فقط نام کلاس خطا).
- نشست Playwright در `PNV_WORKER_STORAGE` با دسترسی `700` / کاربر `pnv`.
- PHP در heartbeat منقضی یا `login_required` **fetch نمی‌کند** → بدون تأیید از داده قدیمی.
- تأیید پرداخت فقط از `instantPayTryDepositMatch` با `transaction_id` + کارت مقصد + dedup موجود PNV.
- ingest بدون secret → HTTP 503؛ امضای wrong → 401.

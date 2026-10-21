<?php
/** Embedded inside authenticated /bigjay_controller/index.php?page=bank. */
if (!function_exists('pnvAdminIsLoggedIn') || !pnvAdminIsLoggedIn()) {
    http_response_code(403);
    exit;
}
require_once dirname(__DIR__) . '/tejarat_bank_lib.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['tejarat_bank_csrf'])) {
    $_SESSION['tejarat_bank_csrf'] = bin2hex(random_bytes(24));
}
$bankMessage = '';
$bankError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['tejarat_bank_save'])) {
    $csrf = (string)($_POST['csrf'] ?? '');
    if ($csrf === '' || !hash_equals((string)$_SESSION['tejarat_bank_csrf'], $csrf)) {
        $bankError = 'نشست فرم نامعتبر است؛ صفحه را دوباره باز کنید.';
    } else {
        $saved = tejaratBankSaveSettings([
            'enabled' => isset($_POST['enabled']),
            'dry_run' => isset($_POST['dry_run']),
            'destination_cards' => (string)($_POST['destination_cards'] ?? ''),
            'webhook_secret' => (string)($_POST['webhook_secret'] ?? ''),
        ]);
        if (!empty($saved['ok'])) {
            $bankMessage = 'تنظیمات اتصال بانک ذخیره شد.';
        } else {
            $bankError = $saved['error'] ?? 'ثبت تنظیمات ناموفق بود.';
        }
    }
}
$bankCfg = tejaratBankLoadConfig();
$bankReady = !empty($bankCfg['enabled']) && !empty($bankCfg['last_verified_at']);
$bankEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$webhookUrl = trim((string)getenv('PNV_TEJARAT_BANK_WEBHOOK_URL'));
if ($webhookUrl === '') $webhookUrl = 'https://panel.ticketin.ir/tejarat-bank-webhook.php';
?>
<style>
.bankPanel{background:#1e293b;padding:22px;border-radius:16px;max-width:860px;margin:0 auto 20px}
.bankPanel h2{margin:0 0 12px}.bankPanel p{line-height:1.9;color:#cbd5e1}
.bankGrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
.bankPanel label{display:block;margin:10px 0;color:#e2e8f0}
.bankPanel textarea,.bankPanel input[type=password],.bankPanel input[type=text]{display:block;width:100%;box-sizing:border-box;background:#0f172a;border:1px solid #475569;border-radius:10px;padding:12px;color:#fff;direction:ltr}
.bankPanel textarea{min-height:85px}.bankPanel code{color:#86efac;overflow-wrap:anywhere;direction:ltr;display:inline-block}
.bankPanel .bankNotice{padding:10px 14px;background:#0f172a;border-radius:10px;color:#e2e8f0}
.bankPanel .bankSuccess{color:#86efac}.bankPanel .bankError{color:#fca5a5}
.bankPanel .bankAction{background:#22c55e;border:0;border-radius:10px;padding:12px 18px;margin-top:16px;color:#052e16;cursor:pointer}
</style>
<section class="bankPanel" dir="rtl">
<h2>اتصال بانک تجارت</h2>
<p>تطبیق خودکار مبلغ یکتای هر سفارش با واریزی واقعی به کارت تجارت. این بخش فقط از گزارش تراکنشِ <strong>تأیید‌شده و امضاشده توسط سرویس معتبر بانکی</strong> استفاده می‌کند؛ ورود رمز اینترنت‌بانک، OTP و کد پیامک لازم نیست.</p>
<p class="bankNotice">وضعیت:
<?php if (!$bankCfg['enabled']): ?>
<strong>غیرفعال</strong>
<?php elseif (!$bankReady): ?>
<strong>در انتظار دریافت گزارش معتبر از تأمین‌کننده بانکی</strong>
<?php elseif (!empty($bankCfg['dry_run'])): ?>
<strong>اتصال آزمایشی تأیید شده؛ تأیید سفارش خاموش است</strong>
<?php else: ?>
<strong>فعال؛ تأیید پرداخت بر اساس گزارش معتبر</strong>
<?php endif; ?>
</p>
<?php if ($bankMessage !== ''): ?><p class="bankSuccess"><?= $bankEsc($bankMessage) ?></p><?php endif; ?>
<?php if ($bankError !== ''): ?><p class="bankError"><?= $bankEsc($bankError) ?></p><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= $bankEsc($_SESSION['tejarat_bank_csrf']) ?>">
<div class="bankGrid">
<div>
<label for="tejaratCards">کارت‌های مقصد تجارت (هر کارت در یک خط)</label>
<textarea id="tejaratCards" name="destination_cards" dir="ltr" placeholder="6273..."><?= $bankEsc(implode("\n", (array)$bankCfg['destination_cards'])) ?></textarea>
</div>
<div>
<label for="tejaratKey">کلید اشتراکی HMAC-SHA256 از سرویس ارائه‌دهنده گردش حساب</label>
<input type="password" id="tejaratKey" name="webhook_secret" placeholder="<?= empty($bankCfg['webhook_secret']) ? 'حداقل ۳۲ کاراکتر تصادفی' : 'کلید قبلی محفوظ است؛ برای تغییر وارد کنید' ?>">
<p>کلید در صفحه نمایش داده نمی‌شود و خارج از پوشه عمومی وب ذخیره می‌شود.</p>
</div>
</div>
<label><input type="checkbox" name="enabled" value="1" <?= !empty($bankCfg['enabled'])?'checked':'' ?>> اتصال فعال باشد</label>
<label><input type="checkbox" name="dry_run" value="1" <?= !empty($bankCfg['dry_run'])?'checked':'' ?>> حالت آزمایشی (بدون تأیید سفارش)</label>
<button type="submit" name="tejarat_bank_save" value="1" class="bankAction">ذخیره تنظیمات بانک</button>
</form>
<h3>اتصال سرویس گزارش واریز</h3>
<p>این URL را در سرویس بانکیِ مجاز برای ارسال گزارش واریزی تنظیم کنید:</p>
<p><code><?= $bankEsc($webhookUrl) ?></code></p>
<p>درخواست باید HTTPS POST با بدنه JSON و هدرهای <code>X-PNV-Timestamp</code> و <code>X-PNV-Signature</code> باشد. امضا: HMAC-SHA256 روی <code>timestamp.body</code> با کلید بالا، با پیشوند <code>sha256=</code>.</p>
<p>فیلدهای ضروری: <code>transaction_id</code>، <code>amount_rial</code>، <code>destination_card</code>، <code>occurred_at</code>، <code>currency=IRR</code>، <code>direction=credit</code>، <code>status=settled</code>.</p>
<p class="bankNotice">فقط تطبیق دقیق مبلغ، کارت مقصد و بازه زمانی سفارش مجاز است. پرداخت تکراری، گزارش نامعتبر، یا مبلغ مشترک بین چند سفارش به تأیید خودکار نمی‌رسد.</p>
<p>آخرین گزارش معتبر: <?= !empty($bankCfg['last_verified_at']) ? $bankEsc(date('Y-m-d H:i:s', (int)$bankCfg['last_verified_at'])) : 'هنوز دریافت نشده' ?></p>
</section>

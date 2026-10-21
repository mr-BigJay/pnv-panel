<?php
/** Included ONLY by authenticated admin/index.php. */
if (!function_exists('pnvAdminIsLoggedIn') || !pnvAdminIsLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}
require_once dirname(__DIR__) . '/bank_connection_lib.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['pnv_bank_csrf'])) $_SESSION['pnv_bank_csrf'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['pnv_bank_csrf'];
$cfg = pnvBankLoadConfig();
$notice = '';
$error = '';
$showSecret = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'نشست امنیتی نامعتبر است؛ صفحه را تازه کنید.';
    } else {
        $secret = trim((string)($_POST['shared_secret'] ?? ''));
        if (isset($_POST['generate_secret'])) {
            $secret = bin2hex(random_bytes(32));
            $showSecret = $secret;
        } elseif ($secret === '') {
            $secret = (string)($cfg['shared_secret'] ?? '');
        }
        $next = array_merge($cfg, [
            'destination_card' => (string)($_POST['destination_card'] ?? $cfg['destination_card']),
            'enabled' => isset($_POST['enabled']),
            'dry_run' => !isset($_POST['enable_live']),
            'shared_secret' => $secret,
        ]);
        if (!pnvBankSaveConfig($next)) {
            $error = 'ذخیره تنظیمات انجام نشد. ابتدا مسیر خصوصی /var/lib/pnv-panel را برای www-data آماده کنید؛ شماره کارت باید ۱۶ رقم باشد.';
        } else {
            $cfg = pnvBankLoadConfig();
            $notice = 'تنظیمات ذخیره شد. ذخیره اطلاعات به معنی اتصال موفق به بانک نیست.';
        }
    }
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$card = (string)($cfg['destination_card'] ?? '');
$enabled = !empty($cfg['enabled']);
$live = $enabled && empty($cfg['dry_run']);
?>
<div class="box" style="direction:rtl;max-width:880px;line-height:2">
<h2>اتصال بانک <small style="font-size:14px;color:#94a3b8">بانک تجارت</small></h2>
<p>این بخش دریافت تراکنش به‌صورت <strong>فقط‌خواندنی</strong> را از یک رابط بانکیِ مورداعتماد مدیریت می‌کند. نام کاربری، رمز بانک، رمز پویا یا کوکی ورود را در این پنل وارد نکنید.</p>
<?php if ($notice): ?><p style="color:#86efac"><?= $h($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p style="color:#fca5a5"><?= $h($error) ?></p><?php endif; ?>
<p><strong>وضعیت:</strong> <?= !$enabled ? 'غیرفعال' : ($live ? 'دریافت فعال؛ تأیید خودکار مجاز شده' : 'فعال در حالت آزمایشی (بدون تأیید پرداخت)') ?></p>
<form method="post" action="<?= $h(pnvAdminUrl('index.php?page=bank-connection')) ?>" autocomplete="off">
<input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
<label for="bank-card">شماره کارت تجارت مقصد (۱۶ رقم)</label>
<input id="bank-card" name="destination_card" dir="ltr" inputmode="numeric" value="<?= $h($card) ?>" maxlength="24" placeholder="شماره کارت واریز">
<label for="bank-secret">کلید محرمانه اتصال رابط مجاز (حداقل ۳۲ کاراکتر)</label>
<input type="password" dir="ltr" id="bank-secret" name="shared_secret" autocomplete="new-password" placeholder="<?= strlen((string)($cfg['shared_secret'] ?? '')) >= 32 ? 'کلید ذخیره شده؛ برای حفظ آن خالی بگذارید' : 'اول کلید ایجاد کنید' ?>">
<label style="display:block"><input type="checkbox" name="enabled" value="1" <?= $enabled?'checked':'' ?>> پذیرش تراکنش امضاشده فعال باشد</label>
<label style="display:block"><input type="checkbox" name="enable_live" value="1" <?= $live?'checked':'' ?>> تأیید خودکار پس از اطمینان از اصالت منبع تراکنش فعال شود</label>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<button type="submit" name="save">ذخیره تنظیمات</button>
<button type="submit" name="generate_secret" value="1">تولید کلید جدید</button>
</div>
</form>
<?php if ($showSecret): ?>
<p style="color:#fcd34d">کلید جدید فقط همین بار نمایش داده می‌شود. در تنظیمات ارسال‌کننده معتبر ذخیره‌اش کنید؛ آن را در چت یا مخزن کد منتشر نکنید.</p>
<textarea readonly rows="2" dir="ltr" style="width:100%"><?= $h($showSecret) ?></textarea>
<?php endif; ?>
<hr>
<h3>روش اتصال</h3>
<p>آدرس دریافت تراکنش امضاشده:</p>
<code dir="ltr" style="display:block;overflow-wrap:anywhere">/bank-transaction-webhook.php</code>
<p>فرمت: JSON با <code>transaction_id</code>، <code>amount_rial</code>، <code>destination_card</code>، <code>occurred_at</code> و <code>direction=credit</code>. هدرهای <code>X-PNV-Timestamp</code> و <code>X-PNV-Signature</code> الزامی هستند. امضا: HMAC-SHA256 روی «timestamp + newline + بدنه خام JSON».</p>
<p><strong>توجه:</strong> هیچ ارتباط بانکی هنوز از صرف ذخیره این تنظیمات برقرار نمی‌شود. یک رابط مورداعتماد بانکداری باز یا Worker مرورگرِ فقط‌خواندن باید داده‌های واقعی تجارت را ارسال کند. در زمان انقضای نشست بانک هیچ پرداختی نباید از داده قدیمی تأیید شود.</p>
<p>تطبیق تنها با مبلغ دقیق ریالی، شماره کارت مقصد، زمان معتبر، سفارش یکتا و شناسه تراکنش انجام می‌شود. تراکنش‌های مبهم و تکراری به بررسی دستی می‌روند.</p>
</div>

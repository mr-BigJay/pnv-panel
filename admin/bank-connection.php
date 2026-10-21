<?php
/** Rendered ONLY by the authenticated admin dashboard. */
if (!function_exists('pnvAdminIsLoggedIn') || !pnvAdminIsLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}
require_once dirname(__DIR__) . '/bank_connection_lib.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['pnv_bank_csrf'])) {
    $_SESSION['pnv_bank_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['pnv_bank_csrf'];
$cfg = pnvBankLoadConfig();
$notice = '';
$error = '';
$showSecret = '';
$viewTab = 'tejarat';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'نشست امنیتی معتبر نیست. صفحه را تازه کنید.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_credentials') {
            $username = (string)($_POST['bank_username'] ?? '');
            $password = (string)($_POST['bank_password'] ?? '');
            if (pnvBankSaveTejaratCredentials($username, $password)) {
                $notice = 'اطلاعات ورود تجارت به‌صورت رمزگذاری‌شده در فضای خصوصی سرور ذخیره شد.';
            } else {
                $error = 'ذخیره امن انجام نشد. کلید رمزگذاری سرور یا اطلاعات ورودی را بررسی کنید.';
            }
            unset($password, $_POST['bank_password']);
        } elseif ($action === 'delete_credentials') {
            if ((string)($_POST['confirm_delete'] ?? '') !== '1') {
                $error = 'برای حذف اطلاعات ورود، تأیید حذف را انتخاب کنید.';
            } elseif (pnvBankDeleteTejaratCredentials()) {
                $notice = 'اطلاعات ورود ذخیره‌شده حذف شد.';
            } else {
                $error = 'حذف اطلاعات ورود ناموفق بود.';
            }
        } elseif ($action === 'save_feed' || $action === 'generate_secret') {
            $secret = trim((string)($_POST['shared_secret'] ?? ''));
            if ($action === 'generate_secret') {
                $secret = bin2hex(random_bytes(32));
                $showSecret = $secret;
            } elseif ($secret === '') {
                $secret = (string)($cfg['shared_secret'] ?? '');
            }
            $newConfig = array_merge($cfg, [
                'destination_card' => (string)($_POST['destination_card'] ?? $cfg['destination_card']),
                'enabled' => isset($_POST['enabled']),
                'dry_run' => !isset($_POST['enable_live']),
                'shared_secret' => $secret,
            ]);
            if (!pnvBankSaveConfig($newConfig)) {
                $error = 'ذخیره تنظیمات تراکنش ناموفق بود. مسیر خصوصی /var/lib/pnv-panel و شماره کارت ۱۶ رقمی را بررسی کنید.';
            } else {
                $cfg = pnvBankLoadConfig();
                $notice = 'تنظیمات دریافت تراکنش ذخیره شد؛ این کار به معنی اتصال موفق به بانک نیست.';
            }
        } else {
            $error = 'درخواست تنظیمات ناشناخته است.';
        }
    }
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$card = (string)($cfg['destination_card'] ?? '');
$enabled = !empty($cfg['enabled']);
$live = $enabled && empty($cfg['dry_run']);
$credentialsStatus = pnvBankTejaratCredentialsStatus();
?>
<div class="box" style="direction:rtl;max-width:920px;line-height:1.9">
<h2 style="margin-bottom:8px">بانک‌ها</h2>
<p style="color:#94a3b8">مدیریت اتصال حساب‌ها و تطبیق خودکار واریزی‌ها با مبالغ پنل</p>
<div role="tablist" aria-label="انتخاب بانک" style="display:flex;gap:10px;margin:20px 0;border-bottom:1px solid #475569;padding-bottom:12px">
  <div role="tab" aria-selected="true" style="background:#2563eb;color:#fff;padding:9px 23px;border-radius:12px;font-weight:bold">بانک تجارت</div>
</div>
<?php if ($notice): ?><p role="status" style="color:#86efac"><?= $h($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p role="alert" style="color:#fca5a5"><?= $h($error) ?></p><?php endif; ?>
<h3>اتصال حساب تجارت</h3>
<p><strong>وضعیت:</strong> <?= $credentialsStatus === 'configured' ? 'اطلاعات ورود رمزگذاری و ذخیره شده؛ اتصال بانکی هنوز تأیید نشده' : ($credentialsStatus === 'invalid' ? 'اطلاعات ورود ذخیره‌شده قابل بازیابی نیست (کلید/فایل را بررسی کنید)' : 'اطلاعات ورود تنظیم نشده') ?></p>
<p style="color:#cbd5e1">نام کاربری و رمز عبور اینترنت‌بانک اختیاری هستند. فقط در صورت نیاز به دسترسی حساب وارد شوند؛ برای ورود اولیه یا تمدید نشست ممکن است احراز هویت دومرحله‌ای بانک لازم باشد. این فرم خودش ورود به بانک یا تأیید پرداخت انجام نمی‌دهد.</p>
<form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks')) ?>" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <input type="hidden" name="action" value="save_credentials">
  <label for="tejarat-username" style="display:block;margin-top:12px">نام کاربری اینترنت‌بانک تجارت</label>
  <input id="tejarat-username" name="bank_username" type="text" dir="ltr" autocomplete="off" maxlength="150" required placeholder="<?= $credentialsStatus === 'configured' ? 'برای تغییر، نام کاربری جدید را وارد کنید' : 'نام کاربری اینترنت‌بانک' ?>">
  <label for="tejarat-password" style="display:block;margin-top:12px">رمز عبور اینترنت‌بانک تجارت</label>
  <input id="tejarat-password" name="bank_password" type="password" dir="ltr" autocomplete="new-password" maxlength="1024" required placeholder="<?= $credentialsStatus === 'configured' ? 'برای تغییر، رمز عبور جدید را وارد کنید' : 'رمز ورود' ?>">
  <p style="color:#94a3b8;font-size:13px">رمز عبور بعد از ذخیره در فرم نمایش داده نمی‌شود. رمز پویا، کد پیامکی و OTP را اینجا وارد نکنید.</p>
  <button type="submit" style="margin-top:12px">ذخیره امن اطلاعات ورود</button>
</form>
<?php if ($credentialsStatus !== 'not_configured'): ?>
<form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks')) ?>" style="margin:14px 0 26px">
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <input type="hidden" name="action" value="delete_credentials">
  <label><input type="checkbox" name="confirm_delete" value="1" required> تأیید حذف اطلاعات ورود ذخیره‌شده</label>
  <button type="submit" style="background:#991b1b">حذف اطلاعات ورود تجارت</button>
</form>
<?php endif; ?>
<hr style="border:0;border-top:1px solid #475569;margin:24px 0">
<h3>تنظیمات دریافت و تطبیق واریزی‌ها</h3>
<p><strong>وضعیت پردازش:</strong> <?= !$enabled ? 'غیرفعال' : ($live ? 'پذیرش تراکنش و امکان تأیید خودکار فعال شده' : 'حالت آزمایشی، بدون تأیید پرداخت') ?></p>
<form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks')) ?>" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <label for="tejarat-card" style="display:block;margin-top:12px">شماره کارت تجارت مقصد (۱۶ رقم)</label>
  <input id="tejarat-card" name="destination_card" dir="ltr" inputmode="numeric" value="<?= $h($card) ?>" maxlength="24" placeholder="شماره کارت واریز">
  <label for="tejarat-feed-key" style="display:block;margin-top:12px">کلید ارتباط امن با منبع گردش حساب</label>
  <input id="tejarat-feed-key" type="password" dir="ltr" name="shared_secret" autocomplete="new-password" placeholder="<?= strlen((string)($cfg['shared_secret'] ?? '')) >= 32 ? 'کلید موجود است؛ برای نگه داشتن خالی بگذارید' : 'ابتدا یک کلید ایجاد کنید' ?>">
  <label style="display:block;margin:10px 0"><input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>> دریافت تراکنش امضاشده فعال باشد</label>
  <label style="display:block;margin:10px 0"><input type="checkbox" name="enable_live" value="1" <?= $live ? 'checked' : '' ?>> پس از آزمایش کامل منبع معتبر، تأیید خودکار فعال شود</label>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button type="submit" name="action" value="save_feed">ذخیره تنظیمات تراکنش</button>
    <button type="submit" name="action" value="generate_secret" style="background:#475569">تولید کلید اتصال</button>
  </div>
</form>
<?php if ($showSecret): ?>
<p style="color:#fcd34d">کلید جدید فقط همین بار نمایش داده می‌شود؛ آن را محرمانه در Worker مجاز ذخیره کنید.</p>
<textarea readonly dir="ltr" rows="2" style="width:100%"><?= $h($showSecret) ?></textarea>
<?php endif; ?>
<p style="font-size:14px;color:#94a3b8">آدرس دریافت تراکنش از منبع معتبر: <code dir="ltr">/bank-transaction-webhook.php</code>. تا زمانی که اطلاعات واقعی و امضاشده بانک به این مسیر نرسد، پرداخت‌ها به‌صورت خودکار تأیید نمی‌شوند.</p>
</div>

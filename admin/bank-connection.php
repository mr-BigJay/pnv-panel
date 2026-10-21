<?php
/** Rendered ONLY by the authenticated admin dashboard. */
if (!function_exists('pnvAdminIsLoggedIn') || !pnvAdminIsLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}
require_once dirname(__DIR__) . '/bank_connection_lib.php';
require_once dirname(__DIR__) . '/bank_control_lib.php';
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
$nextWizardStep = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'نشست امنیتی معتبر نیست. صفحه را تازه کنید.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_credentials') {
            $username = (string)($_POST['bank_username'] ?? '');
            $password = (string)($_POST['bank_password'] ?? '');
            if (pnvBankSaveTejaratCredentials($username, $password)) {
                $notice = 'اطلاعات ورود با موفقیت و به‌صورت رمزگذاری‌شده ذخیره شد. مرحله بعد: ورود اولیه.';
                $nextWizardStep = 2;
            } else {
                $error = 'ذخیره امن انجام نشد. کلید رمزگذاری سرور یا اطلاعات ورودی را بررسی کنید.';
            }
            unset($password, $_POST['bank_password']);
        } elseif ($action === 'delete_credentials') {
            if ((string)($_POST['confirm_delete'] ?? '') !== '1') {
                $error = 'برای حذف اطلاعات ورود، تأیید حذف را انتخاب کنید.';
            } elseif (pnvBankDeleteTejaratCredentials()) {
                $notice = 'اطلاعات ورود ذخیره‌شده حذف شد.';
                $nextWizardStep = 1;
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
                $notice = 'تنظیمات ذخیره شد. اتصال واقعی بانک هنوز باید جداگانه آزمایش شود.';
                $nextWizardStep = 3;
            }
        } elseif ($action === 'start_login' || $action === 'check_session') {
            $nextWizardStep = 2;
            if ($action === 'start_login' && pnvBankTejaratCredentialsStatus() !== 'configured') {
                $error = 'ابتدا نام کاربری و رمز حساب را در مرحله اول ذخیره کن.';
            } else {
                $workerResult = pnvTejaratControlRequest($action === 'start_login' ? 'start' : 'check');
                if (!empty($workerResult['ok'])) {
                    $notice = 'دستور بررسی یا شروع اتصال به سرویس بانک ارسال شد. وضعیت را چند ثانیه بعد تازه کن.';
                } elseif (($workerResult['code'] ?? '') === 'already_running') {
                    $notice = 'مرورگر بانک در حال اجراست؛ منتظر نتیجه باش.';
                } else {
                    $error = pnvTejaratControlMessage($workerResult['state'] ?? 'unavailable');
                }
            }
        } elseif ($action === 'check_readiness') {
            // Read-only preflight: this never tests a bank session or changes payment status.
            $nextWizardStep = 3;
            $notice = 'بررسی محلی تنظیمات انجام شد. نتیجه و موارد باقی‌مانده در پایین صفحه دیده می‌شوند؛ این بررسی به معنی اتصال به بانک نیست.';
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
$stepParam = (string)($_GET['step'] ?? '');
$requestedStep = in_array($stepParam, ['1', '2', '3'], true) ? intval($stepParam) : null;
$wizardStep = $nextWizardStep ?? $requestedStep ?? ($credentialsStatus === 'configured' ? 2 : 1);
$hasCard = strlen(pnvBankDigits($card)) === 16;
$hasFeedSecret = strlen((string)($cfg['shared_secret'] ?? '')) >= 32;
$readyConfig = $hasCard && $hasFeedSecret && $enabled;
$workerLiveStatus = ($wizardStep === 2) ? pnvTejaratControlRequest('status') : ['ok'=>false, 'state'=>'unavailable'];
?>

<style>
.pnvBankWizard{max-width:960px;margin:auto;direction:rtl;line-height:1.9;color:#e2e8f0}
.pnvBankWizard *{box-sizing:border-box}
.pnvBankWizard .bankSubtitle{color:#94a3b8;margin:0 0 20px}
.pnvBankWizard .bankStepper{display:flex;align-items:center;gap:8px;margin:18px 0 26px;padding:14px 9px;background:#0f172a;border:1px solid #334155;border-radius:16px}
.pnvBankWizard .bankStep{flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:9px;text-decoration:none;color:#94a3b8;padding:8px 5px;border-radius:11px;font-size:13px;white-space:nowrap}
.pnvBankWizard .bankStep.isActive{color:#fff;background:#1d4ed8}
.pnvBankWizard .bankStep.isVisited:not(.isActive){color:#a5b4fc}
.pnvBankWizard .bankStepNo{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;width:30px;height:30px;background:#334155;color:#e2e8f0;font-weight:bold;flex:none}
.pnvBankWizard .bankStep.isActive .bankStepNo{background:#eff6ff;color:#1d4ed8}
.pnvBankWizard .bankDivider{height:2px;width:10px;background:#475569;flex:none}
.pnvBankWizard .bankPanel{background:#0f172a;border:1px solid #334155;border-radius:16px;padding:20px}
.pnvBankWizard .bankPanel h3{margin:0 0 6px}
.pnvBankWizard .bankHint{color:#94a3b8;font-size:13px;line-height:1.9;margin:8px 0 14px}
.pnvBankWizard .bankNote{background:#1e293b;border:1px solid #475569;border-radius:12px;padding:13px;margin:14px 0;font-size:14px}
.pnvBankWizard .bankError{color:#fecaca;background:#7f1d1d;padding:12px;border-radius:11px}
.pnvBankWizard .bankOK{color:#bbf7d0;background:#14532d;padding:12px;border-radius:11px}
.pnvBankWizard label.bankLabel{display:block;margin:12px 0 5px}
.pnvBankWizard .bankField{width:100%;background:#1e293b;color:#fff;padding:12px;border:1px solid #475569;border-radius:10px;font:inherit;font-size:14px}
.pnvBankWizard .bankActions{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
.pnvBankWizard .bankAction{display:inline-flex;align-items:center;justify-content:center;text-align:center;text-decoration:none;background:#2563eb;color:#fff;border:0;border-radius:11px;padding:11px 16px;font:inherit;font-size:14px;cursor:pointer}
.pnvBankWizard .bankAction.secondary{background:#334155}
.pnvBankWizard .bankAction.danger{background:#991b1b}
.pnvBankWizard .bankAction[disabled]{opacity:.55;cursor:not-allowed}
.pnvBankWizard .bankCheckRow{display:flex;align-items:center;gap:8px;margin:11px 0}
.pnvBankWizard .bankCheckRow input{width:auto;flex:none}
.pnvBankWizard .bankProgress{display:grid;gap:6px;padding:0;margin:12px 0;list-style:none}
.pnvBankWizard .bankProgress li{border-bottom:1px solid #334155;padding:7px 0;font-size:14px}
.pnvBankWizard .bankBadge{color:#fbbf24}
.pnvBankWizard .bankStatus{font-size:14px;padding:12px;background:#1e293b;border-radius:12px;margin-bottom:15px}
@media(max-width:660px){
 .pnvBankWizard .bankStepper{gap:3px;padding:8px 4px}
 .pnvBankWizard .bankStep{font-size:11px;flex-direction:column;gap:3px;white-space:normal;line-height:1.3;text-align:center;padding:8px 3px}
 .pnvBankWizard .bankStepNo{width:26px;height:26px}
 .pnvBankWizard .bankDivider{width:6px}
 .pnvBankWizard .bankPanel{padding:14px}
}
</style>
<div class="box pnvBankWizard">
  <h2 style="margin:0 0 4px">اتصال بانک</h2>
  <p class="bankSubtitle">راه‌اندازی مرحله‌به‌مرحله تأیید واریزها، مشابه خرید و تمدید اشتراک</p>
  <div role="tablist" aria-label="انتخاب بانک" style="margin:12px 0">
    <span role="tab" aria-selected="true" style="display:inline-block;padding:8px 20px;background:#2563eb;color:white;border-radius:11px">بانک تجارت</span>
  </div>

  <nav class="bankStepper" aria-label="مراحل راه‌اندازی بانک تجارت">
    <?php foreach ([1 => 'اطلاعات حساب', 2 => 'ورود و پیامک', 3 => 'تست و فعال‌سازی'] as $num => $label): ?>
      <?php if ($num > 1): ?><span class="bankDivider" aria-hidden="true"></span><?php endif; ?>
      <a href="<?= $h(pnvAdminUrl('index.php?page=banks&step=' . $num)) ?>" class="bankStep <?= $wizardStep === $num ? 'isActive' : ($num < $wizardStep ? 'isVisited' : '') ?>" <?= $wizardStep === $num ? 'aria-current="step"' : '' ?>>
        <span class="bankStepNo"><?= $num ?></span><span><?= $h($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($notice): ?><p role="status" class="bankOK"><?= $h($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p role="alert" class="bankError"><?= $h($error) ?></p><?php endif; ?>

  <?php if ($wizardStep === 1): ?>
    <section class="bankPanel" aria-labelledby="bank-title-one">
      <h3 id="bank-title-one">مرحله ۱ از ۳ — اطلاعات حساب تجارت</h3>
      <p class="bankHint">نام کاربری و رمز عبور به‌شکل رمزگذاری‌شده و خارج از پوشه عمومی وب ذخیره می‌شوند. اگر قبلاً وارد کرده‌ای، دوباره لازم نیست.</p>
      <div class="bankStatus"><strong>وضعیت اطلاعات ورود:</strong>
        <?= $credentialsStatus === 'configured' ? 'ذخیره‌شده و قابل‌بازیابی' : ($credentialsStatus === 'invalid' ? 'خطا در بازیابی رمز؛ کلید سرور را بررسی کن' : 'هنوز ذخیره نشده') ?>
      </div>
      <form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks&step=1')) ?>" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="save_credentials">
        <label class="bankLabel" for="tejarat-username">نام کاربری اینترنت‌بانک</label>
        <input class="bankField" id="tejarat-username" name="bank_username" type="text" dir="ltr" maxlength="150" autocomplete="off" required placeholder="نام کاربری اینترنت‌بانک تجارت">
        <label class="bankLabel" for="tejarat-password">رمز عبور</label>
        <input class="bankField" id="tejarat-password" name="bank_password" type="password" dir="ltr" maxlength="1024" autocomplete="new-password" required placeholder="رمز عبور اینترنت‌بانک">
        <p class="bankHint">رمز پس از ذخیره نمایش داده نمی‌شود. کد پیامکی، رمز پویا و OTP را اینجا وارد نکن.</p>
        <div class="bankActions">
          <button class="bankAction" type="submit">ذخیره و ادامه</button>
          <?php if ($credentialsStatus === 'configured'): ?>
          <a class="bankAction secondary" href="<?= $h(pnvAdminUrl('index.php?page=banks&step=2')) ?>">قبلاً ذخیره کردم؛ ادامه</a>
          <?php endif; ?>
        </div>
      </form>
      <?php if ($credentialsStatus !== 'not_configured'): ?>
        <form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks&step=1')) ?>" style="margin-top:22px">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <input type="hidden" name="action" value="delete_credentials">
          <label class="bankCheckRow"><input type="checkbox" name="confirm_delete" value="1" required> تأیید حذف اطلاعات ورود ذخیره‌شده</label>
          <button class="bankAction danger" type="submit">حذف اطلاعات ورود</button>
        </form>
      <?php endif; ?>
    </section>
  <?php elseif ($wizardStep === 2): ?>
    <section class="bankPanel" aria-labelledby="bank-title-two">
      <h3 id="bank-title-two">مرحله ۲ از ۳ — ورود اولیه و تأیید پیامکی</h3>
      <div class="bankStatus" role="status"><strong>وضعیت مرورگر سرور:</strong>
        <?= $h(pnvTejaratControlMessage($workerLiveStatus['state'] ?? 'unavailable')) ?>
      </div>
      <p class="bankHint">با «شروع اتصال»، Worker تلاش می‌کند نشست قبلی را بررسی کند و در صورت نیاز، صفحه رسمی ورود تجارت را با اطلاعات ذخیره‌شده باز کند. اگر بانک پیامک یا فعال‌سازی درخواست کرد، آن مرحله را فقط در مرورگر قابل‌مشاهده روی سرور تکمیل کن.</p>
      <div class="bankNote">توجه: صفحه <code dir="ltr">/register</code> ممکن است علاوه بر نام کاربری، شماره همراه ثبت‌شده را بخواهد. این مرحله بدون تعامل در مرورگر رسمی بانک قابل‌تکمیل نیست. همچنین مرورگر سرور باید نمایشگر امن Xvfb/VNC (ترجیحاً فقط از طریق تونل SSH) داشته باشد؛ باز کردن سایت روی رایانه تو نشست سرور را فعال نمی‌کند.</div>
      <form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks&step=2')) ?>">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <div class="bankActions">
          <button class="bankAction" type="submit" name="action" value="start_login" <?= $credentialsStatus !== 'configured' ? 'disabled' : '' ?>>شروع اتصال بانک تجارت</button>
          <button class="bankAction secondary" type="submit" name="action" value="check_session">بررسی نشست ذخیره‌شده</button>
          <a class="bankAction secondary" href="<?= $h(pnvAdminUrl('index.php?page=banks&step=2')) ?>">تازه‌کردن وضعیت</a>
        </div>
      </form>
      <p class="bankHint">پنل هیچ رمز یک‌بارمصرف یا کد پیامکی از تو دریافت نمی‌کند. OTP را <strong>فقط در صفحه رسمی بانک</strong> داخل نشست امن مرورگر سرور وارد کن.</p>
      <div class="bankActions">
        <a class="bankAction secondary" href="<?= $h(pnvAdminUrl('index.php?page=banks&step=1')) ?>">مرحله قبل</a>
        <a class="bankAction secondary" href="https://hb.tejaratbank.ir/login" rel="noopener noreferrer" target="_blank">وب‌بانک رسمی <span aria-hidden="true">↗</span></a>
        <a class="bankAction" href="<?= $h(pnvAdminUrl('index.php?page=banks&step=3')) ?>">مرحله سوم: تنظیمات و تست</a>
      </div>
      <div class="bankNote"><strong>محدودیت فعلی:</strong> حتی وضعیت «نشست تأیید شد» به‌معنی دریافت تراکنش یا اجازه تأیید خودکار نیست. Worker دریافت تراکنش باید جداگانه با داده واقعی و حالت آزمایشی تست شود.</div>
    </section>
  <?php else: ?>
    <section class="bankPanel" aria-labelledby="bank-title-three">
      <h3 id="bank-title-three">مرحله ۳ از ۳ — آزمایش و تأیید واریزی‌ها</h3>
      <div class="bankStatus"><strong>وضعیت بانک:</strong> اتصال واقعی و نشست معتبر بانک از این صفحه هنوز قابل تأیید نیست.</div>
      <p class="bankHint">بعد از راه‌اندازی Worker فقط‌خواندن، تراکنش‌های واقعی باید با امضای معتبر از بانک تجارت به پنل برسن و مبلغ دقیق، کارت مقصد و شناسه یکتای تراکنش با سفارش تطبیق داده بشن.</p>
      <form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks&step=3')) ?>" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <label class="bankLabel" for="tejarat-card">شماره کارت تجارت مقصد (۱۶ رقم)</label>
        <input class="bankField" id="tejarat-card" name="destination_card" dir="ltr" inputmode="numeric" maxlength="24" value="<?= $h($card) ?>" placeholder="شماره کارت واریز">
        <label class="bankLabel" for="tejarat-feed-key">کلید اتصال امن به دریافت‌کننده تراکنش‌ها</label>
        <input class="bankField" type="password" dir="ltr" id="tejarat-feed-key" name="shared_secret" autocomplete="new-password" placeholder="<?= $hasFeedSecret ? 'کلید موجود است؛ برای نگه داشتن خالی بگذار' : 'کلید اتصال را تولید کن' ?>">
        <label class="bankCheckRow"><input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>> دریافت تراکنش امضاشده فعال باشد</label>
        <label class="bankCheckRow"><input type="checkbox" name="enable_live" value="1" <?= $live ? 'checked' : '' ?>> فقط پس از بررسی واقعی منبع بانک، تأیید خودکار را فعال کنم</label>
        <div class="bankNote">برای جلوگیری از تأیید اشتباه، این گزینه رو تا قبل از اتصال Worker، انجام Dry Run با تراکنش واقعی و تأیید نتیجه خاموش نگه دار.</div>
        <div class="bankActions">
          <button class="bankAction" type="submit" name="action" value="save_feed">ذخیره تنظیمات</button>
          <button class="bankAction secondary" type="submit" name="action" value="generate_secret">تولید کلید امن</button>
        </div>
      </form>
      <?php if ($showSecret): ?>
        <p class="bankHint">کلید تازه تولیدشده فقط همین بار نمایش داده می‌شود؛ آن را فقط در محیط امن Worker ذخیره کن.</p>
        <textarea class="bankField" dir="ltr" readonly rows="2"><?= $h($showSecret) ?></textarea>
      <?php endif; ?>
      <h4 style="margin-bottom:6px">وضعیت آمادگی تنظیمات</h4>
      <ul class="bankProgress">
        <li><?= $credentialsStatus === 'configured' ? '✓' : '○' ?> اطلاعات ورود رمزگذاری‌شده: <?= $credentialsStatus === 'configured' ? 'موجود' : 'ثبت نشده / نامعتبر (برای API رسمی اختیاری)' ?></li>
        <li><?= $hasCard ? '✓' : '○' ?> شماره کارت تجارت: <?= $hasCard ? 'ثبت شده' : 'ثبت نشده' ?></li>
        <li><?= $hasFeedSecret ? '✓' : '○' ?> کلید ارتباط امضاشده: <?= $hasFeedSecret ? 'ساخته شده' : 'ساخته نشده' ?></li>
        <li><?= $enabled ? '✓' : '○' ?> دریافت تراکنش: <?= $enabled ? 'پیکربندی‌شده' : 'غیرفعال' ?></li>
        <li><span class="bankBadge">!</span> نشست Worker و دریافت گردش حساب واقعی: هنوز تأیید نشده</li>
      </ul>
      <div class="bankNote">
        <strong>جمع‌بندی:</strong> <?= $readyConfig ? 'تنظیمات محلی آماده‌اند، اما بانک هنوز وصل‌شده تلقی نمی‌شود.' : 'برای آزمایش عملی، ابتدا شماره کارت، کلید اتصال و حالت دریافت تراکنش را تنظیم کن.' ?>
        <span style="display:block;margin-top:6px">آدرس دریافت تراکنش از منبع معتبر: <code dir="ltr">/bank-transaction-webhook.php</code></span>
      </div>
      <form method="post" action="<?= $h(pnvAdminUrl('index.php?page=banks&step=3')) ?>">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="check_readiness">
        <div class="bankActions">
          <a class="bankAction secondary" href="<?= $h(pnvAdminUrl('index.php?page=banks&step=2')) ?>">مرحله قبل</a>
          <button class="bankAction" type="submit">بررسی آمادگی تنظیمات (بدون اتصال به بانک)</button>
        </div>
      </form>
    </section>
  <?php endif; ?>
</div>
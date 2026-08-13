<?php

if(file_exists(__DIR__ . '/auth.php')){
    require_once __DIR__ . '/auth.php';
    pnvAdminRequireAuth();
}
else{
    session_start();

    if(!isset($_SESSION['admin'])){
        header('Location: index.php');
        exit;
    }
}

require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/../sms_lib.php';

$config = smsLoadConfig();
$message = '';
$error = '';
$providers = smsProviderOptions();
$currentProvider = (string)($config['provider'] ?? 'smsir');

if(isset($_POST['save'])){
    $saved = smsLoadConfig();

    $apiKey = trim((string)($_POST['api_key'] ?? ''));
    if($apiKey === '' && trim((string)($saved['api_key'] ?? '')) !== ''){
        $apiKey = $saved['api_key'];
    }

    $password = trim((string)($_POST['password'] ?? ''));
    if($password === '' && trim((string)($saved['password'] ?? '')) !== ''){
        $password = $saved['password'];
    }

    $provider = trim((string)($_POST['provider'] ?? 'smsir'));
    if(!isset($providers[$provider])){
        $provider = 'smsir';
    }

    $config = [
        'enabled' => isset($_POST['enabled']),
        'provider' => $provider,
        'api_key' => $apiKey,
        'username' => trim((string)($_POST['username'] ?? '')),
        'password' => $password,
        'sender' => trim((string)($_POST['sender'] ?? '')),
        'register_welcome' => isset($_POST['register_welcome']),
        'register_welcome_template' => trim((string)($_POST['register_welcome_template'] ?? '')),
        'test_mobile' => trim((string)($_POST['test_mobile'] ?? '')),
    ];

    smsSaveConfig($config);
    $currentProvider = $provider;
    $message = 'تنظیمات پیامک ذخیره شد.';
}

if(isset($_POST['test'])){
    $config = smsLoadConfig();
    $mobile = trim((string)($_POST['test_mobile'] ?? ($config['test_mobile'] ?? '')));

    if($mobile === ''){
        $error = 'شماره موبایل تست را وارد کنید.';
    }
    else{
        $result = smsSend($mobile, '✅ تست اتصال پنل SMS تیکتین — ' . date('Y-m-d H:i'), $config);
        if(!empty($result['ok'])){
            $message = 'پیامک تست با موفقیت ارسال شد.';
        }
        else{
            $error = $result['error'] ?? 'ارسال پیامک تست ناموفق بود.';
        }
    }
}

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$needsApiKey = in_array($currentProvider, ['smsir', 'kavenegar', 'ippanel'], true);
$needsUserPass = ($currentProvider === 'melipayamak');
$senderHint = ($currentProvider === 'smsir')
    ? 'شماره خط اختصاصی SMS.ir (مثلاً 30004505000017)'
    : '1000xxxx یا 3000xxxx';

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات پیامک</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{width:100%;max-width:760px;margin:auto;background:#1e293b;padding:30px;border-radius:20px}
h2{text-align:center;margin:0 0 28px;font-size:26px}
label{display:block;margin:18px 0 8px;font-size:15px;color:#e2e8f0}
input,textarea,select{width:100%;border:0;border-radius:12px;padding:14px;background:#0f172a;color:#fff;font-family:inherit;font-size:15px;line-height:1.8}
textarea{min-height:90px;resize:vertical}
select{cursor:pointer}
.toggle{display:flex;align-items:center;gap:10px;cursor:pointer;margin:0 0 22px}
.toggle input{width:20px;height:20px;margin:0}
.hint{background:#172554;border-radius:12px;padding:14px;color:#cbd5e1;font-size:14px;line-height:2;margin-top:10px}
.hint code{direction:ltr;display:inline-block;color:#93c5fd;word-break:break-all}
.msg,.err{padding:14px;border-radius:12px;line-height:1.8;margin-bottom:18px}
.msg{background:#166534}.err{background:#991b1b}
button,.back{display:block;width:100%;border:0;border-radius:12px;padding:15px;background:#22c55e;color:#fff;font:inherit;font-size:17px;cursor:pointer;text-align:center;text-decoration:none;margin-top:14px}
.test{background:#2563eb}.back{background:#334155;margin-top:20px}
.fieldGroup[data-provider]{display:none}
.fieldGroup.is-visible{display:block}
@media(max-width:600px){body{padding:10px}.box{padding:22px 16px;border-radius:16px}h2{font-size:22px}}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('sms'); ?>

<div class="box">
<h2>اتصال پنل SMS</h2>

<?php if($message !== ''){ ?><div class="msg"><?php echo $h($message); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo $h($error); ?></div><?php } ?>

<form method="post">
<label class="toggle">
<input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی ارسال پیامک</span>
</label>

<label for="provider">سرویس‌دهنده</label>
<select name="provider" id="provider">
<?php foreach($providers as $key => $label){ ?>
<option value="<?php echo $h($key); ?>" <?php echo $currentProvider === $key ? 'selected' : ''; ?>><?php echo $h($label); ?></option>
<?php } ?>
</select>

<div class="fieldGroup <?php echo $needsApiKey ? 'is-visible' : ''; ?>" data-provider="smsir kavenegar ippanel">
<label for="api_key">API Key</label>
<input type="password" name="api_key" id="api_key" value="" placeholder="<?php echo trim((string)($config['api_key'] ?? '')) !== '' ? '•••••••• (ذخیره شده)' : 'کلید API از پنل SMS.ir'; ?>" autocomplete="off">
</div>

<div class="fieldGroup <?php echo $needsUserPass ? 'is-visible' : ''; ?>" data-provider="melipayamak">
<label for="username">نام کاربری پنل</label>
<input type="text" name="username" id="username" value="<?php echo $h($config['username'] ?? ''); ?>" dir="ltr">
<label for="password">رمز عبور پنل</label>
<input type="password" name="password" id="password" value="" placeholder="<?php echo trim((string)($config['password'] ?? '')) !== '' ? '•••••••• (ذخیره شده)' : 'رمز عبور'; ?>" autocomplete="off">
</div>

<label for="sender">شماره خط ارسال (Line Number)</label>
<input type="text" name="sender" id="sender" value="<?php echo $h($config['sender'] ?? ''); ?>" dir="ltr" placeholder="<?php echo $h($senderHint); ?>">

<label class="toggle">
<input type="checkbox" name="register_welcome" <?php echo !empty($config['register_welcome']) ? 'checked' : ''; ?>>
<span>ارسال پیامک خوش‌آمد بعد از ثبت‌نام</span>
</label>

<label for="register_welcome_template">متن پیامک خوش‌آمد</label>
<textarea name="register_welcome_template" id="register_welcome_template"><?php echo $h($config['register_welcome_template'] ?? ''); ?></textarea>

<label for="test_mobile">موبایل تست</label>
<input type="text" name="test_mobile" id="test_mobile" value="<?php echo $h($config['test_mobile'] ?? ''); ?>" placeholder="09123456789" dir="ltr">

<div class="hint">
<p>برای <strong>SMS.ir (ایده‌پردازان)</strong>: از منوی <code>برنامه‌نویسان</code> در <a href="https://app.sms.ir/developer/list" target="_blank" rel="noopener" style="color:#93c5fd">app.sms.ir</a> کلید API بگیرید.</p>
<p>شماره خط (Line Number) همان خط اختصاصی پنل شماست — از بخش خطوط در SMS.ir کپی کنید.</p>
<p>پس از ذخیره، با دکمه «ارسال تست» اتصال را بررسی کنید.</p>
<p>متغیرهای قالب خوش‌آمد: <code>{username}</code> ، <code>{mobile}</code></p>
</div>

<button type="submit" name="save" value="1">ذخیره تنظیمات</button>
<button type="submit" name="test" value="1" class="test">ارسال پیامک تست</button>
</form>

<a class="back" href="<?php echo $h(function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php'); ?>">بازگشت به داشبورد</a>
</div>

<script>
(function(){
    var provider = document.getElementById('provider');
    var groups = document.querySelectorAll('.fieldGroup[data-provider]');

    function syncFields(){
        var value = provider ? provider.value : 'smsir';
        groups.forEach(function(group){
            var allowed = (group.getAttribute('data-provider') || '').split(/\s+/);
            group.classList.toggle('is-visible', allowed.indexOf(value) >= 0);
        });
    }

    if(provider){
        provider.addEventListener('change', syncFields);
        syncFields();
    }
})();
</script>

</body>
</html>

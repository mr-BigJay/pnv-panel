<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';

$smsLibPath = dirname(__DIR__) . '/sms_lib.php';
if(!is_file($smsLibPath)){
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    $entry = function_exists('pnvAdminUrl') ? pnvAdminUrl() : '../bigjay_controller/';
    echo '<!DOCTYPE html><html lang="fa"><head><meta charset="UTF-8"><title>خطای پیامک</title></head><body style="font-family:tahoma;direction:rtl;padding:24px;background:#0f172a;color:#fff">';
    echo '<h2>فایل sms_lib.php روی سرور نیست</h2>';
    echo '<p>برای رفع خطای 500، فایل <code>sms_lib.php</code> را در ریشه سایت آپلود کنید.</p>';
    echo '<p><a href="' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . '" style="color:#93c5fd">بازگشت به داشبورد</a></p>';
    echo '</body></html>';
    exit;
}

require_once $smsLibPath;

if(!function_exists('smsLoadConfig')){
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fa"><head><meta charset="UTF-8"><title>خطای پیامک</title></head><body style="font-family:tahoma;direction:rtl;padding:24px;background:#0f172a;color:#fff">';
    echo '<h2>کتابخانه پیامک ناقص است</h2>';
    echo '<p>فایل sms_lib.php قدیمی یا خراب است. آخرین نسخه را از GitHub دوباره deploy کنید.</p>';
    echo '</body></html>';
    exit;
}

if(!function_exists('smsSanitizeApiKey')){
    function smsSanitizeApiKey($key){
        $key = trim((string)$key);
        return trim($key, " \t\n\r\0\x0B\"'`");
    }
}

pnvAdminRequireAuth();

$config = smsLoadConfig();
$message = '';
$error = '';
$providers = smsProviderOptions();
$templateMenu = smsTemplateMenu();
$templateMeta = smsTemplateMeta();
$templates = smsMergeTemplates($config['templates'] ?? null);
$currentProvider = (string)($config['provider'] ?? 'smsir');

$tab = trim((string)($_GET['tab'] ?? 'connection'));
if(!isset($templateMenu[$tab])){
    $tab = 'connection';
}

$basePageUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl('sms.php') : 'sms.php';

if(isset($_POST['save'])){
    $saved = smsLoadConfig();

    $apiKey = smsSanitizeApiKey($_POST['api_key'] ?? '');
    if($apiKey === '' && smsSanitizeApiKey($saved['api_key'] ?? '') !== ''){
        $apiKey = smsSanitizeApiKey($saved['api_key'] ?? '');
    }

    $password = trim((string)($_POST['password'] ?? ''));
    if($password === '' && trim((string)($saved['password'] ?? '')) !== ''){
        $password = $saved['password'];
    }

    $provider = trim((string)($_POST['provider'] ?? 'smsir'));
    if(!isset($providers[$provider])){
        $provider = 'smsir';
    }

    $postTab = trim((string)($_POST['tab'] ?? $tab));
    if(!isset($templateMenu[$postTab])){
        $postTab = 'connection';
    }

    if($postTab === 'connection'){
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
            'templates' => $saved['templates'] ?? smsDefaultTemplates(),
        ];
    }
    else{
        $config = $saved;
        $config['templates'] = smsParseTemplatesFromPost($_POST);
        if(trim((string)($_POST['test_mobile'] ?? '')) !== ''){
            $config['test_mobile'] = trim((string)$_POST['test_mobile']);
        }
    }

    smsSaveConfig($config);
    $config = smsLoadConfig();
    $templates = smsMergeTemplates($config['templates'] ?? null);
    $currentProvider = $provider;
    $tab = $postTab;
    $message = 'تنظیمات پیامک ذخیره شد.';
}

if(isset($_POST['test_connection']) || isset($_POST['test_template'])){
    $postTab = trim((string)($_POST['tab'] ?? $tab));
    if(isset($templateMenu[$postTab])){
        $tab = $postTab;
    }
}

if(isset($_POST['test_connection'])){
    $config = smsLoadConfig();
    $mobile = smsResolveTestMobile($_POST, $config);

    if($mobile === ''){
        $error = 'شماره موبایل تست را وارد کنید.';
    }
    else{
        smsRememberTestMobile($mobile, $config);
        $config = smsLoadConfig();
        $result = smsSend($mobile, '✅ تست اتصال پنل SMS تیکتین — ' . date('Y-m-d H:i'), $config);
        if(!empty($result['ok'])){
            $message = 'پیامک تست اتصال با موفقیت ارسال شد.';
        }
        else{
            $error = $result['error'] ?? 'ارسال پیامک تست ناموفق بود.';
        }
    }
}

if(isset($_POST['test_template'])){
    $config = smsLoadConfig();
    $mobile = smsResolveTestMobile($_POST, $config);
    $templateKey = smsNormalizeTemplateKey($_POST['test_template'] ?? ($_POST['test_template_key'] ?? ''));

    if($mobile === ''){
        $error = 'شماره موبایل تست را وارد کنید.';
    }
    elseif($templateKey === null){
        $error = 'الگوی انتخاب‌شده نامعتبر است.';
    }
    else{
        smsRememberTestMobile($mobile, $config);
        $config = smsLoadConfig();
        $draftTemplates = smsParseTemplatesFromPost($_POST);
        $config['templates'] = smsMergeTemplates($config['templates'] ?? null);
        $config['templates'][$templateKey] = $draftTemplates[$templateKey];
        $config['templates'][$templateKey]['enabled'] = true;

        $result = smsSendTemplate($mobile, $templateKey, smsSampleTemplateVars($templateKey), $config);
        if(!empty($result['ok'])){
            $message = 'پیامک نمونه الگوی «' . ($templateMenu[$templateKey] ?? $templateKey) . '» ارسال شد.';
        }
        else{
            $error = $result['error'] ?? 'ارسال پیامک نمونه ناموفق بود.';
        }
    }
}

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$tabUrl = static function($key) use ($basePageUrl){
    $sep = strpos($basePageUrl, '?') !== false ? '&' : '?';
    return $basePageUrl . $sep . 'tab=' . rawurlencode($key);
};

$needsApiKey = in_array($currentProvider, ['smsir', 'kavenegar', 'ippanel'], true);
$needsUserPass = ($currentProvider === 'melipayamak');
$senderHint = ($currentProvider === 'smsir')
    ? 'شماره خط اختصاصی SMS.ir (مثلاً 30004505000017)'
    : '1000xxxx یا 3000xxxx';

$testMobileDisplay = trim(smsNormalizeDigits((string)($_POST['test_mobile'] ?? ($config['test_mobile'] ?? ''))));
$apiKeySaved = smsSanitizeApiKey($config['api_key'] ?? '') !== '';
$senderSaved = smsParseLineNumber($config['sender'] ?? '') !== null;

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
.layout{width:100%;max-width:920px;margin:auto;display:grid;grid-template-columns:220px minmax(0,1fr);gap:16px}
.box{background:#1e293b;padding:24px;border-radius:20px}
.menuBox{padding:16px}
h2{margin:0 0 18px;font-size:24px}
h3{margin:0 0 10px;font-size:18px;color:#e2e8f0}
label{display:block;margin:16px 0 8px;font-size:15px;color:#e2e8f0}
input,textarea,select{width:100%;border:0;border-radius:12px;padding:14px;background:#0f172a;color:#fff;font-family:inherit;font-size:15px;line-height:1.8}
textarea{min-height:140px;resize:vertical}
select{cursor:pointer}
.toggle{display:flex;align-items:center;gap:10px;cursor:pointer;margin:0 0 18px}
.toggle input{width:20px;height:20px;margin:0}
.hint{background:#172554;border-radius:12px;padding:14px;color:#cbd5e1;font-size:14px;line-height:2;margin-top:10px}
.hint code{direction:ltr;display:inline-block;color:#93c5fd;word-break:break-all}
.msg,.err{padding:14px;border-radius:12px;line-height:1.8;margin-bottom:18px}
.msg{background:#166534}.err{background:#991b1b}
button,.back,.menuLink{display:block;width:100%;border:0;border-radius:12px;padding:13px 16px;background:#22c55e;color:#fff;font:inherit;font-size:15px;cursor:pointer;text-align:center;text-decoration:none;margin-top:12px}
.test{background:#2563eb}.back,.menuLink{background:#334155}
.menuLink{margin-top:0;margin-bottom:8px}
.menuLink.is-active{background:#2563eb;color:#fff}
.sectionDesc{color:#94a3b8;line-height:1.9;margin:0 0 16px;font-size:14px}
.placeholderList{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 0}
.placeholderList code{background:#0f172a;padding:4px 8px;border-radius:8px;font-size:12px}
.testMobileBar{background:#0f172a;border:1px solid #334155;border-radius:14px;padding:14px 16px;margin-bottom:18px}
.testMobileBar input{color:#fff}
.testMobileBar input::placeholder{color:#64748b;opacity:1}
.fieldGroup[data-provider]{display:none}
.fieldGroup.is-visible{display:block}
.panelSection{display:none}
.panelSection.is-active{display:block}
.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.actions button,.actions .test{margin-top:0}
@media(max-width:760px){
body{padding:10px}
.layout{grid-template-columns:1fr}
.menuBox{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.menuLink{margin:0;font-size:13px;padding:11px 10px}
.box{padding:18px 14px;border-radius:16px}
.actions{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php adminQuickNavStyles(); adminQuickNav('sms'); ?>

<div class="layout">
<aside class="box menuBox">
<h2 style="font-size:18px;margin-bottom:12px">منوی پیامک</h2>
<?php foreach($templateMenu as $key => $label){ ?>
<a class="menuLink <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo $h($tabUrl($key)); ?>"><?php echo $h($label); ?></a>
<?php } ?>
<a class="menuLink back" href="<?php echo $h(function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php'); ?>">بازگشت به داشبورد</a>
</aside>

<div class="box">
<h2>تنظیمات پیامک</h2>

<?php if($message !== ''){ ?><div class="msg"><?php echo $h($message); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo $h($error); ?></div><?php } ?>

<form method="post" id="smsAdminForm">
<input type="hidden" name="tab" value="<?php echo $h($tab); ?>">

<div class="testMobileBar">
<label for="test_mobile">موبایل تست <span style="color:#94a3b8;font-size:12px">(الزامی برای ارسال نمونه)</span></label>
<input type="tel" name="test_mobile" id="test_mobile" value="<?php echo $h($testMobileDisplay); ?>" placeholder="مثال: 09121234567" dir="ltr" autocomplete="tel" inputmode="tel" required>
</div>

<div class="panelSection <?php echo $tab === 'connection' ? 'is-active' : ''; ?>" data-panel="connection">
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
<label for="api_key">API Key <span style="color:#94a3b8;font-size:12px">(کلید وب‌سرویس — نه شناسه الگو)</span></label>
<input type="password" name="api_key" id="api_key" value="" placeholder="<?php echo $apiKeySaved ? '•••••••• (ذخیره شده)' : 'کلید API از پنل SMS.ir'; ?>" autocomplete="off">
<?php if($currentProvider === 'smsir'){ ?>
<p style="margin:8px 0 0;font-size:13px;color:<?php echo $apiKeySaved ? '#86efac' : '#fca5a5'; ?>">
<?php echo $apiKeySaved ? '✓ کلید API ذخیره شده' : '✗ کلید API هنوز ذخیره نشده — حتماً اینجا وارد و ذخیره کنید'; ?>
</p>
<?php } ?>
</div>

<div class="fieldGroup <?php echo $needsUserPass ? 'is-visible' : ''; ?>" data-provider="melipayamak">
<label for="username">نام کاربری پنل</label>
<input type="text" name="username" id="username" value="<?php echo $h($config['username'] ?? ''); ?>" dir="ltr">
<label for="password">رمز عبور پنل</label>
<input type="password" name="password" id="password" value="" placeholder="<?php echo trim((string)($config['password'] ?? '')) !== '' ? '•••••••• (ذخیره شده)' : 'رمز عبور'; ?>" autocomplete="off">
</div>

<label for="sender">شماره خط ارسال (Line Number)</label>
<input type="text" name="sender" id="sender" value="<?php echo $h($config['sender'] ?? ''); ?>" dir="ltr" placeholder="<?php echo $h($senderHint); ?>">
<?php if($currentProvider === 'smsir'){ ?>
<p style="margin:8px 0 0;font-size:13px;color:<?php echo $senderSaved ? '#86efac' : '#fca5a5'; ?>">
<?php echo $senderSaved ? '✓ شماره خط ذخیره شده' : '✗ شماره خط ارسال را وارد کنید (برای تست اتصال bulk لازم است)'; ?>
</p>
<?php } ?>

<div class="hint">
<p>برای <strong>SMS.ir (ایده‌پردازان)</strong>: API Key را از <a href="https://app.sms.ir/developer/list" target="_blank" rel="noopener" style="color:#93c5fd">برنامه‌نویسان → لیست کلیدهای API</a> بگیرید.</p>
<p><strong>توجه:</strong> شناسه الگو (مثل 588023) در تب‌های الگو است و با API Key فرق دارد.</p>
<p>الگوهای پیامک را از منوی کنار ویرایش کنید.</p>
</div>

<div class="actions">
<button type="submit" name="save" value="1" formnovalidate="formnovalidate">ذخیره تنظیمات</button>
<button type="submit" name="test_connection" value="1" class="test">ارسال تست اتصال</button>
</div>
</div>

<?php foreach($templateMeta as $key => $meta){
    $row = $templates[$key] ?? smsDefaultTemplates()[$key];
    $prefix = 'tpl_' . $key . '_';
?>
<div class="panelSection <?php echo $tab === $key ? 'is-active' : ''; ?>" data-panel="<?php echo $h($key); ?>">
<h3><?php echo $h($meta['title']); ?></h3>
<p class="sectionDesc"><?php echo $h($meta['desc']); ?></p>

<label class="toggle">
<input type="checkbox" name="<?php echo $h($prefix); ?>enabled" <?php echo !empty($row['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی این الگو</span>
</label>

<label for="<?php echo $h($prefix); ?>template_id">شناسه الگو در SMS.ir <?php echo $key === 'verify_mobile' ? '(الزامی برای OTP)' : '(اختیاری)'; ?></label>
<input type="text" name="<?php echo $h($prefix); ?>template_id" id="<?php echo $h($prefix); ?>template_id" value="<?php echo $h($row['template_id'] ?? ''); ?>" dir="ltr" placeholder="<?php echo $key === 'verify_mobile' ? 'مثال: 588023' : 'Template ID از پنل SMS.ir'; ?>">

<div class="hint">
<?php if($key === 'verify_mobile'){ ?>
<p>برای کد تایید، SMS.ir از API مخصوص <code>/send/verify</code> استفاده می‌کند. شناسه الگوی تأیید‌شده در پنل (مثل 588023) را اینجا بگذارید.</p>
<p>متغیر <code>#CODE#</code> در پنل SMS.ir باید با نام <code>CODE</code> تعریف شده باشد.</p>
<?php } ?>
<p>متغیرهای قابل استفاده:</p>
<div class="placeholderList">
<?php foreach($meta['placeholders'] as $ph){ ?>
<code><?php echo $h($ph); ?></code>
<?php } ?>
</div>
</div>

<label for="<?php echo $h($prefix); ?>text">متن الگو (برای نمایش و تست محلی)</label>
<textarea name="<?php echo $h($prefix); ?>text" id="<?php echo $h($prefix); ?>text"><?php echo $h($row['text'] ?? ''); ?></textarea>

<div class="actions">
<button type="submit" name="save" value="1" formnovalidate="formnovalidate">ذخیره الگو</button>
<button type="submit" name="test_template" value="<?php echo $h($key); ?>" class="test">ارسال نمونه</button>
</div>
</div>
<?php } ?>

</form>
</div>
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

    var testMobile = document.getElementById('test_mobile');
    var form = document.getElementById('smsAdminForm');

    if(form && testMobile){
        form.addEventListener('submit', function(e){
            var submitter = e.submitter;
            if(!submitter){
                return;
            }

            var isTest = submitter.name === 'test_template' || submitter.name === 'test_connection';
            if(!isTest){
                return;
            }

            var mobile = (testMobile.value || '').trim();
            if(mobile === ''){
                e.preventDefault();
                testMobile.focus();
                alert('لطفاً شماره موبایل تست را در کادر بالای صفحه وارد کنید.');
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../form_validation_fa.php'; pnvFormValidationFaScript(); ?>
<?php adminPageEnd(['active' => 'sms', 'more_mode' => 'sheet']); ?>
</body>
</html>

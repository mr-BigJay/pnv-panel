<?php

require_once __DIR__ . '/auth.php';
pnvAdminRequireAuth();

require_once __DIR__ . '/../chatwoot_lib.php';

$configFile = __DIR__ . '/../db/chatwoot.json';
$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $payload = [
        'enabled' => isset($_POST['enabled']),
        'base_url' => rtrim(trim($_POST['base_url'] ?? ''), '/'),
        'website_token' => trim($_POST['website_token'] ?? ''),
        'identity_validation_key' => trim($_POST['identity_validation_key'] ?? ''),
        'admin_url' => rtrim(trim($_POST['admin_url'] ?? ''), '/'),
        'inbox_url' => rtrim(trim($_POST['inbox_url'] ?? ''), '/')
    ];

    if(
        $payload['enabled']
        &&
        (
            $payload['base_url'] === ''
            ||
            $payload['website_token'] === ''
        )
    ){
        $error = 'برای فعال‌سازی، آدرس Chatwoot و Website Token الزامی است';
    }
    else{

        file_put_contents(
            $configFile,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        $message = 'تنظیمات Chatwoot ذخیره شد';

    }

}

$config = chatwootConfig();

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات Chatwoot</title>
<style>
body{
margin:0;
padding:16px;
background:#0f172a;
font-family:tahoma;
direction:rtl;
color:white;
}
.box{
max-width:640px;
margin:auto;
background:#1e293b;
padding:22px;
border-radius:16px;
}
h2{
margin-top:0;
}
label{
display:block;
margin:14px 0 6px;
font-size:14px;
color:#cbd5e1;
}
input[type="text"],
input[type="url"]{
width:100%;
padding:12px;
border:none;
border-radius:10px;
box-sizing:border-box;
}
.checkboxRow{
display:flex;
align-items:center;
gap:8px;
margin-top:16px;
}
button,
a.btn{
display:inline-block;
margin-top:16px;
padding:12px 18px;
border:none;
border-radius:10px;
background:#22c55e;
color:white;
text-decoration:none;
cursor:pointer;
font-family:tahoma;
}
a.gray{
background:#475569;
}
.flash{
padding:12px;
border-radius:10px;
margin-bottom:14px;
font-size:14px;
}
.ok{
background:#14532d;
color:#bbf7d0;
}
.err{
background:#450a0a;
color:#fecaca;
}
.help{
font-size:13px;
color:#94a3b8;
line-height:28px;
margin-top:18px;
}
</style>
</head>
<body>

<div class="box">

<h2>تنظیمات Chatwoot</h2>

<?php if($message){ ?>
<div class="flash ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<?php if($error){ ?>
<div class="flash err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<form method="POST">

<div class="checkboxRow">
<input type="checkbox" name="enabled" id="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<label for="enabled" style="margin:0;">فعال‌سازی Chatwoot (جایگزین مسنجر قدیمی)</label>
</div>

<label>آدرس Chatwoot (Base URL)</label>
<input type="url" name="base_url" value="<?php echo htmlspecialchars($config['base_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://panel.ticketin.ir">

<label>Website Token</label>
<input type="text" name="website_token" value="<?php echo htmlspecialchars($config['website_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

<label>Identity Validation Key (HMAC)</label>
<input type="text" name="identity_validation_key" value="<?php echo htmlspecialchars($config['identity_validation_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

<label>آدرس پنل ادمین Chatwoot</label>
<input type="url" name="admin_url" value="<?php echo htmlspecialchars($config['admin_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://panel.ticketin.ir/app">

<label>آدرس مستقیم Inbox (اختیاری)</label>
<input type="url" name="inbox_url" value="<?php echo htmlspecialchars($config['inbox_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://panel.ticketin.ir/app/accounts/1/inbox/1">

<button type="submit">ذخیره تنظیمات</button>

</form>

<div class="help">
راه‌اندازی سرور: پوشه <code>chatwoot/</code> را روی سرور اجرا کنید.<br>
بعد از نصب، در Chatwoot یک Inbox از نوع Website بسازید و Tokenها را اینجا وارد کنید.
</div>

<a href="index.php" class="btn gray">بازگشت</a>

<?php if(chatwootEnabled()){ ?>
<a href="<?php echo htmlspecialchars(chatwootAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>" class="btn" target="_blank" rel="noopener">ورود به پنل Chatwoot</a>
<?php } ?>

</div>

</body>
</html>

<?php

if(file_exists(__DIR__ . '/auth.php')){
    require_once __DIR__ . '/auth.php';
    if(function_exists('pnvAdminRequireAuth')){
        pnvAdminRequireAuth();
    }
}
else{
    session_start();

    if(!isset($_SESSION['admin'])){
        header('Location: index.php');
        exit;
    }
}

require_once __DIR__ . '/../xui_lib.php';

$config = xuiLoadConfig();
$message = '';
$error = '';

function xuiAdminH($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function xuiAdminReadPostedServers(){
    $servers = [];

    foreach(($_POST['server_id'] ?? []) as $i => $id){
        $servers[] = [
            'id' => trim((string)$id),
            'name' => trim((string)($_POST['server_name'][$i] ?? '')),
            'base_url' => rtrim(trim((string)($_POST['server_url'][$i] ?? '')), '/') . '/',
            'api_token' => trim((string)($_POST['server_token'][$i] ?? '')),
            'inbound_id' => intval($_POST['server_inbound'][$i] ?? 1),
            'host' => trim((string)($_POST['server_host'][$i] ?? '')),
            'username' => trim((string)($_POST['server_user'][$i] ?? '')),
            'password' => trim((string)($_POST['server_pass'][$i] ?? ''))
        ];
    }

    return $servers;
}

function xuiAdminReadPostedConfig(){
    $buyIds = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string)($_POST['buy_server_ids'] ?? '')))));
    $renewIds = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string)($_POST['renew_server_ids'] ?? '')))));

    return [
        'enabled' => isset($_POST['enabled']),
        'sub_port' => intval($_POST['sub_port'] ?? 2096),
        'buy_server_ids' => $buyIds,
        'renew_server_ids' => $renewIds,
        'servers' => xuiAdminReadPostedServers()
    ];
}

if(isset($_POST['save'])){
    $config = xuiAdminReadPostedConfig();
    xuiSaveConfig($config);
    $message = 'تنظیمات سرورهای 3x-ui ذخیره شد.';
}

if(isset($_POST['test_id'])){
    // اول مقادیر فرم را ذخیره کن تا تست با توکن تازه کار کند
    $config = xuiAdminReadPostedConfig();
    xuiSaveConfig($config);

    $server = xuiFindServerById(trim((string)$_POST['test_id']), $config);

    if(!$server){
        $error = 'سرور پیدا نشد';
    }
    else{
        $token = trim((string)($server['api_token'] ?? ''));

        if($token === '' || strpos($token, 'REPLACE_TOKEN_') === 0){
            $error = 'برای «' . ($server['name'] ?? $server['id']) . '» هنوز API Token واقعی وارد نشده است.';
        }
        else{
            $test = xuiTestServer($server);

            if(!empty($test['ok'])){
                $message = 'اتصال به «' . ($server['name'] ?? $server['id']) . '» موفق بود. تعداد inbound: ' . count($test['inbounds'] ?? []);
            }
            else{
                $error = 'تست ناموفق: ' . ($test['error'] ?? 'خطای نامشخص');
            }
        }
    }
}

$backUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php';
$dashboardUrl = $backUrl;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>سرورهای 3x-ui</title>
<style>
*{box-sizing:border-box}
body{
margin:0;
padding:20px;
background:#0f172a;
color:#fff;
font-family:tahoma,sans-serif;
direction:rtl;
}
.wrap{
width:100%;
max-width:820px;
margin:0 auto;
}
.topbar{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
margin-bottom:16px;
}
.topbar h1{
margin:0;
font-size:22px;
font-weight:700;
}
.topbar a{
color:#93c5fd;
text-decoration:none;
font-size:14px;
}
.box{
background:#1e293b;
border-radius:18px;
padding:22px;
margin-bottom:16px;
}
.box h2{
margin:0 0 14px;
font-size:17px;
color:#e2e8f0;
}
.msg,.err{
padding:12px 14px;
border-radius:12px;
margin-bottom:16px;
line-height:1.9;
font-size:14px;
}
.msg{background:#14532d;color:#bbf7d0}
.err{background:#7f1d1d;color:#fecaca}
.field{
margin-bottom:14px;
}
.field label{
display:block;
margin:0 0 7px;
color:#cbd5e1;
font-size:13px;
}
.field input{
width:100%;
padding:12px 14px;
border:0;
border-radius:10px;
background:#0f172a;
color:#fff;
font:inherit;
font-size:14px;
}
.field input[dir="ltr"]{
direction:ltr;
text-align:left;
}
.toggle{
display:flex;
align-items:center;
gap:10px;
margin-bottom:16px;
padding:12px 14px;
background:#0f172a;
border-radius:12px;
}
.toggle input{
width:18px;
height:18px;
margin:0;
flex:0 0 auto;
}
.toggle span{
color:#e2e8f0;
font-size:14px;
line-height:1.7;
}
.hint{
margin:0;
color:#94a3b8;
font-size:12px;
line-height:1.9;
}
.server{
background:#0f172a;
border:1px solid #334155;
border-radius:14px;
padding:16px;
margin-bottom:14px;
}
.server:last-child{margin-bottom:0}
.server-head{
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
margin-bottom:12px;
}
.server-head h3{
margin:0;
font-size:16px;
}
.badge{
font-size:11px;
padding:4px 8px;
border-radius:999px;
background:#1e293b;
color:#94a3b8;
direction:ltr;
}
.actions{
display:flex;
flex-wrap:wrap;
gap:10px;
margin-top:8px;
}
button,.btn{
appearance:none;
border:0;
border-radius:12px;
padding:13px 16px;
font:inherit;
font-size:15px;
cursor:pointer;
text-align:center;
text-decoration:none;
color:#fff;
}
.btn-save{background:#22c55e;width:100%;margin-top:8px}
.btn-test{background:#2563eb}
.btn-back{background:#334155;width:100%;margin-top:12px;display:block}
@media(max-width:640px){
body{padding:12px}
.box{padding:16px;border-radius:14px}
.topbar h1{font-size:18px}
.server-head{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>
<div class="wrap">

<div class="topbar">
<h1>سرورهای 3x-ui</h1>
<a href="<?php echo xuiAdminH($dashboardUrl); ?>">بازگشت به داشبورد</a>
</div>

<?php if($message !== ''){ ?><div class="msg"><?php echo xuiAdminH($message); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo xuiAdminH($error); ?></div><?php } ?>

<form method="post">

<div class="box">
<h2>تنظیمات کلی</h2>

<label class="toggle">
<input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی ساخت و تمدید خودکار</span>
</label>

<div class="field">
<label for="sub_port">پورت Subscription</label>
<input id="sub_port" type="number" name="sub_port" value="<?php echo (int)($config['sub_port'] ?? 2096); ?>" dir="ltr">
</div>

<div class="field">
<label for="buy_server_ids">سرورهای خرید جدید (با ویرگول)</label>
<input id="buy_server_ids" type="text" name="buy_server_ids" value="<?php echo xuiAdminH(implode(',', $config['buy_server_ids'] ?? [])); ?>" placeholder="vip,vip3,vip4" dir="ltr">
</div>

<div class="field">
<label for="renew_server_ids">سرورهای مجاز تمدید (با ویرگول)</label>
<input id="renew_server_ids" type="text" name="renew_server_ids" value="<?php echo xuiAdminH(implode(',', $config['renew_server_ids'] ?? [])); ?>" placeholder="vip,vip2,vip3,vip4" dir="ltr">
</div>

<p class="hint">خرید جدید بین سرورهای خرید می‌چرخد. تمدید همیشه روی همان سرور لینک قبلی انجام می‌شود. توکن‌ها را از Settings → Security → API Token در پنل 3x-ui بردارید.</p>
</div>

<div class="box">
<h2>سرورها</h2>

<?php foreach(($config['servers'] ?? []) as $i => $server){ ?>
<div class="server">
<div class="server-head">
<h3><?php echo xuiAdminH($server['name'] ?? $server['id'] ?? ('سرور ' . ($i + 1))); ?></h3>
<span class="badge"><?php echo xuiAdminH($server['id'] ?? ''); ?></span>
</div>

<input type="hidden" name="server_id[]" value="<?php echo xuiAdminH($server['id'] ?? ''); ?>">

<div class="field">
<label>نام نمایشی</label>
<input type="text" name="server_name[]" value="<?php echo xuiAdminH($server['name'] ?? ''); ?>">
</div>

<div class="field">
<label>Host اشتراک</label>
<input type="text" name="server_host[]" value="<?php echo xuiAdminH($server['host'] ?? ''); ?>" dir="ltr">
</div>

<div class="field">
<label>آدرس پنل 3x-ui</label>
<input type="text" name="server_url[]" value="<?php echo xuiAdminH($server['base_url'] ?? ''); ?>" dir="ltr" autocomplete="off">
</div>

<div class="field">
<label>API Token</label>
<input type="text" name="server_token[]" value="<?php echo xuiAdminH($server['api_token'] ?? ''); ?>" dir="ltr" autocomplete="off" placeholder="توکن Bearer را اینجا بگذارید">
</div>

<div class="field">
<label>Inbound ID</label>
<input type="number" name="server_inbound[]" value="<?php echo (int)($server['inbound_id'] ?? 1); ?>" dir="ltr">
</div>

<div class="field">
<label>Username (اختیاری)</label>
<input type="text" name="server_user[]" value="<?php echo xuiAdminH($server['username'] ?? ''); ?>" autocomplete="off">
</div>

<div class="field">
<label>Password (اختیاری)</label>
<input type="password" name="server_pass[]" value="<?php echo xuiAdminH($server['password'] ?? ''); ?>" autocomplete="new-password">
</div>

<div class="actions">
<button class="btn btn-test" type="submit" name="test_id" value="<?php echo xuiAdminH($server['id'] ?? ''); ?>">تست اتصال</button>
</div>
</div>
<?php } ?>
</div>

<button class="btn btn-save" type="submit" name="save" value="1">ذخیره تنظیمات</button>
</form>

<a class="btn btn-back" href="<?php echo xuiAdminH($backUrl); ?>">بازگشت به داشبورد</a>

</div>
</body>
</html>

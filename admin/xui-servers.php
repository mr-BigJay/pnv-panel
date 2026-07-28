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
                $message = 'اتصال به «' . ($server['name'] ?? $server['id']) . '» موفق بود.';
            }
            else{
                $error = 'تست ناموفق: ' . ($test['error'] ?? 'خطای نامشخص');
            }
        }
    }
}

$backUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>سرورهای 3x-ui</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
padding:20px;
background:#0f172a;
color:#fff;
font-family:tahoma,Arial,sans-serif;
direction:rtl;
line-height:1.7;
}
.box{
width:100%;
max-width:720px;
margin:0 auto;
background:#1e293b;
padding:28px 24px;
border-radius:20px;
}
h2{
margin:0 0 22px;
text-align:center;
font-size:24px;
font-weight:700;
}
h3{
margin:28px 0 12px;
padding-top:18px;
border-top:1px solid #334155;
font-size:16px;
color:#e2e8f0;
font-weight:700;
}
h3:first-of-type{
margin-top:8px;
}
label{
display:block;
width:100%;
margin:16px 0 8px;
font-size:14px;
color:#e2e8f0;
text-align:right;
}
input[type="text"],
input[type="number"],
input[type="password"]{
display:block;
width:100%;
max-width:100%;
margin:0;
padding:12px 14px;
border:0;
border-radius:12px;
background:#0f172a;
color:#fff;
font-family:inherit;
font-size:14px;
line-height:1.6;
}
.ltr{
direction:ltr;
text-align:left;
}
.toggle{
display:flex;
align-items:center;
gap:10px;
margin:0 0 8px;
cursor:pointer;
}
.toggle input{
width:18px;
height:18px;
margin:0;
flex-shrink:0;
}
.toggle span{
margin:0;
font-size:14px;
color:#e2e8f0;
}
.hint{
margin:8px 0 0;
padding:12px 14px;
background:#172554;
border-radius:12px;
color:#cbd5e1;
font-size:13px;
line-height:1.9;
}
.msg,.err{
padding:12px 14px;
border-radius:12px;
margin:0 0 16px;
font-size:14px;
line-height:1.8;
}
.msg{background:#166534}
.err{background:#991b1b}
.server-title{
margin:26px 0 4px;
padding:12px 14px;
background:#0f172a;
border-radius:12px;
font-size:15px;
font-weight:700;
color:#fff;
}
button,.back{
display:block;
width:100%;
margin-top:12px;
padding:14px;
border:0;
border-radius:12px;
background:#22c55e;
color:#fff;
font:inherit;
font-size:16px;
cursor:pointer;
text-align:center;
text-decoration:none;
}
.test{
background:#2563eb;
margin-top:14px;
}
.back{
background:#334155;
margin-top:18px;
}
@media(max-width:600px){
body{padding:10px}
.box{padding:20px 14px;border-radius:16px}
h2{font-size:20px}
}
</style>
</head>
<body>
<div class="box">

<h2>سرورهای 3x-ui</h2>

<?php if($message !== ''){ ?><div class="msg"><?php echo xuiAdminH($message); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo xuiAdminH($error); ?></div><?php } ?>

<form method="post" action="">

<label class="toggle">
<input type="checkbox" name="enabled" value="1" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی ساخت و تمدید خودکار</span>
</label>

<label for="sub_port">پورت اشتراک</label>
<input class="ltr" id="sub_port" type="number" name="sub_port" value="<?php echo (int)($config['sub_port'] ?? 2096); ?>">

<label for="buy_server_ids">سرورهای خرید جدید</label>
<input class="ltr" id="buy_server_ids" type="text" name="buy_server_ids" value="<?php echo xuiAdminH(implode(',', $config['buy_server_ids'] ?? [])); ?>" placeholder="vip,vip3,vip4">

<label for="renew_server_ids">سرورهای مجاز تمدید</label>
<input class="ltr" id="renew_server_ids" type="text" name="renew_server_ids" value="<?php echo xuiAdminH(implode(',', $config['renew_server_ids'] ?? [])); ?>" placeholder="vip,vip2,vip3,vip4">

<div class="hint">خرید جدید بین سرورهای خرید می‌چرخد. تمدید روی همان سرور لینک قبلی است. توکن را از Settings → Security → API Token کپی کنید.</div>

<?php foreach(($config['servers'] ?? []) as $i => $server){
    $sid = (string)($server['id'] ?? '');
    $sname = (string)($server['name'] ?? $sid);
?>
<div class="server-title"><?php echo xuiAdminH($sname); ?> (<?php echo xuiAdminH($sid); ?>)</div>
<input type="hidden" name="server_id[]" value="<?php echo xuiAdminH($sid); ?>">

<label>نام</label>
<input type="text" name="server_name[]" value="<?php echo xuiAdminH($server['name'] ?? ''); ?>">

<label>هاست اشتراک</label>
<input class="ltr" type="text" name="server_host[]" value="<?php echo xuiAdminH($server['host'] ?? ''); ?>">

<label>آدرس پنل</label>
<input class="ltr" type="text" name="server_url[]" value="<?php echo xuiAdminH($server['base_url'] ?? ''); ?>" autocomplete="off">

<label>API Token</label>
<input class="ltr" type="text" name="server_token[]" value="<?php echo xuiAdminH($server['api_token'] ?? ''); ?>" autocomplete="off" placeholder="REPLACE_TOKEN را با توکن واقعی عوض کنید">

<label>Inbound ID</label>
<input class="ltr" type="number" name="server_inbound[]" value="<?php echo (int)($server['inbound_id'] ?? 1); ?>">

<input type="hidden" name="server_user[]" value="<?php echo xuiAdminH($server['username'] ?? ''); ?>">
<input type="hidden" name="server_pass[]" value="<?php echo xuiAdminH($server['password'] ?? ''); ?>">

<button class="test" type="submit" name="test_id" value="<?php echo xuiAdminH($sid); ?>">تست اتصال <?php echo xuiAdminH($sname); ?></button>
<?php } ?>

<button type="submit" name="save" value="1">ذخیره تنظیمات</button>
</form>

<a class="back" href="<?php echo xuiAdminH($backUrl); ?>">بازگشت به مدیریت</a>

</div>
</body>
</html>

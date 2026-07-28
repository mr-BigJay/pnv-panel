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

if(isset($_POST['save'])){
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

    $buyIds = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string)($_POST['buy_server_ids'] ?? '')))));
    $renewIds = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string)($_POST['renew_server_ids'] ?? '')))));

    $config = [
        'enabled' => isset($_POST['enabled']),
        'sub_port' => intval($_POST['sub_port'] ?? 2096),
        'buy_server_ids' => $buyIds,
        'renew_server_ids' => $renewIds,
        'servers' => $servers
    ];

    xuiSaveConfig($config);
    $message = 'تنظیمات سرورهای 3x-ui ذخیره شد.';
}

if(isset($_POST['test_id'])){
    $config = xuiLoadConfig();
    $server = xuiFindServerById(trim((string)$_POST['test_id']), $config);

    if(!$server){
        $error = 'سرور پیدا نشد';
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

function h($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$backUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php';
?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات سرورهای 3x-ui</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{max-width:980px;margin:auto;background:#1e293b;border-radius:20px;padding:28px}
h2{margin:0 0 20px;text-align:center}
.msg,.err{padding:12px 14px;border-radius:12px;margin-bottom:16px;line-height:1.8}
.msg{background:#166534}.err{background:#991b1b}
label{display:block;margin:14px 0 8px;color:#cbd5e1}
input{width:100%;padding:12px;border:0;border-radius:10px;background:#0f172a;color:#fff}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.server{background:#0f172a;border-radius:14px;padding:16px;margin-top:18px}
.server h3{margin:0 0 10px}
.toggle{display:flex;align-items:center;gap:10px}
.toggle input{width:18px;height:18px}
button,.back{display:inline-block;width:100%;margin-top:14px;padding:14px;border:0;border-radius:12px;background:#22c55e;color:#fff;font:inherit;cursor:pointer;text-align:center;text-decoration:none}
.test{background:#2563eb;width:auto;padding:10px 16px}
.back{background:#334155;margin-top:18px}
.hint{color:#94a3b8;font-size:13px;line-height:1.8;margin-top:8px}
@media(max-width:700px){.row{grid-template-columns:1fr}body{padding:10px}.box{padding:18px}}
</style>
</head>
<body>
<div class="box">
<h2>تنظیمات سرورهای 3x-ui</h2>

<?php if($message !== ''){ ?><div class="msg"><?php echo h($message); ?></div><?php } ?>
<?php if($error !== ''){ ?><div class="err"><?php echo h($error); ?></div><?php } ?>

<form method="post">
<label class="toggle">
<input type="checkbox" name="enabled" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?>>
<span>فعال‌سازی ساخت/تمدید خودکار</span>
</label>

<label>پورت Subscription</label>
<input type="number" name="sub_port" value="<?php echo (int)($config['sub_port'] ?? 2096); ?>">

<label>سرورهای خرید جدید (با ویرگول)</label>
<input type="text" name="buy_server_ids" value="<?php echo h(implode(',', $config['buy_server_ids'] ?? [])); ?>" placeholder="vip,vip3,vip4">

<label>سرورهای مجاز تمدید (با ویرگول)</label>
<input type="text" name="renew_server_ids" value="<?php echo h(implode(',', $config['renew_server_ids'] ?? [])); ?>" placeholder="vip,vip2,vip3,vip4">
<div class="hint">خرید جدید به‌صورت چرخشی بین سرورهای خرید انجام می‌شود. تمدید همیشه روی همان سرور لینک قبلی است.</div>

<?php foreach(($config['servers'] ?? []) as $i => $server){ ?>
<div class="server">
<h3><?php echo h($server['name'] ?? $server['id'] ?? ('سرور ' . ($i+1))); ?></h3>
<input type="hidden" name="server_id[]" value="<?php echo h($server['id'] ?? ''); ?>">
<div class="row">
<div>
<label>نام</label>
<input type="text" name="server_name[]" value="<?php echo h($server['name'] ?? ''); ?>">
</div>
<div>
<label>Host اشتراک</label>
<input type="text" name="server_host[]" value="<?php echo h($server['host'] ?? ''); ?>">
</div>
</div>
<label>آدرس پنل</label>
<input type="text" name="server_url[]" value="<?php echo h($server['base_url'] ?? ''); ?>" dir="ltr">
<label>API Token</label>
<input type="text" name="server_token[]" value="<?php echo h($server['api_token'] ?? ''); ?>" dir="ltr">
<div class="row">
<div>
<label>Inbound ID</label>
<input type="number" name="server_inbound[]" value="<?php echo (int)($server['inbound_id'] ?? 1); ?>">
</div>
<div>
<label>Username (اختیاری)</label>
<input type="text" name="server_user[]" value="<?php echo h($server['username'] ?? ''); ?>">
</div>
</div>
<label>Password (اختیاری)</label>
<input type="text" name="server_pass[]" value="<?php echo h($server['password'] ?? ''); ?>">
<button class="test" type="submit" name="test_id" value="<?php echo h($server['id'] ?? ''); ?>">تست اتصال</button>
</div>
<?php } ?>

<button type="submit" name="save">ذخیره تنظیمات</button>
</form>

<a class="back" href="<?php echo h($backUrl); ?>">بازگشت</a>
</div>
</body>
</html>

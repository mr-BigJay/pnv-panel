<?php

require_once __DIR__ . '/auth.php';
pnvAdminRequireAuth();

$username = trim((string)($_GET['user'] ?? ''));

if($username === ''){
    header('Location: ' . pnvAdminUrl('users.php'));
    exit;
}

$usersFile = '../db/users.json';
$paymentsFile = '../invoices/payments.csv';

$users = [];

if(file_exists($usersFile)){
    $users = json_decode(file_get_contents($usersFile), true);
}

if(!is_array($users)){
    $users = [];
}

$userData = null;

foreach($users as $u){
    if(strtolower($u['username'] ?? '') === strtolower($username)){
        $userData = $u;
        break;
    }
}

$purchases = [];

if(file_exists($paymentsFile)){
    $f = fopen($paymentsFile, 'r');

    while(($d = fgetcsv($f)) !== false){
        if(isset($d[0]) && strtolower(trim($d[0])) === strtolower($username)){
            $purchases[] = [
                'target' => $d[1] ?? '',
                'plan' => $d[2] ?? '',
                'date' => $d[4] ?? '',
                'time' => $d[5] ?? '',
                'status' => $d[6] ?? '',
                'link' => $d[7] ?? '',
                'type' => $d[9] ?? ''
            ];
        }
    }

    fclose($f);
}

usort($purchases, function($a, $b){
    return strcmp($b['date'] . ' ' . $b['time'], $a['date'] . ' ' . $a['time']);
});

$page = max(1, intval($_GET['p'] ?? 1));
$perPage = 10;
$total = count($purchases);
$totalPages = max(1, (int)ceil($total / $perPage));

if($page > $totalPages){
    $page = $totalPages;
}

$purchasesPage = array_slice($purchases, ($page - 1) * $perPage, $perPage);

function upH($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پروفایل <?php echo upH($username); ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:16px;background:#0f172a;color:#fff;font-family:tahoma;direction:rtl}
.box{max-width:860px;margin:0 auto;background:#1e293b;border-radius:16px;padding:20px}
h2{margin:0 0 16px;text-align:center}
.info{background:#0f172a;border-radius:12px;padding:14px;line-height:2;margin-bottom:16px}
.card{background:#0f172a;border-radius:12px;padding:14px;margin-bottom:10px}
.meta{color:#94a3b8;font-size:13px;margin-top:6px}
.linkRow{display:flex;gap:8px;margin-top:10px}
.linkRow input{flex:1;padding:10px;border:0;border-radius:8px;background:#1e293b;color:#fff;direction:ltr;text-align:left}
.linkRow button,.back{border:0;border-radius:8px;padding:10px 14px;background:#2563eb;color:#fff;cursor:pointer;font:inherit}
.back{display:inline-block;margin-top:14px;background:#334155;text-decoration:none}
.pager{display:flex;flex-wrap:wrap;gap:6px;margin-top:14px}
.pager a{padding:8px 12px;border-radius:8px;background:#0f172a;color:#fff;text-decoration:none}
.pager a.active{background:#22c55e}
.empty{color:#94a3b8;text-align:center;padding:20px}
.status{display:inline-block;padding:3px 8px;border-radius:8px;font-size:12px;background:#334155}
</style>
</head>
<body>
<div class="box">
<h2>پروفایل کاربر</h2>

<div class="info">
<div><b>نام کاربری:</b> <?php echo upH($username); ?></div>
<div><b>موبایل:</b> <?php echo upH($userData['mobile'] ?? '-'); ?></div>
<div><b>معرف:</b> <?php echo upH($userData['referrer'] ?? '-'); ?></div>
<div><b>کد معرف:</b> <?php echo upH($userData['referral_code'] ?? '-'); ?></div>
<div><b>تعداد رکورد پرداخت:</b> <?php echo (int)$total; ?></div>
</div>

<h3 style="margin:0 0 12px">اشتراک‌ها / پرداخت‌ها</h3>

<?php if(count($purchasesPage) === 0){ ?>
<div class="empty">موردی یافت نشد</div>
<?php } ?>

<?php foreach($purchasesPage as $sub){ ?>
<div class="card">
<div><b><?php echo upH($sub['plan'] !== '' ? $sub['plan'] : $sub['target']); ?></b></div>
<div class="meta">
<?php echo upH($sub['type'] !== '' ? $sub['type'] : 'خرید'); ?>
|
<span class="status"><?php echo upH($sub['status']); ?></span>
|
<?php echo upH(trim($sub['date'] . ' ' . $sub['time'])); ?>
</div>
<?php
$showLink = trim($sub['link']);
if($showLink === '' || $showLink === 'رد شد'){
    $showLink = $sub['target'];
}
?>
<?php if($showLink !== ''){ ?>
<div class="linkRow">
<input type="text" readonly value="<?php echo upH($showLink); ?>">
<button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">کپی</button>
</div>
<?php } ?>
</div>
<?php } ?>

<?php if($totalPages > 1){ ?>
<div class="pager">
<?php for($i = 1; $i <= $totalPages; $i++){ ?>
<a class="<?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo upH(pnvAdminUrl('user-profile.php?user=' . urlencode($username) . '&p=' . $i)); ?>"><?php echo $i; ?></a>
<?php } ?>
</div>
<?php } ?>

<a class="back" href="<?php echo upH(pnvAdminUrl('users.php')); ?>">بازگشت به کاربران</a>
</div>
</body>
</html>

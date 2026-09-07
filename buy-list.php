<?php

require_once __DIR__ . '/pnv_date_bootstrap.php';
require_once __DIR__ . '/payment_list_ui.php';
require_once __DIR__ . '/subscription_lib.php';

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$payments = [];

if(file_exists('invoices/payments.csv')){
    $f = fopen('invoices/payments.csv', 'r');

    while(($d = fgetcsv($f)) !== false){
        $payments[] = $d;
    }

    fclose($f);
}

function buyListStatusColor($status){
    return paymentListStatusColor($status);
}

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لیست خریدها</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:10px;background:#0f172a;font-family:tahoma;direction:rtl;color:#fff}
.box{width:100%;max-width:760px;margin:auto}
.card{background:#1e293b;padding:16px;border-radius:16px;margin-bottom:14px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.item{background:#0f172a;padding:10px;border-radius:8px}
.label{font-size:11px;color:#94a3b8;margin-bottom:4px}
.value{font-size:14px;line-height:24px;word-break:break-word}
.status{padding:6px 10px;border-radius:8px;display:inline-block;font-size:13px;margin-top:4px}
.info{margin-top:12px;padding:12px;border-radius:10px;background:#0f172a;line-height:24px;font-size:13px;word-break:break-word}
.empty{background:#1e293b;padding:20px;border-radius:16px;text-align:center;font-size:15px;line-height:28px}
<?php echo paymentListTabsCss(); ?>
@media(min-width:768px){body{padding:18px}.box{max-width:920px}.card{padding:20px}}
</style>
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="fonts.css">
</head>
<body>
<div class="box">
<?php
require_once __DIR__ . '/user_nav.php';
userBackBar('dashboard.php', 'لیست خریدها');
$activeTab = paymentListActiveTab('pending');
paymentListRenderTabs('buy-list.php', $activeTab);
$found = false;

foreach(array_reverse($payments) as $p){
    if(($p[0] ?? '') != $user){
        continue;
    }

    $type = trim((string)($p[9] ?? ''));

    if(!pnvPaymentRowIsBuy($type)){
        continue;
    }

    $listTab = paymentListUserDisplayTab($p);

    if($listTab === null || $listTab !== $activeTab){
        continue;
    }

    $found = true;
    $status = trim((string)($p[6] ?? ''));

    if($status === ''){
        $status = 'درحال بررسی';
    }

    $payWhen = pnvFormatPaymentRowDateTime($p);
    $infoText = paymentListInfoText($status, 'buy');

    if($status === 'رد شد' && trim((string)($p[7] ?? '')) !== ''){
        $infoText = trim((string)$p[7]);
    }
?>
<div class="card">
<div class="grid">
<div class="item">
<div class="label">نام کانفیگ</div>
<div class="value"><?php echo htmlspecialchars($p[1] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
</div>
<div class="item">
<div class="label">پلن</div>
<div class="value"><?php echo htmlspecialchars($p[2] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
</div>
<div class="item">
<div class="label">شماره پیگیری</div>
<div class="value"><?php echo htmlspecialchars($p[3] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
</div>
<div class="item">
<div class="label">تاریخ</div>
<div class="value"><?php echo htmlspecialchars($payWhen['date'], ENT_QUOTES, 'UTF-8'); ?></div>
</div>
<div class="item">
<div class="label">ساعت</div>
<div class="value"><?php echo htmlspecialchars($payWhen['time'], ENT_QUOTES, 'UTF-8'); ?></div>
</div>
<div class="item">
<div class="label">وضعیت</div>
<div class="value"><span class="status" style="background:<?php echo buyListStatusColor($status); ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></div>
</div>
</div>
<?php if($infoText !== ''){ ?>
<div class="info"><?php echo htmlspecialchars($infoText, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
</div>
<?php } ?>

<?php if(!$found){ ?>
<div class="empty">در این بخش درخواستی ثبت نشده است</div>
<?php } ?>
</div>
</body>
</html>

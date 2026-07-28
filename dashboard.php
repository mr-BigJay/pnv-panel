<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$supportFile = 'db/support.json';
$paymentsFile = 'invoices/payments.csv';
$hasUnreadSupport = false;
$approvedSubs = 0;
$pendingBuys = 0;
$pendingRenews = 0;

if(file_exists($supportFile)){
    $supportData = json_decode(file_get_contents($supportFile), true);

    if(is_array($supportData)){
        foreach($supportData as $ticket){
            if(($ticket['user'] ?? '') !== $user){
                continue;
            }

            foreach(($ticket['messages'] ?? []) as $msg){
                if(($msg['sender'] ?? '') === 'admin' && empty($msg['seen_by_user'])){
                    $hasUnreadSupport = true;
                    break 2;
                }
            }
        }
    }
}

if(file_exists($paymentsFile)){
    $handle = fopen($paymentsFile, 'r');

    while(($row = fgetcsv($handle)) !== false){
        if(($row[0] ?? '') !== $user){
            continue;
        }

        $status = trim((string)($row[6] ?? ''));
        $type = trim((string)($row[9] ?? ''));
        $link = trim((string)($row[7] ?? ''));

        if($status === 'تایید شد' && $type === 'خرید' && $link !== ''){
            $approvedSubs++;
        }

        if($status !== 'تایید شد' && $status !== 'رد شد'){
            if($type === 'تمدید'){
                $pendingRenews++;
            }
            else{
                $pendingBuys++;
            }
        }
    }

    fclose($handle);
}

function dashH($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>داشبورد کاربر</title>
<link rel="stylesheet" href="user_panel.css?v=2">
<style>
.dashPage{
animation:dashIn .35s ease;
}
@keyframes dashIn{
from{opacity:0;transform:translateY(8px)}
to{opacity:1;transform:none}
}
.dashWelcome{
background:#0f172a;
border:1px solid #334155;
border-radius:18px;
padding:18px 16px;
margin-bottom:18px;
}
.dashHello{
margin:0 0 6px;
font-size:14px;
color:#94a3b8;
}
.dashUser{
margin:0 0 14px;
font-size:22px;
font-weight:700;
word-break:break-word;
}
.dashStats{
display:grid;
grid-template-columns:repeat(3,minmax(0,1fr));
gap:8px;
}
.dashStat{
background:#1e293b;
border-radius:12px;
padding:10px 8px;
text-align:center;
}
.dashStatNum{
font-size:18px;
font-weight:700;
color:#22c55e;
line-height:1.2;
}
.dashStatLabel{
margin-top:4px;
font-size:11px;
color:#94a3b8;
line-height:1.5;
}
.dashSection{
margin-bottom:18px;
}
.dashSectionTitle{
margin:0 0 10px;
font-size:13px;
color:#94a3b8;
font-weight:700;
letter-spacing:.2px;
}
.dashPrimaryGrid{
display:grid;
grid-template-columns:1fr 1fr;
gap:10px;
}
.dashPrimary{
display:flex;
flex-direction:column;
justify-content:center;
gap:8px;
min-height:118px;
padding:16px;
border-radius:16px;
text-decoration:none;
color:#fff;
background:linear-gradient(180deg,#1f3a2c 0%,#163325 100%);
border:1px solid #166534;
transition:transform .15s ease, border-color .15s ease, background .15s ease;
}
.dashPrimary:hover{
transform:translateY(-2px);
border-color:#22c55e;
}
.dashPrimary--renew{
background:linear-gradient(180deg,#1e3a5f 0%,#172554 100%);
border-color:#1d4ed8;
}
.dashPrimary--renew:hover{
border-color:#3b82f6;
}
.dashPrimaryIcon{
width:36px;
height:36px;
border-radius:10px;
display:flex;
align-items:center;
justify-content:center;
background:rgba(34,197,94,.18);
color:#86efac;
font-size:18px;
font-weight:700;
}
.dashPrimary--renew .dashPrimaryIcon{
background:rgba(59,130,246,.18);
color:#93c5fd;
}
.dashPrimaryLabel{
font-size:16px;
font-weight:700;
line-height:1.5;
}
.dashPrimaryHint{
font-size:12px;
color:#cbd5e1;
line-height:1.6;
}
.dashList{
background:#0f172a;
border:1px solid #334155;
border-radius:16px;
overflow:hidden;
}
.dashItem{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
padding:14px 14px;
text-decoration:none;
color:#fff;
border-bottom:1px solid #1e293b;
transition:background .15s ease;
position:relative;
}
.dashItem:last-child{
border-bottom:0;
}
.dashItem:hover{
background:#1e293b;
}
.dashItemMain{
display:flex;
align-items:center;
gap:12px;
min-width:0;
}
.dashItemIcon{
width:34px;
height:34px;
border-radius:10px;
flex:0 0 auto;
display:flex;
align-items:center;
justify-content:center;
background:#1e293b;
color:#93c5fd;
font-size:14px;
font-weight:700;
}
.dashItemText{
font-size:15px;
font-weight:700;
line-height:1.5;
}
.dashItemMeta{
font-size:12px;
color:#64748b;
}
.dashLogout{
display:block;
margin-top:4px;
padding:14px;
border-radius:14px;
background:#7f1d1d;
border:1px solid #dc2626;
color:#fff;
text-align:center;
text-decoration:none;
font-size:16px;
font-weight:700;
transition:background .15s ease;
}
.dashLogout:hover{
background:#991b1b;
}
.dashNotif{
position:absolute;
top:14px;
left:14px;
width:10px;
height:10px;
border-radius:50%;
background:#ef4444;
box-shadow:0 0 10px rgba(239,68,68,.7);
animation:userPanelPulse 1.5s infinite;
}
@media(max-width:480px){
.dashPrimaryGrid{grid-template-columns:1fr}
.dashStats{grid-template-columns:1fr 1fr 1fr}
.dashStatLabel{font-size:10px}
.dashUser{font-size:20px}
}
</style>
</head>
<body class="userPanel userPanel--dashboard">

<div class="userPanelWrap dashPage">
<div class="userPanelBox">

<h1 class="userPanelTitle">پنل کاربری</h1>

<div class="dashWelcome">
<p class="dashHello">خوش آمدید</p>
<p class="dashUser"><?php echo dashH($user); ?></p>
<div class="dashStats">
<div class="dashStat">
<div class="dashStatNum"><?php echo (int)$approvedSubs; ?></div>
<div class="dashStatLabel">اشتراک فعال</div>
</div>
<div class="dashStat">
<div class="dashStatNum"><?php echo (int)$pendingBuys; ?></div>
<div class="dashStatLabel">خرید در انتظار</div>
</div>
<div class="dashStat">
<div class="dashStatNum"><?php echo (int)$pendingRenews; ?></div>
<div class="dashStatLabel">تمدید در انتظار</div>
</div>
</div>
</div>

<section class="dashSection">
<h2 class="dashSectionTitle">عملیات اصلی</h2>
<div class="dashPrimaryGrid">
<a class="dashPrimary" href="buy.php">
<span class="dashPrimaryIcon">+</span>
<span class="dashPrimaryLabel">خرید اشتراک جدید</span>
<span class="dashPrimaryHint">ثبت رسید و دریافت کانفیگ</span>
</a>
<a class="dashPrimary dashPrimary--renew" href="renew.php">
<span class="dashPrimaryIcon">↻</span>
<span class="dashPrimaryLabel">تمدید اشتراک</span>
<span class="dashPrimaryHint">افزایش حجم همان لینک</span>
</a>
</div>
</section>

<section class="dashSection">
<h2 class="dashSectionTitle">مدیریت</h2>
<div class="dashList">
<a class="dashItem" href="subscriptions.php">
<span class="dashItemMain">
<span class="dashItemIcon">≡</span>
<span class="dashItemText">لیست اشتراک‌ها</span>
</span>
<span class="dashItemMeta">مشاهده</span>
</a>
<a class="dashItem" href="renew-list.php">
<span class="dashItemMain">
<span class="dashItemIcon">↻</span>
<span class="dashItemText">لیست تمدیدها</span>
</span>
<span class="dashItemMeta">پیگیری</span>
</a>
<a class="dashItem" href="downloads.php">
<span class="dashItemMain">
<span class="dashItemIcon">↓</span>
<span class="dashItemText">دانلود نرم‌افزارها</span>
</span>
<span class="dashItemMeta">اپ‌ها</span>
</a>
<a class="dashItem" href="coupon.php">
<span class="dashItemMain">
<span class="dashItemIcon">%</span>
<span class="dashItemText">کوپن تخفیف</span>
</span>
<span class="dashItemMeta">دعوت دوستان</span>
</a>
<a class="dashItem" href="support.php">
<?php if($hasUnreadSupport){ ?><span class="dashNotif"></span><?php } ?>
<span class="dashItemMain">
<span class="dashItemIcon">✉</span>
<span class="dashItemText">پیام به پشتیبانی</span>
</span>
<span class="dashItemMeta"><?php echo $hasUnreadSupport ? 'پیام جدید' : 'گفتگو'; ?></span>
</a>
</div>
</section>

<a class="dashLogout" href="logout.php">خروج</a>

</div>
</div>

</body>
</html>

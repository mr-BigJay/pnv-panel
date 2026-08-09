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

function dashUserInitial($username){

    $username = trim((string)$username);

    if($username === ''){
        return '?';
    }

    if(function_exists('mb_substr')){
        return mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($username, 0, 1));

}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>داشبورد کاربر</title>
<link rel="stylesheet" href="user_panel.css?v=6">
<style>
html,body{
height:100%;
overflow:hidden;
}
body.userPanel--dashboard{
min-height:100dvh;
height:100dvh;
align-items:stretch !important;
padding:10px !important;
overflow:hidden;
}
.dashPage{
height:100%;
display:flex;
flex-direction:column;
animation:dashIn .3s ease;
}
@keyframes dashIn{
from{opacity:0;transform:translateY(6px)}
to{opacity:1;transform:none}
}
.dashPage .userPanelBox{
flex:1;
display:flex;
flex-direction:column;
min-height:0;
padding:14px 12px !important;
border-radius:18px !important;
overflow:hidden;
}
.dashTopBar{
position:relative;
display:flex;
align-items:center;
justify-content:center;
margin-bottom:8px;
flex:0 0 auto;
min-height:34px;
}
.dashTopBar .userPanelTitle{
margin:0 !important;
font-size:18px !important;
}
.dashMoreWrap{
position:absolute;
left:0;
top:50%;
transform:translateY(-50%);
z-index:5;
}
.dashMoreBtn{
width:34px;
height:34px;
border:none;
border-radius:10px;
background:#334155;
color:#e2e8f0;
font-size:20px;
line-height:1;
cursor:pointer;
padding:0;
display:flex;
align-items:center;
justify-content:center;
}
.dashMoreMenu{
display:none;
position:absolute;
left:0;
top:calc(100% + 6px);
min-width:148px;
background:#0f172a;
border:1px solid #334155;
border-radius:12px;
padding:6px;
box-shadow:0 12px 28px rgba(0,0,0,.45);
}
.dashMoreMenu.is-open{
display:block;
}
.dashMoreMenu button,
.dashMoreMenu a{
display:block;
width:100%;
padding:10px 12px;
border:none;
border-radius:8px;
background:transparent;
color:#fff;
text-decoration:none;
font-family:inherit;
font-size:13px;
text-align:right;
cursor:pointer;
}
.dashMoreMenu button:hover,
.dashMoreMenu a:hover{
background:#1e293b;
}
.dashGrowZone{
flex:1;
min-height:0;
display:flex;
flex-direction:column;
gap:8px;
}
.dashWelcome{
background:#0f172a;
border:1px solid #334155;
border-radius:14px;
padding:14px 12px;
flex:3 1 0;
min-height:96px;
display:flex;
flex-direction:column;
justify-content:center;
}
.dashWelcomeRow{
display:flex;
align-items:center;
gap:12px;
margin-bottom:10px;
}
.dashAvatarWrap{
position:relative;
flex:0 0 auto;
}
.dashAvatar{
width:52px;
height:52px;
border-radius:50%;
display:flex;
align-items:center;
justify-content:center;
font-size:22px;
font-weight:700;
color:#fff;
background:linear-gradient(135deg,#22c55e 0%,#2563eb 100%);
box-shadow:0 6px 16px rgba(37,99,235,.28);
}
.dashAvatarCam{
position:absolute;
left:-2px;
bottom:-2px;
width:22px;
height:22px;
border:none;
border-radius:50%;
background:#334155;
border:2px solid #0f172a;
color:#cbd5e1;
font-size:11px;
line-height:1;
cursor:pointer;
padding:0;
display:flex;
align-items:center;
justify-content:center;
}
.dashWelcomeText{
min-width:0;
flex:1;
}
.dashHello{
margin:0 0 2px;
font-size:12px;
color:#94a3b8;
}
.dashUser{
margin:0;
font-size:18px;
font-weight:700;
word-break:break-word;
line-height:1.3;
}
.dashStats{
display:grid;
grid-template-columns:repeat(3,minmax(0,1fr));
gap:6px;
}
.dashStat{
background:#1e293b;
border-radius:10px;
padding:8px 6px;
text-align:center;
}
.dashStatNum{
font-size:16px;
font-weight:700;
color:#22c55e;
line-height:1.1;
}
.dashStatLabel{
margin-top:3px;
font-size:10px;
color:#94a3b8;
line-height:1.4;
}
.dashPrimaryGrid{
display:grid;
grid-template-columns:1fr 1fr;
gap:8px;
flex:0 0 auto;
width:min(100%, 168px);
margin:0 auto;
}
.dashPrimary{
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
gap:5px;
aspect-ratio:1 / 1;
height:auto;
width:100%;
padding:8px 6px;
border-radius:14px;
text-decoration:none;
color:#fff;
text-align:center;
background:linear-gradient(180deg,#1f3a2c 0%,#163325 100%);
border:1px solid #166534;
box-sizing:border-box;
}
.dashPrimary--renew{
background:linear-gradient(180deg,#1e3a5f 0%,#172554 100%);
border-color:#1d4ed8;
}
.dashPrimaryIcon{
width:30px;
height:30px;
border-radius:8px;
display:flex;
align-items:center;
justify-content:center;
background:rgba(34,197,94,.18);
color:#86efac;
font-size:18px;
font-weight:700;
flex:0 0 auto;
line-height:1;
}
.dashPrimary--renew .dashPrimaryIcon{
background:rgba(59,130,246,.18);
color:#93c5fd;
}
.dashPrimaryLabel{
font-size:11px;
font-weight:700;
line-height:1.3;
}
.dashList{
background:#0f172a;
border:1px solid #334155;
border-radius:14px;
overflow:hidden;
flex:1 1 0;
min-height:0;
display:flex;
flex-direction:column;
justify-content:stretch;
}
.dashItem{
display:flex;
align-items:center;
justify-content:center;
gap:8px;
padding:0 12px;
flex:1;
min-height:44px;
text-decoration:none;
color:#fff;
border-bottom:1px solid #1e293b;
position:relative;
}
.dashItem:last-child{border-bottom:0}
.dashItemMain{
display:flex;
align-items:center;
justify-content:center;
gap:10px;
min-width:0;
}
.dashItemIcon{
width:28px;
height:28px;
border-radius:8px;
flex:0 0 auto;
display:flex;
align-items:center;
justify-content:center;
background:#1e293b;
color:#93c5fd;
font-size:13px;
font-weight:700;
position:relative;
}
.dashItemText{
font-size:13px;
font-weight:700;
line-height:1.3;
text-align:center;
}
.dashLogout{
display:block;
margin-top:8px;
padding:11px;
border-radius:12px;
background:#7f1d1d;
border:1px solid #dc2626;
color:#fff;
text-align:center;
text-decoration:none;
font-size:14px;
font-weight:700;
flex:0 0 auto;
}
.dashNotif{
position:absolute;
top:-2px;
left:-2px;
width:8px;
height:8px;
border-radius:50%;
background:#ef4444;
box-shadow:0 0 8px rgba(239,68,68,.7);
}
@media(max-width:360px){
.dashPrimaryLabel{font-size:10px}
.dashItemText{font-size:12px}
.dashStatLabel{font-size:9px}
.dashAvatar{width:46px;height:46px;font-size:19px}
}
</style>
</head>
<body class="userPanel userPanel--dashboard">

<div class="userPanelWrap dashPage">
<div class="userPanelBox">

<div class="dashTopBar">
<h1 class="userPanelTitle">پنل کاربری</h1>
<div class="dashMoreWrap">
<button type="button" class="dashMoreBtn" id="dashMoreBtn" aria-label="منو">⋮</button>
<div class="dashMoreMenu" id="dashMoreMenu">
<button type="button" id="dashEditProfileBtn">ویرایش پروفایل</button>
</div>
</div>
</div>

<div class="dashGrowZone">

<div class="dashWelcome">
<div class="dashWelcomeRow">
<div class="dashAvatarWrap">
<div class="dashAvatar"><?php echo dashH(dashUserInitial($user)); ?></div>
<button type="button" class="dashAvatarCam" id="dashAvatarCamBtn" title="تغییر عکس پروفایل" aria-label="تغییر عکس پروفایل">📷</button>
</div>
<div class="dashWelcomeText">
<p class="dashHello">خوش آمدید</p>
<p class="dashUser"><?php echo dashH($user); ?></p>
</div>
</div>
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

<div class="dashPrimaryGrid">
<a class="dashPrimary" href="buy.php">
<span class="dashPrimaryIcon">+</span>
<span class="dashPrimaryLabel">خرید اشتراک جدید</span>
</a>
<a class="dashPrimary dashPrimary--renew" href="renew.php">
<span class="dashPrimaryIcon">↻</span>
<span class="dashPrimaryLabel">تمدید اشتراک</span>
</a>
</div>

<div class="dashList">
<a class="dashItem" href="subscriptions.php">
<span class="dashItemMain">
<span class="dashItemIcon">≡</span>
<span class="dashItemText">لیست اشتراک‌ها</span>
</span>
</a>
<a class="dashItem" href="renew-list.php">
<span class="dashItemMain">
<span class="dashItemIcon">↻</span>
<span class="dashItemText">لیست تمدیدها</span>
</span>
</a>
<a class="dashItem" href="downloads.php">
<span class="dashItemMain">
<span class="dashItemIcon">↓</span>
<span class="dashItemText">دانلود نرم‌افزارها</span>
</span>
</a>
<a class="dashItem" href="coupon.php">
<span class="dashItemMain">
<span class="dashItemIcon">%</span>
<span class="dashItemText">کوپن تخفیف</span>
</span>
</a>
<a class="dashItem" href="support.php">
<span class="dashItemMain">
<span class="dashItemIcon">✉<?php if($hasUnreadSupport){ ?><span class="dashNotif"></span><?php } ?></span>
<span class="dashItemText">پیام به پشتیبانی</span>
</span>
</a>
</div>

</div>

<a class="dashLogout" href="logout.php">خروج</a>

</div>
</div>

<script>
(function(){
    var moreBtn = document.getElementById('dashMoreBtn');
    var moreMenu = document.getElementById('dashMoreMenu');
    var editBtn = document.getElementById('dashEditProfileBtn');
    var camBtn = document.getElementById('dashAvatarCamBtn');

    function closeMenu(){
        if(moreMenu){
            moreMenu.classList.remove('is-open');
        }
    }

    if(moreBtn && moreMenu){
        moreBtn.addEventListener('click', function(e){
            e.stopPropagation();
            moreMenu.classList.toggle('is-open');
        });

        document.addEventListener('click', function(){
            closeMenu();
        });

        moreMenu.addEventListener('click', function(e){
            e.stopPropagation();
        });
    }

    function showSoon(){
        alert('ویرایش پروفایل به زودی فعال می‌شود.');
        closeMenu();
    }

    if(editBtn){
        editBtn.addEventListener('click', showSoon);
    }

    if(camBtn){
        camBtn.addEventListener('click', showSoon);
    }
})();
</script>

</body>
</html>

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
<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="user_bg.css?v=4">
<style>
html,body{
height:100%;
overflow:hidden;
}
body.userPanel--dashboard{
margin:0;
min-height:100dvh;
height:100dvh;
padding:14px 12px !important;
padding-top:max(14px, env(safe-area-inset-top)) !important;
padding-bottom:max(14px, env(safe-area-inset-bottom)) !important;
display:flex;
flex-direction:column;
align-items:stretch;
overflow:hidden;
box-sizing:border-box;
}
.dashPage{
flex:1;
min-height:0;
display:flex;
flex-direction:column;
max-width:430px;
width:100%;
margin:0 auto;
animation:dashIn .3s ease;
}
@keyframes dashIn{
from{opacity:0;transform:translateY(6px)}
to{opacity:1;transform:none}
}
.dashTitle{
margin:0 0 12px;
text-align:center;
font-family:"Lalezar",tahoma,sans-serif;
font-size:20px;
font-weight:400;
color:#fff;
line-height:1.4;
flex:0 0 auto;
}
.dashShell{
flex:1;
min-height:0;
display:flex;
flex-direction:column;
gap:10px;
background:rgba(18,24,32,.72);
border:1px solid rgba(148,163,184,.14);
border-radius:24px;
padding:14px 12px;
box-shadow:0 20px 48px rgba(0,0,0,.28);
backdrop-filter:blur(10px);
-webkit-backdrop-filter:blur(10px);
overflow:hidden;
}
.dashWelcome{
position:relative;
flex:0 0 auto;
padding-left:36px;
}
.dashMoreWrap{
position:absolute;
left:0;
top:0;
z-index:5;
}
.dashMoreBtn{
width:30px;
height:30px;
border:none;
border-radius:8px;
background:rgba(15,23,42,.85);
border:1px solid rgba(148,163,184,.2);
color:#e2e8f0;
font-size:18px;
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
background:rgba(15,23,42,.96);
border:1px solid rgba(148,163,184,.2);
border-radius:12px;
padding:6px;
box-shadow:0 12px 28px rgba(0,0,0,.45);
}
.dashMoreMenu.is-open{display:block}
.dashMoreMenu button{
display:block;
width:100%;
padding:10px 12px;
border:none;
border-radius:8px;
background:transparent;
color:#fff;
font-family:inherit;
font-size:13px;
text-align:right;
cursor:pointer;
}
.dashMoreMenu button:hover{background:rgba(30,41,59,.8)}
.dashWelcomeRow{
display:flex;
align-items:flex-start;
gap:12px;
}
.dashAvatar{
width:48px;
height:48px;
border-radius:50%;
flex:0 0 auto;
display:flex;
align-items:center;
justify-content:center;
font-size:20px;
font-weight:700;
color:#fff;
background:linear-gradient(135deg,#22c55e 0%,#2563eb 100%);
box-shadow:0 4px 14px rgba(37,99,235,.25);
}
.dashWelcomeText{
min-width:0;
flex:1;
}
.dashHello{
margin:0 0 2px;
font-size:11px;
color:#94a3b8;
}
.dashUser{
margin:0 0 8px;
font-size:17px;
font-weight:700;
word-break:break-word;
line-height:1.3;
}
.dashStatsInline{
display:flex;
flex-wrap:wrap;
gap:6px;
}
.dashChip{
display:inline-flex;
align-items:center;
gap:4px;
padding:4px 8px;
border-radius:999px;
background:rgba(30,41,59,.75);
border:1px solid rgba(148,163,184,.12);
font-size:10px;
color:#94a3b8;
line-height:1.3;
white-space:nowrap;
}
.dashChip b{
color:#4ade80;
font-size:12px;
font-weight:700;
}
.dashPrimaryGrid{
display:grid;
grid-template-columns:1fr 1fr;
gap:10px;
flex:0 0 auto;
}
.dashPrimary{
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
gap:10px;
aspect-ratio:1;
min-height:118px;
padding:12px 8px;
border-radius:18px;
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
width:44px;
height:44px;
border-radius:12px;
display:flex;
align-items:center;
justify-content:center;
background:rgba(34,197,94,.18);
color:#86efac;
font-size:24px;
font-weight:700;
line-height:1;
}
.dashPrimary--renew .dashPrimaryIcon{
background:rgba(59,130,246,.18);
color:#93c5fd;
}
.dashPrimaryLabel{
font-size:12px;
font-weight:700;
line-height:1.35;
}
.dashList{
flex:1;
min-height:0;
display:flex;
flex-direction:column;
border-radius:14px;
overflow:hidden;
background:rgba(15,23,42,.55);
border:1px solid rgba(148,163,184,.1);
}
.dashItem{
display:flex;
align-items:center;
justify-content:space-between;
gap:8px;
padding:0 12px;
flex:1;
min-height:44px;
text-decoration:none;
color:#fff;
border-bottom:1px solid rgba(30,41,59,.8);
position:relative;
}
.dashItem:last-child{border-bottom:0}
.dashItemMain{
display:flex;
align-items:center;
gap:10px;
min-width:0;
}
.dashItemIcon{
width:26px;
height:26px;
border-radius:8px;
flex:0 0 auto;
display:flex;
align-items:center;
justify-content:center;
background:rgba(30,41,59,.9);
color:#93c5fd;
font-size:12px;
font-weight:700;
}
.dashItemText{
font-size:13px;
font-weight:600;
line-height:1.3;
}
.dashItemChevron{
color:#64748b;
font-size:14px;
flex:0 0 auto;
line-height:1;
}
.dashLogout{
display:block;
margin-top:10px;
padding:12px;
border-radius:14px;
background:rgba(127,29,29,.85);
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
top:50%;
left:28px;
transform:translateY(-50%);
width:7px;
height:7px;
border-radius:50%;
background:#ef4444;
box-shadow:0 0 8px rgba(239,68,68,.7);
}
@media(max-width:360px){
.dashPrimary{min-height:108px}
.dashPrimaryIcon{width:38px;height:38px;font-size:20px}
.dashPrimaryLabel{font-size:11px}
.dashAvatar{width:42px;height:42px;font-size:18px}
.dashChip{font-size:9px}
.dashChip b{font-size:11px}
}
</style>
</head>
<body class="userPanel userPanel--dashboard">

<div class="dashPage">

<h1 class="dashTitle">پنل کاربری</h1>

<div class="dashShell">

<div class="dashWelcome">
<div class="dashMoreWrap">
<button type="button" class="dashMoreBtn" id="dashMoreBtn" aria-label="منو">⋮</button>
<div class="dashMoreMenu" id="dashMoreMenu">
<button type="button" id="dashEditAvatarBtn">ویرایش عکس پروفایل</button>
<button type="button" id="dashEditUsernameBtn">تغییر نام کاربری</button>
</div>
</div>
<div class="dashWelcomeRow">
<div class="dashAvatar"><?php echo dashH(dashUserInitial($user)); ?></div>
<div class="dashWelcomeText">
<p class="dashHello">خوش آمدید</p>
<p class="dashUser"><?php echo dashH($user); ?></p>
<div class="dashStatsInline">
<span class="dashChip"><b><?php echo (int)$approvedSubs; ?></b> اشتراک فعال</span>
<span class="dashChip"><b><?php echo (int)$pendingBuys; ?></b> خرید در انتظار</span>
<span class="dashChip"><b><?php echo (int)$pendingRenews; ?></b> تمدید در انتظار</span>
</div>
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
<span class="dashItemText">اشتراک‌های من</span>
</span>
<span class="dashItemChevron" aria-hidden="true">‹</span>
</a>
<a class="dashItem" href="downloads.php">
<span class="dashItemMain">
<span class="dashItemIcon">↓</span>
<span class="dashItemText">دانلود نرم‌افزارها</span>
</span>
<span class="dashItemChevron" aria-hidden="true">‹</span>
</a>
<a class="dashItem" href="coupon.php">
<span class="dashItemMain">
<span class="dashItemIcon">%</span>
<span class="dashItemText">کوپن تخفیف</span>
</span>
<span class="dashItemChevron" aria-hidden="true">‹</span>
</a>
<a class="dashItem" href="support.php">
<?php if($hasUnreadSupport){ ?><span class="dashNotif"></span><?php } ?>
<span class="dashItemMain">
<span class="dashItemIcon">✉</span>
<span class="dashItemText">پیام به پشتیبانی</span>
</span>
<span class="dashItemChevron" aria-hidden="true">‹</span>
</a>
</div>

</div>

<a class="dashLogout" href="logout.php">خروج</a>

</div>

<script>
(function(){
    var moreBtn = document.getElementById('dashMoreBtn');
    var moreMenu = document.getElementById('dashMoreMenu');

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
        document.addEventListener('click', closeMenu);
        moreMenu.addEventListener('click', function(e){
            e.stopPropagation();
        });
    }

    function showSoon(msg){
        alert(msg || 'به زودی فعال می‌شود.');
        closeMenu();
    }

    var editAvatarBtn = document.getElementById('dashEditAvatarBtn');
    var editUsernameBtn = document.getElementById('dashEditUsernameBtn');

    if(editAvatarBtn){
        editAvatarBtn.addEventListener('click', function(){
            showSoon('ویرایش عکس پروفایل به زودی فعال می‌شود.');
        });
    }
    if(editUsernameBtn){
        editUsernameBtn.addEventListener('click', function(){
            showSoon('تغییر نام کاربری به زودی فعال می‌شود.');
        });
    }
})();
</script>

</body>
</html>

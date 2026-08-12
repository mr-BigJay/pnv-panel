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
<link rel="stylesheet" href="user_panel.css?v=6">
<style>
html,body{
height:100%;
overflow:hidden;
background:#0f172a;
color:#fff;
}
body.userPanel--dashboard{
min-height:100dvh;
height:100dvh;
align-items:stretch !important;
padding:10px !important;
overflow:hidden;
background:#0f172a;
color:#fff;
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
padding:16px 14px !important;
border-radius:18px !important;
overflow:hidden;
}
.userPanelTitle{
margin:0 0 10px !important;
font-size:20px !important;
}
.dashWelcome{
background:#0f172a;
border:1px solid #334155;
border-radius:14px;
padding:12px;
margin-bottom:10px;
flex:0 0 auto;
}
.dashHello{
margin:0 0 2px;
font-size:12px;
color:#94a3b8;
}
.dashUser{
margin:0 0 8px;
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
margin-bottom:10px;
flex:0 0 auto;
}
.dashPrimary{
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
gap:8px;
height:136px;
padding:12px 10px;
border-radius:16px;
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
width:36px;
height:36px;
border-radius:10px;
display:flex;
align-items:center;
justify-content:center;
background:rgba(34,197,94,.18);
color:#86efac;
font-size:22px;
font-weight:700;
flex:0 0 auto;
line-height:1;
}
.dashPrimary--renew .dashPrimaryIcon{
background:rgba(59,130,246,.18);
color:#93c5fd;
}
.dashPrimaryLabel{
font-size:13px;
font-weight:700;
line-height:1.35;
}
.dashList{
background:#0f172a;
border:1px solid #334155;
border-radius:14px;
overflow:hidden;
flex:1;
min-height:0;
display:flex;
flex-direction:column;
}
.dashItem{
display:flex;
align-items:center;
justify-content:space-between;
gap:8px;
padding:0 12px;
flex:1;
min-height:34px;
text-decoration:none;
color:#fff;
border-bottom:1px solid #1e293b;
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
}
.dashItemText{
font-size:13px;
font-weight:700;
line-height:1.3;
}
.dashItemMeta{
font-size:11px;
color:#64748b;
flex:0 0 auto;
}
.dashLogout{
display:block;
margin-top:10px;
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
top:50%;
left:12px;
transform:translateY(-50%);
width:8px;
height:8px;
border-radius:50%;
background:#ef4444;
box-shadow:0 0 8px rgba(239,68,68,.7);
}
.dashAnnouncements{display:flex;flex-direction:column;gap:8px;margin:8px 0}
.dashAnnouncementBanner{padding:12px 14px;border-radius:14px;border:1px solid #334155;background:#1e293b;line-height:1.8;font-size:13px}
.dashAnnouncementBanner strong{display:block;margin-bottom:4px;font-size:14px}
.dashAnnouncementBanner.is-info{border-color:#38bdf8}
.dashAnnouncementBanner.is-success{border-color:#22c55e}
.dashAnnouncementBanner.is-warning{border-color:#f59e0b}
.dashAnnouncementBanner.is-special{border-color:#a855f7}
.dashAnnouncementModal{position:fixed;inset:0;background:rgba(2,6,23,.72);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:16px;z-index:2000}
.dashAnnouncementModal.is-open{display:flex}
.dashAnnouncementCard{width:min(92vw,420px);background:#1e293b;border:1px solid #334155;border-radius:18px;padding:18px;color:#fff;box-shadow:0 20px 50px rgba(0,0,0,.35)}
.dashAnnouncementCard h3{margin:0 0 10px;font-size:18px}
.dashAnnouncementCard p{margin:0 0 16px;line-height:1.9;font-size:14px;color:#e2e8f0;white-space:pre-wrap}
.dashAnnouncementCard button{width:100%;padding:12px;border:none;border-radius:12px;background:#22c55e;color:#052e16;font-family:tahoma;font-size:15px;font-weight:700;cursor:pointer}
.dashAnnouncementCard.is-info{border-color:#38bdf8}
.dashAnnouncementCard.is-success{border-color:#22c55e}
.dashAnnouncementCard.is-warning{border-color:#f59e0b}
.dashAnnouncementCard.is-special{border-color:#a855f7}
@media(max-width:360px){
.dashPrimary{height:108px}
.dashPrimaryLabel{font-size:12px}
.dashItemText{font-size:12px}
.dashStatLabel{font-size:9px}
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

<div class="dashAnnouncements" id="dashAnnouncements"></div>

<div class="dashAnnouncementModal" id="dashAnnouncementModal" hidden>
<div class="dashAnnouncementCard is-info" id="dashAnnouncementCard">
<h3 id="dashAnnouncementTitle"></h3>
<p id="dashAnnouncementMessage"></p>
<button type="button" id="dashAnnouncementDismiss">متوجه شدم</button>
</div>
</div>

<a class="dashLogout" href="logout.php">خروج</a>

</div>
</div>

<script>
(function(){
    const modal = document.getElementById('dashAnnouncementModal');
    const card = document.getElementById('dashAnnouncementCard');
    const titleEl = document.getElementById('dashAnnouncementTitle');
    const messageEl = document.getElementById('dashAnnouncementMessage');
    const dismissBtn = document.getElementById('dashAnnouncementDismiss');
    const bannerWrap = document.getElementById('dashAnnouncements');
    let currentId = '';

    function typeClass(type){
        return 'is-' + (type || 'info');
    }

    function renderBanner(item){
        const div = document.createElement('div');
        div.className = 'dashAnnouncementBanner ' + typeClass(item.type);
        div.innerHTML = '<strong>' + (item.title || '') + '</strong><div>' + (item.message || '') + '</div>';
        return div;
    }

    function openModal(item){
        currentId = item.id || '';
        titleEl.textContent = item.title || '';
        messageEl.textContent = item.message || '';
        card.className = 'dashAnnouncementCard ' + typeClass(item.type);
        modal.hidden = false;
        modal.classList.add('is-open');
    }

    function closeModal(){
        modal.classList.remove('is-open');
        modal.hidden = true;
        currentId = '';
    }

    dismissBtn.addEventListener('click', function(){
        if(!currentId){ closeModal(); return; }
        const body = new URLSearchParams();
        body.set('action', 'dismiss');
        body.set('id', currentId);
        fetch('announcement-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        }).finally(function(){
            closeModal();
        });
    });

    fetch('announcement-api.php?action=list', {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(!data || !data.ok){ return; }
            if(data.modal){ openModal(data.modal); }
            (data.banners || []).forEach(function(item){
                bannerWrap.appendChild(renderBanner(item));
            });
        })
        .catch(function(){});
})();
</script>

</body>
</html>

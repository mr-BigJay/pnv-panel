<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$hasUnreadSupport = false;
$approvedSubs = 0;
$pendingBuys = 0;
$pendingRenews = 0;
$avatarUrl = '';

require_once __DIR__ . '/profile_lib.php';
require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/support_lib.php';

$avatarUrl = profileGetUserAvatar($user);
$hasUnreadSupport = supportUserHasUnread($user);

$dashStats = pnvDashboardUserPaymentStats($user);
$approvedSubs = intval($dashStats['approved_subs'] ?? 0);
$pendingBuys = intval($dashStats['pending_buys'] ?? 0);
$pendingRenews = intval($dashStats['pending_renews'] ?? 0);

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
<link rel="stylesheet" href="user_bg.css?v=5">
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
flex:0 0 auto;
display:flex;
flex-direction:column;
max-width:400px;
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
flex:0 0 auto;
display:flex;
flex-direction:column;
gap:8px;
background:rgba(18,24,32,.72);
border:1px solid rgba(148,163,184,.14);
border-radius:22px;
padding:12px 10px;
box-shadow:0 20px 48px rgba(0,0,0,.28);
backdrop-filter:blur(10px);
-webkit-backdrop-filter:blur(10px);
overflow:hidden;
}
.dashWelcome{
position:relative;
flex:0 0 auto;
padding:16px 10px 16px 36px;
margin-bottom:4px;
border-bottom:1px solid rgba(148,163,184,.16);
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
width:72px;
height:72px;
border-radius:50%;
flex:0 0 auto;
display:flex;
align-items:center;
justify-content:center;
font-size:30px;
font-weight:700;
color:#fff;
background:linear-gradient(135deg,#22c55e 0%,#2563eb 100%);
box-shadow:0 4px 14px rgba(37,99,235,.25);
overflow:hidden;
}
.dashAvatar img{
width:100%;
height:100%;
object-fit:cover;
display:block;
}
.dashAvatar.is-loading{
opacity:.65;
}
.dashWelcomeText{
min-width:0;
flex:1;
}
.dashHello{
margin:0 0 6px;
font-size:12px;
color:#94a3b8;
}
.dashNameRow{
display:flex;
flex-direction:column;
align-items:flex-start;
gap:8px;
}
.dashUser{
margin:0;
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
gap:8px;
flex:0 0 auto;
margin-top:4px;
}
.dashPrimary{
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
gap:8px;
aspect-ratio:1;
min-height:100px;
padding:10px 8px;
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
width:40px;
height:40px;
border-radius:11px;
display:flex;
align-items:center;
justify-content:center;
background:rgba(34,197,94,.18);
color:#86efac;
font-size:22px;
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
flex:0 0 auto;
display:flex;
flex-direction:column;
border-radius:12px;
overflow:hidden;
background:rgba(15,23,42,.55);
border:1px solid rgba(148,163,184,.1);
}
.dashItem{
display:flex;
align-items:center;
justify-content:space-between;
gap:8px;
padding:8px 10px;
flex:0 0 auto;
min-height:38px;
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
width:24px;
height:24px;
border-radius:7px;
flex:0 0 auto;
display:flex;
align-items:center;
justify-content:center;
background:rgba(30,41,59,.9);
color:#93c5fd;
font-size:11px;
font-weight:700;
}
.dashItemText{
font-size:12px;
font-weight:600;
line-height:1.25;
}
.dashItemChevron{
color:#64748b;
font-size:14px;
flex:0 0 auto;
line-height:1;
}
.dashLogout{
display:block;
margin-top:8px;
padding:11px;
border-radius:12px;
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
.dashModalOverlay{
display:none;
position:fixed;
inset:0;
z-index:100;
background:rgba(2,6,23,.72);
backdrop-filter:blur(4px);
-webkit-backdrop-filter:blur(4px);
align-items:center;
justify-content:center;
padding:16px;
box-sizing:border-box;
}
.dashModalOverlay.is-open{
display:flex;
}
.dashModal{
width:100%;
max-width:340px;
background:rgba(15,23,42,.96);
border:1px solid rgba(148,163,184,.18);
border-radius:18px;
padding:18px 16px 16px;
box-shadow:0 20px 48px rgba(0,0,0,.45);
}
.dashModalTitle{
margin:0 0 12px;
font-size:16px;
font-weight:700;
color:#fff;
}
.dashModalInput{
width:100%;
height:44px;
border:1px solid rgba(148,163,184,.2);
border-radius:12px;
padding:0 12px;
box-sizing:border-box;
background:rgba(30,41,59,.85);
color:#fff;
font-family:inherit;
font-size:14px;
outline:none;
}
.dashModalInput:focus{
border-color:#3b82f6;
}
.dashModalHint{
margin:8px 0 0;
font-size:11px;
color:#94a3b8;
line-height:1.5;
}
.dashModalError{
display:none;
margin:10px 0 0;
padding:8px 10px;
border-radius:10px;
background:rgba(127,29,29,.75);
border:1px solid rgba(239,68,68,.35);
color:#fecaca;
font-size:12px;
line-height:1.45;
}
.dashModalError.is-visible{
display:block;
}
.dashModalActions{
display:flex;
gap:8px;
margin-top:14px;
}
.dashModalBtn{
flex:1;
height:42px;
border:none;
border-radius:12px;
font-family:inherit;
font-size:13px;
font-weight:700;
cursor:pointer;
}
.dashModalBtn--ghost{
background:rgba(51,65,85,.85);
color:#e2e8f0;
}
.dashModalBtn--primary{
background:#2563eb;
color:#fff;
}
.dashModalBtn:disabled{
opacity:.6;
cursor:not-allowed;
}
.dashAvatarInput{
display:none;
}
.avatarCropWrap{
display:flex;
flex-direction:column;
align-items:center;
gap:14px;
}
.avatarCropViewport{
position:relative;
width:260px;
height:260px;
border-radius:50%;
overflow:hidden;
background:#0f172a;
border:2px solid rgba(148,163,184,.22);
touch-action:none;
cursor:grab;
box-shadow:inset 0 0 0 1px rgba(15,23,42,.6);
}
.avatarCropViewport.is-dragging{
cursor:grabbing;
}
.avatarCropImage{
position:absolute;
top:0;
left:0;
max-width:none;
user-select:none;
-webkit-user-drag:none;
pointer-events:none;
transform-origin:top left;
}
.avatarCropZoomWrap{
width:100%;
}
.avatarCropZoomLabel{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:8px;
font-size:12px;
color:#94a3b8;
}
.avatarCropZoom{
width:100%;
accent-color:#2563eb;
}
.dashModal--crop{
max-width:360px;
}
@media(max-width:360px){
.dashPrimary{min-height:92px}
.dashPrimaryIcon{width:34px;height:34px;font-size:18px}
.dashPrimaryLabel{font-size:11px}
.dashAvatar{width:66px;height:66px;font-size:26px}
.dashChip{font-size:9px}
.dashChip b{font-size:11px}
.dashItem{min-height:36px;padding:7px 10px}
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
<div class="dashAvatar" id="dashAvatar">
<?php if($avatarUrl !== ''){ ?>
<img src="<?php echo dashH($avatarUrl); ?>?v=<?php echo (int)filemtime(__DIR__ . '/' . ltrim($avatarUrl, '/')); ?>" alt="">
<?php } else { ?>
<?php echo dashH(dashUserInitial($user)); ?>
<?php } ?>
</div>
<div class="dashWelcomeText">
<p class="dashHello">خوش آمدید</p>
<div class="dashNameRow">
<p class="dashUser"><?php echo dashH($user); ?></p>
<div class="dashStatsInline">
<span class="dashChip"><b><?php echo (int)$approvedSubs; ?></b> اشتراک فعال</span>
<span class="dashChip"><b><?php echo (int)$pendingBuys; ?></b> خرید در انتظار</span>
<span class="dashChip"><b><?php echo (int)$pendingRenews; ?></b> تمدید در انتظار</span>
</div>
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
<span class="dashItemText">اشتراک من</span>
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
<a class="dashItem" href="coupon.php">
<span class="dashItemMain">
<span class="dashItemIcon">%</span>
<span class="dashItemText">کوپن تخفیف</span>
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
</div>

</div>

<a class="dashLogout" href="logout.php">خروج</a>

</div>

<input type="file" id="dashAvatarInput" class="dashAvatarInput" accept="image/jpeg,image/png,image/webp">

<div class="dashModalOverlay" id="dashUsernameModal" aria-hidden="true">
<div class="dashModal" role="dialog" aria-modal="true" aria-labelledby="dashUsernameModalTitle">
<h2 class="dashModalTitle" id="dashUsernameModalTitle">تغییر نام کاربری</h2>
<input type="text" class="dashModalInput" id="dashUsernameInput" maxlength="20" autocomplete="username" value="<?php echo dashH($user); ?>">
<p class="dashModalHint">نام کاربری باید 6 تا 20 کاراکتر و فقط شامل حروف لاتین، عدد و . _ - باشد.</p>
<div class="dashModalError" id="dashUsernameError"></div>
<div class="dashModalActions">
<button type="button" class="dashModalBtn dashModalBtn--ghost" id="dashUsernameCancel">انصراف</button>
<button type="button" class="dashModalBtn dashModalBtn--primary" id="dashUsernameSave">ذخیره</button>
</div>
</div>
</div>

<div class="dashModalOverlay" id="dashAvatarCropModal" aria-hidden="true">
<div class="dashModal dashModal--crop" role="dialog" aria-modal="true" aria-labelledby="dashAvatarCropTitle">
<h2 class="dashModalTitle" id="dashAvatarCropTitle">تنظیم عکس پروفایل</h2>
<div class="avatarCropWrap">
<div class="avatarCropViewport" id="avatarCropViewport">
<img class="avatarCropImage" id="avatarCropImage" alt="">
</div>
<div class="avatarCropZoomWrap">
<div class="avatarCropZoomLabel">
<span>بزرگ‌نمایی</span>
<span id="avatarCropZoomValue">100%</span>
</div>
<input type="range" class="avatarCropZoom" id="avatarCropZoom" min="100" max="300" step="1" value="100">
</div>
<p class="dashModalHint">عکس را بکشید تا قسمت دلخواه داخل دایره قرار بگیرد.</p>
<div class="dashModalError" id="avatarCropError"></div>
<div class="dashModalActions">
<button type="button" class="dashModalBtn dashModalBtn--ghost" id="avatarCropCancel">انصراف</button>
<button type="button" class="dashModalBtn dashModalBtn--primary" id="avatarCropSave">ذخیره عکس</button>
</div>
</div>
</div>
</div>

<script>
(function(){
    var moreBtn = document.getElementById('dashMoreBtn');
    var moreMenu = document.getElementById('dashMoreMenu');
    var editAvatarBtn = document.getElementById('dashEditAvatarBtn');
    var editUsernameBtn = document.getElementById('dashEditUsernameBtn');
    var avatarInput = document.getElementById('dashAvatarInput');
    var avatarEl = document.getElementById('dashAvatar');
    var usernameModal = document.getElementById('dashUsernameModal');
    var usernameInput = document.getElementById('dashUsernameInput');
    var usernameError = document.getElementById('dashUsernameError');
    var usernameCancel = document.getElementById('dashUsernameCancel');
    var usernameSave = document.getElementById('dashUsernameSave');
    var dashUserEl = document.querySelector('.dashUser');
    var avatarCropModal = document.getElementById('dashAvatarCropModal');
    var avatarCropViewport = document.getElementById('avatarCropViewport');
    var avatarCropImage = document.getElementById('avatarCropImage');
    var avatarCropZoom = document.getElementById('avatarCropZoom');
    var avatarCropZoomValue = document.getElementById('avatarCropZoomValue');
    var avatarCropCancel = document.getElementById('avatarCropCancel');
    var avatarCropSave = document.getElementById('avatarCropSave');
    var avatarCropError = document.getElementById('avatarCropError');

    var cropState = {
        baseScale: 1,
        zoom: 1,
        offsetX: 0,
        offsetY: 0,
        viewportSize: 260,
        outputSize: 512,
        dragging: false,
        startX: 0,
        startY: 0,
        startOffsetX: 0,
        startOffsetY: 0
    };

    function closeMenu(){
        if(moreMenu){
            moreMenu.classList.remove('is-open');
        }
    }

    function showUsernameError(msg){
        if(!usernameError){
            return;
        }

        if(msg){
            usernameError.textContent = msg;
            usernameError.classList.add('is-visible');
        } else {
            usernameError.textContent = '';
            usernameError.classList.remove('is-visible');
        }
    }

    function openUsernameModal(){
        if(!usernameModal || !usernameInput){
            return;
        }

        showUsernameError('');
        usernameInput.value = dashUserEl ? dashUserEl.textContent.trim() : '';
        usernameModal.classList.add('is-open');
        usernameModal.setAttribute('aria-hidden', 'false');
        closeMenu();
        setTimeout(function(){
            usernameInput.focus();
            usernameInput.select();
        }, 0);
    }

    function closeUsernameModal(){
        if(!usernameModal){
            return;
        }

        usernameModal.classList.remove('is-open');
        usernameModal.setAttribute('aria-hidden', 'true');
        showUsernameError('');
    }

    function showCropError(msg){
        if(!avatarCropError){
            return;
        }

        if(msg){
            avatarCropError.textContent = msg;
            avatarCropError.classList.add('is-visible');
        } else {
            avatarCropError.textContent = '';
            avatarCropError.classList.remove('is-visible');
        }
    }

    function getCropScale(){
        return cropState.baseScale * cropState.zoom;
    }

    function clampCropOffsets(){
        if(!avatarCropImage || !avatarCropImage.naturalWidth){
            return;
        }

        var scale = getCropScale();
        var iw = avatarCropImage.naturalWidth * scale;
        var ih = avatarCropImage.naturalHeight * scale;
        var half = cropState.viewportSize / 2;
        var maxX = Math.max(0, (iw - cropState.viewportSize) / 2);
        var maxY = Math.max(0, (ih - cropState.viewportSize) / 2);

        cropState.offsetX = Math.min(maxX, Math.max(-maxX, cropState.offsetX));
        cropState.offsetY = Math.min(maxY, Math.max(-maxY, cropState.offsetY));
    }

    function renderCropPreview(){
        if(!avatarCropImage || !avatarCropViewport || !avatarCropImage.naturalWidth){
            return;
        }

        var scale = getCropScale();
        var iw = avatarCropImage.naturalWidth * scale;
        var ih = avatarCropImage.naturalHeight * scale;
        var half = cropState.viewportSize / 2;
        var left = half - (iw / 2) + cropState.offsetX;
        var top = half - (ih / 2) + cropState.offsetY;

        avatarCropImage.style.width = iw + 'px';
        avatarCropImage.style.height = ih + 'px';
        avatarCropImage.style.left = left + 'px';
        avatarCropImage.style.top = top + 'px';
    }

    function resetCropState(){
        if(!avatarCropImage || !avatarCropImage.naturalWidth){
            return;
        }

        cropState.baseScale = Math.max(
            cropState.viewportSize / avatarCropImage.naturalWidth,
            cropState.viewportSize / avatarCropImage.naturalHeight
        );
        cropState.zoom = 1;
        cropState.offsetX = 0;
        cropState.offsetY = 0;

        if(avatarCropZoom){
            avatarCropZoom.value = '100';
        }

        if(avatarCropZoomValue){
            avatarCropZoomValue.textContent = '100%';
        }

        clampCropOffsets();
        renderCropPreview();
    }

    function closeAvatarCropModal(){
        if(!avatarCropModal){
            return;
        }

        avatarCropModal.classList.remove('is-open');
        avatarCropModal.setAttribute('aria-hidden', 'true');
        showCropError('');

        if(avatarCropImage){
            avatarCropImage.removeAttribute('src');
        }
    }

    function openAvatarCropModal(file){
        if(!avatarCropModal || !avatarCropImage || !file){
            return;
        }

        showCropError('');

        var reader = new FileReader();

        reader.onload = function(){
            avatarCropImage.onload = function(){
                resetCropState();
            };
            avatarCropImage.src = reader.result;
            avatarCropModal.classList.add('is-open');
            avatarCropModal.setAttribute('aria-hidden', 'false');
        };

        reader.onerror = function(){
            alert('خواندن عکس انجام نشد.');
        };

        reader.readAsDataURL(file);
    }

    function beginCropDrag(clientX, clientY){
        cropState.dragging = true;
        cropState.startX = clientX;
        cropState.startY = clientY;
        cropState.startOffsetX = cropState.offsetX;
        cropState.startOffsetY = cropState.offsetY;

        if(avatarCropViewport){
            avatarCropViewport.classList.add('is-dragging');
        }
    }

    function moveCropDrag(clientX, clientY){
        if(!cropState.dragging){
            return;
        }

        cropState.offsetX = cropState.startOffsetX + (clientX - cropState.startX);
        cropState.offsetY = cropState.startOffsetY + (clientY - cropState.startY);
        clampCropOffsets();
        renderCropPreview();
    }

    function endCropDrag(){
        cropState.dragging = false;

        if(avatarCropViewport){
            avatarCropViewport.classList.remove('is-dragging');
        }
    }

    function buildCroppedAvatarBlob(callback){
        if(!avatarCropImage || !avatarCropImage.naturalWidth){
            callback(null);
            return;
        }

        var canvas = document.createElement('canvas');
        var output = cropState.outputSize;
        var ratio = output / cropState.viewportSize;
        var scale = getCropScale();
        var iw = avatarCropImage.naturalWidth * scale;
        var ih = avatarCropImage.naturalHeight * scale;
        var half = cropState.viewportSize / 2;
        var left = (half - (iw / 2) + cropState.offsetX) * ratio;
        var top = (half - (ih / 2) + cropState.offsetY) * ratio;

        canvas.width = output;
        canvas.height = output;

        var ctx = canvas.getContext('2d');

        if(!ctx){
            callback(null);
            return;
        }

        ctx.clearRect(0, 0, output, output);
        ctx.save();
        ctx.beginPath();
        ctx.arc(output / 2, output / 2, output / 2, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        ctx.drawImage(
            avatarCropImage,
            left,
            top,
            iw * ratio,
            ih * ratio
        );
        ctx.restore();

        canvas.toBlob(function(blob){
            callback(blob);
        }, 'image/jpeg', 0.92);
    }

    function setAvatarImage(url){
        if(!avatarEl){
            return;
        }

        avatarEl.innerHTML = '';
        var img = document.createElement('img');
        img.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'v=' + Date.now();
        img.alt = '';
        avatarEl.appendChild(img);
    }

    function uploadAvatar(file){
        if(!file || !avatarEl){
            return;
        }

        var formData = new FormData();
        formData.append('action', 'avatar');
        formData.append('avatar', file, file.name || 'avatar.jpg');

        avatarEl.classList.add('is-loading');

        fetch('profile-api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(res){
            return res.json();
        })
        .then(function(data){
            avatarEl.classList.remove('is-loading');

            if(!data || !data.ok){
                alert((data && data.error) ? data.error : 'آپلود عکس انجام نشد.');
                return;
            }

            setAvatarImage(data.avatar);
        })
        .catch(function(){
            avatarEl.classList.remove('is-loading');
            alert('خطا در ارتباط با سرور.');
        });
    }

    function saveCroppedAvatar(){
        if(!avatarCropSave){
            return;
        }

        showCropError('');
        avatarCropSave.disabled = true;

        buildCroppedAvatarBlob(function(blob){
            if(!blob){
                avatarCropSave.disabled = false;
                showCropError('ساخت عکس نهایی انجام نشد.');
                return;
            }

            var croppedFile = new File([blob], 'avatar.jpg', {type: 'image/jpeg'});
            closeAvatarCropModal();
            avatarCropSave.disabled = false;
            uploadAvatar(croppedFile);
        });
    }

    function saveUsername(){
        if(!usernameInput || !usernameSave){
            return;
        }

        var nextUsername = usernameInput.value.trim();

        if(nextUsername.length < 6){
            showUsernameError('نام کاربری باید حداقل 6 کاراکتر باشد.');
            return;
        }

        usernameSave.disabled = true;
        showUsernameError('');

        var body = new URLSearchParams();
        body.append('action', 'username');
        body.append('username', nextUsername);

        fetch('profile-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function(res){
            return res.json();
        })
        .then(function(data){
            usernameSave.disabled = false;

            if(!data || !data.ok){
                showUsernameError((data && data.error) ? data.error : 'تغییر نام کاربری انجام نشد.');
                return;
            }

            if(dashUserEl){
                dashUserEl.textContent = data.username;
            }

            closeUsernameModal();
        })
        .catch(function(){
            usernameSave.disabled = false;
            showUsernameError('خطا در ارتباط با سرور.');
        });
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

    if(editAvatarBtn && avatarInput){
        editAvatarBtn.addEventListener('click', function(){
            closeMenu();
            avatarInput.click();
        });

        avatarInput.addEventListener('change', function(){
            if(avatarInput.files && avatarInput.files[0]){
                openAvatarCropModal(avatarInput.files[0]);
            }
            avatarInput.value = '';
        });
    }

    if(avatarCropZoom){
        avatarCropZoom.addEventListener('input', function(){
            cropState.zoom = parseInt(avatarCropZoom.value, 10) / 100;

            if(avatarCropZoomValue){
                avatarCropZoomValue.textContent = avatarCropZoom.value + '%';
            }

            clampCropOffsets();
            renderCropPreview();
        });
    }

    if(avatarCropViewport){
        avatarCropViewport.addEventListener('mousedown', function(e){
            e.preventDefault();
            beginCropDrag(e.clientX, e.clientY);
        });

        avatarCropViewport.addEventListener('touchstart', function(e){
            if(!e.touches || !e.touches[0]){
                return;
            }

            beginCropDrag(e.touches[0].clientX, e.touches[0].clientY);
        }, {passive:true});

        window.addEventListener('mousemove', function(e){
            moveCropDrag(e.clientX, e.clientY);
        });

        window.addEventListener('touchmove', function(e){
            if(!e.touches || !e.touches[0]){
                return;
            }

            moveCropDrag(e.touches[0].clientX, e.touches[0].clientY);
        }, {passive:true});

        window.addEventListener('mouseup', endCropDrag);
        window.addEventListener('touchend', endCropDrag);
        window.addEventListener('touchcancel', endCropDrag);
    }

    if(avatarCropCancel){
        avatarCropCancel.addEventListener('click', closeAvatarCropModal);
    }

    if(avatarCropSave){
        avatarCropSave.addEventListener('click', saveCroppedAvatar);
    }

    if(avatarCropModal){
        avatarCropModal.addEventListener('click', function(e){
            if(e.target === avatarCropModal){
                closeAvatarCropModal();
            }
        });
    }

    if(editUsernameBtn){
        editUsernameBtn.addEventListener('click', openUsernameModal);
    }

    if(usernameCancel){
        usernameCancel.addEventListener('click', closeUsernameModal);
    }

    if(usernameSave){
        usernameSave.addEventListener('click', saveUsername);
    }

    if(usernameModal){
        usernameModal.addEventListener('click', function(e){
            if(e.target === usernameModal){
                closeUsernameModal();
            }
        });
    }

    if(usernameInput){
        usernameInput.addEventListener('keydown', function(e){
            if(e.key === 'Enter'){
                e.preventDefault();
                saveUsername();
            }

            if(e.key === 'Escape'){
                closeUsernameModal();
            }
        });
    }
})();
</script>

</body>
</html>

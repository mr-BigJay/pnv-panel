<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/telegram_lib.php';
require_once __DIR__ . '/pnv_date_bootstrap.php';

$user = $_SESSION['user'];
$config = telegramLoadConfig();
$botEnabled = !empty($config['enabled']) && trim((string)($config['bot_token'] ?? '')) !== '';
$botUsername = trim((string)($config['bot_username'] ?? ''));

$tgInfo = telegramGetUserTelegramInfo($user);
$chatId = $tgInfo['chat_id'] ?? '';
$isConnected = $chatId !== '';

$tgDisplayName = '';
if(!empty($tgInfo['tg_username'])){
    $tgDisplayName = '@' . $tgInfo['tg_username'];
} elseif(!empty($tgInfo['tg_name'])){
    $tgDisplayName = $tgInfo['tg_name'];
}

$connectedDate = '';
if($isConnected && !empty($tgInfo['connected_at'])){
    pnvEnsureTehranTimezone();
    $connectedDate = pnvFormatJalaliDate($tgInfo['connected_at'], '/') . ' ' . pnvFormatTehranTime($tgInfo['connected_at'], false);
}

$botLink = $botUsername !== '' ? 'https://t.me/' . rawurlencode(ltrim($botUsername, '@')) : '';

function tgH($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>اتصال تلگرام</title>
<link rel="stylesheet" href="/fonts.css">
<link rel="stylesheet" href="user_bg.css?v=5">
<style>
*{box-sizing:border-box}
html,body{
height:100%;
margin:0;
}
body{
min-height:100dvh;
padding:14px 14px;
padding-top:max(14px,env(safe-area-inset-top));
padding-bottom:max(14px,env(safe-area-inset-bottom));
display:flex;
flex-direction:column;
align-items:stretch;
overflow-y:auto;
}
.tgPage{
max-width:400px;
width:100%;
margin:0 auto;
display:flex;
flex-direction:column;
gap:14px;
animation:fadeUp .28s ease;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
/* top bar */
.tgTopBar{
display:flex;
align-items:center;
justify-content:space-between;
margin-bottom:2px;
}
.tgTopTitle{
font-size:18px;
font-weight:700;
color:#fff;
}
.tgBackBtn{
padding:6px 14px;
border-radius:10px;
background:rgba(30,41,59,.8);
border:1px solid rgba(148,163,184,.18);
color:#e2e8f0;
font-family:inherit;
font-size:13px;
font-weight:600;
cursor:pointer;
text-decoration:none;
}
/* card */
.tgCard{
background:rgba(18,24,32,.75);
border:1px solid rgba(148,163,184,.14);
border-radius:20px;
padding:24px 16px 20px;
box-shadow:0 16px 40px rgba(0,0,0,.3);
backdrop-filter:blur(12px);
-webkit-backdrop-filter:blur(12px);
display:flex;
flex-direction:column;
align-items:center;
gap:6px;
}
/* telegram logo */
.tgLogo{
width:64px;
height:64px;
border-radius:50%;
background:linear-gradient(145deg,#2AABEE,#229ED9);
display:flex;
align-items:center;
justify-content:center;
box-shadow:0 6px 20px rgba(34,158,217,.35);
margin-bottom:4px;
}
.tgLogo svg{
width:36px;
height:36px;
fill:#fff;
}
/* status */
.tgConnStatus{
font-size:16px;
font-weight:700;
color:#4ade80;
}
.tgConnStatus--off{
color:#94a3b8;
}
.tgConnMeta{
font-size:13px;
color:#94a3b8;
line-height:1.6;
text-align:center;
}
/* action row */
.tgActions{
display:flex;
gap:8px;
margin-top:12px;
width:100%;
}
.tgBtn{
display:flex;
align-items:center;
justify-content:center;
gap:6px;
padding:10px 16px;
border:none;
border-radius:12px;
font-family:inherit;
font-size:13px;
font-weight:700;
cursor:pointer;
text-decoration:none;
transition:opacity .15s;
flex:1;
}
.tgBtn:disabled{opacity:.5;cursor:not-allowed}
.tgBtn--tg{background:#229ED9;color:#fff}
.tgBtn--disconnect{
background:transparent;
border:1px solid rgba(239,68,68,.5);
color:#fca5a5;
}
.tgBtn--disconnect:hover{background:rgba(127,29,29,.35)}
.tgBtn--primary{background:#2563eb;color:#fff;width:100%}
/* features section */
.tgFeaturesTitle{
font-size:13px;
font-weight:700;
color:#94a3b8;
text-align:right;
padding-right:2px;
}
.tgFeaturesCard{
background:rgba(15,23,42,.6);
border:1px solid rgba(148,163,184,.1);
border-radius:16px;
overflow:hidden;
}
.tgFeatureItem{
display:flex;
align-items:center;
gap:12px;
padding:12px 14px;
border-bottom:1px solid rgba(30,41,59,.9);
font-size:13px;
color:#cbd5e1;
line-height:1.4;
}
.tgFeatureItem:last-child{border-bottom:0}
.tgFeatureIcon{
font-size:18px;
flex:0 0 auto;
line-height:1;
}
/* connect flow */
.tgConnectCard{
background:rgba(18,24,32,.75);
border:1px solid rgba(148,163,184,.14);
border-radius:20px;
padding:20px 16px;
backdrop-filter:blur(12px);
-webkit-backdrop-filter:blur(12px);
display:flex;
flex-direction:column;
gap:12px;
}
.tgStep{
display:flex;
align-items:flex-start;
gap:10px;
}
.tgStepNum{
flex:0 0 auto;
width:22px;
height:22px;
border-radius:50%;
background:rgba(37,99,235,.3);
border:1px solid rgba(59,130,246,.3);
color:#93c5fd;
font-size:11px;
font-weight:700;
display:flex;
align-items:center;
justify-content:center;
}
.tgStepText{font-size:12px;color:#e2e8f0;line-height:1.55}
.tgLinkBox{
display:none;
flex-direction:column;
gap:8px;
padding:12px;
background:rgba(15,23,42,.6);
border:1px solid rgba(148,163,184,.12);
border-radius:12px;
}
.tgLinkBox.is-open{display:flex}
.tgTimer{font-size:11px;color:#64748b;text-align:center}
.tgWarn{
padding:10px 12px;
border-radius:10px;
font-size:12px;
line-height:1.55;
background:rgba(78,52,0,.6);
border:1px solid rgba(234,179,8,.2);
color:#fde68a;
}
.tgWait{
font-size:12px;
color:#93c5fd;
text-align:center;
display:none;
}
.tgWait.is-open{display:block}
.tgHide{display:none!important}
</style>
</head>
<body class="userPanel">

<div class="tgPage">

<div class="tgTopBar">
<span class="tgTopTitle">اتصال تلگرام</span>
<a class="tgBackBtn" href="dashboard.php">بازگشت</a>
</div>

<?php if(!$botEnabled){ ?>
<div class="tgCard">
<div class="tgLogo">
<svg viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
</div>
<div class="tgConnStatus tgConnStatus--off">ربات فعال نیست</div>
<div class="tgConnMeta">ادمین هنوز ربات را راه‌اندازی نکرده است</div>
</div>

<?php } elseif($isConnected){ ?>

<div class="tgCard">
<div class="tgLogo">
<svg viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
</div>
<div class="tgConnStatus">✅ متصل</div>
<?php if($tgDisplayName !== ''){ ?>
<div class="tgConnMeta"><?php echo tgH($tgDisplayName); ?></div>
<?php } ?>
<?php if($connectedDate !== ''){ ?>
<div class="tgConnMeta" style="font-size:11px;opacity:.7"><?php echo tgH($connectedDate); ?></div>
<?php } ?>
<div class="tgActions">
<?php if($botLink !== ''){ ?>
<a class="tgBtn tgBtn--tg" href="<?php echo tgH($botLink); ?>" target="_blank" rel="noopener">
<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
ربات
</a>
<?php } ?>
<button type="button" class="tgBtn tgBtn--disconnect" id="tgDisconnectBtn">قطع اتصال</button>
</div>
</div>

<div class="tgFeaturesTitle">با اتصال چه می‌کنید؟</div>
<div class="tgFeaturesCard">
<div class="tgFeatureItem">
<span class="tgFeatureIcon">📋</span>
<span>مشاهده اشتراک‌ها و وضعیت حجم/زمان</span>
</div>
<div class="tgFeatureItem">
<span class="tgFeatureIcon">⏳</span>
<span>اعلان قبل از انقضا یا اتمام حجم</span>
</div>
<div class="tgFeatureItem">
<span class="tgFeatureIcon">💬</span>
<span>دریافت پاسخ پشتیبانی در تلگرام</span>
</div>
<div class="tgFeatureItem">
<span class="tgFeatureIcon">🔔</span>
<span>اطلاع تأیید پرداخت و کمپین‌ها</span>
</div>
</div>

<?php } else { ?>

<div class="tgCard">
<div class="tgLogo">
<svg viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
</div>
<div class="tgConnStatus tgConnStatus--off">متصل نیست</div>
</div>

<div class="tgConnectCard">
<div class="tgStep">
<span class="tgStepNum">۱</span>
<span class="tgStepText">روی «دریافت لینک اتصال» بزنید</span>
</div>
<div class="tgStep">
<span class="tgStepNum">۲</span>
<span class="tgStepText">لینک را در تلگرام باز کنید و <strong>Start</strong> بزنید</span>
</div>
<div class="tgStep">
<span class="tgStepNum">۳</span>
<span class="tgStepText">اتصال ثبت می‌شود و اعلان‌ها برای شما فعال می‌شود</span>
</div>

<div class="tgLinkBox" id="tgLinkBox">
<?php if($botLink !== ''){ ?>
<a class="tgBtn tgBtn--tg" id="tgBotLink" href="#" target="_blank" rel="noopener">باز کردن ربات تلگرام</a>
<?php } else { ?>
<div style="display:flex;gap:6px;">
<input type="text" id="tgTokenInput" readonly style="flex:1;background:rgba(15,23,42,.8);border:1px solid rgba(148,163,184,.2);border-radius:10px;padding:9px 12px;color:#e2e8f0;font-size:12px;font-family:monospace;direction:ltr;outline:none;">
<button type="button" class="tgBtn" onclick="copyToken()" style="flex:0 0 auto;background:rgba(51,65,85,.85);color:#e2e8f0;width:auto;padding:0 14px;font-size:12px;">کپی</button>
</div>
<?php } ?>
<div class="tgTimer" id="tgTimer"></div>
</div>

<button type="button" class="tgBtn tgBtn--primary" id="tgGetLinkBtn">دریافت لینک اتصال</button>
<div class="tgWait" id="tgWaitMsg">در حال انتظار برای اتصال...</div>
</div>

<div class="tgFeaturesTitle">با اتصال چه می‌کنید؟</div>
<div class="tgFeaturesCard">
<div class="tgFeatureItem">
<span class="tgFeatureIcon">📋</span>
<span>مشاهده اشتراک‌ها و وضعیت حجم/زمان</span>
</div>
<div class="tgFeatureItem">
<span class="tgFeatureIcon">⏳</span>
<span>اعلان قبل از انقضا یا اتمام حجم</span>
</div>
<div class="tgFeatureItem">
<span class="tgFeatureIcon">💬</span>
<span>دریافت پاسخ پشتیبانی در تلگرام</span>
</div>
<div class="tgFeatureItem">
<span class="tgFeatureIcon">🔔</span>
<span>اطلاع تأیید پرداخت و کمپین‌ها</span>
</div>
</div>

<?php } ?>

</div>

<script>
(function(){
    var isConnected = <?php echo $isConnected ? 'true' : 'false'; ?>;
    var botLink = <?php echo json_encode($botLink); ?>;

    var disconnectBtn = document.getElementById('tgDisconnectBtn');
    var getLinkBtn    = document.getElementById('tgGetLinkBtn');
    var linkBox       = document.getElementById('tgLinkBox');
    var botLinkEl     = document.getElementById('tgBotLink');
    var tokenInput    = document.getElementById('tgTokenInput');
    var timerEl       = document.getElementById('tgTimer');
    var waitMsg       = document.getElementById('tgWaitMsg');

    var pollInterval  = null;
    var timerInterval = null;

    function startTimer(seconds){
        clearInterval(timerInterval);
        var rem = seconds;
        function tick(){
            if(rem <= 0){
                clearInterval(timerInterval);
                if(timerEl) timerEl.textContent = 'لینک منقضی شده — دوباره دریافت کنید';
                stopPoll();
                if(linkBox) linkBox.classList.remove('is-open');
                if(getLinkBtn){ getLinkBtn.disabled = false; getLinkBtn.textContent = 'دریافت لینک جدید'; }
                if(waitMsg) waitMsg.classList.remove('is-open');
                return;
            }
            var m = Math.floor(rem / 60), s = rem % 60;
            if(timerEl) timerEl.textContent = 'انقضا: ' + m + ':' + (s < 10 ? '0' : '') + s;
            rem--;
        }
        tick();
        timerInterval = setInterval(tick, 1000);
    }

    function stopPoll(){ clearInterval(pollInterval); pollInterval = null; }

    function startPoll(){
        stopPoll();
        pollInterval = setInterval(function(){
            fetch('telegram-connect-api.php?action=status', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(d && d.connected){ stopPoll(); clearInterval(timerInterval); window.location.reload(); } })
            .catch(function(){});
        }, 3000);
    }

    window.copyToken = function(){
        if(tokenInput){ tokenInput.select(); document.execCommand('copy'); }
    };

    if(getLinkBtn){
        getLinkBtn.addEventListener('click', function(){
            getLinkBtn.disabled = true;
            getLinkBtn.textContent = '...';
            fetch('telegram-connect-api.php?action=token', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                getLinkBtn.disabled = false;
                if(!d || !d.ok){ alert(d && d.error ? d.error : 'خطا'); getLinkBtn.textContent = 'دریافت لینک اتصال'; return; }
                getLinkBtn.textContent = 'دریافت لینک جدید';
                if(linkBox) linkBox.classList.add('is-open');
                if(botLinkEl && d.bot_link) botLinkEl.href = d.bot_link;
                if(tokenInput){ var cmd = '/start ' + d.token; tokenInput.value = cmd; }
                if(waitMsg) waitMsg.classList.add('is-open');
                startTimer(d.expires_in || 600);
                startPoll();
            })
            .catch(function(){ getLinkBtn.disabled = false; getLinkBtn.textContent = 'دریافت لینک اتصال'; alert('خطا در ارتباط با سرور'); });
        });
    }

    if(disconnectBtn){
        disconnectBtn.addEventListener('click', function(){
            if(!confirm('قطع اتصال تلگرام؟')) return;
            disconnectBtn.disabled = true;
            fetch('telegram-connect-api.php?action=disconnect', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(d && d.ok){ window.location.reload(); } else { disconnectBtn.disabled = false; alert(d && d.error ? d.error : 'خطا'); } })
            .catch(function(){ disconnectBtn.disabled = false; alert('خطا'); });
        });
    }
})();
</script>

</body>
</html>

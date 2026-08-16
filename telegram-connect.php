<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/telegram_lib.php';

$user = $_SESSION['user'];
$config = telegramLoadConfig();
$botEnabled = !empty($config['enabled']) && trim((string)($config['bot_token'] ?? '')) !== '';
$botUsername = trim((string)($config['bot_username'] ?? ''));
$chatId = telegramGetUserChatId($user);
$isConnected = $chatId !== '';

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
html,body{
height:100%;
overflow:hidden;
}
body.tgConnectPage{
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
.tgPage{
flex:0 0 auto;
display:flex;
flex-direction:column;
max-width:400px;
width:100%;
margin:0 auto;
gap:10px;
animation:dashIn .3s ease;
}
@keyframes dashIn{
from{opacity:0;transform:translateY(6px)}
to{opacity:1;transform:none}
}
.tgTitle{
margin:0;
text-align:center;
font-family:"Lalezar",tahoma,sans-serif;
font-size:20px;
font-weight:400;
color:#fff;
}
.tgCard{
background:rgba(18,24,32,.72);
border:1px solid rgba(148,163,184,.14);
border-radius:22px;
padding:18px 14px;
box-shadow:0 20px 48px rgba(0,0,0,.28);
backdrop-filter:blur(10px);
-webkit-backdrop-filter:blur(10px);
display:flex;
flex-direction:column;
gap:12px;
}
.tgStatus{
display:flex;
align-items:center;
gap:10px;
padding:12px 14px;
border-radius:14px;
font-size:13px;
font-weight:600;
}
.tgStatus--connected{
background:rgba(20,83,45,.75);
border:1px solid rgba(34,197,94,.25);
color:#86efac;
}
.tgStatus--disconnected{
background:rgba(30,41,59,.6);
border:1px solid rgba(148,163,184,.12);
color:#94a3b8;
}
.tgStatusDot{
width:9px;
height:9px;
border-radius:50%;
flex:0 0 auto;
}
.tgStatus--connected .tgStatusDot{background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,.6)}
.tgStatus--disconnected .tgStatusDot{background:#475569}
.tgSection{
display:flex;
flex-direction:column;
gap:8px;
}
.tgLabel{
font-size:12px;
color:#94a3b8;
font-weight:600;
}
.tgDesc{
font-size:12px;
color:#cbd5e1;
line-height:1.65;
}
.tgStep{
display:flex;
align-items:flex-start;
gap:10px;
padding:10px 12px;
background:rgba(15,23,42,.5);
border:1px solid rgba(148,163,184,.1);
border-radius:12px;
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
.tgStepText{
font-size:12px;
color:#e2e8f0;
line-height:1.55;
}
.tgBtn{
display:flex;
align-items:center;
justify-content:center;
gap:8px;
width:100%;
padding:13px 16px;
border:none;
border-radius:14px;
font-family:inherit;
font-size:14px;
font-weight:700;
cursor:pointer;
text-decoration:none;
box-sizing:border-box;
transition:opacity .15s;
}
.tgBtn:disabled{opacity:.5;cursor:not-allowed}
.tgBtn--primary{background:#2563eb;color:#fff}
.tgBtn--telegram{background:#229ED9;color:#fff}
.tgBtn--danger{background:rgba(127,29,29,.85);border:1px solid #dc2626;color:#fff}
.tgBtn--ghost{background:rgba(51,65,85,.85);color:#e2e8f0}
.tgConnectBox{
display:flex;
flex-direction:column;
gap:10px;
padding:12px;
background:rgba(15,23,42,.5);
border:1px solid rgba(148,163,184,.1);
border-radius:14px;
}
.tgConnectTimer{
font-size:11px;
color:#64748b;
text-align:center;
}
.tgAlertBox{
padding:10px 12px;
border-radius:12px;
font-size:12px;
line-height:1.55;
}
.tgAlertBox--info{
background:rgba(23,37,84,.7);
border:1px solid rgba(59,130,246,.25);
color:#93c5fd;
}
.tgAlertBox--warn{
background:rgba(78,52,0,.7);
border:1px solid rgba(234,179,8,.25);
color:#fde68a;
}
.tgAlertBox--success{
background:rgba(20,83,45,.7);
border:1px solid rgba(34,197,94,.25);
color:#86efac;
}
.tgBack{
display:block;
text-align:center;
padding:12px;
border-radius:14px;
background:rgba(30,41,59,.6);
border:1px solid rgba(148,163,184,.1);
color:#94a3b8;
text-decoration:none;
font-size:13px;
font-weight:600;
}
.tgHide{display:none!important}
</style>
</head>
<body class="userPanel tgConnectPage">

<div class="tgPage">

<h1 class="tgTitle">اتصال تلگرام</h1>

<div class="tgCard">

<?php if(!$botEnabled){ ?>
<div class="tgAlertBox tgAlertBox--warn">
ربات تلگرام هنوز توسط ادمین راه‌اندازی نشده است. لطفاً بعداً دوباره بررسی کنید.
</div>
<?php } else { ?>

<div class="tgStatus <?php echo $isConnected ? 'tgStatus--connected' : 'tgStatus--disconnected'; ?>" id="tgStatusBar">
<span class="tgStatusDot" id="tgStatusDot"></span>
<span id="tgStatusText"><?php echo $isConnected ? 'اتصال فعال است' : 'متصل نیست'; ?></span>
</div>

<?php if(!$isConnected){ ?>
<div class="tgSection" id="tgConnectSection">
<div class="tgLabel">چطور متصل شوم؟</div>
<div class="tgStep">
<span class="tgStepNum">۱</span>
<span class="tgStepText">روی دکمه «دریافت لینک اتصال» بزنید تا یک لینک اختصاصی دریافت کنید.</span>
</div>
<div class="tgStep">
<span class="tgStepNum">۲</span>
<span class="tgStepText">لینک را باز کنید و در ربات تلگرام روی <strong>Start</strong> بزنید.</span>
</div>
<div class="tgStep">
<span class="tgStepNum">۳</span>
<span class="tgStepText">اتصال شما ثبت می‌شود و اعلان‌ها از این پس به تلگرام شما ارسال می‌شوند.</span>
</div>

<div class="tgConnectBox tgHide" id="tgLinkBox">
<?php if($botUsername !== ''){ ?>
<a class="tgBtn tgBtn--telegram" id="tgBotLink" href="#" target="_blank" rel="noopener">باز کردن ربات تلگرام</a>
<?php } else { ?>
<div class="tgAlertBox tgAlertBox--info" id="tgManualToken">لینک اتصال دریافت شد. آن را کپی کنید و در تلگرام به ربات ارسال کنید.</div>
<div style="display:flex;gap:6px;">
<input type="text" id="tgTokenInput" readonly style="flex:1;background:rgba(15,23,42,.8);border:1px solid rgba(148,163,184,.2);border-radius:10px;padding:9px 12px;color:#e2e8f0;font-size:12px;font-family:monospace;direction:ltr;outline:none;">
<button type="button" class="tgBtn tgBtn--ghost" onclick="copyToken()" style="width:auto;padding:0 14px;font-size:12px;">کپی</button>
</div>
<?php } ?>
<div class="tgConnectTimer" id="tgTimer"></div>
</div>

<button type="button" class="tgBtn tgBtn--primary" id="tgGetLinkBtn">دریافت لینک اتصال</button>
<div class="tgAlertBox tgAlertBox--info tgHide" id="tgWaitingMsg">
در حال انتظار برای اتصال...
</div>
</div>
<?php } else { ?>
<div class="tgSection" id="tgConnectedSection">
<div class="tgDesc">حساب کاربری شما به ربات متصل است. اعلان‌های زیر به تلگرام شما ارسال می‌شود:</div>
<div class="tgAlertBox tgAlertBox--success">
• پاسخ پشتیبانی<br>
• نزدیک شدن به پایان اشتراک<br>
• کمپین‌ها و اطلاع‌رسانی‌ها
</div>
<button type="button" class="tgBtn tgBtn--danger" id="tgDisconnectBtn">قطع اتصال تلگرام</button>
</div>
<?php } ?>

<?php } ?>

</div>

<a class="tgBack" href="dashboard.php">← بازگشت به داشبورد</a>

</div>

<script>
(function(){
    var connected = <?php echo $isConnected ? 'true' : 'false'; ?>;
    var botUsername = <?php echo json_encode($botUsername); ?>;
    var statusBar = document.getElementById('tgStatusBar');
    var statusDot = document.getElementById('tgStatusDot');
    var statusText = document.getElementById('tgStatusText');
    var getLinkBtn = document.getElementById('tgGetLinkBtn');
    var linkBox = document.getElementById('tgLinkBox');
    var botLink = document.getElementById('tgBotLink');
    var tokenInput = document.getElementById('tgTokenInput');
    var timerEl = document.getElementById('tgTimer');
    var waitingMsg = document.getElementById('tgWaitingMsg');
    var disconnectBtn = document.getElementById('tgDisconnectBtn');
    var connectSection = document.getElementById('tgConnectSection');
    var connectedSection = document.getElementById('tgConnectedSection');

    var pollInterval = null;
    var timerInterval = null;
    var expiresAt = 0;

    function setConnected(isConnected){
        connected = isConnected;

        if(!statusBar || !statusDot || !statusText){
            return;
        }

        if(isConnected){
            statusBar.className = 'tgStatus tgStatus--connected';
            statusText.textContent = 'اتصال فعال است';
        } else {
            statusBar.className = 'tgStatus tgStatus--disconnected';
            statusText.textContent = 'متصل نیست';
        }
    }

    function showConnectedState(){
        if(connectSection){
            connectSection.classList.add('tgHide');
        }
        if(connectedSection){
            connectedSection.classList.remove('tgHide');
        }
        setConnected(true);
    }

    function showDisconnectedState(){
        if(connectedSection){
            connectedSection.classList.add('tgHide');
        }
        if(connectSection){
            connectSection.classList.remove('tgHide');
        }
        setConnected(false);
    }

    function startTimer(seconds){
        clearInterval(timerInterval);
        var remaining = seconds;

        function tick(){
            if(remaining <= 0){
                clearInterval(timerInterval);
                if(timerEl){
                    timerEl.textContent = 'لینک منقضی شده است';
                }
                stopPoll();
                if(linkBox){
                    linkBox.classList.add('tgHide');
                }
                if(getLinkBtn){
                    getLinkBtn.disabled = false;
                    getLinkBtn.textContent = 'دریافت لینک جدید';
                }
                if(waitingMsg){
                    waitingMsg.classList.add('tgHide');
                }
                return;
            }

            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            if(timerEl){
                timerEl.textContent = 'انقضا: ' + m + ':' + (s < 10 ? '0' : '') + s;
            }

            remaining--;
        }

        tick();
        timerInterval = setInterval(tick, 1000);
    }

    function stopPoll(){
        clearInterval(pollInterval);
        pollInterval = null;
    }

    function startPoll(){
        stopPoll();
        pollInterval = setInterval(function(){
            fetch('telegram-connect-api.php?action=status', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
                if(data && data.connected){
                    stopPoll();
                    clearInterval(timerInterval);
                    showConnectedState();
                    window.location.reload();
                }
            })
            .catch(function(){});
        }, 3000);
    }

    function copyToken(){
        if(tokenInput){
            tokenInput.select();
            document.execCommand('copy');
            var btn = document.querySelector('[onclick="copyToken()"]');
            if(btn){
                btn.textContent = 'کپی شد!';
                setTimeout(function(){ btn.textContent = 'کپی'; }, 1500);
            }
        }
    }

    window.copyToken = copyToken;

    if(getLinkBtn){
        getLinkBtn.addEventListener('click', function(){
            getLinkBtn.disabled = true;
            getLinkBtn.textContent = 'در حال دریافت...';

            fetch('telegram-connect-api.php?action=token', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
                if(!data || !data.ok){
                    alert(data && data.error ? data.error : 'خطا در دریافت لینک');
                    getLinkBtn.disabled = false;
                    getLinkBtn.textContent = 'دریافت لینک اتصال';
                    return;
                }

                getLinkBtn.textContent = 'دریافت لینک جدید';
                expiresAt = Date.now() + (data.expires_in || 600) * 1000;

                if(linkBox){
                    linkBox.classList.remove('tgHide');
                }

                if(botLink && data.bot_link){
                    botLink.href = data.bot_link;
                } else if(tokenInput){
                    var startCmd = '/start ' + data.token;
                    tokenInput.value = startCmd;
                }

                if(waitingMsg){
                    waitingMsg.classList.remove('tgHide');
                }

                startTimer(data.expires_in || 600);
                startPoll();
                getLinkBtn.disabled = false;
            })
            .catch(function(){
                alert('خطا در ارتباط با سرور');
                getLinkBtn.disabled = false;
                getLinkBtn.textContent = 'دریافت لینک اتصال';
            });
        });
    }

    if(disconnectBtn){
        disconnectBtn.addEventListener('click', function(){
            if(!confirm('آیا مطمئن هستید که می‌خواهید اتصال تلگرام را قطع کنید؟')){
                return;
            }

            disconnectBtn.disabled = true;
            disconnectBtn.textContent = 'در حال قطع اتصال...';

            fetch('telegram-connect-api.php?action=disconnect', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
                if(data && data.ok){
                    window.location.reload();
                } else {
                    alert(data && data.error ? data.error : 'خطا در قطع اتصال');
                    disconnectBtn.disabled = false;
                    disconnectBtn.textContent = 'قطع اتصال تلگرام';
                }
            })
            .catch(function(){
                alert('خطا در ارتباط با سرور');
                disconnectBtn.disabled = false;
                disconnectBtn.textContent = 'قطع اتصال تلگرام';
            });
        });
    }
})();
</script>

</body>
</html>

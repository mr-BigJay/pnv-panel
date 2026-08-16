<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/user_nav.php';

$user = (string)$_SESSION['user'];
$telegramStatus = ['linked' => false, 'telegram_username' => '', 'linked_at' => ''];
$botEnabled = false;
$botUsername = '';

if(is_file(__DIR__ . '/telegram_user_lib.php')){
    require_once __DIR__ . '/telegram_user_lib.php';
    require_once __DIR__ . '/telegram_lib.php';

    if(function_exists('tgUserGetTelegramStatus')){
        $telegramStatus = tgUserGetTelegramStatus($user);
    }

    $config = telegramLoadConfig();
    $botEnabled = !empty($config['enabled']) && trim((string)($config['bot_token'] ?? '')) !== '';

    if(function_exists('tgUserGetBotUsername')){
        $botUsername = tgUserGetBotUsername($config);
    }
}

$linked = !empty($telegramStatus['linked']);
$telegramUsername = trim((string)($telegramStatus['telegram_username'] ?? ''));
$linkedAt = trim((string)($telegramStatus['linked_at'] ?? ''));

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$features = [
    '📋 مشاهده اشتراک‌ها و وضعیت حجم/زمان',
    '⏳ اعلان قبل از انقضا یا اتمام حجم',
    '💬 دریافت پاسخ پشتیبانی در تلگرام',
    '💳 اطلاع تأیید پرداخت و کمپین‌ها',
];

$metaLines = [];

if($linked){
    if($telegramUsername !== ''){
        $metaLines[] = $telegramUsername;
    }

    if($linkedAt !== ''){
        $metaLines[] = $linkedAt;
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>اتصال تلگرام</title>
<link rel="stylesheet" href="fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="telegram_ui.css?v=3">
</head>
<body class="telegramPage">

<div class="telegramApp">

<?php userBackBar('dashboard.php', 'اتصال تلگرام'); ?>

<section class="telegramMainCard">
<div class="telegramMainIcon" aria-hidden="true">
<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
<path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 0 0-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .37z"/>
</svg>
</div>
<div class="telegramMainStatus<?php echo $linked ? ' is-on' : ' is-off'; ?>" id="tgStatusValue">
<?php echo $linked ? '✅ متصل' : '❌ متصل نیست'; ?>
</div>
<div class="telegramMainMeta<?php echo count($metaLines) === 0 ? ' is-empty' : ''; ?>" id="tgStatusMeta"><?php
if($linked && count($metaLines) > 0){
    echo $h(implode("\n", $metaLines));
}
elseif(!$linked){
    echo 'برای دریافت اعلان‌ها، ربات را به پنل وصل کنید.';
}
?></div>

<div class="telegramFlash" id="tgFlash"></div>

<?php if(!$botEnabled){ ?>
<p class="telegramBotDisabled">ربات تلگرام در حال حاضر فعال نیست. لطفاً بعداً دوباره تلاش کنید.</p>
<?php } else { ?>
<div class="telegramActions" id="tgActions">
<?php if($linked){ ?>
<button type="button" class="telegramBtn telegramBtn--danger" id="tgDisconnectBtn">قطع اتصال</button>
<?php } else { ?>
<button type="button" class="telegramBtn telegramBtn--primary" id="tgConnectBtn">اتصال به ربات تلگرام</button>
<p class="telegramHint"><?php
if($botUsername !== ''){
    echo 'بعد از کلیک، ربات ' . $h('@' . ltrim($botUsername, '@')) . ' باز می‌شود. Start را بزنید.';
}
else{
    echo 'بعد از کلیک، صفحه ربات در تلگرام باز می‌شود. Start را بزنید.';
}
?></p>
<?php } ?>
</div>
<?php } ?>
</section>

<section class="telegramFeatures">
<h2 class="telegramSectionTitle">با اتصال چه می‌کنید؟</h2>
<div class="telegramFeatureBox">
<?php foreach($features as $line){ ?>
<div class="telegramFeatureLine"><?php echo $h($line); ?></div>
<?php } ?>
</div>
</section>

</div>

<script>
(function(){
    var flashEl = document.getElementById('tgFlash');
    var statusValue = document.getElementById('tgStatusValue');
    var statusMeta = document.getElementById('tgStatusMeta');
    var connectBtn = document.getElementById('tgConnectBtn');
    var disconnectBtn = document.getElementById('tgDisconnectBtn');

    function showFlash(msg, kind){
        if(!flashEl){
            return;
        }

        if(!msg){
            flashEl.textContent = '';
            flashEl.className = 'telegramFlash';
            return;
        }

        flashEl.textContent = msg;
        flashEl.className = 'telegramFlash is-visible' + (kind ? ' is-' + kind : '');
    }

    if(connectBtn){
        connectBtn.addEventListener('click', function(){
            showFlash('');
            connectBtn.disabled = true;

            fetch('profile-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=telegram_link'
            })
            .then(function(r){ return r.json(); })
            .then(function(data){
                connectBtn.disabled = false;

                if(!data.ok){
                    showFlash(data.error || 'ساخت لینک اتصال ناموفق بود', 'error');
                    return;
                }

                if(data.url){
                    window.open(data.url, '_blank');
                    showFlash('صفحه ربات باز شد. در تلگرام Start را بزنید.', 'success');
                }
            })
            .catch(function(){
                connectBtn.disabled = false;
                showFlash('خطا در ارتباط با سرور', 'error');
            });
        });
    }

    if(disconnectBtn){
        disconnectBtn.addEventListener('click', function(){
            if(!window.confirm('اتصال تلگرام قطع شود؟')){
                return;
            }

            showFlash('');
            disconnectBtn.disabled = true;

            fetch('profile-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=telegram_disconnect'
            })
            .then(function(r){ return r.json(); })
            .then(function(data){
                disconnectBtn.disabled = false;

                if(!data.ok){
                    showFlash(data.error || 'قطع اتصال ناموفق بود', 'error');
                    return;
                }

                showFlash('اتصال قطع شد.', 'success');
                window.setTimeout(function(){
                    window.location.reload();
                }, 500);
            })
            .catch(function(){
                disconnectBtn.disabled = false;
                showFlash('خطا در ارتباط با سرور', 'error');
            });
        });
    }
})();
</script>

</body>
</html>

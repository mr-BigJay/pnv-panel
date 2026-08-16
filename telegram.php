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
<link rel="stylesheet" href="telegram_ui.css?v=2">
</head>
<body class="telegramPage">

<div class="telegramApp">

<?php userBackBar('dashboard.php', 'اتصال تلگرام'); ?>

<section class="telegramMainCard">
<div class="telegramMainIcon" aria-hidden="true">✈</div>
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
<button type="button" class="telegramBtn telegramBtn--ghost" id="tgDisconnectBtn">قطع اتصال</button>
<button type="button" class="telegramLinkBtn" id="tgTestBtn">ارسال پیام تست</button>
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
    var testBtn = document.getElementById('tgTestBtn');
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

    if(testBtn){
        testBtn.addEventListener('click', function(){
            showFlash('');
            testBtn.disabled = true;

            fetch('profile-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=telegram_test'
            })
            .then(function(r){ return r.json(); })
            .then(function(data){
                testBtn.disabled = false;

                if(!data.ok){
                    showFlash(data.error || 'ارسال پیام تست ناموفق بود', 'error');
                    return;
                }

                showFlash('پیام تست ارسال شد ✅', 'success');
            })
            .catch(function(){
                testBtn.disabled = false;
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

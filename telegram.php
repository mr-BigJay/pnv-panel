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
    [
        'title' => 'مدیریت اشتراک‌ها',
        'text' => 'لیست اشتراک‌های فعال، مشاهده وضعیت زمان و حجم، و دسترسی سریع به لینک اشتراک.',
    ],
    [
        'title' => 'اعلان انقضا و حجم',
        'text' => 'قبل از اتمام زمان یا حجم اشتراک، به‌صورت خودکار در تلگرام مطلع می‌شوید.',
    ],
    [
        'title' => 'پاسخ پشتیبانی',
        'text' => 'پاسخ ادمین به تیکت شما مستقیم در تلگرام نمایش داده می‌شود.',
    ],
    [
        'title' => 'تأیید پرداخت',
        'text' => 'بعد از تأیید خرید یا تمدید، پیام تأیید با جزئیات اشتراک دریافت می‌کنید.',
    ],
    [
        'title' => 'اطلاع‌رسانی‌های پنل',
        'text' => 'پیام‌های مهم، کمپین‌ها و اطلاعیه‌های داشبورد در تلگرام برای شما ارسال می‌شود.',
    ],
    [
        'title' => 'منوی سریع',
        'text' => 'بدون ورود به سایت، از منوی پایین صفحه به بخش‌های اصلی دسترسی دارید.',
    ],
];

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>اتصال تلگرام</title>
<link rel="stylesheet" href="fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="telegram_ui.css?v=1">
</head>
<body class="telegramPage">

<div class="telegramApp">

<?php userBackBar('dashboard.php', 'اتصال تلگرام'); ?>

<section class="telegramHero">
<h1 class="telegramHeroTitle">ربات تلگرام پنل</h1>
<p class="telegramHeroText">با اتصال تلگرام، مدیریت اشتراک و اعلان‌های مهم را سریع‌تر دریافت کنید و دیگر نیازی به بررسی مداوم داشبورد نباشد.</p>
</section>

<section class="telegramStatusCard">
<div class="telegramStatusRow">
<span class="telegramStatusLabel">وضعیت اتصال</span>
<span class="telegramStatusValue<?php echo $linked ? ' is-on' : ''; ?>" id="tgStatusValue"><?php echo $linked ? 'متصل ✅' : 'غیرفعال'; ?></span>
</div>
<div class="telegramStatusMeta" id="tgStatusMeta"><?php
if($linked){
    $lines = [];

    if($telegramUsername !== ''){
        $lines[] = 'تلگرام: ' . $telegramUsername;
    }

    if($linkedAt !== ''){
        $lines[] = 'متصل شده: ' . $linkedAt;
    }

    echo $h(implode("\n", $lines));
}
else{
    echo 'هنوز تلگرام شما به پنل متصل نشده است.';
}
?></div>
</section>

<section class="telegramSection">
<h2 class="telegramSectionTitle">چه کمکی به شما می‌کند؟</h2>
<div class="telegramFeatureList">
<?php foreach($features as $feature){ ?>
<div class="telegramFeature">
<div class="telegramFeatureTitle"><?php echo $h($feature['title']); ?></div>
<div class="telegramFeatureText"><?php echo $h($feature['text']); ?></div>
</div>
<?php } ?>
</div>
</section>

<div class="telegramFlash" id="tgFlash"></div>

<div class="telegramActions" id="tgActions">
<?php if(!$botEnabled){ ?>
<p class="telegramHint">ربات تلگرام در حال حاضر توسط پشتیبانی فعال نشده است. لطفاً بعداً دوباره تلاش کنید.</p>
<?php } elseif($linked){ ?>
<button type="button" class="telegramBtn telegramBtn--primary" id="tgTestBtn">ارسال پیام تست</button>
<button type="button" class="telegramBtn telegramBtn--ghost" id="tgDisconnectBtn">قطع اتصال تلگرام</button>
<?php } else { ?>
<button type="button" class="telegramBtn telegramBtn--primary" id="tgConnectBtn">اتصال به ربات تلگرام</button>
<?php if($botUsername !== ''){ ?>
<p class="telegramHint">بعد از کلیک، ربات <?php echo $h('@' . ltrim($botUsername, '@')); ?> در تلگرام باز می‌شود. روی Start بزنید تا اتصال کامل شود.</p>
<?php } else { ?>
<p class="telegramHint">بعد از کلیک، صفحه ربات در تلگرام باز می‌شود. روی Start بزنید تا اتصال کامل شود.</p>
<?php } ?>
<?php } ?>
</div>

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

    function setLinkedState(data){
        var linked = !!data.linked;
        if(statusValue){
            statusValue.textContent = linked ? 'متصل ✅' : 'غیرفعال';
            statusValue.classList.toggle('is-on', linked);
        }

        if(statusMeta){
            if(linked){
                var lines = [];
                if(data.telegram_username){
                    lines.push('تلگرام: ' + data.telegram_username);
                }
                if(data.linked_at){
                    lines.push('متصل شده: ' + data.linked_at);
                }
                statusMeta.textContent = lines.join('\n');
            }
            else{
                statusMeta.textContent = 'هنوز تلگرام شما به پنل متصل نشده است.';
            }
        }
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

                showFlash('پیام تست به تلگرام شما ارسال شد ✅', 'success');
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

                setLinkedState({linked: false});
                showFlash('اتصال تلگرام قطع شد.', 'success');
                window.location.reload();
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

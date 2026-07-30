<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/user_nav.php';

$username = $_SESSION['user'];
$summary = couponGetUserSummary($username);

if(!$summary){
    die('کاربر یافت نشد');
}

$myCode = (string)($summary['referral_code'] ?? '');
$myLink = 'https://panel.ticketin.ir/register.php?ref=' . urlencode($myCode);
$inviteCount = intval($summary['successful_count'] ?? 0);
$reward = $summary['reward'] ?? [];
$rewardLabel = trim((string)($reward['label'] ?? ''));
$rewardActive = intval($reward['percent'] ?? 0) > 0;
$activeCodes = $summary['active_codes'] ?? [];

if($rewardLabel === ''){
    $rewardLabel = 'هنوز پاداشی فعال نشده';
}

$tiers = [
    ['need' => 3,  'title' => 'تخفیف ۲۰٪', 'desc' => '۳ دعوت موفق → یک کد ۲۰ درصدی'],
    ['need' => 5,  'title' => 'تخفیف ۴۰٪', 'desc' => '۵ دعوت موفق → یک کد ۴۰ درصدی'],
    ['need' => 10, 'title' => 'کد ۱۰۰٪', 'desc' => '۱۰ دعوت موفق → یک کد ۱۰۰ درصدی'],
    ['need' => 20, 'title' => '۳ کد ۱۰۰٪', 'desc' => '۲۰ دعوت موفق → سه کد ۱۰۰ درصدی'],
];

$nextNeed = null;
foreach($tiers as $tier){
    if($inviteCount < intval($tier['need'])){
        $nextNeed = intval($tier['need']);
        break;
    }
}

$progressPct = 100;
if($nextNeed !== null && $nextNeed > 0){
    $progressPct = max(0, min(100, round(($inviteCount / $nextNeed) * 100)));
}

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>دعوت دوستان</title>
<link rel="stylesheet" href="fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="coupon_ui.css?v=1">
</head>
<body class="couponPage">

<div class="couponApp">

<?php userBackBar('dashboard.php', 'دعوت دوستان'); ?>

<section class="couponSection">
<div class="couponCodeCard">
<div class="couponCodeLabel">کد دعوت شما</div>
<div class="couponCodeHero" id="refcode"><?php echo $h($myCode); ?></div>
<button type="button" class="couponBtn" id="copyCodeBtn">کپی کد</button>
</div>
</section>

<section class="couponSection">
<div class="couponLinkRow">
<div class="couponLinkMeta">
<div class="couponLinkLabel">لینک دعوت</div>
<div class="couponLinkValue" id="reflink" title="<?php echo $h($myLink); ?>"><?php echo $h($myLink); ?></div>
</div>
<button type="button" class="couponBtn couponBtn--ghost couponBtn--sm" id="copyLinkBtn">کپی</button>
</div>
</section>

<section class="couponSection">
<div class="couponProgressCard">
<div class="couponProgressTop">
<div class="couponProgressCount">
<?php echo $inviteCount; ?>
<span>دعوت موفق</span>
</div>
<div class="couponProgressHint">
<?php if($nextNeed !== null){ ?>
تا سطح بعد: <?php echo max(0, $nextNeed - $inviteCount); ?> دعوت
<?php } else { ?>
به بالاترین سطح رسیدید
<?php } ?>
</div>
</div>
<div class="couponProgressTrack" aria-hidden="true">
<div class="couponProgressFill" style="width:<?php echo (int)$progressPct; ?>%"></div>
</div>
<div class="couponProgressSub">ثبت‌نام با لینک/کد شما + حداقل یک خرید تاییدشده</div>
</div>
</section>

<section class="couponSection">
<div class="couponStatusPill <?php echo $rewardActive ? 'is-active' : ''; ?>">
<span class="couponStatusDot" aria-hidden="true"></span>
<span><b>پاداش فعال:</b> <?php echo $h($rewardLabel); ?></span>
</div>
</section>

<?php if(!empty($activeCodes)){ ?>
<section class="couponSection">
<div class="couponTierHead"><h2>کدهای تخفیف فعال</h2></div>
<div class="couponActiveList">
<?php foreach($activeCodes as $coupon){ ?>
<div class="couponActiveItem">
<div>
<div class="couponActiveCode"><?php echo $h($coupon['code'] ?? ''); ?></div>
<div class="couponActiveMeta">تخفیف <?php echo (int)($coupon['percent'] ?? 0); ?>٪ — یک‌بار مصرف</div>
</div>
<button
    type="button"
    class="couponBtn couponBtn--ghost couponBtn--sm"
    data-copy="<?php echo $h($coupon['code'] ?? ''); ?>"
>کپی</button>
</div>
<?php } ?>
</div>
</section>
<?php } ?>

<section class="couponSection">
<div class="couponTierHead"><h2>سطوح پاداش</h2></div>
<div class="couponTierList">
<?php foreach($tiers as $tier){
    $need = intval($tier['need']);
    $reached = $inviteCount >= $need;
?>
<div class="couponTierRow <?php echo $reached ? 'is-reached' : ''; ?>">
<div class="couponTierBadge"><?php echo $need; ?></div>
<div class="couponTierBody">
<div class="couponTierTitle"><?php echo $h($tier['title']); ?></div>
<div class="couponTierDesc"><?php echo $h($tier['desc']); ?></div>
</div>
</div>
<?php } ?>
</div>
</section>

<div class="couponHint">
با استفاده از هر کد تخفیف، شمارش دعوت‌ها از صفر شروع می‌شود. دعوت موفق یعنی کاربر با لینک یا کد شما ثبت‌نام کرده و حداقل یک خرید تاییدشده داشته باشد.
</div>

</div>

<div class="couponToast" id="couponToast">کپی شد</div>

<script>
(function(){
    var toast = document.getElementById('couponToast');
    var toastTimer = null;

    function showToast(msg){
        if(!toast){ return; }
        toast.textContent = msg || 'کپی شد';
        toast.classList.add('is-show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function(){
            toast.classList.remove('is-show');
        }, 1600);
    }

    function copyValue(text){
        if(!text){ return; }
        if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(text).then(function(){
                showToast('کپی شد');
            }).catch(function(){
                fallbackCopy(text);
            });
            return;
        }
        fallbackCopy(text);
    }

    function fallbackCopy(text){
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try{
            document.execCommand('copy');
            showToast('کپی شد');
        }catch(e){
            showToast('کپی ناموفق بود');
        }
        document.body.removeChild(area);
    }

    var codeBtn = document.getElementById('copyCodeBtn');
    var linkBtn = document.getElementById('copyLinkBtn');
    var codeEl = document.getElementById('refcode');
    var linkEl = document.getElementById('reflink');

    if(codeBtn && codeEl){
        codeBtn.addEventListener('click', function(){
            copyValue(codeEl.textContent.trim());
        });
    }
    if(linkBtn && linkEl){
        linkBtn.addEventListener('click', function(){
            copyValue(linkEl.getAttribute('title') || linkEl.textContent.trim());
        });
    }

    document.querySelectorAll('[data-copy]').forEach(function(btn){
        btn.addEventListener('click', function(){
            copyValue(btn.getAttribute('data-copy') || '');
        });
    });
})();
</script>

</body>
</html>

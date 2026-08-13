<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/mobile_verify_lib.php';
mobileVerifyGuardRedirectIfNeeded((string)$_SESSION['user']);

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
    [
        'need' => 3,
        'title' => '۳ دعوت موفق',
        'desc' => 'یک کد تخفیف ۲۰٪',
        'chip' => '۲۰٪',
    ],
    [
        'need' => 5,
        'title' => '۵ دعوت موفق',
        'desc' => 'یک کد تخفیف ۴۰٪',
        'chip' => '۴۰٪',
    ],
    [
        'need' => 10,
        'title' => '۱۰ دعوت موفق',
        'desc' => 'یک کد تخفیف ۱۰۰٪',
        'chip' => '۱۰۰٪',
    ],
    [
        'need' => 20,
        'title' => '۲۰ دعوت موفق',
        'desc' => '۳ کد ۱۰۰٪ (هر کد یک‌بار مصرف)',
        'chip' => '۳×۱۰۰٪',
    ],
];

$nextNeed = null;
foreach($tiers as $tier){
    if($inviteCount < intval($tier['need'])){
        $nextNeed = intval($tier['need']);
        break;
    }
}

$progressPct = 100;
$progressDenom = $nextNeed ?? 20;
if($nextNeed !== null && $nextNeed > 0){
    $progressPct = max(0, min(100, round(($inviteCount / $nextNeed) * 100)));
}

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$fa = static function($value){
    return strtr((string)$value, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
};

$iconCopy = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconLink = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 5.93" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.4a5 5 0 0 0 7.07 7.07L14 18.07" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconUsers = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9.5" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M19 19v-1a3.5 3.5 0 0 0-2.5-3.35M16.5 4.7a3 3 0 0 1 0 5.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconGift = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 10v10M4 14h16M12 10c-2.2 0-4-1.3-4-3s1.2-2.2 2.5-1.3C11.4 6.4 12 8 12 10c0-2 .6-3.6 1.5-4.3C14.8 4.8 16 5.6 16 7s-1.8 3-4 3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$iconBulb = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.8c.7.5 1.1 1.2 1.2 2.2h4.6c.1-1 .5-1.7 1.2-2.2A6 6 0 0 0 12 3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>دعوت دوستان</title>
<link rel="stylesheet" href="fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="coupon_ui.css?v=3">
</head>
<body class="couponPage">

<div class="couponApp">

<?php userBackBar('dashboard.php', 'دعوت دوستان'); ?>

<section class="couponSection">
<div class="couponCodeCard">
<div class="couponCodeLabel">کد دعوت شما</div>
<div class="couponCodeBox">
<div class="couponCodeHero" id="refcode"><?php echo $h($myCode); ?></div>
</div>
<button type="button" class="couponBtn" id="copyCodeBtn">
<?php echo $iconCopy; ?>
<span>کپی کد</span>
</button>
</div>
</section>

<section class="couponSection">
<div class="couponLinkRow">
<div class="couponLinkMeta">
<span class="couponLinkIcon" aria-hidden="true"><?php echo $iconLink; ?></span>
<div class="couponLinkText">
<div class="couponLinkLabel">لینک دعوت</div>
<div class="couponLinkValue" id="reflink" title="<?php echo $h($myLink); ?>"><?php echo $h($myLink); ?></div>
</div>
</div>
<button type="button" class="couponBtn couponBtn--ghost couponBtn--sm" id="copyLinkBtn">
<?php echo $iconCopy; ?>
<span>کپی لینک</span>
</button>
</div>
</section>

<section class="couponSection">
<div class="couponProgressCard">
<div class="couponProgressTop">
<div class="couponProgressCount">
<span class="couponProgressIcon" aria-hidden="true"><?php echo $iconUsers; ?></span>
<span class="couponProgressNum"><?php echo $fa($inviteCount); ?></span>
<span>دعوت موفق</span>
</div>
<div class="couponProgressHint">
<?php if($nextNeed !== null){ ?>
تا سطح بعدی: <?php echo $fa(max(0, $nextNeed - $inviteCount)); ?> دعوت دیگر
<?php } else { ?>
به بالاترین سطح رسیدید
<?php } ?>
</div>
</div>
<div class="couponProgressMeta">
<span><?php echo $fa($inviteCount); ?> / <?php echo $fa($progressDenom); ?> دعوت</span>
</div>
<div class="couponProgressTrack" aria-hidden="true">
<div class="couponProgressFill" style="width:<?php echo (int)$progressPct; ?>%"></div>
</div>
</div>
</section>

<section class="couponSection">
<div class="couponStatusRow">
<div class="couponStatusLabel">
<span class="couponStatusIcon" aria-hidden="true"><?php echo $iconGift; ?></span>
<span>وضعیت پاداش</span>
</div>
<div class="couponStatusPill <?php echo $rewardActive ? 'is-active' : ''; ?>">
<span class="couponStatusDot" aria-hidden="true"></span>
<span><?php echo $h($rewardLabel); ?></span>
</div>
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
<div class="couponActiveMeta">تخفیف <?php echo $fa((int)($coupon['percent'] ?? 0)); ?>٪ — یک‌بار مصرف</div>
</div>
<button
    type="button"
    class="couponBtn couponBtn--ghost couponBtn--sm"
    data-copy="<?php echo $h($coupon['code'] ?? ''); ?>"
><?php echo $iconCopy; ?><span>کپی</span></button>
</div>
<?php } ?>
</div>
</section>
<?php } ?>

<section class="couponSection">
<div class="couponTierHead"><h2>سطوح و پاداش‌ها</h2></div>
<div class="couponTierList">
<?php foreach($tiers as $tier){
    $need = intval($tier['need']);
    $reached = $inviteCount >= $need;
    $isNext = (!$reached && $nextNeed === $need);
    $rowClass = 'couponTierRow';
    if($reached){
        $rowClass .= ' is-reached';
    } elseif($isNext){
        $rowClass .= ' is-next';
    } else {
        $rowClass .= ' is-locked';
    }
?>
<div class="<?php echo $rowClass; ?>">
<div class="couponTierBadge"><?php echo $fa($need); ?></div>
<div class="couponTierBody">
<div class="couponTierTitle"><?php echo $h($tier['title']); ?></div>
<div class="couponTierDesc"><?php echo $h($tier['desc']); ?></div>
</div>
<div class="couponTierChip"><?php echo $h($tier['chip']); ?></div>
</div>
<?php } ?>
</div>
</section>

<div class="couponHint">
<span class="couponHintIcon" aria-hidden="true"><?php echo $iconBulb; ?></span>
<span>هر دعوت باید با خرید و فعال‌سازی اشتراک توسط دوست شما تکمیل شود تا برای شما ثبت گردد. با استفاده از هر کد تخفیف، شمارش دعوت‌ها از صفر شروع می‌شود.</span>
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

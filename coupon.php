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
    $rewardLabel = 'هنوز پاداشی فعال نشده است';
}

function couponFaNum($value){
    return strtr((string)$value, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
}

/* Tier thresholds mirror couponRewardForCount() — presentation only */
$tierDefs = [
    ['need' => 3, 'percent' => 20, 'qty' => 1],
    ['need' => 5, 'percent' => 40, 'qty' => 1],
    ['need' => 10, 'percent' => 100, 'qty' => 1],
    ['need' => 20, 'percent' => 100, 'qty' => 3],
];

$tiers = [];
foreach($tierDefs as $i => $def){
    $need = intval($def['need']);
    $percent = intval($def['percent']);
    $qty = intval($def['qty']);
    if($qty > 1){
        $chip = couponFaNum($qty) . '×' . couponFaNum($percent) . '٪';
        $desc = couponFaNum($qty) . ' کد ' . couponFaNum($percent) . '٪ (هر کد یک‌بار مصرف)';
    } else {
        $chip = couponFaNum($percent) . '٪';
        $desc = 'یک کد تخفیف ' . couponFaNum($percent) . '٪';
    }
    $tiers[] = [
        'need' => $need,
        'percent' => $percent,
        'qty' => $qty,
        'title' => couponFaNum($need) . ' دعوت موفق',
        'desc' => $desc,
        'chip' => $chip,
    ];
}

$nextNeed = null;
$nextTier = null;
foreach($tiers as $tier){
    if($inviteCount < intval($tier['need'])){
        $nextNeed = intval($tier['need']);
        $nextTier = $tier;
        break;
    }
}

$progressPct = 100;
$progressDenom = $nextNeed ?? intval($tiers[count($tiers) - 1]['need']);
if($nextNeed !== null && $nextNeed > 0){
    $progressPct = max(0, min(100, round(($inviteCount / $nextNeed) * 100)));
}

$heroTier = $nextTier ?? $tiers[count($tiers) - 1];
$heroNeed = intval($heroTier['need']);
$heroPercent = intval($heroTier['percent']);
$heroQty = intval($heroTier['qty']);
if($heroQty > 1){
    $heroDiscountMain = couponFaNum($heroQty) . '×' . couponFaNum($heroPercent) . '٪';
    $heroDiscountSub = 'تخفیف';
} else {
    $heroDiscountMain = couponFaNum($heroPercent) . '٪';
    $heroDiscountSub = 'تخفیف';
}

$codeDisplay = trim(implode(' ', preg_split('//u', strtoupper($myCode), -1, PREG_SPLIT_NO_EMPTY)));

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$fa = static function($value){
    return couponFaNum($value);
};

$iconCopy = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconLink = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 5.93" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.4a5 5 0 0 0 7.07 7.07L14 18.07" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconUsers = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9.5" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M19 19v-1a3.5 3.5 0 0 0-2.5-3.35M16.5 4.7a3 3 0 0 1 0 5.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$iconGift = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 10v10M4 14h16M12 10c-2.2 0-4-1.3-4-3s1.2-2.2 2.5-1.3C11.4 6.4 12 8 12 10c0-2 .6-3.6 1.5-4.3C14.8 4.8 16 5.6 16 7s-1.8 3-4 3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$iconBulb = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.8c.7.5 1.1 1.2 1.2 2.2h4.6c.1-1 .5-1.7 1.2-2.2A6 6 0 0 0 12 3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$iconTag = '<svg class="couponIcon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12l-8 8-4-4V8h8l4 4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="14.5" cy="9.5" r="1.2" fill="currentColor"/></svg>';

$showStepper = $progressDenom > 0 && $progressDenom <= 10;

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>باشگاه معرفی</title>
<link rel="stylesheet" href="fonts.css">
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="coupon_ui.css?v=4">
</head>
<body class="couponPage">

<div class="couponApp">

<?php userBackBar('dashboard.php', 'باشگاه معرفی'); ?>

<header class="couponPageIntro">
<p class="couponPageSubtitle">دوستان خود را دعوت کنید و از تخفیف‌های ویژه بهره‌مند شوید.</p>
</header>

<section class="couponSection">
<div class="couponHero">
<div class="couponHeroContent">
<div class="couponHeroBadge">
<span class="couponHeroBadgeIcon" aria-hidden="true"><?php echo $iconGift; ?></span>
<span>پاداش شما</span>
</div>
<div class="couponHeroDiscount">
<span class="couponHeroDiscountMain"><?php echo $h($heroDiscountMain); ?></span>
<span class="couponHeroDiscountSub"><?php echo $h($heroDiscountSub); ?></span>
</div>
<p class="couponHeroMeta">
<?php if($nextNeed !== null){ ?>
پس از <?php echo $fa($heroNeed); ?> دعوت موفق
<?php } else { ?>
بالاترین سطح پاداش
<?php } ?>
</p>
</div>
<div class="couponHeroArt" aria-hidden="true">
<svg class="couponHeroGiftSvg" viewBox="0 0 160 140" xmlns="http://www.w3.org/2000/svg">
<defs>
<linearGradient id="giftBox" x1="0%" y1="0%" x2="100%" y2="100%">
<stop offset="0%" stop-color="#1e293b"/>
<stop offset="100%" stop-color="#0f172a"/>
</linearGradient>
<linearGradient id="giftRibbon" x1="0%" y1="0%" x2="0%" y2="100%">
<stop offset="0%" stop-color="#4ade80"/>
<stop offset="100%" stop-color="#16a34a"/>
</linearGradient>
<filter id="giftGlow" x="-20%" y="-20%" width="140%" height="140%">
<feGaussianBlur stdDeviation="6" result="blur"/>
<feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
</filter>
</defs>
<ellipse cx="80" cy="118" rx="52" ry="10" fill="rgba(34,197,94,.12)"/>
<rect x="38" y="52" width="84" height="62" rx="10" fill="url(#giftBox)" stroke="rgba(34,197,94,.35)" stroke-width="1.5"/>
<rect x="74" y="52" width="12" height="62" fill="url(#giftRibbon)" opacity=".95"/>
<rect x="38" y="72" width="84" height="12" fill="url(#giftRibbon)" opacity=".95"/>
<path d="M80 52c-14 0-22-8-22-16 0-6 5-10 12-10 5 0 9 3 10 8 1-5 5-8 10-8 7 0 12 4 12 10 0 8-8 16-22 16z" fill="url(#giftRibbon)" filter="url(#giftGlow)"/>
<text x="80" y="92" text-anchor="middle" fill="#86efac" font-size="22" font-weight="700" font-family="tahoma,sans-serif">٪</text>
</svg>
</div>
</div>
</section>

<section class="couponSection">
<div class="couponFieldCard">
<div class="couponFieldHead">
<span class="couponFieldIcon couponFieldIcon--green" aria-hidden="true"><?php echo $iconTag; ?></span>
<span class="couponFieldLabel">کد دعوت شما</span>
</div>
<div class="couponFieldRow">
<div class="couponCodeInput" id="refcode" data-copy="<?php echo $h($myCode); ?>"><?php echo $h($codeDisplay); ?></div>
<button type="button" class="couponBtn couponBtn--outline couponBtn--compact" id="copyCodeBtn">
<?php echo $iconCopy; ?>
<span>کپی کد</span>
</button>
</div>
</div>
</section>

<section class="couponSection">
<div class="couponFieldCard">
<div class="couponFieldHead">
<span class="couponFieldIcon couponFieldIcon--green" aria-hidden="true"><?php echo $iconLink; ?></span>
<span class="couponFieldLabel">لینک دعوت شما</span>
</div>
<div class="couponFieldRow couponFieldRow--stack">
<div class="couponLinkInput" id="reflink" title="<?php echo $h($myLink); ?>"><?php echo $h($myLink); ?></div>
<button type="button" class="couponBtn couponBtn--outline couponBtn--compact" id="copyLinkBtn">
<?php echo $iconCopy; ?>
<span>کپی لینک</span>
</button>
</div>
</div>
</section>

<section class="couponSection">
<div class="couponProgressCard">
<div class="couponProgressHead">
<div class="couponProgressTitleRow">
<span class="couponProgressIcon" aria-hidden="true"><?php echo $iconUsers; ?></span>
<div>
<div class="couponProgressTitle">پیشرفت دعوت‌ها</div>
<div class="couponProgressSubtitle"><?php echo $fa($inviteCount); ?> دعوت موفق از <?php echo $fa($progressDenom); ?> دعوت</div>
</div>
</div>
<div class="couponProgressHint">
<?php if($nextNeed !== null){ ?>
تا سطح بعدی: <?php echo $fa(max(0, $nextNeed - $inviteCount)); ?> دعوت دیگر
<?php } else { ?>
به بالاترین سطح رسیدید
<?php } ?>
</div>
</div>

<?php if($showStepper){ ?>
<ol class="couponStepper" aria-label="پیشرفت دعوت">
<?php for($step = 1; $step <= $progressDenom; $step++){
    $done = $inviteCount >= $step;
    $current = !$done && $inviteCount === $step - 1;
    $stepClass = 'couponStep';
    if($done){
        $stepClass .= ' is-done';
    } elseif($current){
        $stepClass .= ' is-current';
    }
?>
<li class="<?php echo $stepClass; ?>">
<span class="couponStepDot" aria-hidden="true"><?php echo $done ? '✓' : $fa($step); ?></span>
<span class="couponStepLabel">دعوت <?php echo $fa($step); ?></span>
</li>
<?php } ?>
</ol>
<?php } else { ?>
<div class="couponProgressTrack" aria-hidden="true">
<div class="couponProgressFill" style="width:<?php echo (int)$progressPct; ?>%"></div>
</div>
<?php } ?>
</div>
</section>

<section class="couponSection">
<div class="couponRewardCard">
<div class="couponRewardMain">
<span class="couponRewardIconWrap" aria-hidden="true"><?php echo $iconGift; ?></span>
<div>
<div class="couponRewardTitle">وضعیت پاداش</div>
<div class="couponRewardText"><?php echo $h($rewardLabel); ?></div>
</div>
</div>
<div class="couponStatusPill <?php echo $rewardActive ? 'is-active' : ''; ?>">
<span class="couponStatusDot" aria-hidden="true"></span>
<span><?php echo $rewardActive ? 'پاداش فعال' : 'در انتظار تکمیل'; ?></span>
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
    $chipClass = 'couponTierChip';
    if($isNext || $reached){
        $chipClass .= ' couponTierChip--hero';
    }
?>
<div class="<?php echo $rowClass; ?>">
<div class="couponTierBadge"><?php echo $fa($need); ?></div>
<div class="couponTierBody">
<div class="couponTierTitle"><?php echo $h($tier['title']); ?></div>
<div class="couponTierDesc"><?php echo $h($tier['desc']); ?></div>
</div>
<div class="<?php echo $chipClass; ?>"><?php echo $h($tier['chip']); ?></div>
</div>
<?php } ?>
</div>
</section>

<div class="couponHint">
<span class="couponHintIcon" aria-hidden="true"><?php echo $iconBulb; ?></span>
<div class="couponHintBody">
<div class="couponHintTitle">سطوح و پاداش‌ها</div>
<p>هر دعوت موفق شما را یک قدم به پاداش نزدیک‌تر می‌کند. پس از رسیدن به هر سطح و تکمیل ثبت‌نام دوستان، کد تخفیف متناسب برای شما فعال می‌شود.</p>
<p class="couponHintFine">هر دعوت باید با خرید و فعال‌سازی اشتراک توسط دوست شما تکمیل شود. با استفاده از هر کد تخفیف، شمارش دعوت‌ها از صفر شروع می‌شود.</p>
</div>
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
            copyValue(codeEl.getAttribute('data-copy') || codeEl.textContent.replace(/\s+/g, '').trim());
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

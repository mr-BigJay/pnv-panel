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

$myCode = $summary['referral_code'];
$myLink = 'https://panel.ticketin.ir/register.php?ref=' . urlencode($myCode);
$inviteCount = $summary['successful_count'];
$reward = $summary['reward']['label'] ?? 'هنوز پاداشی فعال نشده';
$activeCodes = $summary['active_codes'] ?? [];

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>کوپن تخفیف</title>
<link rel="stylesheet" href="user_nav.css?v=1">
<link rel="stylesheet" href="fonts.css">
<link rel="stylesheet" href="user_panel.css?v=6">
<style>
.couponPageBox{margin-bottom:14px;}
.couponCodeItem{background:#0f172a;padding:14px;border-radius:12px;margin-top:10px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
.couponCodeText{font-size:18px;font-weight:700;letter-spacing:2px;word-break:break-all;}
.couponCodeMeta{font-size:13px;color:#94a3b8;}
.couponStats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;}
.couponStat{background:#0f172a;padding:14px;border-radius:12px;text-align:center;}
.couponStatNum{font-size:24px;font-weight:700;margin-bottom:6px;}
.couponStatLabel{font-size:12px;color:#94a3b8;}
.couponLevels{background:#0f172a;padding:16px;border-radius:12px;line-height:1.9;font-size:14px;margin-top:12px;}
.couponHint{font-size:13px;color:#94a3b8;line-height:1.8;margin-top:10px;}
.userPageWrap .userBackBar{margin-bottom:14px;}
</style>
</head>
<body class="userPage userPanel--dashboard">

<div class="userPageWrap">

<?php userBackBar('dashboard.php', 'سیستم دعوت دوستان'); ?>

<div class="userPageBox couponPageBox">

<div class="userPageLabel">کد دعوت شما</div>
<div class="userPageRefbox" id="refcode"><?php echo htmlspecialchars($myCode, ENT_QUOTES, 'UTF-8'); ?></div>
<button type="button" class="userPageBtn" onclick="copyText('refcode')">کپی کد دعوت</button>
</div>

<div class="userPageBox couponPageBox">
<div class="userPageLabel">لینک دعوت شما</div>
<div class="userPageRefbox" id="reflink"><?php echo htmlspecialchars($myLink, ENT_QUOTES, 'UTF-8'); ?></div>
<button type="button" class="userPageBtn" onclick="copyText('reflink')">کپی لینک دعوت</button>

<div class="couponStats">
<div class="couponStat">
<div class="couponStatNum"><?php echo (int)$inviteCount; ?></div>
<div class="couponStatLabel">دعوت موفق (ثبت‌نام + خرید تایید‌شده)</div>
</div>
<div class="couponStat">
<div class="couponStatNum"><?php echo htmlspecialchars($myCode, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="couponStatLabel">کد معرف شما</div>
</div>
</div>

<div class="userPageRefbox" style="margin-top:12px;">
<b>پاداش فعال:</b><br><br><?php echo htmlspecialchars($reward, ENT_QUOTES, 'UTF-8'); ?>
</div>

<?php if(!empty($activeCodes)){ ?>
<div class="userPageLabel" style="margin-top:16px;">کدهای تخفیف فعال شما</div>
<?php foreach($activeCodes as $coupon){ ?>
<div class="couponCodeItem">
<div>
<div class="couponCodeText"><?php echo htmlspecialchars($coupon['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
<div class="couponCodeMeta">تخفیف <?php echo (int)($coupon['percent'] ?? 0); ?>٪ — یک‌بار مصرف</div>
</div>
<button type="button" class="userPageBtn" style="width:auto;min-width:90px;height:44px;padding:0 14px;" onclick="copyTextValue('<?php echo htmlspecialchars($coupon['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">کپی</button>
</div>
<?php } ?>
<?php } ?>

<div class="couponLevels">
<b>سطوح پاداش:</b><br><br>
3 دعوت موفق → تخفیف 20 درصدی<br>
5 دعوت موفق → تخفیف 40 درصدی<br>
10 دعوت موفق → کد تخفیف 100 درصدی<br>
20 دعوت موفق → 3 کد تخفیف 100 درصدی
</div>

<div class="couponHint">
با استفاده از هر کد تخفیف، شمارش دعوت‌ها از صفر شروع می‌شود. دعوت موفق یعنی کاربر با لینک/کد شما ثبت‌نام کرده و حداقل یک خرید تایید‌شده داشته باشد.
</div>
</div>

</div>

<script>
function copyText(id){
    navigator.clipboard.writeText(document.getElementById(id).innerText);
    alert('کپی شد');
}
function copyTextValue(text){
    navigator.clipboard.writeText(text);
    alert('کپی شد');
}
</script>

</body>
</html>

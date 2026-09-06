<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/campaign_ui.php';
require_once __DIR__ . '/../campaign_lib.php';
require_once __DIR__ . '/../coupon_lib.php';

pnvAdminRequireAuth();

$flash = '';
$flashOk = isset($_GET['saved']) && ($_GET['saved'] ?? '') === '1';
$settings = referralLoadSettings();
$tiers = referralLoadTiers();
$stats = referralAdminStats();
$coupons = couponLoadCoupons();
$q = trim((string)($_GET['q'] ?? ''));

if(isset($_POST['save_referral_settings'])){
    $enabled = !empty($_POST['enabled']);
    $startsAt = campaignParseDateTime($_POST['starts_at'] ?? '');
    $expiresAt = campaignParseDateTime($_POST['expires_at'] ?? '');
    $inviteBaseUrl = trim((string)($_POST['invite_base_url'] ?? ''));
    $hintText = trim((string)($_POST['hint_text'] ?? ''));

    if($inviteBaseUrl === ''){
        $flash = 'آدرس پایه لینک دعوت الزامی است';
    }
    elseif($startsAt > 0 && $expiresAt > 0 && $expiresAt <= $startsAt){
        $flash = 'تاریخ پایان باید بعد از تاریخ شروع باشد';
    }
    else{
        $saved = referralSaveSettings([
            'enabled' => $enabled,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'invite_base_url' => $inviteBaseUrl,
            'hint_text' => $hintText !== '' ? $hintText : referralDefaultSettings()['hint_text'],
        ]);

        if(!$saved){
            $flash = 'خطا در ذخیره تنظیمات برنامه دعوت';
        }
        else{
            header('Location: ' . pnvAdminUrl('campaign-referral.php?saved=1'));
            exit;
        }
    }
}

if(isset($_POST['save_referral_tiers'])){
    $minInvites = $_POST['tier_min_invites'] ?? [];
    $percents = $_POST['tier_percent'] ?? [];
    $qtys = $_POST['tier_qty'] ?? [];
    $titles = $_POST['tier_title'] ?? [];
    $descs = $_POST['tier_desc'] ?? [];
    $chips = $_POST['tier_chip'] ?? [];
    $rows = [];
    $count = max(
        count($minInvites),
        count($percents),
        count($qtys),
        count($titles),
        count($descs),
        count($chips)
    );

    for($i = 0; $i < $count; $i++){
        $min = intval($minInvites[$i] ?? 0);
        $percent = intval($percents[$i] ?? 0);

        if($min <= 0 || $percent <= 0){
            continue;
        }

        $rows[] = [
            'min_invites' => $min,
            'percent' => $percent,
            'qty' => max(1, intval($qtys[$i] ?? 1)),
            'title' => trim((string)($titles[$i] ?? '')),
            'desc' => trim((string)($descs[$i] ?? '')),
            'chip' => trim((string)($chips[$i] ?? '')),
        ];
    }

    if(empty($rows)){
        $flash = 'حداقل یک سطح پاداش معتبر لازم است';
    }
    else{
        $saved = referralSaveTiers($rows);

        if(!$saved){
            $flash = 'خطا در ذخیره سطوح پاداش';
        }
        else{
            header('Location: ' . pnvAdminUrl('campaign-referral.php?saved=1#tiers'));
            exit;
        }
    }
}

$settings = referralLoadSettings();
$tiers = referralLoadTiers();

if(function_exists('couponSyncUsedFromApprovedPayments')){
    couponSyncUsedFromApprovedPayments();
}

$stats = referralAdminStats();
$coupons = couponLoadCoupons();

if($q !== ''){
    $coupons = array_values(array_filter($coupons, static function($row) use ($q){
        $hay = strtolower(
            ($row['code'] ?? '')
            . ' '
            . ($row['owner'] ?? '')
            . ' '
            . ($row['used_by'] ?? '')
        );
        return strpos($hay, strtolower($q)) !== false;
    }));
}

usort($coupons, static function($a, $b){
    return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
});

function referralAdminDateText($ts){
    $ts = intval($ts);

    if($ts <= 0){
        return '—';
    }

    return campaignFormatDateTime($ts);
}

function referralAdminCouponStatus($row){
    if(!empty($row['used'])){
        $by = trim((string)($row['used_by'] ?? ''));

        if($by === '_reset' || $by === '_expired_tier'){
            return ['label' => 'باطل‌شده', 'class' => 'is-inactive'];
        }

        return ['label' => 'منقضی (مصرف‌شده)', 'class' => 'is-inactive'];
    }

    return ['label' => 'فعال', 'class' => 'is-active'];
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>برنامه دعوت</title>
<?php campaignAdminPageHead(true); ?>
<style>
.referralStats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:14px}
.referralStat{padding:12px;border:1px solid #334155;border-radius:14px;background:#141b26;text-align:center}
.referralStatNum{font-size:24px;font-weight:700;color:#34d399;margin-bottom:4px}
.referralStatLabel{font-size:11px;color:#94a3b8;line-height:1.6}
.referralStatusBanner{margin-bottom:14px;padding:12px 14px;border-radius:14px;font-size:13px;line-height:1.8}
.referralStatusBanner.is-active{background:#14532d;color:#bbf7d0;border:1px solid rgba(52,211,153,.35)}
.referralStatusBanner.is-inactive{background:#334155;color:#cbd5e1;border:1px solid #475569}
.referralTierEditor{display:flex;flex-direction:column;gap:10px}
.referralTierRow{padding:12px;border:1px solid #334155;border-radius:14px;background:#141b26}
.referralTierRowHead{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.referralTierRowTitle{font-size:13px;font-weight:700;color:#e2e8f0}
.referralTierRemove{padding:6px 10px;border:1px solid rgba(248,113,113,.35);border-radius:10px;background:transparent;color:#fca5a5;font-family:inherit;font-size:12px;cursor:pointer}
.referralTierAdd{width:100%;padding:12px;border:1px dashed #475569;border-radius:14px;background:transparent;color:#cbd5e1;font-family:inherit;font-size:13px;cursor:pointer}
.referralPreview{margin-top:10px;padding:12px 14px;border:1px solid #334155;border-radius:14px;background:#141b26;font-size:12px;color:#94a3b8;line-height:1.9}
.referralPreview strong{color:#e2e8f0}
@media(max-width:640px){.referralStats{grid-template-columns:1fr}}
</style>
</head>
<body class="<?php echo campaignAdminBodyClass(); ?>">
<div class="campaignShell">

<?php campaignAdminNav('referral'); ?>

<?php if($flashOk){ ?>
<div class="campaignFlash is-success">تغییرات ذخیره شد.</div>
<?php } elseif($flash !== ''){ ?>
<div class="campaignFlash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<div class="referralStatusBanner <?php echo $stats['program_active'] ? 'is-active' : 'is-inactive'; ?>">
<?php if($stats['program_active']){ ?>
برنامه دعوت فعال است — کاربران در صفحه «دعوت دوستان» سطوح زیر را می‌بینند.
<?php } else { ?>
<?php echo htmlspecialchars(referralProgramStatusText() ?: 'برنامه دعوت غیرفعال است', ENT_QUOTES, 'UTF-8'); ?>
<?php } ?>
</div>

<div class="campaignCard">
<h2 class="campaignCardTitle" style="margin:0 0 14px">آمار کدهای پاداش</h2>
<div class="referralStats">
<div class="referralStat"><div class="referralStatNum"><?php echo (int)$stats['issued']; ?></div><div class="referralStatLabel">کل صادرشده</div></div>
<div class="referralStat"><div class="referralStatNum"><?php echo (int)$stats['active']; ?></div><div class="referralStatLabel">فعال (استفاده‌نشده)</div></div>
<div class="referralStat"><div class="referralStatNum"><?php echo (int)$stats['used']; ?></div><div class="referralStatLabel">مصرف یا باطل‌شده</div></div>
<div class="referralStat"><div class="referralStatNum"><?php echo (int)$stats['tiers']; ?></div><div class="referralStatLabel">سطح پاداش</div></div>
</div>
</div>

<form method="post" class="campaignCard">
<div class="campaignCardHead">
<h2 class="campaignCardTitle">تنظیمات برنامه</h2>
<span class="campaignCardIcon"><?php echo campaignIconCalendar(); ?></span>
</div>

<div class="campaignToggleRow campaignSection">
<div class="campaignToggleText">فعال بودن برنامه دعوت در پنل کاربر</div>
<label class="campaignToggle">
<input type="checkbox" name="enabled" value="1" <?php echo !empty($settings['enabled']) ? 'checked' : ''; ?>>
<span class="campaignToggleTrack"></span>
</label>
</div>

<div class="campaignGrid2 campaignSection">
<div class="campaignField">
<label class="campaignLabel">شروع (اختیاری)</label>
<?php campaignJalaliDateTimeInput('starts_at', intval($settings['starts_at'] ?? 0)); ?>
<div class="campaignHint">خالی = از همین الان</div>
</div>
<div class="campaignField">
<label class="campaignLabel">پایان (اختیاری)</label>
<?php campaignJalaliDateTimeInput('expires_at', intval($settings['expires_at'] ?? 0)); ?>
<div class="campaignHint">برای کمپین چندروزه — مثلاً ۳ روز</div>
</div>
</div>

<div class="campaignField">
<label class="campaignLabel">آدرس پایه لینک دعوت</label>
<input class="campaignInput" name="invite_base_url" value="<?php echo htmlspecialchars((string)($settings['invite_base_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" dir="ltr">
<div class="campaignHint">کد دعوت کاربر به انتهای این آدرس اضافه می‌شود</div>
</div>

<div class="campaignField">
<label class="campaignLabel">متن راهنمای پایین صفحه کاربر</label>
<textarea class="campaignTextarea" name="hint_text"><?php echo htmlspecialchars((string)($settings['hint_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>

<button type="submit" class="campaignSubmit" name="save_referral_settings" value="1">ذخیره تنظیمات</button>
</form>

<form method="post" class="campaignCard" id="tiers">
<div class="campaignCardHead">
<h2 class="campaignCardTitle">سطوح پاداش (صفحه coupon.php)</h2>
<span class="campaignCardIcon"><?php echo campaignIconTicket(); ?></span>
</div>

<p class="campaignHint campaignSection">مثال: برای «۳ نفر در ۵ روز = ۱۰۰٪» یک سطح با حداقل ۳ دعوت و ۱۰۰٪ تعریف کنید و تاریخ پایان را در تنظیمات بالا بگذارید.</p>

<div class="referralTierEditor" id="referralTierEditor">
<?php foreach($tiers as $index => $tier){ ?>
<div class="referralTierRow" data-tier-row>
<div class="referralTierRowHead">
<div class="referralTierRowTitle">سطح <?php echo (int)($index + 1); ?></div>
<button type="button" class="referralTierRemove" data-tier-remove>حذف</button>
</div>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">حداقل دعوت موفق</label>
<input class="campaignInput" type="number" min="1" name="tier_min_invites[]" value="<?php echo (int)($tier['min_invites'] ?? 0); ?>">
</div>
<div class="campaignField">
<label class="campaignLabel">درصد تخفیف</label>
<input class="campaignInput" type="number" min="1" max="100" name="tier_percent[]" value="<?php echo (int)($tier['percent'] ?? 0); ?>">
</div>
</div>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">تعداد کد</label>
<input class="campaignInput" type="number" min="1" name="tier_qty[]" value="<?php echo (int)($tier['qty'] ?? 1); ?>">
</div>
<div class="campaignField">
<label class="campaignLabel">برچسب کوتاه</label>
<input class="campaignInput" name="tier_chip[]" value="<?php echo htmlspecialchars((string)($tier['chip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
</div>
</div>
<div class="campaignField">
<label class="campaignLabel">عنوان</label>
<input class="campaignInput" name="tier_title[]" value="<?php echo htmlspecialchars((string)($tier['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
</div>
<div class="campaignField">
<label class="campaignLabel">توضیح پاداش</label>
<input class="campaignInput" name="tier_desc[]" value="<?php echo htmlspecialchars((string)($tier['desc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
</div>
</div>
<?php } ?>
</div>

<button type="button" class="referralTierAdd" id="referralTierAdd">+ افزودن سطح</button>

<div class="referralPreview">
<strong>پیش‌نمایش:</strong>
<?php foreach($tiers as $tier){ ?>
<br><?php echo (int)($tier['min_invites'] ?? 0); ?> دعوت → <?php echo (int)($tier['percent'] ?? 0); ?>٪ (<?php echo (int)($tier['qty'] ?? 1); ?> کد)
<?php } ?>
</div>

<button type="submit" class="campaignSubmit" name="save_referral_tiers" value="1">ذخیره سطوح</button>
</form>

<div class="campaignCard">
<div class="campaignCardHead">
<h2 class="campaignCardTitle">کدهای صادرشده از دعوت</h2>
<span class="campaignCardIcon"><?php echo campaignIconList(); ?></span>
</div>

<form method="get" class="campaignSearchRow">
<div class="campaignSearchWrap">
<?php echo campaignIconSearch(); ?>
<input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="جستجو کد، مالک، مصرف‌کننده">
</div>
<button type="submit" class="campaignFilterBtn">جستجو</button>
</form>

<div class="campaignList">
<?php if(empty($coupons)){ ?>
<div class="campaignHint">کدی ثبت نشده است.</div>
<?php } else { foreach(array_slice($coupons, 0, 80) as $row){
    $status = referralAdminCouponStatus($row);
?>
<div class="campaignListItem">
<div class="campaignItemTop">
<div class="campaignItemHead">
<div class="campaignItemCode"><?php echo htmlspecialchars((string)($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
<span class="campaignBadge <?php echo htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</div>
<div class="campaignItemType">تخفیف <?php echo (int)($row['percent'] ?? 0); ?>٪ — مالک: <?php echo htmlspecialchars((string)($row['owner'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="campaignItemMeta">
<div><strong>ایجاد</strong><?php echo referralAdminDateText($row['created_at'] ?? 0); ?></div>
<div><strong>مصرف</strong><?php echo !empty($row['used']) ? referralAdminDateText($row['used_at'] ?? 0) : '—'; ?></div>
<div><strong>مصرف‌کننده</strong><?php echo htmlspecialchars((string)($row['used_by'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
</div>
</div>
<?php } } ?>
</div>

<?php if(count($coupons) > 80){ ?>
<div class="campaignHint" style="margin-top:10px">فقط ۸۰ مورد آخر نمایش داده شد.</div>
<?php } ?>
</div>

</div>

<template id="referralTierTemplate">
<div class="referralTierRow" data-tier-row>
<div class="referralTierRowHead">
<div class="referralTierRowTitle">سطح جدید</div>
<button type="button" class="referralTierRemove" data-tier-remove>حذف</button>
</div>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">حداقل دعوت موفق</label>
<input class="campaignInput" type="number" min="1" name="tier_min_invites[]" value="3">
</div>
<div class="campaignField">
<label class="campaignLabel">درصد تخفیف</label>
<input class="campaignInput" type="number" min="1" max="100" name="tier_percent[]" value="100">
</div>
</div>
<div class="campaignGrid2">
<div class="campaignField">
<label class="campaignLabel">تعداد کد</label>
<input class="campaignInput" type="number" min="1" name="tier_qty[]" value="1">
</div>
<div class="campaignField">
<label class="campaignLabel">برچسب کوتاه</label>
<input class="campaignInput" name="tier_chip[]" value="۱۰۰٪">
</div>
</div>
<div class="campaignField">
<label class="campaignLabel">عنوان</label>
<input class="campaignInput" name="tier_title[]" value="">
</div>
<div class="campaignField">
<label class="campaignLabel">توضیح پاداش</label>
<input class="campaignInput" name="tier_desc[]" value="">
</div>
</div>
</template>

<script>
(function(){
    var editor = document.getElementById('referralTierEditor');
    var addBtn = document.getElementById('referralTierAdd');
    var template = document.getElementById('referralTierTemplate');

    function bindRemove(row){
        var btn = row.querySelector('[data-tier-remove]');
        if(!btn){ return; }
        btn.addEventListener('click', function(){
            var rows = editor.querySelectorAll('[data-tier-row]');
            if(rows.length <= 1){
                window.alert('حداقل یک سطح باید باقی بماند');
                return;
            }
            row.remove();
        });
    }

    editor.querySelectorAll('[data-tier-row]').forEach(bindRemove);

    if(addBtn && template && editor){
        addBtn.addEventListener('click', function(){
            var node = template.content.firstElementChild.cloneNode(true);
            editor.appendChild(node);
            bindRemove(node);
        });
    }
})();
</script>

<?php campaignAdminPageFoot(true); ?>
</body>
</html>

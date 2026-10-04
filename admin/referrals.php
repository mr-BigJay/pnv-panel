<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/campaign_ui.php';
require_once __DIR__ . '/../coupon_lib.php';

pnvAdminRequireAuth();

$flash = '';
$flashOk = false;
$csrf = couponAdminCsrfToken();

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rebuild_referral_events'])){

    if(!couponAdminCsrfValid($_POST['csrf'] ?? '')){
        $flash = 'خطای امنیتی (CSRF). دوباره تلاش کنید.';
    }
    else{
        $rebuild = couponRebuildReferralEvents();
        $flashOk = true;
        $flash = 'آمار ارجاع بازسازی شد (' . intval($rebuild['count'] ?? 0) . ' رویداد).';
    }

}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_referral_config'])){

    if(!couponAdminCsrfValid($_POST['csrf'] ?? '')){
        $flash = 'خطای امنیتی (CSRF). دوباره تلاش کنید.';
    }
    else{
        $config = couponBuildReferralConfigFromAdminPost($_POST);
        $saved = couponSaveReferralConfig($config);

        if($saved === false){
            $flash = 'ذخیره تنظیمات ناموفق بود. فایل config قبلی حفظ شده است.';
        }
        else{
            $flashOk = true;
            $flash = 'تنظیمات ذخیره شد.';
        }
    }

}

$search = trim((string)($_GET['q'] ?? ''));
$detailReferrer = trim((string)($_GET['referrer'] ?? ''));
$report = couponAdminReferralReport($search);
$config = $report['config'];
$kpis = $report['kpis'];
$referrers = $report['referrers'];
$unresolvedReferrals = $report['unresolved_referrals'] ?? [];
$invalidSelfReferrals = $report['invalid_self_referrals'] ?? [];

$detailRow = null;

foreach($referrers as $row){
    if(strcasecmp($row['username'], $detailReferrer) === 0){
        $detailRow = $row;
        break;
    }
}

$h = static function($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$fmtTs = static function($ts){
    $ts = intval($ts);

    if($ts <= 0){
        return '—';
    }

    return date('Y/m/d H:i', $ts);
};

$fmtMoneyThousands = static function($amount){
    $amount = intval($amount);

    if($amount <= 0){
        return '—';
    }

    return number_format($amount) . ' هزار تومان';
};

$flagLabels = [
    'same_mobile' => 'موبایل یکسان',
    'self_referral' => 'ارجاع به خود',
    'signup_spike' => 'ثبت‌نام غیرعادی',
    'no_purchase' => 'بدون خرید',
];

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>ارجاع و جوایز</title>
<?php campaignAdminPageHead(); ?>
<style>
.refKpiGrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}
.refKpi{background:#141b26;border:1px solid #334155;border-radius:14px;padding:12px;text-align:center}
.refKpiNum{font-size:22px;font-weight:700;color:#34d399;margin-bottom:4px;line-height:1.2}
.refKpiLabel{font-size:11px;color:#94a3b8;line-height:1.6}
.refTableWrap{overflow:auto;border:1px solid #334155;border-radius:14px}
.refTable{width:100%;border-collapse:collapse;font-size:12px;min-width:920px}
.refTable th,.refTable td{padding:10px 8px;border-bottom:1px solid #243041;text-align:right;vertical-align:top}
.refTable th{background:#141b26;color:#94a3b8;font-weight:700;position:sticky;top:0}
.refTable tr:hover td{background:rgba(52,211,153,.04)}
.refLink{color:#6ee7b7;text-decoration:none;font-weight:700}
.refFlags{display:flex;flex-wrap:wrap;gap:4px}
.refFlag{font-size:10px;padding:2px 6px;border-radius:999px;background:#713f12;color:#fde68a}
.refDetailBox{margin-top:12px;padding:12px;border:1px solid #334155;border-radius:14px;background:#141b26}
.refTierRow{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto;gap:8px;margin-bottom:8px;align-items:end}
.refTierRow .campaignInput{padding:10px 12px;font-size:13px}
.refPhaseNote{font-size:12px;color:#94a3b8;line-height:1.7;margin-top:8px;padding:10px 12px;border-radius:12px;background:#0f172a;border:1px dashed #475569}
@media(max-width:900px){.refKpiGrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.refKpiGrid{grid-template-columns:1fr}.refTierRow{grid-template-columns:1fr 1fr}}
</style>
</head>
<body class="<?php echo campaignAdminBodyClass(); ?>">
<div class="campaignShell">

<h1 class="campaignPageTitle">ارجاع و جوایز</h1>

<?php adminQuickNav('referrals'); ?>

<?php if($flash !== ''){ ?>
<div class="campaignFlash<?php echo $flashOk ? ' is-success' : ''; ?>"><?php echo $h($flash); ?></div>
<?php } ?>

<div class="campaignCard">
<h2 class="campaignCardTitle" style="margin:0 0 12px">شاخص‌های کلیدی</h2>
<div class="refKpiGrid">
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['total_signups']); ?></div><div class="refKpiLabel">ثبت‌نام referral</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['successful_referrals']); ?></div><div class="refKpiLabel">ارجاع موفق</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['active_referrers']); ?></div><div class="refKpiLabel">معرف فعال</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['converting_referrers']); ?></div><div class="refKpiLabel">معرف با تبدیل</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo $h($kpis['conversion_rate']); ?>٪</div><div class="refKpiLabel">نرخ تبدیل</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo $fmtMoneyThousands($kpis['referral_revenue_thousands']); ?></div><div class="refKpiLabel">درآمد زیرمجموعه (هزار تومان)</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['coupons_issued']); ?></div><div class="refKpiLabel">کوپن صادرشده</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['coupons_used']); ?></div><div class="refKpiLabel">کوپن مصرف‌شده</div></div>
<div class="refKpi"><div class="refKpiNum" style="font-size:13px;color:#cbd5e1"><?php echo $h($kpis['discount_cost_note']); ?></div><div class="refKpiLabel">هزینه تخفیف</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['unresolved_referrals'] ?? 0); ?></div><div class="refKpiLabel">ارجاع قدیمی حل‌نشده</div></div>
<div class="refKpi"><div class="refKpiNum"><?php echo number_format($kpis['invalid_self_referrals'] ?? 0); ?></div><div class="refKpiLabel">self-referral نامعتبر</div></div>
</div>
<form method="post" action="" style="margin:0 0 14px">
<input type="hidden" name="csrf" value="<?php echo $h($csrf); ?>">
<button type="submit" name="rebuild_referral_events" value="1" class="campaignFilterBtn" onclick="return confirm('ledger آمار از users/payments بازسازی شود؟');">بازسازی آمار ارجاع</button>
</form>
</div>

<?php if(!empty($unresolvedReferrals) || !empty($invalidSelfReferrals)){ ?>
<div class="campaignCard">
<h2 class="campaignCardTitle" style="margin:0 0 12px">ارجاع‌های نامعتبر / حل‌نشده</h2>
<div class="refTableWrap">
<table class="refTable">
<thead>
<tr>
<th>کاربر</th>
<th>مقدار referrer ذخیره‌شده</th>
<th>دلیل</th>
</tr>
</thead>
<tbody>
<?php foreach($invalidSelfReferrals as $row){ ?>
<tr>
<td><?php echo $h($row['referred'] ?? ''); ?></td>
<td><?php echo $h($row['referrer_value'] ?? ''); ?></td>
<td>self-referral (نامعتبر)</td>
</tr>
<?php } ?>
<?php foreach($unresolvedReferrals as $row){ ?>
<tr>
<td><?php echo $h($row['referred'] ?? ''); ?></td>
<td><?php echo $h($row['referrer_value'] ?? ''); ?></td>
<td><?php echo $h($row['reason'] ?? 'owner not found'); ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
<?php } ?>

<form class="campaignSearchRow" method="get" action="">
<div class="campaignSearchWrap">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
<input type="search" name="q" value="<?php echo $h($search); ?>" placeholder="جستجو: نام کاربری / کد دعوت / موبایل">
</div>
<button type="submit" class="campaignFilterBtn">جستجو</button>
</form>

<div class="campaignCard">
<h2 class="campaignCardTitle" style="margin:0 0 12px">برترین معرف‌ها</h2>
<div class="refTableWrap">
<table class="refTable">
<thead>
<tr>
<th>کاربر معرف</th>
<th>کد دعوت</th>
<th>ثبت‌نام</th>
<th>خرید موفق</th>
<th>چرخه / کل</th>
<th>Conversion</th>
<th>درآمد زیرمجموعه</th>
<th>کوپن فعال</th>
<th>کوپن مصرف</th>
<th>آخرین فعالیت</th>
<th>هشدار</th>
</tr>
</thead>
<tbody>
<?php if(empty($referrers)){ ?>
<tr><td colspan="11">موردی یافت نشد.</td></tr>
<?php } else { foreach($referrers as $row){ ?>
<tr>
<td><a class="refLink" href="?referrer=<?php echo urlencode($row['username']); ?>&amp;q=<?php echo urlencode($search); ?>"><?php echo $h($row['username']); ?></a></td>
<td><?php echo $h($row['referral_code']); ?></td>
<td><?php echo number_format($row['signups']); ?></td>
<td><?php echo number_format($row['successful']); ?></td>
<td><?php echo number_format($row['cycle_successful']); ?> / <?php echo number_format($row['lifetime_successful']); ?></td>
<td><?php echo $h($row['conversion_pct']); ?>٪</td>
<td><?php echo $fmtMoneyThousands($row['revenue_thousands']); ?></td>
<td><?php echo number_format($row['coupons_active']); ?></td>
<td><?php echo number_format($row['coupons_used']); ?></td>
<td><?php echo $h($fmtTs($row['last_activity'])); ?></td>
<td>
<div class="refFlags">
<?php foreach($row['flags'] as $flag){
    if(!isset($flagLabels[$flag])){ continue; }
?>
<span class="refFlag"><?php echo $h($flagLabels[$flag]); ?></span>
<?php } ?>
</div>
</td>
</tr>
<?php }} ?>
</tbody>
</table>
</div>
</div>

<?php if($detailRow){ ?>
<div class="campaignCard refDetailBox" id="referrer-detail">
<h2 class="campaignCardTitle" style="margin:0 0 10px">جزئیات معرف: <?php echo $h($detailRow['username']); ?></h2>
<p style="margin:0 0 12px;font-size:12px;color:#94a3b8">موفق در چرخه فعلی: <?php echo number_format($detailRow['cycle_successful']); ?> — کل عمر: <?php echo number_format($detailRow['lifetime_successful']); ?></p>
<div class="refTableWrap">
<table class="refTable">
<thead>
<tr>
<th>کاربر معرفی‌شده</th>
<th>تاریخ ثبت‌نام</th>
<th>وضعیت</th>
<th>اولین خرید</th>
<th>خرید/تمدید تایید</th>
<th>مجموع مبلغ</th>
</tr>
</thead>
<tbody>
<?php foreach($detailRow['referred'] as $ref){ ?>
<tr>
<td><?php echo $h($ref['username']); ?></td>
<td><?php echo $h($fmtTs($ref['registered_at'])); ?></td>
<td><?php echo $h($ref['status_label']); ?></td>
<td><?php echo $h($fmtTs($ref['first_purchase_at'])); ?></td>
<td><?php echo number_format($ref['purchase_count']); ?></td>
<td><?php echo $fmtMoneyThousands($ref['revenue_thousands']); ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
<?php } ?>

<div class="campaignCard">
<h2 class="campaignCardTitle" style="margin:0 0 12px">تنظیمات سیستم دعوت</h2>
<form method="post" action="">
<input type="hidden" name="csrf" value="<?php echo $h($csrf); ?>">
<input type="hidden" name="save_referral_config" value="1">

<div class="campaignToggleRow campaignSection">
<div class="campaignToggleText">فعال بودن سیستم دعوت</div>
<label class="campaignToggle">
<input type="checkbox" name="referral_enabled" value="1"<?php echo !empty($config['enabled']) ? ' checked' : ''; ?>>
<span class="campaignToggleTrack"></span>
</label>
</div>

<div class="campaignSection">
<div class="campaignSectionTitle">سطوح پاداش (tier)</div>
<div id="tierEditor">
<?php
$tierRows = $config['tiers'] ?? [];

if(empty($tierRows)){
    $tierRows = couponReferralDefaultConfig()['tiers'];
}

foreach($tierRows as $i => $tier){
    $rowKey = 'r_' . substr(md5(($tier['id'] ?? '') . $i), 0, 8);
?>
<div class="refTierRow" data-tier-row data-row-key="<?php echo $h($rowKey); ?>">
<input type="hidden" name="tiers[<?php echo $h($rowKey); ?>][id]" value="<?php echo $h($tier['id'] ?? ('tier_' . ($i + 1))); ?>">
<div><label class="campaignLabel">تعداد معرفی موفق</label><input class="campaignInput" type="number" min="1" name="tiers[<?php echo $h($rowKey); ?>][referrals]" value="<?php echo intval($tier['referrals']); ?>"></div>
<div><label class="campaignLabel">درصد تخفیف</label><input class="campaignInput" type="number" min="1" max="100" name="tiers[<?php echo $h($rowKey); ?>][percent]" value="<?php echo intval($tier['percent']); ?>"></div>
<div><label class="campaignLabel">تعداد کد</label><input class="campaignInput" type="number" min="1" name="tiers[<?php echo $h($rowKey); ?>][qty]" value="<?php echo intval($tier['qty']); ?>"></div>
<div><label class="campaignLabel">سقف (هزار تومان)</label><input class="campaignInput" type="number" min="0" name="tiers[<?php echo $h($rowKey); ?>][max_discount_thousands]" value="<?php echo intval($tier['max_discount_thousands']); ?>"></div>
<div><label class="campaignLabel">فعال</label><label class="campaignToggle" style="margin-top:6px"><input type="checkbox" name="tiers[<?php echo $h($rowKey); ?>][enabled]" value="1"<?php echo !empty($tier['enabled']) ? ' checked' : ''; ?>><span class="campaignToggleTrack"></span></label></div>
<button type="button" class="campaignFilterBtn" data-remove-tier>حذف</button>
</div>
<?php } ?>
</div>
<button type="button" class="campaignMoreBtn" id="addTierRow">+ افزودن سطح</button>
</div>

<div class="campaignSection">
<div class="campaignSectionTitle">پاداش کاربر جدید</div>
<div class="campaignGrid2">
<div class="campaignToggleRow">
<div class="campaignToggleText">فعال — فاز بعد — هنوز روی خرید اعمال نمی‌شود</div>
<label class="campaignToggle"><input type="checkbox" disabled><span class="campaignToggleTrack"></span></label>
</div>
<div class="campaignField"><label class="campaignLabel">درصد خرید اول</label><input class="campaignInput" type="number" min="1" max="100" name="new_user_reward_percent" value="<?php echo intval($config['new_user_reward']['first_purchase_percent'] ?? 10); ?>"></div>
<div class="campaignField"><label class="campaignLabel">سقف (هزار تومان)</label><input class="campaignInput" type="number" min="0" name="new_user_reward_max" value="<?php echo intval($config['new_user_reward']['max_discount_thousands'] ?? 0); ?>"></div>
</div>
</div>

<div class="campaignSection">
<div class="campaignSectionTitle">پاداش تکراری معرف (خرید/تمدید زیرمجموعه)</div>
<div class="campaignGrid2">
<div class="campaignToggleRow">
<div class="campaignToggleText">فعال — آماده برای فاز بعد — فعلاً غیرفعال</div>
<label class="campaignToggle"><input type="checkbox" disabled><span class="campaignToggleTrack"></span></label>
</div>
<div class="campaignField"><label class="campaignLabel">درصد</label><input class="campaignInput" type="number" min="1" max="100" name="referrer_purchase_percent" value="<?php echo intval($config['referrer_purchase_reward']['percent'] ?? 5); ?>"></div>
<div class="campaignField"><label class="campaignLabel">سقف ماهانه (هزار تومان)</label><input class="campaignInput" type="number" min="0" name="referrer_purchase_cap" value="<?php echo intval($config['referrer_purchase_reward']['monthly_cap_thousands'] ?? 500); ?>"></div>
</div>
<p class="refPhaseNote">در فاز ۱ هیچ balance واقعی برای درصد دائمی خرید/تمدید زیرمجموعه ایجاد نمی‌شود؛ فقط config ذخیره می‌شود.</p>
</div>

<button type="submit" class="campaignSubmit">ذخیره تنظیمات</button>
</form>
</div>

</div>
<?php campaignAdminPageFoot(); ?>
<?php adminBottomNav(['active' => 'referrals']); ?>
<?php adminBottomNavScript(); ?>
<script>
(function(){
    var editor = document.getElementById('tierEditor');
    var addBtn = document.getElementById('addTierRow');
    function newRowKey(){
        return 'r_' + Math.random().toString(16).slice(2, 10);
    }

    function bindRemove(row){
        var btn = row.querySelector('[data-remove-tier]');
        if(!btn){ return; }
        btn.addEventListener('click', function(){
            if(editor.querySelectorAll('[data-tier-row]').length <= 1){
                return;
            }
            row.remove();
        });
    }

    if(editor){
        editor.querySelectorAll('[data-tier-row]').forEach(bindRemove);
    }

    if(addBtn && editor){
        addBtn.addEventListener('click', function(){
            var rowKey = newRowKey();
            var row = document.createElement('div');
            row.className = 'refTierRow';
            row.setAttribute('data-tier-row', '');
            row.setAttribute('data-row-key', rowKey);
            var p = 'tiers[' + rowKey + ']';
            row.innerHTML =
                '<input type="hidden" name="' + p + '[id]" value="' + rowKey + '">' +
                '<div><label class="campaignLabel">تعداد معرفی موفق</label><input class="campaignInput" type="number" min="1" name="' + p + '[referrals]" value="1"></div>' +
                '<div><label class="campaignLabel">درصد تخفیف</label><input class="campaignInput" type="number" min="1" max="100" name="' + p + '[percent]" value="20"></div>' +
                '<div><label class="campaignLabel">تعداد کد</label><input class="campaignInput" type="number" min="1" name="' + p + '[qty]" value="1"></div>' +
                '<div><label class="campaignLabel">سقف (هزار تومان)</label><input class="campaignInput" type="number" min="0" name="' + p + '[max_discount_thousands]" value="0"></div>' +
                '<div><label class="campaignLabel">فعال</label><label class="campaignToggle" style="margin-top:6px"><input type="checkbox" name="' + p + '[enabled]" value="1" checked><span class="campaignToggleTrack"></span></label></div>' +
                '<button type="button" class="campaignFilterBtn" data-remove-tier>حذف</button>';
            editor.appendChild(row);
            bindRemove(row);
        });
    }
})();
</script>
</body>
</html>

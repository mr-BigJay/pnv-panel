<?php

$dashConceptPreview = defined('PNV_DASHBOARD_CONCEPTS_PREVIEW') && PNV_DASHBOARD_CONCEPTS_PREVIEW;

if (!$dashConceptPreview) {
    foreach ([__DIR__ . '/auth.php', __DIR__ . '/../admin/auth.php'] as $boot) {
        if (is_file($boot)) {
            require_once $boot;
            break;
        }
    }
}

foreach ([__DIR__ . '/functions.php', __DIR__ . '/../admin/functions.php'] as $boot) {
    if (is_file($boot)) {
        require_once $boot;
        break;
    }
}

foreach ([__DIR__ . '/../pnv_date_bootstrap.php', dirname(__DIR__) . '/pnv_date_bootstrap.php'] as $boot) {
    if (is_file($boot)) {
        require_once $boot;
        break;
    }
}

if (!function_exists('pnvJalaliToday')) {
    function pnvJalaliToday($sep = '/') {
        return date('Y' . $sep . 'm' . $sep . 'd');
    }
}

if (!function_exists('pnvIsTodayTehran')) {
    function pnvIsTodayTehran($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }
        return substr($value, 0, 10) === date('Y-m-d');
    }
}

if (!function_exists('pnvPaymentRowIsToday')) {
    function pnvPaymentRowIsToday($row) {
        return trim((string)($row[4] ?? '')) === pnvJalaliToday('/');
    }
}

if (!function_exists('pnvAdminUrl')) {
    function pnvAdminUrl($path = 'index.php') {
        $base = defined('PNV_ADMIN_BASE') ? rtrim(PNV_ADMIN_BASE, '/') : '/bigjay_controller';
        if ($path === '' || $path === 'index.php') {
            return $base . '/';
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!$dashConceptPreview) {
    pnvAdminRequireAuth();
}

$pnvRootDir = dirname(__DIR__);
$usersFile = $pnvRootDir . '/db/users.json';
$paymentsFile = $pnvRootDir . '/invoices/payments.csv';

$instantPayLib = $pnvRootDir . '/instant_pay_lib.php';
if (is_file($instantPayLib)) {
    require_once $instantPayLib;
}

$users = file_exists($usersFile)
    ? json_decode(file_get_contents($usersFile), true)
    : [];
if (!is_array($users)) {
    $users = [];
}

if (function_exists('instantPayPurgeAndReloadPaymentsCsv')) {
    $payments = instantPayPurgeAndReloadPaymentsCsv($paymentsFile);
} else {
    $payments = [];
    if (file_exists($paymentsFile)) {
        $handle = fopen($paymentsFile, 'r');
        while (($row = fgetcsv($handle)) !== false) {
            $payments[] = $row;
        }
        fclose($handle);
    }
}

$todayUsers = 0;
foreach ($users as $u) {
    if (isset($u['created_at']) && pnvIsTodayTehran($u['created_at'])) {
        $todayUsers++;
    }
}
$totalUsers = count($users);

$totalPayments = 0;
$todayPayments = 0;
$totalRenews = 0;
$todayRenews = 0;

foreach ($payments as $pay) {
    $type = trim($pay[9] ?? '');
    if ($type === 'تمدید') {
        $totalRenews++;
        if (pnvPaymentRowIsToday($pay)) {
            $todayRenews++;
        }
    } else {
        $totalPayments++;
        if (pnvPaymentRowIsToday($pay)) {
            $todayPayments++;
        }
    }
}

/** همان ۶ آیتم dashboard.php — بدون تغییر عنوان */
$stats = [
    ['key' => 'users_total', 'title' => 'تعداد کل کاربران', 'value' => $totalUsers],
    ['key' => 'users_today', 'title' => 'ثبت نام های امروز', 'value' => $todayUsers],
    ['key' => 'buy_total', 'title' => 'تعداد کل خریدهای اشتراک', 'value' => $totalPayments],
    ['key' => 'buy_today', 'title' => 'تعداد خریدهای اشتراک امروز', 'value' => $todayPayments],
    ['key' => 'renew_total', 'title' => 'تعداد کل تمدیدهای اشتراک', 'value' => $totalRenews],
    ['key' => 'renew_today', 'title' => 'تعداد تمدیدهای اشتراک امروز', 'value' => $todayRenews],
];

$statGroups = [
    [
        'label' => 'کاربران',
        'total' => $stats[0],
        'today' => $stats[1],
    ],
    [
        'label' => 'خرید اشتراک',
        'total' => $stats[2],
        'today' => $stats[3],
    ],
    [
        'label' => 'تمدید اشتراک',
        'total' => $stats[4],
        'today' => $stats[5],
    ],
];

$telegramEnabled = false;
$telegramConfigured = false;
if (file_exists($pnvRootDir . '/telegram_lib.php')) {
    require_once $pnvRootDir . '/telegram_lib.php';
    if (function_exists('telegramLoadConfig')) {
        $tgConfig = telegramLoadConfig();
        $telegramConfigured = trim((string)($tgConfig['bot_token'] ?? '')) !== '';
        $telegramEnabled = !empty($tgConfig['enabled']) && $telegramConfigured;
    }
}

$baleEnabled = false;
$baleConfigured = false;
if (file_exists($pnvRootDir . '/bale_lib.php')) {
    require_once $pnvRootDir . '/bale_lib.php';
    if (function_exists('baleLoadConfig')) {
        $baleConfig = baleLoadConfig();
        $baleConfigured = trim((string)($baleConfig['bot_token'] ?? '')) !== '';
        $baleEnabled = !empty($baleConfig['enabled']) && $baleConfigured;
    }
}

function dashConceptH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dashConceptFmt($value) {
    return number_format((int)$value);
}

function dashConceptSetupBadge($enabled, $configured) {
    if ($enabled) {
        return '<span class="setupBadge is-on">فعال</span>';
    }
    if ($configured) {
        return '<span class="setupBadge is-warn">پیکربندی شده</span>';
    }
    return '<span class="setupBadge">نیاز به ستاپ</span>';
}

function dashConceptRenderSetupCards($telegramEnabled, $telegramConfigured, $baleEnabled, $baleConfigured, $mode = 'full') {
    $tgUrl = dashConceptH(pnvAdminUrl('telegram.php'));
    $baleUrl = dashConceptH(pnvAdminUrl('bale.php'));
    $tgBadge = dashConceptSetupBadge($telegramEnabled, $telegramConfigured);
    $baleBadge = dashConceptSetupBadge($baleEnabled, $baleConfigured);

    if ($mode === 'compact') {
        ob_start();
        ?>
        <div class="setupMini">
            <a class="setupRow" href="<?php echo $tgUrl; ?>">
                <span class="setupRowTitle">بات تلگرام</span>
                <?php echo $tgBadge; ?>
            </a>
            <a class="setupRow" href="<?php echo $baleUrl; ?>">
                <span class="setupRowTitle">بازوی بله</span>
                <?php echo $baleBadge; ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <div class="setupGrid">
        <a class="setupCard" href="<?php echo $tgUrl; ?>">
            <div class="setupTop">
                <div class="setupTitle">بات تلگرام</div>
                <?php echo $tgBadge; ?>
            </div>
            <div class="setupDesc">اعلان خرید/تمدید و منوی مدیریت در تلگرام</div>
            <div class="setupAction">تنظیمات تلگرام ←</div>
        </a>
        <a class="setupCard" href="<?php echo $baleUrl; ?>">
            <div class="setupTop">
                <div class="setupTitle">بازوی بله</div>
                <?php echo $baleBadge; ?>
            </div>
            <div class="setupDesc">پرداخت آنی کارت‌به‌کارت با فوروارد واریز پست‌بانک</div>
            <div class="setupAction">تنظیمات بله ←</div>
        </a>
    </div>
    <?php
    return ob_get_clean();
}

function dashConceptWelcomeBox($compact = false) {
    ob_start();
    ?>
    <div class="welcomeBox<?php echo $compact ? ' is-compact' : ''; ?>">
        <h2>داشبورد مدیریت</h2>
        <p>به پنل مدیریت خوش آمدید</p>
    </div>
    <?php
    return ob_get_clean();
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>طرح‌های داشبورد — داده واقعی</title>
<style>
*{box-sizing:border-box}
body{
margin:0;
padding:16px 12px 100px;
background:#070d18;
color:#f1f5f9;
font-family:tahoma,sans-serif;
direction:rtl;
}
.pageHead{
max-width:420px;
margin:0 auto 18px;
text-align:center;
padding:0 4px;
}
.pageHead h1{margin:0 0 8px;font-size:18px;font-weight:700}
.pageHead p{margin:0;font-size:12px;color:#94a3b8;line-height:1.8}
.liveStrip{
display:flex;flex-wrap:wrap;justify-content:center;gap:6px;
margin-top:12px;
}
.livePill{
padding:5px 10px;border-radius:999px;font-size:11px;
background:#1e293b;border:1px solid #334155;color:#cbd5e1;
}
.livePill strong{color:#86efac;font-weight:700}

.concept{
max-width:420px;
margin:0 auto 24px;
border:1px solid #334155;
border-radius:20px;
overflow:hidden;
background:#0f172a;
box-shadow:0 8px 32px rgba(0,0,0,.35);
}
.conceptLabel{
display:flex;align-items:flex-start;justify-content:space-between;gap:10px;
padding:12px 14px;
background:linear-gradient(180deg,#1e293b,#172033);
border-bottom:1px solid #334155;
}
.conceptLabel .name{font-size:14px;font-weight:700;line-height:1.45}
.conceptLabel .tag{
flex-shrink:0;
font-size:10px;padding:4px 9px;border-radius:999px;
background:#334155;color:#cbd5e1;
}
.conceptLabel .tag.is-current{background:#713f12;color:#fde68a}
.conceptLabel .tag.is-rec{background:#14532d;color:#bbf7d0}
.conceptBody{padding:12px;background:#0b1220}
.phoneFrame{
border:1px solid #1e293b;border-radius:16px;overflow:hidden;background:#0f172a;
}
.conceptNote{
padding:10px 14px 13px;
font-size:11px;color:#64748b;line-height:1.7;
border-top:1px solid #1e293b;background:#0f172a;
}

/* shared dashboard pieces */
.statTitle{font-size:15px;color:#cbd5e1;line-height:1.45}
.statValue{font-size:30px;font-weight:700;color:#22c55e;line-height:1.1}
.setupGrid{display:grid;grid-template-columns:1fr;gap:12px;margin-top:12px}
.setupCard{
display:block;text-decoration:none;color:#fff;
background:#1e293b;border:1px solid #334155;border-radius:16px;padding:16px 14px;
}
.setupTop{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
.setupTitle{font-size:16px;font-weight:700}
.setupBadge{display:inline-flex;padding:4px 9px;border-radius:999px;font-size:11px;background:#334155;color:#e2e8f0;white-space:nowrap}
.setupBadge.is-on{background:#14532d;color:#bbf7d0}
.setupBadge.is-warn{background:#713f12;color:#fde68a}
.setupDesc{font-size:12px;line-height:1.75;color:#94a3b8;margin-bottom:12px}
.setupAction{font-size:13px;font-weight:700;color:#86efac}
.welcomeBox{background:#1e293b;border-radius:16px;padding:18px;margin-top:12px}
.welcomeBox h2{margin:0 0 6px;font-size:18px}
.welcomeBox p{margin:0;color:#94a3b8;font-size:13px;line-height:1.65}
.welcomeBox.is-compact{padding:14px}
.welcomeBox.is-compact h2{font-size:15px;margin-bottom:4px}
.welcomeBox.is-compact p{font-size:12px}
.setupMini{margin-top:12px;display:flex;flex-direction:column;gap:6px}
.setupRow{
display:flex;align-items:center;justify-content:space-between;gap:8px;
background:#1e293b;border:1px solid #334155;border-radius:10px;padding:10px 12px;
text-decoration:none;color:#fff;
}
.setupRowTitle{font-size:12px;font-weight:600}

/* فعلی */
.currentGrid{display:grid;grid-template-columns:1fr;gap:14px}
.currentBox,.statBox{
background:#1e293b;padding:22px;border-radius:18px;text-align:center;
}
.currentBox .statTitle,.statBox .statTitle{margin-bottom:12px}
.currentBox .statValue,.statBox .statValue{font-size:30px}

/* A: 2 col */
.a-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.a-cell{
background:#1e293b;border:1px solid #334155;border-radius:12px;
padding:11px 8px 10px;text-align:center;min-height:74px;
display:flex;flex-direction:column;justify-content:center;
}
.a-cell .statTitle{font-size:11px;margin-bottom:5px;color:#94a3b8}
.a-cell .statValue{font-size:21px}

/* B: rows */
.b-list{display:flex;flex-direction:column;gap:5px}
.b-row{
display:flex;align-items:center;justify-content:space-between;gap:10px;
background:#1e293b;border:1px solid #334155;border-radius:10px;padding:9px 12px;
}
.b-row .statTitle{font-size:12px;margin:0;flex:1;text-align:right}
.b-row .statValue{font-size:18px;flex-shrink:0;margin:0}

/* C: grouped */
.c-groups{display:flex;flex-direction:column;gap:8px}
.c-group{background:#1e293b;border:1px solid #334155;border-radius:14px;overflow:hidden}
.c-groupHead{
padding:8px 12px;font-size:12px;font-weight:700;color:#86efac;
background:rgba(34,197,94,.07);border-bottom:1px solid #334155;
}
.c-groupSub{font-size:10px;font-weight:400;color:#64748b;display:block;margin-top:2px}
.c-groupGrid{display:grid;grid-template-columns:1fr 1fr}
.c-mini{padding:10px 8px;text-align:center;border-left:1px solid #334155}
.c-mini:first-child{border-left:none}
.c-mini .statTitle{font-size:10px;margin-bottom:4px;color:#94a3b8}
.c-mini .statValue{font-size:19px}

/* D: 2 col + full setup */
.d-wrap .a-grid{margin-bottom:0}
.d-wrap .setupGrid{grid-template-columns:1fr}

.pickHint{
position:fixed;left:12px;right:12px;bottom:12px;z-index:20;
max-width:420px;margin:0 auto;padding:12px 14px;
background:rgba(30,41,59,.96);backdrop-filter:blur(8px);
border:1px solid #22c55e;border-radius:14px;
font-size:12px;color:#e2e8f0;line-height:1.75;text-align:center;
box-shadow:0 8px 24px rgba(0,0,0,.4);
}
.backLink{
display:block;text-align:center;margin:8px auto 0;max-width:420px;
font-size:13px;color:#86efac;text-decoration:none;
}
@media(min-width:860px){
body{padding-bottom:40px}
.conceptsWrap{
display:grid;grid-template-columns:repeat(2,minmax(0,1fr));
gap:20px;max-width:880px;margin:0 auto;
}
.concept{max-width:none;margin:0}
.pageHead,.pickHint,.backLink{max-width:880px}
.pickHint{position:static;margin-top:8px}
}
</style>
</head>
<body>

<div class="pageHead">
<h1>طرح‌های داشبورد — موارد واقعی شما</h1>
<p>۶ آمار + بات تلگرام + بازوی بله + پیام خوش‌آمد — همان محتوای <code style="color:#86efac">dashboard.php</code>؛ فقط چیدمان عوض شده.</p>
<div class="liveStrip">
<span class="livePill">امروز <?php echo dashConceptH(pnvJalaliToday('/')); ?></span>
<span class="livePill">کاربران <strong><?php echo dashConceptFmt($totalUsers); ?></strong></span>
<span class="livePill">خرید <strong><?php echo dashConceptFmt($totalPayments); ?></strong></span>
<span class="livePill">تمدید <strong><?php echo dashConceptFmt($totalRenews); ?></strong></span>
</div>
</div>

<div class="conceptsWrap">

<!-- فعلی -->
<section class="concept">
<div class="conceptLabel">
<div class="name">فعلی<br><span style="font-size:11px;font-weight:400;color:#64748b">هر آمار یک کارت تمام‌عرض</span></div>
<span class="tag is-current">روی سرور</span>
</div>
<div class="conceptBody">
<div class="phoneFrame">
<div class="currentGrid">
<?php foreach ($stats as $stat) { ?>
<div class="statBox">
<div class="statTitle"><?php echo dashConceptH($stat['title']); ?></div>
<div class="statValue"><?php echo dashConceptFmt($stat['value']); ?></div>
</div>
<?php } ?>
</div>
<?php echo dashConceptRenderSetupCards($telegramEnabled, $telegramConfigured, $baleEnabled, $baleConfigured, 'full'); ?>
<?php echo dashConceptWelcomeBox(); ?>
</div>
</div>
<p class="conceptNote">مشکل موبایل: ۶ کارت بزرگ پشت سر هم → اسکرول زیاد. padding 22px و عدد 30px.</p>
</section>

<!-- A -->
<section class="concept">
<div class="conceptLabel">
<div class="name">طرح A — ۲ ستون فشرده<br><span style="font-size:11px;font-weight:400;color:#64748b">همان ۶ عنوان، نصف ارتفاع</span></div>
<span class="tag is-rec">پیشنهادی</span>
</div>
<div class="conceptBody">
<div class="phoneFrame">
<div class="a-grid">
<?php foreach ($stats as $stat) { ?>
<div class="a-cell">
<div class="statTitle"><?php echo dashConceptH($stat['title']); ?></div>
<div class="statValue"><?php echo dashConceptFmt($stat['value']); ?></div>
</div>
<?php } ?>
</div>
<?php echo dashConceptRenderSetupCards($telegramEnabled, $telegramConfigured, $baleEnabled, $baleConfigured, 'compact'); ?>
<?php echo dashConceptWelcomeBox(true); ?>
</div>
</div>
<p class="conceptNote">ترتیب و عنوان‌ها دقیقاً مثل الان؛ فقط grid دو ستونه. تنظیمات به‌صورت ردیف باریک.</p>
</section>

<!-- B -->
<section class="concept">
<div class="conceptLabel">
<div class="name">طرح B — لیست باریک<br><span style="font-size:11px;font-weight:400;color:#64748b">عنوان راست، عدد چپ</span></div>
<span class="tag">کم‌اسکرول</span>
</div>
<div class="conceptBody">
<div class="phoneFrame">
<div class="b-list">
<?php foreach ($stats as $stat) { ?>
<div class="b-row">
<span class="statTitle"><?php echo dashConceptH($stat['title']); ?></span>
<span class="statValue"><?php echo dashConceptFmt($stat['value']); ?></span>
</div>
<?php } ?>
</div>
<?php echo dashConceptRenderSetupCards($telegramEnabled, $telegramConfigured, $baleEnabled, $baleConfigured, 'compact'); ?>
<?php echo dashConceptWelcomeBox(true); ?>
</div>
</div>
<p class="conceptNote">فشرده‌ترین حالت؛ مناسب وقتی فقط عدد را سریع می‌خواهید ببینید.</p>
</section>

<!-- C -->
<section class="concept">
<div class="conceptLabel">
<div class="name">طرح C — ۳ گروه<br><span style="font-size:11px;font-weight:400;color:#64748b">کاربر / خرید / تمدید — کل و امروز</span></div>
<span class="tag">خوانا</span>
</div>
<div class="conceptBody">
<div class="phoneFrame">
<div class="c-groups">
<?php foreach ($statGroups as $group) { ?>
<div class="c-group">
<div class="c-groupHead">
<?php echo dashConceptH($group['label']); ?>
<span class="c-groupSub"><?php echo dashConceptH($group['total']['title']); ?> · <?php echo dashConceptH($group['today']['title']); ?></span>
</div>
<div class="c-groupGrid">
<div class="c-mini">
<div class="statTitle">کل</div>
<div class="statValue"><?php echo dashConceptFmt($group['total']['value']); ?></div>
</div>
<div class="c-mini">
<div class="statTitle">امروز</div>
<div class="statValue"><?php echo dashConceptFmt($group['today']['value']); ?></div>
</div>
</div>
</div>
<?php } ?>
</div>
<?php echo dashConceptRenderSetupCards($telegramEnabled, $telegramConfigured, $baleEnabled, $baleConfigured, 'compact'); ?>
<?php echo dashConceptWelcomeBox(true); ?>
</div>
</div>
<p class="conceptNote">عنوان کامل هر آمار زیر نام گروه نوشته شده؛ ۳ کارت به‌جای ۶.</p>
</section>

<!-- D -->
<section class="concept">
<div class="conceptLabel">
<div class="name">طرح D — A + کارت تنظیمات کامل<br><span style="font-size:11px;font-weight:400;color:#64748b">آمار فشرده، تلگرام/بله با توضیح</span></div>
<span class="tag is-rec">ترکیبی</span>
</div>
<div class="conceptBody">
<div class="phoneFrame d-wrap">
<div class="a-grid">
<?php foreach ($stats as $stat) { ?>
<div class="a-cell">
<div class="statTitle"><?php echo dashConceptH($stat['title']); ?></div>
<div class="statValue"><?php echo dashConceptFmt($stat['value']); ?></div>
</div>
<?php } ?>
</div>
<?php echo dashConceptRenderSetupCards($telegramEnabled, $telegramConfigured, $baleEnabled, $baleConfigured, 'full'); ?>
<?php echo dashConceptWelcomeBox(true); ?>
</div>
</div>
<p class="conceptNote">همان A برای آمار + کارت‌های کامل تلگرام/بله (با توضیح و لینک).</p>
</section>

</div><!-- conceptsWrap -->

<div class="pickHint">
داده از <strong>users.json</strong> (<?php echo dashConceptFmt($totalUsers); ?> کاربر) و <strong>payments.csv</strong> خوانده شده.<br>
یکی را انتخاب کنید: <strong>فعلی</strong> · <strong>A</strong> · <strong>B</strong> · <strong>C</strong> · <strong>D</strong>
</div>

<a class="backLink" href="<?php echo dashConceptH(pnvAdminUrl()); ?>">← بازگشت به داشبورد</a>

</body>
</html>

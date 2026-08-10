<?php

require_once __DIR__ . '/dashboard_stats.php';

foreach([
    __DIR__ . '/auth.php',
    __DIR__ . '/../admin/auth.php',
] as $boot){
    if(is_file($boot)){
        require_once $boot;
        break;
    }
}

if(!function_exists('pnvAdminIsLoggedIn')){
    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }
}

if(function_exists('pnvAdminRequireAuth')){
    pnvAdminRequireAuth();
}
elseif(empty($_SESSION['admin']) && empty($_SESSION['pnv_admin']['user'])){
    header('Location: index.php');
    exit;
}

$dash = dashboardLoadStats();
$stats = $dash['stats'];
$setups = $dash['setups'];
$homeUrl = function_exists('pnvAdminUrl') ? pnvAdminUrl() : 'index.php';

function dcH($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dcBadgeHtml($badge){
    $class = 'setupBadge';

    if(!empty($badge['class'])){
        $class .= ' ' . $badge['class'];
    }

    return '<span class="' . dcH($class) . '">' . dcH($badge['text'] ?? '') . '</span>';
}

function dcSetupMini($setups){
    echo '<div class="setupMini">';

    foreach($setups as $setup){
        echo '<a class="setupRow" href="' . dcH($setup['href']) . '">';
        echo '<span>' . dcH($setup['title']) . '</span>';
        echo dcBadgeHtml($setup['badge']);
        echo '</a>';
    }

    echo '</div>';
}

function dcSetupCards($setups){
    echo '<div class="setupGridConcept">';

    foreach($setups as $setup){
        echo '<a class="setupCardConcept" href="' . dcH($setup['href']) . '">';
        echo '<div class="setupTopConcept">';
        echo '<div class="setupTitleConcept">' . dcH($setup['title']) . '</div>';
        echo dcBadgeHtml($setup['badge']);
        echo '</div>';
        echo '<div class="setupDescConcept">' . dcH($setup['desc']) . '</div>';
        echo '<div class="setupActionConcept">' . dcH($setup['action']) . '</div>';
        echo '</a>';
    }

    echo '</div>';
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
padding:16px 12px calc(28px + env(safe-area-inset-bottom));
background:#0b1220;
color:#f1f5f9;
font-family:tahoma,sans-serif;
direction:rtl;
line-height:1.5;
}
.pageHead{text-align:center;margin-bottom:16px;padding:0 8px}
.pageHead h1{margin:0 0 8px;font-size:18px;font-weight:700}
.pageHead p{margin:0 0 10px;font-size:13px;color:#94a3b8;line-height:1.7}
.backLink{
display:inline-flex;align-items:center;justify-content:center;
padding:8px 14px;border-radius:10px;background:#334155;color:#fff;
text-decoration:none;font-size:13px;margin-bottom:8px;
}
.liveTag{
display:inline-block;font-size:11px;color:#86efac;background:#14532d;
padding:4px 10px;border-radius:999px;margin-top:6px;
}
.concept{
margin-bottom:24px;background:#111827;border:1px solid #334155;
border-radius:20px;overflow:hidden;max-width:420px;margin-left:auto;margin-right:auto;
}
.conceptLabel{
display:flex;align-items:center;justify-content:space-between;gap:10px;
padding:12px 14px;background:#1e293b;border-bottom:1px solid #334155;
}
.conceptLabel strong{font-size:15px;color:#fff}
.conceptLabel span{font-size:11px;color:#86efac;background:#14532d;padding:4px 10px;border-radius:999px}
.conceptBody{padding:12px;background:#0f172a}
.conceptNote{
padding:10px 14px 14px;font-size:12px;color:#64748b;line-height:1.7;border-top:1px solid #1e293b;
}
.currentBox{
margin:0 auto 20px;max-width:420px;padding:12px 14px;
background:#1e293b;border:1px solid #475569;border-radius:14px;font-size:12px;color:#94a3b8;line-height:1.8;
}
.currentBox b{color:#e2e8f0}

.a-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.a-cell{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:10px 8px;text-align:center}
.a-cell .m{margin-bottom:4px;line-height:1.35;font-size:11px;color:#94a3b8}
.a-cell .v{font-size:20px;font-weight:700;color:#22c55e;line-height:1.2}

.b-list{display:flex;flex-direction:column;gap:6px}
.b-row{
display:flex;align-items:center;justify-content:space-between;gap:10px;
background:#1e293b;border:1px solid #334155;border-radius:10px;padding:10px 12px;min-height:44px;
}
.b-row .m{font-size:12px;color:#cbd5e1;flex:1;line-height:1.4}
.b-row .v{font-size:17px;font-weight:700;color:#22c55e;flex-shrink:0}

.c-groups{display:flex;flex-direction:column;gap:10px}
.c-group{background:#1e293b;border:1px solid #334155;border-radius:14px;overflow:hidden}
.c-groupHead{padding:8px 12px;font-size:12px;font-weight:700;color:#86efac;background:rgba(34,197,94,.08);border-bottom:1px solid #334155}
.c-groupGrid{display:grid;grid-template-columns:1fr 1fr}
.c-mini{padding:10px;text-align:center;border-left:1px solid #334155}
.c-mini:first-child{border-left:none}
.c-mini .m{font-size:10px;color:#94a3b8;margin-bottom:3px;line-height:1.35}
.c-mini .v{font-size:18px;font-weight:700;color:#22c55e}

.d-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.d-tile{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:10px 6px 8px;text-align:center}
.d-icon{
width:28px;height:28px;margin:0 auto 6px;border-radius:8px;
display:flex;align-items:center;justify-content:center;font-size:14px;
background:rgba(34,197,94,.15);color:#86efac;
}
.d-tile .m{font-size:9px;line-height:1.3;margin-bottom:2px;color:#94a3b8}
.d-tile .v{font-size:16px;font-weight:700;color:#22c55e}

.setupMini{margin-top:10px;display:flex;flex-direction:column;gap:6px}
.setupRow{
display:flex;align-items:center;justify-content:space-between;gap:8px;
background:#1e293b;border:1px solid #334155;border-radius:10px;padding:10px 12px;
text-decoration:none;color:#fff;font-size:12px;
}
.setupGridConcept{margin-top:10px;display:flex;flex-direction:column;gap:8px}
.setupCardConcept{
display:block;text-decoration:none;color:#fff;background:#1e293b;border:1px solid #334155;
border-radius:12px;padding:12px;
}
.setupTopConcept{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px}
.setupTitleConcept{font-size:14px;font-weight:700}
.setupDescConcept{font-size:11px;line-height:1.6;color:#94a3b8;margin-bottom:8px}
.setupActionConcept{font-size:12px;font-weight:700;color:#86efac}
.setupBadge{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:10px;background:#334155;color:#e2e8f0;white-space:nowrap}
.setupBadge.is-on{background:#14532d;color:#bbf7d0}
.setupBadge.is-warn{background:#713f12;color:#fde68a}

.welcomeMini{
margin-top:10px;background:#1e293b;border:1px solid #334155;border-radius:12px;padding:12px;
}
.welcomeMini h3{margin:0 0 6px;font-size:14px}
.welcomeMini p{margin:0;font-size:12px;color:#94a3b8;line-height:1.7}

.pickHint{
max-width:420px;margin:8px auto 0;padding:14px;background:#1e293b;
border:1px dashed #22c55e;border-radius:14px;font-size:13px;color:#cbd5e1;line-height:1.8;text-align:center;
}
</style>
</head>
<body>

<div class="pageHead">
<a class="backLink" href="<?php echo dcH($homeUrl); ?>">← بازگشت به داشبورد</a>
<h1>طرح‌های داشبورد (داده واقعی)</h1>
<p>همه اعداد و وضعیت تلگرام/بله از همین لحظه خوانده شده — همان موارد داشبورد فعلی شما.</p>
<span class="liveTag">زنده · <?php echo dcH(date('Y/m/d H:i')); ?></span>
</div>

<div class="currentBox">
<b>داده فعلی سایت:</b>
کاربران <?php echo dcH($dash['raw']['totalUsers']); ?> ·
ثبت‌نام امروز <?php echo dcH($dash['raw']['todayUsers']); ?> ·
خرید <?php echo dcH($dash['raw']['totalPayments']); ?> (امروز <?php echo dcH($dash['raw']['todayPayments']); ?>) ·
تمدید <?php echo dcH($dash['raw']['totalRenews']); ?> (امروز <?php echo dcH($dash['raw']['todayRenews']); ?>)
</div>

<!-- A -->
<section class="concept">
<div class="conceptLabel"><strong>طرح A — شبکه ۲ ستونه فشرده</strong><span>پیشنهادی</span></div>
<div class="conceptBody">
<div class="a-grid">
<?php foreach($stats as $stat){ ?>
<div class="a-cell">
<div class="m"><?php echo dcH($stat['title']); ?></div>
<div class="v"><?php echo dcH($stat['value']); ?></div>
</div>
<?php } ?>
</div>
<?php dcSetupMini($setups); ?>
</div>
<p class="conceptNote">همان ۶ آمار داشبورد + کارت‌های تلگرام و بله، فشرده در ۲ ستون.</p>
</section>

<!-- B -->
<section class="concept">
<div class="conceptLabel"><strong>طرح B — لیست باریک</strong><span>مینیمال</span></div>
<div class="conceptBody">
<div class="b-list">
<?php foreach($stats as $stat){ ?>
<div class="b-row">
<span class="m"><?php echo dcH($stat['title']); ?></span>
<span class="v"><?php echo dcH($stat['value']); ?></span>
</div>
<?php } ?>
</div>
<?php dcSetupMini($setups); ?>
</div>
<p class="conceptNote">عناوین دقیقاً مثل داشبورد فعلی؛ فقط چیدمان ردیفی و باریک.</p>
</section>

<!-- C -->
<section class="concept">
<div class="conceptLabel"><strong>طرح C — گروه‌بندی موضوعی</strong><span>خوانا</span></div>
<div class="conceptBody">
<div class="c-groups">
<div class="c-group">
<div class="c-groupHead">👥 کاربران</div>
<div class="c-groupGrid">
<div class="c-mini"><div class="m"><?php echo dcH(dashboardStatByKey($stats, 'total_users')['title']); ?></div><div class="v"><?php echo dcH(dashboardStatByKey($stats, 'total_users')['value']); ?></div></div>
<div class="c-mini"><div class="m"><?php echo dcH(dashboardStatByKey($stats, 'today_users')['title']); ?></div><div class="v"><?php echo dcH(dashboardStatByKey($stats, 'today_users')['value']); ?></div></div>
</div>
</div>
<div class="c-group">
<div class="c-groupHead">🛒 خرید اشتراک</div>
<div class="c-groupGrid">
<div class="c-mini"><div class="m"><?php echo dcH(dashboardStatByKey($stats, 'total_payments')['title']); ?></div><div class="v"><?php echo dcH(dashboardStatByKey($stats, 'total_payments')['value']); ?></div></div>
<div class="c-mini"><div class="m"><?php echo dcH(dashboardStatByKey($stats, 'today_payments')['title']); ?></div><div class="v"><?php echo dcH(dashboardStatByKey($stats, 'today_payments')['value']); ?></div></div>
</div>
</div>
<div class="c-group">
<div class="c-groupHead">↻ تمدید اشتراک</div>
<div class="c-groupGrid">
<div class="c-mini"><div class="m"><?php echo dcH(dashboardStatByKey($stats, 'total_renews')['title']); ?></div><div class="v"><?php echo dcH(dashboardStatByKey($stats, 'total_renews')['value']); ?></div></div>
<div class="c-mini"><div class="m"><?php echo dcH(dashboardStatByKey($stats, 'today_renews')['title']); ?></div><div class="v"><?php echo dcH(dashboardStatByKey($stats, 'today_renews')['value']); ?></div></div>
</div>
</div>
</div>
<?php dcSetupCards($setups); ?>
</div>
<p class="conceptNote">۳ گروه واقعی داشبورد؛ کارت‌های تلگرام/بله با توضیح کامل مثل الان.</p>
</section>

<!-- D -->
<section class="concept">
<div class="conceptLabel"><strong>طرح D — کاشی ۳ ستونه</strong><span>مدرن</span></div>
<div class="conceptBody">
<div class="d-grid">
<?php foreach($stats as $stat){ ?>
<div class="d-tile">
<div class="d-icon"><?php echo dcH($stat['icon']); ?></div>
<div class="m"><?php echo dcH($stat['short']); ?></div>
<div class="v"><?php echo dcH($stat['value']); ?></div>
</div>
<?php } ?>
</div>
<?php dcSetupMini($setups); ?>
<div class="welcomeMini">
<h3>داشبورد مدیریت</h3>
<p>به پنل مدیریت خوش آمدید</p>
</div>
</div>
<p class="conceptNote">۶ آمار واقعی با برچسب کوتاه + باکس خوش‌آمد فعلی داشبورد.</p>
</section>

<div class="pickHint">
یکی را انتخاب کنید: <strong>طرح A</strong>، <strong>B</strong>، <strong>C</strong> یا <strong>D</strong>
</div>

</body>
</html>

<?php
$telegramEnabled = false;
$telegramConfigured = false;
$baleEnabled = false;
$baleConfigured = false;

if(file_exists(__DIR__ . '/../telegram_lib.php')){
    require_once __DIR__ . '/../telegram_lib.php';
    if(function_exists('telegramLoadConfig')){
        $tgConfig = telegramLoadConfig();
        $telegramConfigured = trim((string)($tgConfig['bot_token'] ?? '')) !== '';
        $telegramEnabled = !empty($tgConfig['enabled']) && $telegramConfigured;
    }
}

if(file_exists(__DIR__ . '/../bale_lib.php')){
    require_once __DIR__ . '/../bale_lib.php';
    if(function_exists('baleLoadConfig')){
        $baleConfig = baleLoadConfig();
        $baleConfigured = trim((string)($baleConfig['bot_token'] ?? '')) !== '';
        $baleEnabled = !empty($baleConfig['enabled']) && $baleConfigured;
    }
}
?>

<div class="statsGrid">

    <div class="statBox">
        <div class="statTitle">
            تعداد کل کاربران
        </div>
        <div class="statValue">
            <?php echo number_format($totalUsers); ?>
        </div>
    </div>

    <div class="statBox">
        <div class="statTitle">
            ثبت نام های امروز
        </div>
        <div class="statValue">
            <?php echo number_format($todayUsers); ?>
        </div>
    </div>

    <div class="statBox">
        <div class="statTitle">
            تعداد کل خریدهای اشتراک
        </div>
        <div class="statValue">
            <?php echo number_format($totalPayments); ?>
        </div>
    </div>

    <div class="statBox">
        <div class="statTitle">
            تعداد خریدهای اشتراک امروز
        </div>
        <div class="statValue">
            <?php echo number_format($todayPayments); ?>
        </div>
    </div>

    <div class="statBox">
        <div class="statTitle">
            تعداد کل تمدیدهای اشتراک
        </div>
        <div class="statValue">
            <?php echo number_format($totalRenews); ?>
        </div>
    </div>

    <div class="statBox">
        <div class="statTitle">
            تعداد تمدیدهای اشتراک امروز
        </div>
        <div class="statValue">
            <?php echo number_format($todayRenews); ?>
        </div>
    </div>

</div>

<div class="setupGrid">

    <a class="setupCard" href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl('telegram.php') : 'telegram.php', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="setupTop">
            <div class="setupTitle">بات تلگرام</div>
            <?php if(!empty($telegramEnabled)){ ?>
                <span class="setupBadge is-on">فعال</span>
            <?php } elseif(!empty($telegramConfigured)){ ?>
                <span class="setupBadge is-warn">پیکربندی شده</span>
            <?php } else { ?>
                <span class="setupBadge">نیاز به ستاپ</span>
            <?php } ?>
        </div>
        <div class="setupDesc">اعلان خرید/تمدید و منوی مدیریت در تلگرام</div>
        <div class="setupAction">تنظیمات تلگرام ←</div>
    </a>

    <a class="setupCard" href="<?php echo htmlspecialchars(function_exists('pnvAdminUrl') ? pnvAdminUrl('bale.php') : 'bale.php', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="setupTop">
            <div class="setupTitle">بازوی بله</div>
            <?php if(!empty($baleEnabled)){ ?>
                <span class="setupBadge is-on">فعال</span>
            <?php } elseif(!empty($baleConfigured)){ ?>
                <span class="setupBadge is-warn">پیکربندی شده</span>
            <?php } else { ?>
                <span class="setupBadge">نیاز به ستاپ</span>
            <?php } ?>
        </div>
        <div class="setupDesc">پرداخت آنی کارت‌به‌کارت با فوروارد واریز پست‌بانک</div>
        <div class="setupAction">تنظیمات بله ←</div>
    </a>

</div>

<style>
.setupGrid{
display:grid;
grid-template-columns:repeat(2,minmax(0,1fr));
gap:14px;
margin:18px 0 8px;
}
.setupCard{
display:block;
text-decoration:none;
color:#fff;
background:#1e293b;
border:1px solid #334155;
border-radius:18px;
padding:18px 16px;
transition:border-color .15s ease, transform .15s ease;
}
.setupCard:hover{
border-color:#22c55e;
transform:translateY(-1px);
}
.setupTop{
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
margin-bottom:10px;
}
.setupTitle{
font-size:18px;
font-weight:700;
}
.setupBadge{
display:inline-flex;
align-items:center;
padding:5px 10px;
border-radius:999px;
font-size:12px;
background:#334155;
color:#e2e8f0;
white-space:nowrap;
}
.setupBadge.is-on{background:#14532d;color:#bbf7d0}
.setupBadge.is-warn{background:#713f12;color:#fde68a}
.setupDesc{
font-size:13px;
line-height:1.8;
color:#94a3b8;
margin-bottom:14px;
}
.setupAction{
font-size:14px;
font-weight:700;
color:#86efac;
}
@media(max-width:700px){
.setupGrid{grid-template-columns:1fr}
}
</style>

<div class="box">

    <h2>

        داشبورد مدیریت

    </h2>

    <p>

        به پنل مدیریت خوش آمدید

    </p>

</div>

<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/../campaign_lib.php';

pnvAdminRequireAuth();

$stats = campaignOverviewStats();

function campaignAdminSharedStyles(){
    echo '<style>
*{box-sizing:border-box}
body{margin:0;padding:20px;background:#0f172a;font-family:tahoma;direction:rtl;color:#fff}
.container{max-width:1100px;margin:auto}
.box{background:#1e293b;padding:20px;border-radius:20px;margin-bottom:20px}
h2{margin-top:0;margin-bottom:16px;font-size:24px}
.campNav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.campNav a{display:inline-flex;padding:8px 12px;border-radius:10px;background:#334155;color:#fff;text-decoration:none;font-size:13px}
.campNav a.is-active{background:#22c55e;color:#052e16;font-weight:700}
.statsGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.statCard{background:#0f172a;border:1px solid #334155;border-radius:16px;padding:16px;text-align:center}
.statCardNum{font-size:28px;font-weight:700;color:#22c55e;margin-bottom:6px}
.statCardLabel{font-size:12px;color:#94a3b8;line-height:1.7}
.campLinks{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.campLink{display:block;background:#0f172a;border:1px solid #334155;border-radius:16px;padding:18px;text-decoration:none;color:#fff}
.campLink strong{display:block;font-size:18px;margin-bottom:8px}
.campLink span{font-size:13px;color:#94a3b8;line-height:1.8}
.back{display:block;margin-top:20px;background:#334155;padding:14px;border-radius:14px;text-align:center;color:#fff;text-decoration:none}
@media(max-width:768px){body{padding:10px}.statsGrid,.campLinks{grid-template-columns:1fr}}
</style>';
}

?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>کمپین‌ها</title>
<?php campaignAdminSharedStyles(); ?>
</head>
<body>
<div class="container">

<nav class="campNav">
<a class="is-active" href="<?php echo htmlspecialchars(pnvAdminUrl('campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">نمای کلی</a>
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php'), ENT_QUOTES, 'UTF-8'); ?>">کدهای تخفیف</a>
<a href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php'), ENT_QUOTES, 'UTF-8'); ?>">پیام‌های داشبورد</a>
</nav>

<div class="box">
<h2>کمپین‌ها</h2>
<div class="statsGrid">
<div class="statCard"><div class="statCardNum"><?php echo (int)$stats['active_codes']; ?></div><div class="statCardLabel">کدهای فعال</div></div>
<div class="statCard"><div class="statCardNum"><?php echo (int)$stats['expired_codes']; ?></div><div class="statCardLabel">کدهای منقضی‌شده</div></div>
<div class="statCard"><div class="statCardNum"><?php echo (int)$stats['active_announcements']; ?></div><div class="statCardLabel">کمپین‌های فعال (پیام)</div></div>
<div class="statCard"><div class="statCardNum"><?php echo (int)$stats['today_uses']; ?></div><div class="statCardLabel">استفاده امروز از کدها</div></div>
</div>
</div>

<div class="box">
<h2>بخش‌ها</h2>
<div class="campLinks">
<a class="campLink" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php'), ENT_QUOTES, 'UTF-8'); ?>">
<strong>کدهای تخفیف</strong>
<span>ایجاد، ویرایش، محدودیت استفاده و اتصال به فرآیند خرید</span>
</a>
<a class="campLink" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php'), ENT_QUOTES, 'UTF-8'); ?>">
<strong>پیام‌های داشبورد</strong>
<span>اعلان Popup/Banner برای کاربران در داشبورد</span>
</a>
</div>
</div>

<a class="back" href="<?php echo htmlspecialchars(pnvAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>">بازگشت به داشبورد</a>
</div>
</body>
</html>

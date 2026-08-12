<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/campaign_ui.php';
require_once __DIR__ . '/../campaign_lib.php';

pnvAdminRequireAuth();

$stats = campaignOverviewStats();

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>کمپین‌ها</title>
<?php campaignAdminStyles(); ?>
<style>
.campaignStats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.campaignStat{background:#141b26;border:1px solid #334155;border-radius:16px;padding:14px;text-align:center}
.campaignStatNum{font-size:26px;font-weight:700;color:#34d399;margin-bottom:4px}
.campaignStatLabel{font-size:12px;color:#94a3b8;line-height:1.7}
.campaignLinks{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.campaignLink{display:block;background:#141b26;border:1px solid #334155;border-radius:16px;padding:16px;text-decoration:none;color:#fff}
.campaignLink strong{display:block;font-size:16px;margin-bottom:6px}
.campaignLink span{font-size:12px;color:#94a3b8;line-height:1.8}
@media(max-width:640px){.campaignStats,.campaignLinks{grid-template-columns:1fr}}
</style>
</head>
<body class="campaignAdmin">
<div class="campaignShell">

<?php campaignAdminNav('overview'); ?>

<div class="campaignCard">
<h2 class="campaignCardTitle" style="margin:0 0 14px">نمای کلی</h2>
<div class="campaignStats">
<div class="campaignStat"><div class="campaignStatNum"><?php echo (int)$stats['active_codes']; ?></div><div class="campaignStatLabel">کدهای فعال</div></div>
<div class="campaignStat"><div class="campaignStatNum"><?php echo (int)$stats['expired_codes']; ?></div><div class="campaignStatLabel">کدهای منقضی‌شده</div></div>
<div class="campaignStat"><div class="campaignStatNum"><?php echo (int)$stats['active_announcements']; ?></div><div class="campaignStatLabel">کمپین‌های فعال</div></div>
<div class="campaignStat"><div class="campaignStatNum"><?php echo (int)$stats['today_uses']; ?></div><div class="campaignStatLabel">استفاده امروز</div></div>
</div>
</div>

<div class="campaignCard">
<h2 class="campaignCardTitle" style="margin:0 0 14px">بخش‌ها</h2>
<div class="campaignLinks">
<a class="campaignLink" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-discounts.php'), ENT_QUOTES, 'UTF-8'); ?>">
<strong>کدهای تخفیف</strong>
<span>ایجاد، ویرایش، محدودیت استفاده و اتصال به فرآیند خرید</span>
</a>
<a class="campaignLink" href="<?php echo htmlspecialchars(pnvAdminUrl('campaign-announcements.php'), ENT_QUOTES, 'UTF-8'); ?>">
<strong>پیام‌های داشبورد</strong>
<span>اعلان Popup و Banner برای کاربران</span>
</a>
</div>
</div>

<a class="campaignBack" href="<?php echo htmlspecialchars(pnvAdminUrl(), ENT_QUOTES, 'UTF-8'); ?>">بازگشت به داشبورد</a>
</div>
</body>
</html>

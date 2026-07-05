<?php

if(!function_exists('pnvAdminUrl')){
    require_once __DIR__ . '/auth.php';
}

$pnvPwaBase = rtrim(PNV_ADMIN_BASE, '/') . '/';

?>
<link rel="manifest" href="<?php echo htmlspecialchars(pnvAdminUrl('manifest.php'), ENT_QUOTES, 'UTF-8'); ?>">
<meta name="theme-color" content="#111827">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ادمین">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars(pnvAdminUrl('icons/icon-192.png'), ENT_QUOTES, 'UTF-8'); ?>">

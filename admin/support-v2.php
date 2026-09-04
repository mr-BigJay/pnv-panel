<?php

if(!isset($supportEmbedded)){
    $supportEmbedded = false;
}

require_once __DIR__ . '/../support_lib.php';

if(!$supportEmbedded && supportIsEmbeddedRequest()){
    $supportEmbedded = true;
}

if(!$supportEmbedded){

    require_once __DIR__ . '/auth.php';

    if(!pnvAdminIsLoggedIn()){
        header('Location: ' . pnvAdminEntryUrl());
        exit;
    }

}

$assetDir = __DIR__ . '/../assets/support/admin';
$jsFile = 'support-admin.js';
$cssFile = '';

if(is_dir($assetDir)){
    foreach(glob($assetDir . '/support-admin*.css') ?: [] as $cssPath){
        $cssFile = basename($cssPath);
        break;
    }
}

$initialUser = supportNormalizeUsername($_GET['user'] ?? '');

if(function_exists('pnvAdminUrl')){
    $apiUrl = pnvAdminUrl('support-api.php');
}
elseif(defined('PNV_ADMIN_BASE')){
    $apiUrl = rtrim(PNV_ADMIN_BASE, '/') . '/support-api.php';
}
else{
    $apiUrl = '/bigjay_controller/support-api.php';
}

$assetBase = '/assets/support/admin/';

$config = [
    'apiUrl' => $apiUrl,
    'csrf' => supportCsrfToken(),
    'embedded' => (bool)$supportEmbedded,
    'initialUser' => $initialUser,
    'pollIntervalMs' => 3000,
];

$rootHeight = $supportEmbedded ? 'calc(100vh - 100px)' : '100vh';

if(!$supportEmbedded){
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>پیام‌های کاربران (v2)</title>
<?php if($cssFile !== ''){ ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . $cssFile, ENT_QUOTES, 'UTF-8'); ?>">
<?php } ?>
</head>
<body style="margin:0;background:#0e1621;min-height:100vh;">
<?php } else { ?>
<?php if($cssFile !== ''){ ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . $cssFile, ENT_QUOTES, 'UTF-8'); ?>">
<?php } ?>
<?php } ?>

<div id="support-v2-root" style="height:<?php echo htmlspecialchars($rootHeight, ENT_QUOTES, 'UTF-8'); ?>;"></div>

<script>
window.SUPPORT_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<?php if(!is_file($assetDir . '/' . $jsFile)){ ?>
<div style="padding:24px;color:#fecaca;font-family:sans-serif;text-align:center;">
    فایل‌های UI ساخته نشده‌اند. در پوشه <code>support-ui</code> دستور <code>npm run build</code> را اجرا کنید.
</div>
<?php } else { ?>
<script type="module" src="<?php echo htmlspecialchars($assetBase . $jsFile, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php } ?>

<?php if(!$supportEmbedded){ ?>
</body>
</html>
<?php } ?>

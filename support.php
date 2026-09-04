<?php

session_start();

if(!isset($_SESSION['user'])){
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/support_lib.php';

$assetDir = __DIR__ . '/assets/support/admin';
$jsFile = 'support-admin.js';
$cssFile = '';

if(is_dir($assetDir)){
    foreach(glob($assetDir . '/support-admin*.css') ?: [] as $cssPath){
        $cssFile = basename($cssPath);
        break;
    }
}

$assetBase = '/assets/support/admin/';
$jsPath = $assetDir . '/' . $jsFile;
$cssPath = $cssFile !== '' ? $assetDir . '/' . $cssFile : '';
$jsVer = is_file($jsPath) ? filemtime($jsPath) : time();
$cssVer = ($cssPath !== '' && is_file($cssPath)) ? filemtime($cssPath) : time();

$config = [
    'apiUrl' => 'support-api.php',
    'csrf' => supportCsrfToken(),
    'embedded' => false,
    'role' => 'user',
    'initialUser' => 'support',
    'displayTitle' => 'پشتیبانی',
    'displaySubtitle' => 'معمولاً در کمتر از ۱ ساعت پاسخ می‌دهیم',
    'backUrl' => 'dashboard.php',
    'pinScope' => 'user',
    'pollIntervalMs' => 5000,
];

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>پیام به پشتیبانی</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600&display=swap" rel="stylesheet">
<?php if($cssFile !== ''){ ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . $cssFile, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int)$cssVer; ?>">
<?php } ?>
<style>
html,body{margin:0;padding:0;background:#0e1621;color:#e4ecf4;height:100%;overflow:hidden;}
#support-v2-root{height:100%;}
</style>
</head>
<body>

<div id="support-v2-root"></div>

<script>
window.SUPPORT_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<?php if(!is_file($assetDir . '/' . $jsFile)){ ?>
<div style="padding:24px;color:#fecaca;font-family:sans-serif;text-align:center;">
    فایل‌های UI ساخته نشده‌اند. در پوشه <code>support-ui</code> دستور <code>npm run build</code> را اجرا کنید.
</div>
<?php } else { ?>
<script type="module" src="<?php echo htmlspecialchars($assetBase . $jsFile, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int)$jsVer; ?>"></script>
<?php } ?>

</body>
</html>

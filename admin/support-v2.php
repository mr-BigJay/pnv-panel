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

$initialUser = supportNormalizeUsername(
    $supportV2InitialUser
    ?? $_GET['user']
    ?? ''
);

$config = supportV2AdminConfig([
    'embedded' => (bool)$supportEmbedded,
    'initialUser' => $initialUser,
]);

$rootHeight = $supportEmbedded ? '100%' : '100vh';
$assets = supportV2AssetMeta();

if(!$supportEmbedded){
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>پیام‌های کاربران</title>
<?php supportV2RenderHeadAssets(); ?>
</head>
<body style="margin:0;background:#0e1621;min-height:100vh;">
<?php } ?>

<div id="support-v2-root" data-support-ui="v2" style="height:<?php echo htmlspecialchars($rootHeight, ENT_QUOTES, 'UTF-8'); ?>;"></div>

<script>
window.SUPPORT_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<?php if($supportEmbedded){ ?>
<?php /* CSS + module script loaded from admin/index.php head/footer */ ?>
<?php } elseif(!$assets['hasJs']){ ?>
<div style="padding:24px;color:#fecaca;font-family:sans-serif;text-align:center;">
    فایل‌های UI ساخته نشده‌اند. اسکریپت <code>restore-support-telegram.sh</code> را اجرا کنید.
</div>
<?php } else { ?>
<?php supportV2RenderModuleScript(); ?>
<?php } ?>

<?php if(!$supportEmbedded){ ?>
</body>
</html>
<?php } ?>

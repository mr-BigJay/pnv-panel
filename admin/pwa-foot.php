<?php

if(!function_exists('pnvAdminUrl')){
    require_once __DIR__ . '/auth.php';
}

$pnvPwaLoggedIn = !empty($pnvPwaLoggedIn);

?>
<script>
window.PNV_ADMIN_PWA = {
    loggedIn: <?php echo $pnvPwaLoggedIn ? 'true' : 'false'; ?>,
    pollUrl: <?php echo json_encode(pnvAdminUrl('support-api.php'), JSON_UNESCAPED_UNICODE); ?>,
    supportUrl: <?php echo json_encode(pnvAdminUrl('index.php?page=support'), JSON_UNESCAPED_UNICODE); ?>,
    swUrl: <?php echo json_encode(pnvAdminUrl('sw.js'), JSON_UNESCAPED_UNICODE); ?>,
    swScope: <?php echo json_encode(rtrim(PNV_ADMIN_BASE, '/') . '/', JSON_UNESCAPED_UNICODE); ?>,
    vapidUrl: <?php echo json_encode(pnvAdminUrl('push-vapid.php'), JSON_UNESCAPED_UNICODE); ?>,
    subscribeUrl: <?php echo json_encode(pnvAdminUrl('push-subscribe.php'), JSON_UNESCAPED_UNICODE); ?>,
    promptNotifications: <?php echo $pnvPwaLoggedIn ? 'true' : 'false'; ?>
};
</script>
<script src="<?php echo htmlspecialchars(pnvAdminUrl('pwa.js?v=2'), ENT_QUOTES, 'UTF-8'); ?>"></script>

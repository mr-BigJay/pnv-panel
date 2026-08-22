<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/user-profile-render.php';

$pnvRootDir = dirname(__DIR__);

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<div class="profileOverlay" onclick="typeof closeProfileModal===\'function\'&&closeProfileModal()"></div>';
    echo '<div class="profileModal"><p style="padding:16px;text-align:center">دسترسی مجاز نیست. صفحه را رفرش کنید و دوباره وارد شوید.</p></div>';
    exit;
}

$username = trim($_GET['user'] ?? $_POST['user'] ?? '');

if($username === ''){
    http_response_code(400);
    exit('user required');
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_link'])){
    header('Content-Type: application/json; charset=utf-8');

    $subscriptionLib = $pnvRootDir . '/subscription_lib.php';
    if(!is_file($subscriptionLib)){
        echo json_encode(['ok' => false, 'error' => 'ماژول اشتراک در دسترس نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once $subscriptionLib;

    $result = pnvClearUserSubscriptionLink(
        $username,
        trim((string)($_POST['tracking'] ?? '')),
        intval($_POST['timestamp'] ?? 0)
    );

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$page = intval($_GET['p'] ?? 1);
$showAll = isset($_GET['all']) && $_GET['all'] === '1';

echo pnvAdminUserProfileHtml($username, $page, $showAll, ['context' => 'api']);

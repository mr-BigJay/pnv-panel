<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی مجاز نیست']);
    exit;
}

require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';

if(is_file(__DIR__ . '/campaign_lib.php')){
    require_once __DIR__ . '/campaign_lib.php';
}
else{
    require_once __DIR__ . '/pnv_campaign_bootstrap.php';
}

if(!function_exists('campaignDiscountInactiveReason')){
    echo json_encode([
        'ok' => false,
        'error' => 'سیستم کد تخفیف ادمین روی سرور فعال نیست. فایل campaign_lib.php را آپدیت کنید.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = $_SESSION['user'];
$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$plan = trim($_GET['plan'] ?? $_POST['plan'] ?? '');
$preview = !empty($_GET['preview']) || !empty($_POST['preview']);
$baseOnly = !empty($_GET['base']) || !empty($_POST['base']);

$plans = function_exists('pnvLoadPlans') ? pnvLoadPlans() : [];

if(!is_array($plans)){
    $plans = [];
}

if($baseOnly){
    echo json_encode(checkoutValidateDiscountCodeBase($username, $code), JSON_UNESCAPED_UNICODE);
    exit;
}

if($preview && $plan === ''){
    echo json_encode(checkoutPreviewDiscountCode($username, $code, $plans), JSON_UNESCAPED_UNICODE);
    exit;
}

if($plan === ''){
    echo json_encode(['ok' => false, 'error' => 'ابتدا پلن را انتخاب کنید'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = checkoutCalculateDiscountCode($username, $code, $plan, $plans);

echo json_encode($result, JSON_UNESCAPED_UNICODE);

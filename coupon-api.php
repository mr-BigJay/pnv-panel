<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی مجاز نیست']);
    exit;
}

require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/campaign_lib.php';

$username = $_SESSION['user'];
$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$plan = trim($_GET['plan'] ?? $_POST['plan'] ?? '');

$plans = [];

if(file_exists(__DIR__ . '/db/plans.json')){
    $plans = json_decode(file_get_contents(__DIR__ . '/db/plans.json'), true);
}

if(!is_array($plans)){
    $plans = [];
}

if($plan === ''){
    echo json_encode(['ok' => false, 'error' => 'ابتدا پلن را انتخاب کنید'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = checkoutCalculateDiscountCode($username, $code, $plan, $plans);

echo json_encode($result, JSON_UNESCAPED_UNICODE);

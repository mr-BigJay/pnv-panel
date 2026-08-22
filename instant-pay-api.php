<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'ورود لازم است']);
    exit;
}

require_once __DIR__ . '/subscription_lib.php';
require_once __DIR__ . '/instant_pay_lib.php';
require_once __DIR__ . '/coupon_lib.php';
if(is_file(__DIR__ . '/campaign_lib.php')){
    require_once __DIR__ . '/campaign_lib.php';
}
else{
    require_once __DIR__ . '/pnv_campaign_bootstrap.php';
}
require_once __DIR__ . '/telegram_lib.php';

$username = $_SESSION['user'];
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'status'));

$plans = function_exists('pnvLoadPlans') ? pnvLoadPlans() : [];

if(!is_array($plans)){
    $plans = [];
}

if($action === 'status'){
    $id = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
    $item = instantPayGet($id);

    if(!$item || ($item['user'] ?? '') !== $username){
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'سفارش پیدا نشد']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'item' => instantPayPublicView($item)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'cancel'){
    $id = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
    instantPayCancelUserWaiting($username, $id !== '' ? $id : null);
    echo json_encode(['ok' => true, 'cancelled' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if($action === 'create'){
    $input = $_POST;

    if(empty($input)){
        $json = json_decode(file_get_contents('php://input') ?: '[]', true);
        if(is_array($json)){
            $input = $json;
        }
    }

    $type = trim((string)($input['type'] ?? 'خرید'));
    $plan = trim((string)($input['plan'] ?? ''));
    $card = trim((string)($input['card'] ?? ''));
    $cardName = trim((string)($input['card_name'] ?? ''));
    $subname = trim((string)($input['subname'] ?? ''));
    $sub = trim((string)($input['sub'] ?? ''));
    $hasCoupon = !empty($input['has_coupon']);
    $couponCode = trim((string)($input['coupon_code'] ?? ''));
    $discountPercent = 0;
    $discountSource = '';
    $discountFinalThousands = 0;
    $discountType = '';
    $discountValue = 0;

    if($hasCoupon){
        if($couponCode === ''){
            echo json_encode(['ok' => false, 'error' => 'کد تخفیف را وارد کنید'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $couponResult = checkoutCalculateDiscountCode($username, $couponCode, $plan, $plans);

        if(empty($couponResult['ok'])){
            echo json_encode(['ok' => false, 'error' => $couponResult['error'] ?? 'کد تخفیف معتبر نیست'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $discountPercent = intval($couponResult['percent'] ?? 0);
        $discountSource = (string)($couponResult['source'] ?? 'referral');
        $discountFinalThousands = intval($couponResult['final'] ?? 0);
        $discountType = (string)($couponResult['type'] ?? 'percent');
        $discountValue = intval($couponResult['value'] ?? $discountPercent);
    }

    $result = instantPayCreate([
        'user' => $username,
        'type' => $type,
        'plan' => $plan,
        'subname' => $subname,
        'sub' => $sub,
        'card' => $card,
        'card_name' => $cardName,
        'plans' => $plans,
        'coupon_code' => $hasCoupon ? $couponCode : '',
        'discount_percent' => $discountPercent,
        'discount_source' => $discountSource,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'discount_final_thousands' => $discountFinalThousands,
    ]);

    if(
        !empty($result['ok'])
        && !empty($result['item']['id'])
        && $hasCoupon
        && $discountSource === 'admin_discount'
    ){
        $reserve = campaignDiscountValidate(
            $username,
            $couponCode,
            $plan,
            $plans,
            true,
            (string)$result['item']['id']
        );

        if(empty($reserve['ok'])){
            instantPayCancelUserWaiting($username, (string)$result['item']['id']);
            echo json_encode(['ok' => false, 'error' => $reserve['error'] ?? 'رزرو کد تخفیف ناموفق بود'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'action نامعتبر']);

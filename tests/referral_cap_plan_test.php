<?php

require_once __DIR__ . '/../coupon_lib.php';

$plans = [['name' => 'CapPlan', 'price' => 500, 'days' => '30']];
$planValue = 'CapPlan - ' . couponFormatPriceThousands(500);
$owner = '_cap_plan_owner';
$code = 'CAPPLAN100';

$coupons = array_values(array_filter(couponLoadCoupons(), static function($c) use ($code){
    return strtoupper($c['code'] ?? '') !== $code;
}));

$coupons[] = [
    'code' => $code,
    'owner' => $owner,
    'percent' => 100,
    'tier_id' => 'tier_test',
    'max_discount_thousands' => 300,
    'created_at' => time(),
    'used' => false,
    'used_at' => null,
    'used_by' => null,
];
couponSaveCoupons($coupons);

$users = couponLoadUsers();
$hasOwner = false;

foreach($users as $user){
    if(($user['username'] ?? '') === $owner){
        $hasOwner = true;
        break;
    }
}

if(!$hasOwner){
    $users[] = [
        'username' => $owner,
        'password' => 'x',
        'mobile' => '09000000001',
        'referral_code' => 'CAPTEST',
    ];
    couponSaveUsers($users);
}

$result = couponCalculateForPlan($owner, $code, $planValue, $plans);
$ok = !empty($result['ok'])
    && intval($result['final_amount']) === 200
    && intval($result['discount_amount']) === 300;

echo $ok ? "PASS capped plan calculate 500->200\n" : "FAIL capped plan calculate\n";
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
exit($ok ? 0 : 1);

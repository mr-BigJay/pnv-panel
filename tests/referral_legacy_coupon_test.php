<?php

require_once __DIR__ . '/../coupon_lib.php';

$plans = [
    ['name' => 'Test', 'price' => 500, 'days' => '30'],
];

$planValue = 'Test - ' . couponFormatPriceThousands(500);

$coupons = couponLoadCoupons();
$testCode = 'LEGACYTEST01';
$owner = '_legacy_test_owner';

foreach($coupons as $i => $c){
    if(strtoupper($c['code'] ?? '') === $testCode){
        unset($coupons[$i]);
    }
}

$coupons[] = [
    'code' => $testCode,
    'owner' => $owner,
    'percent' => 100,
    'created_at' => time(),
    'used' => false,
    'used_at' => null,
    'used_by' => null,
];

couponSaveCoupons(array_values($coupons));

$users = couponLoadUsers();
$found = false;

foreach($users as $i => $user){
    if(($user['username'] ?? '') === $owner){
        $found = true;
        break;
    }
}

if(!$found){
    $users[] = [
        'username' => $owner,
        'password' => 'x',
        'mobile' => '09000000000',
        'referral_code' => 'LEGTEST',
    ];
    couponSaveUsers($users);
}

$result = couponCalculateForPlan($owner, $testCode, $planValue, $plans);

$ok = !empty($result['ok'])
    && intval($result['final_amount']) === 0
    && intval($result['max_discount_thousands']) === 0
    && ($result['tier_id'] ?? '') === '';

echo $ok ? "PASS legacy coupon without cap fields\n" : "FAIL legacy coupon\n";
echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";

exit($ok ? 0 : 1);

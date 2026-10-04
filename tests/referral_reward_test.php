<?php

require_once __DIR__ . '/../coupon_lib.php';

$config = couponReferralDefaultConfig();
$config['tiers'] = [
    [
        'id' => 'tier_test',
        'referrals' => 3,
        'percent' => 100,
        'qty' => 1,
        'max_discount_thousands' => 300,
        'enabled' => true,
    ],
];
couponSaveReferralConfig($config);

$reward = couponRewardForCount(3);

$ok = intval($reward['percent']) === 100
    && intval($reward['qty']) === 1
    && intval($reward['need']) === 3;

echo $ok ? "PASS reward at 3 referrals\n" : "FAIL reward at 3 referrals\n";
echo json_encode($reward, JSON_UNESCAPED_UNICODE) . "\n";

exit($ok ? 0 : 1);

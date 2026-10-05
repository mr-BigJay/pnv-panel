<?php

require_once __DIR__ . '/../coupon_lib.php';

$post = [
    'referral_enabled' => '1',
    'tiers' => [
        'r_a' => [
            'id' => 'tier_3',
            'referrals' => '3',
            'percent' => '20',
            'qty' => '1',
            'max_discount_thousands' => '0',
            'enabled' => '1',
        ],
        'r_c' => [
            'id' => 'tier_10',
            'referrals' => '10',
            'percent' => '100',
            'qty' => '1',
            'max_discount_thousands' => '300',
        ],
        'r_d' => [
            'id' => 'tier_new',
            'referrals' => '7',
            'percent' => '50',
            'qty' => '2',
            'max_discount_thousands' => '150',
            'enabled' => '1',
        ],
    ],
    'new_user_reward_percent' => '15',
    'new_user_reward_max' => '100',
    'referrer_purchase_percent' => '5',
    'referrer_purchase_cap' => '500',
];

$config = couponNormalizeReferralConfig(couponBuildReferralConfigFromAdminPost($post));

$byId = [];

foreach($config['tiers'] as $tier){
    $byId[$tier['id']] = $tier;
}

$ok = count($config['tiers']) === 3
    && ($byId['tier_10']['max_discount_thousands'] ?? -1) === 300
    && ($byId['tier_10']['enabled'] ?? true) === false
    && ($byId['tier_new']['qty'] ?? 0) === 2
    && ($byId['tier_new']['referrals'] ?? 0) === 7
    && empty($config['new_user_reward']['enabled'])
    && empty($config['referrer_purchase_reward']['enabled']);

echo $ok ? "PASS tier form nested parse\n" : "FAIL tier form nested parse\n";
echo json_encode($config['tiers'], JSON_UNESCAPED_UNICODE) . "\n";

exit($ok ? 0 : 1);

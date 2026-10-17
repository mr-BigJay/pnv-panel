<?php

require_once __DIR__ . '/../coupon_lib.php';

$users = [
    [
        'username' => 'Ali',
        'mobile' => '09121111111',
        'referrer' => '',
        'referral_code' => 'ALICODE',
        'created_at' => '2026-01-01 10:00:00',
    ],
    [
        'username' => 'Reza',
        'mobile' => '09122222222',
        'referrer' => 'ALICODE',
        'referral_code' => 'REZCODE',
        'created_at' => '2026-01-02 10:00:00',
    ],
];

$indexes = couponBuildUserReferrerIndexes($users);
$referrer = couponResolveReferrerUsername($users[1], $indexes);

$ok = strcasecmp($referrer, 'Ali') === 0
    && !couponReferralIsSelf($users[1], $indexes);

echo $ok ? "PASS normal referral resolve\n" : "FAIL normal referral resolve\n";
exit($ok ? 0 : 1);

<?php

require_once __DIR__ . '/../coupon_lib.php';

$users = [
    [
        'username' => 'Hosein',
        'mobile' => '09004900128',
        'referrer' => '09004900128',
        'referral_code' => 'HOSEIN1',
    ],
    [
        'username' => 'Ali',
        'mobile' => '09120000001',
        'referrer' => 'HOSEIN1',
        'referral_code' => 'ALI001',
    ],
];

$indexes = couponBuildUserReferrerIndexes($users);

$selfResolved = couponResolveReferrerUsername($users[0], $indexes);
$selfWasReferred = couponUserWasReferredBy($users[0], $users[0]);
$ok = $selfResolved === ''
    && $selfWasReferred === false
    && couponReferralIsSelf($users[0], $indexes)
    && couponResolveReferrerUsername($users[1], $indexes) === 'Hosein';

echo $ok ? "PASS self-referral blocked\n" : "FAIL self-referral blocked\n";
echo 'resolve=' . var_export($selfResolved, true) . "\n";

exit($ok ? 0 : 1);

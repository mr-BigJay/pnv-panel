<?php

require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../xui_lib.php';

function xuiRenewTestAssert($label, $condition){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }

    echo "ok: {$label}\n";
}

$dirty = 'https://vip.boozhaan.ir:2096/sub/qw2bir9fvzhf2col             ثبت درخواست تمدید      بازگشت';
$parsed = xuiParseSubLink($dirty);
xuiRenewTestAssert('parse dirty renew link', is_array($parsed) && ($parsed['sub_id'] ?? '') === 'qw2bir9fvzhf2col');

$trail = 'https://vip3.boozhaan.ir:2096/sub/y3c4ezykoudk3eru/';
$parsedTrail = xuiParseSubLink($trail);
xuiRenewTestAssert('parse trailing slash', is_array($parsedTrail) && ($parsedTrail['sub_id'] ?? '') === 'y3c4ezykoudk3eru');

xuiRenewTestAssert('bare sub id detection', xuiLooksLikeBareSubId('qw2bir9fvzhf2col'));

$rowRenew = [
    'user1',
    'https://vip.boozhaan.ir:2096/sub/abc12345',
    '10 گیگ - 250 هزار تومان',
    'AUTO-0001',
    '',
    '',
    'درحال بررسی',
    '',
    time(),
    'تمدید',
];
xuiRenewTestAssert('payment row type renew', xuiPaymentRowType($rowRenew, 'خرید') === 'تمدید');

$rowMissingType = $rowRenew;
$rowMissingType[9] = '';
xuiRenewTestAssert('infer renew from sub link column', xuiPaymentRowType($rowMissingType, '') === 'تمدید');

$bulkOk = [
    'success' => true,
    'obj' => ['adjusted' => 1],
];
xuiRenewTestAssert('bulkAdjust applied', xuiBulkAdjustApplied($bulkOk, 'alice@x'));

$bulkSkipped = [
    'success' => true,
    'obj' => [
        'adjusted' => 0,
        'skipped' => [['email' => 'bob', 'reason' => 'unlimited traffic']],
    ],
];
xuiRenewTestAssert('bulkAdjust skipped email', !xuiBulkAdjustApplied($bulkSkipped, 'bob'));

echo "all xui renew resolve tests passed\n";

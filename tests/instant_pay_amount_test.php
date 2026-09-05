<?php

/**
 * تست سریع تولید/پارس مبلغ پرداخت آنی
 * اجرا: php tests/instant_pay_amount_test.php
 */

require_once dirname(__DIR__) . '/bale_lib.php';
require_once dirname(__DIR__) . '/instant_pay_lib.php';

$fail = 0;

function assertTrue($cond, $msg){
    global $fail;
    if($cond){
        echo "OK  $msg\n";
    }
    else{
        echo "FAIL $msg\n";
        $fail++;
    }
}

// کدها مضرب ۱۰ و مبلغ تومان صحیح
for($i = 0; $i < 10; $i++){
    $code = (string)(random_int(100, 999) * 10);
    $amount = instantPayBuildAmountRial(1500000, $code);
    assertTrue($amount % 10 === 0, "amount whole toman for code $code → $amount");
    assertTrue($amount < 1500000, "amount under list price for code $code → $amount");
    assertTrue(($amount % 10000) === intval($code), "last4 equals code $code → $amount");
}

// نمونه واقعی پست‌بانک (ي عربی، +مبلغ، مانده)
$sample1 = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/10\n9:47\nمانده: 44,108,899 ريال";
$sample2 = "پست بانک\nواريز به کارت: 6156\n+3,698,233\n1405/05/9\n20:46\nمانده: 43,110,709 ريال";

assertTrue(baleLooksLikeDeposit($sample1), 'sample1 looks like deposit');
assertTrue(baleLooksLikePostBankCardDeposit($sample1), 'sample1 card deposit');
assertTrue(baleLooksLikeDeposit($sample1) === baleLooksLikePostBankCardDeposit($sample1), 'deposit alias matches card deposit');
assertTrue(!baleLooksLikeDeposit("پست بانک\nموجودی حساب شما"), 'generic postbank without deposit rejected');
assertTrue(baleLooksLikePostBankNotice($sample1), 'sample1 is postbank notice');

$a1 = baleExtractRialAmounts($sample1);
$a2 = baleExtractRialAmounts($sample2);

assertTrue($a1 === [998190], 'sample1 deposit only, not balance. got ' . json_encode($a1));
assertTrue($a2 === [3698233], 'sample2 deposit only, not balance. got ' . json_encode($a2));

// نباید مانده را به‌عنوان کاندید بیاورد
$c1 = instantPayExpandAmountCandidates($a1, ['rial_only' => true]);
assertTrue($c1 === [998190], 'no toman×10 expand for postbank. got ' . json_encode($c1));
assertTrue(!in_array(44108899, $c1, true), 'balance must not be candidate');

// بدون mbstring هم باید کار کند
if(function_exists('mb_stripos')){
    assertTrue(true, 'mbstring available');
}
else{
    assertTrue(baleContains('پست بانک واریز', 'پست بانک'), 'baleContains works without mbstring');
}

$forwarded = [
    'text' => '',
    'forward_from_message' => ['text' => $sample1],
    'chat' => ['id' => '1']
];
assertTrue(baleExtractMessageText($forwarded) !== '', 'forwarded postbank text extracted');

$forwardTop = [
    'text' => $sample1,
    'forward_from_chat' => ['username' => 'postbank_bot'],
    'chat' => ['id' => '1']
];
assertTrue(baleExtractMessageText($forwardTop) === $sample1, 'top-level forwarded text extracted');
assertTrue(baleLooksLikePostBankForward($forwardTop), 'postbank forward detected by username');

$nestedReply = [
    'text' => '',
    'reply_to_message' => ['text' => $sample2],
    'chat' => ['id' => '1']
];
assertTrue(baleExtractMessageText($nestedReply) === $sample2, 'reply_to_message text extracted');

$groupForward = [
    'text' => $sample1,
    'chat' => ['id' => '-100999', 'type' => 'group'],
    'from' => ['id' => '555001'],
    'forward_from_chat' => ['username' => 'postbank_bot'],
];
assertTrue(baleIsAdminMessage($groupForward, ['admin_chat_ids' => '555001']), 'group forward allowed by sender user id');
assertTrue(baleIsAdminMessage($groupForward, ['admin_chat_ids' => '-100999']), 'group forward allowed by group chat id');
assertTrue(!baleIsAdminMessage($groupForward, ['admin_chat_ids' => '999888']), 'wrong admin id rejected');

$forwardOrigin = [
    'text' => $sample1,
    'chat' => ['id' => '555001', 'type' => 'private'],
    'forward_origin' => [
        'type' => 'channel',
        'chat' => ['username' => 'postbank_bot'],
        'message' => ['text' => $sample1],
    ],
];
assertTrue(baleExtractMessageText($forwardOrigin) !== '', 'forward_origin nested text extracted');
assertTrue(baleForwardSourceLabel($forwardOrigin) === '@postbank_bot', 'forward_origin source label');

// پارس فرمت‌های دیگر
$samples = [
    'واریز 1,499,280 ریال به حساب شما' => 1499280,
    'مبلغ: ۱٬۴۹۹٬۲۸۰ ریال' => 1499280,
    'واریز 149,928 تومان' => 1499280,
];

foreach($samples as $text => $expect){
    $got = baleExtractRialAmounts($text);
    assertTrue(in_array($expect, $got, true), "parse [$text] expect $expect got " . json_encode($got));
}

// سفارش منقضی‌شده در بازه grace هنوز قابل مچ است
$now = time();
$expiredItem = [
    'id' => 'test-expired',
    'status' => 'expired',
    'amount' => 2492920,
    'currency' => 'rial',
    'code' => 2920,
    'expires_at' => $now - 120,
    'csv_index' => 0,
    'user' => 'demo'
];
assertTrue(instantPayItemMatchable($expiredItem, $now), 'expired order within grace is matchable');
assertTrue(instantPayMatchAmountExact(2492920) === null, 'no match without stored order');

$oldExpired = $expiredItem;
$oldExpired['expires_at'] = $now - instantPayMatchGraceSeconds() - 60;
assertTrue(!instantPayItemMatchable($oldExpired, $now), 'expired order outside grace is not matchable');

$cancelledFresh = [
    'id' => 'test-cancelled',
    'status' => 'cancelled',
    'amount' => 1498650,
    'currency' => 'rial',
    'code' => 8650,
    'expires_at' => $now + 600,
    'cancelled_at' => $now - 60,
    'csv_index' => -1,
    'csv_purged' => true,
    'user' => 'demo',
];
assertTrue(instantPayItemMatchable($cancelledFresh, $now), 'cancelled order within cancel grace is matchable');
$oldCancelled = $cancelledFresh;
$oldCancelled['cancelled_at'] = $now - instantPayCancelGraceSeconds() - 60;
assertTrue(!instantPayItemMatchable($oldCancelled, $now), 'cancelled order outside cancel grace is not matchable');

// CSV fallback matcher
$csvRow = array_fill(0, 14, '');
$csvRow[0] = 'demo';
$csvRow[2] = '20 گیگ';
$csvRow[3] = 'AUTO-2920';
$csvRow[6] = 'درحال بررسی';
$csvRow[8] = time();
$csvRow[9] = 'خرید';
$csvRow[12] = 2492920;
$csvRow[13] = 2920;
assertTrue(instantPayCsvRowPending($csvRow), 'csv pending row detected');
assertTrue(instantPayCsvRowAmountRial($csvRow) === 2492920, 'csv amount column');

$visibleRow = $csvRow;
assertTrue(instantPayAdminRowVisible($visibleRow), 'auto pending without json visible within admin window');
$meta = instantPayAdminRowStatusMeta($visibleRow);
assertTrue(($meta['title'] ?? '') === 'در حال بررسی', 'in-progress status label');

$oldRow = $csvRow;
$oldRow[8] = time() - instantPayAdminVisibilitySeconds() - 60;
assertTrue(instantPayAdminRowVisible($oldRow), 'auto pending outside review window still visible as expired');
assertTrue(instantPayResolveDisplayTab($oldRow) === 'expired', 'old auto order is expired tab');

$cancelRow = $csvRow;
$cancelRow[8] = time();
instantPaySave([[
    'id' => 't2',
    'user' => 'demo',
    'type' => 'خرید',
    'status' => 'cancelled',
    'code' => 2920,
    'expires_at' => time() + 600,
    'csv_index' => 0,
]]);
assertTrue(!instantPayAdminRowVisible($cancelRow), 'cancelled json hides admin row');

$items = [[
    'id' => 't1',
    'user' => 'demo',
    'type' => 'خرید',
    'status' => 'waiting',
    'code' => 2920,
    'expires_at' => time() + 600,
    'csv_index' => 0,
]];
instantPaySave($items);
assertTrue(instantPayAdminRowVisible($visibleRow), 'auto pending with active waiting json visible');

$expiredRow = $csvRow;
$expiredRow[8] = time() - 2000;
instantPaySave([[
    'id' => 't3',
    'user' => 'demo',
    'type' => 'خرید',
    'status' => 'expired',
    'code' => 2920,
    'expires_at' => time() - 100,
    'csv_index' => 0,
]]);
assertTrue(instantPayAdminRowVisible($expiredRow), 'expired json still visible within admin window');

$meta = instantPayAdminRowStatusMeta($visibleRow);
assertTrue(($meta['title'] ?? '') === 'در حال بررسی', 'waiting json status label');

$processingFresh = [
    'id' => 'proc-fresh',
    'status' => 'processing',
    'processing_at' => time(),
    'created_at' => time(),
    'expires_at' => time() + 600,
];
assertTrue(!instantPayItemMatchable($processingFresh), 'fresh processing is not matchable');
assertTrue(!instantPayProcessingIsStale($processingFresh), 'fresh processing is not stale');

$processingStale = [
    'id' => 'proc-stale',
    'status' => 'processing',
    'processing_at' => time() - instantPayProcessingTimeoutSeconds() - 5,
    'created_at' => time() - 3600,
    'expires_at' => time() - 60,
];
assertTrue(instantPayProcessingIsStale($processingStale), 'old processing is stale');
instantPaySave([$processingStale]);
instantPayRecoverStaleProcessing();
$reloaded = instantPayLoad()[0] ?? [];
assertTrue(($reloaded['status'] ?? '') === 'failed', 'stale processing recovered to failed');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

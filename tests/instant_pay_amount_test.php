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
assertTrue(!instantPayAdminRowVisible($oldRow), 'auto pending outside admin window hidden');

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

// همگام‌سازی پلن: سفارش waiting با قیمت قدیمی باید لغو شود
$plansOld = [['name' => '10 گیگ', 'price' => 150, 'days' => 'نامحدود', 'id' => 'p1']];
$plansNew = [['name' => '10 گیگ', 'price' => 200, 'days' => 'نامحدود', 'id' => 'p1']];
$staleOrder = [
    'id' => 'stale-plan',
    'user' => 'demo',
    'type' => 'خرید',
    'status' => 'waiting',
    'plan_value' => '10 گیگ - 150 هزار تومان',
    'plan_signature' => pnvPlanSignature($plansOld[0]),
    'plan_price_thousands' => 150,
    'code' => 1010,
    'amount' => instantPayBuildAmountRial(instantPayBaseRial(150), 1010),
    'currency' => 'rial',
    'expires_at' => time() + 600,
    'csv_index' => -1,
    'card' => '6037',
];
instantPaySave([$staleOrder]);
$result = instantPayInvalidateStaleOrders($plansNew);
assertTrue(intval($result['cancelled'] ?? 0) >= 1, 'stale waiting order cancelled after plan price change');
$reloaded = instantPayGet('stale-plan');
assertTrue(is_array($reloaded) && ($reloaded['status'] ?? '') === 'cancelled', 'stale order status cancelled');

$freshOrder = [
    'id' => 'fresh-plan',
    'user' => 'demo2',
    'type' => 'خرید',
    'status' => 'waiting',
    'plan_value' => pnvPlanOptionValue($plansNew[0]),
    'plan_signature' => pnvPlanSignature($plansNew[0]),
    'plan_price_thousands' => 200,
    'code' => 2020,
    'amount' => instantPayBuildAmountRial(instantPayBaseRial(200), 2020),
    'currency' => 'rial',
    'expires_at' => time() + 600,
    'csv_index' => -1,
    'card' => '6037',
];
assertTrue(instantPayOrderMatchesPlans($freshOrder, $plansNew), 'fresh order matches current plans');

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

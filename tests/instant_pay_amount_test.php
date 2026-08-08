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

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

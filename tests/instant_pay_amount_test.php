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
for($i = 0; $i < 30; $i++){
    $code = (string)(random_int(100, 999) * 10);
    $amount = instantPayBuildAmountRial(1500000, $code);
    assertTrue($amount % 10 === 0, "amount whole toman for code $code → $amount");
    assertTrue($amount < 1500000, "amount under list price for code $code → $amount");
    assertTrue(($amount % 10000) === intval($code), "last4 equals code $code → $amount");
}

// پارس فرمت‌های رایج بانک
$samples = [
    'واریز 1,499,280 ریال به حساب شما' => 1499280,
    'مبلغ: ۱٬۴۹۹٬۲۸۰ ریال' => 1499280,
    'واریز 1.499.280 ریال' => 1499280,
    'واریز 149,928 تومان' => 1499280,
    'بستانکار 149928 تومان' => 1499280,
    'مبلغ 149928' => 149928, // بدون واحد؛ لایه مچ ×۱۰ را هم امتحان می‌کند
];

foreach($samples as $text => $expect){
    $got = baleExtractRialAmounts($text);
    assertTrue(in_array($expect, $got, true), "parse [$text] expect $expect got " . json_encode($got));
}

$expanded = instantPayExpandAmountCandidates([149928]);
assertTrue(in_array(1499280, $expanded, true), 'expand toman 149928 → 1499280 rial');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

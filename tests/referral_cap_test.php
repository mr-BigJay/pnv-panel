<?php

require_once __DIR__ . '/../coupon_lib.php';

$cases = [
    [500, 100, 300, 300, 200],
    [200, 100, 300, 200, 0],
    [500, 20, 0, 100, 400],
];

$ok = true;

foreach($cases as [$price, $percent, $cap, $expectDiscount, $expectFinal]){
    $calc = couponCalculateDiscountThousands($price, $percent, $cap);

    if(
        intval($calc['discount_amount']) !== $expectDiscount
        || intval($calc['final_amount']) !== $expectFinal
    ){
        $ok = false;
        echo "FAIL price=$price percent=$percent cap=$cap\n";
        echo json_encode($calc, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo $ok ? "PASS referral cap calculations\n" : "FAIL referral cap calculations\n";
exit($ok ? 0 : 1);

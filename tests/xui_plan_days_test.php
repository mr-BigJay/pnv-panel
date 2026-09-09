<?php

require_once __DIR__ . '/../xui_lib.php';

function assertEq($label, $expected, $actual){
    if($expected !== $actual){
        fwrite(STDERR, "FAIL {$label}: expected {$expected}, got {$actual}\n");
        exit(1);
    }

    echo "OK {$label}\n";
}

assertEq('xuiParsePlanDays 1 month', 30, xuiParsePlanDays('20 گیگ - 250 هزار تومان - 1 ماهه'));
assertEq('xuiParsePlanDays 3 months', 90, xuiParsePlanDays('50 گیگ - 500 هزار تومان - 3 ماهه'));
assertEq('xuiParsePlanDays 30 days', 30, xuiParsePlanDays('10 گیگ - 150 هزار تومان - 30 روزه'));

$now = 1700000000 * 1000;
$expiry30 = xuiExpiryTimeMsFromDays(30, $now);
assertEq('xuiExpiryTimeMsFromDays adds 30 days', $now + (30 * 86400 * 1000), $expiry30);

$future = $now + (10 * 86400 * 1000);
$extended = xuiExtendExpiryMs($future, 30);
assertEq('xuiExtendExpiryMs stacks on existing expiry', $future + (30 * 86400 * 1000), $extended);

assertEq('xuiExpiryTimeMsFromDays zero for unlimited', 0, xuiExpiryTimeMsFromDays(0));

echo "All xui plan days tests passed.\n";

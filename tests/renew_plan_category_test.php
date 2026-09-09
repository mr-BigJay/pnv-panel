<?php

require_once __DIR__ . '/../xui_lib.php';
require_once __DIR__ . '/../plan_ui_lib.php';

function assertEq($label, $expected, $actual){
    if($expected !== $actual){
        fwrite(STDERR, "FAIL {$label}: expected {$expected}, got {$actual}\n");
        exit(1);
    }

    echo "OK {$label}\n";
}

assertEq(
    'xuiParsePlanDays prefers month suffix over catalog name',
    30,
    xuiParsePlanDays('20 گیگ - 250 هزار تومان - 1 ماهه')
);

assertEq(
    'xuiParsePlanDays prefers day suffix',
    30,
    xuiParsePlanDays('10 گیگ - 150 هزار تومان - 30 روزه')
);

assertEq(
    'xuiParsePlanDays month without suffix letter',
    60,
    xuiParsePlanDays('50 گیگ - 500 هزار تومان - 2 ماه')
);

if(!function_exists('xuiFetchSubUserinfoExpireOriginal')){
    // When panel reports expire=0, invoice text with duration must still classify as limited.
    $category = pnvResolveSubTimeCategory(
        'https://vip.example/sub/test',
        '20 گیگ - 250 هزار تومان - 1 ماهه',
        ''
    );
    assertEq('pnvResolveSubTimeCategory honors limited invoice text over expire=0', 'limited', $category);
}

echo "All renew plan category tests passed.\n";

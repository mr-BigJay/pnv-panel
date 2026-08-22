<?php

/**
 * تست تطبیق پلن برای کد تخفیف (پلن محدود و نامحدود زمانی)
 * اجرا: php tests/coupon_plan_match_test.php
 */

require_once dirname(__DIR__) . '/coupon_lib.php';
require_once dirname(__DIR__) . '/plan_ui_lib.php';
require_once dirname(__DIR__) . '/campaign_lib.php';

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

$plans = [
    ['name' => '10 گیگ', 'price' => 150, 'days' => 'نامحدود'],
    ['name' => '20 گیگ', 'price' => 150, 'days' => '30'],
    ['name' => '50 گیگ', 'price' => 275, 'days' => '30'],
    ['name' => '100 گیگ', 'price' => 490, 'days' => '30'],
    ['name' => '20 گیگ', 'price' => 190, 'days' => '90'],
];

$unlimitedValue = pnvPlanOptionValue($plans[0]);
$limitedValue = pnvPlanOptionValue($plans[1]);

assertTrue(
    couponFindPlanByValue($unlimitedValue, $plans)['name'] === '10 گیگ',
    'unlimited plan matches coupon lookup'
);

assertTrue(
    couponFindPlanByValue($limitedValue, $plans)['days'] === '30',
    'time-limited plan matches coupon lookup'
);

assertTrue(
    strpos($limitedValue, '30 روزه') !== false,
    'limited plan value includes duration label'
);

$validation = campaignDiscountValidate('testuser', 'TEST30', $limitedValue, $plans);

assertTrue(
    !empty($validation['ok']) || ($validation['error'] ?? '') !== 'پلن انتخاب‌شده معتبر نیست',
    'campaign discount does not reject valid time-limited plan value'
);

$couponCalc = couponCalculateForPlan('ignored', 'IGNORED', $limitedValue, $plans);

assertTrue(
    ($couponCalc['error'] ?? '') === 'کد تخفیف معتبر نیست',
    'referral coupon fails on invalid code, not invalid plan'
);

exit($fail > 0 ? 1 : 0);

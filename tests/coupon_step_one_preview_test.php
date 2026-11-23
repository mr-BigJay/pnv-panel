<?php
/**
 * Scope: coupon parsing and step-one discounted-plan previews only.
 * Must never create an order, reserve a coupon or mutate payment records.
 */
require_once __DIR__ . '/../coupon_lib.php';
require_once __DIR__ . '/../plan_ui_lib.php';

$failed = 0;
function couponPreviewAssert($value, $label){
    global $failed;
    echo ($value ? 'PASS ' : 'FAIL ') . $label . "\n";
    if(!$value){ $failed++; }
}

$plans = [
    ['name' => '20 گیگ', 'price' => 300, 'days' => '30'],
    ['name' => '50 گیگ', 'price' => 600, 'days' => '90'],
    ['name' => '100 گیگ', 'price' => 1000, 'days' => 'نامحدود'],
];
$ui = pnvPlansForStepUi($plans);
couponPreviewAssert(count($ui) === 3, 'three-step plan catalog loaded');
couponPreviewAssert(strpos($ui[0]['value'], 'ماهه') !== false, 'timed plan contains duration suffix');
couponPreviewAssert(
    couponFindPlanByValue($ui[0]['value'], $plans)['name'] === '20 گیگ',
    'coupon resolves 30-day plan with the same value as three-step UI'
);
couponPreviewAssert(
    couponFindPlanByValue($ui[1]['value'], $plans)['name'] === '50 گیگ',
    'coupon resolves 90-day plan with the same value as three-step UI'
);
couponPreviewAssert(
    couponFindPlanByValue($ui[2]['value'], $plans)['name'] === '100 گیگ',
    'coupon resolves time-unlimited plan'
);
couponPreviewAssert(
    couponFindPlanByValue($plans[0]['name'].' - '.couponFormatPriceThousands($plans[0]['price']), $plans)['name'] === '20 گیگ',
    'legacy coupon value still resolves'
);
couponPreviewAssert(couponFindPlanByValue('UNLISTED - 1 میلیون تومان', $plans) === null, 'nonexistent plan remains rejected');
couponPreviewAssert(
    strpos(couponBuildPlanLabel($plans[0], 20), 'ماهه') !== false,
    'discounted 30-day invoice retains purchased plan duration'
);
couponPreviewAssert(
    strpos(couponBuildPlanLabel($plans[1], 15), 'ماهه') !== false,
    'discounted 90-day invoice retains purchased plan duration'
);

$called = [];
$calculate = function($user, $code, $value, $catalog) use (&$called, $ui){
    $called[] = $value;
    if($code !== 'SAVE20'){
        return ['ok'=>false, 'error'=>'کد تخفیف معتبر نیست'];
    }
    $plan = couponFindPlanByValue($value, $catalog);
    if($value === $ui[1]['value']){
        return ['ok'=>false, 'error'=>'پلن مشمول این کد نیست'];
    }
    $price = intval($plan['price']);
    return [
        'ok'=>true, 'percent'=>20, 'original'=>$price,
        'final'=>couponApplyDiscountThousands($price, 20),
    ];
};

$preview = couponPreviewForPlans('alice', 'SAVE20', $plans, $calculate);
couponPreviewAssert($preview['ok'] === true && count($called) === 3, 'preview checks all plans before choosing a plan');
couponPreviewAssert(intval($preview['eligible_count']) === 2, 'restricted plan excluded');
couponPreviewAssert(intval($preview['discounts'][$ui[0]['value']]['final']) === 240, '30-day plan shows 20 percent discount');
couponPreviewAssert(intval($preview['discounts'][$ui[2]['value']]['final']) === 800, 'unlimited plan shows 20 percent discount');
couponPreviewAssert(!isset($preview['discounts'][$ui[1]['value']]), 'ineligible plan has no discount');
$invalid = couponPreviewForPlans('alice', 'NOTVALID', $plans, $calculate);
couponPreviewAssert(empty($invalid['ok']) && !empty($invalid['error']), 'invalid code returns error without any eligible prices');
$empty = couponPreviewForPlans('alice', '', $plans, $calculate);
couponPreviewAssert(empty($empty['ok']), 'empty code rejected');
$referral = couponPreviewForPlans('alice', 'FRIEND15', $plans, function($user,$code,$value,$catalog){
    $plan = couponFindPlanByValue($value, $catalog);
    $original = intval($plan['price']);
    return [
        'ok'=>true, 'percent'=>15,
        'original_amount'=>$original,
        'final_amount'=>couponApplyDiscountThousands($original, 15),
    ];
});
couponPreviewAssert(intval($referral['discounts'][$ui[0]['value']]['final']) === 255,
    'referral coupon final_amount fallback shown correctly');

foreach(['buy.php', 'renew.php'] as $file){
    $html = file_get_contents(__DIR__ . '/../' . $file);
    $formStep2 = strpos($html, 'id="step2"');
    $coupon = strpos($html, 'id="hasCouponCheck"');
    $planGrid = strpos($html, 'id="planGrid"');
    couponPreviewAssert($coupon !== false && $coupon < $planGrid && $coupon < $formStep2,
        $file . ' coupon input is above plan selection in step one');
    couponPreviewAssert(strpos($html, 'mode=preview&code=') !== false,
        $file . ' reads all discounted prices before selecting a plan');
    couponPreviewAssert(strpos($html, 'couponNewPrice') !== false
        && strpos($html, 'couponDiscounts[planSelect.value]') !== false,
        $file . ' renders discounted plan prices and enforces eligibility');
    couponPreviewAssert(strpos($html, "body.set('coupon_code', couponCodeInput.value.trim())") !== false,
        $file . ' retains existing payment submit coupon field');
}
$api = file_get_contents(__DIR__ . '/../coupon-api.php');
couponPreviewAssert(strpos($api, "=== 'preview'") < strpos($api, "if(\$plan === '')"),
    'coupon preview no longer requires a selected plan');

if($failed){
    fwrite(STDERR, "FAIL {$failed} coupon checkout tests\n");
    exit(1);
}
echo "All coupon first-step checkout tests passed\n";

<?php

if(function_exists('checkoutCalculateDiscountCode')){
    return;
}

$__pnvCampaignLibCandidates = [
    __DIR__ . '/campaign_lib.php',
    dirname(__DIR__) . '/campaign_lib.php',
];

foreach($__pnvCampaignLibCandidates as $__pnvCampaignLibPath){
    if(is_file($__pnvCampaignLibPath)){
        require_once $__pnvCampaignLibPath;
        return;
    }
}

// fallback: جلوگیری از 500 اگر campaign_lib.php آپلود نشده باشد
if(!function_exists('campaignDiscountValidate')){
    function campaignDiscountValidate($username, $code, $planValue, $plans, $reserve = false, $orderId = ''){
        return ['ok' => false, 'error' => 'کد تخفیف معتبر نیست'];
    }
}

if(!function_exists('checkoutCalculateDiscountCode')){
    function checkoutCalculateDiscountCode($username, $code, $planValue, $plans){
        if(!function_exists('couponCalculateForPlan')){
            require_once __DIR__ . '/coupon_lib.php';
        }

        return couponCalculateForPlan($username, $code, $planValue, $plans);
    }
}

if(!function_exists('checkoutPrepareDiscountForOrder')){
    function checkoutPrepareDiscountForOrder($username, $code, $planValue, $plans, $orderId){
        return checkoutCalculateDiscountCode($username, $code, $planValue, $plans);
    }
}

if(!function_exists('checkoutMarkDiscountPaid')){
    function checkoutMarkDiscountPaid($item){
        if(!is_array($item) || empty($item['coupon_code']) || !function_exists('couponMarkUsed')){
            return;
        }

        couponMarkUsed($item['coupon_code'], $item['user'] ?? '');
    }
}

if(!function_exists('checkoutReleaseDiscountOrder')){
    function checkoutReleaseDiscountOrder($orderId){
        return;
    }
}

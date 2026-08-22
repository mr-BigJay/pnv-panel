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

if(!function_exists('checkoutPreviewDiscountCode')){
    function checkoutPreviewDiscountCode($username, $code, $plans){
        if(!function_exists('pnvPlansForStepUi')){
            require_once __DIR__ . '/plan_ui_lib.php';
        }

        if(!is_array($plans)){
            $plans = [];
        }

        $plansUi = pnvPlansForStepUi($plans);
        $planMap = [];
        $allowedCount = 0;
        $headlinePercent = 0;

        foreach($plansUi as $planUi){
            $value = trim((string)($planUi['value'] ?? ''));

            if($value === ''){
                continue;
            }

            $calc = checkoutCalculateDiscountCode($username, $code, $value, $plans);

            if(!empty($calc['ok'])){
                $allowedCount++;
                $headlinePercent = max($headlinePercent, intval($calc['percent'] ?? 0));
                $planMap[$value] = [
                    'allowed' => true,
                    'original' => intval($calc['original'] ?? 0),
                    'final' => intval($calc['final'] ?? 0),
                    'original_text' => $calc['original_text'] ?? '',
                    'final_text' => $calc['final_text'] ?? '',
                    'percent' => intval($calc['percent'] ?? 0),
                ];
            }
            else{
                $planMap[$value] = [
                    'allowed' => false,
                    'error' => $calc['error'] ?? 'نامعتبر برای این پلن',
                ];
            }
        }

        if($allowedCount === 0){
            return ['ok' => false, 'error' => 'این کد برای هیچ‌یک از پلن‌های فعلی قابل استفاده نیست'];
        }

        return [
            'ok' => true,
            'code' => $code,
            'percent' => $headlinePercent,
            'percent_label' => $headlinePercent . '٪',
            'plans' => $planMap,
        ];
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

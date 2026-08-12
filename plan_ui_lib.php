<?php

if(!function_exists('pnvFormatPlanPrice')){

    function pnvFormatPlanPrice($price){
        $price = intval($price);

        if($price < 1000){
            return number_format($price) . ' هزار تومان';
        }

        $million = $price / 1000;
        $million = rtrim(rtrim(number_format($million, 3), '0'), '.');

        return $million . ' میلیون تومان';
    }

    function pnvFormatPlanPriceShort($price){
        $price = intval($price);

        if($price < 1000){
            return number_format($price) . ' تومن';
        }

        $million = $price / 1000;
        $million = rtrim(rtrim(number_format($million, 3), '0'), '.');

        return $million . ' میلیون';
    }

    function pnvPlanIsUnlimited($plan){
        $days = trim((string)($plan['days'] ?? ''));

        if($days === '' || $days === 'نامحدود' || strcasecmp($days, 'unlimited') === 0){
            return true;
        }

        return intval($days) <= 0;
    }

    function pnvPlanDaysLabel($plan){
        if(pnvPlanIsUnlimited($plan)){
            return 'نامحدود زمانی';
        }

        $days = trim((string)($plan['days'] ?? ''));

        if($days === ''){
            return '—';
        }

        if(preg_match('/^\d+$/', $days)){
            $n = intval($days);

            if($n <= 0){
                return 'نامحدود زمانی';
            }

            // Exact month multiples (30-day months) → e.g. «۱ ماهه»
            if($n >= 30 && ($n % 30) === 0){
                $months = intdiv($n, 30);
                return $months . ' ماهه';
            }

            return $n . ' روزه';
        }

        return $days;
    }

    function pnvPlanOptionValue($plan){
        $name = trim((string)($plan['name'] ?? ''));
        $priceText = pnvFormatPlanPrice($plan['price'] ?? 0);
        $value = $name . ' - ' . $priceText;

        if(!pnvPlanIsUnlimited($plan)){
            $daysLabel = pnvPlanDaysLabel($plan);

            if($daysLabel !== '' && $daysLabel !== '—' && $daysLabel !== 'نامحدود زمانی'){
                $value .= ' - ' . $daysLabel;
            }
        }

        return $value;
    }

    function pnvFindPlanByValue($planValue, $plans){
        $planValue = trim((string)$planValue);

        if($planValue === '' || !is_array($plans)){
            return null;
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            if(pnvPlanOptionValue($plan) === $planValue){
                return $plan;
            }
        }

        // سازگاری با فاکتورهای قدیمی بدون برچسب روز
        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            $legacy = trim((string)($plan['name'] ?? ''));

            if($legacy === ''){
                continue;
            }

            $priceText = pnvFormatPlanPrice($plan['price'] ?? 0);
            $legacyValue = $legacy . ' - ' . $priceText;

            if($legacyValue === $planValue || strpos($planValue, $legacyValue) === 0){
                return $plan;
            }
        }

        return null;
    }

    function pnvPlanDaysFromValue($planValue, $plans, $preferLimited = false){
        if(function_exists('xuiParsePlanDays')){
            $days = xuiParsePlanDays($planValue);

            if($days > 0){
                return $days;
            }
        }

        if(!is_array($plans)){
            $plans = function_exists('xuiLoadPlansCatalog') ? xuiLoadPlansCatalog() : [];
        }

        $matches = [];

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            if(pnvFindPlanByValue($planValue, [$plan])){
                $matches[] = $plan;
            }
        }

        $pickDays = static function($list){
            foreach($list as $plan){
                if(pnvPlanIsUnlimited($plan)){
                    continue;
                }

                $days = trim((string)($plan['days'] ?? ''));

                if(preg_match('/^\d+$/', $days) && intval($days) > 0){
                    return intval($days);
                }
            }

            return 0;
        };

        if($preferLimited){
            $days = $pickDays($matches);

            if($days > 0){
                return $days;
            }
        }
        else{
            $days = $pickDays($matches);

            if($days > 0){
                return $days;
            }
        }

        if(function_exists('xuiParsePlanDaysFromCatalog')){
            return xuiParsePlanDaysFromCatalog($planValue, $preferLimited);
        }

        return 0;
    }

    function pnvPlansForStepUi($plans){
        $out = [];

        if(!is_array($plans)){
            return $out;
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            $name = trim((string)($plan['name'] ?? ''));

            if($name === ''){
                continue;
            }

            $unlimited = pnvPlanIsUnlimited($plan);
            $value = pnvPlanOptionValue($plan);

            $out[] = [
                'name' => $name,
                'price' => intval($plan['price'] ?? 0),
                'price_text' => pnvFormatPlanPrice($plan['price'] ?? 0),
                'price_short' => pnvFormatPlanPriceShort($plan['price'] ?? 0),
                'days' => trim((string)($plan['days'] ?? '')),
                'days_label' => pnvPlanDaysLabel($plan),
                'category' => $unlimited ? 'unlimited' : 'limited',
                'value' => $value
            ];
        }

        return $out;
    }

    function pnvFindSubLinkFromCsv($username, $subLink){
        $username = trim((string)$username);
        $subLink = trim((string)$subLink);
        $file = __DIR__ . '/invoices/payments.csv';

        if($username === '' || $subLink === '' || !file_exists($file)){
            return $subLink;
        }

        if(preg_match('#^https?://#i', $subLink)){
            return $subLink;
        }

        $needle = strtolower($subLink);
        $found = '';

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            if(($row[0] ?? '') !== $username){
                continue;
            }

            if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            $type = trim((string)($row[9] ?? ''));
            $buyLink = trim((string)($row[7] ?? ''));
            $renewLink = trim((string)($row[1] ?? ''));

            if($type === 'خرید' && $buyLink !== ''){
                $hay = strtolower($buyLink);

                if($hay === $needle || strpos($hay, $needle) !== false || strpos($needle, $hay) !== false){
                    $found = $buyLink;
                    break;
                }
            }

            if($type === 'تمدید' && $renewLink !== ''){
                $hay = strtolower($renewLink);

                if($hay === $needle || strpos($hay, $needle) !== false || strpos($needle, $hay) !== false){
                    $found = $renewLink;
                    break;
                }
            }
        }

        fclose($handle);

        return $found !== '' ? $found : $subLink;
    }

    function pnvResolveSubTimeCategory($link, $planText = ''){
        $planDays = function_exists('xuiParsePlanDays') ? xuiParsePlanDays($planText) : 0;

        if($planDays > 0){
            return 'limited';
        }

        $link = trim((string)$link);

        if($link !== '' && preg_match('#^https?://#i', $link) && function_exists('xuiFetchSubUserinfoExpire')){
            $expire = xuiFetchSubUserinfoExpire($link);

            if($expire !== null){
                return $expire > 0 ? 'limited' : 'unlimited';
            }
        }

        return 'unlimited';
    }

    function pnvFindSubPlanTextFromCsv($username, $subLink){
        $username = trim((string)$username);
        $subLink = trim((string)$subLink);
        $file = __DIR__ . '/invoices/payments.csv';

        if($username === '' || $subLink === '' || !file_exists($file)){
            return '';
        }

        $target = strtolower(rtrim($subLink, '/'));
        $planText = '';

        $handle = fopen($file, 'r');

        while(($row = fgetcsv($handle)) !== false){
            if(($row[0] ?? '') !== $username){
                continue;
            }

            if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            $type = trim((string)($row[9] ?? ''));
            $buyLink = strtolower(rtrim(trim((string)($row[7] ?? '')), '/'));
            $renewLink = strtolower(rtrim(trim((string)($row[1] ?? '')), '/'));

            if($type === 'خرید' && $buyLink !== '' && ($buyLink === $target || strpos($target, $buyLink) !== false || strpos($buyLink, $target) !== false)){
                $planText = trim((string)($row[2] ?? ''));
                break;
            }

            if($type === 'تمدید' && $renewLink !== '' && ($renewLink === $target || strpos($target, $renewLink) !== false || strpos($renewLink, $target) !== false)){
                if($planText === ''){
                    $planText = trim((string)($row[2] ?? ''));
                }
            }
        }

        fclose($handle);

        return $planText;
    }

    function pnvValidateRenewPlanCategory($username, $subLink, $planValue, $plans){
        $selectedPlan = null;

        if(!is_array($plans)){
            $plans = [];
        }

        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            if(pnvPlanOptionValue($plan) === $planValue){
                $selectedPlan = $plan;
                break;
            }
        }

        if(!$selectedPlan){
            $selectedPlan = pnvFindPlanByValue($planValue, $plans);
        }

        if(!$selectedPlan){
            return ['ok' => false, 'error' => 'پلن انتخاب‌شده معتبر نیست'];
        }

        $selectedCategory = pnvPlanIsUnlimited($selectedPlan) ? 'unlimited' : 'limited';
        $fullLink = pnvFindSubLinkFromCsv($username, $subLink);
        $planText = pnvFindSubPlanTextFromCsv($username, $subLink);
        $subCategory = pnvResolveSubTimeCategory($fullLink, $planText);

        if($subCategory === $selectedCategory){
            return ['ok' => true];
        }

        if($subCategory === 'limited'){
            return [
                'ok' => false,
                'error' => 'این اشتراک زمان‌دار است و نمی‌توان آن را با پلن نامحدود زمانی تمدید کرد.'
            ];
        }

        return [
            'ok' => false,
            'error' => 'این اشتراک نامحدود زمانی است و نمی‌توان آن را با پلن زمان‌دار تمدید کرد.'
        ];
    }
}

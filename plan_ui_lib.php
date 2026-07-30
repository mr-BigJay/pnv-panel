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

        return $name . ' - ' . $priceText;
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
}

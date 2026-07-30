<?php

require_once __DIR__ . '/bale_lib.php';
require_once __DIR__ . '/xui_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';

if(!function_exists('instantPayPath')){

    function instantPayPath(){
        return __DIR__ . '/db/instant_payments.json';
    }

    function instantPayLoad(){
        $path = instantPayPath();

        if(!file_exists($path)){
            return [];
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    function instantPaySave($items){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        file_put_contents(
            instantPayPath(),
            json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function instantPayWindowSeconds($config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        $n = intval($config['pay_window_seconds'] ?? 600);
        return $n > 60 ? $n : 600;
    }

    function instantPayNewId(){
        try{
            return bin2hex(random_bytes(8));
        }catch(Throwable $e){
            return uniqid('pay_', true);
        }
    }

    function instantPayBaseToman($planPriceThousands){
        return intval($planPriceThousands) * 1000;
    }

    function instantPayActiveCodes($items = null){
        if($items === null){
            $items = instantPayLoad();
        }

        $now = time();
        $codes = [];

        foreach($items as $item){
            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(intval($item['expires_at'] ?? 0) < $now){
                continue;
            }

            $codes[] = str_pad((string)intval($item['code'] ?? 0), 4, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    function instantPayAllocateCode($items = null){
        $used = instantPayActiveCodes($items);
        $usedMap = array_fill_keys($used, true);

        for($i = 0; $i < 4000; $i++){
            $code = (string)random_int(1000, 9999);

            if(!isset($usedMap[$code])){
                return $code;
            }
        }

        return null;
    }

    function instantPayBuildAmount($baseToman, $code){
        $baseToman = intval($baseToman);
        $code = intval($code);

        if($baseToman < 10000){
            // برای مبالغ خیلی کوچک: خود کد + پایه
            return $baseToman + $code;
        }

        // ۴ رقم آخر مبلغ = کد یکتا
        return (intdiv($baseToman, 10000) * 10000) + $code;
    }

    function instantPayFindPlan($planValue, $plans){
        foreach($plans as $plan){
            if(!is_array($plan)){
                continue;
            }

            if(pnvPlanOptionValue($plan) === $planValue){
                return $plan;
            }
        }

        return null;
    }

    function instantPayExpireDue($items = null){
        if($items === null){
            $items = instantPayLoad();
        }

        $now = time();
        $changed = false;

        foreach($items as $i => $item){
            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(intval($item['expires_at'] ?? 0) >= $now){
                continue;
            }

            $items[$i]['status'] = 'expired';
            $changed = true;

            $csvIndex = intval($item['csv_index'] ?? -1);

            if($csvIndex >= 0 && function_exists('xuiRejectPaymentIndex')){
                xuiRejectPaymentIndex($csvIndex, 'منقضی شد');
            }
        }

        if($changed){
            instantPaySave($items);
        }

        return $items;
    }

    function instantPayPublicView($item){
        if(!is_array($item)){
            return null;
        }

        $expires = intval($item['expires_at'] ?? 0);
        $now = time();
        $remaining = max(0, $expires - $now);

        return [
            'id' => $item['id'] ?? '',
            'status' => $item['status'] ?? '',
            'amount' => intval($item['amount'] ?? 0),
            'amount_text' => number_format(intval($item['amount'] ?? 0)) . ' تومان',
            'code' => str_pad((string)intval($item['code'] ?? 0), 4, '0', STR_PAD_LEFT),
            'card' => $item['card'] ?? '',
            'card_name' => $item['card_name'] ?? '',
            'plan' => $item['plan'] ?? '',
            'type' => $item['type'] ?? 'خرید',
            'expires_at' => $expires,
            'remaining' => $remaining,
            'link' => $item['link'] ?? '',
            'message' => $item['message'] ?? ''
        ];
    }

    function instantPayGet($id){
        $items = instantPayExpireDue();
        $id = trim((string)$id);

        foreach($items as $item){
            if(($item['id'] ?? '') === $id){
                return $item;
            }
        }

        return null;
    }

    function instantPayCreate($opts){
        $username = trim((string)($opts['user'] ?? ''));
        $type = trim((string)($opts['type'] ?? 'خرید'));
        $planValue = trim((string)($opts['plan'] ?? ''));
        $subname = trim((string)($opts['subname'] ?? ''));
        $sub = trim((string)($opts['sub'] ?? ''));
        $card = trim((string)($opts['card'] ?? ''));
        $cardName = trim((string)($opts['card_name'] ?? ''));
        $plans = $opts['plans'] ?? [];
        $couponCode = trim((string)($opts['coupon_code'] ?? ''));
        $discountPercent = intval($opts['discount_percent'] ?? 0);

        if($username === '' || $planValue === '' || $card === ''){
            return ['ok' => false, 'error' => 'اطلاعات سفارش ناقص است'];
        }

        if($type === 'خرید'){
            if(strlen($subname) < 5 || strlen($subname) > 20){
                return ['ok' => false, 'error' => 'نام کانفیگ باید بین ۵ تا ۲۰ کاراکتر باشد'];
            }

            if(!preg_match('/^[a-zA-Z0-9._-]+$/', $subname)){
                return ['ok' => false, 'error' => 'نام کانفیگ فقط حروف لاتین، عدد و . _ -'];
            }
        }
        else{
            if($sub === ''){
                return ['ok' => false, 'error' => 'لینک اشتراک الزامی است'];
            }

            $validDomains = [
                'vip.boozhaan.ir',
                'vip2.boozhaan.ir',
                'vip3.boozhaan.ir',
                'vip4.boozhaan.ir'
            ];
            $okDomain = false;

            foreach($validDomains as $domain){
                if(stripos($sub, $domain) !== false){
                    $okDomain = true;
                    break;
                }
            }

            if(!$okDomain && !preg_match('/^[A-Za-z0-9]{8,32}$/', $sub)){
                return ['ok' => false, 'error' => 'لینک اشتراک صحیح نیست'];
            }
        }

        $plan = instantPayFindPlan($planValue, $plans);

        if(!$plan){
            return ['ok' => false, 'error' => 'پلن انتخاب‌شده معتبر نیست'];
        }

        $items = instantPayExpireDue();

        // جلوگیری از چند سفارش باز همزمان برای یک کاربر
        foreach($items as $item){
            if(($item['user'] ?? '') !== $username){
                continue;
            }

            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            return [
                'ok' => true,
                'reused' => true,
                'item' => instantPayPublicView($item)
            ];
        }

        $code = instantPayAllocateCode($items);

        if($code === null){
            return ['ok' => false, 'error' => 'کد یکتا در دسترس نیست؛ کمی بعد دوباره تلاش کنید'];
        }

        $priceThousands = intval($plan['price'] ?? 0);

        if($discountPercent > 0 && $discountPercent <= 100 && function_exists('couponApplyDiscountThousands')){
            $priceThousands = couponApplyDiscountThousands($priceThousands, $discountPercent);
        }

        $baseToman = instantPayBaseToman($priceThousands);
        $amount = instantPayBuildAmount($baseToman, $code);
        $now = time();
        $expires = $now + instantPayWindowSeconds();

        $target = ($type === 'تمدید') ? $sub : $subname;
        $planLabel = $planValue;

        if($discountPercent > 0 && function_exists('couponBuildPlanLabel')){
            $planLabel = couponBuildPlanLabel($plan, $discountPercent);
        }

        // ردیف CSV برای تأیید خودکار بعدی
        $row = [
            $username,
            $target,
            $planLabel,
            'AUTO-' . $code,
            '',
            '',
            'درحال بررسی',
            '',
            $now,
            $type,
            $couponCode !== '' ? strtoupper($couponCode) : '',
            $discountPercent,
            $amount,
            $code
        ];

        $payments = xuiLoadPayments();
        $payments[] = $row;
        $csvIndex = count($payments) - 1;
        xuiSavePayments($payments);

        $item = [
            'id' => instantPayNewId(),
            'user' => $username,
            'type' => $type,
            'subname' => $subname,
            'sub' => $sub,
            'plan' => $planLabel,
            'plan_value' => $planValue,
            'card' => $card,
            'card_name' => $cardName,
            'amount' => $amount,
            'code' => $code,
            'status' => 'waiting',
            'created_at' => $now,
            'expires_at' => $expires,
            'csv_index' => $csvIndex,
            'coupon_code' => $couponCode,
            'discount_percent' => $discountPercent,
            'link' => '',
            'message' => ''
        ];

        $items[] = $item;
        instantPaySave($items);

        if(function_exists('telegramNotifyNewPayment')){
            try{
                telegramNotifyNewPayment($type, $row);
            }catch(Throwable $e){
                error_log('instant pay telegram notify failed: ' . $e->getMessage());
            }
        }

        return [
            'ok' => true,
            'item' => instantPayPublicView($item)
        ];
    }

    function instantPayMarkPaid($id, $meta = []){
        $items = instantPayExpireDue();
        $id = trim((string)$id);
        $found = null;
        $idx = -1;

        foreach($items as $i => $item){
            if(($item['id'] ?? '') === $id){
                $found = $item;
                $idx = $i;
                break;
            }
        }

        if(!$found){
            return ['ok' => false, 'error' => 'سفارش پیدا نشد'];
        }

        if(($found['status'] ?? '') === 'paid'){
            return ['ok' => true, 'already' => true, 'item' => instantPayPublicView($found)];
        }

        if(($found['status'] ?? '') !== 'waiting'){
            return ['ok' => false, 'error' => 'سفارش قابل تأیید نیست (' . ($found['status'] ?? '') . ')'];
        }

        $csvIndex = intval($found['csv_index'] ?? -1);

        if($csvIndex < 0){
            return ['ok' => false, 'error' => 'ایندکس پرداخت نامعتبر است'];
        }

        // تاریخ/ساعت را برای لاگ پر می‌کنیم
        $payments = xuiLoadPayments();

        if(isset($payments[$csvIndex])){
            $payments[$csvIndex][4] = $meta['date'] ?? date('Y/m/d');
            $payments[$csvIndex][5] = $meta['time'] ?? date('H:i');
            xuiSavePayments($payments);
        }

        $result = xuiApprovePaymentIndex($csvIndex, $found['type'] ?? 'خرید');

        if(empty($result['ok'])){
            $items[$idx]['status'] = 'failed';
            $items[$idx]['message'] = $result['error'] ?? 'تأیید ناموفق';
            instantPaySave($items);
            return $result;
        }

        $items[$idx]['status'] = 'paid';
        $items[$idx]['paid_at'] = time();
        $items[$idx]['link'] = $result['link'] ?? '';
        $items[$idx]['message'] = 'پرداخت تأیید شد';
        $items[$idx]['matched_amount'] = intval($meta['amount'] ?? 0);
        $items[$idx]['matched_text'] = substr((string)($meta['text'] ?? ''), 0, 500);
        instantPaySave($items);

        if(!empty($found['coupon_code']) && function_exists('couponMarkUsed')){
            couponMarkUsed($found['coupon_code'], $found['user']);
        }

        return [
            'ok' => true,
            'item' => instantPayPublicView($items[$idx]),
            'provision' => $result
        ];
    }

    function instantPayMatchAmount($amountToman){
        $amountToman = intval($amountToman);
        $items = instantPayExpireDue();
        $code = str_pad((string)($amountToman % 10000), 4, '0', STR_PAD_LEFT);
        $now = time();
        $candidates = [];

        foreach($items as $item){
            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(intval($item['expires_at'] ?? 0) < $now){
                continue;
            }

            if(intval($item['amount'] ?? 0) === $amountToman){
                return $item;
            }

            if(str_pad((string)intval($item['code'] ?? 0), 4, '0', STR_PAD_LEFT) === $code){
                $candidates[] = $item;
            }
        }

        if(count($candidates) === 1){
            return $candidates[0];
        }

        return null;
    }

    function instantPayHandleDepositText($text, $meta = []){
        $text = trim((string)$text);

        if($text === ''){
            return ['ok' => false, 'error' => 'متن خالی'];
        }

        if(!baleLooksLikeDeposit($text)){
            return ['ok' => false, 'error' => 'شبیه پیام واریز نیست', 'ignored' => true];
        }

        $amounts = baleExtractTomanAmounts($text);

        if(count($amounts) === 0){
            return ['ok' => false, 'error' => 'مبلغی در پیام پیدا نشد'];
        }

        // بزرگ‌ترین مبلغ معمولاً مبلغ واریز است
        rsort($amounts, SORT_NUMERIC);

        foreach($amounts as $amount){
            $item = instantPayMatchAmount($amount);

            if(!$item){
                continue;
            }

            $result = instantPayMarkPaid($item['id'], [
                'amount' => $amount,
                'text' => $text,
                'date' => $meta['date'] ?? '',
                'time' => $meta['time'] ?? ''
            ]);

            $result['matched_amount'] = $amount;
            return $result;
        }

        return [
            'ok' => false,
            'error' => 'سفارش بازی با این مبلغ پیدا نشد',
            'amounts' => $amounts
        ];
    }
}

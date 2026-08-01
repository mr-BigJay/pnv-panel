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

    function instantPayBaseRial($planPriceThousands){
        // قیمت پلن در UI تومان است؛ مبلغ واریز بانکی به ریال است (×۱۰)
        return instantPayBaseToman($planPriceThousands) * 10;
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

        // فقط کدهای مضرب ۱۰ (۱۰۰۰، ۱۰۱۰، …، ۹۹۹۰)
        // تا مبلغ ریالی همیشه تومان صحیح باشد و اپ بانک رند نکند.
        for($i = 0; $i < 4000; $i++){
            $code = (string)(random_int(100, 999) * 10);

            if(!isset($usedMap[$code])){
                return $code;
            }
        }

        return null;
    }

    /**
     * مبلغ واریز به ریال، همیشه کمتر از مبلغ اعلامی پلن و مضرب ۱۰ (تومان صحیح).
     * بازه: [baseRial-9000 .. baseRial-10] و ۴ رقم آخر = کد
     * مثال: پلن ۱۵۰ هزار تومان = ۱٬۵۰۰٬۰۰۰ ریال → کد ۹۲۸۰ ⇒ ۱٬۴۹۹٬۲۸۰ ریال (= ۱۴۹٬۹۲۸ تومان)
     */
    function instantPayBuildAmountRial($baseRial, $code){
        $baseRial = intval($baseRial);
        $code = intval($code);

        // تضمین تومان صحیح
        if($code % 10 !== 0){
            $code = $code - ($code % 10);
        }

        if($code < 1000){
            $code = 1000;
        }

        if($baseRial < 10000){
            $amount = max(1000, $baseRial - max(10, $code % 1000));
            $amount = $amount - ($amount % 10);
            return ($amount > 0 && $amount < $baseRial) ? $amount : max(10, $baseRial - 10);
        }

        $amount = ($baseRial - 10000) + $code;

        if($amount >= $baseRial){
            $amount = $baseRial - 10;
        }

        if($amount <= 0){
            $amount = max(10, $baseRial - 10);
        }

        // همیشه مضرب ۱۰
        $amount = $amount - ($amount % 10);

        return $amount;
    }

    // سازگاری با نام قدیمی
    function instantPayBuildAmount($baseToman, $code){
        return instantPayBuildAmountRial(intval($baseToman) * 10, $code);
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
            $st = $item['status'] ?? '';
            if($st !== 'waiting'){
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

    /**
     * لغو سفارش‌های waiting کاربر (انصراف / بازگشت).
     */
    function instantPayCancelUserWaiting($username, $id = null, $items = null){
        $username = trim((string)$username);
        $id = $id !== null ? trim((string)$id) : null;

        if($items === null){
            $items = instantPayExpireDue();
        }

        $changed = false;

        foreach($items as $i => $item){
            if(($item['user'] ?? '') !== $username){
                continue;
            }

            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if($id !== null && $id !== '' && ($item['id'] ?? '') !== $id){
                continue;
            }

            $items[$i]['status'] = 'cancelled';
            $items[$i]['message'] = 'لغو توسط کاربر';
            $items[$i]['cancelled_at'] = time();
            $changed = true;

            $csvIndex = intval($item['csv_index'] ?? -1);

            if($csvIndex >= 0 && function_exists('xuiRejectPaymentIndex')){
                xuiRejectPaymentIndex($csvIndex, 'لغو شد');
            }
        }

        if($changed){
            instantPaySave($items);
            $items = instantPayLoad();
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
        $amount = intval($item['amount'] ?? 0); // ریال
        $baseRial = intval($item['base_amount'] ?? 0); // ریال
        $planToman = intval($item['plan_toman'] ?? 0);

        // سازگاری با سفارش‌های قدیمی تومان‌محور
        $currency = strtolower(trim((string)($item['currency'] ?? 'rial')));
        if($currency !== 'rial' && $amount > 0 && $amount < 1000000){
            $amount = $amount * 10;
            if($baseRial > 0 && $baseRial < 1000000){
                $baseRial = $baseRial * 10;
            }
        }

        if($baseRial <= 0){
            $baseRial = $amount > 0 ? (intdiv($amount, 10000) * 10000 + 10000) : 0;
        }

        if($planToman <= 0 && $baseRial > 0){
            $planToman = intdiv($baseRial, 10);
        }

        $saved = max(0, $baseRial - $amount);
        $ready = (($item['status'] ?? '') === 'paid');

        return [
            'id' => $item['id'] ?? '',
            'status' => $item['status'] ?? '',
            'ready' => $ready,
            'currency' => 'rial',
            'amount' => $amount,
            'amount_toman' => intdiv($amount, 10),
            'amount_text' => number_format($amount) . ' ریال',
            'amount_toman_text' => number_format(intdiv($amount, 10)) . ' تومان',
            'base_amount' => $baseRial,
            'base_text' => $baseRial > 0 ? (number_format($baseRial) . ' ریال') : '',
            'plan_toman' => $planToman,
            'plan_toman_text' => $planToman > 0 ? (number_format($planToman) . ' تومان') : '',
            'saved' => $saved,
            'saved_text' => $saved > 0 ? (number_format($saved) . ' ریال') : '',
            'code' => str_pad((string)intval($item['code'] ?? 0), 4, '0', STR_PAD_LEFT),
            'card' => $item['card'] ?? '',
            'card_name' => $item['card_name'] ?? '',
            'plan' => $item['plan'] ?? '',
            'subname' => $item['subname'] ?? '',
            'sub' => $item['sub'] ?? '',
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

        // سفارش waiting قبلی را ببند تا مبلغ/تایمر تازه ساخته شود
        $items = instantPayCancelUserWaiting($username, null, $items);

        $code = instantPayAllocateCode($items);

        if($code === null){
            return ['ok' => false, 'error' => 'کد یکتا در دسترس نیست؛ کمی بعد دوباره تلاش کنید'];
        }

        $priceThousands = intval($plan['price'] ?? 0);

        if($discountPercent > 0 && $discountPercent <= 100 && function_exists('couponApplyDiscountThousands')){
            $priceThousands = couponApplyDiscountThousands($priceThousands, $discountPercent);
        }

        $baseToman = instantPayBaseToman($priceThousands);
        $baseRial = instantPayBaseRial($priceThousands);
        $amount = instantPayBuildAmountRial($baseRial, $code);
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
            'base_amount' => $baseRial,
            'plan_toman' => $baseToman,
            'currency' => 'rial',
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

        // اطلاع تلگرام فقط بعد از تأیید پرداخت ارسال می‌شود (نه در شروع مهلت)

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

        // اول وضعیت را روی processing بگذار تا UI هنوز «تأیید شد» نگوید
        $items[$idx]['status'] = 'processing';
        $items[$idx]['message'] = 'در حال صدور اشتراک…';
        instantPaySave($items);

        $result = xuiApprovePaymentIndex($csvIndex, $found['type'] ?? 'خرید');

        // دوباره بخوان (ممکن است همزمان تغییر کرده باشد)
        $items = instantPayLoad();
        $idx = -1;
        foreach($items as $i => $item){
            if(($item['id'] ?? '') === $id){
                $idx = $i;
                break;
            }
        }
        if($idx < 0){
            return ['ok' => false, 'error' => 'سفارش بعد از پردازش پیدا نشد'];
        }

        if(empty($result['ok'])){
            $items[$idx]['status'] = 'failed';
            $items[$idx]['message'] = $result['error'] ?? 'تأیید ناموفق';
            instantPaySave($items);
            return $result;
        }

        // فقط وقتی کانفیگ آماده است «paid» می‌شود
        $items[$idx]['status'] = 'paid';
        $items[$idx]['paid_at'] = time();
        $items[$idx]['link'] = $result['link'] ?? ($found['sub'] ?? '');
        $items[$idx]['message'] = 'پرداخت تأیید شد';
        $items[$idx]['matched_amount'] = intval($meta['amount'] ?? 0);
        $items[$idx]['matched_text'] = substr((string)($meta['text'] ?? ''), 0, 500);
        instantPaySave($items);

        if(!empty($found['coupon_code']) && function_exists('couponMarkUsed')){
            couponMarkUsed($found['coupon_code'], $found['user']);
        }

        // اطلاع‌رسانی تلگرام فقط بعد از تأیید نهایی
        if(function_exists('telegramNotifyNewPayment')){
            try{
                $payments = xuiLoadPayments();
                $notifyRow = $payments[$csvIndex] ?? null;

                if(!is_array($notifyRow)){
                    $notifyRow = [
                        $found['user'] ?? '',
                        ($found['type'] ?? '') === 'تمدید' ? ($found['sub'] ?? '') : ($found['subname'] ?? ''),
                        $found['plan'] ?? '',
                        'AUTO-' . ($found['code'] ?? ''),
                        $meta['date'] ?? date('Y/m/d'),
                        $meta['time'] ?? date('H:i'),
                        'تایید شد',
                        $items[$idx]['link'] ?? '',
                        intval($found['created_at'] ?? time()),
                        $found['type'] ?? 'خرید',
                        $found['coupon_code'] ?? '',
                        intval($found['discount_percent'] ?? 0),
                        intval($found['amount'] ?? 0),
                        $found['code'] ?? ''
                    ];
                }

                telegramNotifyNewPayment($found['type'] ?? 'خرید', $notifyRow, ['confirmed' => true]);
            }catch(Throwable $e){
                error_log('instant pay telegram confirm notify failed: ' . $e->getMessage());
            }
        }

        return [
            'ok' => true,
            'item' => instantPayPublicView($items[$idx]),
            'provision' => $result
        ];
    }

    function instantPayNormalizeItemAmountRial($item){
        $itemAmount = intval($item['amount'] ?? 0);
        $currency = strtolower(trim((string)($item['currency'] ?? 'rial')));

        // سفارش‌های قدیمی تومان‌محور
        if($currency !== 'rial' && $itemAmount > 0 && $itemAmount < 1000000){
            $itemAmount *= 10;
        }

        return $itemAmount;
    }

    function instantPayMatchAmount($amountRial){
        $amountRial = intval($amountRial);

        if($amountRial <= 0){
            return null;
        }

        $items = instantPayExpireDue();
        $code = str_pad((string)($amountRial % 10000), 4, '0', STR_PAD_LEFT);
        $now = time();
        $candidates = [];

        foreach($items as $item){
            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(intval($item['expires_at'] ?? 0) < $now){
                continue;
            }

            $itemAmount = instantPayNormalizeItemAmountRial($item);

            if($itemAmount === $amountRial){
                return $item;
            }

            // اگر پیام مبلغ را به تومان آورده (بدون تبدیل)
            if($itemAmount > 0 && intdiv($itemAmount, 10) === $amountRial){
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

    /**
     * مبالغ استخراج‌شده را به کاندیدهای ریالی گسترش می‌دهد
     * (عدد بدون واحد ممکن است تومان باشد).
     * برای پیام پست‌بانک گسترش ×۱۰ انجام نمی‌شود.
     */
    function instantPayExpandAmountCandidates($amounts, $opts = []){
        $allowTomanGuess = empty($opts['rial_only']);
        $out = [];

        foreach($amounts as $amount){
            $amount = intval($amount);

            if($amount <= 0){
                continue;
            }

            $out[$amount] = true;

            if($allowTomanGuess && $amount <= 50000000){
                $asRial = $amount * 10;

                if($asRial > $amount){
                    $out[$asRial] = true;
                }
            }
        }

        $list = array_map('intval', array_keys($out));
        rsort($list, SORT_NUMERIC);
        return $list;
    }

    function instantPayHandleDepositText($text, $meta = []){
        $text = trim((string)$text);

        if($text === ''){
            return ['ok' => false, 'error' => 'متن خالی'];
        }

        if(!baleLooksLikeDeposit($text)){
            return ['ok' => false, 'error' => 'شبیه پیام واریز نیست', 'ignored' => true];
        }

        $amounts = baleExtractRialAmounts($text);

        if(count($amounts) === 0){
            return ['ok' => false, 'error' => 'مبلغی در پیام پیدا نشد'];
        }

        // پیام پست‌بانک: مبالغ ریال‌اند؛ «مانده» را از قبل حذف کرده‌ایم
        $rialOnly = function_exists('baleLooksLikePostBankNotice') && baleLooksLikePostBankNotice($text);
        $candidates = instantPayExpandAmountCandidates($amounts, ['rial_only' => $rialOnly]);

        // اول exact match — کد ۴رقمی فقط اگر exact نبود
        foreach($candidates as $amount){
            $item = instantPayMatchAmountExact($amount);

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
            $result['parsed_amounts'] = $amounts;
            return $result;
        }

        foreach($candidates as $amount){
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
            $result['parsed_amounts'] = $amounts;
            return $result;
        }

        return [
            'ok' => false,
            'error' => 'سفارش بازی با این مبلغ پیدا نشد',
            'amounts' => $amounts,
            'candidates' => $candidates
        ];
    }

    function instantPayMatchAmountExact($amountRial){
        $amountRial = intval($amountRial);

        if($amountRial <= 0){
            return null;
        }

        $items = instantPayExpireDue();
        $now = time();

        foreach($items as $item){
            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(intval($item['expires_at'] ?? 0) < $now){
                continue;
            }

            $itemAmount = instantPayNormalizeItemAmountRial($item);

            if($itemAmount === $amountRial){
                return $item;
            }

            if($itemAmount > 0 && intdiv($itemAmount, 10) === $amountRial){
                return $item;
            }
        }

        return null;
    }
}

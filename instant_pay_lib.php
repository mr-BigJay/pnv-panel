<?php

require_once __DIR__ . '/time_lib.php';
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

        // پیش‌فرض ۳۰ دقیقه (تایمر نمایش به کاربر)
        $n = intval($config['pay_window_seconds'] ?? 1800);
        return $n > 60 ? $n : 1800;
    }

    /**
     * مهلت اضافه بعد از پایان تایمر برای مچ واریز دیررس.
     * پیش‌فرض ۱۰ دقیقه → سقف اعتبار مبلغ = ۳۰ + ۱۰ = ۴۰ دقیقه.
     */
    function instantPayGraceSeconds($config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        $n = intval($config['pay_grace_seconds'] ?? 600);
        return $n >= 0 ? $n : 600;
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
        $grace = instantPayGraceSeconds();
        $codes = [];

        foreach($items as $item){
            if(!empty($item['match_closed'])){
                continue;
            }

            $status = (string)($item['status'] ?? '');
            $expires = intval($item['expires_at'] ?? 0);

            // کد تا پایان مهلت نمایش + ۱۰ دقیقهٔ grace رزرو بماند
            if($status === 'waiting'){
                if($expires < $now && ($now - $expires) > $grace){
                    continue;
                }
            }
            elseif($status === 'expired'){
                if($expires <= 0 || ($now - $expires) > $grace){
                    continue;
                }
            }
            else{
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

    function instantPayTrackingCode($itemOrCode){
        if(is_array($itemOrCode)){
            $code = $itemOrCode['code'] ?? '';
        } else {
            $code = $itemOrCode;
        }
        return 'AUTO-' . str_pad((string)intval($code), 4, '0', STR_PAD_LEFT);
    }

    /**
     * ایندکس‌های csv_index را بعد از حذف/جابه‌جایی از روی AUTO-code دوباره بساز.
     */
    function instantPayRebuildCsvIndexes($items = null){
        if($items === null){
            $items = instantPayLoad();
        }

        if(!function_exists('xuiLoadPayments')){
            return $items;
        }

        $payments = xuiLoadPayments();
        $map = [];

        foreach($payments as $i => $row){
            if(!is_array($row)){
                continue;
            }
            $tracking = trim((string)($row[3] ?? ''));
            if(strpos($tracking, 'AUTO-') !== 0){
                continue;
            }
            $user = strtolower(trim((string)($row[0] ?? '')));
            $map[$user . '|' . $tracking] = $i;
        }

        $changed = false;
        foreach($items as $i => $item){
            $tracking = instantPayTrackingCode($item);
            $key = strtolower(trim((string)($item['user'] ?? ''))) . '|' . $tracking;
            $newIndex = array_key_exists($key, $map) ? intval($map[$key]) : -1;
            if(intval($item['csv_index'] ?? -1) !== $newIndex){
                $items[$i]['csv_index'] = $newIndex;
                $changed = true;
            }
        }

        if($changed){
            instantPaySave($items);
        }

        return $items;
    }

    /**
     * ردیف(های) unpaid مرتبط با سفارش آنی را از لیست ادمین حذف کن.
     */
    function instantPayDeleteAbandonedCsv($item){
        if(!is_array($item) || !function_exists('xuiLoadPayments') || !function_exists('xuiDeletePaymentIndexes')){
            return 0;
        }

        $tracking = instantPayTrackingCode($item);
        $user = trim((string)($item['user'] ?? ''));
        $payments = xuiLoadPayments();
        $toDelete = [];

        foreach($payments as $i => $row){
            if(!is_array($row)){
                continue;
            }
            if(trim((string)($row[3] ?? '')) !== $tracking){
                continue;
            }
            if($user !== '' && strcasecmp(trim((string)($row[0] ?? '')), $user) !== 0){
                continue;
            }

            $st = trim((string)($row[6] ?? ''));
            // فقط ردیف‌های پرداخت‌نشده / رد‌شدهٔ موقت
            if(in_array($st, ['', 'درحال بررسی', 'در حال بررسی', 'رد شد'], true)){
                $toDelete[] = $i;
            }
        }

        if(!$toDelete){
            return 0;
        }

        $result = xuiDeletePaymentIndexes($toDelete);
        instantPayRebuildCsvIndexes();
        return intval($result['deleted'] ?? 0);
    }

    /**
     * سفارش‌های paid را با CSV همگام کن (رفع گیر کردن روی «درحال بررسی»).
     */
    function instantPaySyncPaidCsvRows($items = null){
        if($items === null){
            $items = instantPayLoad();
        }
        if(!function_exists('xuiLoadPayments') || !function_exists('xuiSavePayments')){
            return $items;
        }

        $payments = xuiLoadPayments();
        $changed = false;

        foreach($items as $item){
            if(($item['status'] ?? '') !== 'paid'){
                continue;
            }

            $tracking = instantPayTrackingCode($item);
            $user = trim((string)($item['user'] ?? ''));
            $link = trim((string)($item['link'] ?? ''));

            foreach($payments as $pi => $row){
                if(!is_array($row)){
                    continue;
                }
                if(trim((string)($row[3] ?? '')) !== $tracking){
                    continue;
                }
                if($user !== '' && strcasecmp(trim((string)($row[0] ?? '')), $user) !== 0){
                    continue;
                }

                if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
                    $payments[$pi][6] = 'تایید شد';
                    $changed = true;
                }
                if($link !== '' && trim((string)($row[7] ?? '')) === ''){
                    $payments[$pi][7] = $link;
                    $changed = true;
                }
            }
        }

        if($changed){
            xuiSavePayments($payments);
            $items = instantPayRebuildCsvIndexes($items);
        }

        return $items;
    }

    /**
     * بعد از ۳۰+۱۰ دقیقه بدون پرداخت، ردیف ادمین را پاک کن.
     */
    function instantPayPurgeStaleAdminRows($items = null){
        if($items === null){
            $items = instantPayLoad();
        }

        $now = time();
        $grace = instantPayGraceSeconds();
        $changed = false;

        foreach($items as $i => $item){
            $status = (string)($item['status'] ?? '');
            $expires = intval($item['expires_at'] ?? 0);
            $cancelledAt = intval($item['cancelled_at'] ?? 0);

            $shouldDelete = false;

            // بازگشت کاربر یا جایگزینی مبلغ — فوراً از لیست ادمین حذف
            if(in_array($status, ['cancelled', 'failed'], true)){
                $shouldDelete = true;
            }

            // بعد از ۳۰ دقیقه نمایش + ۱۰ دقیقه grace
            if($status === 'expired' && $expires > 0 && ($now - $expires) >= $grace){
                $shouldDelete = true;
            }

            // waiting که از مهلت + grace گذشته (اگر ExpireDue جا مانده)
            if($status === 'waiting' && $expires > 0 && ($now - $expires) >= $grace){
                $items[$i]['status'] = 'expired';
                $items[$i]['match_closed'] = true;
                $changed = true;
                $shouldDelete = true;
            }

            if($shouldDelete && empty($items[$i]['csv_purged'])){
                instantPayDeleteAbandonedCsv($items[$i]);
                $items[$i]['csv_purged'] = true;
                $items[$i]['match_closed'] = true;
                $changed = true;
            }
        }

        // یتیم‌های AUTO قدیمی در CSV (بدون سفارش فعال)
        if(function_exists('xuiLoadPayments') && function_exists('xuiDeletePaymentIndexes')){
            $payments = xuiLoadPayments();
            $activeKeys = [];
            foreach($items as $item){
                $st = (string)($item['status'] ?? '');
                if(in_array($st, ['waiting', 'processing', 'paid', 'expired'], true) && empty($item['csv_purged'])){
                    // expired داخل grace هنوز نگه دار
                    if($st === 'expired'){
                        $expires = intval($item['expires_at'] ?? 0);
                        if($expires > 0 && ($now - $expires) <= $grace){
                            $activeKeys[strtolower(trim((string)($item['user'] ?? ''))) . '|' . instantPayTrackingCode($item)] = true;
                        }
                    } else {
                        $activeKeys[strtolower(trim((string)($item['user'] ?? ''))) . '|' . instantPayTrackingCode($item)] = true;
                    }
                }
            }

            $orphanDelete = [];
            $window = instantPayWindowSeconds() + $grace;

            foreach($payments as $pi => $row){
                if(!is_array($row)){
                    continue;
                }
                $tracking = trim((string)($row[3] ?? ''));
                if(strpos($tracking, 'AUTO-') !== 0){
                    continue;
                }
                $st = trim((string)($row[6] ?? ''));
                if(!in_array($st, ['', 'درحال بررسی', 'در حال بررسی', 'رد شد'], true)){
                    continue;
                }
                $created = intval($row[8] ?? 0);
                if($created > 0 && ($now - $created) < $window){
                    // هنوز داخل سقف ۴۰ دقیقه — اگر سفارش فعال دارد نگه دار
                    $key = strtolower(trim((string)($row[0] ?? ''))) . '|' . $tracking;
                    if(isset($activeKeys[$key])){
                        continue;
                    }
                    // اگر سفارش فعالی نیست ولی هنوز داخل پنجره است، فعلاً نگه دار
                    // (ممکن است JSON هنوز نخوانده شده) — فقط بعد از پنجره پاک کن
                    continue;
                }
                if($created > 0 && ($now - $created) >= $window){
                    $key = strtolower(trim((string)($row[0] ?? ''))) . '|' . $tracking;
                    if(!isset($activeKeys[$key])){
                        $orphanDelete[] = $pi;
                    }
                }
            }

            if($orphanDelete){
                xuiDeletePaymentIndexes($orphanDelete);
                $items = instantPayRebuildCsvIndexes($items);
            }
        }

        if($changed){
            instantPaySave($items);
        }

        return $items;
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

            // فقط وضعیت را expired کن — CSV را رد نکن (تا پایان grace برای مچ بماند)
            $items[$i]['status'] = 'expired';
            $changed = true;
        }

        if($changed){
            instantPaySave($items);
        }

        // همگام‌سازی سفارش‌های paid که CSVشان هنوز «درحال بررسی» است
        $items = instantPaySyncPaidCsvRows($items);

        // پاک‌سازی ردیف‌های ادمین بعد از بازگشت یا ۳۰+۱۰
        $items = instantPayPurgeStaleAdminRows($items);

        return $items;
    }

    /**
     * لغو سفارش‌های waiting کاربر (انصراف / بازگشت).
     * مبلغ بلافاصله از مچ خارج می‌شود و ردیف از لیست ادمین حذف می‌شود.
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
            $items[$i]['match_closed'] = true;
            $changed = true;

            instantPayDeleteAbandonedCsv($items[$i]);
            $items[$i]['csv_purged'] = true;
            $items[$i]['csv_index'] = -1;
        }

        if($changed){
            instantPaySave($items);
            $items = instantPayRebuildCsvIndexes(instantPayLoad());
        }

        return $items;
    }

    /**
     * بستن کامل مبلغ‌های قابل‌مچ کاربر (قبل از ساخت مبلغ جدید).
     */
    function instantPayCloseUserMatchable($username, $exceptId = null, $items = null){
        $username = trim((string)$username);
        $exceptId = $exceptId !== null ? trim((string)$exceptId) : null;

        if($items === null){
            $items = instantPayExpireDue();
        }

        $changed = false;
        $now = time();

        foreach($items as $i => $item){
            if(($item['user'] ?? '') !== $username){
                continue;
            }

            $id = (string)($item['id'] ?? '');
            if($exceptId !== null && $exceptId !== '' && $id === $exceptId){
                continue;
            }

            $status = (string)($item['status'] ?? '');
            if(!in_array($status, ['waiting', 'expired', 'failed'], true)){
                continue;
            }

            if($status === 'waiting' || $status === 'expired' || $status === 'failed'){
                if($status === 'waiting'){
                    $items[$i]['status'] = 'cancelled';
                    $items[$i]['message'] = 'لغو به‌خاطر مبلغ جدید';
                    $items[$i]['cancelled_at'] = $now;
                }

                instantPayDeleteAbandonedCsv($items[$i]);
                $items[$i]['csv_purged'] = true;
                $items[$i]['csv_index'] = -1;
            }

            $items[$i]['match_closed'] = true;
            $changed = true;
        }

        if($changed){
            instantPaySave($items);
            $items = instantPayRebuildCsvIndexes(instantPayLoad());
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

    /**
     * نوع زمانی اشتراک از روی فاکتورهای تاییدشده:
     * unlimited | limited | unknown
     */
    function instantPayNormalizeSubKey($value){
        $value = trim((string)$value);

        if($value === ''){
            return '';
        }

        if(function_exists('xuiParseSubLink')){
            $parsed = xuiParseSubLink($value);

            if(is_array($parsed) && !empty($parsed['sub_id'])){
                return strtolower(($parsed['host'] ?? '') . '|' . $parsed['sub_id']);
            }
        }

        if(preg_match('/^[A-Za-z0-9]{8,32}$/', $value)){
            return strtolower('id|' . $value);
        }

        return strtolower($value);
    }

    function instantPaySubTimeCategory($username, $subLink){
        $username = trim((string)$username);
        $subKey = instantPayNormalizeSubKey($subLink);

        if($username === '' || $subKey === ''){
            return 'unknown';
        }

        $file = __DIR__ . '/invoices/payments.csv';

        if(!file_exists($file)){
            return 'unknown';
        }

        $buyDays = null;
        $anyDays = null;
        $handle = fopen($file, 'r');

        while(($data = fgetcsv($handle)) !== false){
            if(($data[0] ?? '') !== $username){
                continue;
            }

            if(trim((string)($data[6] ?? '')) !== 'تایید شد'){
                continue;
            }

            $type = trim((string)($data[9] ?? ''));
            $planText = trim((string)($data[2] ?? ''));
            $col1 = trim((string)($data[1] ?? ''));
            $link = trim((string)($data[7] ?? ''));
            $days = function_exists('xuiParsePlanDays') ? xuiParsePlanDays($planText) : 0;

            $candidates = [];

            if($type === 'خرید' && $link !== ''){
                $candidates[] = $link;
            }

            if($type === 'تمدید' && $col1 !== ''){
                $candidates[] = $col1;
            }

            $matched = false;

            foreach($candidates as $cand){
                if(instantPayNormalizeSubKey($cand) === $subKey){
                    $matched = true;
                    break;
                }
            }

            if(!$matched){
                continue;
            }

            if($type === 'خرید'){
                $buyDays = $days;
            }
            elseif($anyDays === null){
                $anyDays = $days;
            }
        }

        fclose($handle);

        $resolved = $buyDays !== null ? $buyDays : $anyDays;

        if($resolved === null){
            return 'unknown';
        }

        return intval($resolved) > 0 ? 'limited' : 'unlimited';
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

        // تمدید: نوع زمانی پلن باید با نوع اشتراک یکی باشد
        if($type === 'تمدید' || $type === 'renew'){
            $subCategory = instantPaySubTimeCategory($username, $sub);
            $planUnlimited = function_exists('pnvPlanIsUnlimited')
                ? pnvPlanIsUnlimited($plan)
                : (intval($plan['days'] ?? 0) <= 0 && !preg_match('/^\d+$/', trim((string)($plan['days'] ?? ''))));
            $planCategory = $planUnlimited ? 'unlimited' : 'limited';

            if($subCategory === 'unlimited' && $planCategory === 'limited'){
                return [
                    'ok' => false,
                    'error' => 'این اشتراک نامحدود زمانی است و با پلن زمان‌دار تمدید نمی‌شود. در صورت نیاز خرید اشتراک جدید را بزنید.'
                ];
            }

            if($subCategory === 'limited' && $planCategory === 'unlimited'){
                return [
                    'ok' => false,
                    'error' => 'این اشتراک زمان‌دار است و با پلن نامحدود زمانی تمدید نمی‌شود. در صورت نیاز خرید اشتراک جدید را بزنید.'
                ];
            }
        }

        $items = instantPayExpireDue();

        // مبلغ‌های قبلی (waiting / grace) را کامل ببند تا کد تکراری و مچ اشتباه نشود
        $items = instantPayCloseUserMatchable($username, null, $items);

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

    /**
     * ایندکس CSV را پیدا کن؛ اگر به‌خاطر حذف/جابه‌جایی لیست خراب شده، ردیف را از نو بساز.
     */
    function instantPayResolveOrCreateCsvIndex($item, $meta = []){
        $payments = xuiLoadPayments();
        $code = str_pad((string)intval($item['code'] ?? 0), 4, '0', STR_PAD_LEFT);
        $tracking = 'AUTO-' . $code;
        $user = trim((string)($item['user'] ?? ''));
        $type = trim((string)($item['type'] ?? 'خرید'));
        $created = intval($item['created_at'] ?? 0);
        $csvIndex = intval($item['csv_index'] ?? -1);

        $matchesRow = static function($row) use ($tracking, $user, $type, $created){
            if(!is_array($row)){
                return false;
            }

            $rowUser = trim((string)($row[0] ?? ''));
            $rowTrack = trim((string)($row[3] ?? ''));
            $rowType = trim((string)($row[9] ?? ''));
            $rowCreated = intval($row[8] ?? 0);

            if($rowTrack !== $tracking){
                return false;
            }

            if($user !== '' && strcasecmp($rowUser, $user) !== 0){
                return false;
            }

            if($type !== '' && $rowType !== '' && $rowType !== $type){
                return false;
            }

            // اگر created ذخیره شده، نزدیک بودن را ترجیح بده (اختیاری)
            if($created > 0 && $rowCreated > 0 && abs($rowCreated - $created) > 86400){
                return false;
            }

            return true;
        };

        // ۱) ایندکس ذخیره‌شده اگر هنوز همان ردیف AUTO باشد
        if($csvIndex >= 0 && isset($payments[$csvIndex]) && $matchesRow($payments[$csvIndex])){
            $payments[$csvIndex][4] = $meta['date'] ?? ($payments[$csvIndex][4] ?: pnvJalaliToday('/'));
            $payments[$csvIndex][5] = $meta['time'] ?? ($payments[$csvIndex][5] ?: pnvTehranTime(null, 'H:i'));
            if(trim((string)($payments[$csvIndex][6] ?? '')) === 'رد شد'){
                $payments[$csvIndex][6] = 'درحال بررسی';
                $payments[$csvIndex][7] = '';
            }
            xuiSavePayments($payments);
            return ['ok' => true, 'index' => $csvIndex, 'rebuilt' => false];
        }

        // ۲) جستجو با AUTO-code
        foreach($payments as $i => $row){
            if(!$matchesRow($row)){
                continue;
            }

            $payments[$i][4] = $meta['date'] ?? ($payments[$i][4] ?: pnvJalaliToday('/'));
            $payments[$i][5] = $meta['time'] ?? ($payments[$i][5] ?: pnvTehranTime(null, 'H:i'));
            if(trim((string)($payments[$i][6] ?? '')) === 'رد شد'){
                $payments[$i][6] = 'درحال بررسی';
                $payments[$i][7] = '';
            }
            xuiSavePayments($payments);
            return ['ok' => true, 'index' => $i, 'rebuilt' => false];
        }

        // ۳) ردیف نیست — از روی سفارش آنی دوباره بساز
        $target = ($type === 'تمدید')
            ? trim((string)($item['sub'] ?? ''))
            : trim((string)($item['subname'] ?? ''));

        $row = [
            $user,
            $target,
            $item['plan'] ?? '',
            $tracking,
            $meta['date'] ?? pnvJalaliToday('/'),
            $meta['time'] ?? pnvTehranTime(null, 'H:i'),
            'درحال بررسی',
            '',
            $created > 0 ? $created : time(),
            $type !== '' ? $type : 'خرید',
            !empty($item['coupon_code']) ? strtoupper((string)$item['coupon_code']) : '',
            intval($item['discount_percent'] ?? 0),
            intval($item['amount'] ?? 0),
            $code
        ];

        $payments[] = $row;
        $newIndex = count($payments) - 1;
        xuiSavePayments($payments);

        return ['ok' => true, 'index' => $newIndex, 'rebuilt' => true];
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

        $st = (string)($found['status'] ?? '');
        // failed هم قابل تلاش دوباره است (مثلاً csv_index خراب بوده)
        $allowed = ['waiting', 'expired', 'cancelled', 'failed', 'processing'];

        if(!in_array($st, $allowed, true)){
            return ['ok' => false, 'error' => 'سفارش قابل تأیید نیست (' . $st . ')'];
        }

        // اگر منقضی/لغو/ناموفق شده ولی مبلغ دقیقاً مچ شده، برای صدور دوباره waiting کن
        if($st !== 'waiting'){
            $items[$idx]['status'] = 'waiting';
            $items[$idx]['message'] = 'مچ مجدد پس از ' . $st;
            instantPaySave($items);
            $found = $items[$idx];
        }

        $resolved = instantPayResolveOrCreateCsvIndex($found, $meta);

        if(empty($resolved['ok'])){
            return ['ok' => false, 'error' => $resolved['error'] ?? 'ایندکس پرداخت نامعتبر است'];
        }

        $csvIndex = intval($resolved['index']);
        $items[$idx]['csv_index'] = $csvIndex;
        instantPaySave($items);
        $found = $items[$idx];

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
        $link = trim((string)($result['link'] ?? ($found['sub'] ?? '')));
        $items[$idx]['status'] = 'paid';
        $items[$idx]['paid_at'] = time();
        $items[$idx]['link'] = $link;
        $items[$idx]['message'] = 'پرداخت تأیید شد';
        $items[$idx]['matched_amount'] = intval($meta['amount'] ?? 0);
        $items[$idx]['matched_text'] = substr((string)($meta['text'] ?? ''), 0, 500);
        $items[$idx]['csv_purged'] = false;
        instantPaySave($items);

        // همه ردیف‌های هم‌کد AUTO در CSV را حتماً «تایید شد» کن
        // (رفع باگ: کانفیگ صادر شده ولی لیست ادمین روی نارنجی/درحال بررسی می‌ماند)
        if(function_exists('xuiLoadPayments') && function_exists('xuiSavePayments')){
            $tracking = instantPayTrackingCode($items[$idx]);
            $userName = trim((string)($items[$idx]['user'] ?? $found['user'] ?? ''));
            $payments = xuiLoadPayments();
            $changedCsv = false;
            $matchedIndexes = [];

            foreach($payments as $pi => $prow){
                if(!is_array($prow)){
                    continue;
                }

                $rowTrack = trim((string)($prow[3] ?? ''));
                $rowUser = trim((string)($prow[0] ?? ''));
                $rowCode = str_pad((string)intval($prow[13] ?? 0), 4, '0', STR_PAD_LEFT);
                $trackCode = str_pad((string)intval($items[$idx]['code'] ?? 0), 4, '0', STR_PAD_LEFT);

                $isSame =
                    ($rowTrack === $tracking)
                    || ($rowTrack === '' && $rowCode === $trackCode && $rowCode !== '0000');

                if(!$isSame){
                    continue;
                }

                if($userName !== '' && strcasecmp($rowUser, $userName) !== 0){
                    continue;
                }

                $matchedIndexes[] = $pi;
                if(trim((string)($payments[$pi][6] ?? '')) !== 'تایید شد'){
                    $payments[$pi][6] = 'تایید شد';
                    $changedCsv = true;
                }
                if($link !== ''){
                    $payments[$pi][7] = $link;
                    $changedCsv = true;
                }
                if(trim((string)($payments[$pi][3] ?? '')) === ''){
                    $payments[$pi][3] = $tracking;
                    $changedCsv = true;
                }
            }

            // اگر هیچ ردیفی پیدا نشد، با ایندکس resolve‌شده اجباراً آپدیت کن
            if(!$matchedIndexes && $csvIndex >= 0 && isset($payments[$csvIndex])){
                $payments[$csvIndex][6] = 'تایید شد';
                if($link !== ''){
                    $payments[$csvIndex][7] = $link;
                }
                $payments[$csvIndex][3] = $tracking;
                $changedCsv = true;
                $matchedIndexes[] = $csvIndex;
            }

            if($changedCsv){
                xuiSavePayments($payments);
            }

            if($matchedIndexes){
                $items[$idx]['csv_index'] = intval($matchedIndexes[0]);
                instantPaySave($items);
            }

            // یک‌بار دیگر همگام‌سازی ایمنی
            instantPaySyncPaidCsvRows();
        }

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
                        $meta['date'] ?? pnvJalaliToday('/'),
                        $meta['time'] ?? pnvTehranTime(null, 'H:i'),
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

        $open = function_exists('instantPayListMatchableOrders') ? instantPayListMatchableOrders(15) : [];

        return [
            'ok' => false,
            'error' => 'سفارش بازی با این مبلغ پیدا نشد',
            'amounts' => $amounts,
            'candidates' => $candidates,
            'open_orders' => $open
        ];
    }

    function instantPayIsMatchableItem($item, $now = null){
        if(!is_array($item) || !empty($item['match_closed'])){
            return false;
        }

        if($now === null){
            $now = time();
        }

        $status = (string)($item['status'] ?? '');
        $expires = intval($item['expires_at'] ?? 0);
        $grace = instantPayGraceSeconds();

        // بازگشت/لغو کاربر → مبلغ فوراً نامعتبر
        if($status === 'cancelled' || $status === 'paid' || $status === 'processing'){
            return false;
        }

        if($status === 'waiting' || $status === 'failed'){
            // waiting بعد از expires_at توسط ExpireDue به expired تبدیل می‌شود؛
            // اگر هنوز waiting مانده باشد تا پایان grace قابل مچ است.
            if($expires > 0 && $expires < $now && ($now - $expires) > $grace){
                return false;
            }
            return true;
        }

        if($status === 'expired'){
            return $expires > 0 && ($now - $expires) <= $grace;
        }

        return false;
    }

    function instantPayMatchAmountExact($amountRial){
        $amountRial = intval($amountRial);

        if($amountRial <= 0){
            return null;
        }

        $items = instantPayExpireDue();
        $now = time();
        $hits = [];

        foreach($items as $item){
            if(!instantPayIsMatchableItem($item, $now)){
                continue;
            }

            $itemAmount = instantPayNormalizeItemAmountRial($item);

            if($itemAmount === $amountRial || ($itemAmount > 0 && intdiv($itemAmount, 10) === $amountRial)){
                $hits[] = $item;
            }
        }

        if(count($hits) === 1){
            return $hits[0];
        }

        // اگر چندتا بود، جدیدترین را بگیر
        if(count($hits) > 1){
            usort($hits, static function($a, $b){
                return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
            });
            return $hits[0];
        }

        return null;
    }

    function instantPayListMatchableOrders($limit = 20){
        $items = instantPayExpireDue();
        $now = time();
        $out = [];

        foreach($items as $item){
            $status = (string)($item['status'] ?? '');
            $expires = intval($item['expires_at'] ?? 0);

            if(!instantPayIsMatchableItem($item, $now)){
                continue;
            }

            $amount = instantPayNormalizeItemAmountRial($item);
            $out[] = [
                'id' => $item['id'] ?? '',
                'user' => $item['user'] ?? '',
                'status' => $status,
                'amount' => $amount,
                'amount_text' => number_format($amount) . ' ریال',
                'code' => str_pad((string)intval($item['code'] ?? 0), 4, '0', STR_PAD_LEFT),
                'plan' => $item['plan'] ?? '',
                'type' => $item['type'] ?? '',
                'expires_at' => $expires,
                'remaining' => max(0, $expires - $now),
                'match_remaining' => max(0, ($expires > 0 ? $expires + instantPayGraceSeconds() : 0) - $now),
                'created_at' => intval($item['created_at'] ?? 0)
            ];
        }

        usort($out, static function($a, $b){
            return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
        });

        if($limit > 0){
            $out = array_slice($out, 0, $limit);
        }

        return $out;
    }
}

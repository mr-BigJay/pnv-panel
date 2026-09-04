<?php

require_once __DIR__ . '/bale_lib.php';
require_once __DIR__ . '/xui_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';
require_once __DIR__ . '/pnv_date_bootstrap.php';

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

        $n = intval($config['pay_window_seconds'] ?? 1800);
        return $n > 60 ? $n : 1800;
    }

    /**
     * پس از پایان مهلت پرداخت، چند ثانیه هنوز واریز را مچ کنیم.
     */
    function instantPayMatchGraceSeconds($config = null){
        if($config === null){
            $config = baleLoadConfig();
        }

        $n = intval($config['match_grace_seconds'] ?? 0);

        if($n > 0){
            return $n;
        }

        return max(1800, instantPayWindowSeconds($config) * 2);
    }

    function instantPayMaxOrderAgeSeconds($config = null){
        return instantPayWindowSeconds($config) + instantPayMatchGraceSeconds($config);
    }

    /**
     * مدت نمایش سفارش AUTO در لیست ادمین: مهلت پرداخت + ۱۰ دقیقه.
     */
    function instantPayAdminVisibilitySeconds($config = null){
        return instantPayWindowSeconds($config) + 600;
    }

    function instantPayNormalizeTracking($tracking){
        $tracking = trim((string)$tracking);

        if(preg_match('/^AUTO-(\d+)$/i', $tracking, $m)){
            return 'AUTO-' . str_pad((string)intval($m[1]), 4, '0', STR_PAD_LEFT);
        }

        return $tracking;
    }

    function instantPayCsvRowPending($row){
        if(!is_array($row)){
            return false;
        }

        $tracking = trim((string)($row[3] ?? ''));

        if(strpos($tracking, 'AUTO-') !== 0){
            return false;
        }

        $status = trim((string)($row[6] ?? ''));

        return in_array($status, ['', 'درحال بررسی', 'در حال بررسی'], true);
    }

    function instantPayCsvRowAmountRial($row){
        if(!is_array($row)){
            return 0;
        }

        return intval($row[12] ?? 0);
    }

    function instantPayFindJsonByTracking($user, $tracking, $items = null){
        $userKey = strtolower(trim((string)$user));
        $tracking = instantPayNormalizeTracking($tracking);

        if($userKey === '' || $tracking === ''){
            return null;
        }

        if($items === null){
            $items = instantPayLoad();
        }

        foreach($items as $item){
            if(strtolower(trim((string)($item['user'] ?? ''))) !== $userKey){
                continue;
            }

            if(instantPayNormalizeTracking(instantPayTrackingCode($item)) !== $tracking){
                continue;
            }

            return $item;
        }

        return null;
    }

    function instantPayFindCsvMatchByAmount($amountRial){
        $amountRial = intval($amountRial);

        if($amountRial <= 0 || !function_exists('xuiLoadPayments')){
            return null;
        }

        $payments = xuiLoadPayments();
        $now = time();
        $maxAge = instantPayMaxOrderAgeSeconds();
        $matches = [];

        foreach($payments as $i => $row){
            if(!instantPayCsvRowPending($row)){
                continue;
            }

            $rowAmount = instantPayCsvRowAmountRial($row);

            if($rowAmount <= 0){
                continue;
            }

            if($rowAmount !== $amountRial && intdiv($rowAmount, 10) !== $amountRial){
                continue;
            }

            $created = intval($row[8] ?? 0);

            if($created > 0 && ($now - $created) > $maxAge){
                continue;
            }

            $matches[] = [
                'csv_index' => $i,
                'row' => $row,
            ];
        }

        if(count($matches) === 1){
            return $matches[0];
        }

        if(count($matches) > 1){
            usort($matches, static function($a, $b){
                return intval($b['row'][8] ?? 0) <=> intval($a['row'][8] ?? 0);
            });

            return $matches[0];
        }

        return null;
    }

    function instantPayWithinMatchGrace($item, $now = null){
        $now = $now ?? time();
        $expires = intval($item['expires_at'] ?? 0);

        if($expires <= 0){
            return false;
        }

        return ($now - $expires) <= instantPayMatchGraceSeconds();
    }

    function instantPayProcessingTimeoutSeconds(){
        return 180;
    }

    function instantPayProcessingIsStale($item, $now = null){
        if(($item['status'] ?? '') !== 'processing'){
            return false;
        }

        $now = $now ?? time();
        $started = intval($item['processing_at'] ?? 0);

        if($started <= 0){
            $started = intval($item['created_at'] ?? 0);
        }

        if($started <= 0){
            return true;
        }

        return ($now - $started) >= instantPayProcessingTimeoutSeconds();
    }

    function instantPayRecoverStaleProcessing($items = null){
        if($items === null){
            $items = instantPayLoad();
        }

        $now = time();
        $changed = false;

        foreach($items as $i => $item){
            if(!instantPayProcessingIsStale($item, $now)){
                continue;
            }

            $items[$i]['status'] = 'failed';
            $items[$i]['message'] = 'تأیید نیمه‌کاره؛ دوباره تلاش می‌شود';
            $changed = true;
        }

        if($changed){
            instantPaySave($items);
        }

        return $items;
    }

    /**
     * بعد از تأیید CSV (AUTO)، ردیف JSON را هم paid کن — مثلاً تأیید از پنل/ربات ادمین.
     */
    function instantPaySyncJsonAfterCsvApproval($csvIndex, $row, $result = []){
        if(!is_array($row)){
            return false;
        }

        $tracking = trim((string)($row[3] ?? ''));

        if(strpos($tracking, 'AUTO-') !== 0){
            return false;
        }

        $csvIndex = intval($csvIndex);
        $userKey = strtolower(trim((string)($row[0] ?? '')));
        $trackingKey = instantPayNormalizeTracking($tracking);
        $link = trim((string)($result['link'] ?? ($row[7] ?? '')));
        $items = instantPayLoad();
        $changed = false;

        foreach($items as $i => $item){
            $matches = false;

            if($csvIndex >= 0 && intval($item['csv_index'] ?? -1) === $csvIndex){
                $matches = true;
            }
            elseif(
                $userKey !== ''
                && strtolower(trim((string)($item['user'] ?? ''))) === $userKey
                && instantPayNormalizeTracking(instantPayTrackingCode($item)) === $trackingKey
            ){
                $matches = true;
            }

            if(!$matches){
                continue;
            }

            if(($item['status'] ?? '') === 'paid'){
                return true;
            }

            $items[$i]['status'] = 'paid';
            $items[$i]['paid_at'] = time();
            $items[$i]['link'] = $link;
            $items[$i]['message'] = 'پرداخت تأیید شد';
            $items[$i]['csv_index'] = $csvIndex;
            $items[$i]['csv_purged'] = false;
            unset($items[$i]['processing_at']);
            $changed = true;
            break;
        }

        if($changed){
            instantPaySave($items);
        }

        return $changed;
    }

    /**
     * اطلاع تأیید پرداخت به ادمین (تلگرام + بله).
     */
    function instantPayNotifyPaymentConfirmed($found, $row = null){
        if(!is_array($found)){
            return;
        }

        if(!is_array($row)){
            $csvIndex = instantPayResolveCsvIndex($found);

            if($csvIndex < 0 || !function_exists('xuiLoadPayments')){
                return;
            }

            $payments = xuiLoadPayments();

            if(!isset($payments[$csvIndex]) || !is_array($payments[$csvIndex])){
                return;
            }

            $row = $payments[$csvIndex];
        }

        if(trim((string)($row[6] ?? '')) !== 'تایید شد'){
            return;
        }

        $link = trim((string)($row[7] ?? ($found['link'] ?? '')));
        $typeHint = trim((string)($found['type'] ?? ''));

        if($typeHint === '' && function_exists('xuiResolvePaymentType')){
            $typeHint = xuiResolvePaymentType($row, 'خرید');
        }

        if($typeHint === ''){
            $typeHint = 'خرید';
        }

        $opts = ['link' => $link];

        if(function_exists('telegramNotifyPaymentConfirmedRow')){
            try{
                telegramNotifyPaymentConfirmedRow($row, $typeHint, $opts);
            }
            catch(Throwable $e){
                error_log('instant pay confirm telegram notify failed: ' . $e->getMessage());
            }
        }
        elseif(function_exists('xuiTelegramNotifyApproved')){
            try{
                xuiTelegramNotifyApproved($row, $typeHint, $link);
            }
            catch(Throwable $e){
                error_log('instant pay confirm telegram notify failed: ' . $e->getMessage());
            }
        }

        if(function_exists('baleNotifyPaymentConfirmedRow')){
            try{
                baleNotifyPaymentConfirmedRow($row, $typeHint, $opts);
            }
            catch(Throwable $e){
                error_log('instant pay confirm bale notify failed: ' . $e->getMessage());
            }
        }
    }

    /** @deprecated use instantPayNotifyPaymentConfirmed */
    function instantPayTryConfirmTelegramNotify($found, $row = null){
        instantPayNotifyPaymentConfirmed($found, $row);
    }

    function instantPayItemMatchable($item, $now = null){
        $now = $now ?? time();
        $status = (string)($item['status'] ?? '');

        if($status === 'waiting'){
            if(intval($item['expires_at'] ?? 0) >= $now){
                return true;
            }

            return instantPayWithinMatchGrace($item, $now);
        }

        if($status === 'expired'){
            return instantPayWithinMatchGrace($item, $now);
        }

        if($status === 'failed'){
            return instantPayWithinMatchGrace($item, $now);
        }

        return false;
    }

    function instantPayEnsureCsvRowForItem($item, &$items, $idx){
        if(!is_array($item)){
            return -1;
        }

        $csvIndex = instantPayResolveCsvIndex($item);

        if($csvIndex >= 0){
            return $csvIndex;
        }

        if(!function_exists('xuiLoadPayments') || !function_exists('xuiSavePayments')){
            return -1;
        }

        $amount = intval($item['amount'] ?? 0);

        if($amount > 0){
            $match = instantPayFindCsvMatchByAmount($amount);

            if(is_array($match)){
                $csvIndex = intval($match['csv_index'] ?? -1);

                if($csvIndex >= 0 && $idx >= 0){
                    $items[$idx]['csv_index'] = $csvIndex;
                    $items[$idx]['csv_purged'] = false;
                    instantPaySave($items);
                }

                return $csvIndex;
            }
        }

        $type = instantPayNormalizeType($item['type'] ?? 'خرید');
        $target = ($type === 'تمدید') ? trim((string)($item['sub'] ?? '')) : trim((string)($item['subname'] ?? ''));
        $code = intval($item['code'] ?? 0);
        $now = intval($item['created_at'] ?? time());
        $amount = $amount > 0 ? $amount : instantPayNormalizeItemAmountRial($item);

        if($target === '' || $amount <= 0){
            return -1;
        }

        $row = [
            trim((string)($item['user'] ?? '')),
            $target,
            trim((string)($item['plan'] ?? '')),
            instantPayTrackingCode($item),
            '',
            '',
            'درحال بررسی',
            '',
            $now,
            $type,
            trim((string)($item['coupon_code'] ?? '')) !== '' ? strtoupper(trim((string)($item['coupon_code'] ?? ''))) : '',
            intval($item['discount_percent'] ?? 0),
            $amount,
            $code,
        ];

        $payments = xuiLoadPayments();
        $payments[] = $row;
        $csvIndex = count($payments) - 1;
        xuiSavePayments($payments);

        if($idx >= 0){
            $items[$idx]['csv_index'] = $csvIndex;
            $items[$idx]['csv_purged'] = false;
            instantPaySave($items);
        }

        instantPayRebuildCsvIndexes();

        return $csvIndex;
    }

    function instantPayResolveCsvIndex($item){
        $csvIndex = intval($item['csv_index'] ?? -1);

        if($csvIndex >= 0 && function_exists('xuiLoadPayments')){
            $payments = xuiLoadPayments();

            if(isset($payments[$csvIndex]) && is_array($payments[$csvIndex])){
                $rowTracking = instantPayNormalizeTracking(trim((string)($payments[$csvIndex][3] ?? '')));
                $itemTracking = instantPayTrackingCode($item);

                if($rowTracking !== '' && $rowTracking === $itemTracking){
                    return $csvIndex;
                }
            }
        }

        if(!function_exists('xuiLoadPayments')){
            return -1;
        }

        instantPayRebuildCsvIndexes();
        $tracking = instantPayTrackingCode($item);
        $user = strtolower(trim((string)($item['user'] ?? '')));
        $payments = xuiLoadPayments();

        foreach($payments as $i => $row){
            if(!is_array($row)){
                continue;
            }

            if(instantPayNormalizeTracking(trim((string)($row[3] ?? ''))) !== $tracking){
                continue;
            }

            if($user !== '' && strtolower(trim((string)($row[0] ?? ''))) !== $user){
                continue;
            }

            return $i;
        }

        return -1;
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
        if(function_exists('pnvFindPlanByValue')){
            $plan = pnvFindPlanByValue($planValue, $plans);

            if($plan){
                return $plan;
            }
        }

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
        }
        else{
            $code = $itemOrCode;
        }

        return 'AUTO-' . str_pad((string)intval($code), 4, '0', STR_PAD_LEFT);
    }

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

            $tracking = instantPayNormalizeTracking(trim((string)($row[3] ?? '')));

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

            if(instantPayNormalizeTracking(trim((string)($row[3] ?? ''))) !== instantPayNormalizeTracking($tracking)){
                continue;
            }

            if($user !== '' && strcasecmp(trim((string)($row[0] ?? '')), $user) !== 0){
                continue;
            }

            $st = trim((string)($row[6] ?? ''));

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
     * ردیف‌های پرداخت آنیِ رها‌شده را از CSV حذف و لیست ادمین را تمیز می‌کند.
     */
    function instantPayPurgeStaleAdminRows($items = null){
        if($items === null){
            $items = instantPayExpireDue();
        }

        $now = time();
        $grace = instantPayMatchGraceSeconds();
        $changed = false;

        foreach($items as $i => $item){
            $status = (string)($item['status'] ?? '');
            $expires = intval($item['expires_at'] ?? 0);
            $shouldDelete = false;

            if(in_array($status, ['cancelled', 'failed'], true)){
                if($status === 'failed' && instantPayWithinMatchGrace($item, $now)){
                    $shouldDelete = false;
                }
                elseif($status === 'cancelled' && instantPayWithinMatchGrace($item, $now)){
                    $shouldDelete = false;
                }
                else{
                    $shouldDelete = true;
                }
            }

            if($status === 'expired' && $expires > 0 && !instantPayWithinMatchGrace($item, $now)){
                $shouldDelete = true;
            }

            if($status === 'waiting' && $expires > 0 && $expires < $now){
                $items[$i]['status'] = 'expired';
                $changed = true;

                if(!instantPayWithinMatchGrace($items[$i], $now)){
                    $shouldDelete = true;
                }
            }

            if($shouldDelete && empty($items[$i]['csv_purged'])){
                instantPayDeleteAbandonedCsv($items[$i]);
                $items[$i]['csv_purged'] = true;
                $items[$i]['csv_index'] = -1;
                $changed = true;
            }
        }

        if($changed){
            instantPaySave($items);
            $items = instantPayRebuildCsvIndexes(instantPayLoad());
        }

        if(!function_exists('xuiLoadPayments') || !function_exists('xuiDeletePaymentIndexes')){
            return $items;
        }

        $payments = xuiLoadPayments();
        $activeKeys = [];

        foreach($items as $item){
            $st = (string)($item['status'] ?? '');

            if(!in_array($st, ['waiting', 'processing', 'paid', 'expired', 'failed'], true)){
                continue;
            }

            if(empty($item['csv_purged']) && in_array($st, ['expired', 'failed'], true) && !instantPayWithinMatchGrace($item, $now)){
                continue;
            }

            if(empty($item['csv_purged'])){
                $activeKeys[strtolower(trim((string)($item['user'] ?? ''))) . '|' . instantPayTrackingCode($item)] = true;
            }
        }

        $orphanDelete = [];
        $window = instantPayMaxOrderAgeSeconds();

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
            $key = strtolower(trim((string)($row[0] ?? ''))) . '|' . instantPayNormalizeTracking($tracking);

            if(function_exists('instantPayAdminRowIsInProgress') && instantPayAdminRowIsInProgress($row)){
                continue;
            }

            if($created > 0 && ($now - $created) < $window && isset($activeKeys[$key])){
                continue;
            }

            $orphanDelete[] = $pi;
        }

        if($orphanDelete){
            xuiDeletePaymentIndexes($orphanDelete);
            instantPayRebuildCsvIndexes();
        }

        return $items;
    }

    function instantPayNormalizeType($type){
        return trim((string)$type) === 'تمدید' ? 'تمدید' : 'خرید';
    }

    function instantPayAdminRowIsInProgress($row){
        if(!is_array($row)){
            return false;
        }

        if(!instantPayCsvRowPending($row)){
            return false;
        }

        $tracking = trim((string)($row[3] ?? ''));

        if(strpos($tracking, 'AUTO-') !== 0){
            return true;
        }

        $created = intval($row[8] ?? 0);
        $now = time();
        $maxAge = instantPayAdminVisibilitySeconds();

        if($created <= 0 || ($now - $created) > $maxAge){
            return false;
        }

        $user = trim((string)($row[0] ?? ''));
        $item = instantPayFindJsonByTracking($user, $tracking);

        if(is_array($item) && in_array((string)($item['status'] ?? ''), ['cancelled', 'failed'], true)){
            return false;
        }

        return true;
    }

    function instantPayAdminRowStatusMeta($row){
        $status = trim((string)($row[6] ?? ''));

        if($status === 'تایید شد'){
            return ['title' => 'تایید شد', 'class' => 'statusDot--green'];
        }

        if($status === 'رد شد'){
            return ['title' => 'رد شد', 'class' => 'statusDot--red'];
        }

        if(instantPayAdminRowIsInProgress($row)){
            $tracking = trim((string)($row[3] ?? ''));

            if(strpos($tracking, 'AUTO-') === 0){
                $item = instantPayFindJsonByTracking($row[0] ?? '', $tracking);

                if(is_array($item) && ($item['status'] ?? '') === 'processing'){
                    return ['title' => 'در حال صدور', 'class' => 'statusDot--yellow'];
                }

                return ['title' => 'در حال بررسی', 'class' => 'statusDot--yellow'];
            }
        }

        return ['title' => 'در حال بررسی', 'class' => 'statusDot--yellow'];
    }

    /**
     * آیا این ردیف CSV در لیست ادمین نمایش داده شود؟
     * سفارش AUTOِ درحال‌انجام تا پایان مهلت پرداخت + ۱۰ دقیقه دیده می‌شود.
     */
    function instantPayAdminRowVisible($row){
        if(!is_array($row)){
            return false;
        }

        $tracking = trim((string)($row[3] ?? ''));

        if(strpos($tracking, 'AUTO-') !== 0){
            return true;
        }

        $status = trim((string)($row[6] ?? ''));

        if($status === 'تایید شد'){
            return true;
        }

        if(in_array($status, ['', 'درحال بررسی', 'در حال بررسی'], true)){
            return instantPayAdminRowIsInProgress($row);
        }

        if($status === 'رد شد'){
            $reason = trim((string)($row[7] ?? ''));

            if(in_array($reason, ['لغو شد', 'منقضی شد', 'لغو به‌خاطر مبلغ جدید', 'لغو به‌خاطر درخواست جدید'], true)){
                return false;
            }
        }

        return true;
    }

    function instantPayOptsSignature($opts){
        return implode('|', [
            trim((string)($opts['type'] ?? 'خرید')),
            trim((string)($opts['plan'] ?? '')),
            trim((string)($opts['subname'] ?? '')),
            trim((string)($opts['sub'] ?? '')),
            trim((string)($opts['card'] ?? '')),
            strtoupper(trim((string)($opts['coupon_code'] ?? ''))),
            (string)intval($opts['discount_percent'] ?? 0),
        ]);
    }

    function instantPayItemSignature($item){
        return instantPayOptsSignature([
            'type' => $item['type'] ?? 'خرید',
            'plan' => $item['plan_value'] ?? ($item['plan'] ?? ''),
            'subname' => $item['subname'] ?? '',
            'sub' => $item['sub'] ?? '',
            'card' => $item['card'] ?? '',
            'coupon_code' => $item['coupon_code'] ?? '',
            'discount_percent' => $item['discount_percent'] ?? 0,
        ]);
    }

    function instantPayFindReusableWaiting($username, $opts, $items = null){
        $username = trim((string)$username);

        if($username === ''){
            return null;
        }

        if($items === null){
            $items = instantPayExpireDue();
        }

        $sig = instantPayOptsSignature($opts);
        $now = time();

        foreach($items as $item){
            if(trim((string)($item['user'] ?? '')) !== $username){
                continue;
            }

            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            if(intval($item['expires_at'] ?? 0) < $now){
                continue;
            }

            if(instantPayItemSignature($item) !== $sig){
                continue;
            }

            return $item;
        }

        return null;
    }

    function instantPaySupersedeUserWaiting($username, $type, $exceptId = null, $items = null){
        $username = trim((string)$username);
        $type = instantPayNormalizeType($type);
        $exceptId = $exceptId !== null ? trim((string)$exceptId) : null;

        if($items === null){
            $items = instantPayExpireDue();
        }

        $changed = false;
        $now = time();

        if(!function_exists('checkoutReleaseDiscountOrder')){
            require_once __DIR__ . '/campaign_lib.php';
        }

        foreach($items as $i => $item){
            if(trim((string)($item['user'] ?? '')) !== $username){
                continue;
            }

            if(instantPayNormalizeType($item['type'] ?? 'خرید') !== $type){
                continue;
            }

            $id = (string)($item['id'] ?? '');

            if($exceptId !== null && $exceptId !== '' && $id === $exceptId){
                continue;
            }

            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            $items[$i]['status'] = 'cancelled';
            $items[$i]['message'] = 'لغو به‌خاطر درخواست جدید';
            $items[$i]['cancelled_at'] = $now;
            $changed = true;

            checkoutReleaseDiscountOrder($items[$i]['id'] ?? '');
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

    function instantPayCloseUserMatchable($username, $exceptId = null, $items = null, $type = null){
        if($type !== null){
            return instantPaySupersedeUserWaiting($username, $type, $exceptId, $items);
        }

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

            if($status !== 'waiting'){
                continue;
            }

            $items[$i]['status'] = 'cancelled';
            $items[$i]['message'] = 'لغو به‌خاطر مبلغ جدید';
            $items[$i]['cancelled_at'] = $now;
            $changed = true;
        }

        if($changed){
            instantPaySave($items);
            $items = instantPayRebuildCsvIndexes(instantPayLoad());
        }

        return $items;
    }

    function instantPayExpireDue($items = null){
        if($items === null){
            $items = instantPayLoad();
        }

        $items = instantPayRecoverStaleProcessing($items);

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

            if(!function_exists('checkoutReleaseDiscountOrder')){
                require_once __DIR__ . '/pnv_campaign_bootstrap.php';
            }

            checkoutReleaseDiscountOrder($items[$i]['id'] ?? '');

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
        $discountSource = trim((string)($opts['discount_source'] ?? ''));
        $discountFinalThousands = intval($opts['discount_final_thousands'] ?? 0);

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

        if($type === 'تمدید' && function_exists('pnvValidateRenewPlanCategory')){
            $categoryCheck = pnvValidateRenewPlanCategory($username, $sub, $planValue, $plans);

            if(empty($categoryCheck['ok'])){
                return $categoryCheck;
            }
        }

        $items = instantPayExpireDue();

        $createOpts = [
            'user' => $username,
            'type' => $type,
            'plan' => $planValue,
            'subname' => $subname,
            'sub' => $sub,
            'card' => $card,
            'coupon_code' => $couponCode,
            'discount_percent' => $discountPercent,
            'discount_source' => $discountSource,
            'discount_final_thousands' => $discountFinalThousands,
        ];

        $reusable = instantPayFindReusableWaiting($username, $createOpts, $items);

        if(is_array($reusable)){
            return [
                'ok' => true,
                'item' => instantPayPublicView($reusable),
                'reused' => true,
            ];
        }

        // فقط یک درخواست درحال‌انجام برای هر نوع (خرید/تمدید)
        $items = instantPaySupersedeUserWaiting($username, $type, null, $items);

        $code = instantPayAllocateCode($items);

        if($code === null){
            return ['ok' => false, 'error' => 'کد یکتا در دسترس نیست؛ کمی بعد دوباره تلاش کنید'];
        }

        $priceThousands = intval($plan['price'] ?? 0);

        if($discountFinalThousands > 0 && $discountSource === 'admin_discount'){
            $priceThousands = max(0, $discountFinalThousands);
        }
        elseif($discountPercent > 0 && $discountPercent <= 100 && function_exists('couponApplyDiscountThousands')){
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
            'discount_source' => $discountSource,
            'discount_final_thousands' => $discountFinalThousands,
            'link' => '',
            'message' => ''
        ];

        $items[] = $item;
        instantPaySave($items);

        if(function_exists('telegramNotifyNewPayment')){
            try{
                telegramNotifyNewPayment($type, $row);
            }
            catch(Throwable $e){
                error_log('instant pay telegram create notify failed: ' . $e->getMessage());
            }
        }

        return [
            'ok' => true,
            'item' => instantPayPublicView($item)
        ];
    }

    function instantPayMarkPaid($id, $meta = [], $opts = []){
        $force = !empty($opts['force']);
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
            instantPayNotifyPaymentConfirmed($found);

            return ['ok' => true, 'already' => true, 'item' => instantPayPublicView($found)];
        }

        if(($found['status'] ?? '') === 'processing' && !instantPayProcessingIsStale($found)){
            return ['ok' => false, 'error' => 'سفارش در حال پردازش است؛ چند لحظه صبر کنید'];
        }

        if(!$force && !instantPayItemMatchable($found)){
            return ['ok' => false, 'error' => 'سفارش قابل تأیید نیست (' . ($found['status'] ?? '') . ')'];
        }

        if($force && ($found['status'] ?? '') === 'cancelled'){
            $csvIndexCheck = instantPayResolveCsvIndex($found);

            if($csvIndexCheck < 0){
                return ['ok' => false, 'error' => 'سفارش لغو شده است'];
            }

            $items[$idx]['status'] = 'expired';
            $items[$idx]['message'] = 'بازیابی برای تأیید واریز';
            $items[$idx]['csv_index'] = $csvIndexCheck;
            $items[$idx]['csv_purged'] = false;
            instantPaySave($items);
            $found = $items[$idx];
        }

        $csvIndex = instantPayEnsureCsvRowForItem($found, $items, $idx);

        if($csvIndex < 0){
            return ['ok' => false, 'error' => 'ایندکس پرداخت نامعتبر است'];
        }

        $found = $items[$idx] ?? $found;

        // تاریخ/ساعت را برای لاگ پر می‌کنیم
        $payments = xuiLoadPayments();

        if(isset($payments[$csvIndex])){
            $nowParts = pnvNowParts();
            $payments[$csvIndex][4] = $meta['date'] ?? $nowParts['date'];
            $payments[$csvIndex][5] = $meta['time'] ?? $nowParts['time'];
            xuiSavePayments($payments);
        }

        // اول وضعیت را روی processing بگذار تا UI هنوز «تأیید شد» نگوید
        $items[$idx]['status'] = 'processing';
        $items[$idx]['processing_at'] = time();
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

        if(!empty($found['coupon_code']) || !empty($found['discount_source'])){
            if(!function_exists('checkoutMarkDiscountPaid')){
                require_once __DIR__ . '/pnv_campaign_bootstrap.php';
            }

            checkoutMarkDiscountPaid($found);
        }

        $payments = xuiLoadPayments();
        $paidRow = isset($payments[$csvIndex]) && is_array($payments[$csvIndex]) ? $payments[$csvIndex] : null;
        instantPayNotifyPaymentConfirmed($items[$idx], $paidRow);

        return [
            'ok' => true,
            'item' => instantPayPublicView($items[$idx]),
            'provision' => $result
        ];
    }

    function instantPayMarkPaidFromCsv($csvIndex, $meta = []){
        if(!function_exists('xuiLoadPayments')){
            return ['ok' => false, 'error' => 'سیستم پرداخت در دسترس نیست'];
        }

        $csvIndex = intval($csvIndex);
        $payments = xuiLoadPayments();

        if(!isset($payments[$csvIndex]) || !is_array($payments[$csvIndex])){
            return ['ok' => false, 'error' => 'رد CSV پیدا نشد'];
        }

        $row = $payments[$csvIndex];
        $user = trim((string)($row[0] ?? ''));
        $tracking = trim((string)($row[3] ?? ''));

        if(!instantPayCsvRowPending($row)){
            $status = trim((string)($row[6] ?? ''));

            if($status === 'تایید شد'){
                $link = trim((string)($row[7] ?? ''));
                instantPaySyncJsonAfterCsvApproval($csvIndex, $row, ['ok' => true, 'link' => $link]);

                $jsonItem = instantPayFindJsonByTracking($user, $tracking);
                $notifyFound = is_array($jsonItem) ? $jsonItem : [
                    'type' => xuiResolvePaymentType($row, 'خرید'),
                    'link' => $link,
                ];
                instantPayNotifyPaymentConfirmed($notifyFound, $row);
                $publicItem = is_array($jsonItem) && !empty($jsonItem['id'])
                    ? instantPayPublicView($jsonItem)
                    : [
                        'status' => 'paid',
                        'ready' => true,
                        'link' => $link,
                        'amount_text' => number_format(instantPayCsvRowAmountRial($row)) . ' ریال',
                        'plan' => trim((string)($row[2] ?? '')),
                        'code' => preg_replace('/^AUTO-/i', '', trim((string)($row[3] ?? ''))),
                    ];

                return [
                    'ok' => true,
                    'already' => true,
                    'item' => $publicItem,
                ];
            }

            return ['ok' => false, 'error' => 'رد CSV قابل تأیید نیست (' . $status . ')'];
        }

        $jsonItem = instantPayFindJsonByTracking($user, $tracking);

        if(is_array($jsonItem) && !empty($jsonItem['id'])){
            $items = instantPayLoad();

            foreach($items as $i => $item){
                if(($item['id'] ?? '') !== ($jsonItem['id'] ?? '')){
                    continue;
                }

                $items[$i]['csv_index'] = $csvIndex;
                $items[$i]['csv_purged'] = false;

                if(($items[$i]['status'] ?? '') === 'failed'){
                    $items[$i]['status'] = 'expired';
                    $items[$i]['message'] = 'تلاش مجدد تأیید خودکار';
                }

                instantPaySave($items);
                break;
            }

            return instantPayMarkPaid($jsonItem['id'], $meta, ['force' => true]);
        }

        if(isset($payments[$csvIndex])){
            $nowParts = pnvNowParts();
            $payments[$csvIndex][4] = $meta['date'] ?? $nowParts['date'];
            $payments[$csvIndex][5] = $meta['time'] ?? $nowParts['time'];
            xuiSavePayments($payments);
        }

        $result = xuiApprovePaymentIndex($csvIndex, xuiResolvePaymentType($row, 'خرید'));

        if(empty($result['ok'])){
            return $result;
        }

        $paidItem = [
            'id' => instantPayNewId(),
            'user' => $user,
            'type' => trim((string)($row[9] ?? 'خرید')),
            'subname' => (trim((string)($row[9] ?? '')) === 'تمدید') ? '' : trim((string)($row[1] ?? '')),
            'sub' => (trim((string)($row[9] ?? '')) === 'تمدید') ? trim((string)($row[1] ?? '')) : '',
            'plan' => trim((string)($row[2] ?? '')),
            'amount' => instantPayCsvRowAmountRial($row),
            'currency' => 'rial',
            'code' => intval($row[13] ?? 0),
            'status' => 'paid',
            'created_at' => intval($row[8] ?? time()),
            'expires_at' => intval($row[8] ?? time()),
            'paid_at' => time(),
            'csv_index' => $csvIndex,
            'link' => $result['link'] ?? '',
            'message' => 'پرداخت تأیید شد (CSV)',
            'matched_amount' => intval($meta['amount'] ?? instantPayCsvRowAmountRial($row)),
        ];

        $items = instantPayLoad();
        $items[] = $paidItem;
        instantPaySave($items);

        return [
            'ok' => true,
            'item' => instantPayPublicView($paidItem),
            'provision' => $result,
            'matched_via' => 'csv',
        ];
    }

    function instantPayTryDepositAmount($amount, $text, $meta = []){
        $amount = intval($amount);

        if($amount <= 0){
            return null;
        }

        $payload = [
            'amount' => $amount,
            'text' => $text,
            'date' => $meta['date'] ?? '',
            'time' => $meta['time'] ?? '',
        ];

        $item = instantPayMatchAmountExact($amount);

        if(!$item){
            $item = instantPayMatchAmount($amount);
        }

        if(is_array($item) && !empty($item['id'])){
            $result = instantPayMarkPaid($item['id'], $payload);
            $result['matched_amount'] = $amount;
            $result['matched_via'] = 'json';

            if(!empty($result['ok']) || !empty($result['already'])){
                return $result;
            }
        }

        $csvMatch = instantPayFindCsvMatchByAmount($amount);

        if(!is_array($csvMatch)){
            return null;
        }

        $result = instantPayMarkPaidFromCsv($csvMatch['csv_index'], $payload);
        $result['matched_amount'] = $amount;
        if(empty($result['matched_via'])){
            $result['matched_via'] = 'csv';
        }

        return $result;
    }

    function instantPayMatchDebugSnapshot($amounts = []){
        $items = instantPayLoad();
        $now = time();
        $waiting = 0;
        $grace = 0;
        $csvPending = 0;

        foreach($items as $item){
            $st = (string)($item['status'] ?? '');

            if($st === 'waiting' && intval($item['expires_at'] ?? 0) >= $now){
                $waiting++;
            }

            if(in_array($st, ['waiting', 'expired', 'failed'], true) && instantPayItemMatchable($item, $now)){
                $grace++;
            }
        }

        if(function_exists('xuiLoadPayments')){
            foreach(xuiLoadPayments() as $row){
                if(instantPayCsvRowPending($row)){
                    $csvPending++;
                }
            }
        }

        return [
            'waiting' => $waiting,
            'matchable' => $grace,
            'csv_pending' => $csvPending,
            'amounts' => array_values(array_map('intval', (array)$amounts)),
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
            if(!instantPayItemMatchable($item, $now)){
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

    function instantPayListMatchableOrders($limit = 8){
        $items = instantPayExpireDue();
        $now = time();
        $out = [];

        foreach($items as $item){
            if(!instantPayItemMatchable($item, $now)){
                continue;
            }

            $view = instantPayPublicView($item);
            $out[] = [
                'id' => $item['id'] ?? '',
                'user' => $item['user'] ?? '',
                'amount' => intval($view['amount'] ?? ($item['amount'] ?? 0)),
                'amount_text' => $view['amount_text'] ?? '',
                'code' => intval($item['code'] ?? 0),
                'status' => $item['status'] ?? '',
                'plan' => $item['plan'] ?? '',
            ];

            if(count($out) >= max(1, intval($limit))){
                break;
            }
        }

        return $out;
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
            return ['ok' => false, 'error' => 'مبلغی در پیام پیدا نشد', 'ignored' => true];
        }

        // پیام پست‌بانک: مبالغ ریال‌اند؛ «مانده» را از قبل حذف کرده‌ایم
        $rialOnly = function_exists('baleLooksLikePostBankNotice') && baleLooksLikePostBankNotice($text);
        $candidates = instantPayExpandAmountCandidates($amounts, ['rial_only' => $rialOnly]);

        $lastFailure = null;

        foreach($candidates as $amount){
            $result = instantPayTryDepositAmount($amount, $text, [
                'date' => $meta['date'] ?? '',
                'time' => $meta['time'] ?? '',
            ]);

            if(!is_array($result)){
                continue;
            }

            $result['parsed_amounts'] = $amounts;

            if(!empty($result['ok']) || !empty($result['already'])){
                return $result;
            }

            $lastFailure = $result;
        }

        if(is_array($lastFailure)){
            if(empty($lastFailure['ignored'])){
                $lastFailure['ignored'] = false;
            }

            return $lastFailure;
        }

        return [
            'ok' => false,
            'error' => instantPayDepositNoMatchError($amounts, $candidates),
            'ignored' => true,
            'amounts' => $amounts,
            'candidates' => $candidates,
            'debug' => instantPayMatchDebugSnapshot($amounts),
            'open_orders' => instantPayListMatchableOrders(),
        ];
    }

    function instantPayDepositNoMatchError($amounts, $candidates = []){
        $items = instantPayLoad();
        $now = time();

        foreach((array)$amounts as $amount){
            $amount = intval($amount);

            if($amount <= 0){
                continue;
            }

            $csvMatch = instantPayFindCsvMatchByAmount($amount);

            if(is_array($csvMatch)){
                return 'رد CSV پیدا شد ولی تأیید خودکار ناموفق بود؛ دوباره فوروارد کنید یا از ادمین تأیید کنید.';
            }

            foreach($items as $item){
                $itemAmount = instantPayNormalizeItemAmountRial($item);

                if($itemAmount !== $amount){
                    continue;
                }

                $status = (string)($item['status'] ?? '');

                if($status === 'cancelled'){
                    if(is_array(instantPayFindCsvMatchByAmount($amount))){
                        return 'سفارش در سیستم لغو شده ولی ردیف پرداخت هنوز فعال است؛ دوباره فوروارد کنید.';
                    }

                    return 'سفارش لغو شده است (احتمالاً مبلغ جدید ساخته شده). کاربر باید دقیقاً مبلغ فعلی صفحه را واریز کند.';
                }

                if($status === 'expired' || ($status === 'waiting' && intval($item['expires_at'] ?? 0) < $now)){
                    if(!instantPayWithinMatchGrace($item, $now)){
                        return 'سفارش این مبلغ منقضی شده است. از ادمین تأیید کنید یا مبلغ جدید بسازید.';
                    }
                }

                if($status === 'processing' && !instantPayProcessingIsStale($item, $now)){
                    return 'سفارش این مبلغ در حال صدور است؛ چند لحظه صبر کنید.';
                }

                if($status === 'failed'){
                    return 'تأیید خودکار ناموفق بود: ' . trim((string)($item['message'] ?? 'خطای XUI')) . ' — دوباره فوروارد کنید یا از ادمین تأیید کنید.';
                }
            }
        }

        return 'سفارش بازی با این مبلغ پیدا نشد';
    }

    function instantPayMatchAmountExact($amountRial){
        $amountRial = intval($amountRial);

        if($amountRial <= 0){
            return null;
        }

        $items = instantPayExpireDue();
        $now = time();

        foreach($items as $item){
            if(!instantPayItemMatchable($item, $now)){
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

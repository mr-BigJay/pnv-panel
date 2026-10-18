<?php

require_once __DIR__ . '/bale_lib.php';
require_once __DIR__ . '/xui_lib.php';
require_once __DIR__ . '/plan_ui_lib.php';
require_once __DIR__ . '/pnv_date_bootstrap.php';

if(!function_exists('instantPayPath')){

    function instantPayPath(){
        $override = getenv('PNV_INSTANT_PAYMENTS_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

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
        if(
            !empty($GLOBALS['instantPayTestSaveInterceptor'])
            && is_callable($GLOBALS['instantPayTestSaveInterceptor'])
        ){
            $decision = $GLOBALS['instantPayTestSaveInterceptor']($items);

            if($decision === false){
                return false;
            }
        }

        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($json === false){
            return false;
        }

        $written = file_put_contents(instantPayPath(), $json, LOCK_EX);

        return $written !== false && $written >= strlen($json);
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

    // Tracking codes may be reused months later. Bind CSV/JSON by creation time.
    function instantPayFindJsonForCsvRow($row, $items = null){
        if(!is_array($row)) return null;
        $user = strtolower(trim((string)($row[0] ?? '')));
        $tracking = instantPayNormalizeTracking($row[3] ?? '');
        $created = intval($row[8] ?? 0);
        if($user === '' || $tracking === '') return null;
        if($items === null) $items = instantPayLoad();
        foreach($items as $item){
            if(!is_array($item)
                || strtolower(trim((string)($item['user'] ?? ''))) !== $user
                || instantPayNormalizeTracking(instantPayTrackingCode($item)) !== $tracking
                || ($created > 0 && intval($item['created_at'] ?? 0) !== $created)){
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
            $tracking = trim((string)($row[3] ?? ''));
            if(strpos($tracking, 'AUTO-') === 0){
                $linked = instantPayFindJsonForCsvRow($row);
                if(is_array($linked) && !instantPayItemMatchable($linked)){
                    continue;
                }
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

        if($status === 'payment_received_provision_failed'){
            return false;
        }

        return false;
    }

    function instantPayDepositConfirmedStatuses(){
        return ['payment_received', 'processing', 'payment_received_provision_failed', 'paid'];
    }

    function instantPayResolveCsvIndex($item){
        if(!is_array($item) || !function_exists('xuiLoadPayments')) return -1;
        $user = strtolower(trim((string)($item['user'] ?? '')));
        $tracking = instantPayNormalizeTracking(instantPayTrackingCode($item));
        $created = intval($item['created_at'] ?? 0);
        $payments = xuiLoadPayments();
        $preferred = intval($item['csv_index'] ?? -1);
        if($preferred >= 0 && isset($payments[$preferred])){
            $p = $payments[$preferred];
            if(strtolower(trim((string)($p[0] ?? ''))) === $user
                && instantPayNormalizeTracking($p[3] ?? '') === $tracking
                && ($created <= 0 || intval($p[8] ?? 0) === $created)){
                return $preferred;
            }
        }
        foreach($payments as $i => $p){
            if(!is_array($p)) continue;
            if(strtolower(trim((string)($p[0] ?? ''))) === $user
                && instantPayNormalizeTracking($p[3] ?? '') === $tracking
                && ($created <= 0 || intval($p[8] ?? 0) === $created)){
                return $i;
            }
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

            $tracking = trim((string)($row[3] ?? ''));

            if(strpos($tracking, 'AUTO-') !== 0){
                continue;
            }

            $user = strtolower(trim((string)($row[0] ?? '')));
            $map[$user . '|' . $tracking . '|' . intval($row[8] ?? 0)] = $i;
        }

        $changed = false;

        foreach($items as $i => $item){
            $tracking = instantPayTrackingCode($item);
            $key = strtolower(trim((string)($item['user'] ?? ''))) . '|' . $tracking
                . '|' . intval($item['created_at'] ?? 0);
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

    function instantPayRejectCsvRowAtIndex($csvIndex, $reason){
        if(!function_exists('xuiLoadPayments') || !function_exists('xuiSavePayments')){
            return false;
        }

        $csvIndex = intval($csvIndex);
        $rows = xuiLoadPayments();

        if(!isset($rows[$csvIndex]) || !is_array($rows[$csvIndex])){
            return false;
        }

        if(!instantPayCsvRowPending($rows[$csvIndex])){
            return true;
        }

        $rows[$csvIndex][6] = 'رد شد';
        $rows[$csvIndex][7] = $reason;

        return xuiSavePayments($rows);
    }

    /**
     * Recover a checkout that never persisted its new JSON order.
     * Restore only prior waiting rows superseded by this checkout and reject
     * the newly appended CSV row. Never roll back approved financial records.
     * Call only while the create lock is held.
     */
    function instantPayRollbackFailedCreate(array $oldItems, array $oldCsv, $newCsvIndex, $newRow){
        $rows = xuiLoadPayments();
        $newCsvIndex = intval($newCsvIndex);
        if(!isset($rows[$newCsvIndex]) || !is_array($rows[$newCsvIndex])
            || trim((string)($rows[$newCsvIndex][3] ?? '')) !== trim((string)($newRow[3] ?? ''))
            || intval($rows[$newCsvIndex][8] ?? 0) !== intval($newRow[8] ?? 0)
            || trim((string)($rows[$newCsvIndex][0] ?? '')) !== trim((string)($newRow[0] ?? ''))){
            return false;
        }

        foreach($oldCsv as $i => $prior){
            if(!isset($rows[$i]) || !is_array($prior) || !is_array($rows[$i])){
                return false;
            }
            $current = $rows[$i];
            if(trim((string)($prior[0] ?? '')) !== trim((string)($current[0] ?? ''))
                || trim((string)($prior[3] ?? '')) !== trim((string)($current[3] ?? ''))
                || intval($prior[8] ?? 0) !== intval($current[8] ?? 0)){
                return false;
            }
            if(instantPayCsvRowPending($prior)
                && trim((string)($current[6] ?? '')) === 'رد شد'
                && trim((string)($current[7] ?? '')) === 'لغو به‌خاطر درخواست جدید'){
                $rows[$i][6] = $prior[6] ?? 'درحال بررسی';
                $rows[$i][7] = $prior[7] ?? '';
            }
        }

        if(instantPayCsvRowPending($rows[$newCsvIndex])){
            $rows[$newCsvIndex][6] = 'رد شد';
            $rows[$newCsvIndex][7] = 'ساخت سفارش ناموفق؛ نیازمند درخواست جدید';
        }
        if(!xuiSavePayments($rows)){
            return false;
        }

        // Undo only this checkout's cancellations in JSON, not unrelated
        // changes made by payment processing or other handlers.
        $oldMap = [];
        foreach($oldItems as $old){
            if(is_array($old) && isset($old['id'])){
                $oldMap[(string)$old['id']] = $old;
            }
        }
        $stored = instantPayLoad();
        foreach($stored as $i => $current){
            $id = (string)($current['id'] ?? '');
            if(!isset($oldMap[$id])){
                continue;
            }
            if(($oldMap[$id]['status'] ?? '') === 'waiting'
                && ($current['status'] ?? '') === 'cancelled'
                && ($current['message'] ?? '') === 'لغو به‌خاطر درخواست جدید'){
                $stored[$i] = $oldMap[$id];
            }
        }
        return instantPaySave($stored);
    }

    // Archive cancelled AUTO orders without deleting financial CSV rows (single write).
    function instantPayMarkManyCsvCancelled(array $items, $reason){
        if(!function_exists('xuiLoadPayments') || !function_exists('xuiSavePayments')){
            return false;
        }

        if(count($items) === 0){
            return true;
        }

        $rows = xuiLoadPayments();
        $changed = false;

        foreach($items as $item){
            if(!is_array($item)){
                return false;
            }

            $tracking = instantPayNormalizeTracking(instantPayTrackingCode($item));
            $username = strtolower(trim((string)($item['user'] ?? '')));
            $created = intval($item['created_at'] ?? 0);

            foreach($rows as $i => $row){
                if(!is_array($row)
                    || instantPayNormalizeTracking($row[3] ?? '') !== $tracking
                    || strtolower(trim((string)($row[0] ?? ''))) !== $username
                    || ($created > 0 && intval($row[8] ?? 0) !== $created)){
                    continue;
                }

                if(trim((string)($row[6] ?? '')) === 'تایید شد'){
                    return false;
                }

                if(instantPayCsvRowPending($row)){
                    $rows[$i][6] = 'رد شد';
                    $rows[$i][7] = $reason;
                    $changed = true;
                }
            }
        }

        return !$changed || xuiSavePayments($rows);
    }

    // Archive a cancelled AUTO order without deleting its financial CSV row.
    function instantPayMarkCsvCancelled($item, $reason){
        if(!is_array($item)){
            return false;
        }

        return instantPayMarkManyCsvCancelled([$item], $reason);
    }

    /**
     * Legacy callers must not delete accounting history either.
     * Kept for compatibility; returns 0 removed rows.
     */
    function instantPayDeleteAbandonedCsv($item){
        if(is_array($item)){
            instantPayMarkCsvCancelled($item, 'لغو به‌خاطر درخواست جدید');
        }
        return 0;
    }

    // Keep the legacy entrypoint but never purge financial records on admin page views.
    function instantPayPurgeStaleAdminRows($items = null){
        return instantPayExpireDue($items);
    }

    function instantPayAdminRowBucket($row){
        if(!is_array($row)) return 'active';
        $status = trim((string)($row[6] ?? ''));
        if($status === 'تایید شد') return 'active';
        $tracking = trim((string)($row[3] ?? ''));
        if(strpos($tracking, 'AUTO-') === 0){
            $item = instantPayFindJsonForCsvRow($row);
            if(is_array($item) && ($item['status'] ?? '') === 'cancelled'){
                return 'cancelled';
            }
        }
        $reason = trim((string)($row[7] ?? ''));
        return $status === 'رد شد' && strpos($reason, 'لغو') === 0 ? 'cancelled' : 'active';
    }

    // Read-only reconstruction of cancelled orders deleted from CSV by old code.
    // Synthetic rows have index=null and must never be passed to payment actions.
    function instantPayAdminCancelledHistory($payments, $type){
        $rows = [];
        $seen = [];
        $wanted = instantPayNormalizeType($type);
        foreach((array)$payments as $i => $row){
            if(!is_array($row) || instantPayNormalizeType($row[9] ?? '') !== $wanted) continue;
            $created = intval($row[8] ?? 0);
            $key = strtolower(trim((string)($row[0] ?? '')))
                . '|' . instantPayNormalizeTracking($row[3] ?? '') . '|' . $created;
            $seen[$key] = true;
            if(instantPayAdminRowBucket($row) === 'cancelled'){
                $rows[] = ['index'=>$i,'data'=>$row,'synthetic'=>false,'sort_timestamp'=>$created];
            }
        }
        foreach(instantPayLoad() as $item){
            if(!is_array($item) || ($item['status'] ?? '') !== 'cancelled'
                || instantPayNormalizeType($item['type'] ?? '') !== $wanted) continue;
            $created = intval($item['created_at'] ?? 0);
            $tracking = instantPayTrackingCode($item);
            $key = strtolower(trim((string)($item['user'] ?? '')))
                . '|' . instantPayNormalizeTracking($tracking) . '|' . $created;
            if(isset($seen[$key])) continue;
            $seen[$key] = true;
            $rows[] = [
                'index'=>null,'synthetic'=>true,'sort_timestamp'=>$created,
                'data'=>[
                    $item['user'] ?? '',
                    $wanted === 'تمدید' ? ($item['sub'] ?? '') : ($item['subname'] ?? ''),
                    $item['plan'] ?? '',$tracking,'','','رد شد',
                    $item['message'] ?? 'لغو شده',$created,$wanted,
                    $item['coupon_code'] ?? '',intval($item['discount_percent'] ?? 0),
                    intval($item['amount'] ?? 0),$item['code'] ?? ''
                ]
            ];
        }
        usort($rows, static function($a,$b){
            $byTime = $b['sort_timestamp'] <=> $a['sort_timestamp'];
            return $byTime !== 0 ? $byTime : (($b['index'] ?? -1) <=> ($a['index'] ?? -1));
        });
        return $rows;
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

        $item = instantPayFindJsonForCsvRow($row);

        if(is_array($item) && in_array((string)($item['status'] ?? ''), ['cancelled', 'failed'], true)){
            return false;
        }

        return true;
    }

    function instantPayAdminRowStatusMeta($row){
        $status = trim((string)($row[6] ?? ''));
        $tracking = trim((string)($row[3] ?? ''));

        if($status !== 'تایید شد' && instantPayAdminRowBucket($row) === 'cancelled'){
            return ['title' => 'کنسل شده', 'class' => 'statusDot--red'];
        }
        if(strpos($tracking, 'AUTO-') === 0){
            $item = instantPayFindJsonForCsvRow($row);
            if($status !== 'تایید شد' && is_array($item) && ($item['status'] ?? '') === 'expired'){
                return ['title' => 'منقضی شده', 'class' => 'statusDot--red'];
            }

            $item = instantPayFindJsonForCsvRow($row);

            if(is_array($item) && ($item['status'] ?? '') === 'payment_received_provision_failed'){
                return [
                    'title' => 'واریز تأیید شد — فعال‌سازی ناموفق',
                    'class' => 'statusDot--red',
                    'detail' => instantPayAdminProvisionFailureDetail($row, $item),
                ];
            }

            if(is_array($item) && ($item['status'] ?? '') === 'payment_received'){
                return ['title' => 'واریز تأیید شد', 'class' => 'statusDot--blue'];
            }
        }

        if($status === 'تایید شد'){
            return ['title' => 'تایید شد', 'class' => 'statusDot--green'];
        }

        if($status === 'رد شد'){
            return ['title' => 'رد شد', 'class' => 'statusDot--red'];
        }

        if(instantPayAdminRowIsInProgress($row)){
            if(strpos($tracking, 'AUTO-') === 0){
                $item = instantPayFindJsonForCsvRow($row);

                if(is_array($item) && ($item['status'] ?? '') === 'processing'){
                    return ['title' => 'در حال صدور', 'class' => 'statusDot--yellow'];
                }

                return ['title' => 'در حال بررسی', 'class' => 'statusDot--yellow'];
            }
        }

        return ['title' => 'در حال بررسی', 'class' => 'statusDot--yellow'];
    }

    function instantPayAdminProvisionFailureDetail($row, $item = null){
        if(!is_array($row)){
            return '';
        }

        $tracking = trim((string)($row[3] ?? ''));

        if(strpos($tracking, 'AUTO-') !== 0){
            return '';
        }

        if(!is_array($item)){
            $item = instantPayFindJsonForCsvRow($row);
        }

        if(!is_array($item) || ($item['status'] ?? '') !== 'payment_received_provision_failed'){
            return '';
        }

        $user = trim((string)($row[0] ?? ($item['user'] ?? '')));
        $sub = trim((string)($item['sub'] ?? ($row[1] ?? '')));
        $amountRial = intval($item['matched_amount'] ?? ($row[12] ?? 0));
        $amountText = $amountRial > 0 ? (number_format($amountRial) . ' ریال') : '—';
        $error = trim((string)($item['provision_error'] ?? ($item['message'] ?? '')));

        if($error === ''){
            $error = 'خطای نامشخص فعال‌سازی';
        }

        return 'کاربر: ' . ($user !== '' ? $user : '—')
            . ' | اشتراک: ' . ($sub !== '' ? $sub : '—')
            . ' | مبلغ: ' . $amountText
            . ' | خطا: ' . $error;
    }

    /**
     * آیا این ردیف CSV در لیست ادمین نمایش داده شود؟
     * سفارش AUTOِ درحال‌انجام تا پایان مهلت پرداخت + ۱۰ دقیقه دیده می‌شود.
     */
    function instantPayAdminRowVisible($row){
        // Admin tabs decide presentation; do not hide historic financial records.
        return is_array($row);
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
        $type = $type === null ? null : instantPayNormalizeType($type);
        $exceptId = $exceptId !== null ? trim((string)$exceptId) : null;

        if($items === null){
            $items = instantPayExpireDue();
        }

        $changed = false;
        $now = time();

        if(!function_exists('checkoutReleaseDiscountOrder')){
            require_once __DIR__ . '/campaign_lib.php';
        }

        $toCancel = [];

        foreach($items as $i => $item){
            if(trim((string)($item['user'] ?? '')) !== $username){
                continue;
            }

            if($type !== null && instantPayNormalizeType($item['type'] ?? 'خرید') !== $type){
                continue;
            }

            $id = (string)($item['id'] ?? '');

            if($exceptId !== null && $exceptId !== '' && $id === $exceptId){
                continue;
            }

            if(($item['status'] ?? '') !== 'waiting'){
                continue;
            }

            $toCancel[] = $i;
        }

        if(count($toCancel) === 0){
            return $items;
        }

        $csvItems = [];

        foreach($toCancel as $i){
            $csvItems[] = $items[$i];
        }

        if(!instantPayMarkManyCsvCancelled($csvItems, 'لغو به‌خاطر درخواست جدید')){
            return false;
        }

        foreach($toCancel as $i){
            $items[$i]['status'] = 'cancelled';
            $items[$i]['message'] = 'لغو به‌خاطر درخواست جدید';
            $items[$i]['cancelled_at'] = $now;
            $items[$i]['csv_purged'] = false;
            $changed = true;
            checkoutReleaseDiscountOrder($items[$i]['id'] ?? '');
        }

        if($changed && !instantPaySave($items)){
            return false;
        }

        if($changed){
            $items = instantPayRebuildCsvIndexes(instantPayLoad());
        }

        return $items;
    }

    function instantPayCloseUserMatchable($username, $exceptId = null, $items = null, $type = null){
        return instantPaySupersedeUserWaiting($username, $type, $exceptId, $items);
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
        $toCancel = [];

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

            $toCancel[] = $i;
        }

        if(count($toCancel) > 0){
            $csvItems = [];

            foreach($toCancel as $i){
                $csvItems[] = $items[$i];
            }

            if(!instantPayMarkManyCsvCancelled($csvItems, 'لغو توسط کاربر')){
                return false;
            }

            if(!function_exists('checkoutReleaseDiscountOrder')){
                require_once __DIR__ . '/pnv_campaign_bootstrap.php';
            }

            foreach($toCancel as $i){
                $items[$i]['status'] = 'cancelled';
                $items[$i]['message'] = 'لغو توسط کاربر';
                $items[$i]['cancelled_at'] = time();
                $items[$i]['csv_purged'] = false;
                $changed = true;
                checkoutReleaseDiscountOrder($items[$i]['id'] ?? '');
            }
        }

        if($changed){
            if(!instantPaySave($items)){
                return false;
            }

            $items = instantPayRebuildCsvIndexes(instantPayLoad());
        }

        return $items;
    }

    function instantPayApplyRenewProvisionMeta(array &$item, array $result){
        if(trim((string)($item['type'] ?? '')) !== 'تمدید'){
            return;
        }

        $message = trim((string)($result['user_renew_message'] ?? ''));

        if($message !== ''){
            $item['user_renew_message'] = $message;
        }

        $outcome = trim((string)($result['renew_outcome'] ?? ''));

        if($outcome !== ''){
            $item['renew_outcome'] = $outcome;
        }

        $reserveId = trim((string)($result['renew_reserve_id'] ?? ''));

        if($reserveId !== ''){
            $item['renew_reserve_id'] = $reserveId;
        }
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
            'message' => $item['message'] ?? '',
            'user_renew_message' => $item['user_renew_message'] ?? '',
            'renew_outcome' => $item['renew_outcome'] ?? '',
            'renew_reserve_id' => $item['renew_reserve_id'] ?? '',
            'payment_received_at' => intval($item['payment_received_at'] ?? 0),
            'provision_failed_at' => intval($item['provision_failed_at'] ?? 0),
            'provision_error' => $item['provision_error'] ?? '',
            'provision_code' => $item['provision_code'] ?? '',
            'matched_amount' => intval($item['matched_amount'] ?? 0),
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

        // Serialize all checkout creates before reading shared JSON and CSV.
        // A single file lock across users prevents lost updates and duplicate
        // CSV indexes from concurrent browser tabs / PHP-FPM workers.
        $lockDir = dirname(instantPayPath()) . '/locks';
        if(!is_dir($lockDir) && !@mkdir($lockDir, 0750, true) && !is_dir($lockDir)){
            return ['ok' => false, 'error' => 'قفل سفارش قابل ایجاد نیست', 'code' => 'lock_failed'];
        }
        $lockHandle = @fopen($lockDir . '/instant_pay_create.lock', 'c');
        if(!$lockHandle){
            return ['ok' => false, 'error' => 'قفل سفارش در دسترس نیست', 'code' => 'lock_failed'];
        }
        if(!flock($lockHandle, LOCK_EX)){
            fclose($lockHandle);
            return ['ok' => false, 'error' => 'قفل سفارش برقرار نشد', 'code' => 'lock_failed'];
        }

        try {
            // Test-only delay inside the lock to force deterministic contention.
            if(!empty($GLOBALS['instantPayTestCreateLockHook']) &&
               is_callable($GLOBALS['instantPayTestCreateLockHook'])){
                $GLOBALS['instantPayTestCreateLockHook']();
            }
            // IMPORTANT: refresh the shared state *after* acquiring the lock.
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

        // A successful new checkout replaces all prior waiting checkouts.
        // Simply navigating away must not cancel the previous checkout.
        $code = instantPayAllocateCode($items);
        if($code === null){
            return ['ok'=>false,'error'=>'کد یکتا در دسترس نیست؛ کمی بعد دوباره تلاش کنید'];
        }

        $newOrderId = instantPayNewId();
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
        $oldPayments = $payments;
        $oldItems = $items;
        $payments[] = $row;
        $csvIndex = count($payments) - 1;

        if(!xuiSavePayments($payments)){
            return ['ok' => false, 'error' => 'ذخیره سفارش در فایل پرداخت ناموفق بود'];
        }

        $items = instantPaySupersedeUserWaiting($username, null, $newOrderId, $items);

        if(!is_array($items)){
            $recovered = instantPayRollbackFailedCreate($oldItems, $oldPayments, $csvIndex, $row);
            return [
                'ok' => false,
                'error' => $recovered
                    ? 'بایگانی سفارش قبلی ناموفق بود؛ سفارش قبلی محفوظ است'
                    : 'بایگانی ناموفق و بازیابی خودکار ناقص است؛ با پشتیبانی تماس بگیرید',
                'code' => $recovered ? 'create_failed' : 'manual_recovery_required',
            ];
        }

        $item = [
            'id' => $newOrderId,
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

        if(!instantPaySave($items)){
            $recovered = instantPayRollbackFailedCreate($oldItems, $oldPayments, $csvIndex, $row);
            return [
                'ok' => false,
                'error' => $recovered
                    ? 'ذخیره سفارش جدید ناموفق بود؛ سفارش قبلی محفوظ است'
                    : 'ذخیره سفارش ناموفق و بازیابی خودکار ناقص است؛ با پشتیبانی تماس بگیرید',
                'code' => $recovered ? 'persist_failed' : 'manual_recovery_required',
            ];
        }

        // اطلاع تلگرام فقط بعد از تأیید پرداخت ارسال می‌شود (نه در شروع مهلت)

        return [
            'ok' => true,
            'item' => instantPayPublicView($item)
        ];
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
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
            return ['ok' => true, 'already' => true, 'item' => instantPayPublicView($found)];
        }

        if(($found['status'] ?? '') === 'payment_received_provision_failed'){
            if(!$force){
                return [
                    'ok' => false,
                    'error' => 'واریز ثبت شده است؛ برای تلاش مجدد فعال‌سازی از مسیر پشتیبانی/ادمین استفاده کنید',
                    'code' => 'provision_retry_required',
                ];
            }
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

        $csvIndex = instantPayResolveCsvIndex($found);

        if($csvIndex < 0){
            return ['ok' => false, 'error' => 'ایندکس پرداخت نامعتبر است'];
        }
        $items[$idx]['csv_index'] = $csvIndex;

        // تاریخ/ساعت را برای لاگ پر می‌کنیم
        $payments = xuiLoadPayments();

        if(isset($payments[$csvIndex])){
            $nowParts = pnvNowParts();
            $payments[$csvIndex][4] = $meta['date'] ?? $nowParts['date'];
            $payments[$csvIndex][5] = $meta['time'] ?? $nowParts['time'];
            xuiSavePayments($payments);
        }

        $matchedAmount = intval($meta['amount'] ?? 0);
        $items[$idx]['status'] = 'payment_received';
        $items[$idx]['payment_received_at'] = time();
        $items[$idx]['matched_amount'] = $matchedAmount;
        $items[$idx]['matched_text'] = substr((string)($meta['text'] ?? ''), 0, 500);
        $items[$idx]['message'] = 'واریز تأیید شد؛ در حال فعال‌سازی اشتراک…';
        instantPaySave($items);

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
            $provisionCode = trim((string)($result['code'] ?? ''));

            if($provisionCode === 'persist_failed' && !empty($result['recoverable'])){
                $items[$idx]['status'] = 'payment_received';
                $items[$idx]['message'] = 'واریز ثبت شد؛ ذخیره نهایی سفارش ناقص بود و با تلاش مجدد تکمیل می‌شود';
                $items[$idx]['provision_code'] = $provisionCode;
                $items[$idx]['provision_error'] = $result['error'] ?? '';
                instantPaySave($items);

                return $result;
            }

            $items[$idx]['status'] = 'payment_received_provision_failed';
            $items[$idx]['provision_failed_at'] = time();
            $items[$idx]['provision_error'] = $result['error'] ?? 'فعال‌سازی اشتراک ناموفق بود';
            $items[$idx]['provision_code'] = $provisionCode;
            $items[$idx]['message'] = 'واریز شما ثبت شد اما فعال‌سازی اشتراک انجام نشد. نیازی به پرداخت مجدد نیست.';
            if($matchedAmount <= 0){
                $items[$idx]['matched_amount'] = intval($items[$idx]['matched_amount'] ?? 0);
            }
            instantPaySave($items);

            return $result;
        }

        // فقط وقتی کانفیگ آماده است «paid» می‌شود
        $items[$idx]['status'] = 'paid';
        $items[$idx]['paid_at'] = time();
        $items[$idx]['link'] = $result['link'] ?? ($found['sub'] ?? '');
        $items[$idx]['message'] = 'پرداخت تأیید شد';
        instantPayApplyRenewProvisionMeta($items[$idx], is_array($result) ? $result : []);
        $items[$idx]['matched_amount'] = intval($meta['amount'] ?? 0);
        $items[$idx]['matched_text'] = substr((string)($meta['text'] ?? ''), 0, 500);
        instantPaySave($items);

        if(!empty($found['coupon_code']) || !empty($found['discount_source'])){
            if(!function_exists('checkoutMarkDiscountPaid')){
                require_once __DIR__ . '/pnv_campaign_bootstrap.php';
            }

            checkoutMarkDiscountPaid($found);
        }

        // اطلاع‌رسانی تلگرام فقط بعد از تأیید نهایی
        if(function_exists('telegramNotifyNewPayment')){
            try{
                $payments = xuiLoadPayments();
                $notifyRow = $payments[$csvIndex] ?? null;

                if(!is_array($notifyRow)){
                    $nowParts = pnvNowParts();
                    $notifyRow = [
                        $found['user'] ?? '',
                        ($found['type'] ?? '') === 'تمدید' ? ($found['sub'] ?? '') : ($found['subname'] ?? ''),
                        $found['plan'] ?? '',
                        'AUTO-' . ($found['code'] ?? ''),
                        $meta['date'] ?? $nowParts['date'],
                        $meta['time'] ?? $nowParts['time'],
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

        if(!instantPayCsvRowPending($row)){
            $status = trim((string)($row[6] ?? ''));

            if($status === 'تایید شد'){
                $link = trim((string)($row[7] ?? ''));
                return [
                    'ok' => true,
                    'already' => true,
                    'item' => [
                        'status' => 'paid',
                        'ready' => true,
                        'link' => $link,
                        'amount_text' => number_format(instantPayCsvRowAmountRial($row)) . ' ریال',
                        'plan' => trim((string)($row[2] ?? '')),
                        'code' => preg_replace('/^AUTO-/i', '', trim((string)($row[3] ?? ''))),
                    ],
                ];
            }

            return ['ok' => false, 'error' => 'رد CSV قابل تأیید نیست (' . $status . ')'];
        }

        $user = trim((string)($row[0] ?? ''));
        $tracking = trim((string)($row[3] ?? ''));
        $jsonItem = instantPayFindJsonForCsvRow($row);

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

        $result = xuiApprovePaymentIndex($csvIndex, trim((string)($row[9] ?? 'خرید')));

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

        if(function_exists('telegramNotifyNewPayment')){
            try{
                $payments = xuiLoadPayments();
                $notifyRow = $payments[$csvIndex] ?? $row;
                telegramNotifyNewPayment($paidItem['type'], $notifyRow, ['confirmed' => true]);
            }catch(Throwable $e){
                error_log('instant pay csv telegram notify failed: ' . $e->getMessage());
            }
        }

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

            if(!empty($result['ok'])){
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

        foreach($candidates as $amount){
            $result = instantPayTryDepositAmount($amount, $text, [
                'date' => $meta['date'] ?? '',
                'time' => $meta['time'] ?? '',
            ]);

            if(!is_array($result)){
                continue;
            }

            $result['parsed_amounts'] = $amounts;
            return $result;
        }

        return [
            'ok' => false,
            'error' => instantPayDepositNoMatchError($amounts, $candidates),
            'amounts' => $amounts,
            'candidates' => $candidates,
            'debug' => instantPayMatchDebugSnapshot($amounts),
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

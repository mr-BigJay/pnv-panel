<?php

/**
 * نمایش/فیلتر لیست خرید و تمدید ادمین (بدون منطق پرداخت یا 3x-ui).
 */

if(!function_exists('instantPayAdminListStatusKeys')){

    function instantPayAdminListStatusKeys(){
        return ['approved', 'pending', 'expired', 'rejected', 'cancelled'];
    }

    function instantPayAdminListStatusLabels(){
        return [
            'approved' => 'تایید شده',
            'pending' => 'درحال انتظار',
            'expired' => 'منقضی',
            'rejected' => 'رد شده',
            'cancelled' => 'کنسل شده',
        ];
    }

    function instantPayNormalizeAdminListStatus($status){
        $status = trim((string)$status);
        $allowed = instantPayAdminListStatusKeys();

        return in_array($status, $allowed, true) ? $status : 'approved';
    }

    /** Tracking codes may be reused; bind CSV row to JSON by created_at. */
    function instantPayFindJsonForCsvRow($row, $items = null){
        if(!is_array($row)){
            return null;
        }

        $user = strtolower(trim((string)($row[0] ?? '')));
        $tracking = instantPayNormalizeTracking($row[3] ?? '');
        $created = intval($row[8] ?? 0);

        if($user === '' || $tracking === ''){
            return null;
        }

        if($items === null){
            $items = instantPayLoad();
        }

        foreach($items as $item){
            if(!is_array($item)){
                continue;
            }

            if(strtolower(trim((string)($item['user'] ?? ''))) !== $user){
                continue;
            }

            if(instantPayNormalizeTracking(instantPayTrackingCode($item)) !== $tracking){
                continue;
            }

            if($created > 0 && intval($item['created_at'] ?? 0) !== $created){
                continue;
            }

            return $item;
        }

        return null;
    }

    function instantPayAdminRowBucket($row){
        if(!is_array($row)){
            return 'active';
        }

        $status = trim((string)($row[6] ?? ''));

        if($status === 'تایید شد'){
            return 'active';
        }

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

    /**
     * بازسازی read-only سفارش‌های کنسل حذف‌شده از CSV (رد synthetic بدون index).
     */
    function instantPayAdminCancelledHistory($payments, $type){
        $rows = [];
        $seen = [];
        $wanted = instantPayNormalizeType($type);

        foreach((array)$payments as $i => $row){
            if(!is_array($row) || instantPayNormalizeType($row[9] ?? '') !== $wanted){
                continue;
            }

            $created = intval($row[8] ?? 0);
            $key = strtolower(trim((string)($row[0] ?? '')))
                . '|' . instantPayNormalizeTracking($row[3] ?? '')
                . '|' . $created;
            $seen[$key] = true;

            if(instantPayAdminRowBucket($row) === 'cancelled'){
                $rows[] = [
                    'index' => $i,
                    'data' => $row,
                    'synthetic' => false,
                    'sort_timestamp' => $created,
                ];
            }
        }

        foreach(instantPayLoad() as $item){
            if(!is_array($item) || ($item['status'] ?? '') !== 'cancelled'){
                continue;
            }

            if(instantPayNormalizeType($item['type'] ?? '') !== $wanted){
                continue;
            }

            $created = intval($item['created_at'] ?? 0);
            $tracking = instantPayTrackingCode($item);
            $key = strtolower(trim((string)($item['user'] ?? '')))
                . '|' . instantPayNormalizeTracking($tracking)
                . '|' . $created;

            if(isset($seen[$key])){
                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'index' => null,
                'synthetic' => true,
                'sort_timestamp' => $created,
                'data' => [
                    $item['user'] ?? '',
                    $wanted === 'تمدید' ? ($item['sub'] ?? '') : ($item['subname'] ?? ''),
                    $item['plan'] ?? '',
                    $tracking,
                    '',
                    '',
                    'رد شد',
                    $item['message'] ?? 'لغو شده',
                    $created,
                    $wanted,
                    $item['coupon_code'] ?? '',
                    intval($item['discount_percent'] ?? 0),
                    intval($item['amount'] ?? 0),
                    $item['code'] ?? '',
                ],
            ];
        }

        usort($rows, static function($a, $b){
            $byTime = ($b['sort_timestamp'] ?? 0) <=> ($a['sort_timestamp'] ?? 0);

            return $byTime !== 0 ? $byTime : (($b['index'] ?? -1) <=> ($a['index'] ?? -1));
        });

        return $rows;
    }

    function instantPayAdminRowListBucket($row){
        if(!is_array($row)){
            return 'pending';
        }

        $status = trim((string)($row[6] ?? ''));

        if($status === 'تایید شد'){
            return 'approved';
        }

        $tracking = trim((string)($row[3] ?? ''));
        $isAuto = strpos($tracking, 'AUTO-') === 0;
        $item = $isAuto ? instantPayFindJsonForCsvRow($row) : null;
        $jsonStatus = is_array($item) ? strtolower(trim((string)($item['status'] ?? ''))) : '';
        $reason = trim((string)($row[7] ?? ''));

        if($jsonStatus === 'cancelled' || ($status === 'رد شد' && strpos($reason, 'لغو') === 0)){
            return 'cancelled';
        }

        if($status === 'رد شد'){
            return 'rejected';
        }

        if($jsonStatus === 'failed'){
            return 'rejected';
        }

        if($isAuto){
            $now = time();
            $expiresAt = is_array($item) ? intval($item['expires_at'] ?? 0) : 0;

            if($jsonStatus === 'expired'){
                return 'expired';
            }

            if($expiresAt > 0 && $expiresAt < $now){
                return 'expired';
            }

            if(in_array($jsonStatus, ['waiting', 'processing'], true) && $expiresAt > $now){
                return 'pending';
            }

            if(function_exists('instantPayCsvRowPending')
                && function_exists('instantPayAdminRowIsInProgress')
                && instantPayCsvRowPending($row)
                && instantPayAdminRowIsInProgress($row)){
                return 'pending';
            }

            if(function_exists('instantPayAdminRowIsInProgress') && !instantPayAdminRowIsInProgress($row)){
                return 'expired';
            }
        }

        if(in_array($status, ['', 'درحال بررسی', 'در حال بررسی'], true)){
            return 'pending';
        }

        return 'pending';
    }

    function instantPayAdminRowVisibleForTabs($row){
        return is_array($row);
    }

    function instantPayAdminPaymentRowIsActionable($row){
        if(!is_array($row)){
            return false;
        }

        return instantPayAdminRowBucket($row) !== 'cancelled';
    }
}

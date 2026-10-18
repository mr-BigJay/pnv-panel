<?php

/**
 * Smart renew: reserved plan queue + activation (FIFO per subscription).
 */

if(!function_exists('renewReservePath')){

    function renewReservePath(){
        $override = getenv('PNV_RENEW_RESERVE_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/renew_reservations.json';
    }

    function renewReserveNow(){
        if(isset($GLOBALS['renewReserveTestNow'])){
            return max(0, intval($GLOBALS['renewReserveTestNow']));
        }

        return time();
    }

    function renewReserveNowMs(){
        return renewReserveNow() * 1000;
    }

    function renewReserveLoad(){
        $path = renewReservePath();

        if(!is_file($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    function renewReserveSave(array $items){
        $path = renewReservePath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function renewReserveQueueKey($username, $host, $subId){
        return hash('sha256', strtolower(trim((string)$username)) . '|' . strtolower(trim((string)$host)) . '|' . trim((string)$subId));
    }

    function renewReserveMakeId(){
        return 'rsv_' . bin2hex(random_bytes(8));
    }

    function renewReserveFindByProvisionKey($provisionKey){
        $provisionKey = trim((string)$provisionKey);

        if($provisionKey === ''){
            return null;
        }

        foreach(renewReserveLoad() as $item){
            if(trim((string)($item['provision_key'] ?? '')) === $provisionKey){
                return $item;
            }
        }

        return null;
    }

    function renewReserveListForUser($username, $statuses = null){
        $username = strtolower(trim((string)$username));
        $out = [];

        foreach(renewReserveLoad() as $item){
            if(strtolower(trim((string)($item['username'] ?? ''))) !== $username){
                continue;
            }

            $st = trim((string)($item['status'] ?? ''));

            if(is_array($statuses) && count($statuses) > 0 && !in_array($st, $statuses, true)){
                continue;
            }

            $out[] = $item;
        }

        usort($out, static function($a, $b){
            return intval($a['reserved_at'] ?? 0) <=> intval($b['reserved_at'] ?? 0);
        });

        return $out;
    }

    function renewReserveFindForRow($row){
        if(!is_array($row)){
            return null;
        }

        if(!function_exists('xuiPaymentRowProvisionKey')){
            return null;
        }

        $key = xuiPaymentRowProvisionKey($row);

        return renewReserveFindByProvisionKey($key);
    }

    function renewReserveAdminDetailForRow($row){
        $item = renewReserveFindForRow($row);

        if(!is_array($item)){
            return '';
        }

        $st = trim((string)($item['status'] ?? ''));
        $plan = trim((string)($item['plan_text'] ?? ''));
        $gb = intval($item['plan_gb'] ?? 0);
        $days = intval($item['plan_days'] ?? 0);
        $reservedAt = intval($item['reserved_at'] ?? 0);
        $activatedAt = intval($item['activated_at'] ?? 0);
        $err = trim((string)($item['last_error'] ?? ''));

        $parts = [
            'رزرو: ' . $st,
            'پلن رزرو: ' . ($plan !== '' ? $plan : ($gb . 'GB / ' . $days . 'd')),
        ];

        if($reservedAt > 0){
            $parts[] = 'خرید: ' . date('Y-m-d H:i', $reservedAt);
        }

        if($activatedAt > 0){
            $parts[] = 'فعال‌سازی: ' . date('Y-m-d H:i', $activatedAt);
        }

        if($err !== '' && in_array($st, ['activation_failed', 'ambiguous'], true)){
            $parts[] = 'خطا: ' . $err;
        }

        return implode(' | ', $parts);
    }

    /**
     * Runtime state from XUI client + subscription header (time vs volume).
     */
    function xuiRenewAnalyzeRuntimeState($server, $client, $subLink){
        if(!is_array($client)){
            return ['ambiguous' => true, 'error' => 'کلاینت نامعتبر است'];
        }

        if(!empty($GLOBALS['xuiTestRenewRuntimeState']) && is_array($GLOBALS['xuiTestRenewRuntimeState'])){
            return array_merge(['ambiguous' => false], $GLOBALS['xuiTestRenewRuntimeState']);
        }

        $nowMs = renewReserveNowMs();
        $expiryMs = function_exists('xuiClientExpiryMs') ? xuiClientExpiryMs($client) : 0;
        $totalBytes = function_exists('xuiClientTotalBytes') ? xuiClientTotalBytes($client) : 0;
        $usedBytes = function_exists('xuiClientUsedBytes') ? xuiClientUsedBytes($client) : 0;
        $remainingBytes = max(0, $totalBytes - $usedBytes);

        $headerExpire = function_exists('xuiFetchSubUserinfoExpire') ? xuiFetchSubUserinfoExpire($subLink) : null;

        $timeUnlimited = null;
        $timeRemainingSec = null;
        $timeExpired = false;

        if($expiryMs > 0){
            $timeUnlimited = false;
            $timeRemainingSec = max(0, intdiv($expiryMs - $nowMs, 1000));
            $timeExpired = $timeRemainingSec <= 0;
        }
        else{
            if($headerExpire === null){
                $timeUnlimited = null;
            }
            elseif(intval($headerExpire) === 0){
                $timeUnlimited = true;
            }
            else{
                $timeUnlimited = false;
                $timeRemainingSec = max(0, intval($headerExpire) - renewReserveNow());
                $timeExpired = $timeRemainingSec <= 0;
            }
        }

        $volumeExhausted = $totalBytes > 0 && $remainingBytes <= 0;
        $shouldActivateNow = $timeExpired || $volumeExhausted;

        $ambiguous = ($timeUnlimited === null && !$timeExpired && !$volumeExhausted);

        return [
            'ambiguous' => $ambiguous,
            'time_unlimited' => $timeUnlimited,
            'time_remaining_sec' => $timeRemainingSec,
            'time_expired' => $timeExpired,
            'volume_remaining_bytes' => $remainingBytes,
            'volume_exhausted' => $volumeExhausted,
            'should_activate_now' => $shouldActivateNow,
        ];
    }

    function renewReserveDecideAction(array $runtime){
        if(!empty($runtime['ambiguous'])){
            return 'ambiguous';
        }

        if(!empty($runtime['should_activate_now'])){
            return 'activate_now';
        }

        if($runtime['time_unlimited'] === true){
            return 'immediate_unlimited';
        }

        return 'reserve';
    }

    function renewReserveEnqueue(array $entry){
        $items = renewReserveLoad();
        $provisionKey = trim((string)($entry['provision_key'] ?? ''));

        if($provisionKey !== ''){
            foreach($items as $existing){
                if(trim((string)($existing['provision_key'] ?? '')) === $provisionKey){
                    return $existing;
                }
            }
        }

        $entry['id'] = trim((string)($entry['id'] ?? ''));

        if($entry['id'] === ''){
            $entry['id'] = renewReserveMakeId();
        }

        $entry['status'] = trim((string)($entry['status'] ?? 'reserved'));
        $entry['reserved_at'] = intval($entry['reserved_at'] ?? renewReserveNow());
        $entry['queue_key'] = trim((string)($entry['queue_key'] ?? ''));
        $items[] = $entry;
        renewReserveSave($items);

        return $entry;
    }

    function renewReserveUpdateById($id, array $patch){
        $id = trim((string)$id);
        $items = renewReserveLoad();
        $found = null;

        foreach($items as $i => $item){
            if(trim((string)($item['id'] ?? '')) !== $id){
                continue;
            }

            $items[$i] = array_merge($item, $patch);
            $found = $items[$i];
            break;
        }

        if($found === null){
            return null;
        }

        renewReserveSave($items);

        return $found;
    }

    function renewReservePendingOrdered(){
        $items = renewReserveLoad();
        $pending = [];

        foreach($items as $item){
            $st = trim((string)($item['status'] ?? ''));

            if($st === 'reserved'){
                $pending[] = $item;
            }
        }

        usort($pending, static function($a, $b){
            $qk = strcmp((string)($a['queue_key'] ?? ''), (string)($b['queue_key'] ?? ''));

            if($qk !== 0){
                return $qk;
            }

            return intval($a['reserved_at'] ?? 0) <=> intval($b['reserved_at'] ?? 0);
        });

        $seen = [];

        foreach($pending as $i => $item){
            $key = (string)($item['queue_key'] ?? '');

            if($key !== '' && isset($seen[$key])){
                unset($pending[$i]);
                continue;
            }

            if($key !== ''){
                $seen[$key] = true;
            }
        }

        return array_values($pending);
    }

    function renewReserveUserMessageReserved(){
        return 'پرداخت تأیید شد. پلن جدید پس از اتمام حجم یا زمان پلن فعلی به‌صورت خودکار فعال می‌شود.';
    }

    function renewReserveUserMessageActivated(){
        return 'پلن جدید شما فعال شد.';
    }

    function renewReserveCreateFromPayment(array $paymentRow, array $parsed, $server, $gb, $days, $planText){
        $username = trim((string)($paymentRow[0] ?? ''));
        $subLink = function_exists('xuiBuildSubLink')
            ? xuiBuildSubLink($parsed['host'], $parsed['sub_id'], function_exists('xuiLoadConfig') ? xuiLoadConfig() : [])
            : trim((string)($paymentRow[1] ?? ''));

        return renewReserveEnqueue([
            'provision_key' => function_exists('xuiPaymentRowProvisionKey') ? xuiPaymentRowProvisionKey($paymentRow) : '',
            'transaction_id' => function_exists('xuiPaymentRowTransactionId') ? xuiPaymentRowTransactionId($paymentRow) : '',
            'payment_tracking' => trim((string)($paymentRow[3] ?? '')),
            'username' => $username,
            'sub_id' => trim((string)($parsed['sub_id'] ?? '')),
            'sub_link' => $subLink,
            'server_id' => trim((string)($server['id'] ?? '')),
            'server_host' => trim((string)($parsed['host'] ?? '')),
            'plan_text' => trim((string)$planText),
            'plan_gb' => intval($gb),
            'plan_days' => intval($days),
            'queue_key' => renewReserveQueueKey($username, $parsed['host'] ?? '', $parsed['sub_id'] ?? ''),
            'status' => 'reserved',
            'reserved_at' => renewReserveNow(),
            'last_check_at' => 0,
            'last_check_note' => '',
            'activation_op_id' => '',
        ]);
    }

    function renewReserveBuildPaymentSuccess(array $reservation, $subLink){
        return [
            'ok' => true,
            'link' => $subLink,
            'sub_id' => trim((string)($reservation['sub_id'] ?? '')),
            'server_id' => trim((string)($reservation['server_id'] ?? '')),
            'gb' => intval($reservation['plan_gb'] ?? 0),
            'renew_outcome' => 'reserved',
            'renew_reserve_id' => trim((string)($reservation['id'] ?? '')),
            'user_renew_message' => renewReserveUserMessageReserved(),
            'method' => 'renew_reserve',
        ];
    }

    function renewReserveActivateOne(array $reservation){
        if(!function_exists('xuiLoadConfig')){
            require_once __DIR__ . '/xui_lib.php';
        }

        $config = xuiLoadConfig();
        $id = trim((string)($reservation['id'] ?? ''));

        if($id === ''){
            return ['ok' => false, 'error' => 'رزرو نامعتبر است'];
        }

        $lockPath = (function_exists('xuiLocksDir') ? xuiLocksDir() : (__DIR__ . '/db/locks')) . '/renew_reserve_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $id) . '.lock';
        $fp = fopen($lockPath, 'c');

        if($fp === false || !flock($fp, LOCK_EX | LOCK_NB)){
            if(is_resource($fp)){
                fclose($fp);
            }

            return ['ok' => false, 'skipped' => true, 'reason' => 'locked'];
        }

        try{
            $fresh = null;

            foreach(renewReserveLoad() as $item){
                if(trim((string)($item['id'] ?? '')) === $id){
                    $fresh = $item;
                    break;
                }
            }

            if(!is_array($fresh)){
                return ['ok' => false, 'error' => 'رزرو پیدا نشد'];
            }

            $st = trim((string)($fresh['status'] ?? ''));

            if($st === 'activated'){
                return ['ok' => true, 'already' => true];
            }

            if(in_array($st, ['ambiguous', 'activation_failed'], true)){
                return ['ok' => false, 'error' => 'رزرو نیاز به بررسی دستی دارد', 'code' => 'manual_review'];
            }

            if($st === 'activating'){
                $started = intval($fresh['activating_at'] ?? 0);

                if($started > 0 && (renewReserveNow() - $started) < 300){
                    return ['ok' => false, 'skipped' => true, 'reason' => 'activating'];
                }

                renewReserveUpdateById($id, [
                    'status' => 'ambiguous',
                    'last_error' => 'فعال‌سازی قبلی نامشخص ماند',
                ]);

                return ['ok' => false, 'code' => 'ambiguous', 'error' => 'فعال‌سازی نامشخص'];
            }

            $host = trim((string)($fresh['server_host'] ?? ''));
            $subId = trim((string)($fresh['sub_id'] ?? ''));
            $subLink = trim((string)($fresh['sub_link'] ?? ''));
            $server = xuiFindServerByHost($host, $config);

            if(!$server){
                renewReserveUpdateById($id, [
                    'status' => 'activation_failed',
                    'last_error' => 'سرور XUI پیدا نشد',
                    'last_check_at' => renewReserveNow(),
                ]);

                return ['ok' => false, 'error' => 'سرور پیدا نشد'];
            }

            $lookup = xuiRenewLookupClient($server, $subId, $subLink);

            if(($lookup['status'] ?? '') === 'api_error'){
                renewReserveUpdateById($id, [
                    'last_check_at' => renewReserveNow(),
                    'last_check_note' => 'api_unreachable',
                ]);

                return ['ok' => false, 'error' => 'XUI در دسترس نیست', 'code' => 'api_error'];
            }

            if(($lookup['status'] ?? '') === 'not_found'){
                renewReserveUpdateById($id, [
                    'status' => 'ambiguous',
                    'last_error' => 'کلاینت قبل از فعال‌سازی حذف شده است',
                    'last_check_at' => renewReserveNow(),
                ]);

                return ['ok' => false, 'code' => 'ambiguous', 'error' => 'کلاینت حذف شده'];
            }

            $client = $lookup['client'] ?? null;
            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);

            if(!empty($runtime['ambiguous'])){
                renewReserveUpdateById($id, [
                    'status' => 'ambiguous',
                    'last_error' => 'وضعیت اشتراک نامشخص است',
                    'last_check_at' => renewReserveNow(),
                ]);

                return ['ok' => false, 'code' => 'ambiguous'];
            }

            if(empty($runtime['should_activate_now'])){
                renewReserveUpdateById($id, [
                    'last_check_at' => renewReserveNow(),
                    'last_check_note' => 'not_due',
                ]);

                return ['ok' => false, 'skipped' => true, 'reason' => 'not_due'];
            }

            $opId = hash('sha256', $id . '|activate|' . renewReserveNow());
            renewReserveUpdateById($id, [
                'status' => 'activating',
                'activating_at' => renewReserveNow(),
                'activation_op_id' => $opId,
            ]);

            $gb = intval($fresh['plan_gb'] ?? 0);
            $days = intval($fresh['plan_days'] ?? 0);
            $email = trim((string)($client['email'] ?? ''));
            $applied = xuiApplyRenewPlanAbsolute($server, $client, $gb, $days);

            if(empty($applied['ok'])){
                renewReserveUpdateById($id, [
                    'status' => 'activation_failed',
                    'last_error' => $applied['error'] ?? 'فعال‌سازی ناموفق',
                    'last_check_at' => renewReserveNow(),
                ]);

                return $applied;
            }

            renewReserveUpdateById($id, [
                'status' => 'activated',
                'activated_at' => renewReserveNow(),
                'last_check_at' => renewReserveNow(),
                'last_check_note' => 'ok',
                'activation_op_id' => $opId,
            ]);

            return [
                'ok' => true,
                'reserve_id' => $id,
                'method' => $applied['method'] ?? '',
            ];
        }
        finally{
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    function renewReserveProcessQueue($limit = 20){
        $limit = max(1, intval($limit));
        $processed = 0;
        $summary = ['activated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach(renewReservePendingOrdered() as $reservation){
            if($processed >= $limit){
                break;
            }

            $processed++;
            $result = renewReserveActivateOne($reservation);

            if(!empty($result['ok'])){
                $summary['activated']++;
            }
            elseif(!empty($result['skipped'])){
                $summary['skipped']++;
            }
            else{
                $summary['failed']++;
            }
        }

        return $summary;
    }
}

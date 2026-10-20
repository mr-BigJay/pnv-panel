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

    function renewReserveActivatingGraceSeconds(){
        if(isset($GLOBALS['renewReserveTestActivatingGrace'])){
            return max(1, intval($GLOBALS['renewReserveTestActivatingGrace']));
        }

        return 180;
    }

    function renewReserveQueueLockPath(){
        $dir = function_exists('xuiLocksDir') ? xuiLocksDir() : (__DIR__ . '/db/locks');

        return rtrim($dir, '/') . '/renew_reservations_queue.lock';
    }

    function renewReserveWithQueueLock(callable $callback){
        $lockPath = renewReserveQueueLockPath();
        $dir = dirname($lockPath);

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        $fp = fopen($lockPath, 'c');

        if($fp === false || !flock($fp, LOCK_EX)){
            if(is_resource($fp)){
                fclose($fp);
            }

            return ['ok' => false, 'error' => 'queue_lock_failed'];
        }

        try{
            return $callback();
        }
        finally{
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    function renewReserveLoadResult(){
        $path = renewReservePath();

        if(!is_file($path)){
            return ['ok' => true, 'items' => []];
        }

        $raw = (string)file_get_contents($path);
        $data = json_decode($raw, true);

        if(json_last_error() !== JSON_ERROR_NONE || !is_array($data)){
            return [
                'ok' => false,
                'error' => 'corrupt_json',
                'items' => null,
            ];
        }

        return ['ok' => true, 'items' => $data];
    }

    function renewReserveLoad(){
        $result = renewReserveLoadResult();

        if(empty($result['ok'])){
            return null;
        }

        return is_array($result['items']) ? $result['items'] : [];
    }

    function renewReserveSave(array $items){
        if(
            !empty($GLOBALS['renewReserveTestSaveFail'])
            && is_callable($GLOBALS['renewReserveTestSaveFail'])
        ){
            $allow = $GLOBALS['renewReserveTestSaveFail']($items);

            if($allow === false){
                return false;
            }
        }

        $path = renewReservePath();
        $dir = dirname($path);

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($json === false){
            return false;
        }

        $tmp = $path . '.tmp.' . getmypid();
        $written = file_put_contents($tmp, $json, LOCK_EX);

        if($written === false || $written < strlen($json)){
            @unlink($tmp);

            return false;
        }

        if(!@rename($tmp, $path)){
            @unlink($tmp);

            return false;
        }

        return true;
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

        $items = renewReserveLoad();

        if(!is_array($items)){
            return null;
        }

        foreach($items as $item){
            if(trim((string)($item['provision_key'] ?? '')) === $provisionKey){
                return $item;
            }
        }

        return null;
    }

    function renewReserveListForUser($username, $statuses = null){
        $username = strtolower(trim((string)$username));
        $out = [];

        $items = renewReserveLoad();

        if(!is_array($items)){
            return [];
        }

        foreach($items as $item){
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

    /**
     * Choose by the PURCHASED plan duration, not the existing client's expiry.
     * days=0: add purchased GB to the existing cap immediately (no queue).
     * days>0: reserve until the current subscription expires OR exhausts quota.
     * Keep the optional argument for older callers that only pass runtime.
     */
    function renewReserveDecideAction(array $runtime, $purchasedDays = null){
        if($purchasedDays !== null && intval($purchasedDays) <= 0){
            return 'immediate_unlimited';
        }

        if(!empty($runtime['ambiguous'])){
            return 'ambiguous';
        }

        if($purchasedDays !== null){
            return !empty($runtime['should_activate_now']) ? 'activate_now' : 'reserve';
        }

        // Backwards-compatible rule for legacy callers without purchase metadata.
        if(($runtime['time_unlimited'] ?? null) === true){
            return !empty($runtime['volume_exhausted']) ? 'immediate_unlimited' : 'reserve';
        }

        return !empty($runtime['should_activate_now']) ? 'activate_now' : 'reserve';
    }

    function renewReserveEnqueue(array $entry){
        $result = renewReserveWithQueueLock(static function() use ($entry){
            $loaded = renewReserveLoadResult();

            if(empty($loaded['ok'])){
                return ['ok' => false, 'error' => $loaded['error'] ?? 'load_failed'];
            }

            $items = $loaded['items'];
            $provisionKey = trim((string)($entry['provision_key'] ?? ''));

            if($provisionKey !== ''){
                foreach($items as $existing){
                    if(trim((string)($existing['provision_key'] ?? '')) === $provisionKey){
                        return ['ok' => true, 'item' => $existing];
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

            if(!renewReserveSave($items)){
                return ['ok' => false, 'error' => 'save_failed'];
            }

            return ['ok' => true, 'item' => $entry];
        });

        if(!is_array($result) || empty($result['ok'])){
            return null;
        }

        return is_array($result['item'] ?? null) ? $result['item'] : null;
    }

    function renewReserveUpdateById($id, array $patch){
        $id = trim((string)$id);
        $result = renewReserveWithQueueLock(static function() use ($id, $patch){
            $loaded = renewReserveLoadResult();

            if(empty($loaded['ok'])){
                return ['ok' => false, 'error' => $loaded['error'] ?? 'load_failed'];
            }

            $items = $loaded['items'];
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
                return ['ok' => false, 'error' => 'not_found'];
            }

            if(!renewReserveSave($items)){
                return ['ok' => false, 'error' => 'save_failed'];
            }

            return ['ok' => true, 'item' => $found];
        });

        if(!is_array($result) || empty($result['ok'])){
            return null;
        }

        return is_array($result['item'] ?? null) ? $result['item'] : null;
    }

    function renewReservePendingOrdered(){
        $loaded = renewReserveLoadResult();

        if(empty($loaded['ok']) || !is_array($loaded['items'])){
            return [];
        }

        $byQueue = [];

        foreach($loaded['items'] as $item){
            $key = (string)($item['queue_key'] ?? '');

            if($key === ''){
                continue;
            }

            $byQueue[$key][] = $item;
        }

        $pending = [];
        $blockStatuses = ['activating', 'ambiguous', 'activation_failed'];

        foreach($byQueue as $list){
            usort($list, static function($a, $b){
                return intval($a['reserved_at'] ?? 0) <=> intval($b['reserved_at'] ?? 0);
            });

            $blocked = false;

            foreach($list as $item){
                $st = trim((string)($item['status'] ?? ''));

                if($blocked){
                    continue;
                }

                if(in_array($st, $blockStatuses, true)){
                    $blocked = true;
                    continue;
                }

                if($st === 'reserved'){
                    $pending[] = $item;
                    break;
                }
            }
        }

        usort($pending, static function($a, $b){
            return intval($a['reserved_at'] ?? 0) <=> intval($b['reserved_at'] ?? 0);
        });

        return $pending;
    }

    function renewReserveQueueWorkItems(){
        $loaded = renewReserveLoadResult();

        if(empty($loaded['ok']) || !is_array($loaded['items'])){
            return [];
        }

        $byQueue = [];

        foreach($loaded['items'] as $item){
            $key = (string)($item['queue_key'] ?? '');

            if($key === ''){
                continue;
            }

            $byQueue[$key][] = $item;
        }

        $grace = renewReserveActivatingGraceSeconds();
        $work = [];

        foreach($byQueue as $list){
            usort($list, static function($a, $b){
                return intval($a['reserved_at'] ?? 0) <=> intval($b['reserved_at'] ?? 0);
            });

            foreach($list as $item){
                $st = trim((string)($item['status'] ?? ''));

                if($st === 'activated'){
                    continue;
                }

                if(in_array($st, ['ambiguous', 'activation_failed'], true)){
                    break;
                }

                if($st === 'activating'){
                    $started = intval($item['activating_at'] ?? 0);

                    if($started > 0 && (renewReserveNow() - $started) >= $grace){
                        $work[] = $item;
                    }

                    break;
                }

                if($st === 'reserved'){
                    $work[] = $item;
                    break;
                }

                break;
            }
        }

        usort($work, static function($a, $b){
            return intval($a['reserved_at'] ?? 0) <=> intval($b['reserved_at'] ?? 0);
        });

        return $work;
    }

    function renewReserveRecoverStaleActivating(array $fresh){
        if(!function_exists('xuiLoadConfig')){
            require_once __DIR__ . '/xui_lib.php';
        }

        $config = xuiLoadConfig();
        $id = trim((string)($fresh['id'] ?? ''));
        $host = trim((string)($fresh['server_host'] ?? ''));
        $subId = trim((string)($fresh['sub_id'] ?? ''));
        $subLink = trim((string)($fresh['sub_link'] ?? ''));
        $gb = intval($fresh['plan_gb'] ?? 0);
        $days = intval($fresh['plan_days'] ?? 0);
        $anchorMs = intval($fresh['activation_anchor_ms'] ?? 0);
        $opId = trim((string)($fresh['activation_op_id'] ?? ''));

        if($anchorMs <= 0){
            return [
                'ok' => false,
                'code' => 'ambiguous',
                'error' => 'مرجع زمانی فعال‌سازی ذخیره نشده است',
            ];
        }

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

        if(($lookup['status'] ?? '') !== 'found' || !is_array($lookup['client'] ?? null)){
            renewReserveUpdateById($id, [
                'status' => 'ambiguous',
                'last_error' => 'وضعیت اشتراک نامشخص است',
                'last_check_at' => renewReserveNow(),
            ]);

            return ['ok' => false, 'code' => 'ambiguous'];
        }

        $client = $lookup['client'];

        // Legacy time-unlimited reservations are additive, never absolute.
        // If the worker crashed, verify the saved baseline without another POST.
        if($days === 0 && ($fresh['activation_operation'] ?? '') === 'incremental_unlimited'){
            $baseline = $fresh['activation_baseline'] ?? null;
            $verified = is_array($baseline) && xuiRenewVerifyIncrementalAdjust($baseline, $client, $gb, 0);

            if($verified){
                $done = renewReserveUpdateById($id, [
                    'status' => 'activated',
                    'activated_at' => renewReserveNow(),
                    'last_check_at' => renewReserveNow(),
                    'last_check_note' => 'recovered_additive_volume',
                ]);
                return is_array($done)
                    ? ['ok' => true, 'reserve_id' => $id, 'method' => 'recover_incremental_unlimited']
                    : ['ok' => false, 'code' => 'persist_failed', 'error' => 'ذخیره فعال‌سازی ناموفق بود'];
            }

            renewReserveUpdateById($id, [
                'status' => 'ambiguous',
                'last_check_at' => renewReserveNow(),
                'last_error' => 'وضعیت افزایش حجم قبلی قابل اثبات نیست؛ درخواست تکرار نشد',
            ]);
            return ['ok' => false, 'code' => 'ambiguous', 'error' => 'نیاز به بررسی دستی افزایش حجم'];
        }

        if(xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs)){
            $done = renewReserveUpdateById($id, [
                'status' => 'activated',
                'activated_at' => renewReserveNow(),
                'last_check_at' => renewReserveNow(),
                'last_check_note' => 'recovered_stale_activating',
                'activation_op_id' => $opId !== '' ? $opId : ($fresh['activation_op_id'] ?? ''),
            ]);

            if(!is_array($done)){
                return [
                    'ok' => false,
                    'code' => 'persist_failed',
                    'error' => 'فعال‌سازی تأیید شد اما ذخیره activated ناموفق بود',
                    'recoverable' => true,
                ];
            }

            return ['ok' => true, 'reserve_id' => $id, 'method' => 'recover_stale_activating'];
        }

        $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);

        if(!empty($runtime['should_activate_now']) && empty($runtime['ambiguous'])){
            $reopened = renewReserveUpdateById($id, [
                'status' => 'reserved',
                'activating_at' => 0,
                'last_error' => '',
                'last_check_at' => renewReserveNow(),
                'last_check_note' => 'stale_no_apply_detected',
                'activation_anchor_ms' => $anchorMs,
            ]);

            if(!is_array($reopened)){
                return ['ok' => false, 'code' => 'persist_failed', 'error' => 'ذخیره وضعیت reserved ناموفق بود'];
            }

            return ['ok' => false, 'skipped' => true, 'reason' => 'stale_reopened_for_retry'];
        }

        renewReserveUpdateById($id, [
            'status' => 'ambiguous',
            'last_error' => 'فعال‌سازی قبلی نامشخص ماند',
            'last_check_at' => renewReserveNow(),
        ]);

        return ['ok' => false, 'code' => 'ambiguous', 'error' => 'فعال‌سازی نامشخص'];
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

    /**
     * Previously queued time-unlimited purchases must add volume immediately,
     * NOT reset the client's cap to the purchased GB. Runs under the reserve lock.
     */
    function renewReserveActivateQueuedUnlimited(array $fresh, $server, array $client, $subLink){
        $id = trim((string)($fresh['id'] ?? ''));
        $gb = intval($fresh['plan_gb'] ?? 0);
        $subId = trim((string)($fresh['sub_id'] ?? ''));
        $email = trim((string)($client['email'] ?? ''));
        $baseline = xuiRenewClientSnapshot($client);

        if($gb <= 0 || $email === '' || intval($baseline['total_bytes'] ?? 0) <= 0){
            renewReserveUpdateById($id, [
                'status' => 'ambiguous',
                'last_error' => 'اطلاعات حجم/کلاینت برای افزایش امن حجم نامعتبر است',
                'last_check_at' => renewReserveNow(),
            ]);
            return ['ok' => false, 'code' => 'ambiguous', 'error' => 'اطلاعات افزایش حجم ناقص است'];
        }

        $anchorMs = intval($fresh['activation_anchor_ms'] ?? 0);
        if($anchorMs <= 0){
            $anchorMs = renewReserveNowMs();
        }

        $opId = hash('sha256', $id . '|add_volume|' . $anchorMs);
        $activating = renewReserveUpdateById($id, [
            'status' => 'activating',
            'activating_at' => renewReserveNow(),
            'activation_anchor_ms' => $anchorMs,
            'activation_op_id' => $opId,
            'activation_operation' => 'incremental_unlimited',
            'activation_baseline' => $baseline,
        ]);

        if(!is_array($activating)){
            return ['ok' => false, 'code' => 'persist_failed', 'error' => 'ثبت عملیات افزایش حجم ناموفق بود'];
        }

        $usedBytes = intval($baseline['used_bytes'] ?? 0);
        $totalBytes = intval($baseline['total_bytes'] ?? 0);
        if($usedBytes >= $totalBytes){
            $applied = xuiAdjustClientTrafficLegacy($server, $client, $gb);
        }
        else{
            $applied = xuiAdjustClientTraffic($server, $email, $gb, $client, 0);
        }

        // Never resend a POST after a timeout or an ambiguous response.
        $lookup = xuiRenewLookupClient($server, $subId, $subLink);
        $verified = ($lookup['status'] ?? '') === 'found'
            && is_array($lookup['client'] ?? null)
            && xuiRenewVerifyIncrementalAdjust($baseline, $lookup['client'], $gb, 0);

        if($verified){
            $done = renewReserveUpdateById($id, [
                'status' => 'activated',
                'activated_at' => renewReserveNow(),
                'last_check_at' => renewReserveNow(),
                'last_check_note' => 'unlimited_volume_added',
            ]);
            return is_array($done)
                ? ['ok' => true, 'reserve_id' => $id, 'method' => $applied['method'] ?? 'verified_additive_volume']
                : ['ok' => false, 'code' => 'persist_failed', 'error' => 'افزایش حجم تأیید شد اما ذخیره ناموفق بود'];
        }

        // Even a nominal 2xx response does not prove the volume actually changed.
        // Keep the record for manual inspection without automatic replay.
        renewReserveUpdateById($id, [
            'status' => 'ambiguous',
            'last_error' => 'نتیجه افزایش حجم با اطلاعات سرور تطبیق ندارد؛ درخواست مجدد ارسال نشد',
            'last_check_at' => renewReserveNow(),
        ]);
        return [
            'ok' => false,
            'code' => 'ambiguous',
            'error' => $applied['error'] ?? 'نتیجه افزایش حجم تأیید نشد',
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

            $allItems = renewReserveLoad();

            if(!is_array($allItems)){
                return ['ok' => false, 'error' => 'صف رزرو قابل خواندن نیست', 'code' => 'queue_corrupt'];
            }

            foreach($allItems as $item){
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
                $grace = renewReserveActivatingGraceSeconds();

                if($started > 0 && (renewReserveNow() - $started) < $grace){
                    return ['ok' => false, 'skipped' => true, 'reason' => 'activating'];
                }

                return renewReserveRecoverStaleActivating($fresh);
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

            // Existing reservations created under the old policy: settle their
            // time-unlimited purchases immediately by additive volume only.
            if(is_array($client)
                && array_key_exists('plan_days', $fresh)
                && intval($fresh['plan_days']) === 0){
                return renewReserveActivateQueuedUnlimited($fresh, $server, $client, $subLink);
            }

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

            $gb = intval($fresh['plan_gb'] ?? 0);
            $days = intval($fresh['plan_days'] ?? 0);
            $email = trim((string)($client['email'] ?? ''));
            $anchorMs = intval($fresh['activation_anchor_ms'] ?? 0);

            if($anchorMs <= 0){
                $anchorMs = renewReserveNowMs();
            }

            $opId = hash('sha256', $id . '|activate|' . $anchorMs);
            $activating = renewReserveUpdateById($id, [
                'status' => 'activating',
                'activating_at' => renewReserveNow(),
                'activation_op_id' => $opId,
                'activation_anchor_ms' => $anchorMs,
            ]);

            if(!is_array($activating)){
                return ['ok' => false, 'error' => 'ثبت وضعیت activating ناموفق بود', 'code' => 'persist_failed'];
            }

            $applied = xuiApplyRenewPlanAbsolute($server, $client, $gb, $days, $anchorMs);
            $applyDiag = function_exists('xuiApplyRenewDiagSummary')
                ? xuiApplyRenewDiagSummary($applied)
                : trim((string)($applied['error'] ?? ''));

            if(empty($applied['ok'])){
                $code = trim((string)($applied['code'] ?? ''));

                if($code === 'transport_error'){
                    $verifyLookup = xuiRenewLookupClient($server, $subId, $subLink);

                    if(($verifyLookup['status'] ?? '') === 'found' && is_array($verifyLookup['client'] ?? null)){
                        if(xuiRenewVerifyAbsolutePlan($verifyLookup['client'], $gb, $days, $anchorMs)){
                            $done = renewReserveUpdateById($id, [
                                'status' => 'activated',
                                'activated_at' => renewReserveNow(),
                                'last_check_at' => renewReserveNow(),
                                'last_check_note' => 'verified_after_transport',
                                'activation_op_id' => $opId,
                            ]);

                            if(!is_array($done)){
                                return ['ok' => false, 'code' => 'persist_failed', 'error' => 'فعال‌سازی تأیید شد اما ذخیره ناموفق بود'];
                            }

                            return ['ok' => true, 'reserve_id' => $id, 'method' => 'verify_after_transport'];
                        }

                        renewReserveUpdateById($id, [
                            'status' => 'reserved',
                            'last_error' => $applyDiag !== '' ? $applyDiag : 'updateClient failed; XUI unchanged after transport',
                            'last_check_at' => renewReserveNow(),
                            'last_check_note' => 'transport_no_change',
                        ]);

                        return [
                            'ok' => false,
                            'code' => 'api_error',
                            'error' => 'تغییر اعمال نشده؛ retry امن',
                            'apply_diag' => $applied['attempts'] ?? [],
                        ];
                    }

                    renewReserveUpdateById($id, [
                        'status' => 'ambiguous',
                        'last_error' => 'نتیجه فعال‌سازی پس از timeout نامشخص است',
                        'last_check_at' => renewReserveNow(),
                    ]);

                    return ['ok' => false, 'code' => 'ambiguous', 'error' => 'فعال‌سازی نامشخص'];
                }

                renewReserveUpdateById($id, [
                    'status' => 'activation_failed',
                    'last_error' => $applyDiag !== '' ? $applyDiag : ($applied['error'] ?? 'فعال‌سازی ناموفق'),
                    'last_check_at' => renewReserveNow(),
                ]);

                return $applied;
            }

            $verifyLookup = xuiRenewLookupClient($server, $subId, $subLink);

            if(
                ($verifyLookup['status'] ?? '') !== 'found'
                || !is_array($verifyLookup['client'] ?? null)
                || !xuiRenewVerifyAbsolutePlan($verifyLookup['client'], $gb, $days, $anchorMs)
            ){
                renewReserveUpdateById($id, [
                    'status' => 'ambiguous',
                    'last_error' => 'پاسخ API موفق بود اما تطابق پلن روی سرور اثبات نشد',
                    'last_check_at' => renewReserveNow(),
                ]);

                return ['ok' => false, 'code' => 'ambiguous', 'error' => 'تطابق پلن اثبات نشد'];
            }

            $done = renewReserveUpdateById($id, [
                'status' => 'activated',
                'activated_at' => renewReserveNow(),
                'last_check_at' => renewReserveNow(),
                'last_check_note' => 'ok',
                'activation_op_id' => $opId,
            ]);

            if(!is_array($done)){
                return [
                    'ok' => false,
                    'code' => 'persist_failed',
                    'error' => 'فعال‌سازی روی XUI انجام شد اما ذخیره activated ناموفق بود',
                    'recoverable' => true,
                ];
            }

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

        foreach(renewReserveQueueWorkItems() as $reservation){
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

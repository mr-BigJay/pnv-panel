<?php

/**
 * صف تراکنش Worker تجارت + heartbeat (بدون ذخیره رمز/OTP/کوکی در Git).
 */

if(!function_exists('tejaratWorkerInboxPath')){

    function tejaratWorkerInboxPath(){
        $override = getenv('PNV_TEJARAT_WORKER_INBOX_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/tejarat_worker_inbox.json';
    }

    function tejaratWorkerHealthPath(){
        $override = getenv('PNV_TEJARAT_WORKER_HEALTH_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/tejarat_worker_health.json';
    }

    function tejaratWorkerDefaultInbox(){
        return [
            'version' => 1,
            'transactions' => [],
        ];
    }

    function tejaratWorkerDefaultHealth(){
        return [
            'updated_at' => 0,
            'session_status' => 'unknown',
            'last_fetch_ok_at' => 0,
            'last_error' => '',
            'worker_version' => '',
            'stats' => [
                'pushes' => 0,
                'transactions_received' => 0,
            ],
        ];
    }

    function tejaratWorkerLoadInbox(){
        $path = tejaratWorkerInboxPath();

        if(!is_file($path)){
            return tejaratWorkerDefaultInbox();
        }

        $data = json_decode((string)file_get_contents($path), true);

        if(!is_array($data)){
            return tejaratWorkerDefaultInbox();
        }

        $base = tejaratWorkerDefaultInbox();

        if(!isset($data['transactions']) || !is_array($data['transactions'])){
            $data['transactions'] = [];
        }

        return array_merge($base, $data);
    }

    function tejaratWorkerSaveInbox(array $inbox){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $json = json_encode($inbox, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($json === false){
            return false;
        }

        $written = file_put_contents(tejaratWorkerInboxPath(), $json, LOCK_EX);

        return $written !== false && $written >= strlen($json);
    }

    function tejaratWorkerLoadHealth(){
        $path = tejaratWorkerHealthPath();

        if(!is_file($path)){
            return tejaratWorkerDefaultHealth();
        }

        $data = json_decode((string)file_get_contents($path), true);

        if(!is_array($data)){
            return tejaratWorkerDefaultHealth();
        }

        $base = tejaratWorkerDefaultHealth();

        if(isset($data['stats']) && is_array($data['stats'])){
            $base['stats'] = array_merge($base['stats'], $data['stats']);
        }

        return array_merge($base, $data);
    }

    function tejaratWorkerSaveHealth(array $health){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $json = json_encode($health, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($json === false){
            return false;
        }

        $written = file_put_contents(tejaratWorkerHealthPath(), $json, LOCK_EX);

        return $written !== false && $written >= strlen($json);
    }

    function tejaratWorkerIngestSecret(array $config){
        return trim((string)($config['worker_ingest_secret'] ?? ''));
    }

    function tejaratWorkerVerifySignature($rawBody, $signature, array $config){
        $secret = tejaratWorkerIngestSecret($config);

        if($secret === '' || !is_string($rawBody)){
            return false;
        }

        $signature = trim((string)$signature);

        if($signature === ''){
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    function tejaratWorkerNormalizeIncomingTransaction(array $row){
        $amount = intval($row['amount'] ?? $row['amount_rial'] ?? 0);
        $direction = strtolower(trim((string)($row['direction'] ?? 'credit')));

        if($amount <= 0){
            return null;
        }

        if($direction !== '' && !in_array($direction, ['credit', 'in', 'deposit'], true)){
            return null;
        }

        $txId = trim((string)($row['transaction_id'] ?? $row['id'] ?? $row['reference'] ?? ''));

        if($txId === ''){
            return null;
        }

        $card = preg_replace('/\D+/', '', (string)($row['destination_card'] ?? ($row['card'] ?? '')));
        $occurred = intval($row['occurred_at'] ?? $row['timestamp'] ?? 0);

        return [
            'amount' => $amount,
            'destination_card' => $card,
            'transaction_id' => $txId,
            'occurred_at' => $occurred,
            'description' => trim((string)($row['description'] ?? $row['text'] ?? '')),
            'direction' => 'credit',
            'ingested_at' => time(),
            'consumed' => false,
            'source_session_at' => intval($row['source_session_at'] ?? 0),
        ];
    }

    function tejaratWorkerMergeTransactions(array $existing, array $incoming){
        $index = [];

        foreach($existing as $i => $row){
            if(!is_array($row)){
                continue;
            }

            $key = trim((string)($row['transaction_id'] ?? ''));

            if($key !== ''){
                $index[$key] = $i;
            }
        }

        foreach($incoming as $row){
            if(!is_array($row)){
                continue;
            }

            $norm = tejaratWorkerNormalizeIncomingTransaction($row);

            if($norm === null){
                continue;
            }

            $key = $norm['transaction_id'];

            if(isset($index[$key])){
                $pos = $index[$key];
                $prev = $existing[$pos];

                if(is_array($prev)){
                    $existing[$pos] = array_merge($prev, $norm, [
                        'consumed' => !empty($prev['consumed']),
                        'ingested_at' => max(intval($prev['ingested_at'] ?? 0), intval($norm['ingested_at'] ?? 0)),
                    ]);
                }

                continue;
            }

            $existing[] = $norm;
            $index[$key] = count($existing) - 1;
        }

        return $existing;
    }

    /**
     * @return array{ok:bool,code?:string,error?:string,added?:int}
     */
    function tejaratWorkerHandleIngestPayload(array $payload, array $config){
        $inbox = tejaratWorkerLoadInbox();
        $health = tejaratWorkerLoadHealth();
        $now = time();
        $added = 0;

        if(isset($payload['health']) && is_array($payload['health'])){
            $h = $payload['health'];
            $health['updated_at'] = $now;
            $health['session_status'] = trim((string)($h['session_status'] ?? $health['session_status'] ?? 'unknown'));
            $health['last_fetch_ok_at'] = intval($h['last_fetch_ok_at'] ?? $health['last_fetch_ok_at'] ?? 0);
            $health['last_error'] = trim((string)($h['last_error'] ?? ''));
            $health['worker_version'] = trim((string)($h['worker_version'] ?? $health['worker_version'] ?? ''));

            if($health['session_status'] === 'logged_in' && $health['last_fetch_ok_at'] <= 0){
                $health['last_fetch_ok_at'] = $now;
            }
        }

        $before = count($inbox['transactions'] ?? []);
        $incoming = is_array($payload['transactions'] ?? null) ? $payload['transactions'] : [];
        $inbox['transactions'] = tejaratWorkerMergeTransactions($inbox['transactions'] ?? [], $incoming);
        $after = count($inbox['transactions'] ?? []);
        $added = max(0, $after - $before);

        $health['stats']['pushes'] = intval($health['stats']['pushes'] ?? 0) + 1;
        $health['stats']['transactions_received'] = intval($health['stats']['transactions_received'] ?? 0) + count($incoming);

        if(!tejaratWorkerSaveInbox($inbox) || !tejaratWorkerSaveHealth($health)){
            return [
                'ok' => false,
                'code' => 'persist_failed',
                'error' => 'ذخیره inbox/health ناموفق بود',
            ];
        }

        return [
            'ok' => true,
            'added' => $added,
            'inbox_size' => $after,
        ];
    }

    function tejaratWorkerHeartbeatMaxAgeSeconds(array $config){
        return max(60, intval($config['worker_heartbeat_max_age_seconds'] ?? 300));
    }

    function tejaratWorkerSessionIsFresh(array $config){
        $health = tejaratWorkerLoadHealth();
        $status = strtolower(trim((string)($health['session_status'] ?? 'unknown')));
        $updated = intval($health['updated_at'] ?? 0);
        $maxAge = tejaratWorkerHeartbeatMaxAgeSeconds($config);

        if($status !== 'logged_in'){
            return false;
        }

        if($updated <= 0 || (time() - $updated) > $maxAge){
            return false;
        }

        return true;
    }

    function tejaratWorkerFetchPendingTransactions(array $config){
        if(!tejaratWorkerSessionIsFresh($config)){
            $health = tejaratWorkerLoadHealth();
            $status = (string)($health['session_status'] ?? 'unknown');

            return [
                'ok' => false,
                'connection_ok' => false,
                'transactions' => [],
                'code' => $status === 'login_required' ? 'session_login_required' : 'session_stale',
                'error' => $status === 'login_required'
                    ? 'نشست اینترنت‌بانک منقضی شده؛ ورود مجدد مدیر لازم است'
                    : 'heartbeat Worker منقضی است؛ از تأیید با داده قدیمی خودداری شد',
            ];
        }

        $inbox = tejaratWorkerLoadInbox();
        $rows = [];
        $maxAge = max(3600, intval($config['max_deposit_age_seconds'] ?? 86400));
        $now = time();

        foreach(($inbox['transactions'] ?? []) as $row){
            if(!is_array($row) || !empty($row['consumed'])){
                continue;
            }

            $ingested = intval($row['ingested_at'] ?? 0);

            if($ingested > 0 && ($now - $ingested) > $maxAge){
                continue;
            }

            $rows[] = $row;
        }

        if(function_exists('tejaratVerifyNormalizeTransactionList')){
            $rows = tejaratVerifyNormalizeTransactionList($rows, $config);
        }

        return [
            'ok' => true,
            'connection_ok' => true,
            'transactions' => $rows,
        ];
    }

    function tejaratWorkerMarkTransactionsConsumed(array $transactionIds){
        $ids = array_values(array_unique(array_filter(array_map('strval', $transactionIds), static fn($id) => $id !== '')));

        if(count($ids) === 0){
            return true;
        }

        $inbox = tejaratWorkerLoadInbox();
        $changed = false;

        foreach($inbox['transactions'] as $i => $row){
            if(!is_array($row)){
                continue;
            }

            $txId = trim((string)($row['transaction_id'] ?? ''));

            if($txId !== '' && in_array($txId, $ids, true)){
                $inbox['transactions'][$i]['consumed'] = true;
                $inbox['transactions'][$i]['consumed_at'] = time();
                $changed = true;
            }
        }

        if(!$changed){
            return true;
        }

        return tejaratWorkerSaveInbox($inbox);
    }

    function tejaratWorkerAdminSnapshot(array $config){
        $health = tejaratWorkerLoadHealth();
        $inbox = tejaratWorkerLoadInbox();
        $pending = 0;

        foreach(($inbox['transactions'] ?? []) as $row){
            if(is_array($row) && empty($row['consumed'])){
                $pending++;
            }
        }

        return [
            'session_status' => (string)($health['session_status'] ?? 'unknown'),
            'heartbeat_at' => intval($health['updated_at'] ?? 0),
            'last_fetch_ok_at' => intval($health['last_fetch_ok_at'] ?? 0),
            'last_error' => (string)($health['last_error'] ?? ''),
            'worker_version' => (string)($health['worker_version'] ?? ''),
            'pending_inbox' => $pending,
            'heartbeat_fresh' => tejaratWorkerSessionIsFresh($config),
            'ingest_configured' => tejaratWorkerIngestSecret($config) !== '',
        ];
    }
}

<?php

/**
 * تأیید خودکار واریز بانک تجارت — ماژول مستقل (بدون ذخیره رمز/OTP/توکن بانک).
 *
 * منبع تراکنش:
 * - mock: تست و توسعه
 * - api_stub: غیرفعال تا دسترسی رسمی/مجاز به API گردش حساب فراهم شود
 * - disabled: ماژول خاموش
 *
 * جایگزین پیشنهادی وقتی API پایدار نیست: دریافت پیامک واریز روی اندروید
 * (Notification Listener / Tasker) و ارسال امن به webhook داخلی — مشابه مسیر بله.
 */

if(!function_exists('tejaratVerifyConfigPath')){

    function tejaratVerifyConfigPath(){
        $override = getenv('PNV_TEJARAT_VERIFY_CONFIG_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/tejarat_verify.json';
    }

    function tejaratVerifyStatePath(){
        $override = getenv('PNV_TEJARAT_VERIFY_STATE_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/tejarat_verify_state.json';
    }

    function tejaratVerifyDefaultConfig(){
        return [
            'enabled' => false,
            'provider' => 'disabled',
            'poll_interval_seconds' => 60,
            'dry_run' => true,
            'destination_cards' => [],
            'max_deposit_age_seconds' => 86400,
            'notes' => 'بدون مجوز بانک provider را روی api_stub یا disabled نگه دارید.',
        ];
    }

    function tejaratVerifyDefaultState(){
        return [
            'connection_status' => 'disabled',
            'last_poll_at' => 0,
            'last_success_at' => 0,
            'last_error' => '',
            'stats' => [
                'polls' => 0,
                'transactions_seen' => 0,
                'matches_attempted' => 0,
                'matches_confirmed' => 0,
                'errors' => 0,
            ],
        ];
    }

    function tejaratVerifyLoadConfig(){
        $path = tejaratVerifyConfigPath();
        $base = tejaratVerifyDefaultConfig();

        if(!is_file($path)){
            return $base;
        }

        $data = json_decode((string)file_get_contents($path), true);

        if(!is_array($data)){
            return $base;
        }

        return array_merge($base, $data);
    }

    function tejaratVerifySaveConfig(array $config){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($json === false){
            return false;
        }

        $written = file_put_contents(tejaratVerifyConfigPath(), $json, LOCK_EX);

        return $written !== false && $written >= strlen($json);
    }

    function tejaratVerifyLoadState(){
        $path = tejaratVerifyStatePath();
        $base = tejaratVerifyDefaultState();

        if(!is_file($path)){
            return $base;
        }

        $data = json_decode((string)file_get_contents($path), true);

        if(!is_array($data)){
            return $base;
        }

        $merged = array_merge($base, $data);

        if(isset($data['stats']) && is_array($data['stats'])){
            $merged['stats'] = array_merge($base['stats'], $data['stats']);
        }

        return $merged;
    }

    function tejaratVerifySaveState(array $state){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($json === false){
            return false;
        }

        $written = file_put_contents(tejaratVerifyStatePath(), $json, LOCK_EX);

        return $written !== false && $written >= strlen($json);
    }

    function tejaratVerifyNormalizeCard($card){
        if(function_exists('instantPayNormalizeCardNumber')){
            return instantPayNormalizeCardNumber($card);
        }

        return preg_replace('/\D+/', '', (string)$card);
    }

    function tejaratVerifyProviderLabel($provider){
        $provider = strtolower(trim((string)$provider));

        return match($provider){
            'mock' => 'آزمایشی (Mock)',
            'api_stub' => 'API بانک (غیرفعال)',
            'disabled' => 'خاموش',
            default => $provider !== '' ? $provider : 'نامشخص',
        };
    }

    function tejaratVerifyIsRunnable(array $config){
        if(empty($config['enabled'])){
            return false;
        }

        $provider = strtolower(trim((string)($config['provider'] ?? 'disabled')));

        return in_array($provider, ['mock', 'api_stub'], true);
    }

    /**
     * @return array{ok:bool,transactions:array<int,array>,connection_ok:bool,error?:string,code?:string}
     */
    function tejaratVerifyFetchTransactions(array $config){
        $provider = strtolower(trim((string)($config['provider'] ?? 'disabled')));

        if($provider === 'mock'){
            return tejaratVerifyMockFetch($config);
        }

        if($provider === 'api_stub'){
            return [
                'ok' => false,
                'connection_ok' => false,
                'transactions' => [],
                'code' => 'api_not_configured',
                'error' => 'اتصال رسمی به API گردش حساب تجارت پیکربندی نشده است. از mock یا پیامک اندروید استفاده کنید.',
            ];
        }

        return [
            'ok' => false,
            'connection_ok' => false,
            'transactions' => [],
            'code' => 'provider_disabled',
            'error' => 'provider غیرفعال است',
        ];
    }

    function tejaratVerifyMockTransactionsFromEnv(){
        $raw = getenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON');

        if(!is_string($raw) || trim($raw) === ''){
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    function tejaratVerifyMockFetch(array $config){
        $forceError = getenv('PNV_TEJARAT_MOCK_FORCE_ERROR');

        if(is_string($forceError) && trim($forceError) !== ''){
            return [
                'ok' => false,
                'connection_ok' => false,
                'transactions' => [],
                'code' => 'mock_connection_error',
                'error' => trim($forceError),
            ];
        }

        $fromEnv = tejaratVerifyMockTransactionsFromEnv();

        if(is_array($fromEnv)){
            return [
                'ok' => true,
                'connection_ok' => true,
                'transactions' => tejaratVerifyNormalizeTransactionList($fromEnv, $config),
            ];
        }

        $fixtures = $config['mock_transactions'] ?? [];

        if(!is_array($fixtures)){
            $fixtures = [];
        }

        return [
            'ok' => true,
            'connection_ok' => true,
            'transactions' => tejaratVerifyNormalizeTransactionList($fixtures, $config),
        ];
    }

    function tejaratVerifyNormalizeTransactionList(array $rows, array $config){
        $out = [];

        foreach($rows as $row){
            if(!is_array($row)){
                continue;
            }

            $normalized = tejaratVerifyNormalizeTransaction($row, $config);

            if($normalized !== null){
                $out[] = $normalized;
            }
        }

        return $out;
    }

    function tejaratVerifyNormalizeTransaction(array $row, array $config){
        $amount = intval($row['amount'] ?? $row['amount_rial'] ?? 0);
        $direction = strtolower(trim((string)($row['direction'] ?? 'credit')));

        if($amount <= 0){
            return null;
        }

        if($direction !== '' && !in_array($direction, ['credit', 'in', 'deposit'], true)){
            return null;
        }

        $destination = tejaratVerifyNormalizeCard($row['destination_card'] ?? ($row['card'] ?? ''));
        $allowed = array_map('tejaratVerifyNormalizeCard', (array)($config['destination_cards'] ?? []));
        $allowed = array_values(array_filter($allowed, static fn($c) => $c !== ''));

        if($destination !== '' && count($allowed) > 0 && !in_array($destination, $allowed, true)){
            return null;
        }

        if($destination === '' && count($allowed) === 1){
            $destination = $allowed[0];
        }

        $txId = trim((string)($row['transaction_id'] ?? $row['id'] ?? ''));
        $occurred = intval($row['occurred_at'] ?? 0);

        if($occurred <= 0){
            $occurred = intval($row['timestamp'] ?? 0);
        }

        return [
            'amount' => $amount,
            'destination_card' => $destination,
            'transaction_id' => $txId,
            'occurred_at' => $occurred,
            'description' => trim((string)($row['description'] ?? $row['text'] ?? '')),
            'direction' => 'credit',
        ];
    }

    function tejaratVerifyBuildDepositMeta(array $tx, array $config){
        $meta = [
            'source' => 'tejarat',
            'bank_id' => 'tejarat',
            'destination_card' => tejaratVerifyNormalizeCard($tx['destination_card'] ?? ''),
            'transaction_id' => trim((string)($tx['transaction_id'] ?? '')),
            'occurred_at' => intval($tx['occurred_at'] ?? 0),
            'text' => trim((string)($tx['description'] ?? 'Tejarat deposit')),
            'max_deposit_age_seconds' => intval($config['max_deposit_age_seconds'] ?? 86400),
        ];

        return $meta;
    }

    /**
     * یک دور polling — بدون تأیید اشتباه در خطای اتصال.
     *
     * @return array<string,mixed>
     */
    function tejaratVerifyPollOnce(array $opts = []){
        require_once __DIR__ . '/instant_pay_lib.php';

        $config = tejaratVerifyLoadConfig();
        $state = tejaratVerifyLoadState();
        $now = time();
        $state['stats']['polls'] = intval($state['stats']['polls'] ?? 0) + 1;
        $state['last_poll_at'] = $now;

        if(!tejaratVerifyIsRunnable($config)){
            $state['connection_status'] = 'disabled';
            tejaratVerifySaveState($state);

            return [
                'ok' => true,
                'skipped' => true,
                'reason' => 'disabled',
            ];
        }

        $dryRun = !empty($opts['dry_run']) || !empty($config['dry_run']);
        $fetch = tejaratVerifyFetchTransactions($config);

        if(empty($fetch['ok'])){
            $state['connection_status'] = 'error';
            $state['last_error'] = (string)($fetch['error'] ?? 'خطای نامشخص');
            $state['stats']['errors'] = intval($state['stats']['errors'] ?? 0) + 1;
            tejaratVerifySaveState($state);

            return [
                'ok' => false,
                'connection_ok' => !empty($fetch['connection_ok']),
                'code' => $fetch['code'] ?? 'fetch_failed',
                'error' => $state['last_error'],
            ];
        }

        $state['connection_status'] = 'ok';
        $state['last_success_at'] = $now;
        $state['last_error'] = '';

        $transactions = is_array($fetch['transactions'] ?? null) ? $fetch['transactions'] : [];
        $state['stats']['transactions_seen'] = intval($state['stats']['transactions_seen'] ?? 0) + count($transactions);

        $results = [];

        foreach($transactions as $tx){
            $amount = intval($tx['amount'] ?? 0);

            if($amount <= 0){
                continue;
            }

            $state['stats']['matches_attempted'] = intval($state['stats']['matches_attempted'] ?? 0) + 1;
            $depositMeta = tejaratVerifyBuildDepositMeta($tx, $config);
            $match = instantPayTryDepositMatch($amount, $depositMeta, [
                'apply' => !$dryRun,
                'dry_run' => $dryRun,
            ]);
            $match['transaction_id'] = $depositMeta['transaction_id'];
            $results[] = $match;

            if(!empty($match['ok']) && empty($match['dry_run'])){
                $state['stats']['matches_confirmed'] = intval($state['stats']['matches_confirmed'] ?? 0) + 1;
            }
        }

        tejaratVerifySaveState($state);

        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'transactions' => count($transactions),
            'results' => $results,
        ];
    }

    function tejaratVerifyAdminSummary(){
        $config = tejaratVerifyLoadConfig();
        $state = tejaratVerifyLoadState();

        return [
            'enabled' => !empty($config['enabled']),
            'dry_run' => !empty($config['dry_run']),
            'provider' => tejaratVerifyProviderLabel($config['provider'] ?? ''),
            'provider_key' => strtolower(trim((string)($config['provider'] ?? ''))),
            'poll_interval_seconds' => intval($config['poll_interval_seconds'] ?? 60),
            'connection_status' => (string)($state['connection_status'] ?? 'disabled'),
            'last_poll_at' => intval($state['last_poll_at'] ?? 0),
            'last_success_at' => intval($state['last_success_at'] ?? 0),
            'last_error' => (string)($state['last_error'] ?? ''),
            'stats' => $state['stats'] ?? tejaratVerifyDefaultState()['stats'],
            'android_sms_fallback_hint' => 'در صورت نبود API پایدار: پیامک واریز تجارت را روی اندروید بخوانید و به webhook امن (مشابه بله) بفرستید.',
        ];
    }

    function tejaratVerifyFormatTimestamp($ts){
        $ts = intval($ts);

        if($ts <= 0){
            return '—';
        }

        if(function_exists('pnvFormatPaymentRowDateTime')){
            return pnvFormatPaymentRowDateTime($ts);
        }

        return date('Y-m-d H:i:s', $ts);
    }
}

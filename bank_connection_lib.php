<?php
/**
 * Tejarat Bank read-only transaction feed: signed, exact matching and
 * conservative crash-safe claims. Does not automate login or transfer money.
 * Production configuration is intentionally OUTSIDE the public web root.
 */
if (!function_exists('pnvBankConfigPath')) {
    function pnvBankConfigPath() {
        $value = getenv('PNV_BANK_CONNECTION_CONFIG_PATH');
        return is_string($value) && $value !== '' ? $value : '/var/lib/pnv-panel/bank-connection.json';
    }
    function pnvBankLedgerPath() {
        $value = getenv('PNV_BANK_CONNECTION_LEDGER_PATH');
        return is_string($value) && $value !== '' ? $value : '/var/lib/pnv-panel/bank-transactions.json';
    }
    function pnvBankDefaultConfig() {
        return [
            'enabled' => false,
            'dry_run' => true,
            'provider' => 'signed_readonly_feed',
            'destination_card' => '',
            'shared_secret' => '',
            'max_age_seconds' => 7200,
        ];
    }
    function pnvBankLoadConfig() {
        $path = pnvBankConfigPath();
        if (!is_file($path)) return pnvBankDefaultConfig();
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? array_merge(pnvBankDefaultConfig(), $data) : pnvBankDefaultConfig();
    }
    function pnvBankDigits($value) {
        $value = strtr((string)$value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        return preg_replace('/[^0-9]/', '', $value);
    }
    function pnvBankSaveJson($path, array $data) {
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) return false;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;
        $tmp = tempnam($dir, '.bank-');
        if ($tmp === false) return false;
        chmod($tmp, 0600);
        $ok = file_put_contents($tmp, $json, LOCK_EX) === strlen($json);
        if ($ok) $ok = rename($tmp, $path);
        if (!$ok) @unlink($tmp);
        return $ok;
    }
    function pnvBankSaveConfig(array $config) {
        if (!is_string($config['shared_secret'] ?? null)) return false;
        $card = pnvBankDigits($config['destination_card'] ?? '');
        if ($card !== '' && strlen($card) !== 16) return false;
        if (!empty($config['enabled']) && ($card === '' || strlen($config['shared_secret']) < 32)) return false;
        $safe = array_merge(pnvBankDefaultConfig(), [
            'enabled' => !empty($config['enabled']),
            'dry_run' => !empty($config['dry_run']),
            'provider' => 'signed_readonly_feed',
            'destination_card' => $card,
            'shared_secret' => (string)$config['shared_secret'],
            'max_age_seconds' => max(300, min(86400, intval($config['max_age_seconds'] ?? 7200))),
        ]);
        return pnvBankSaveJson(pnvBankConfigPath(), $safe);
    }
    function pnvBankVerifySignature($raw, $ts, $signature, array $config, $now = null) {
        $now = $now ?? time();
        $secret = (string)($config['shared_secret'] ?? '');
        if (strlen($secret) < 32 || !is_string($raw) || strlen($raw) > 131072 ||
            !is_scalar($ts) || !preg_match('/^[0-9]{10}$/D', (string)$ts) ||
            abs($now - intval($ts)) > 300 ||
            !is_string($signature) || !preg_match('/^[a-f0-9]{64}$/D', $signature)) return false;
        return hash_equals(hash_hmac('sha256', (string)$ts . "\n" . $raw, $secret), $signature);
    }
    function pnvBankNormalizeTransaction($input, array $config, $now = null) {
        $now = $now ?? time();
        if (!is_array($input) || ($input['direction'] ?? '') !== 'credit') return null;
        // Amount must be a positive integral rial value, not a floating approximation.
        $amount = $input['amount_rial'] ?? null;
        if (!is_int($amount) && !(is_string($amount) && preg_match('/^[1-9][0-9]{0,14}$/D', $amount))) return null;
        $amount = intval($amount);
        $card = pnvBankDigits($input['destination_card'] ?? '');
        $registeredCard = pnvBankDigits($config['destination_card'] ?? '');
        $reference = trim((string)($input['transaction_id'] ?? ''));
        $when = $input['occurred_at'] ?? 0;
        $maxAge = max(300, min(86400, intval($config['max_age_seconds'] ?? 7200)));
        if ($amount <= 0 || $card === '' || strlen($card) !== 16 || $card !== $registeredCard ||
            !preg_match('/^[A-Za-z0-9._:\\/-]{6,96}$/D', $reference) ||
            !is_int($when) || $when > $now + 60 || $when < $now - $maxAge) return null;
        return [
            'transaction_id' => $reference,
            'amount_rial' => $amount,
            'destination_card' => $card,
            'occurred_at' => $when,
        ];
    }
    function pnvBankSelectOrder(array $tx, array $items, $now = null) {
        $now = $now ?? time();
        $hits = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id'])) continue;
            if (!function_exists('instantPayItemMatchable') || !instantPayItemMatchable($item, $now)) continue;
            $amount = function_exists('instantPayNormalizeItemAmountRial')
                ? instantPayNormalizeItemAmountRial($item) : intval($item['amount'] ?? 0);
            if ($amount !== $tx['amount_rial']) continue;
            if (pnvBankDigits($item['card'] ?? '') !== $tx['destination_card']) continue;
            $created = intval($item['created_at'] ?? 0);
            if ($created <= 0 || $tx['occurred_at'] < $created - 120) continue;
            if ($tx['occurred_at'] > intval($item['expires_at'] ?? 0) + 1800) continue;
            $hits[] = $item;
        }
        if (count($hits) !== 1) return ['ok'=>false, 'code'=>count($hits) ? 'ambiguous_match' : 'no_match'];
        return ['ok'=>true, 'order'=>$hits[0]];
    }
    /**
     * Signature is transport authentication; it does not by itself prove
     * a bank-originated deposit. Only enable LIVE after vetting bridge source.
     */
    function pnvBankHandleSignedDeposit($raw, $timestamp, $signature, $now = null) {
        $now = $now ?? time();
        $config = pnvBankLoadConfig();
        if (empty($config['enabled'])) return ['ok'=>false,'code'=>'disabled'];
        if (!pnvBankVerifySignature($raw, $timestamp, $signature, $config, $now))
            return ['ok'=>false,'code'=>'unauthorized'];
        $payload = json_decode($raw, true);
        $tx = pnvBankNormalizeTransaction($payload, $config, $now);
        if ($tx === null) return ['ok'=>false,'code'=>'invalid_transaction'];
        require_once __DIR__ . '/instant_pay_lib.php';
        $path = pnvBankLedgerPath();
        if (!is_dir(dirname($path)) || !is_writable(dirname($path)))
            return ['ok'=>false,'code'=>'storage_unavailable'];
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            return ['ok'=>false,'code'=>'lock_failed'];
        }
        try {
            $ledger = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
            if (!is_array($ledger)) return ['ok'=>false,'code'=>'ledger_corrupt'];
            $key = hash('sha256', 'tejarat|' . $tx['transaction_id'] . '|' . $tx['destination_card']);
            if (array_key_exists($key, $ledger)) return ['ok'=>false,'code'=>'duplicate_transaction'];
            $match = pnvBankSelectOrder($tx, instantPayLoad(), $now);
            if (empty($match['ok'])) return $match;
            $order = $match['order'];
            if (!empty($config['dry_run'])) {
                return ['ok'=>true,'dry_run'=>true,'matched_order_id'=>$order['id']];
            }
            $ledger[$key] = [
                'state'=>'claimed', 'order_id'=>$order['id'],
                'amount_rial'=>$tx['amount_rial'],'claimed_at'=>$now,
            ];
            if (!pnvBankSaveJson($path, $ledger)) return ['ok'=>false,'code'=>'persist_failed'];
            try {
                if (isset($GLOBALS['pnvBankTestMarkPaid']) && is_callable($GLOBALS['pnvBankTestMarkPaid'])) {
                    $result = $GLOBALS['pnvBankTestMarkPaid']($order['id'], $tx);
                } else {
                    $result = instantPayMarkPaid($order['id'], ['amount'=>$tx['amount_rial'], 'text'=>'Tejarat verified transaction']);
                }
            } catch (Throwable $e) {
                $result = ['ok'=>false,'code'=>'provision_exception'];
            }
            $ledger[$key]['state'] = !empty($result['ok']) ? 'confirmed' : 'manual_review';
            $ledger[$key]['completed_at'] = time();
            if (!pnvBankSaveJson($path, $ledger)) {
                return ['ok'=>false,'code'=>'ledger_commit_failed','manual_review'=>true];
            }
            return !empty($result['ok'])
                ? ['ok'=>true,'dry_run'=>false,'matched_order_id'=>$order['id']]
                : ['ok'=>false,'code'=>'manual_review','manual_review'=>true];
        } finally {
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}

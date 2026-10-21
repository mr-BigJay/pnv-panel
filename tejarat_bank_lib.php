<?php
/**
 * Bank Tejarat: signed transaction-feed receiver and exact instant-pay matching.
 *
 * This is NOT a Tejarat Internet Banking scraper or an official bank API client.
 * A bank-authorized transaction feed must deliver settled credits over HTTPS,
 * signed with the pre-shared HMAC key. No bank password, OTP, or session cookies.
 *
 * Config/ledger default outside the public document root.
 */
if (!function_exists('tejaratBankConfigPath')) {
    function tejaratBankBaseDir() {
        $env = trim((string)getenv('PNV_TEJARAT_BANK_DATA_DIR'));
        return $env !== '' ? rtrim($env, '/') : dirname(__DIR__) . '/pnv-bank';
    }

    function tejaratBankConfigPath() { return tejaratBankBaseDir() . '/config.json'; }
    function tejaratBankLedgerPath() { return tejaratBankBaseDir() . '/ledger.json'; }
    function tejaratBankLockPath() { return tejaratBankBaseDir() . '/claims.lock'; }

    function tejaratBankDefaults() {
        return [
            'enabled' => false,
            'dry_run' => true,
            'provider' => 'signed_feed',
            'destination_cards' => [],
            'webhook_secret' => '',
            'last_verified_at' => 0,
        ];
    }

    function tejaratBankLoadConfig() {
        $raw = @file_get_contents(tejaratBankConfigPath());
        $saved = is_string($raw) ? json_decode($raw, true) : null;
        return array_merge(tejaratBankDefaults(), is_array($saved) ? $saved : []);
    }

    function tejaratBankWriteJson($path, array $data) {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) return false;
        $tmp = @tempnam($dir, '.pnv-bank-');
        if ($tmp === false) return false;
        @chmod($tmp, 0600);
        $ok = @file_put_contents($tmp, $json, LOCK_EX);
        if ($ok !== strlen($json) || !@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @chmod($path, 0600);
        return true;
    }

    function tejaratBankCard($value) {
        $s = strtr((string)$value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'
        ]);
        return preg_replace('/[^0-9]/', '', $s);
    }

    function tejaratBankSaveSettings(array $input) {
        $old = tejaratBankLoadConfig();
        $lines = preg_split('/[\r\n,;]+/', (string)($input['destination_cards'] ?? '')) ?: [];
        $cards = [];
        foreach ($lines as $line) {
            $c = tejaratBankCard($line);
            if ($c === '') continue;
            if (!preg_match('/^[0-9]{16}$/', $c)) return ['ok'=>false,'error'=>'شماره کارت مقصد باید ۱۶ رقم باشد'];
            $cards[$c] = true;
        }
        $secret = trim((string)($input['webhook_secret'] ?? ''));
        if ($secret === '') $secret = (string)($old['webhook_secret'] ?? '');
        if ($secret !== '' && strlen($secret) < 32) return ['ok'=>false,'error'=>'کلید اتصال باید حداقل ۳۲ کاراکتر تصادفی باشد'];
        $enabled = !empty($input['enabled']);
        if ($enabled && (!$cards || $secret === '')) {
            return ['ok'=>false,'error'=>'برای فعال‌سازی، کارت مقصد و کلید اتصال الزامی‌اند'];
        }
        $changed = !hash_equals((string)($old['webhook_secret'] ?? ''), $secret)
            || array_keys($cards) !== (array)($old['destination_cards'] ?? []);
        $config = [
            'enabled' => $enabled,
            // A newly paired source must first submit a valid dry-run event.
            'dry_run' => $changed ? true : !empty($input['dry_run']),
            'provider' => 'signed_feed',
            'destination_cards' => array_keys($cards),
            'webhook_secret' => $secret,
            'last_verified_at' => $changed ? 0 : intval($old['last_verified_at'] ?? 0),
        ];
        if (!$config['dry_run'] && intval($config['last_verified_at']) <= 0) {
            return ['ok'=>false,'error'=>'قبل از فعال‌سازی تأیید واقعی، یک رویداد بانکی معتبر در حالت آزمایشی دریافت کنید'];
        }
        return tejaratBankWriteJson(tejaratBankConfigPath(), $config)
            ? ['ok'=>true,'config'=>$config]
            : ['ok'=>false,'error'=>'محل ذخیره امن تنظیمات قابل نوشتن نیست'];
    }

    function tejaratBankAuthenticate($rawBody, $timestamp, $signature, $secret, $now = null) {
        $now = $now ?? time();
        $timestamp = (string)$timestamp;
        if (!preg_match('/^[0-9]{10,11}$/', $timestamp) || abs($now - (int)$timestamp) > 300) return false;
        if (!is_string($secret) || strlen($secret) < 32) return false;
        if (!preg_match('/^sha256=([a-f0-9]{64})$/i', (string)$signature, $m)) return false;
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        return hash_equals($expected, strtolower($m[1]));
    }

    function tejaratBankValidateEvent($event, array $config, $now = null) {
        $now = $now ?? time();
        if (!is_array($event)) return ['ok'=>false,'code'=>'malformed'];
        // Never guess tomans, accept screenshots, or treat a pending credit as settled.
        if (($event['currency'] ?? '') !== 'IRR'
            || ($event['direction'] ?? '') !== 'credit'
            || ($event['status'] ?? '') !== 'settled') {
            return ['ok'=>false,'code'=>'not_settled_credit'];
        }
        $amount = $event['amount_rial'] ?? null;
        if ((!is_int($amount) && !(is_string($amount) && preg_match('/^[1-9][0-9]*$/', $amount)))
            || intval($amount) <= 0) return ['ok'=>false,'code'=>'bad_amount'];
        $id = trim((string)($event['transaction_id'] ?? ''));
        $card = tejaratBankCard($event['destination_card'] ?? '');
        $occurred = $event['occurred_at'] ?? null;
        if (!preg_match('/^[A-Za-z0-9_.:\/-]{6,120}$/', $id)
            || !preg_match('/^[0-9]{16}$/', $card)
            || (!is_int($occurred) && !(is_string($occurred) && ctype_digit($occurred)))
            || intval($occurred) < ($now - 86400) || intval($occurred) > ($now + 60)) {
            return ['ok'=>false,'code'=>'invalid_receipt'];
        }
        if (!in_array($card, (array)($config['destination_cards'] ?? []), true)) {
            return ['ok'=>false,'code'=>'wrong_destination_card'];
        }
        return ['ok'=>true,'event'=>[
            'amount_rial'=>(int)$amount,
            'transaction_id'=>$id,
            'destination_card'=>$card,
            'occurred_at'=>(int)$occurred,
        ]];
    }

    function tejaratBankSelectOrder(array $orders, array $event) {
        require_once __DIR__ . '/instant_pay_lib.php';
        $matches = [];
        $occurred = $event['occurred_at'];
        foreach ($orders as $order) {
            if (!is_array($order)) continue;
            if (!in_array((string)($order['status'] ?? ''), ['waiting', 'expired'], true)) continue;
            if (tejaratBankCard($order['card'] ?? '') !== $event['destination_card']) continue;
            if (instantPayNormalizeItemAmountRial($order) !== $event['amount_rial']) continue;
            if (empty($order['id']) || empty($order['created_at']) || empty($order['expires_at'])) continue;
            if ($occurred < intval($order['created_at']) - 60
                || $occurred > intval($order['expires_at']) + instantPayMatchGraceSeconds()) continue;
            $matches[] = $order;
        }
        if (count($matches) !== 1) {
            return ['ok'=>false,'code'=>count($matches) ? 'ambiguous_order' : 'no_matching_order'];
        }
        return ['ok'=>true,'order'=>$matches[0]];
    }

    function tejaratBankClaimEvent(array $rawEvent, array $config, $apply = true) {
        require_once __DIR__ . '/instant_pay_lib.php';
        $valid = tejaratBankValidateEvent($rawEvent, $config);
        if (empty($valid['ok'])) return $valid;
        $event = $valid['event'];
        $key = hash('sha256', 'tejarat|' . $event['destination_card'] . '|' . $event['transaction_id']);
        $dir = tejaratBankBaseDir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return ['ok'=>false,'code'=>'storage_unavailable'];
        }
        $fp = @fopen(tejaratBankLockPath(), 'c');
        if (!$fp || !flock($fp, LOCK_EX)) return ['ok'=>false,'code'=>'lock_failed'];
        try {
            $rawLedger = @file_get_contents(tejaratBankLedgerPath());
            $ledger = is_string($rawLedger) ? json_decode($rawLedger, true) : [];
            if (!is_array($ledger)) return ['ok'=>false,'code'=>'ledger_corrupt'];
            if (isset($ledger[$key])) {
                return ['ok'=>false,'code'=>'duplicate_or_pending_review'];
            }
            $match = tejaratBankSelectOrder(instantPayLoad(), $event);
            if (empty($match['ok'])) return $match;
            $order = $match['order'];
            if (!$apply) return ['ok'=>true,'dry_run'=>true,'order_id'=>$order['id']];
            // Claim before provisioning: failed/ambiguous outcomes need manual review,
            // never replay a possibly successful 3x-ui mutation automatically.
            $ledger[$key] = [
                'status'=>'processing', 'order_id'=>$order['id'],
                'at'=>time(), 'amount_rial'=>$event['amount_rial']
            ];
            if (!tejaratBankWriteJson(tejaratBankLedgerPath(), $ledger)) {
                return ['ok'=>false,'code'=>'claim_persist_failed'];
            }
            $meta = [
                'amount'=>$event['amount_rial'],
                'text'=>'Trusted Tejarat signed feed; tx=' . $event['transaction_id'],
            ];
            $res = (!empty($GLOBALS['tejaratBankTestApproval']) && is_callable($GLOBALS['tejaratBankTestApproval']))
                ? $GLOBALS['tejaratBankTestApproval']($order['id'], $meta)
                : instantPayMarkPaid($order['id'], $meta);
            $ledger[$key]['status'] = !empty($res['ok']) ? 'approved' : 'needs_review';
            $ledger[$key]['updated_at'] = time();
            // A failed final state write is still safe: processing claim remains.
            tejaratBankWriteJson(tejaratBankLedgerPath(), $ledger);
            return [
                'ok'=>!empty($res['ok']),
                'code'=>!empty($res['ok']) ? 'approved' : 'needs_review',
                'order_id'=>$order['id'],
            ];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    function tejaratBankProcessSignedPayload($rawBody, $timestamp, $signature) {
        $config = tejaratBankLoadConfig();
        if (empty($config['enabled'])) return ['ok'=>false,'code'=>'disabled'];
        if (strlen($rawBody) > 8192) return ['ok'=>false,'code'=>'payload_too_large'];
        if (!tejaratBankAuthenticate($rawBody, $timestamp, $signature, (string)$config['webhook_secret'])) {
            return ['ok'=>false,'code'=>'unauthorized'];
        }
        $event = json_decode($rawBody, true);
        $validated = tejaratBankValidateEvent($event, $config);
        if (empty($validated['ok'])) return $validated;
        if (intval($config['last_verified_at'] ?? 0) <= 0) {
            $config['last_verified_at'] = time();
            tejaratBankWriteJson(tejaratBankConfigPath(), $config);
        }
        return tejaratBankClaimEvent($event, $config, empty($config['dry_run']));
    }
}

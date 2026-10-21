<?php

require_once __DIR__ . '/bale_lib.php';
require_once __DIR__ . '/pnv_date_bootstrap.php';

if(!function_exists('nomadConfigPath')){

    function nomadConfigPath(){
        $override = getenv('PNV_NOMAD_CONFIG_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/nomad_gateway.json';
    }

    function nomadInboxPath(){
        $override = getenv('PNV_NOMAD_INBOX_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/nomad_sms_inbox.json';
    }

    function nomadDefaultConfig(){
        return [
            'enabled' => false,
            'dry_run' => true,
            'webhook_token' => '',
            'allowed_senders' => [],
            'accept_unknown_sender' => true,
            'max_inbox_entries' => 500,
            'header_name' => 'X-Nomad-Token',
        ];
    }

    function nomadLoadConfig(){
        $path = nomadConfigPath();
        $defaults = nomadDefaultConfig();

        if(!is_file($path)){
            return $defaults;
        }

        $data = json_decode((string)file_get_contents($path), true);

        if(!is_array($data)){
            return $defaults;
        }

        return array_merge($defaults, $data);
    }

    function nomadSaveConfig(array $config){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        $path = nomadConfigPath();
        file_put_contents(
            $path,
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function nomadGenerateToken(){
        return bin2hex(random_bytes(24));
    }

    function nomadWebhookPublicUrl(){
        $base = getenv('PNV_PUBLIC_BASE_URL');

        if(is_string($base) && trim($base) !== ''){
            return rtrim(trim($base), '/') . '/nomad-webhook.php';
        }

        return 'https://panel.ticketin.ir/bigjay_controller/nomad-webhook.php';
    }

    function nomadLoadInbox(){
        $path = nomadInboxPath();

        if(!is_file($path)){
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    function nomadSaveInbox(array $items){
        if(!is_dir(__DIR__ . '/db')){
            @mkdir(__DIR__ . '/db', 0755, true);
        }

        file_put_contents(
            nomadInboxPath(),
            json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    function nomadNewInboxId(){
        return 'sms_' . bin2hex(random_bytes(8));
    }

    function nomadFindInboxEntry($id){
        $id = trim((string)$id);

        foreach(nomadLoadInbox() as $entry){
            if(($entry['id'] ?? '') === $id){
                return $entry;
            }
        }

        return null;
    }

    function nomadExtractBearerToken($headerValue){
        $headerValue = trim((string)$headerValue);

        if($headerValue === ''){
            return '';
        }

        if(preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $headerValue, $m)){
            return trim($m[1]);
        }

        return $headerValue;
    }

    function nomadRequestAuthToken(array $config){
        $expected = trim((string)($config['webhook_token'] ?? ''));

        if($expected === ''){
            return '';
        }

        $headerName = trim((string)($config['header_name'] ?? 'X-Nomad-Token'));

        if($headerName === ''){
            $headerName = 'X-Nomad-Token';
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));

        if(!empty($_SERVER[$serverKey])){
            return nomadExtractBearerToken((string)$_SERVER[$serverKey]);
        }

        foreach(['HTTP_X_NOMAD_TOKEN', 'HTTP_X_WEBHOOK_TOKEN', 'HTTP_AUTHORIZATION'] as $fallback){
            if(empty($_SERVER[$fallback])){
                continue;
            }

            $token = nomadExtractBearerToken((string)$_SERVER[$fallback]);

            if($token !== ''){
                return $token;
            }
        }

        return '';
    }

    function nomadAuthOk(array $config){
        $expected = trim((string)($config['webhook_token'] ?? ''));

        if($expected === ''){
            return false;
        }

        $provided = nomadRequestAuthToken($config);

        if($provided === ''){
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Nomad Gateway + aliases (from, text, sentStamp, receivedStamp, sim).
     */
    function nomadNormalizeGatewayPayload($payload){
        if(!is_array($payload)){
            return null;
        }

        $text = trim((string)(
            $payload['text']
            ?? $payload['message']
            ?? $payload['body']
            ?? ($payload['payload']['message'] ?? '')
            ?? ($payload['payload']['text'] ?? '')
        ));

        $sender = trim((string)(
            $payload['from']
            ?? $payload['sender']
            ?? $payload['phoneNumber']
            ?? ($payload['payload']['phoneNumber'] ?? '')
            ?? ($payload['payload']['sender'] ?? '')
        ));

        $sim = $payload['sim']
            ?? $payload['simId']
            ?? $payload['simNumber']
            ?? ($payload['payload']['simNumber'] ?? null);

        $sentStamp = $payload['sentStamp']
            ?? $payload['sent_at']
            ?? ($payload['payload']['sentAt'] ?? null);

        $receivedStamp = $payload['receivedStamp']
            ?? $payload['received_at']
            ?? ($payload['payload']['receivedAt'] ?? null);

        $messageId = trim((string)(
            $payload['messageId']
            ?? $payload['id']
            ?? ($payload['payload']['messageId'] ?? '')
        ));

        if($text === ''){
            return null;
        }

        $depositTs = 0;

        foreach([$receivedStamp, $sentStamp] as $stamp){
            if($stamp === null || $stamp === ''){
                continue;
            }

            if(is_numeric($stamp)){
                $n = intval($stamp);

                if($n > 9999999999){
                    $depositTs = intdiv($n, 1000);
                }
                elseif($n > 0){
                    $depositTs = $n;
                }

                if($depositTs > 0){
                    break;
                }
            }

            if(is_string($stamp)){
                $parsed = function_exists('pnvParseDateTimeToTimestamp')
                    ? pnvParseDateTimeToTimestamp($stamp)
                    : strtotime($stamp);

                if($parsed > 0){
                    $depositTs = $parsed;
                    break;
                }
            }
        }

        return [
            'sender' => $sender,
            'text' => $text,
            'sim' => $sim,
            'sent_stamp' => $sentStamp,
            'received_stamp' => $receivedStamp,
            'deposit_ts' => $depositTs,
            'message_id' => $messageId,
            'raw_keys' => array_keys($payload),
        ];
    }

    function nomadParsePostBankSmsFields($text){
        $text = baleNormalizeBankText($text);
        $out = [
            'card_last4' => '',
            'date' => '',
            'time' => '',
            'deposit_ts' => 0,
            'amounts' => [],
        ];

        if($text === ''){
            return $out;
        }

        if(preg_match('/واریز\s*به\s*کارت\s*:?\s*(\d{4})/u', $text, $m)){
            $out['card_last4'] = $m[1];
        }

        if(preg_match('/^(\d{4}\/\d{1,2}\/\d{1,2})\s*$/mu', $text, $m)){
            $out['date'] = trim($m[1]);
        }

        if(preg_match('/^(\d{1,2}:\d{2})\s*$/mu', $text, $m)){
            $out['time'] = trim($m[1]);
        }

        if($out['date'] !== ''){
            $dt = $out['date'];

            if($out['time'] !== ''){
                $dt .= ' ' . $out['time'];
            }

            $ts = function_exists('pnvParseJalaliDateTime')
                ? pnvParseJalaliDateTime($dt)
                : 0;

            if($ts > 0){
                $out['deposit_ts'] = $ts;
            }
        }

        $out['amounts'] = baleExtractRialAmounts($text);

        return $out;
    }

    function nomadDedupeKey(array $gateway, array $parse){
        $parts = [
            trim((string)($gateway['sender'] ?? '')),
            trim((string)($gateway['message_id'] ?? '')),
            trim((string)($parse['card_last4'] ?? '')),
            implode(',', (array)($parse['amounts'] ?? [])),
            trim((string)($parse['date'] ?? '')),
            trim((string)($parse['time'] ?? '')),
            hash('sha256', trim((string)($gateway['text'] ?? ''))),
        ];

        return hash('sha256', implode('|', $parts));
    }

    function nomadInboxHasDedupeKey($key){
        $key = trim((string)$key);

        if($key === ''){
            return false;
        }

        foreach(nomadLoadInbox() as $entry){
            if(($entry['dedupe_key'] ?? '') === $key){
                return true;
            }
        }

        return false;
    }

    function nomadTrimInbox(array $items, $max){
        $max = max(50, intval($max));
        usort($items, static function($a, $b){
            return intval($b['received_at'] ?? 0) <=> intval($a['received_at'] ?? 0);
        });

        return array_slice($items, 0, $max);
    }

    function nomadSenderAllowed($sender, array $config){
        $sender = preg_replace('/\D+/', '', (string)$sender);
        $allowed = $config['allowed_senders'] ?? [];

        if(!is_array($allowed) || count($allowed) === 0){
            return !empty($config['accept_unknown_sender']);
        }

        foreach($allowed as $one){
            $one = preg_replace('/\D+/', '', (string)$one);

            if($one !== '' && $one === $sender){
                return true;
            }
        }

        return false;
    }

    function nomadMatchStatusLabel($status){
        $map = [
            'matched' => 'تطبیق شد',
            'unmatched' => 'بدون تطبیق',
            'ambiguous' => 'مبهم (چند سفارش)',
            'duplicate' => 'تکراری',
            'ignored' => 'نادیده',
            'stale' => 'قدیمی',
            'parse_error' => 'خطای پارس',
            'dry_run' => 'آزمایشی (بدون فعال‌سازی)',
            'applied' => 'اعمال شد (ادمین)',
        ];

        return $map[(string)$status] ?? (string)$status;
    }

    function nomadProcessInboundSms(array $gateway, array $config){
        if(empty($config['enabled'])){
            return ['ok' => false, 'error' => 'nomad disabled', 'http' => 503];
        }

        $text = trim((string)($gateway['text'] ?? ''));

        if($text === ''){
            return ['ok' => false, 'error' => 'متن خالی', 'http' => 400];
        }

        if(!nomadSenderAllowed($gateway['sender'] ?? '', $config)){
            return [
                'ok' => true,
                'ignored' => true,
                'match_status' => 'ignored',
                'error' => 'فرستنده مجاز نیست',
            ];
        }

        if(!function_exists('baleLooksLikeDeposit') || !baleLooksLikeDeposit($text)){
            return [
                'ok' => true,
                'ignored' => true,
                'match_status' => 'ignored',
                'error' => 'شبیه پیام واریز نیست',
            ];
        }

        if(function_exists('baleLooksLikePostBankNotice') && !baleLooksLikePostBankNotice($text)){
            return [
                'ok' => true,
                'ignored' => true,
                'match_status' => 'ignored',
                'error' => 'فقط پیام پست‌بانک پذیرفته می‌شود',
            ];
        }

        $fields = nomadParsePostBankSmsFields($text);
        $depositTs = intval($gateway['deposit_ts'] ?? 0);

        if($depositTs <= 0){
            $depositTs = intval($fields['deposit_ts'] ?? 0);
        }

        if(count($fields['amounts'] ?? []) === 0){
            return [
                'ok' => false,
                'error' => 'مبلغ واریز در پیامک پیدا نشد',
                'match_status' => 'parse_error',
                'http' => 422,
            ];
        }

        $rialOnly = true;
        $amountCandidates = function_exists('instantPayExpandAmountCandidates')
            ? instantPayExpandAmountCandidates($fields['amounts'], ['rial_only' => $rialOnly])
            : array_map('intval', $fields['amounts']);

        $parse = [
            'amounts' => $fields['amounts'],
            'amount_candidates' => $amountCandidates,
            'card_last4' => $fields['card_last4'],
            'date' => $fields['date'],
            'time' => $fields['time'],
            'deposit_ts' => $depositTs,
        ];

        $dedupeKey = nomadDedupeKey($gateway, $parse);

        if(nomadInboxHasDedupeKey($dedupeKey)){
            $entry = [
                'id' => nomadNewInboxId(),
                'received_at' => time(),
                'gateway' => $gateway,
                'parse' => $parse,
                'text' => $text,
                'match_status' => 'duplicate',
                'dedupe_key' => $dedupeKey,
                'error' => 'پیامک تکراری',
            ];
            nomadAppendInbox($entry, $config);

            return [
                'ok' => true,
                'duplicate' => true,
                'match_status' => 'duplicate',
                'inbox_id' => $entry['id'],
            ];
        }

        if(!function_exists('instantPayFindNomadOrderMatches')){
            require_once __DIR__ . '/instant_pay_lib.php';
        }

        $matchResult = instantPayFindNomadOrderMatches($parse);
        $status = (string)($matchResult['status'] ?? 'unmatched');
        $matches = $matchResult['matches'] ?? [];

        if($status === 'stale'){
            $entry = nomadBuildInboxEntry($gateway, $parse, $text, $dedupeKey, $status, $matchResult);
            nomadAppendInbox($entry, $config);

            return [
                'ok' => true,
                'match_status' => 'stale',
                'inbox_id' => $entry['id'],
                'error' => $matchResult['error'] ?? 'پیامک قدیمی',
            ];
        }

        if($status === 'ambiguous'){
            $entry = nomadBuildInboxEntry($gateway, $parse, $text, $dedupeKey, 'ambiguous', $matchResult);
            nomadAppendInbox($entry, $config);

            return [
                'ok' => true,
                'match_status' => 'ambiguous',
                'inbox_id' => $entry['id'],
                'candidates' => $matches,
            ];
        }

        if($status === 'unmatched'){
            $entry = nomadBuildInboxEntry($gateway, $parse, $text, $dedupeKey, 'unmatched', $matchResult);
            nomadAppendInbox($entry, $config);

            return [
                'ok' => true,
                'match_status' => 'unmatched',
                'inbox_id' => $entry['id'],
                'error' => $matchResult['error'] ?? 'سفارش بازی پیدا نشد',
            ];
        }

        $dryRun = !isset($config['dry_run']) || !empty($config['dry_run']);
        $finalStatus = $dryRun ? 'dry_run' : 'matched';
        $entry = nomadBuildInboxEntry($gateway, $parse, $text, $dedupeKey, $finalStatus, $matchResult);
        nomadAppendInbox($entry, $config);

        return [
            'ok' => true,
            'match_status' => $finalStatus,
            'inbox_id' => $entry['id'],
            'matched_order_id' => $entry['match_order_id'] ?? '',
            'dry_run' => $dryRun,
            'note' => 'پیامک به‌تنهایی اشتراک را فعال نمی‌کند؛ از ادمین تأیید کنید.',
        ];
    }

    function nomadBuildInboxEntry(array $gateway, array $parse, $text, $dedupeKey, $status, array $matchResult){
        $match = is_array($matchResult['match'] ?? null) ? $matchResult['match'] : null;

        return [
            'id' => nomadNewInboxId(),
            'received_at' => time(),
            'sender' => $gateway['sender'] ?? '',
            'sim' => $gateway['sim'] ?? '',
            'text' => $text,
            'gateway' => [
                'message_id' => $gateway['message_id'] ?? '',
                'sent_stamp' => $gateway['sent_stamp'] ?? null,
                'received_stamp' => $gateway['received_stamp'] ?? null,
            ],
            'parse' => $parse,
            'match_status' => $status,
            'match_order_id' => $match['id'] ?? '',
            'match_order_user' => $match['user'] ?? '',
            'match_amount' => intval($matchResult['matched_amount'] ?? 0),
            'match_code' => $match ? str_pad((string)intval($match['code'] ?? 0), 4, '0', STR_PAD_LEFT) : '',
            'match_card_last4' => $parse['card_last4'] ?? '',
            'dedupe_key' => $dedupeKey,
            'error' => $matchResult['error'] ?? '',
            'applied_at' => 0,
        ];
    }

    function nomadAppendInbox(array $entry, array $config){
        $items = nomadLoadInbox();
        array_unshift($items, $entry);
        $max = intval($config['max_inbox_entries'] ?? 500);
        nomadSaveInbox(nomadTrimInbox($items, $max));
    }

    function nomadApplyInboxMatch($inboxId, array $config){
        if(!empty($config['dry_run'])){
            return ['ok' => false, 'error' => 'حالت آزمایشی فعال است؛ ابتدا dry_run را خاموش کنید'];
        }

        $entry = nomadFindInboxEntry($inboxId);

        if(!is_array($entry)){
            return ['ok' => false, 'error' => 'رکورد inbox پیدا نشد'];
        }

        $status = (string)($entry['match_status'] ?? '');

        if(!in_array($status, ['matched', 'dry_run'], true)){
            return ['ok' => false, 'error' => 'این پیامک برای اعمال آماده نیست (' . nomadMatchStatusLabel($status) . ')'];
        }

        if(intval($entry['applied_at'] ?? 0) > 0){
            return ['ok' => false, 'error' => 'قبلاً اعمال شده است'];
        }

        if(!function_exists('instantPayTryDepositAmount')){
            require_once __DIR__ . '/instant_pay_lib.php';
        }

        $amount = intval($entry['match_amount'] ?? 0);
        $text = (string)($entry['text'] ?? '');
        $parse = is_array($entry['parse'] ?? null) ? $entry['parse'] : [];
        $meta = [
            'date' => $parse['date'] ?? '',
            'time' => $parse['time'] ?? '',
            'source' => 'nomad_gateway',
        ];

        if($amount <= 0){
            return ['ok' => false, 'error' => 'مبلغ تطبیق نامعتبر است'];
        }

        $result = instantPayTryDepositAmount($amount, $text, $meta);

        if(!is_array($result) || empty($result['ok'])){
            return [
                'ok' => false,
                'error' => is_array($result) ? ($result['error'] ?? 'اعمال ناموفق') : 'اعمال ناموفق',
                'detail' => $result,
            ];
        }

        $items = nomadLoadInbox();

        foreach($items as $i => $row){
            if(($row['id'] ?? '') !== $inboxId){
                continue;
            }

            $items[$i]['match_status'] = 'applied';
            $items[$i]['applied_at'] = time();
            $items[$i]['apply_result'] = [
                'matched_via' => $result['matched_via'] ?? '',
                'order_id' => $result['item']['id'] ?? ($entry['match_order_id'] ?? ''),
            ];
            break;
        }

        nomadSaveInbox($items);

        return ['ok' => true, 'result' => $result];
    }
}

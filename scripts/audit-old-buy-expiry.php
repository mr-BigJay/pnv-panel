<?php
/**
 * Read-only audit of previously purchased timed PNV subscriptions.
 *
 * Usage: sudo php scripts/audit-old-buy-expiry.php --root=/var/www/html
 *
 * Reads the existing payments CSV, InstantPay status and 3x-ui inbound settings.
 * NEVER writes to payments, configuration, 3x-ui clients or subscriptions.
 * Does NOT authorize an automatic expiry change.
 */
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit;
}

function pnvExpiryAuditKey($host, $subId){
    return strtolower(trim((string)$host)) . '|' . trim((string)$subId);
}

function pnvExpiryAuditCsvLink(array $row, $type){
    $candidates = $type === 'تمدید'
        ? [trim((string)($row[1] ?? '')), trim((string)($row[7] ?? ''))]
        : [trim((string)($row[7] ?? ''))];

    foreach($candidates as $candidate){
        $parsed = xuiParseSubLink($candidate);
        if(is_array($parsed) && $parsed['host'] !== '' && $parsed['sub_id'] !== ''){
            return $parsed;
        }
    }
    return null;
}

function pnvExpiryAuditPaidIndex($instant){
    $out = [];
    foreach($instant as $item){
        if(!is_array($item)){
            continue;
        }
        $user = strtolower(trim((string)($item['user'] ?? '')));
        $tracking = trim((string)($item['code'] ?? ''));
        $status = trim((string)($item['status'] ?? ''));
        $paidAt = intval($item['paid_at'] ?? 0);
        if($user === '' || $tracking === '' || $status !== 'paid' || $paidAt <= 0){
            continue;
        }
        $tracking = strpos($tracking, 'AUTO-') === 0 ? $tracking : 'AUTO-' . $tracking;
        $key = $user . '|' . $tracking;
        if(array_key_exists($key, $out)){
            // Duplicate keys: intentionally make the timestamp untrustworthy.
            $out[$key] = null;
        }
        else{
            $out[$key] = $item;
        }
    }
    return $out;
}

function pnvExpiryAuditInbounds($server){
    $response = xuiApiRequest($server, 'GET', '/panel/api/inbounds/list');
    if(empty($response['success'])){
        return ['ok' => false, 'clients' => [], 'reason' => 'inbounds_list_unavailable'];
    }
    $object = $response['obj'] ?? null;
    if(!is_array($object)){
        return ['ok' => false, 'clients' => [], 'reason' => 'inbounds_list_invalid'];
    }
    $inbounds = isset($object['list']) ? $object['list'] : $object;
    if(!is_array($inbounds)){
        return ['ok' => false, 'clients' => [], 'reason' => 'inbounds_list_invalid'];
    }

    $bySub = [];
    $inboundCount = 0;
    $hasSettings = false;
    foreach($inbounds as $inbound){
        if(!is_array($inbound)){
            continue;
        }
        $inboundCount++;
        $settings = $inbound['settings'] ?? null;

        if(!is_array($settings) && !is_string($settings)){
            $id = intval($inbound['id'] ?? 0);
            if($id > 0){
                $detail = xuiApiRequest($server, 'GET', '/panel/api/inbounds/get/' . $id);
                if(!empty($detail['success']) && is_array($detail['obj'] ?? null)){
                    $settings = $detail['obj']['settings'] ?? null;
                }
            }
        }

        if(is_string($settings)){
            $settings = json_decode($settings, true);
        }
        if(!is_array($settings) || !isset($settings['clients']) || !is_array($settings['clients'])){
            continue;
        }
        $hasSettings = true;
        foreach($settings['clients'] as $client){
            if(!is_array($client)){
                continue;
            }
            $subId = trim((string)($client['subId'] ?? ''));
            if($subId === ''){
                continue;
            }
            if(!isset($bySub[$subId])){
                $bySub[$subId] = [];
            }
            $bySub[$subId][] = $client;
        }
    }

    if($inboundCount === 0 || !$hasSettings){
        return ['ok' => false, 'clients' => [], 'reason' => 'inbound_settings_unavailable'];
    }
    return ['ok' => true, 'clients' => $bySub, 'reason' => ''];
}

/**
 * Conservative cross-source renewal matcher.
 * Match by username + subId across host aliases. False positives are
 * intentional: an uncertain renewal must NOT be automatically repaired.
 */
function pnvExpiryAuditRenewalSignals($instantOrders, $reservations){
    $signals = [
        'instant_renewals' => [],
        'reservation_renewals' => [],
        'reservation_sub_ids' => [],
        'unknown_users' => [],
        'ambiguous' => false,
    ];

    foreach($instantOrders as $item){
        if(!is_array($item) || trim((string)($item['type'] ?? '')) !== 'تمدید'){
            continue;
        }
        $user = strtolower(trim((string)($item['user'] ?? '')));
        if($user === ''){
            $signals['ambiguous'] = true;
            continue;
        }
        $matched = false;
        foreach(['sub', 'link'] as $field){
            $p = xuiParseSubLink((string)($item[$field] ?? ''));
            if(is_array($p)){
                $signals['instant_renewals'][$user . '|' . $p['sub_id']] = true;
                $matched = true;
            }
        }
        if(!$matched){
            $signals['unknown_users'][$user] = true;
        }
    }

    foreach($reservations as $item){
        if(!is_array($item)){
            $signals['ambiguous'] = true;
            continue;
        }
        $user = strtolower(trim((string)($item['username'] ?? '')));
        $subId = trim((string)($item['sub_id'] ?? ''));
        if($subId === ''){
            $p = xuiParseSubLink((string)($item['sub_link'] ?? ''));
            $subId = is_array($p) ? $p['sub_id'] : '';
        }
        if($subId !== ''){
            $signals['reservation_sub_ids'][$subId] = true;
            if($user !== ''){
                $signals['reservation_renewals'][$user . '|' . $subId] = true;
            }
        }
        elseif($user !== ''){
            $signals['unknown_users'][$user] = true;
        }
        else{
            $signals['ambiguous'] = true;
        }
    }
    return $signals;
}

function pnvExpiryAuditClassification(array $ctx){
    // This function makes no API calls or state changes.
    if(!empty($ctx['history_conflict'])){
        return 'history_conflict';
    }
    if(!empty($ctx['renewal_sources_unavailable'])){
        return 'renewal_sources_unavailable';
    }
    if(!empty($ctx['renewal_history_unknown'])){
        return 'renewal_history_unknown';
    }
    if(!empty($ctx['renewal_payment_detected'])){
        return 'renewal_payment_detected';
    }
    if(!empty($ctx['renew_reservation_detected'])){
        return 'renew_reservation_detected';
    }
    if(empty($ctx['paid_source_ok'])){
        return 'paid_timestamp_unverified';
    }
    if(empty($ctx['server_ok'])){
        return 'server_read_unavailable';
    }
    if(intval($ctx['matching_clients'] ?? 0) !== 1){
        return intval($ctx['matching_clients'] ?? 0) === 0
            ? 'client_not_found' : 'duplicate_client';
    }
    if(empty($ctx['has_expiry_field'])){
        return 'expiry_field_unavailable';
    }
    if(intval($ctx['current_expiry_ms'] ?? 0) !== 0){
        return 'already_has_expiry';
    }
    if(empty($ctx['enabled'])){
        return 'client_disabled';
    }
    if(empty($ctx['quota_matches'])){
        return 'quota_changed_or_unknown';
    }
    if(intval($ctx['expected_ms'] ?? 0) <= intval($ctx['now_ms'] ?? 0)){
        return 'historical_plan_already_elapsed';
    }
    return 'candidate_review_before_apply';
}

function pnvExpiryAuditMain(array $argv){
    $root = '/var/www/html';
    foreach($argv as $arg){
        if(strpos($arg, '--root=') === 0){
            $root = substr($arg, 7);
        }
    }
    $root = realpath($root);
    if($root === false || !is_file($root . '/xui_lib.php')){
        fwrite(STDERR, "STOP invalid PNV root\n");
        exit(2);
    }
    require_once $root . '/xui_lib.php';
    if(is_file($root . '/subscription_lib.php')){
        require_once $root . '/subscription_lib.php';
    }
    if(is_file($root . '/instant_pay_lib.php')){
        require_once $root . '/instant_pay_lib.php';
    }

    $config = xuiLoadConfig();
    $payments = xuiLoadPayments();
    $paidRecords = function_exists('instantPayLoad') ? instantPayLoad() : null;
    $paidIndex = is_array($paidRecords) ? pnvExpiryAuditPaidIndex($paidRecords) : [];
    // Read renewal reservation queue without touching queue state or locks.
    if(is_file($root . '/renew_reserve_lib.php')){
        require_once $root . '/renew_reserve_lib.php';
    }
    $renewalSourcesAvailable = is_array($paidRecords) && function_exists('renewReserveLoadResult');
    $reservations = [];
    if($renewalSourcesAvailable){
        $loaded = renewReserveLoadResult();
        $renewalSourcesAvailable = is_array($loaded) && !empty($loaded['ok'])
            && is_array($loaded['items'] ?? null);
        if($renewalSourcesAvailable){
            $reservations = $loaded['items'];
        }
    }
    $signals = pnvExpiryAuditRenewalSignals(
        is_array($paidRecords) ? $paidRecords : [],
        $reservations
    );
    $nowMs = (int)floor(microtime(true) * 1000);
    $groups = [];

    foreach($payments as $index => $row){
        if(!is_array($row)){
            continue;
        }
        $type = trim((string)($row[9] ?? ''));
        $parsed = pnvExpiryAuditCsvLink($row, $type);
        if($parsed === null){
            continue;
        }
        $key = pnvExpiryAuditKey($parsed['host'], $parsed['sub_id']);
        if(!isset($groups[$key])){
            $groups[$key] = ['parsed' => $parsed, 'events' => []];
        }
        $groups[$key]['events'][] = [
            'index' => $index,
            'row' => $row,
            'type' => $type,
            'confirmed' => trim((string)($row[6] ?? '')) === 'تایید شد',
        ];
    }

    $snapshotByServer = [];
    $results = [];
    $stats = ['csv_rows' => count($payments), 'linked_subscriptions' => count($groups),
        'timed_confirmed_buys' => 0, 'candidate_review_before_apply' => 0];
    foreach($groups as $key => $group){
        $events = $group['events'];
        $buys = array_values(array_filter($events, static function($e){
            return $e['type'] === 'خرید' && $e['confirmed'];
        }));
        if(count($buys) === 0){
            continue;
        }
        // One original purchase only, no later/extra payments on this subscription.
        $event = $buys[0];
        $row = $event['row'];
        $duration = xuiPlanDurationMeta(trim((string)($row[2] ?? '')));
        $days = !empty($duration['resolved']) && empty($duration['time_unlimited'])
            ? intval($duration['days'] ?? 0) : 0;
        if(!in_array($days, [30, 90], true)){
            continue;
        }
        $stats['timed_confirmed_buys']++;
        $parsed = $group['parsed'];
        $server = xuiFindServerByHost($parsed['host'], $config);
        $serverId = is_array($server) ? (string)($server['id'] ?? 'unknown') : 'unknown';
        $serverKey = is_array($server) ? $serverId . '|' . $parsed['host'] : $parsed['host'];
        $serverOk = false;
        $clients = [];
        $readReason = 'server_not_configured';
        if(is_array($server)){
            if(!isset($snapshotByServer[$serverKey])){
                $snapshotByServer[$serverKey] = pnvExpiryAuditInbounds($server);
            }
            $snapshot = $snapshotByServer[$serverKey];
            $serverOk = !empty($snapshot['ok']);
            $clients = $serverOk ? ($snapshot['clients'][$parsed['sub_id']] ?? []) : [];
            $readReason = (string)($snapshot['reason'] ?? '');
        }

        $client = count($clients) === 1 ? $clients[0] : [];
        $user = strtolower(trim((string)($row[0] ?? '')));
        $tracking = trim((string)($row[3] ?? ''));
        $paid = $paidIndex[$user . '|' . $tracking] ?? null;
        $paidAt = is_array($paid) ? intval($paid['paid_at'] ?? 0) : 0;
        $matchedLink = $paid !== null && is_array($paid)
            ? xuiParseSubLink(trim((string)($paid['link'] ?? ''))) : null;
        $linkMatches = $matchedLink !== null
            && pnvExpiryAuditKey($matchedLink['host'], $matchedLink['sub_id']) === $key;
        $paidSourceOk = is_array($paid)
            && trim((string)($paid['type'] ?? '')) === 'خرید'
            && trim((string)($paid['status'] ?? '')) === 'paid'
            && $linkMatches
            && $paidAt > 0 && $paidAt <= time()
            && $paidAt >= intval($row[8] ?? 0);

        $expectedMs = $paidAt > 0 ? ($paidAt * 1000 + $days * 86400000) : 0;
        $currentMs = array_key_exists('expiryTime', $client)
            ? xuiClientExpiryMs($client) : 0;
        $gb = xuiParsePlanGb((string)($row[2] ?? ''));
        $totalBytes = is_array($client) && function_exists('xuiClientTotalBytes')
            ? xuiClientTotalBytes($client) : 0;
        $expectedBytes = xuiGbToBytes($gb);
        $quotaMatches = $gb > 0 && $totalBytes > 0
            && abs($totalBytes - $expectedBytes) < 1048576;

        $linkId = (string)$parsed['sub_id'];
        $userSubKey = $user . '|' . $linkId;
        $ctx = [
            'history_conflict' => count($events) !== 1 || count($buys) !== 1,
            'renewal_sources_unavailable' => !$renewalSourcesAvailable,
            'renewal_history_unknown' => !empty($signals['ambiguous'])
                || !empty($signals['unknown_users'][$user]),
            'renewal_payment_detected' => !empty($signals['instant_renewals'][$userSubKey]),
            'renew_reservation_detected' => !empty($signals['reservation_sub_ids'][$linkId])
                || !empty($signals['reservation_renewals'][$userSubKey]),
            'paid_source_ok' => $paidSourceOk,
            'server_ok' => $serverOk,
            'matching_clients' => count($clients),
            'has_expiry_field' => array_key_exists('expiryTime', $client),
            'current_expiry_ms' => $currentMs,
            'enabled' => ($client['enable'] ?? null) === true
                || ($client['enable'] ?? null) === 1
                || ($client['enable'] ?? null) === 'true',
            'quota_matches' => $quotaMatches,
            'expected_ms' => $expectedMs,
            'now_ms' => $nowMs,
        ];

        $status = pnvExpiryAuditClassification($ctx);
        $stats[$status] = intval($stats[$status] ?? 0) + 1;
        $results[] = [
            'key' => substr(hash('sha256', $key), 0, 16),
            'csv_line' => $event['index'] + 1,
            'server_id' => $serverId,
            'days' => $days,
            'status' => $status,
            'paid_at_utc' => $paidSourceOk ? gmdate('c', $paidAt) : null,
            'proposed_expiry_utc' => $paidSourceOk ? gmdate('c', intdiv($expectedMs, 1000)) : null,
            'current_expiry_ms' => $serverOk && count($clients) === 1
                && array_key_exists('expiryTime', $client) ? $currentMs : null,
            'read_error' => !$serverOk ? $readReason : null,
        ];
    }

    $report = [
        'mode' => 'READ_ONLY_NO_MUTATIONS',
        'audit_version' => 2,
        'renewal_sources_available' => $renewalSourcesAvailable,
        'generated_utc' => gmdate('c'),
        'root' => $root,
        'summary' => $stats,
        'items' => $results,
        'note' => 'Cross-checks original purchase, InstantPay renewal orders and reservation queue. Candidates still require manual review. 3x-ui administrator edits, unlogged renewals and exact activation time cannot be established from these sources.',
    ];
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__){
    pnvExpiryAuditMain($argv);
}

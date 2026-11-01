<?php
/**
 * One-by-one repair of historic, wrongly unlimited-time new purchases.
 *
 * Requires a saved read-only audit report, a candidate key, and a matching
 * current audit. Dry-run by default. No purchase/renewal/CSV changes.
 * Even in --apply mode, only the selected XUI client's expiryTime may change.
 *
 * Usage:
 * php scripts/repair-old-buy-expiry.php --report=/root/audit.json --key=0123456789abcdef
 * php scripts/repair-old-buy-expiry.php --report=/root/audit.json --key=0123456789abcdef --apply
 */
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/audit-old-buy-expiry.php';

function pnvRepairArg(array $argv, $name){
    foreach($argv as $arg){
        if(strpos($arg, '--' . $name . '=') === 0){
            return substr($arg, strlen($name) + 3);
        }
    }
    return '';
}

function pnvRepairAbort($reason){
    throw new RuntimeException('STOP ' . $reason);
}

function pnvRepairMatchingItem($report, $key){
    if(!is_array($report) || ($report['mode'] ?? '') !== 'READ_ONLY_NO_MUTATIONS'){
        pnvRepairAbort('invalid audit report');
    }
    $items = array_values(array_filter($report['items'] ?? [], static function($item) use ($key){
        return is_array($item) && ($item['key'] ?? '') === $key;
    }));
    if(count($items) !== 1){
        pnvRepairAbort('candidate key is missing or duplicated');
    }
    $item = $items[0];
    if(($item['status'] ?? '') !== 'candidate_review_before_apply'){
        pnvRepairAbort('not a candidate');
    }
    return $item;
}

function pnvRepairCheckReaudit($saved, $fresh){
    foreach(['key', 'csv_line', 'days', 'server_id', 'paid_at_utc', 'proposed_expiry_utc'] as $field){
        if(($saved[$field] ?? null) !== ($fresh[$field] ?? null)){
            pnvRepairAbort('candidate evidence changed: ' . $field);
        }
    }
    if(($fresh['status'] ?? '') !== 'candidate_review_before_apply'){
        pnvRepairAbort('no longer a repair candidate');
    }
}

function pnvRepairReadOne($server, $subId){
    $response = xuiApiRequest($server, 'GET', '/panel/api/inbounds/list');
    if(empty($response['success'])){
        pnvRepairAbort('inbounds API read failed');
    }
    $object = $response['obj'] ?? null;
    if(!is_array($object)){
        pnvRepairAbort('inbounds list malformed');
    }
    $inbounds = $object['list'] ?? $object;
    if(!is_array($inbounds)){
        pnvRepairAbort('inbounds array invalid');
    }
    $matches = [];
    foreach($inbounds as $inbound){
        if(!is_array($inbound)){
            continue;
        }
        $inboundId = intval($inbound['id'] ?? 0);
        if($inboundId <= 0){
            continue;
        }
        $settings = $inbound['settings'] ?? null;
        if(!is_array($settings) && !is_string($settings)){
            $one = xuiApiRequest($server, 'GET', '/panel/api/inbounds/get/' . $inboundId);
            if(empty($one['success']) || !is_array($one['obj'] ?? null)){
                pnvRepairAbort('inbound details unavailable');
            }
            $settings = $one['obj']['settings'] ?? null;
        }
        if(is_string($settings)){
            $settings = json_decode($settings, true);
        }
        if(!is_array($settings) || !isset($settings['clients']) || !is_array($settings['clients'])){
            pnvRepairAbort('authoritative inbound settings unavailable');
        }
        foreach($settings['clients'] as $item){
            if(!is_array($item)){
                continue;
            }
            if(trim((string)($item['subId'] ?? '')) !== $subId){
                continue;
            }
            $matches[] = ['inbound' => $inboundId, 'client' => $item];
        }
    }
    if(count($matches) !== 1){
        pnvRepairAbort('target client not uniquely identified');
    }
    return $matches[0];
}

function pnvRepairCheckClient($client, $days, $expectedMs, $planGb){
    if(!is_array($client) || !array_key_exists('expiryTime', $client)
        || xuiClientExpiryMs($client) !== 0){
        pnvRepairAbort('client expiry was already modified or missing');
    }
    $enabled = ($client['enable'] ?? null) === true
        || ($client['enable'] ?? null) === 1
        || ($client['enable'] ?? null) === 'true';
    if(!$enabled){
        pnvRepairAbort('client is not currently enabled');
    }
    $id = trim((string)($client['id'] ?? ''));
    if(!preg_match('/^[a-f0-9-]{32,36}$/i', $id)){
        pnvRepairAbort('client UUID missing or unsafe');
    }
    if(trim((string)($client['email'] ?? '')) === '' || trim((string)($client['subId'] ?? '')) === ''){
        pnvRepairAbort('client identity fields missing');
    }
    if(!in_array($days, [30, 90], true) || $expectedMs <= (int)floor(microtime(true) * 1000)){
        pnvRepairAbort('duration or proposed expiry invalid/elapsed');
    }
    $bytes = xuiClientTotalBytes($client);
    if($planGb <= 0 || $bytes <= 0 || abs($bytes - xuiGbToBytes($planGb)) >= 1048576){
        pnvRepairAbort('quota does not match original purchased plan');
    }
    return $id;
}

function pnvRepairSameExceptExpiry($before, $after, $expectedMs){
    if(!is_array($before) || !is_array($after)){
        return false;
    }
    $afterExpiry = xuiClientExpiryMs($after);
    if(abs($afterExpiry - $expectedMs) > 1000){
        return false;
    }
    $before['expiryTime'] = $after['expiryTime'];
    ksort($before);
    ksort($after);
    return $before === $after;
}

function pnvRepairRun(array $args){
    $root = pnvRepairArg($args, 'root') ?: '/var/www/html';
    $reportPath = pnvRepairArg($args, 'report');
    $key = pnvRepairArg($args, 'key');
    $apply = in_array('--apply', $args, true);

    if(!preg_match('/^[a-f0-9]{16}$/', $key)){
        pnvRepairAbort('provide one opaque 16-character candidate --key');
    }
    if($reportPath === '' || !is_file($reportPath)){
        pnvRepairAbort('provide existing --report path');
    }
    if($apply && function_exists('posix_geteuid') && posix_geteuid() !== 0){
        pnvRepairAbort('--apply requires root');
    }

    // Prevent two expiry-repair invocations from touching the same client.
    // The lock remains held by this process until the function returns.
    $repairLock = null;
    if($apply){
        $lockDir = '/root/pnv-expiry-repair-locks';
        if(is_link($lockDir)){
            pnvRepairAbort('unsafe repair lock directory');
        }
        if(!is_dir($lockDir) && !mkdir($lockDir, 0700, true)){
            pnvRepairAbort('cannot create repair lock directory');
        }
        chmod($lockDir, 0700);
        $repairLock = fopen($lockDir . '/' . $key . '.lock', 'c');
        if(!is_resource($repairLock) || !flock($repairLock, LOCK_EX | LOCK_NB)){
            pnvRepairAbort('another repair operation is in progress');
        }
    }

    $savedReport = json_decode((string)file_get_contents($reportPath), true);
    $saved = pnvRepairMatchingItem($savedReport, $key);

    $root = realpath($root);
    if($root === false || !is_file($root . '/xui_lib.php')){
        pnvRepairAbort('invalid panel root');
    }
    require_once $root . '/xui_lib.php';
    if(is_file($root . '/subscription_lib.php')){
        require_once $root . '/subscription_lib.php';
    }
    if(is_file($root . '/instant_pay_lib.php')){
        require_once $root . '/instant_pay_lib.php';
    }

    // Recompute the audit from current CSV/InstantPay and XUI data before proceeding.
    ob_start();
    try {
        pnvExpiryAuditMain(['audit-old-buy-expiry.php', '--root=' . $root]);
        $currentJson = ob_get_clean();
    }
    catch(Throwable $exc){
        ob_end_clean();
        pnvRepairAbort('fresh audit could not be completed');
    }
    $freshReport = json_decode((string)$currentJson, true);
    $fresh = pnvRepairMatchingItem($freshReport, $key);
    pnvRepairCheckReaudit($saved, $fresh);

    $index = intval($fresh['csv_line'] ?? 0) - 1;
    $payments = xuiLoadPayments();
    $row = $payments[$index] ?? null;
    if(!is_array($row) || trim((string)($row[9] ?? '')) !== 'خرید'
        || trim((string)($row[6] ?? '')) !== 'تایید شد'){
        pnvRepairAbort('original confirmed purchase row missing');
    }
    $parsed = pnvExpiryAuditCsvLink($row, 'خرید');
    if(!is_array($parsed)){
        pnvRepairAbort('subscription link missing');
    }
    $actualKey = pnvExpiryAuditKey($parsed['host'], $parsed['sub_id']);
    if(substr(hash('sha256', $actualKey), 0, 16) !== $key){
        pnvRepairAbort('hash/CSV identity mismatch');
    }
    $server = xuiFindServerByHost($parsed['host'], xuiLoadConfig());
    if(!is_array($server) || (string)($server['id'] ?? '') !== (string)($fresh['server_id'] ?? '')){
        pnvRepairAbort('XUI server mismatch');
    }
    $days = intval($fresh['days'] ?? 0);
    $planGb = xuiParsePlanGb((string)($row[2] ?? ''));
    $expectedMs = strtotime((string)$fresh['proposed_expiry_utc']) * 1000;
    if($expectedMs <= 0){
        pnvRepairAbort('expected expiry is not a valid timestamp');
    }

    $lookup = pnvRepairReadOne($server, $parsed['sub_id']);
    $before = $lookup['client'];
    $clientId = pnvRepairCheckClient($before, $days, $expectedMs, $planGb);
    $inboundId = intval($lookup['inbound']);
    $after = $before;
    $after['expiryTime'] = $expectedMs;

    $display = [
        'mode' => $apply ? 'SINGLE_CLIENT_APPLY' : 'DRY_RUN',
        'candidate_key' => $key,
        'csv_line' => $index + 1,
        'server_id' => $fresh['server_id'],
        'plan_days' => $days,
        'expected_expiry_utc' => gmdate('c', intdiv($expectedMs, 1000)),
        'changes' => ['expiryTime' => [0, $expectedMs]],
        'other_fields' => 'unchanged',
    ];

    if(!$apply){
        $display['result'] = 'SAFE_DRY_RUN_PASS';
        return $display;
    }

    // A single mutating request per invocation; NO retry on any response.
    // Persist the exact sensitive pre-image privately before calling 3x-ui.
    $backupDir = '/root/pnv-expiry-repair-backups';
    if(is_link($backupDir)){
        pnvRepairAbort('unsafe backup directory');
    }
    if(!is_dir($backupDir) && !mkdir($backupDir, 0700, true)){
        pnvRepairAbort('cannot create private backup directory');
    }
    chmod($backupDir, 0700);
    $backupPath = tempnam($backupDir, 'preimage-' . $key . '-');
    if($backupPath === false){
        pnvRepairAbort('cannot create backup');
    }
    chmod($backupPath, 0600);
    $json = json_encode(['inbound_id' => $inboundId, 'client' => $before], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if($json === false || file_put_contents($backupPath, $json, LOCK_EX) !== strlen($json)){
        pnvRepairAbort('failed to save preimage backup');
    }

    $path = '/panel/api/inbounds/updateClient/' . rawurlencode($clientId);
    $fields = [
        'id' => (string)$inboundId,
        'settings' => json_encode(['clients' => [$after]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    if($fields['settings'] === false){
        pnvRepairAbort('failed to encode request');
    }
    $reply = xuiApiRequestForm($server, 'POST', $path, $fields);
    $display['http'] = intval($reply['http'] ?? 0);
    $display['server_success'] = !empty($reply['success']);

    // Independent authoritative GET. Do not retry the POST.
    try{
        $readback = pnvRepairReadOne($server, $parsed['sub_id']);
        $sameIdentity = intval($readback['inbound']) === $inboundId;
        $verified = $sameIdentity && pnvRepairSameExceptExpiry($before, $readback['client'], $expectedMs);
        $unchanged = $sameIdentity && $before === $readback['client'];
        $display['verified'] = $verified;
        if($verified){
            $display['result'] = 'APPLIED_AND_VERIFIED';
        }
        elseif($unchanged && !empty($reply['http']) && in_array(intval($reply['http']), [400, 404, 405, 415], true)){
            $display['result'] = 'DEFINITELY_REJECTED_UNCHANGED';
        }
        else{
            $display['result'] = 'AMBIGUOUS_MANUAL_REVIEW_REQUIRED_NO_RETRY';
        }
    }
    catch(Throwable $exc){
        $display['verified'] = false;
        $display['result'] = 'READBACK_FAILED_MANUAL_REVIEW_REQUIRED_NO_RETRY';
    }
    $display['backup_path'] = $backupPath;
    return $display;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__){
    try{
        $report = pnvRepairRun($argv);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        if(($report['result'] ?? '') !== 'SAFE_DRY_RUN_PASS' && ($report['result'] ?? '') !== 'APPLIED_AND_VERIFIED'){
            exit(3);
        }
    }
    catch(Throwable $exc){
        fwrite(STDERR, $exc->getMessage() . "\n");
        exit(2);
    }
}

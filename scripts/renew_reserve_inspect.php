<?php

/**
 * Read-only inspect: reservation queue + live 3x-ui runtime (no activation).
 *
 * Usage:
 *   php scripts/renew_reserve_inspect.php
 *   php scripts/renew_reserve_inspect.php --id=rsv_abc123
 *   php scripts/renew_reserve_inspect.php --pending
 */

require_once __DIR__ . '/../xui_lib.php';
require_once __DIR__ . '/../renew_reserve_lib.php';

function renewReserveInspectBytes($bytes){
    $bytes = max(0, intval($bytes));
    $gb = $bytes / (1024 * 1024 * 1024);

    return round($gb, 3) . 'GB';
}

function renewReserveInspectClientSummary($client){
    if(!is_array($client)){
        return null;
    }

    $snap = xuiRenewClientSnapshot($client);

    return [
        'email' => trim((string)($client['email'] ?? '')),
        'sub_id' => trim((string)($snap['sub_id'] ?? '')),
        'total' => renewReserveInspectBytes(intval($snap['total_bytes'] ?? 0)),
        'used' => renewReserveInspectBytes(intval($snap['used_bytes'] ?? 0)),
        'remaining' => renewReserveInspectBytes(max(0, intval($snap['total_bytes'] ?? 0) - intval($snap['used_bytes'] ?? 0))),
        'expiry_ms' => intval($snap['expiry_ms'] ?? 0),
        'expiry_iso' => intval($snap['expiry_ms'] ?? 0) > 0
            ? date('c', (int)floor(intval($snap['expiry_ms']) / 1000))
            : null,
    ];
}

function renewReserveInspectOne(array $item){
    $config = xuiLoadConfig();
    $host = trim((string)($item['server_host'] ?? ''));
    $subId = trim((string)($item['sub_id'] ?? ''));
    $subLink = trim((string)($item['sub_link'] ?? ''));
    $server = $host !== '' ? xuiFindServerByHost($host, $config) : null;
    $out = [
        'id' => trim((string)($item['id'] ?? '')),
        'status' => trim((string)($item['status'] ?? '')),
        'payment_tracking' => trim((string)($item['payment_tracking'] ?? '')),
        'username' => trim((string)($item['username'] ?? '')),
        'plan_gb' => intval($item['plan_gb'] ?? 0),
        'plan_days' => intval($item['plan_days'] ?? 0),
        'reserved_at' => intval($item['reserved_at'] ?? 0),
        'activating_at' => intval($item['activating_at'] ?? 0),
        'activated_at' => intval($item['activated_at'] ?? 0),
        'activation_op_id' => trim((string)($item['activation_op_id'] ?? '')),
        'activation_anchor_ms' => intval($item['activation_anchor_ms'] ?? 0),
        'last_check_note' => trim((string)($item['last_check_note'] ?? '')),
        'last_error' => trim((string)($item['last_error'] ?? '')),
    ];

    if(!$server){
        $out['xui'] = ['lookup' => 'server_not_found', 'host' => $host];

        return $out;
    }

    $lookup = xuiRenewLookupClient($server, $subId, $subLink);
    $out['xui']['lookup_status'] = $lookup['status'] ?? 'unknown';

    if(($lookup['status'] ?? '') === 'found' && is_array($lookup['client'] ?? null)){
        $client = $lookup['client'];
        $out['xui']['client'] = renewReserveInspectClientSummary($client);
        $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);
        $out['runtime'] = $runtime;
        $out['decision'] = renewReserveDecideAction($runtime);
        $out['worker_would'] = empty($runtime['should_activate_now'])
            ? 'skip_not_due'
            : 'apply_absolute_plan_and_verify';
        $gb = intval($item['plan_gb'] ?? 0);
        $days = intval($item['plan_days'] ?? 0);
        $anchorMs = intval($item['activation_anchor_ms'] ?? 0);

        if($anchorMs <= 0){
            $anchorMs = renewReserveNowMs();
        }

        if(($lookup['status'] ?? '') === 'found'){
            $out['verify_absolute_now'] = xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs);
        }
    }
    elseif(($lookup['status'] ?? '') === 'api_error'){
        $out['xui']['error'] = $lookup['error'] ?? 'api_error';
    }

    return $out;
}

$filterId = '';
$pendingOnly = false;

foreach(array_slice($argv, 1) as $arg){
    if($arg === '--pending'){
        $pendingOnly = true;
        continue;
    }

    if(strpos($arg, '--id=') === 0){
        $filterId = trim(substr($arg, 5));
    }
}

$loaded = renewReserveLoadResult();

if(empty($loaded['ok'])){
    fwrite(STDERR, json_encode(['ok' => false, 'error' => $loaded['error'] ?? 'load_failed'], JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}

$items = is_array($loaded['items']) ? $loaded['items'] : [];
$report = [
    'ok' => true,
    'at' => date('c'),
    'reserve_path' => renewReservePath(),
    'pending_head_count' => count(renewReservePendingOrdered()),
    'work_queue_count' => count(renewReserveQueueWorkItems()),
    'items' => [],
];

foreach($items as $item){
    if(!is_array($item)){
        continue;
    }

    $id = trim((string)($item['id'] ?? ''));

    if($filterId !== '' && $id !== $filterId){
        continue;
    }

    if($pendingOnly){
        $st = trim((string)($item['status'] ?? ''));

        if($st !== 'reserved' && $st !== 'activating'){
            continue;
        }
    }

    $report['items'][] = renewReserveInspectOne($item);
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

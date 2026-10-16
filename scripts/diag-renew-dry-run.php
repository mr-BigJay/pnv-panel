<?php
/**
 * DRY-RUN renew: read-only report only. No bulkAdjust, updateClient, CSV, or 3x-ui writes.
 *
 * Usage:
 *   PNV_ROOT=/var/www/html php scripts/diag-renew-dry-run.php \
 *     --link "https://vip.boozhaan.ir:2096/sub/SUB_ID" \
 *     --plan "10 گیگ - 250 هزار تومان"
 *
 *   PNV_ROOT=/var/www/html php scripts/diag-renew-dry-run.php --csv-index 457
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = getenv('PNV_ROOT') ?: '/var/www/html';
require $root . '/xui_lib.php';
require $root . '/subscription_lib.php';

$link = '';
$plan = '10 گیگ - 250 هزار تومان';
$csvIndex = -1;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--link' && isset($argv[$i + 1])) {
        $link = trim($argv[++$i]);
    } elseif ($argv[$i] === '--plan' && isset($argv[$i + 1])) {
        $plan = trim($argv[++$i]);
    } elseif ($argv[$i] === '--csv-index' && isset($argv[$i + 1])) {
        $csvIndex = intval($argv[++$i]);
    }
}

if ($csvIndex >= 0) {
    $payments = xuiLoadPayments();
    if (!isset($payments[$csvIndex])) {
        fwrite(STDERR, "CSV index not found: {$csvIndex}\n");
        exit(1);
    }
    $row = $payments[$csvIndex];
    $link = trim((string)($row[1] ?? ''));
    if (trim((string)($row[2] ?? '')) !== '') {
        $plan = trim((string)($row[2] ?? ''));
    }
}

if ($link === '') {
    fwrite(STDERR, "Provide --link \"https://host/sub/id\" or --csv-index N\n");
    exit(1);
}

$config = xuiLoadConfig();
$gb = xuiParsePlanGb($plan);
$days = xuiParsePlanDays($plan);

$row = [
    'dry-run-user',
    $link,
    $plan,
    'DRY-RUN',
    '',
    '',
    'درحال بررسی',
    '',
    time(),
    'تمدید',
];

$out = [
    'dry_run' => true,
    'writes_performed' => false,
    'subscription_link' => $link,
    'plan_text' => $plan,
    'plan_gb_to_add' => $gb,
    'plan_days_to_add' => $days,
];

$ctx = xuiResolveRenewContext($row, $config);
$out['xuiResolveRenewContext'] = [
    'ok' => !empty($ctx['ok']),
    'error' => $ctx['error'] ?? null,
];

if (empty($ctx['ok'])) {
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(2);
}

$server = $ctx['server'];
$client = $ctx['client'];
$parsed = $ctx['parsed'];
$email = trim((string)($client['email'] ?? ''));
$subId = trim((string)($parsed['sub_id'] ?? ''));
$uuid = trim((string)($client['id'] ?? $client['uuid'] ?? ''));
if ($uuid !== '' && ctype_digit($uuid) && !empty($client['uuid'])) {
    $uuid = trim((string)$client['uuid']);
}

$inboundId = intval($client['_inbound_id'] ?? ($server['inbound_id'] ?? 0));

$totalBytes = xuiClientTotalBytes($client);
$usedBytes = xuiClientUsedBytes($client);
$expiryMs = xuiClientExpiryMs($client);

$httpSub = null;
if (function_exists('curl_init')) {
    $curl = curl_init($link);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($curl);
    $httpSub = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
}

$panelGet = null;
if ($email !== '') {
    $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));
    $panelGet = [
        'success' => !empty($full['success']),
        'msg' => $full['msg'] ?? null,
        'http' => $full['http'] ?? null,
    ];
    if (!empty($full['success']) && is_array($full['obj'] ?? null)) {
        $panelClient = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);
        if ($panelClient) {
            $totalBytes = max($totalBytes, xuiClientTotalBytes($panelClient));
            $usedBytes = max($usedBytes, xuiClientUsedBytes($panelClient));
            $expMs2 = xuiClientExpiryMs($panelClient);
            if ($expMs2 > 0) {
                $expiryMs = $expMs2;
            }
            if (($panelClient['subId'] ?? '') !== '') {
                $subId = trim((string)$panelClient['subId']);
            }
            if ($uuid === '' && ($panelClient['id'] ?? '') !== '') {
                $uuid = trim((string)$panelClient['id']);
            }
        }
    }
}

$out['checks'] = [
    'renew_allowed' => in_array($server['id'] ?? '', $config['renew_server_ids'] ?? [], true),
    'findClientBySubId' => 'FOUND',
    'public_sub_http_head' => $httpSub,
    'cross_server_scan_note' => 'see resolve path; dry-run uses resolved server only',
];

$out['renew_target'] = [
    'server_id' => $server['id'] ?? '',
    'server_host' => $server['host'] ?? '',
    'sub_id' => $subId,
    'email' => $email,
    'client_uuid' => $uuid,
    'inbound_id' => $inboundId,
    'current_total_bytes' => $totalBytes,
    'current_total_gb' => round($totalBytes / 1024 / 1024 / 1024, 4),
    'current_used_bytes' => $usedBytes,
    'current_expiry_ms' => $expiryMs,
    'current_expiry_iso' => $expiryMs > 0 ? gmdate('c', (int)floor($expiryMs / 1000)) : null,
];

$out['would_apply_via_xuiProvisionRenew'] = [
    'method_primary' => 'POST /panel/api/clients/bulkAdjust',
    'bulkAdjust_body' => [
        'emails' => [$email],
        'addDays' => $days,
        'addBytes' => xuiGbToBytes($gb),
    ],
    'method_fallback' => 'POST /panel/api/inbounds/updateClient/{uuid}',
    'note' => 'NOT EXECUTED — dry run only',
];

$out['xuiProvisionRenew_simulation'] = [
    'would_update_server_id' => $server['id'] ?? '',
    'would_update_email' => $email,
    'would_update_sub_id' => $subId,
    'would_return_link' => xuiBuildSubLink($parsed['host'] ?? '', $subId, $config),
    'same_client_as_resolve' => true,
];

$out['panel_clients_get'] = $panelGet;

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

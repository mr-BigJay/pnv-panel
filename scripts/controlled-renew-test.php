<?php
/**
 * Controlled LIVE renew test for a single client. Does NOT modify payments.csv.
 *
 * Usage (server3):
 *   PNV_ROOT=/var/www/html php scripts/controlled-renew-test.php --confirm
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = getenv('PNV_ROOT') ?: '/var/www/html';
require $root . '/xui_lib.php';

$confirm = false;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--confirm') {
        $confirm = true;
    }
}

if (!$confirm) {
    fwrite(STDERR, "Refusing write without --confirm\n");
    exit(1);
}

const EXPECT_LINK = 'https://vip.boozhaan.ir:2096/sub/i3vkfijpkmjbsmus';
const EXPECT_SERVER_ID = 'vip';
const EXPECT_EMAIL = 'nazigh6221_5783';
const EXPECT_UUID = 'd196c1f1-825c-44a1-8125-b8ae3a9645ff';
const EXPECT_SUB_ID = 'i3vkfijpkmjbsmus';
const ADD_BYTES = 10737418240; // +10 GiB
const ADD_GB = 10;
const ADD_DAYS = 0;
const PLAN_TEXT = '10 گیگ - controlled-renew-test';

function crnClientUuid(array $client): string
{
    $uuid = trim((string)($client['id'] ?? $client['uuid'] ?? ''));
    if ($uuid !== '' && ctype_digit($uuid) && !empty($client['uuid'])) {
        $uuid = trim((string)$client['uuid']);
    }
    return $uuid;
}

function crnSnapshotClient(array $server, array $client, string $subId): array
{
    $email = trim((string)($client['email'] ?? ''));
    if ($email !== '') {
        $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));
        if (!empty($full['success']) && is_array($full['obj'] ?? null)) {
            $panel = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);
            if ($panel) {
                $client = array_merge($client, $panel);
            }
        }
    }

    $inboundId = intval($client['_inbound_id'] ?? ($server['inbound_id'] ?? 0));
    $totalBytes = xuiClientTotalBytes($client);
    $expiryMs = xuiClientExpiryMs($client);

    return [
        'server_id' => $server['id'] ?? '',
        'sub_id' => trim((string)($client['subId'] ?? $subId)),
        'email' => trim((string)($client['email'] ?? '')),
        'client_uuid' => crnClientUuid($client),
        'inbound_id' => $inboundId,
        'total_bytes' => $totalBytes,
        'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 4),
        'used_bytes' => xuiClientUsedBytes($client),
        'expiry_ms' => $expiryMs,
        'expiry_iso' => $expiryMs > 0 ? gmdate('c', (int)floor($expiryMs / 1000)) : null,
    ];
}

function crnPublicSubHttp(string $link): int
{
    if (!function_exists('curl_init')) {
        return 0;
    }
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
    $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $code;
}

$config = xuiLoadConfig();
$report = [
    'test' => 'controlled_renew_single_client',
    'csv_modified' => false,
    'expected_add_bytes' => ADD_BYTES,
    'expected_add_days' => ADD_DAYS,
    'provision_path' => 'xuiProvisionRenew -> xuiAdjustClientTraffic -> bulkAdjust',
];

$paymentRow = [
    'controlled-renew-test',
    EXPECT_LINK,
    PLAN_TEXT,
    'CONTROLLED-TEST',
    '',
    '',
    'درحال بررسی',
    '',
    (string)time(),
    'تمدید',
];

$ctx = xuiResolveRenewContext($paymentRow, $config);
$report['resolve'] = [
    'ok' => !empty($ctx['ok']),
    'error' => $ctx['error'] ?? null,
];

if (empty($ctx['ok'])) {
    $report['result'] = 'RENEW TEST: FAILED';
    $report['failure_reason'] = 'resolve_context';
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(2);
}

$server = $ctx['server'];
$client = $ctx['client'];
$serverId = (string)($server['id'] ?? '');

$before = crnSnapshotClient($server, $client, EXPECT_SUB_ID);
$report['before'] = $before;
$report['before']['public_sub_http'] = crnPublicSubHttp(EXPECT_LINK);

$identityErrors = [];
if ($serverId !== EXPECT_SERVER_ID) {
    $identityErrors[] = "server_id mismatch: {$serverId}";
}
if (strcasecmp($before['email'], EXPECT_EMAIL) !== 0) {
    $identityErrors[] = 'email mismatch: ' . $before['email'];
}
if (strcasecmp($before['sub_id'], EXPECT_SUB_ID) !== 0) {
    $identityErrors[] = 'subId mismatch: ' . $before['sub_id'];
}
if (strcasecmp($before['client_uuid'], EXPECT_UUID) !== 0) {
    $identityErrors[] = 'uuid mismatch: ' . $before['client_uuid'];
}

if (count($identityErrors) > 0) {
    $report['result'] = 'RENEW TEST: FAILED';
    $report['failure_reason'] = 'identity_guard_aborted_before_write';
    $report['identity_errors'] = $identityErrors;
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(3);
}

$gbFromPlan = xuiParsePlanGb(PLAN_TEXT);
$daysFromPlan = xuiParsePlanDays(PLAN_TEXT);
$report['plan_parse'] = [
    'gb' => $gbFromPlan,
    'days' => $daysFromPlan,
    'add_bytes_from_gb' => xuiGbToBytes($gbFromPlan),
];

if ($gbFromPlan !== ADD_GB || $daysFromPlan !== ADD_DAYS || xuiGbToBytes($gbFromPlan) !== ADD_BYTES) {
    $report['result'] = 'RENEW TEST: FAILED';
    $report['failure_reason'] = 'plan_parse_mismatch';
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(4);
}

$provision = xuiProvisionRenew($paymentRow);
$report['provision'] = [
    'ok' => !empty($provision['ok']),
    'method' => $provision['method'] ?? null,
    'error' => $provision['error'] ?? null,
    'email' => $provision['email'] ?? null,
    'sub_id' => $provision['sub_id'] ?? null,
    'server_id' => $provision['server_id'] ?? null,
    'gb' => $provision['gb'] ?? null,
];

// Re-fetch client (same subId) for after snapshot
$afterClient = xuiFindClientBySubId($server, EXPECT_SUB_ID, EXPECT_LINK);
if (!$afterClient) {
    $report['after'] = null;
    $report['findClientBySubId_after'] = 'NOT_FOUND';
} else {
    $after = crnSnapshotClient($server, $afterClient, EXPECT_SUB_ID);
    $report['after'] = $after;
    $report['findClientBySubId_after'] = 'FOUND';
    $report['after']['public_sub_http'] = crnPublicSubHttp(EXPECT_LINK);

    $deltaBytes = $after['total_bytes'] - $before['total_bytes'];
    $report['comparison'] = [
        'total_bytes_before' => $before['total_bytes'],
        'total_bytes_after' => $after['total_bytes'],
        'total_gb_before' => $before['total_gb'],
        'total_gb_after' => $after['total_gb'],
        'delta_bytes' => $deltaBytes,
        'delta_gb' => round($deltaBytes / 1024 / 1024 / 1024, 4),
        'used_bytes_unchanged' => ($after['used_bytes'] === $before['used_bytes']),
        'expiry_unchanged' => ($after['expiry_ms'] === $before['expiry_ms']),
        'email_unchanged' => (strcasecmp($after['email'], $before['email']) === 0),
        'sub_id_unchanged' => (strcasecmp($after['sub_id'], $before['sub_id']) === 0),
        'uuid_unchanged' => (strcasecmp($after['client_uuid'], $before['client_uuid']) === 0),
    ];
}

$checks = [];
$checks['provision_ok'] = !empty($provision['ok']);
$checks['method_is_bulkAdjust'] = (($provision['method'] ?? '') === 'bulkAdjust');
$checks['delta_equals_10gb'] = isset($report['comparison']['delta_bytes'])
    && intval($report['comparison']['delta_bytes']) === ADD_BYTES;
$checks['after_total_is_before_plus_10gb'] = isset($report['comparison']['total_bytes_after'])
    && intval($report['comparison']['total_bytes_after']) === intval($before['total_bytes']) + ADD_BYTES;
$checks['used_unchanged'] = !empty($report['comparison']['used_bytes_unchanged']);
$checks['expiry_unchanged'] = !empty($report['comparison']['expiry_unchanged']);
$checks['identity_unchanged'] = !empty($report['comparison']['email_unchanged'])
    && !empty($report['comparison']['sub_id_unchanged'])
    && !empty($report['comparison']['uuid_unchanged']);
$checks['public_sub_http_200'] = (($report['after']['public_sub_http'] ?? 0) === 200);
$checks['findClientBySubId_found'] = (($report['findClientBySubId_after'] ?? '') === 'FOUND');

$report['checks'] = $checks;
$allPass = !in_array(false, $checks, true);

$report['result'] = $allPass ? 'RENEW TEST: SUCCESS' : 'RENEW TEST: FAILED';

if (!$allPass) {
    $report['failure_reason'] = empty($provision['ok'])
        ? 'xuiProvisionRenew_failed'
        : 'post_write_validation';
    if (empty($provision['ok'])) {
        $report['xui_api_message'] = $provision['error'] ?? 'unknown';
    }
    if (!empty($provision['ok']) && ($provision['method'] ?? '') !== 'bulkAdjust') {
        $report['xui_api_message'] = 'Provision succeeded but method was not bulkAdjust: '
            . ($provision['method'] ?? 'unknown');
    }
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
exit($allPass ? 0 : 5);

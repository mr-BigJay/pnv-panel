<?php

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

$csvIndex = intval($argv[1] ?? -1);
$subLinkArg = trim((string)($argv[2] ?? ''));

require_once dirname(__DIR__) . '/xui_lib.php';
require_once dirname(__DIR__) . '/subscription_lib.php';

$row = null;

if($csvIndex >= 0){
    $payments = xuiLoadPayments();

    if(!isset($payments[$csvIndex])){
        fwrite(STDERR, "CSV index not found: {$csvIndex}\n");
        exit(1);
    }

    $row = $payments[$csvIndex];
}
elseif($subLinkArg !== ''){
    $row = [
        'diag-user',
        $subLinkArg,
        trim((string)($argv[3] ?? '10 گیگ - 250 هزار تومان')),
        'DIAG',
        '',
        '',
        'درحال بررسی',
        '',
        time(),
        'تمدید',
    ];
}
else{
    fwrite(STDERR, "Usage: php scripts/diag-renew-provision.php <csv_index>\n");
    fwrite(STDERR, "   or: php scripts/diag-renew-provision.php --link \"https://host:2096/sub/id\" \"plan text\"\n");
    exit(1);
}

$config = xuiLoadConfig();
$out = [
    'xui_enabled' => xuiIsEnabled($config),
    'row_type' => xuiPaymentRowType($row, 'تمدید'),
    'plan_gb' => xuiParsePlanGb($row[2] ?? ''),
    'plan_days' => xuiParsePlanDays($row[2] ?? ''),
];

$ctx = xuiResolveRenewContext($row, $config);
$out['resolve'] = $ctx;

if(!empty($ctx['ok'])){
    $email = trim((string)($ctx['client']['email'] ?? ''));
    $server = $ctx['server'];
    $out['dry_run'] = [
        'email' => $email,
        'server_id' => $server['id'] ?? '',
        'would_call' => 'bulkAdjust + legacy fallback',
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

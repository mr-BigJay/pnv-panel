<?php

/**
 * Renew recreate tests (isolated temp files, no live 3x-ui).
 * php tests/xui_renew_recreate_test.php
 */

$fail = 0;

function renewRecAssert($cond, $msg){
    global $fail;

    if($cond){
        echo "OK  {$msg}\n";
    }
    else{
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

$tmpRoot = sys_get_temp_dir() . '/pnv_renew_test_' . getmypid();
@mkdir($tmpRoot, 0777, true);
$csvPath = $tmpRoot . '/payments.csv';
$configPath = $tmpRoot . '/xui_servers.json';
$plansPath = $tmpRoot . '/plans.json';
$locksDir = $tmpRoot . '/locks';
@mkdir($locksDir, 0777, true);

putenv('PNV_PAYMENTS_CSV_PATH=' . $csvPath);
putenv('PNV_XUI_CONFIG_PATH=' . $configPath);
putenv('PNV_PLANS_CATALOG_PATH=' . $plansPath);
putenv('PNV_LOCKS_DIR=' . $locksDir);
file_put_contents($plansPath, json_encode([
    ['name' => '10 گیگ', 'days' => 'نامحدود'],
], JSON_UNESCAPED_UNICODE));

require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../xui_lib.php';

$realCsv = __DIR__ . '/../invoices/payments.csv';
$realConfig = __DIR__ . '/../db/xui_servers.json';
$realCsvBefore = is_file($realCsv) ? file_get_contents($realCsv) : null;
$realConfigBefore = is_file($realConfig) ? file_get_contents($realConfig) : null;

renewRecAssert(
    xuiRenewUserMessageRenewed() !== '' && xuiRenewUserMessageRecreated() !== '',
    'user-facing renew messages'
);

$row = [
    'ownerUser',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    '10 گیگ - 30 روز - 150 هزار تومان',
    'AUTO-1001',
    '',
    '',
    'درحال بررسی',
    '',
    1700000000,
    'تمدید',
    '',
    '',
    1500000,
];

$keyA = xuiPaymentRowProvisionKey($row);
$keyB = xuiPaymentRowTransactionId($row);
renewRecAssert($keyA === $keyB && $keyA !== '', 'stable transaction / provision key');

$sampleCsv = "ownerUser,https://vip4.example.com:2096/sub/abc1234567890ab,10 گیگ - 30 روز,TRK1,,,تایید شد,https://vip4.example.com:2096/sub/abc1234567890ab,1700000000,تمدید,,,1500000\n";
file_put_contents($csvPath, $sampleCsv);

renewRecAssert(
    xuiRenewSubOwnedByUser('ownerUser', 'https://vip4.example.com:2096/sub/abc1234567890ab'),
    'ownership allows same host + user'
);
renewRecAssert(
    !xuiRenewSubOwnedByUser('intruder', 'https://vip4.example.com:2096/sub/abc1234567890ab'),
    'ownership rejects other user'
);
renewRecAssert(
    !xuiRenewSubOwnedByUser('ownerUser', 'https://vip5.example.com:2096/sub/abc1234567890ab'),
    'ownership rejects same sub id on different server host'
);

$durationKnown = xuiResolveRenewPlanDays('10 گیگ - 30 روز - 150 هزار تومان');
renewRecAssert(!empty($durationKnown['ok']) && intval($durationKnown['days'] ?? -1) === 30, 'renew duration parsed from plan text');

$durationCatalogUnlimited = xuiResolveRenewPlanDays('10 گیگ - 150 هزار تومان');
renewRecAssert(
    !empty($durationCatalogUnlimited['ok']) && !empty($durationCatalogUnlimited['time_unlimited']),
    'catalog explicitly unlimited plan is allowed for renew'
);
$durationUnknown = xuiResolveRenewPlanDays('7 گیگ - 999 هزار تومان');
renewRecAssert(empty($durationUnknown['ok']) && ($durationUnknown['code'] ?? '') === 'invalid_plan_duration', 'unknown plan duration is not treated as unlimited');

$server = [
    'id' => 'vip4',
    'host' => 'vip4.example.com',
    'inbound_id' => 1,
    'base_url' => 'https://example.test/',
    'api_token' => 'test-token',
];
file_put_contents($configPath, json_encode([
    'enabled' => true,
    'sub_port' => 2096,
    'renew_server_ids' => ['vip4'],
    'buy_server_ids' => ['vip4'],
    'servers' => [$server],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$GLOBALS['xuiTestRenewApiProbe'] = static function(){
    return ['ok' => true];
};

$existingAdjustPhase = 0;
$existingAnchorSec = 1700000001;
$existingAnchorMs = $existingAnchorSec * 1000;
$GLOBALS['renewReserveTestNow'] = $existingAnchorSec;
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$existingAdjustPhase){
    $existingAdjustPhase++;

    return ['ok' => true, 'method' => 'test_bulkAdjust'];
};

$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $subId) use (&$existingAdjustPhase, $existingAnchorMs){
    $after = $existingAdjustPhase > 0;

    return [
        'status' => 'found',
        'client' => [
            'email' => 'owner_1234',
            'subId' => $subId,
            'totalGB' => xuiGbToBytes($after ? 30 : 20),
            'expiryTime' => $after ? ($existingAnchorMs + (86400000 * 30)) : 0,
        ],
    ];
};
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => true,
    'should_activate_now' => false,
];

$renewExisting = xuiProvisionRenew($row);
unset($GLOBALS['renewReserveTestNow']);
renewRecAssert(
    !empty($renewExisting['ok'])
    && ($renewExisting['renew_outcome'] ?? '') === 'reserved'
    && $existingAdjustPhase === 0,
    'existing client renew path reserves while time-unlimited volume remains'
);

$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'api_error', 'error' => 'timeout'];
};
$apiErr = xuiProvisionRenew($row);
renewRecAssert(empty($apiErr['ok']) && ($apiErr['code'] ?? '') === 'api_error', 'api_error does not recreate');

$createCapture = ['calls' => 0, 'last' => []];
$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'not_found'];
};
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$createCapture){
    if($method === 'GET' && strpos($path, '/panel/api/clients/get/') === 0){
        return ['success' => false, 'msg' => 'not found'];
    }

    if($method === 'POST' && strpos($path, '/panel/api/clients/add') === 0){
        $createCapture['calls']++;
        $createCapture['last'] = is_array($body) ? $body : [];
        return ['success' => true, 'msg' => 'ok'];
    }

    return ['success' => false, 'msg' => 'unexpected ' . $method . ' ' . $path];
};

$recreateRow = $row;
$recreateCsvHandle = fopen($csvPath, 'w');
fputcsv($recreateCsvHandle, [
    'ownerUser',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    '10 گیگ - 30 روز',
    'HIST-1',
    '',
    '',
    'تایید شد',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    1699999999,
    'تمدید',
    '',
    '',
    1500000,
]);
fputcsv($recreateCsvHandle, $recreateRow);
fclose($recreateCsvHandle);

$recreate = xuiProvisionRenew($recreateRow);
renewRecAssert(!empty($recreate['ok']) && ($recreate['renew_outcome'] ?? '') === 'recreated', 'mock recreate path succeeds');
$clientPayload = $createCapture['last']['client'] ?? [];
renewRecAssert(intval($clientPayload['totalGB'] ?? 0) === xuiGbToBytes(10), 'recreate uses exact plan volume');
renewRecAssert(trim((string)($clientPayload['subId'] ?? '')) === 'abc1234567890ab', 'recreate preserves sub id when possible');
$expectedLink = xuiBuildSubLink('vip4.example.com', 'abc1234567890ab', xuiLoadConfig());
renewRecAssert(($recreate['link'] ?? '') === $expectedLink, 'recreate returns valid sub link');

$ambiguousCalls = 0;
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$ambiguousCalls){
    if($method === 'GET' && strpos($path, '/panel/api/clients/get/') === 0){
        return ['success' => false, 'msg' => 'not found'];
    }

    if($method === 'POST'){
        $ambiguousCalls++;
        return ['success' => false, 'msg' => 'خطا در ارتباط با 3x-ui: Operation timed out'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'not_found'];
};

$ambiguous = xuiProvisionRenew($recreateRow);
renewRecAssert(empty($ambiguous['ok']) && ($ambiguous['code'] ?? '') === 'ambiguous_provision', 'transport failure becomes ambiguous_provision');
renewRecAssert($ambiguousCalls === 1, 'first create transport skips legacy POST');

$postCalls = 0;
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$postCalls){
    if($method === 'GET' && strpos($path, '/panel/api/clients/get/') === 0){
        return ['success' => false, 'msg' => 'not found'];
    }

    if($method === 'POST' && strpos($path, '/panel/api/clients/add') === 0){
        $postCalls++;
        return ['success' => false, 'msg' => 'خطا در ارتباط با 3x-ui: Operation timed out'];
    }

    if($method === 'POST' && strpos($path, '/panel/api/inbounds/addClient') === 0){
        $postCalls++;
        return ['success' => false, 'msg' => 'should not be called'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
@unlink(xuiRenewProvisionStorePath(xuiPaymentRowProvisionKey($recreateRow)));
$transportAnchorSec = 1700000050;
$GLOBALS['renewReserveTestNow'] = $transportAnchorSec;
$transportAnchorMs = $transportAnchorSec * 1000;
$lookupPhase = 0;
$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $subId) use (&$lookupPhase, $transportAnchorMs){
    $lookupPhase++;

    if($lookupPhase === 1){
        return ['status' => 'not_found'];
    }

    return [
        'status' => 'found',
        'client' => [
            'email' => 'owner_recreated',
            'subId' => $subId,
            'totalGB' => xuiGbToBytes(10),
            'expiryTime' => $transportAnchorMs + (86400000 * 30),
        ],
    ];
};
file_put_contents($csvPath, $sampleCsv);
$transportRecreate = xuiProvisionRenewRecreate(
    $server,
    ['host' => 'vip4.example.com', 'port' => 2096, 'sub_id' => 'abc1234567890ab'],
    $recreateRow,
    10,
    30,
    xuiLoadConfig()
);
renewRecAssert(!empty($transportRecreate['ok']) && $postCalls === 1, 'timeout after successful create verified by lookup without legacy POST');
unset($GLOBALS['renewReserveTestNow']);

$adjustCount = 0;
$renewApproveRow = $recreateRow;
$renewApproveRow[2] = '10 گیگ - نامحدود زمان';
$renewApproveRow[3] = 'AUTO-2002';
$renewApproveRow[6] = 'درحال بررسی';
$renewApproveRow[8] = 1700000100;
$renewApproveKey = xuiPaymentRowProvisionKey($renewApproveRow);
@unlink(xuiRenewProvisionStorePath($renewApproveKey));

$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => true,
    'volume_exhausted' => false,
    'should_activate_now' => false,
];
$adjustAnchorSec = 1700000200;
$adjustAnchorMs = $adjustAnchorSec * 1000;
$GLOBALS['renewReserveTestNow'] = $adjustAnchorSec;
$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $subId) use (&$adjustCount, $adjustAnchorMs){
    $bytes = $adjustCount > 0 ? xuiGbToBytes(30) : xuiGbToBytes(20);
    $expiry = 0;

    return ['status' => 'found', 'client' => ['email' => 'owner_1234', 'subId' => $subId, 'totalGB' => $bytes, 'expiryTime' => $expiry]];
};
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$adjustCount){
    $adjustCount++;

    return ['ok' => true, 'method' => 'test_bulkAdjust'];
};
$GLOBALS['xuiTestRenewProvisionStoreWrite'] = static function($key, $payload){
    if(($payload['phase'] ?? '') === 'provision_complete'){
        return false;
    }

    return true;
};
$GLOBALS['xuiTestApiRequest'] = null;

$renewCsv = fopen($csvPath, 'w');
fputcsv($renewCsv, [
    'ownerUser',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    '10 گیگ - 30 روز',
    'HIST-3',
    '',
    '',
    'تایید شد',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    1699999997,
    'تمدید',
    '',
    '',
    1500000,
]);
fputcsv($renewCsv, $renewApproveRow);
fclose($renewCsv);

$renewDirect = xuiProvisionRenew($renewApproveRow);
renewRecAssert(
    empty($renewDirect['ok'])
    && ($renewDirect['code'] ?? '') === 'persist_failed'
    && $adjustCount === 1,
    'renew apply runs once before simulated crash'
);

unset($GLOBALS['xuiTestRenewProvisionStoreWrite']);
xuiRenewProvisionStoreWrite($renewApproveKey, [
    'state' => 'processing',
    'phase' => 'renew_unlimited_add_started',
    'operation' => 'incremental',
    'baseline' => ['total_bytes' => xuiGbToBytes(20), 'used_bytes' => 0, 'expiry_ms' => 0, 'email' => 'owner_1234', 'sub_id' => 'abc1234567890ab'],
    'plan_gb' => 10,
    'plan_days' => 0,
    'activation_anchor_ms' => $adjustAnchorMs,
    'started_at' => time() - 200,
]);

$approveRenewRetry = xuiApprovePaymentIndex(1, 'تمدید');
renewRecAssert(!empty($approveRenewRetry['ok']) && $adjustCount === 1, 'retry after adjust crash does not adjust again');
unset($GLOBALS['renewReserveTestNow']);

$postCalls = 0;
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$postCalls){
    if($method === 'POST'){
        $postCalls++;
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'not_found'];
};
$createDoneRow = $recreateRow;
$createDoneRow[3] = 'AUTO-2003';
$createDoneRow[6] = 'درحال بررسی';
$createDoneRow[8] = 1700000200;
$createDoneKey = xuiPaymentRowProvisionKey($createDoneRow);
xuiRenewProvisionStoreWrite($createDoneKey, [
    'state' => 'processing',
    'phase' => 'provision_complete',
    'started_at' => time() - 200,
    'result' => [
        'ok' => true,
        'link' => $expectedLink,
        'renew_outcome' => 'recreated',
    ],
]);
$createCsv = fopen($csvPath, 'w');
fputcsv($createCsv, $createDoneRow);
fclose($createCsv);

$approveCreateResume = xuiApprovePaymentIndex(0, 'تمدید');
renewRecAssert(!empty($approveCreateResume['ok']) && $postCalls === 0, 'retry after create completes csv without extra API create');

$interruptRow = $recreateRow;
$interruptRow[6] = 'درحال بررسی';
$interruptRow[7] = '';
$interruptKey = xuiPaymentRowProvisionKey($interruptRow);
xuiRenewProvisionStoreWrite($interruptKey, ['state' => 'done', 'result' => [
    'ok' => true,
    'link' => $expectedLink,
    'renew_outcome' => 'recreated',
]]);
$recreateCsvHandle = fopen($csvPath, 'w');
fputcsv($recreateCsvHandle, [
    'ownerUser',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    '10 گیگ - 30 روز',
    'HIST-2',
    '',
    '',
    'تایید شد',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    1699999998,
    'تمدید',
    '',
    '',
    1500000,
]);
fputcsv($recreateCsvHandle, $interruptRow);
fclose($recreateCsvHandle);

$saveAttempts = 0;
$GLOBALS['xuiTestSavePaymentsInterceptor'] = static function($rows) use (&$saveAttempts){
    $saveAttempts++;

    return $saveAttempts !== 1;
};

$approveFirst = xuiApprovePaymentIndex(1, 'تمدید');
renewRecAssert(empty($approveFirst['ok']) && ($approveFirst['code'] ?? '') === 'persist_failed', 'interrupt before csv save is recoverable');

$approveSecond = xuiApprovePaymentIndex(1, 'تمدید');
renewRecAssert(!empty($approveSecond['ok']), 'retry completes csv from provision store');
$paymentsAfter = xuiLoadPayments();
renewRecAssert(trim((string)($paymentsAfter[1][6] ?? '')) === 'تایید شد', 'csv marked confirmed after retry');

$src = file_get_contents(__DIR__ . '/../xui_lib.php');
renewRecAssert(strpos($src, 'xuiPaymentRowTransactionId') !== false, 'transaction id helper present');
renewRecAssert(strpos($src, 'payment_approve_') !== false && strpos($src, '$txnId') !== false, 'approve lock uses transaction id');

$realCsvAfter = is_file($realCsv) ? file_get_contents($realCsv) : null;
$realConfigAfter = is_file($realConfig) ? file_get_contents($realConfig) : null;
renewRecAssert($realCsvBefore === $realCsvAfter, 'real payments.csv untouched');
renewRecAssert($realConfigBefore === $realConfigAfter, 'real xui_servers.json untouched');

require_once __DIR__ . '/../instant_pay_lib.php';
$instantPath = $tmpRoot . '/instant_payments.json';
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $instantPath);
file_put_contents($instantPath, json_encode([[
    'id' => 'ip-fail-1',
    'user' => 'ownerUser',
    'tracking' => 'AUTO-9001',
    'code' => '9001',
    'status' => 'payment_received_provision_failed',
    'sub' => 'https://vip4.example.com:2096/sub/abc1234567890ab',
    'matched_amount' => 15000000,
    'provision_error' => 'ambiguous_provision test',
]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$adminRow = [
    'ownerUser',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    '10 گیگ - 30 روز',
    'AUTO-9001',
    '',
    '',
    'درحال بررسی',
    '',
    time(),
    'تمدید',
    '',
    '',
    15000000,
];
$adminMeta = instantPayAdminRowStatusMeta($adminRow);
renewRecAssert(($adminMeta['class'] ?? '') === 'statusDot--red', 'admin status shows provision failure');
$adminDetail = instantPayAdminProvisionFailureDetail($adminRow);
renewRecAssert(
    strpos($adminDetail, 'ownerUser') !== false
    && strpos($adminDetail, 'vip4.example.com') !== false
    && strpos($adminDetail, 'ambiguous_provision') !== false,
    'admin detail includes user sub amount and error'
);

unset(
    $GLOBALS['xuiTestRenewApiProbe'],
    $GLOBALS['xuiTestRenewLookupClient'],
    $GLOBALS['xuiTestAdjustClientTraffic'],
    $GLOBALS['xuiTestApiRequest'],
    $GLOBALS['xuiTestSavePaymentsInterceptor'],
    $GLOBALS['xuiTestRenewProvisionStoreWrite']
);
putenv('PNV_PAYMENTS_CSV_PATH');
putenv('PNV_XUI_CONFIG_PATH');
putenv('PNV_PLANS_CATALOG_PATH');
putenv('PNV_LOCKS_DIR');
putenv('PNV_INSTANT_PAYMENTS_PATH');

@array_map('unlink', glob($locksDir . '/*'));
@rmdir($locksDir);
@unlink($csvPath);
@unlink($configPath);
@unlink($plansPath);
@rmdir($tmpRoot);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} test(s) failed\n");
    exit(1);
}

echo "All xui renew recreate tests passed\n";

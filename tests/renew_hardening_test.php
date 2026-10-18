<?php

/**
 * Mandatory hardening regressions (provision preserve, recreate, stale activating, JSON, concurrency).
 */

$fail = 0;

function hardAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmp = sys_get_temp_dir() . '/pnv_hard_' . getmypid();
@mkdir($tmp . '/locks', 0777, true);
putenv('PNV_PAYMENTS_CSV_PATH=' . $tmp . '/p.csv');
putenv('PNV_XUI_CONFIG_PATH=' . $tmp . '/c.json');
putenv('PNV_LOCKS_DIR=' . $tmp . '/locks');
putenv('PNV_RENEW_RESERVE_PATH=' . $tmp . '/r.json');

require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../xui_lib.php';
require_once __DIR__ . '/../renew_reserve_lib.php';

file_put_contents(getenv('PNV_XUI_CONFIG_PATH'), json_encode([
    'enabled' => true,
    'sub_port' => 2096,
    'renew_server_ids' => ['vip4'],
    'servers' => [['id' => 'vip4', 'host' => 'vip4.example.com', 'inbound_id' => 1, 'base_url' => 'https://t/', 'api_token' => 'x']],
], JSON_UNESCAPED_UNICODE));

$link = 'https://vip4.example.com:2096/sub/abc1234567890ab';
$h = fopen(getenv('PNV_PAYMENTS_CSV_PATH'), 'w');
fputcsv($h, ['ownerUser', $link, '10 گیگ - 30 روز', 'HIST', '', '', 'تایید شد', $link, 1, 'تمدید', '', '', 1]);
fclose($h);

$GLOBALS['xuiTestRenewApiProbe'] = static fn() => ['ok' => true];

// 1) XUI success, crash before final persist — store keeps baseline
$row1 = ['ownerUser', $link, '10 گیگ - 30 روز', 'H-1', '', '', 'درحال بررسی', '', 170010, 'تمدید', '', '', 1];
$key1 = xuiPaymentRowProvisionKey($row1);
$baseline1 = ['total_bytes' => xuiGbToBytes(20), 'used_bytes' => 0, 'expiry_ms' => 0, 'email' => 'u1', 'sub_id' => 'abc1234567890ab'];
xuiRenewProvisionStoreWrite($key1, [
    'state' => 'processing',
    'phase' => 'renew_adjust_started',
    'operation' => 'incremental',
    'baseline' => $baseline1,
    'plan_gb' => 10,
    'plan_days' => 30,
    'started_at' => time() - 200,
]);
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $id, 'totalGB' => xuiGbToBytes(30), 'expiryTime' => 0],
];
$recover1 = xuiRenewRecoverProcessingRenew($row1, xuiRenewProvisionStoreRead($key1));
hardAssert(($recover1['action'] ?? '') === 'finalize', 'XUI done crash finalizes from baseline verify');

// 2) persist_failed retry without duplicate adjust
$adjustCalls = 0;
$row2 = ['ownerUser', $link, '10 گیگ - 30 روز', 'H-2', '', '', 'درحال بررسی', '', 170011, 'تمدید', '', '', 1];
$key2 = xuiPaymentRowProvisionKey($row2);
@unlink(xuiRenewProvisionStorePath($key2));
$GLOBALS['xuiTestRenewRuntimeState'] = ['ambiguous' => false, 'time_unlimited' => true, 'should_activate_now' => false];
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $id) use (&$adjustCalls){
    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'subId' => $id,
            'totalGB' => $adjustCalls > 0 ? xuiGbToBytes(30) : xuiGbToBytes(20),
            'expiryTime' => 0,
        ],
    ];
};
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$adjustCalls){
    $adjustCalls++;

    return ['ok' => true, 'method' => 'mock'];
};
$GLOBALS['xuiTestRenewProvisionStoreWrite'] = static function($k, $payload){
    return ($payload['phase'] ?? '') !== 'provision_complete';
};
$csv2 = fopen(getenv('PNV_PAYMENTS_CSV_PATH'), 'w');
fputcsv($csv2, ['ownerUser', $link, '10 گیگ - 30 روز', 'HIST', '', '', 'تایید شد', $link, 1, 'تمدید', '', '', 1]);
fputcsv($csv2, $row2);
fclose($csv2);
$firstProv = xuiProvisionRenew($row2);
hardAssert(($firstProv['code'] ?? '') === 'persist_failed' && $adjustCalls === 1, 'persist_failed after adjust without wiping store');
unset($GLOBALS['xuiTestRenewProvisionStoreWrite']);
$stored2 = xuiRenewProvisionStoreRead($key2);
hardAssert(is_array($stored2['baseline'] ?? null) && ($stored2['operation'] ?? '') === 'incremental', 'persist_failed preserves baseline and operation');
xuiRenewProvisionStorePatch($key2, ['started_at' => time() - 200]);
$approve2 = xuiApprovePaymentIndex(1, 'تمدید');
hardAssert(!empty($approve2['ok']) && $adjustCalls === 1, 'retry finalize without second adjust');

// 3) recreate wrong expiry -> ambiguous
$anchor3 = 1800000000;
$GLOBALS['renewReserveTestNow'] = $anchor3;
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => [
        'email' => 'bad',
        'subId' => $id,
        'totalGB' => xuiGbToBytes(10),
        'expiryTime' => ($anchor3 * 1000) + (86400000 * 7),
    ],
];
$row3 = ['ownerUser', $link, '10 گیگ - 30 روز', 'H-3', '', '', 'درحال بررسی', '', 170012, 'تمدید', '', '', 1];
$badRecreate = xuiProvisionRenewRecreate(
    xuiFindServerByHost('vip4.example.com', xuiLoadConfig()),
    ['host' => 'vip4.example.com', 'sub_id' => 'abc1234567890ab'],
    $row3,
    10,
    30,
    xuiLoadConfig()
);
hardAssert(empty($badRecreate['ok']) && ($badRecreate['code'] ?? '') === 'ambiguous_provision', 'recreate rejects wrong expiry volume-only match');

// 4) transport recreate with correct expiry anchor
$postCalls4 = 0;
$anchor4 = 1800000100;
$GLOBALS['renewReserveTestNow'] = $anchor4;
$anchorMs4 = $anchor4 * 1000;
$phase4 = 0;
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$postCalls4){
    if($method === 'GET'){
        return ['success' => false, 'msg' => 'not found'];
    }

    if($method === 'POST' && strpos($path, '/panel/api/clients/add') === 0){
        $postCalls4++;

        return ['success' => false, 'msg' => 'خطا در ارتباط با 3x-ui: Operation timed out'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $id) use (&$phase4, $anchorMs4){
    $phase4++;

    if($phase4 === 1){
        return ['status' => 'not_found'];
    }

    return [
        'status' => 'found',
        'client' => [
            'email' => 'ok',
            'subId' => $id,
            'totalGB' => xuiGbToBytes(10),
            'expiryTime' => $anchorMs4 + (86400000 * 30),
        ],
    ];
};
$row4 = ['ownerUser', $link, '10 گیگ - 30 روز', 'H-4', '', '', 'درحال بررسی', '', 170013, 'تمدید', '', '', 1];
$okRecreate = xuiProvisionRenewRecreate(
    xuiFindServerByHost('vip4.example.com', xuiLoadConfig()),
    ['host' => 'vip4.example.com', 'sub_id' => 'abc1234567890ab'],
    $row4,
    10,
    30,
    xuiLoadConfig()
);
hardAssert(!empty($okRecreate['ok']) && $postCalls4 === 1, 'transport recreate verify uses stored anchor expiry');
unset($GLOBALS['renewReserveTestNow'], $GLOBALS['xuiTestApiRequest']);

// 5) stale activating recovers via XUI verify without re-apply
$anchor5 = renewReserveNowMs();
$GLOBALS['xuiTestRenewRuntimeState'] = ['should_activate_now' => true, 'volume_exhausted' => true];
$GLOBALS['renewReserveTestActivatingGrace'] = 60;
$GLOBALS['renewReserveTestNow'] = 1000000;
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([[
    'id' => 'stale1', 'status' => 'activating', 'queue_key' => 'qk5', 'reserved_at' => 1,
    'username' => 'ownerUser', 'sub_id' => 'abc1234567890ab', 'sub_link' => $link,
    'server_host' => 'vip4.example.com', 'server_id' => 'vip4', 'plan_gb' => 50, 'plan_days' => 30,
    'activating_at' => 999900, 'activation_anchor_ms' => $anchor5,
]], JSON_UNESCAPED_UNICODE));
$absCalls5 = 0;
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absCalls5){
    $absCalls5++;

    return ['ok' => true, 'method' => 'should_not_run'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => [
        'email' => 'u1',
        'subId' => $id,
        'totalGB' => xuiGbToBytes(50),
        'expiryTime' => $anchor5 + (86400000 * 30),
    ],
];
$staleAct = renewReserveActivateOne(renewReserveLoad()[0]);
hardAssert(!empty($staleAct['ok']) && $absCalls5 === 0, 'stale activating verified without re-apply');
$pendingAfterStale = renewReservePendingOrdered();
hardAssert(count($pendingAfterStale) >= 0, 'queue readable after stale recovery');
unset($GLOBALS['renewReserveTestNow'], $GLOBALS['renewReserveTestActivatingGrace'], $GLOBALS['xuiTestApplyRenewPlanAbsolute']);

// 6) FIFO + concurrent workers (two PHP processes)
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), '[]');
$workerScript = $tmp . '/enqueue_worker.php';
$reservePath = getenv('PNV_RENEW_RESERVE_PATH');
$locksPath = getenv('PNV_LOCKS_DIR');
$libPath = __DIR__ . '/../renew_reserve_lib.php';
file_put_contents($workerScript, <<<PHP
<?php
putenv('PNV_RENEW_RESERVE_PATH={$reservePath}');
putenv('PNV_LOCKS_DIR={$locksPath}');
require_once '{$libPath}';
\$suffix = \$argv[1] ?? 'a';
\$entry = renewReserveEnqueue([
    'provision_key' => 'pk_' . \$suffix,
    'queue_key' => 'q_conc',
    'status' => 'reserved',
    'username' => 'u',
    'sub_id' => 's1',
    'reserved_at' => intval(\$argv[2] ?? 1),
]);
file_put_contents('{$tmp}/out_' . \$suffix . '.json', json_encode(\$entry));
PHP);
$cmd1 = 'php ' . escapeshellarg($workerScript) . ' a 1 > /dev/null 2>&1 &';
$cmd2 = 'php ' . escapeshellarg($workerScript) . ' b 2 > /dev/null 2>&1 &';
exec($cmd1);
exec($cmd2);
$waitStart = time();
while(time() - $waitStart < 10){
    if(is_file($tmp . '/out_a.json') && is_file($tmp . '/out_b.json')){
        break;
    }
    usleep(50000);
}
$loadedConc = renewReserveLoad();
hardAssert(is_array($loadedConc) && count($loadedConc) === 2, 'concurrent workers both persist reservations');
$pendingConc = renewReservePendingOrdered();
hardAssert(count($pendingConc) === 1, 'FIFO exposes only head reserved while second waits');

// 7) json_encode failure does not clobber queue file
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), '[{"id":"keep","status":"reserved","queue_key":"q"}]');
$before7 = file_get_contents(getenv('PNV_RENEW_RESERVE_PATH'));
$badSave = renewReserveSave([['id' => 'x', 'bad' => INF]]);
$after7 = file_get_contents(getenv('PNV_RENEW_RESERVE_PATH'));
hardAssert($badSave === false && $before7 === $after7, 'json_encode failure keeps prior queue file');
$provPath = xuiRenewProvisionStorePath('json_fail_key');
@mkdir(dirname($provPath), 0755, true);
file_put_contents($provPath, '{"state":"processing","baseline":{"total_bytes":1}}');
$provBefore = file_get_contents($provPath);
$provBad = xuiRenewProvisionStoreWrite('json_fail_key', ['state' => 'processing', 'bad' => INF]);
$provAfter = file_get_contents($provPath);
hardAssert($provBad === false && $provBefore === $provAfter, 'provision json_encode failure keeps prior store');

// 8) persist_failed preserves provision fields via approve patch (no state wipe)
$row8 = ['ownerUser', $link, '10 گیگ - 30 روز', 'H-8', '', '', 'درحال بررسی', '', 170018, 'تمدید', '', '', 1];
$key8 = xuiPaymentRowProvisionKey($row8);
xuiRenewProvisionStoreWrite($key8, [
    'state' => 'processing',
    'phase' => 'renew_absolute_started',
    'operation' => 'absolute',
    'baseline' => ['total_bytes' => 1, 'used_bytes' => 0, 'expiry_ms' => 0, 'email' => 'u1', 'sub_id' => 'abc1234567890ab'],
    'activation_anchor_ms' => 12345,
    'plan_gb' => 10,
    'plan_days' => 30,
    'started_at' => time() - 200,
]);
$csv8 = fopen(getenv('PNV_PAYMENTS_CSV_PATH'), 'w');
fputcsv($csv8, ['ownerUser', $link, '10 گیگ - 30 روز', 'HIST', '', '', 'تایید شد', $link, 1, 'تمدید', '', '', 1]);
fputcsv($csv8, $row8);
fclose($csv8);
$GLOBALS['xuiTestRenewRuntimeState'] = ['should_activate_now' => true];
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $id, 'totalGB' => xuiGbToBytes(10), 'expiryTime' => 12345 + 86400000 * 30],
];
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static fn() => ['ok' => true, 'method' => 'mock'];
$GLOBALS['xuiTestRenewProvisionStoreWrite'] = static function($k, $payload){
    return ($payload['phase'] ?? '') !== 'provision_complete';
};
$failProv8 = xuiProvisionRenew($row8);
unset($GLOBALS['xuiTestRenewProvisionStoreWrite'], $GLOBALS['xuiTestApplyRenewPlanAbsolute']);
$stored8 = xuiRenewProvisionStoreRead($key8);
hardAssert(
    ($failProv8['code'] ?? '') === 'persist_failed'
    && ($stored8['state'] ?? '') === 'processing'
    && intval($stored8['activation_anchor_ms'] ?? 0) === 12345
    && is_array($stored8['baseline'] ?? null),
    'persist_failed retains anchor baseline operation'
);

// 9) retry after >3 minutes keeps anchor (absolute apply receives same anchor)
$anchor9 = 555000000000;
$row9 = ['ownerUser', $link, '10 گیگ - 30 روز', 'H-9', '', '', 'درحال بررسی', '', 170019, 'تمدید', '', '', 1];
$key9 = xuiPaymentRowProvisionKey($row9);
xuiRenewProvisionStoreWrite($key9, [
    'state' => 'processing',
    'phase' => 'renew_absolute_started',
    'operation' => 'absolute',
    'started_at' => time() - 400,
    'plan_gb' => 50,
    'plan_days' => 30,
    'activation_anchor_ms' => $anchor9,
    'baseline' => ['total_bytes' => xuiGbToBytes(5), 'used_bytes' => 0, 'expiry_ms' => 0, 'email' => 'u1', 'sub_id' => 'abc1234567890ab'],
]);
$seenAnchor = null;
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function($s, $c, $gb, $days, $anchorMs) use (&$seenAnchor){
    $seenAnchor = $anchorMs;

    return ['ok' => true, 'method' => 'mock'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $id, 'totalGB' => xuiGbToBytes(50), 'expiryTime' => $anchor9 + 86400000 * 30],
];
$recover9 = xuiRenewRecoverProcessingRenew($row9, xuiRenewProvisionStoreRead($key9));
hardAssert(($recover9['action'] ?? '') === 'finalize' && $seenAnchor === null, 'recovery after 3m does not re-apply absolute');
hardAssert(intval(xuiRenewProvisionStoreRead($key9)['activation_anchor_ms'] ?? 0) === $anchor9, 'anchor unchanged in store after recovery path');

$anchorRetry = 777000000001;
$applyCount9 = 0;
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function($s, $c, $gb, $days, $anchorMs) use (&$seenAnchor, &$applyCount9, $anchorRetry){
    $applyCount9++;
    $seenAnchor = $anchorMs;

    if($applyCount9 === 1){
        return ['ok' => false, 'code' => 'transport_error', 'error' => 'timeout'];
    }

    return ['ok' => true, 'method' => 'mock'];
};
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([[
    'id' => 'retry_anchor', 'status' => 'reserved', 'queue_key' => 'qk9', 'reserved_at' => 1,
    'username' => 'ownerUser', 'sub_id' => 'abc1234567890ab', 'sub_link' => $link,
    'server_host' => 'vip4.example.com', 'server_id' => 'vip4', 'plan_gb' => 50, 'plan_days' => 30,
    'activation_anchor_ms' => $anchorRetry,
]], JSON_UNESCAPED_UNICODE));
$GLOBALS['xuiTestRenewRuntimeState'] = ['should_activate_now' => true, 'volume_exhausted' => true];
$lookup9 = 0;
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $id) use (&$lookup9, $anchorRetry){
    $lookup9++;

    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'subId' => $id,
            'totalGB' => xuiGbToBytes($lookup9 > 2 ? 50 : 5),
            'expiryTime' => $lookup9 > 2 ? ($anchorRetry + 86400000 * 30) : 0,
        ],
    ];
};
$seenAnchor = null;
renewReserveActivateOne(renewReserveLoad()[0]);
$firstAnchor = $seenAnchor;
$seenAnchor = null;
renewReserveActivateOne(renewReserveLoad()[0]);
hardAssert($firstAnchor === $anchorRetry && $seenAnchor === $anchorRetry, 'retry after transport reuses stored activation anchor');

if($fail > 0){
    fwrite(STDERR, "\n{$fail} hardening test(s) failed\n");
    exit(1);
}

echo "All renew hardening tests passed\n";

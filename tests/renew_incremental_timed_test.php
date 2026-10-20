<?php

/**
 * Incremental renew on time-unlimited subs with timed plans (volume + duration).
 */

$fail = 0;

function incTimedAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmp = sys_get_temp_dir() . '/pnv_inc_timed_' . getmypid();
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
$sampleCsv = "ownerUser,{$link},50 گیگ - 30 روز,HIST,,,تایید شد,{$link},1,تمدید,,,1\n";
file_put_contents(getenv('PNV_PAYMENTS_CSV_PATH'), $sampleCsv);

$anchorSec = 1900000000;
$anchorMs = $anchorSec * 1000;
$GLOBALS['renewReserveTestNow'] = $anchorSec;
$GLOBALS['xuiTestRenewApiProbe'] = static fn() => ['ok' => true];

$baseline = [
    'total_bytes' => xuiGbToBytes(100),
    'used_bytes' => 0,
    'expiry_ms' => 0,
    'email' => 'u1',
    'sub_id' => 'abc1234567890ab',
];

// verify helper: unlimited time + timed plan requires expiry from anchor
incTimedAssert(
    !xuiRenewVerifyIncrementalAdjust($baseline, ['totalGB' => xuiGbToBytes(150), 'expiryTime' => 0], 50, 30, $anchorMs),
    'volume-only without expiry fails timed incremental verify'
);
incTimedAssert(
    xuiRenewVerifyIncrementalAdjust(
        $baseline,
        ['totalGB' => xuiGbToBytes(150), 'expiryTime' => $anchorMs + (86400000 * 30)],
        50,
        30,
        $anchorMs
    ),
    'unlimited baseline + 50GB/30d verifies with anchor expiry'
);

// 1) time-unlimited + quota remaining → reserve (no immediate bulkAdjust)
$row = ['ownerUser', $link, '50 گیگ - 30 روز', 'INC-1', '', '', 'درحال بررسی', '', 170100, 'تمدید', '', '', 1];
$adjustPhase = 0;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => true,
    'volume_exhausted' => false,
    'should_activate_now' => false,
];
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$adjustPhase){
    $adjustPhase++;

    return ['ok' => true, 'method' => 'bulkAdjust'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => [
        'email' => 'u1',
        'subId' => $id,
        'totalGB' => $baseline['total_bytes'],
        'expiryTime' => 0,
    ],
];
$GLOBALS['xuiTestRenewApiProbe'] = static fn() => ['ok' => true];
$reservedRenew = xuiProvisionRenew($row);
incTimedAssert(
    !empty($reservedRenew['ok']) && ($reservedRenew['renew_outcome'] ?? '') === 'reserved' && $adjustPhase === 0,
    'time-unlimited active sub reserves timed plan renew'
);

// 1b) Purchased timed plan: if current quota is already exhausted, activate
// the NEW timed plan absolutely (never increment volume onto the old plan).
$row1b = ['ownerUser', $link, '50 گیگ - 30 روز', 'INC-1b', '', '', 'درحال بررسی', '', 1701001, 'تمدید', '', '', 1];
$absoluteCalls1b = 0;
$GLOBALS['renewReserveTestNow'] = $anchorSec;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => true,
    'volume_exhausted' => true,
    'should_activate_now' => true,
];
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absoluteCalls1b){
    $absoluteCalls1b++;
    return ['ok' => true, 'method' => 'absolute_test'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $id) use (&$absoluteCalls1b, $anchorMs, $baseline){
    $after = $absoluteCalls1b > 0;
    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'subId' => $id,
            'totalGB' => $after ? xuiGbToBytes(50) : $baseline['total_bytes'],
            'expiryTime' => $after ? ($anchorMs + (86400000 * 30)) : 0,
        ],
    ];
};
$okRenew = xuiProvisionRenew($row1b);
incTimedAssert(!empty($okRenew['ok']) && ($okRenew['renew_outcome'] ?? '') === 'renewed'
    && $absoluteCalls1b === 1,
    'purchased timed plan activates absolutely once when old quota exhausted');

// 2) Absolute volume was updated, but new expiry was not set: fail closed.
$row2 = ['ownerUser', $link, '50 گیگ - 30 روز', 'INC-2', '', '', 'درحال بررسی', '', 170101, 'تمدید', '', '', 1];
$absoluteCalls2 = 0;
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absoluteCalls2){
    $absoluteCalls2++;
    return ['ok' => true, 'method' => 'absolute_test'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $id) use (&$absoluteCalls2, $baseline){
    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'subId' => $id,
            'totalGB' => $absoluteCalls2 > 0 ? xuiGbToBytes(50) : $baseline['total_bytes'],
            'expiryTime' => 0,
        ],
    ];
};
$partial = xuiProvisionRenew($row2);
incTimedAssert(empty($partial['ok']) && ($partial['code'] ?? '') === 'ambiguous_provision'
    && $absoluteCalls2 === 1, 'timed activation refuses volume-only result without expiry');

// 3) A timeout may happen after 3x-ui applied the absolute plan.
// Re-read the actual client; never send the timed plan twice.
$row3 = ['ownerUser', $link, '50 گیگ - 30 روز', 'INC-3', '', '', 'درحال بررسی', '', 170102, 'تمدید', '', '', 1];
$absoluteCalls3 = 0;
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absoluteCalls3){
    $absoluteCalls3++;
    return ['ok' => false, 'code' => 'transport_error', 'error' => 'timed out'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $id) use (&$absoluteCalls3, $anchorMs, $baseline){
    $after = $absoluteCalls3 > 0;
    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'subId' => $id,
            'totalGB' => $after ? xuiGbToBytes(50) : $baseline['total_bytes'],
            'expiryTime' => $after ? ($anchorMs + (86400000 * 30)) : 0,
        ],
    ];
};
$transportOk = xuiProvisionRenew($row3);
incTimedAssert(!empty($transportOk['ok']) && $absoluteCalls3 === 1,
    'timed absolute timeout verifies server state without duplicate mutation');

// 4) Timed purchase cannot silently fall back to legacy volume-only API.
$row4 = ['ownerUser', $link, '50 گیگ - 30 روز', 'INC-4', '', '', 'درحال بررسی', '', 170103, 'تمدید', '', '', 1];
$legacyCalls = 0;
$absoluteCalls4 = 0;
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$legacyCalls){
    $legacyCalls++;
    return ['ok' => true, 'method' => 'forbidden_volume_only'];
};
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absoluteCalls4){
    $absoluteCalls4++;
    return ['ok' => false, 'code' => 'activation_failed', 'error' => 'API endpoint rejected'];
};
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $id, 'totalGB' => xuiGbToBytes(100), 'expiryTime' => 0],
];
$legacyBlock = xuiProvisionRenew($row4);
incTimedAssert(empty($legacyBlock['ok']) && $legacyCalls === 0 && $absoluteCalls4 === 1,
    'timed plan never uses legacy volume-only incremental fallback');

// 5) retry recovery without second bulkAdjust
unset($GLOBALS['xuiTestApiRequest']);
$adjustCalls = 0;
$row5 = ['ownerUser', $link, '50 گیگ - 30 روز', 'INC-5', '', '', 'درحال بررسی', '', 170104, 'تمدید', '', '', 1];
$key5 = xuiPaymentRowProvisionKey($row5);
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$adjustCalls){
    $adjustCalls++;

    return ['ok' => true, 'method' => 'bulkAdjust'];
};
// Simulate an in-flight incremental operation created by the older version.
$csv5 = fopen(getenv('PNV_PAYMENTS_CSV_PATH'), 'w');
fwrite($csv5, $sampleCsv);
fputcsv($csv5, $row5);
fclose($csv5);
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $id) use (&$adjustCalls, $anchorMs){
    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'subId' => $id,
            'totalGB' => xuiGbToBytes($adjustCalls > 0 ? 150 : 100),
            'expiryTime' => $adjustCalls > 0 ? ($anchorMs + 86400000 * 30) : 0,
        ],
    ];
};
// Stored operation below represents a legacy timed incremental pending recovery.
xuiRenewProvisionStoreWrite($key5, [
    'state' => 'processing',
    'phase' => 'renew_adjust_started',
    'operation' => 'incremental',
    'baseline' => $baseline,
    'plan_gb' => 50,
    'plan_days' => 30,
    'activation_anchor_ms' => $anchorMs,
    'started_at' => time() - 400,
]);
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => [
        'email' => 'u1',
        'subId' => $id,
        'totalGB' => xuiGbToBytes(150),
        'expiryTime' => $anchorMs + (86400000 * 30),
    ],
];
$approve5 = xuiApprovePaymentIndex(1, 'تمدید');
incTimedAssert(!empty($approve5['ok']) && $adjustCalls === 0, 'legacy timed incremental recovery finalizes without another API write');

unset($GLOBALS['renewReserveTestNow'], $GLOBALS['xuiTestAdjustClientTraffic'], $GLOBALS['xuiTestRenewLookupClient'], $GLOBALS['xuiTestRenewRuntimeState']);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} incremental timed test(s) failed\n");
    exit(1);
}

echo "All incremental timed renew tests passed\n";

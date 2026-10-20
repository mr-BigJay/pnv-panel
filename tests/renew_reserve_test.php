<?php

/**
 * Smart renew reserve + regression (isolated, mock XUI).
 * php tests/renew_reserve_test.php
 */

$results = [];

function renewReserveResetStores($locksDir, $reservePath){
    @array_map('unlink', glob(rtrim($locksDir, '/') . '/*'));
    file_put_contents($reservePath, '[]');
}

function renewReserveAssert($name, $cond){
    global $results;
    $results[] = [$name, (bool)$cond];
}

$tmpRoot = sys_get_temp_dir() . '/pnv_reserve_test_' . getmypid();
@mkdir($tmpRoot, 0777, true);
$csvPath = $tmpRoot . '/payments.csv';
$configPath = $tmpRoot . '/xui_servers.json';
$locksDir = $tmpRoot . '/locks';
$reservePath = $tmpRoot . '/renew_reservations.json';
@mkdir($locksDir, 0777, true);

putenv('PNV_PAYMENTS_CSV_PATH=' . $csvPath);
putenv('PNV_XUI_CONFIG_PATH=' . $configPath);
putenv('PNV_LOCKS_DIR=' . $locksDir);
putenv('PNV_RENEW_RESERVE_PATH=' . $reservePath);

require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../xui_lib.php';
require_once __DIR__ . '/../renew_reserve_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';

$server = [
    'id' => 'vip4',
    'host' => 'vip4.example.com',
    'inbound_id' => 1,
    'base_url' => 'https://example.test/',
    'api_token' => 'test',
];
file_put_contents($configPath, json_encode([
    'enabled' => true,
    'sub_port' => 2096,
    'renew_server_ids' => ['vip4'],
    'servers' => [$server],
], JSON_UNESCAPED_UNICODE));

$link = 'https://vip4.example.com:2096/sub/abc1234567890ab';

$historyCsv = static function() use ($csvPath, $link){
    $h = fopen($csvPath, 'w');
    fputcsv($h, [
        'ownerUser', $link, '10 گیگ - 30 روز', 'HIST', '', '', 'تایید شد', $link,
        1699999999, 'تمدید', '', '', 1500000,
    ]);
    fclose($h);
};

$baseRow = [
    'ownerUser', $link, '50 گیگ - 30 روز - 500 هزار', 'AUTO-3001', '', '', 'درحال بررسی', '',
    1700001000, 'تمدید', '', '', 5000000,
];

$GLOBALS['xuiTestRenewApiProbe'] = static fn() => ['ok' => true];
$adjustCalls = 0;
$absoluteCalls = 0;
$createPosts = 0;

$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$adjustCalls){
    $adjustCalls++;
    return ['ok' => true, 'method' => 'test_adjust'];
};
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absoluteCalls){
    $absoluteCalls++;
    return ['ok' => true, 'method' => 'test_absolute'];
};
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$createPosts){
    if($method === 'POST'){
        $createPosts++;
    }
    return ['success' => false, 'msg' => 'mock'];
};

// Buy path unchanged (no smart-renew hooks in buy)
$buySrc = file_get_contents(__DIR__ . '/../xui_lib.php');
renewReserveAssert('buy path still uses xuiProvisionBuy', strpos($buySrc, 'function xuiProvisionBuy(') !== false);

// Unlimited immediate renew
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$adjustCalls = 0;
$rowUnlimited = $baseRow;
$rowUnlimited[3] = 'AUTO-3101';
$rowUnlimited[8] = 1700001101;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => true,
    'should_activate_now' => false,
];
$unlimitedAnchorSec = 1700001101;
$GLOBALS['renewReserveTestNow'] = $unlimitedAnchorSec;
$unlimitedAnchorMs = $unlimitedAnchorSec * 1000;
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $subId) use (&$adjustCalls, $unlimitedAnchorMs){
    $after = $adjustCalls > 0;

    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'subId' => $subId,
            'totalGB' => xuiGbToBytes($after ? 60 : 10),
            'expiryTime' => $after ? ($unlimitedAnchorMs + (86400000 * 30)) : 0,
        ],
    ];
};
$unlimited = xuiProvisionRenew($rowUnlimited);
unset($GLOBALS['renewReserveTestNow']);
renewReserveAssert(
    'unlimited-time active sub renew reserved',
    ($unlimited['renew_outcome'] ?? '') === 'reserved' && $adjustCalls === 0 && $absoluteCalls === 0
);

// Purchased time-unlimited plan must immediately add GB even while the
// EXISTING subscription is active with quota remaining.
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$adjustCalls = 0;
$absoluteCalls = 0;
$rowUnlimitedQuota = $baseRow;
$rowUnlimitedQuota[2] = '25 گیگ - نامحدود زمان';
$rowUnlimitedQuota[3] = 'AUTO-3101-UNL';
$rowUnlimitedQuota[8] = 1700001105;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => true,
    'volume_exhausted' => false,
    'should_activate_now' => false,
];
$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $id) use (&$adjustCalls){
    return ['status'=>'found', 'client'=>[
        'email'=>'u1', 'subId'=>$id,
        'totalGB'=>xuiGbToBytes($adjustCalls ? 125 : 100),
        'up'=>xuiGbToBytes(10), 'down'=>0, 'expiryTime'=>0,
    ]];
};
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$adjustCalls){
    $adjustCalls++;
    return ['ok'=>true, 'method'=>'test_add_only'];
};
$unlimitedQuotaResult = xuiProvisionRenew($rowUnlimitedQuota);
renewReserveAssert('purchased unlimited immediately adds GB with active remaining quota',
    !empty($unlimitedQuotaResult['ok'])
    && ($unlimitedQuotaResult['renew_outcome'] ?? '') === 'renewed'
    && $adjustCalls === 1 && $absoluteCalls === 0
    && count(renewReserveLoad()) === 0);

// Purchase duration, not the current expiry type, determines the policy.
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$adjustCalls = 0;
$rowUnlimitedOnTimed = $baseRow;
$rowUnlimitedOnTimed[2] = '15 گیگ - نامحدود زمان';
$rowUnlimitedOnTimed[3] = 'AUTO-3101-MIX';
$rowUnlimitedOnTimed[8] = 1700001106;
$oldExpiry = (renewReserveNow() + 3600) * 1000;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => false,
    'time_remaining_sec' => 3600,
    'volume_exhausted' => false,
    'should_activate_now' => false,
];
$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $id) use (&$adjustCalls, $oldExpiry){
    return ['status'=>'found', 'client'=>[
        'email'=>'u1', 'subId'=>$id,
        'totalGB'=>xuiGbToBytes($adjustCalls ? 115 : 100),
        'up'=>0, 'down'=>0, 'expiryTime'=>$oldExpiry,
    ]];
};
$unlimitedOnTimed = xuiProvisionRenew($rowUnlimitedOnTimed);
renewReserveAssert('unlimited purchase adds GB to existing timed client immediately without changing expiry',
    !empty($unlimitedOnTimed['ok'])
    && ($unlimitedOnTimed['renew_outcome'] ?? '') === 'renewed'
    && $adjustCalls === 1 && count(renewReserveLoad()) === 0);

// No silent success when quota changes but XUI unexpectedly alters expiry.
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$adjustCalls = 0;
$rowAlteredExpiry = $rowUnlimitedOnTimed;
$rowAlteredExpiry[3] = 'AUTO-3101-EXPIRE';
$rowAlteredExpiry[8] = 1700001107;
$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $id) use (&$adjustCalls, $oldExpiry){
    return ['status'=>'found', 'client'=>[
        'email'=>'u1', 'subId'=>$id,
        'totalGB'=>xuiGbToBytes($adjustCalls ? 115 : 100),
        'up'=>0, 'down'=>0,
        'expiryTime'=>$adjustCalls ? 0 : $oldExpiry,
    ]];
};
$badExpiry = xuiProvisionRenew($rowAlteredExpiry);
renewReserveAssert('unlimited volume add refuses read-back if expiry changed',
    empty($badExpiry['ok']) && ($badExpiry['code'] ?? '') === 'ambiguous_provision');

// Quota 0 in XUI means unlimited total traffic; never turn it into a finite cap.
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$adjustCalls = 0;
$rowNoBaseline = $rowUnlimitedQuota;
$rowNoBaseline[3] = 'AUTO-3101-ZERO';
$rowNoBaseline[8] = 1700001108;
$GLOBALS['xuiTestRenewLookupClient'] = static fn($srv, $id) => [
    'status'=>'found', 'client'=>['email'=>'u1','subId'=>$id,'totalGB'=>0,'expiryTime'=>0],
];
$noBaseline = xuiProvisionRenew($rowNoBaseline);
renewReserveAssert('unlimited zero-quota baseline fails without mutation',
    empty($noBaseline['ok'])
    && ($noBaseline['code'] ?? '') === 'invalid_quota_baseline'
    && $adjustCalls === 0 && count(renewReserveLoad()) === 0);

renewReserveAssert('purchased timed policy waits when quota and expiry remain',
    renewReserveDecideAction([
        'ambiguous'=>false,'time_unlimited'=>true,
        'should_activate_now'=>false,'volume_exhausted'=>false,
    ], 30) === 'reserve');
renewReserveAssert('purchased timed policy activates on volume depletion',
    renewReserveDecideAction([
        'ambiguous'=>false,'time_unlimited'=>true,
        'should_activate_now'=>true,'volume_exhausted'=>true,
    ], 30) === 'activate_now');
renewReserveAssert('purchased timed policy activates on expiry',
    renewReserveDecideAction([
        'ambiguous'=>false,'time_unlimited'=>false,
        'should_activate_now'=>true,'time_expired'=>true,
    ], 30) === 'activate_now');

// Unlimited time + volume exhausted → add to cap (legacy), not absolute reset to plan size
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$absoluteCallsUnl = 0;
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absoluteCallsUnl){
    $absoluteCallsUnl++;

    return ['ok' => false, 'error' => 'absolute must not run'];
};
$rowUnlEx = $baseRow;
$rowUnlEx[2] = '50 گیگ - نامحدود زمان';
$rowUnlEx[3] = 'AUTO-3104';
$rowUnlEx[8] = 1700001104;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => true,
    'volume_exhausted' => true,
    'should_activate_now' => true,
];
$unlExLookups = 0;
$GLOBALS['xuiTestRenewLookupClient'] = static function($s, $subId) use (&$unlExLookups){
    $unlExLookups++;
    $after = $unlExLookups > 1;

    return [
        'status' => 'found',
        'client' => [
            'email' => 'u1',
            'id' => 'cid1',
            'subId' => $subId,
            'totalGB' => xuiGbToBytes($after ? 170 : 120),
            'up' => $after ? 0 : xuiGbToBytes(120),
            'down' => 0,
            'expiryTime' => 0,
            '_inbound_id' => 1,
        ],
    ];
};
$GLOBALS['xuiTestAdjustClientTraffic'] = static fn() => ['ok' => false, 'error' => 'bulkAdjust skipped'];
$GLOBALS['xuiTestApiRequest'] = static function($server, $method, $path, $payload = null){
    if($method === 'POST' && strpos($path, 'updateClient') !== false){
        return ['success' => true];
    }

    return ['success' => false, 'msg' => 'mock'];
};
$unlEx = xuiProvisionRenew($rowUnlEx);
renewReserveAssert(
    'unlimited exhausted adds volume not absolute',
    ($unlEx['renew_outcome'] ?? '') === 'renewed'
    && $absoluteCallsUnl === 0
    && (($unlEx['method'] ?? '') === 'updateClient' || ($unlEx['method'] ?? '') === 'incremental_unlimited')
);
unset($GLOBALS['xuiTestApiRequest']);
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$adjustCalls){
    $adjustCalls++;

    return ['ok' => true, 'method' => 'test_adjust'];
};
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absoluteCalls){
    $absoluteCalls++;

    return ['ok' => true, 'method' => 'test_absolute'];
};
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$createPosts){
    if($method === 'POST'){
        $createPosts++;
    }

    return ['success' => false, 'msg' => 'mock'];
};

// Recreate deleted sub
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$rowRecreate = $baseRow;
$rowRecreate[3] = 'AUTO-3102';
$rowRecreate[8] = 1700001102;
$createPosts = 0;
unset($GLOBALS['xuiTestRenewRuntimeState']);
$GLOBALS['xuiTestRenewLookupClient'] = static fn() => ['status' => 'not_found'];
$GLOBALS['xuiTestCreateClientForRenewPlan'] = static fn() => [
    'ok' => true,
    'email' => 'newu',
    'sub_id' => 'abc1234567890ab',
    'link' => $link,
];
$recreated = xuiProvisionRenew($rowRecreate);
renewReserveAssert('deleted sub recreated with plan', ($recreated['renew_outcome'] ?? '') === 'recreated');
unset($GLOBALS['xuiTestCreateClientForRenewPlan']);

// Reserve limited-time with remaining quota
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$adjustCalls = 0;
$absoluteCalls = 0;
$rowReserve = $baseRow;
$rowReserve[3] = 'AUTO-3103';
$rowReserve[8] = 1700001103;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => false,
    'should_activate_now' => false,
    'time_remaining_sec' => 172800,
    'volume_remaining_bytes' => xuiGbToBytes(5),
];
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $subId) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $subId, 'totalGB' => xuiGbToBytes(10), 'expiryTime' => (renewReserveNow() + 86400) * 1000],
];
$reserved = xuiProvisionRenew($rowReserve);
renewReserveAssert('limited renew reserved not applied immediately', ($reserved['renew_outcome'] ?? '') === 'reserved' && $adjustCalls === 0 && $absoluteCalls === 0);
$store = renewReserveLoad();
renewReserveAssert('reserve persisted after restart', count($store) === 1 && ($store[0]['status'] ?? '') === 'reserved');

// Existing queued unlimited-time purchase from an old deployment: cron
// must add GB at its next pass, even if current quota/time has not expired.
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$queueAddCalls = 0;
$queueBaselineMs = renewReserveNowMs();
$oldUnlimitedReserve = [
    'id'=>'old_unlimited_rsv',
    'status'=>'reserved',
    'username'=>'ownerUser',
    'sub_id'=>'abc1234567890ab',
    'sub_link'=>$link,
    'server_host'=>'vip4.example.com',
    'server_id'=>'vip4',
    'plan_gb'=>10,
    'plan_days'=>0,
    'queue_key'=>renewReserveQueueKey('ownerUser', 'vip4.example.com', 'abc1234567890ab'),
    'reserved_at'=>renewReserveNow()-500,
];
file_put_contents($reservePath, json_encode([$oldUnlimitedReserve], JSON_UNESCAPED_UNICODE));
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous'=>false,'time_unlimited'=>true,
    'should_activate_now'=>false,'volume_exhausted'=>false,
];
$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $id) use (&$queueAddCalls){
    return ['status'=>'found', 'client'=>[
        'email'=>'u1','subId'=>$id,
        'totalGB'=>xuiGbToBytes($queueAddCalls > 0 ? 110 : 100),
        'up'=>0,'down'=>0,'expiryTime'=>0,
    ]];
};
$GLOBALS['xuiTestAdjustClientTraffic'] = static function() use (&$queueAddCalls){
    $queueAddCalls++;
    return ['ok'=>true,'method'=>'queued_bulk_add'];
};
$oldQueueResult = renewReserveActivateOne(renewReserveLoad()[0]);
renewReserveAssert('old queued unlimited plan immediately adds quota on cron pass',
    !empty($oldQueueResult['ok']) && $queueAddCalls===1
    && (renewReserveLoad()[0]['status'] ?? '')==='activated');
$oldQueueAgain = renewReserveActivateOne(renewReserveLoad()[0]);
renewReserveAssert('old queued unlimited does not add quota a second time',
    !empty($oldQueueAgain['already']) && $queueAddCalls===1);

// A worker crash after a completed volume change must be recovered from the
// stored baseline, not by issuing a second add mutation.
$staleQueue = array_merge($oldUnlimitedReserve, [
    'status'=>'activating',
    'activation_operation'=>'incremental_unlimited',
    'activation_baseline'=>[
        'total_bytes'=>xuiGbToBytes(100),
        'used_bytes'=>0,
        'expiry_ms'=>0,
        'email'=>'u1',
        'sub_id'=>'abc1234567890ab',
    ],
    'activation_anchor_ms'=>$queueBaselineMs,
    'activating_at'=>renewReserveNow()-400,
]);
file_put_contents($reservePath, json_encode([$staleQueue], JSON_UNESCAPED_UNICODE));
$recoveredQueue = renewReserveActivateOne(renewReserveLoad()[0]);
renewReserveAssert('stale queued unlimited volume add recovers without another API mutation',
    !empty($recoveredQueue['ok']) && $queueAddCalls===1
    && (renewReserveLoad()[0]['status'] ?? '')==='activated');

// A failed or unreadable verification must NEVER trigger another addition.
$staleQueue['status']='activating';
file_put_contents($reservePath, json_encode([$staleQueue], JSON_UNESCAPED_UNICODE));
$GLOBALS['xuiTestRenewLookupClient'] = static fn($srv,$id) => [
    'status'=>'found','client'=>[
        'email'=>'u1','subId'=>$id,'totalGB'=>xuiGbToBytes(100),'expiryTime'=>0
    ]
];
$unverifiedQueue = renewReserveActivateOne(renewReserveLoad()[0]);
renewReserveAssert('unverified queued unlimited is blocked from duplicate API write',
    empty($unverifiedQueue['ok']) && ($unverifiedQueue['code']??'')==='ambiguous'
    && $queueAddCalls===1 && (renewReserveLoad()[0]['status'] ?? '')==='ambiguous');

// FIFO two reserves same sub
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$rowFifo1 = $baseRow;
$rowFifo1[3] = 'AUTO-3001';
$rowFifo1[8] = 1700001000;
$row2 = $baseRow;
$row2[3] = 'AUTO-3002';
$row2[8] = 1700001001;
renewReserveCreateFromPayment($rowFifo1, ['host' => 'vip4.example.com', 'sub_id' => 'abc1234567890ab'], $server, 50, 30, $rowFifo1[2]);
renewReserveCreateFromPayment($row2, ['host' => 'vip4.example.com', 'sub_id' => 'abc1234567890ab'], $server, 50, 30, $row2[2]);
$ordered = renewReservePendingOrdered();
renewReserveAssert(
    'FIFO queue keeps two reservations',
    count(renewReserveLoad()) === 2 && ($ordered[0]['payment_tracking'] ?? '') === 'AUTO-3001'
);

// Activation when volume exhausted
$historyCsv();
file_put_contents($reservePath, json_encode([[
    'id' => 'rsv_test1',
    'status' => 'reserved',
    'username' => 'ownerUser',
    'sub_id' => 'abc1234567890ab',
    'sub_link' => $link,
    'server_host' => 'vip4.example.com',
    'server_id' => 'vip4',
    'plan_gb' => 50,
    'plan_days' => 30,
    'queue_key' => renewReserveQueueKey('ownerUser', 'vip4.example.com', 'abc1234567890ab'),
    'reserved_at' => renewReserveNow() - 100,
]], JSON_UNESCAPED_UNICODE));
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => false,
    'should_activate_now' => true,
    'volume_exhausted' => true,
];
$anchorMs = renewReserveNowMs();
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $subId) => [
    'status' => 'found',
    'client' => [
        'email' => 'u1',
        'subId' => $subId,
        'totalGB' => xuiGbToBytes(50),
        'expiryTime' => $anchorMs + (86400000 * 30),
    ],
];
$absoluteCalls = 0;
$act = renewReserveActivateOne(renewReserveLoad()[0]);
renewReserveAssert('activation on volume exhausted', !empty($act['ok']) && $absoluteCalls === 1);

// Not due keeps reserved
file_put_contents($reservePath, json_encode([[
    'id' => 'rsv_test2',
    'status' => 'reserved',
    'username' => 'ownerUser',
    'sub_id' => 'abc1234567890ab',
    'sub_link' => $link,
    'server_host' => 'vip4.example.com',
    'server_id' => 'vip4',
    'plan_gb' => 50,
    'plan_days' => 30,
    'queue_key' => 'qk2',
    'reserved_at' => renewReserveNow(),
]], JSON_UNESCAPED_UNICODE));
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'should_activate_now' => false,
    'time_remaining_sec' => 86400,
    'volume_remaining_bytes' => xuiGbToBytes(5),
];
$absoluteCalls = 0;
$skip = renewReserveActivateOne(renewReserveLoad()[0]);
renewReserveAssert('not due skips without absolute apply', !empty($skip['skipped']) && $absoluteCalls === 0);

// API unreachable keeps reserve
file_put_contents($reservePath, json_encode([[
    'id' => 'rsv_test3',
    'status' => 'reserved',
    'username' => 'ownerUser',
    'sub_id' => 'abc1234567890ab',
    'sub_link' => $link,
    'server_host' => 'vip4.example.com',
    'server_id' => 'vip4',
    'plan_gb' => 50,
    'plan_days' => 30,
    'queue_key' => 'qk3',
    'reserved_at' => renewReserveNow(),
]], JSON_UNESCAPED_UNICODE));
$GLOBALS['xuiTestRenewLookupClient'] = static fn() => ['status' => 'api_error', 'error' => 'down'];
$keep = renewReserveActivateOne(renewReserveLoad()[0]);
renewReserveAssert('XUI down keeps reservation', ($keep['code'] ?? '') === 'api_error' && (renewReserveLoad()[0]['status'] ?? '') === 'reserved');

// Duplicate reserve idempotency
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$rowDup = $baseRow;
$rowDup[3] = 'AUTO-3104';
$rowDup[8] = 1700001104;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => false,
    'should_activate_now' => false,
];
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $subId) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $subId, 'totalGB' => xuiGbToBytes(10), 'expiryTime' => (renewReserveNow() + 86400) * 1000],
];
$r1 = xuiProvisionRenew($rowDup);
$r2 = xuiProvisionRenew($rowDup);
renewReserveAssert('duplicate payment does not duplicate reserve', count(renewReserveLoad()) === 1 && ($r2['renew_reserve_id'] ?? '') === ($r1['renew_reserve_id'] ?? ''));

// Instant pay + admin visibility for reserved/failed
renewReserveResetStores($locksDir, $reservePath);
$historyCsv();
$adminReserveRow = $baseRow;
$adminReserveRow[3] = 'AUTO-3199';
$adminReserveRow[8] = 1700001199;
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => false,
    'should_activate_now' => false,
];
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $subId) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $subId, 'totalGB' => xuiGbToBytes(10), 'expiryTime' => (renewReserveNow() + 86400) * 1000],
];
xuiProvisionRenew($adminReserveRow);

$instantPath = $tmpRoot . '/instant.json';
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $instantPath);
file_put_contents($instantPath, json_encode([[
    'id' => 'ip1', 'user' => 'ownerUser', 'code' => '3001', 'status' => 'paid',
    'sub' => $link, 'renew_outcome' => 'reserved', 'user_renew_message' => renewReserveUserMessageReserved(),
]], JSON_UNESCAPED_UNICODE));
$adminMeta = instantPayAdminRowStatusMeta($baseRow);
renewReserveAssert('admin can read renew payment row meta', is_array($adminMeta));
$adminRow = $adminReserveRow;
$adminRow[6] = 'درحال بررسی';
$reserveDetail = renewReserveAdminDetailForRow($adminRow);
renewReserveAssert('admin renew reserve detail string', $reserveDetail !== '' && strpos($reserveDetail, 'رزرو') !== false);

// Fake clock activation timing
$GLOBALS['renewReserveTestNow'] = 2000000000;
unset($GLOBALS['xuiTestRenewRuntimeState']);
$GLOBALS['xuiTestSubUserinfoExpire'] = 1999999500;
$expiredRuntime = xuiRenewAnalyzeRuntimeState($server, [
    'email' => 'u',
    'expiryTime' => 0,
    'totalGB' => xuiGbToBytes(10),
    'up' => 0,
    'down' => xuiGbToBytes(2),
], $link);
renewReserveAssert('fake clock detects expired time', !empty($expiredRuntime['should_activate_now']));
unset($GLOBALS['renewReserveTestNow'], $GLOBALS['xuiTestSubUserinfoExpire']);

// Cron script exists
renewReserveAssert('cron script present', is_file(__DIR__ . '/../scripts/renew_reserve_cron.php'));

$fail = 0;
echo "Regression results:\n";
echo str_pad('Scenario', 56) . "Result\n";
echo str_repeat('-', 68) . "\n";
foreach($results as [$name, $ok]){
    echo str_pad($name, 56) . ($ok ? 'PASS' : 'FAIL') . "\n";
    if(!$ok){
        $fail++;
    }
}

putenv('PNV_PAYMENTS_CSV_PATH');
putenv('PNV_XUI_CONFIG_PATH');
putenv('PNV_LOCKS_DIR');
putenv('PNV_RENEW_RESERVE_PATH');
putenv('PNV_INSTANT_PAYMENTS_PATH');
unset(
    $GLOBALS['xuiTestRenewApiProbe'],
    $GLOBALS['xuiTestRenewLookupClient'],
    $GLOBALS['xuiTestAdjustClientTraffic'],
    $GLOBALS['xuiTestApplyRenewPlanAbsolute'],
    $GLOBALS['xuiTestCreateClientForRenewPlan'],
    $GLOBALS['xuiTestApiRequest'],
    $GLOBALS['xuiTestRenewRuntimeState']
);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} regression scenario(s) failed\n");
    exit(1);
}

echo "\nAll renew reserve regression scenarios passed\n";

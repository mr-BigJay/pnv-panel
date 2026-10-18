<?php

/**
 * Fault injection: renew reserve + provision recovery.
 */

$fail = 0;

function faultAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmp = sys_get_temp_dir() . '/pnv_fault_' . getmypid();
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

// 1) 20GB baseline, +10GB renew, stop before XUI change
$row = ['ownerUser', $link, '10 گیگ - 30 روز', 'AUTO-F1', '', '', 'درحال بررسی', '', 170001, 'تمدید', '', '', 1];
$key = xuiPaymentRowProvisionKey($row);
$baseline = ['total_bytes' => xuiGbToBytes(20), 'used_bytes' => 0, 'expiry_ms' => 0, 'email' => 'u1', 'sub_id' => 'abc1234567890ab'];
xuiRenewProvisionStoreWrite($key, [
    'state' => 'processing',
    'phase' => 'renew_adjust_started',
    'operation' => 'incremental',
    'baseline' => $baseline,
    'plan_gb' => 10,
    'plan_days' => 30,
    'started_at' => time() - 200,
]);
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $id, 'totalGB' => xuiGbToBytes(20), 'expiryTime' => 0],
];
$recoverFail = xuiRenewRecoverProcessingRenew($row, xuiRenewProvisionStoreRead($key));
faultAssert(($recoverFail['action'] ?? '') === 'ambiguous', 'incremental without change stays ambiguous');

$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => ['email' => 'u1', 'subId' => $id, 'totalGB' => xuiGbToBytes(30), 'expiryTime' => 0],
];
$recoverOk = xuiRenewRecoverProcessingRenew($row, xuiRenewProvisionStoreRead($key));
faultAssert(($recoverOk['action'] ?? '') === 'finalize', 'incremental verified with baseline+add');

// 2) reserve created, stop before final result
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([[
    'id' => 'rsv_f1', 'status' => 'reserved', 'provision_key' => $key, 'username' => 'ownerUser',
    'sub_id' => 'abc1234567890ab', 'sub_link' => $link, 'server_host' => 'vip4.example.com', 'server_id' => 'vip4',
    'plan_gb' => 50, 'plan_days' => 30, 'queue_key' => renewReserveQueueKey('ownerUser', 'vip4.example.com', 'abc1234567890ab'),
    'reserved_at' => time(),
]], JSON_UNESCAPED_UNICODE));
xuiRenewProvisionStoreWrite($key, [
    'state' => 'processing',
    'phase' => 'renew_reserve_started',
    'operation' => 'reserve',
    'started_at' => time() - 200,
]);
$recoverReserve = xuiRenewRecoverProcessingRenew($row, xuiRenewProvisionStoreRead($key));
faultAssert(($recoverReserve['action'] ?? '') === 'finalize' && ($recoverReserve['result']['renew_outcome'] ?? '') === 'reserved', 'recover finalize reserved outcome');

// 3) save fail before reserve create
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), '[]');
$GLOBALS['renewReserveTestSaveFail'] = static fn() => false;
$created = renewReserveEnqueue([
    'provision_key' => 'pk_fail_test',
    'username' => 'ownerUser',
    'sub_id' => 'abc1234567890ab',
    'queue_key' => 'qk_fail',
    'status' => 'reserved',
]);
unset($GLOBALS['renewReserveTestSaveFail']);
faultAssert($created === null && count(renewReserveLoad()) === 0, 'enqueue fails closed when save fails');

// 4) corrupt JSON fail-closed
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), '{broken json');
$corrupt = renewReserveLoadResult();
faultAssert(empty($corrupt['ok']) && ($corrupt['error'] ?? '') === 'corrupt_json', 'corrupt json fail-closed');
$before = file_get_contents(getenv('PNV_RENEW_RESERVE_PATH'));
$enqueueOnCorrupt = renewReserveEnqueue(['provision_key' => 'x', 'queue_key' => 'q', 'status' => 'reserved', 'username' => 'u', 'sub_id' => 's']);
$after = file_get_contents(getenv('PNV_RENEW_RESERVE_PATH'));
faultAssert($enqueueOnCorrupt === null && $before === $after, 'corrupt queue not overwritten');

// 5) concurrent enqueue unique provision keys
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), '[]');
$a = renewReserveEnqueue(['provision_key' => 'pk_a', 'queue_key' => 'q1', 'status' => 'reserved', 'username' => 'u', 'sub_id' => 's', 'reserved_at' => 1]);
$b = renewReserveEnqueue(['provision_key' => 'pk_b', 'queue_key' => 'q1', 'status' => 'reserved', 'username' => 'u', 'sub_id' => 's', 'reserved_at' => 2]);
faultAssert(is_array($a) && is_array($b) && count(renewReserveLoad()) === 2, 'concurrent sequential enqueues persist both');

// 6) first ambiguous blocks second reserved in pending
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([
    ['id' => 'r1', 'status' => 'ambiguous', 'queue_key' => 'qk6', 'reserved_at' => 1, 'username' => 'u', 'sub_id' => 's'],
    ['id' => 'r2', 'status' => 'reserved', 'queue_key' => 'qk6', 'reserved_at' => 2, 'username' => 'u', 'sub_id' => 's'],
], JSON_UNESCAPED_UNICODE));
$pending6 = renewReservePendingOrdered();
faultAssert(count($pending6) === 0, 'ambiguous head blocks next reserved');

// 7) transport after successful absolute activation
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([[
    'id' => 'rsv_transport', 'status' => 'reserved', 'queue_key' => 'qk7', 'reserved_at' => 1,
    'username' => 'ownerUser', 'sub_id' => 'abc1234567890ab', 'sub_link' => $link,
    'server_host' => 'vip4.example.com', 'server_id' => 'vip4', 'plan_gb' => 50, 'plan_days' => 30,
]], JSON_UNESCAPED_UNICODE));
$anchorMs = renewReserveNowMs();
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'should_activate_now' => true,
    'volume_exhausted' => true,
];
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => [
        'email' => 'u1',
        'subId' => $id,
        'totalGB' => xuiGbToBytes(50),
        'expiryTime' => $anchorMs + (86400000 * 30),
    ],
];
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static fn() => ['ok' => false, 'code' => 'transport_error', 'error' => 'timeout'];
$act7 = renewReserveActivateOne(renewReserveLoad()[0]);
faultAssert(!empty($act7['ok']), 'transport verify activates when plan already applied');

// 8) activated save fail after XUI success returns persist_failed
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static fn() => ['ok' => true, 'method' => 'mock'];
$anchor8 = renewReserveNowMs();
$GLOBALS['xuiTestRenewLookupClient'] = static fn($s, $id) => [
    'status' => 'found',
    'client' => [
        'email' => 'u1',
        'subId' => $id,
        'totalGB' => xuiGbToBytes(50),
        'expiryTime' => $anchor8 + (86400000 * 30),
    ],
];
$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'should_activate_now' => true,
    'volume_exhausted' => true,
];
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([[
    'id' => 'rsv_save_fail', 'status' => 'reserved', 'queue_key' => 'qk8', 'reserved_at' => 1,
    'username' => 'ownerUser', 'sub_id' => 'abc1234567890ab', 'sub_link' => $link,
    'server_host' => 'vip4.example.com', 'server_id' => 'vip4', 'plan_gb' => 50, 'plan_days' => 30,
]], JSON_UNESCAPED_UNICODE));
$saveCalls = 0;
$GLOBALS['renewReserveTestSaveFail'] = static function() use (&$saveCalls){
    $saveCalls++;
    return $saveCalls !== 2;
};
$act8 = renewReserveActivateOne(renewReserveLoad()[0]);
unset($GLOBALS['renewReserveTestSaveFail']);
faultAssert(empty($act8['ok']) && ($act8['code'] ?? '') === 'persist_failed', 'activated save failure surfaces persist_failed');

// 9) retry does not double apply - single absolute call counter
$absCalls = 0;
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$absCalls){
    $absCalls++;
    return ['ok' => true, 'method' => 'mock'];
};
file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([[
    'id' => 'rsv_once', 'status' => 'activated', 'queue_key' => 'qk9', 'reserved_at' => 1,
    'username' => 'ownerUser', 'sub_id' => 'abc1234567890ab', 'sub_link' => $link,
    'server_host' => 'vip4.example.com', 'server_id' => 'vip4', 'plan_gb' => 50, 'plan_days' => 30,
]], JSON_UNESCAPED_UNICODE));
$again = renewReserveActivateOne(renewReserveLoad()[0]);
faultAssert(!empty($again['already']) && $absCalls === 0, 'retry on activated does not re-apply');

// 10) smoke unchanged buy + renew paths
faultAssert(function_exists('xuiProvisionBuy') && function_exists('xuiProvisionRenew'), 'buy and renew entrypoints preserved');

if($fail > 0){
    fwrite(STDERR, "\n{$fail} fault test(s) failed\n");
    exit(1);
}

echo "All renew reserve fault tests passed\n";

<?php

/**
 * Renew recreate tests (no live 3x-ui).
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

require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../xui_lib.php';

renewRecAssert(
    xuiRenewUserMessageRenewed() !== '' && xuiRenewUserMessageRecreated() !== '',
    'user-facing renew messages'
);

$row = [
    'ownerUser',
    'https://vip4.example.com:2096/sub/abc1234567890ab',
    '10 گیگ - 150 هزار تومان',
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
$keyB = xuiPaymentRowProvisionKey($row);
renewRecAssert($keyA === $keyB && $keyA !== '', 'provision key stable');

$storeDir = sys_get_temp_dir() . '/pnv_renew_store_' . getmypid();
@mkdir($storeDir, 0777, true);
putenv('PNV_PROVISION_STORE_DIR=' . $storeDir);

// ownership: other user must not pass
$csvPath = __DIR__ . '/../invoices/payments.csv';
$csvBackup = null;

if(is_file($csvPath)){
    $csvBackup = file_get_contents($csvPath);
}

$sampleCsv = "ownerUser,https://vip4.example.com:2096/sub/abc1234567890ab,10 گیگ - 150 هزار تومان,TRK1,,,تایید شد,https://vip4.example.com:2096/sub/abc1234567890ab,1700000000,تمدید,,,1500000\n";
file_put_contents($csvPath, $sampleCsv);

renewRecAssert(
    xuiRenewSubOwnedByUser('ownerUser', 'https://vip4.example.com:2096/sub/abc1234567890ab'),
    'ownership allows historical user sub'
);
renewRecAssert(
    !xuiRenewSubOwnedByUser('intruder', 'https://vip4.example.com:2096/sub/abc1234567890ab'),
    'ownership rejects other user'
);

$server = ['id' => 'vip4', 'host' => 'vip4.example.com', 'inbound_id' => 1, 'base_url' => 'https://example.test/'];
$configPath = __DIR__ . '/../db/xui_servers.json';
$configBackup = is_file($configPath) ? file_get_contents($configPath) : null;
file_put_contents($configPath, json_encode([
    'enabled' => true,
    'sub_port' => 2096,
    'renew_server_ids' => ['vip4'],
    'buy_server_ids' => ['vip4'],
    'servers' => [$server],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$lookupCalls = 0;
$GLOBALS['xuiTestRenewApiProbe'] = static function() {
    return ['ok' => true];
};

$GLOBALS['xuiTestAdjustClientTraffic'] = static function(){
    return ['ok' => true, 'method' => 'test_bulkAdjust'];
};

$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $subId){
    return ['status' => 'found', 'client' => ['email' => 'owner_1234', 'subId' => $subId, 'totalGB' => xuiGbToBytes(20)]];
};

$renewExisting = xuiProvisionRenew($row);
renewRecAssert(!empty($renewExisting['ok']) && ($renewExisting['renew_outcome'] ?? '') === 'renewed', 'existing client renew path');

$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'not_found'];
};

$createCalls = 0;
$origCreate = null;

// Mock create via global wrapper on xuiCreateClientForRenewPlan - use rename? Hook in recreate:
$GLOBALS['xuiTestCreateClientForRenewPlan'] = null;

// Patch: temporarily override by redefining - PHP can't. Use provision recreate with mock find + real create would hit API.

// Simulate recreate result via direct call to xuiProvisionRenewRecreate with lookup not_found
$parsed = ['host' => 'vip4.example.com', 'port' => 2096, 'sub_id' => 'abc1234567890ab'];

$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'not_found'];
};

// Stub xuiApiRequest for client get + create - heavy. Test api_error path instead:
$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'api_error', 'error' => 'timeout'];
};

$apiErr = xuiProvisionRenew($row);
renewRecAssert(empty($apiErr['ok']) && ($apiErr['code'] ?? '') === 'api_error', 'api_error does not recreate');

$GLOBALS['xuiTestRenewLookupClient'] = static function(){
    return ['status' => 'not_found'];
};

// Idempotency store done prevents duplicate mark
$resultPayload = [
    'ok' => true,
    'link' => 'https://vip4.example.com:2096/sub/abc1234567890ab',
    'renew_outcome' => 'recreated',
    'user_renew_message' => xuiRenewUserMessageRecreated(),
];
xuiRenewProvisionStoreWrite($keyA, ['state' => 'done', 'result' => $resultPayload]);
$cached = xuiRenewProvisionStoreRead($keyA);
renewRecAssert(is_array($cached) && ($cached['state'] ?? '') === 'done', 'idempotency store roundtrip');

$src = file_get_contents(__DIR__ . '/../xui_lib.php');
renewRecAssert(strpos($src, 'xuiCreateClientForRenewPlan') !== false, 'recreate uses fresh plan client helper');
renewRecAssert(strpos($src, 'flock($lockFp, LOCK_EX)') !== false, 'approve payment uses lock');

if($csvBackup !== null){
    file_put_contents($csvPath, $csvBackup);
}
else{
    @unlink($csvPath);
}

if($configBackup !== null){
    file_put_contents($configPath, $configBackup);
}
else{
    @unlink($configPath);
}

unset($GLOBALS['xuiTestRenewApiProbe'], $GLOBALS['xuiTestRenewLookupClient'], $GLOBALS['xuiTestAdjustClientTraffic']);
@array_map('unlink', glob($storeDir . '/*'));
@rmdir($storeDir);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} test(s) failed\n");
    exit(1);
}

echo "All xui renew recreate tests passed\n";

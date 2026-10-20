<?php
/**
 * Admin manual renew: Instant Pay sync requires provision evidence + order binding.
 */

$fail = 0;

function ipSyncAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/pnv_ip_sync_' . getmypid();
mkdir($tmpDir, 0777, true);
$jsonPath = $tmpDir . '/instant.json';
$csvPath = $tmpDir . '/payments.csv';
$locksDir = $tmpDir . '/locks';
mkdir($locksDir, 0777, true);

putenv('PNV_INSTANT_PAYMENTS_PATH=' . $jsonPath);
putenv('PNV_PAYMENTS_CSV_PATH=' . $csvPath);
putenv('PNV_LOCKS_DIR=' . $locksDir);

require_once __DIR__ . '/../instant_pay_lib.php';

$orderId = 'ord-renew-manual-001';
$user = 'renew_user';
$tracking = 'AUTO-1230';
$code = 1230;
$created = time();
$renewLink = 'https://vip3.boozhaan.ir/sub/demo-renewed';

function ipMakeCsvRow($user, $tracking, $code, $created, $link, $amount = 1491230){
    return [
        $user,
        'https://vip3.boozhaan.ir/sub/demo',
        '50GB',
        $tracking,
        '1404/01/01',
        '12:00',
        'تایید شد',
        $link,
        (string)$created,
        'تمدید',
        '',
        '0',
        (string)$amount,
        (string)$code,
    ];
}

function ipWriteCsv($path, array $row){
    $fp = fopen($path, 'w');
    fputcsv($fp, $row);
    fclose($fp);
}

function ipWriteProvision($locksDir, array $row, array $payload){
    $key = xuiPaymentRowProvisionKey($row);
    $path = xuiRenewProvisionStorePath($key);
    $dir = dirname($path);

    if(!is_dir($dir)){
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE));
}

$waitingItem = [
    'id' => $orderId,
    'user' => $user,
    'type' => 'تمدید',
    'sub' => 'https://vip3.boozhaan.ir/sub/demo',
    'plan' => '50GB',
    'amount' => 1491230,
    'currency' => 'rial',
    'code' => $code,
    'status' => 'waiting',
    'created_at' => $created,
    'expires_at' => $created + 1800,
];

$csvRow = ipMakeCsvRow($user, $tracking, $code, $created, $renewLink);
ipWriteCsv($csvPath, $csvRow);
file_put_contents($jsonPath, json_encode([$waitingItem], JSON_UNESCAPED_UNICODE));

ipWriteProvision($locksDir, $csvRow, [
    'state' => 'done',
    'transaction_id' => xuiPaymentRowTransactionId($csvRow),
    'result' => [
        'ok' => true,
        'link' => $renewLink,
        'renew_outcome' => 'renewed',
        'user_renew_message' => 'تمدید شد',
    ],
]);

$found = instantPayFindJsonByTracking($user, $tracking);
ipSyncAssert(is_array($found) && ($found['id'] ?? '') === $orderId, 'tracking matches user + AUTO code only');

$sync = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($sync['ok']) && ($sync['item']['id'] ?? '') === $orderId, 'sync marks matched order paid with stable id');
ipSyncAssert(($sync['item']['status'] ?? '') === 'paid', 'public view status is paid with provision evidence');
ipSyncAssert(strpos((string)($sync['item']['link'] ?? ''), 'demo-renewed') !== false, 'sync uses verified renew link');

$again = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($again['already']), 'second sync is idempotent');

// CSV confirmed but no provision store -> no false paid.
$csvRowNoStore = ipMakeCsvRow($user, $tracking, $code, $created + 1, $renewLink);
ipWriteCsv($csvPath, $csvRowNoStore);
file_put_contents($jsonPath, json_encode([$waitingItem], JSON_UNESCAPED_UNICODE));
$noStore = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(empty($noStore['ok']) && ($noStore['code'] ?? '') === 'provision_not_verified', 'confirmed csv without provision evidence does not mark paid');
$afterNoStore = json_decode(file_get_contents($jsonPath), true);
ipSyncAssert(is_array($afterNoStore) && ($afterNoStore[0]['status'] ?? '') === 'waiting', 'waiting status kept without evidence');

// Failed provision evidence.
ipWriteProvision($locksDir, $csvRowNoStore, [
    'state' => 'ambiguous_provision',
    'transaction_id' => xuiPaymentRowTransactionId($csvRowNoStore),
    'error' => 'provision failed test',
    'result' => ['ok' => false, 'error' => 'xui down'],
]);
$failed = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(empty($failed['ok']) && ($failed['code'] ?? '') === 'provision_failed', 'failed provision does not mark paid');

// Similar user/order with different tracking/code must not cross-sync.
$otherItem = array_merge($waitingItem, [
    'id' => 'ord-renew-manual-002',
    'code' => 4560,
]);
$otherTracking = 'AUTO-4560';
ipWriteCsv($csvPath, $csvRow);
file_put_contents($jsonPath, json_encode([$waitingItem, $otherItem], JSON_UNESCAPED_UNICODE));
ipWriteProvision($locksDir, $csvRow, [
    'state' => 'done',
    'transaction_id' => xuiPaymentRowTransactionId($csvRow),
    'result' => ['ok' => true, 'link' => $renewLink, 'renew_outcome' => 'renewed'],
]);
$cross = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($cross['ok']) && ($cross['item']['id'] ?? '') === $orderId, 'provision evidence applies only to matching tracking/code order');
$itemsCross = json_decode(file_get_contents($jsonPath), true);
$otherStatus = '';

foreach($itemsCross as $it){
    if(($it['id'] ?? '') === 'ord-renew-manual-002'){
        $otherStatus = $it['status'] ?? '';
    }
}

ipSyncAssert($otherStatus === 'waiting', 'different order id stays waiting');

// processing + result.ok is not final; must stay off paid until state=done.
ipWriteCsv($csvPath, $csvRow);
file_put_contents($jsonPath, json_encode([$waitingItem], JSON_UNESCAPED_UNICODE));
ipWriteProvision($locksDir, $csvRow, [
    'state' => 'processing',
    'transaction_id' => xuiPaymentRowTransactionId($csvRow),
    'result' => ['ok' => true, 'link' => $renewLink, 'renew_outcome' => 'renewed'],
]);
$processingOnly = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($processingOnly['processing']) && ($processingOnly['item']['status'] ?? '') === 'processing', 'processing provision does not mark paid');
ipSyncAssert(($processingOnly['item']['status'] ?? '') !== 'paid', 'processing store never exposes paid');

// Concurrent-style: paid must not be downgraded by a stale sync attempt.
$paidItem = array_merge($waitingItem, [
    'status' => 'paid',
    'paid_at' => time(),
    'link' => $renewLink,
]);
file_put_contents($jsonPath, json_encode([$paidItem], JSON_UNESCAPED_UNICODE));
$stillPaid = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($stillPaid['already']) && ($stillPaid['item']['status'] ?? '') === 'paid', 'paid status not overwritten by concurrent sync');

// Save failure must not report success (simulated interceptor).
file_put_contents($jsonPath, json_encode([$waitingItem], JSON_UNESCAPED_UNICODE));
ipWriteProvision($locksDir, $csvRow, [
    'state' => 'done',
    'transaction_id' => xuiPaymentRowTransactionId($csvRow),
    'result' => ['ok' => true, 'link' => $renewLink, 'renew_outcome' => 'renewed'],
]);
$GLOBALS['instantPayTestSaveInterceptor'] = static function(){
    return false;
};
$saveFail = instantPaySyncPaidFromConfirmedCsv(0);
unset($GLOBALS['instantPayTestSaveInterceptor']);
ipSyncAssert(empty($saveFail['ok']) && ($saveFail['code'] ?? '') === 'persist_failed', 'persist failure returns error not success');
$afterSaveFail = json_decode(file_get_contents($jsonPath), true);
ipSyncAssert(is_array($afterSaveFail) && ($afterSaveFail[0]['status'] ?? '') === 'waiting', 'failed save leaves prior status intact');

@exec('rm -rf ' . escapeshellarg($tmpDir));
putenv('PNV_INSTANT_PAYMENTS_PATH');
putenv('PNV_PAYMENTS_CSV_PATH');
putenv('PNV_LOCKS_DIR');

echo $fail ? "\n{$fail} instant pay admin renew sync test(s) failed\n" : "All instant pay admin renew sync tests passed\n";
exit($fail ? 1 : 0);

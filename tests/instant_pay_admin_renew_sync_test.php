<?php
/**
 * Admin manual renew approval must sync waiting Instant Pay orders (user + AUTO tracking).
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

putenv('PNV_INSTANT_PAYMENTS_PATH=' . $jsonPath);
putenv('PNV_PAYMENTS_CSV_PATH=' . $csvPath);

require_once __DIR__ . '/../instant_pay_lib.php';

$orderId = 'ord-renew-manual-001';
$user = 'renew_user';
$tracking = 'AUTO-1230';
$code = 1230;

$waitingItem = [
    'id' => $orderId,
    'user' => $user,
    'type' => 'تمدید',
    'sub' => 'https://example.com/sub/demo',
    'plan' => '50GB',
    'amount' => 1491230,
    'currency' => 'rial',
    'code' => $code,
    'status' => 'waiting',
    'created_at' => time(),
    'expires_at' => time() + 1800,
];

file_put_contents($jsonPath, json_encode([$waitingItem], JSON_UNESCAPED_UNICODE));

$csvRow = [
    $user,
    'https://example.com/sub/demo',
    '50GB',
    $tracking,
    '1404/01/01',
    '12:00',
    'تایید شد',
    'https://example.com/sub/demo-renewed',
    (string)time(),
    'تمدید',
    '',
    '0',
    '1491230',
    (string)$code,
];

$fp = fopen($csvPath, 'w');
fputcsv($fp, $csvRow);
fclose($fp);

$found = instantPayFindJsonByTracking($user, $tracking);
ipSyncAssert(is_array($found) && ($found['id'] ?? '') === $orderId, 'tracking matches user + AUTO code only');

$sync = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($sync['ok']) && !empty($sync['item']['id']) && ($sync['item']['id'] ?? '') === $orderId, 'sync marks matched order paid with stable id');
ipSyncAssert(($sync['item']['status'] ?? '') === 'paid', 'public view status is paid after admin csv confirm');
ipSyncAssert(strpos((string)($sync['item']['link'] ?? ''), 'renewed') !== false, 'sync copies renew link from csv');

$items = json_decode(file_get_contents($jsonPath), true);
ipSyncAssert(is_array($items) && ($items[0]['status'] ?? '') === 'paid', 'json persisted paid status');

$again = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($again['already']), 'second sync is idempotent');

$wrongUserRow = $csvRow;
$wrongUserRow[0] = 'other_user';
$fp = fopen($csvPath, 'w');
fputcsv($fp, $wrongUserRow);
fclose($fp);

file_put_contents($jsonPath, json_encode([$waitingItem], JSON_UNESCAPED_UNICODE));
$noMatch = instantPaySyncPaidFromConfirmedCsv(0);
ipSyncAssert(!empty($noMatch['skipped']), 'wrong csv user does not touch unrelated waiting order');

@unlink($jsonPath);
@unlink($csvPath);
@rmdir($tmpDir);
putenv('PNV_INSTANT_PAYMENTS_PATH');
putenv('PNV_PAYMENTS_CSV_PATH');

echo $fail ? "\n{$fail} instant pay admin renew sync test(s) failed\n" : "All instant pay admin renew sync tests passed\n";
exit($fail ? 1 : 0);

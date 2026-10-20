<?php
/**
 * Admin list buckets: five tabs, cancelled ≠ rejected, history preserved (buy + renew).
 */

$fail = 0;

function tabsAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/pnv_admin_tabs_' . getmypid();
mkdir($tmpDir, 0777, true);
$jsonPath = $tmpDir . '/instant.json';
$csvPath = $tmpDir . '/payments.csv';
$locksDir = $tmpDir . '/locks';
mkdir($locksDir, 0777, true);

putenv('PNV_INSTANT_PAYMENTS_PATH=' . $jsonPath);
putenv('PNV_PAYMENTS_CSV_PATH=' . $csvPath);
putenv('PNV_LOCKS_DIR=' . $locksDir);

require_once __DIR__ . '/../instant_pay_lib.php';

function tabsCsvRow(array $overrides = []){
    $row = [
        'user1',
        'cfg_a',
        '50GB - 149',
        'TRACK-001',
        '1404/01/01',
        '12:00',
        'درحال بررسی',
        '',
        (string)time(),
        'خرید',
        '',
        '0',
        '1490000',
        '1001',
    ];

    foreach($overrides as $k => $v){
        $row[$k] = $v;
    }

    return $row;
}

function tabsWriteJson(array $items){
    global $jsonPath;
    file_put_contents($jsonPath, json_encode(array_values($items), JSON_UNESCAPED_UNICODE));
}

$keys = instantPayAdminListStatusKeys();
tabsAssert(count($keys) === 5 && $keys[4] === 'cancelled', 'five status keys include cancelled');

$cancelledByReason = tabsCsvRow([
    6 => 'رد شد',
    7 => 'لغو توسط کاربر',
    3 => 'AUTO-9001',
    8 => '1700000001',
]);
tabsAssert(instantPayAdminRowListBucket($cancelledByReason) === 'cancelled', 'CSV reject with لغو reason → cancelled');
tabsAssert(instantPayAdminRowBucket($cancelledByReason) === 'cancelled', 'instantPayAdminRowBucket marks cancelled');

$rejectedRow = tabsCsvRow([
    6 => 'رد شد',
    7 => 'اطلاعات پرداخت اشتباه است',
    8 => '1700000002',
]);
tabsAssert(instantPayAdminRowListBucket($rejectedRow) === 'rejected', 'CSV reject without لغو → rejected');

$approvedRow = tabsCsvRow([
    6 => 'تایید شد',
    7 => 'https://example/sub/x',
    8 => '1700000003',
]);
tabsAssert(instantPayAdminRowListBucket($approvedRow) === 'approved', 'approved CSV → approved tab');

tabsAssert(instantPayAdminRowVisibleForTabs($approvedRow), 'old approved row stays visible');

$createdAuto = 1700000100;
$autoPending = tabsCsvRow([
    3 => 'AUTO-7777',
    6 => '',
    8 => (string)$createdAuto,
    13 => '7777',
]);
tabsWriteJson([[
    'id' => 'ord-auto-1',
    'user' => 'user1',
    'type' => 'خرید',
    'subname' => 'cfg_a',
    'plan' => '50GB',
    'amount' => 1490000,
    'code' => 7777,
    'status' => 'waiting',
    'created_at' => $createdAuto,
    'expires_at' => time() + 3600,
]]);
tabsAssert(instantPayAdminRowListBucket($autoPending) === 'pending', 'AUTO waiting + not expired → pending');

tabsWriteJson([[
    'id' => 'ord-auto-2',
    'user' => 'user1',
    'type' => 'خرید',
    'subname' => 'cfg_a',
    'plan' => '50GB',
    'amount' => 1490000,
    'code' => 7777,
    'status' => 'cancelled',
    'message' => 'لغو شده',
    'created_at' => $createdAuto,
    'expires_at' => time() + 3600,
]]);
$autoCancelledCsv = tabsCsvRow([
    3 => 'AUTO-7777',
    6 => 'رد شد',
    7 => 'لغو شده',
    8 => (string)$createdAuto,
    13 => '7777',
]);
tabsAssert(instantPayAdminRowListBucket($autoCancelledCsv) === 'cancelled', 'JSON cancelled binds to CSV row → cancelled not rejected');

$payments = [
    tabsCsvRow([9 => 'خرید', 8 => '1600000001']),
    tabsCsvRow([9 => 'تمدید', 1 => 'https://vip3.example/sub/abc', 8 => '1600000002']),
];

$orphanCancelled = [[
    'id' => 'ord-orphan-buy',
    'user' => 'orphan_buy',
    'type' => 'خرید',
    'subname' => 'cfg_b',
    'plan' => '30GB',
    'amount' => 990000,
    'code' => 5555,
    'status' => 'cancelled',
    'message' => 'لغو شده',
    'created_at' => 1600000999,
]];
tabsWriteJson($orphanCancelled);

$buyArchive = instantPayAdminCancelledHistory($payments, 'خرید');
tabsAssert(count($buyArchive) === 1 && !empty($buyArchive[0]['synthetic']), 'cancelled buy history includes JSON-only row');

$renewArchive = instantPayAdminCancelledHistory($payments, 'تمدید');
tabsAssert(count($renewArchive) === 0, 'renew archive excludes buy-only JSON');

$renewOrphan = [[
    'id' => 'ord-orphan-renew',
    'user' => 'orphan_renew',
    'type' => 'تمدید',
    'sub' => 'https://vip3.example/sub/renew',
    'plan' => '50GB',
    'amount' => 1490000,
    'code' => 6666,
    'status' => 'cancelled',
    'message' => 'لغو شده',
    'created_at' => 1600000888,
]];
tabsWriteJson($renewOrphan);
$renewArchive2 = instantPayAdminCancelledHistory($payments, 'تمدید');
tabsAssert(count($renewArchive2) === 1, 'cancelled renew history includes JSON-only renew');

tabsAssert(!instantPayAdminPaymentRowIsActionable($cancelledByReason), 'cancelled rows are not actionable');

exit($fail > 0 ? 1 : 0);

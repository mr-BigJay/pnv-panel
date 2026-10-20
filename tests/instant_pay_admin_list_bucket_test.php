<?php

require_once __DIR__ . '/../instant_pay_lib.php';

$failures = 0;

function testAssert($label, $cond){
    global $failures;

    if($cond){
        echo "PASS {$label}\n";
        return;
    }

    echo "FAIL {$label}\n";
    $failures++;
}

function testCsvRow(array $overrides = []){
    $row = array_fill(0, 13, '');

    $row[0] = 'bucket_user';
    $row[1] = 'cfg';
    $row[2] = 'plan';
    $row[3] = 'AUTO-0001';
    $row[6] = 'درحال بررسی';
    $row[8] = (string)time();
    $row[9] = 'خرید';

    foreach($overrides as $i => $value){
        $row[$i] = $value;
    }

    return $row;
}

function testFilterRowsByStatus(array $rows, $status){
    return array_values(array_filter($rows, static function($row) use ($status){
        return instantPayAdminRowListBucket($row) === $status;
    }));
}

$instantPath = instantPayPath();
$instantBackup = $instantPath . '.bucket_test_bak';
$hadInstant = is_file($instantPath);

if($hadInstant){
    copy($instantPath, $instantBackup);
}

register_shutdown_function(static function() use ($instantPath, $instantBackup, $hadInstant){
    if($hadInstant && is_file($instantBackup)){
        copy($instantBackup, $instantPath);
        @unlink($instantBackup);
    }
    elseif(!$hadInstant && is_file($instantPath)){
        @unlink($instantPath);
    }
});

$now = time();

// A) CSV approved beats JSON expired
instantPaySave([
    [
        'user' => 'bucket_user',
        'code' => 1,
        'status' => 'expired',
        'expires_at' => $now - 3600,
    ],
]);
$rowA = testCsvRow([
    6 => 'تایید شد',
]);
testAssert('A approved over json expired', instantPayAdminRowListBucket($rowA) === 'approved');

// B) AUTO waiting, expires_at > now => pending
instantPaySave([
    [
        'user' => 'bucket_user',
        'code' => 2,
        'status' => 'waiting',
        'expires_at' => $now + 3600,
    ],
]);
$rowB = testCsvRow([
    3 => 'AUTO-0002',
    6 => 'درحال بررسی',
    8 => (string)$now,
]);
testAssert('B waiting future expiry => pending', instantPayAdminRowListBucket($rowB) === 'pending');

// C) AUTO expired (expires_at < now) => expired
instantPaySave([
    [
        'user' => 'bucket_user',
        'code' => 3,
        'status' => 'waiting',
        'expires_at' => $now - 120,
    ],
]);
$rowC = testCsvRow([
    3 => 'AUTO-0003',
    6 => 'درحال بررسی',
    8 => (string)($now - 7200),
]);
testAssert('C expired expiry => expired', instantPayAdminRowListBucket($rowC) === 'expired');

// D) rejected vs cancelled
$rowD = testCsvRow([
    3 => 'MANUAL-1',
    6 => 'رد شد',
]);
testAssert('D csv rejected => rejected', instantPayAdminRowListBucket($rowD) === 'rejected');

instantPaySave([
    [
        'user' => 'bucket_user',
        'code' => 4,
        'status' => 'cancelled',
        'created_at' => $now,
        'expires_at' => $now + 600,
    ],
]);
$rowD2 = testCsvRow([
    3 => 'AUTO-0004',
    6 => 'درحال بررسی',
    8 => (string)$now,
]);
testAssert('D json cancelled => cancelled', instantPayAdminRowListBucket($rowD2) === 'cancelled');

// E) page filter simulation — buckets are disjoint per status
$mix = [
    testCsvRow([3 => 'AUTO-0101', 6 => 'تایید شد']),
    testCsvRow([3 => 'AUTO-0102', 6 => 'درحال بررسی', 8 => (string)$now]),
    testCsvRow([3 => 'AUTO-0103', 6 => 'رد شد']),
];

instantPaySave([
    [
        'user' => 'bucket_user',
        'code' => 102,
        'status' => 'waiting',
        'expires_at' => $now + 1800,
    ],
    [
        'user' => 'bucket_user',
        'code' => 104,
        'status' => 'expired',
        'expires_at' => $now - 60,
    ],
]);

$mix[] = testCsvRow([
    3 => 'AUTO-0104',
    6 => 'درحال بررسی',
    8 => (string)($now - 5000),
]);

foreach(['approved', 'pending', 'expired', 'rejected'] as $statusKey){
    $filtered = testFilterRowsByStatus($mix, $statusKey);

    foreach($filtered as $row){
        testAssert("E {$statusKey} only contains matching bucket", instantPayAdminRowListBucket($row) === $statusKey);
    }
}

$approvedOnly = testFilterRowsByStatus($mix, 'approved');
$pendingOnly = testFilterRowsByStatus($mix, 'pending');
$expiredOnly = testFilterRowsByStatus($mix, 'expired');
$rejectedOnly = testFilterRowsByStatus($mix, 'rejected');

testAssert('E approved count >= 1', count($approvedOnly) >= 1);
testAssert('E pending count >= 1', count($pendingOnly) >= 1);
testAssert('E expired count >= 1', count($expiredOnly) >= 1);
testAssert('E rejected count >= 1', count($rejectedOnly) >= 1);

$overlap = array_intersect(
    array_map('md5', array_map('serialize', $approvedOnly)),
    array_map('md5', array_map('serialize', $pendingOnly))
);
testAssert('E no approved/pending overlap', count($overlap) === 0);

echo $failures === 0 ? "ALL PASS\n" : "FAILURES: {$failures}\n";
exit($failures === 0 ? 0 : 1);

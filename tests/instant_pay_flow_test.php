<?php

/**
 * تست جریان خرید/تمدید خودکار (مچ مبلغ + ingest)
 * اجرا: php tests/instant_pay_flow_test.php
 */

require_once dirname(__DIR__) . '/bale_lib.php';
require_once dirname(__DIR__) . '/instant_pay_lib.php';

$fail = 0;

function assertTrue($cond, $msg){
    global $fail;
    if($cond){
        echo "OK  $msg\n";
    }
    else{
        echo "FAIL $msg\n";
        $fail++;
    }
}

function backupFile($path){
    if(!is_file($path)){
        return null;
    }

    $bak = $path . '.bak.' . getmypid();
    copy($path, $bak);

    return $bak;
}

function restoreFile($path, $bak){
    if($bak === null){
        if(is_file($path)){
            unlink($path);
        }
        return;
    }

    copy($bak, $path);
    unlink($bak);
}

$instantBak = backupFile(dirname(__DIR__) . '/db/instant_payments.json');
$csvBak = backupFile(dirname(__DIR__) . '/invoices/payments.csv');

$now = time();
$buyAmount = 4992040;
$renewAmount = 2746160;
$buyCode = 2040;
$renewCode = 6160;

$buyRow = [
    'testuser',
    'myconfig1',
    '20 گیگ',
    'AUTO-' . $buyCode,
    '',
    '',
    'درحال بررسی',
    '',
    $now,
    'خرید',
    '',
    0,
    $buyAmount,
    $buyCode,
];

$renewRow = [
    'testuser2',
    'https://vip.boozhaan.ir/sub/abc12345',
    '50 گیگ',
    'AUTO-' . $renewCode,
    '',
    '',
    'درحال بررسی',
    '',
    $now,
    'تمدید',
    '',
    0,
    $renewAmount,
    $renewCode,
];

xuiSavePayments([$buyRow, $renewRow]);

instantPaySave([
    [
        'id' => 'flow-buy-1',
        'user' => 'testuser',
        'type' => 'خرید',
        'subname' => 'myconfig1',
        'sub' => '',
        'plan' => '20 گیگ',
        'amount' => $buyAmount,
        'currency' => 'rial',
        'code' => $buyCode,
        'status' => 'waiting',
        'created_at' => $now,
        'expires_at' => $now + 1800,
        'csv_index' => 0,
    ],
    [
        'id' => 'flow-renew-1',
        'user' => 'testuser2',
        'type' => 'تمدید',
        'subname' => '',
        'sub' => 'https://vip.boozhaan.ir/sub/abc12345',
        'plan' => '50 گیگ',
        'amount' => $renewAmount,
        'currency' => 'rial',
        'code' => $renewCode,
        'status' => 'waiting',
        'created_at' => $now,
        'expires_at' => $now + 1800,
        'csv_index' => 1,
    ],
]);

$buyDeposit = "پست بانک\nواريز به کارت: 6156\n+4,992,040\n1405/06/12\n16:36\nمانده: 13,599,379 ريال";
$renewDeposit = "پست بانک\nواريز به کارت: 6156\n+2,746,160\n1405/06/12\n14:34\nمانده: 3,614,079 ريال";

$buyResult = instantPayHandleDepositText($buyDeposit, ['date' => '1405/06/12', 'time' => '16:36']);
$renewResult = instantPayHandleDepositText($renewDeposit, ['date' => '1405/06/12', 'time' => '14:34']);

assertTrue(empty($buyResult['ignored']), 'buy deposit is not silently ignored');
assertTrue(empty($renewResult['ignored']), 'renew deposit is not silently ignored');
assertTrue(intval($buyResult['matched_amount'] ?? 0) === $buyAmount, 'buy matched amount');
assertTrue(intval($renewResult['matched_amount'] ?? 0) === $renewAmount, 'renew matched amount');

// CSV fallback when JSON row has stale csv_index
instantPaySave([
    [
        'id' => 'flow-buy-stale',
        'user' => 'testuser',
        'type' => 'خرید',
        'subname' => 'myconfig1',
        'sub' => '',
        'plan' => '20 گیگ',
        'amount' => $buyAmount,
        'currency' => 'rial',
        'code' => $buyCode,
        'status' => 'waiting',
        'created_at' => $now,
        'expires_at' => $now + 1800,
        'csv_index' => 99,
        'csv_purged' => false,
    ],
]);

$fallback = instantPayTryDepositAmount($buyAmount, $buyDeposit, ['date' => '1405/06/12', 'time' => '16:36']);
assertTrue(is_array($fallback), 'csv/json fallback returns result');
assertTrue(intval($fallback['matched_amount'] ?? 0) === $buyAmount, 'fallback keeps matched amount');
assertTrue(!empty($fallback['ok']) || !empty($fallback['error']), 'fallback reports outcome');

// سفارش لغوشده با CSV پاک‌شده — در بازه grace باید مچ شود
$cancelledAmount = 1498650;
$cancelledCode = 8650;
instantPaySave([
    [
        'id' => 'flow-cancelled-1',
        'user' => 'canceluser',
        'type' => 'خرید',
        'subname' => 'cfg8650',
        'sub' => '',
        'plan' => '20 گیگ',
        'amount' => $cancelledAmount,
        'currency' => 'rial',
        'code' => $cancelledCode,
        'status' => 'cancelled',
        'created_at' => $now,
        'expires_at' => $now + 1800,
        'cancelled_at' => $now - 120,
        'csv_index' => -1,
        'csv_purged' => true,
        'message' => 'لغو به‌خاطر مبلغ جدید',
    ],
]);
xuiSavePayments([]);

$cancelledItem = instantPayLoad()[0] ?? [];
assertTrue(instantPayItemMatchable($cancelledItem, $now), 'cancelled order within grace is matchable');
assertTrue(instantPayMatchAmountExact($cancelledAmount) !== null, 'cancelled order matches exact amount');

$cancelDeposit = "پست بانک\nواريز به کارت: 6156\n+1,498,650\n1405/06/14\n1:05\nمانده: 28,976,369 ريال";
$cancelResult = instantPayHandleDepositText($cancelDeposit, ['date' => '1405/06/14', 'time' => '1:05']);
assertTrue(intval($cancelResult['matched_amount'] ?? 0) === $cancelledAmount, 'cancelled order deposit matched amount');
assertTrue(!empty($cancelResult['ok']) || !empty($cancelResult['error']), 'cancelled order deposit reports outcome');

$oldCancelled = $cancelledItem;
$oldCancelled['cancelled_at'] = $now - instantPayCancelGraceSeconds() - 60;
instantPaySave([$oldCancelled]);
assertTrue(!instantPayItemMatchable($oldCancelled, $now), 'cancelled order outside grace is not matchable');
assertTrue(instantPayMatchAmountExact($cancelledAmount) === null, 'old cancelled order does not match');

restoreFile(dirname(__DIR__) . '/db/instant_payments.json', $instantBak);
restoreFile(dirname(__DIR__) . '/invoices/payments.csv', $csvBak);

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

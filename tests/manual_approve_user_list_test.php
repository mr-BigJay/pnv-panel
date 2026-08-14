<?php

require_once __DIR__ . '/../subscription_lib.php';

$failures = 0;

function assertTrue($condition, $message){
    global $failures;

    if(!$condition){
        echo "FAIL: {$message}\n";
        $failures++;
        return;
    }

    echo "OK: {$message}\n";
}

$payments = [
    ['Mehran', 'Mehran1', '4 گیگ - 1 تومان', '613419554673', '1405/02/24', '19:56', 'در حال بررسی', '', '', ''],
    ['mohamad-amp', 'Mohammad1', '2 گیگ - 500 تومان', '177886953411', '1405/02/25', '21:56', 'تایید شد', 'https://vip3.boozhaan.ir:2096/sub/wy7g7y9qfktztfcd', '', 'خرید'],
];

assertTrue(
    pnvFindPaymentRowIndex($payments, 'Mehran', '613419554673', 99) === 0,
    'find pending buy by username + tracking'
);

assertTrue(
    pnvFindPaymentRowIndex($payments, 'mehran', '613419554673', null) === 0,
    'case-insensitive username lookup'
);

assertTrue(
    pnvFindPaymentRowIndex($payments, 'Mehran', '613419554673', 1) === 0,
    'tracking wins over stale fallback index'
);

$subs = pnvLoadUserActiveSubscriptions('MOHAMAD-AMP', false);
assertTrue(count($subs) >= 1, 'approved buy visible with case-insensitive session username');

$tempCsv = sys_get_temp_dir() . '/pnv_manual_approve_test_' . getmypid() . '.csv';
$rows = [
    ['TestUser', 'Cfg', '1 گیگ - 300 تومان', 'TRACK123', '1405/03/01', '10:00', 'تایید شد', 'https://vip3.boozhaan.ir:2096/sub/testlink123456', '', ''],
];

$fp = fopen($tempCsv, 'w');
foreach($rows as $row){
    fputcsv($fp, $row);
}
fclose($fp);

$originalPath = pnvPaymentsCsvPath();
$backupHandle = fopen($originalPath, 'r');
$backupContents = stream_get_contents($backupHandle);
fclose($backupHandle);

file_put_contents($originalPath, file_get_contents($tempCsv));

$emptyTypeSubs = pnvLoadUserActiveSubscriptions('testuser', false);
assertTrue(count($emptyTypeSubs) === 1, 'approved buy with empty type column appears in user list');

file_put_contents($originalPath, $backupContents);
@unlink($tempCsv);

if($failures > 0){
    fwrite(STDERR, "\n{$failures} test(s) failed.\n");
    exit(1);
}

echo "\nAll manual-approve user-list tests passed.\n";

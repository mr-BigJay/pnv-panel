<?php

/**
 * امنیت تأیید خودکار: CSV بدون JSON، dedup، mock بدون صدور
 * php tests/instant_pay_deposit_security_test.php
 */

$fail = 0;

function secAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/pnv_dep_sec_' . getmypid();
mkdir($tmpDir, 0777, true);

putenv('PNV_INSTANT_PAYMENTS_PATH=' . $tmpDir . '/instant.json');
putenv('PNV_USED_DEPOSITS_PATH=' . $tmpDir . '/used.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $tmpDir . '/payments.csv');
putenv('PNV_LOCKS_DIR=' . $tmpDir . '/locks');
mkdir(getenv('PNV_LOCKS_DIR'), 0777, true);
putenv('PNV_TEJARAT_VERIFY_CONFIG_PATH=' . $tmpDir . '/tejarat.json');
putenv('PNV_TEJARAT_VERIFY_STATE_PATH=' . $tmpDir . '/tejarat_state.json');
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON');
putenv('PNV_TEJARAT_MOCK_FORCE_ERROR');

require_once __DIR__ . '/../xui_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';
require_once __DIR__ . '/../tejarat_auto_verify_lib.php';

$card = '5859831111222233';
$amount = 1497890;
$now = time();

// CSV pending بدون ردیف JSON مرتبط → بدون تأیید
$csvRow = array_fill(0, 14, '');
$csvRow[0] = 'orphan_user';
$csvRow[2] = '20GB';
$csvRow[3] = 'AUTO-7890';
$csvRow[6] = 'درحال بررسی';
$csvRow[8] = (string)$now;
$csvRow[9] = 'خرید';
$csvRow[12] = (string)$amount;
$csvRow[13] = '7890';
$fp = fopen(getenv('PNV_PAYMENTS_CSV_PATH'), 'w');
fputcsv($fp, $csvRow);
fclose($fp);
instantPaySave([]);

$csvOnly = instantPayFindCsvMatchByAmount($amount, [
    'source' => 'tejarat',
    'bank_id' => 'tejarat',
    'destination_card' => $card,
    'transaction_id' => 'TX-CSV-1',
]);
secAssert($csvOnly === null, 'csv fallback skipped without linked instant-pay json');

$noDedup = instantPayTryDepositMatch($amount, [
    'source' => 'tejarat',
    'bank_id' => 'tejarat',
    'destination_card' => $card,
], ['apply' => true, 'dry_run' => false]);
secAssert(empty($noDedup['ok']) && ($noDedup['code'] ?? '') === 'missing_dedup_key', 'apply blocked without dedup key');

// mock + dry_run=false هنوز صدور زنده ندارد
file_put_contents(getenv('PNV_TEJARAT_VERIFY_CONFIG_PATH'), json_encode([
    'enabled' => true,
    'provider' => 'mock',
    'dry_run' => false,
    'destination_cards' => [$card],
], JSON_UNESCAPED_UNICODE));

instantPaySave([[
    'id' => 'live-block',
    'user' => 'buyer',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => $amount,
    'currency' => 'rial',
    'code' => 7890,
    'card' => $card,
    'card_bank' => 'tejarat',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 0,
]]);

putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([[
    'amount' => $amount,
    'destination_card' => $card,
    'transaction_id' => 'TJ-LIVE-BLOCK',
    'occurred_at' => $now + 2,
]]));

$res = tejaratVerifyPollOnce();
secAssert(!empty($res['dry_run']), 'mock provider forces dry_run even when config dry_run=false');
$items = instantPayLoad();
secAssert(($items[0]['status'] ?? '') === 'waiting', 'mock poll does not live-provision subscription');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

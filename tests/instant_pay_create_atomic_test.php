<?php
/**
 * Checkout create must not cancel prior waiting orders until the new CSV row persists,
 * and must not leave orphan pending CSV rows when JSON persistence fails.
 */
function checkoutReleaseDiscountOrder($id){ return true; }

$dir = sys_get_temp_dir() . '/pnv-create-atomic-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $dir . '/instant_payments.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $dir . '/payments.csv');

require_once dirname(__DIR__) . '/instant_pay_lib.php';

$failed = 0;
function assertCreateAtomic($ok, $msg){
    global $failed;
    if(!$ok){
        fwrite(STDERR, "FAIL: $msg\n");
        $failed++;
    }
}

$plans = [
    [
        'name' => '10 گیگ',
        'price' => 150,
        'days' => 30,
        'traffic_gb' => 10,
    ],
];
$planValue = pnvPlanOptionValue($plans[0]);

try{
    $now = time();
    $prior = [
        'id' => 'prior-wait',
        'user' => 'buyer',
        'type' => 'خرید',
        'code' => '1111',
        'subname' => 'oldcfg',
        'plan' => $planValue,
        'status' => 'waiting',
        'created_at' => $now - 50,
        'expires_at' => $now + 1800,
        'amount' => 1491111,
        'csv_index' => 0,
    ];
    instantPaySave([$prior]);
    $csv = [[
        'buyer', 'oldcfg', $planValue, 'AUTO-1111', '', '',
        'درحال بررسی', '', $now - 50, 'خرید', '', '', 1491111, '1111',
    ]];
    assertCreateAtomic(xuiSavePayments($csv), 'seed CSV');

    // CSV save failure before supersede: prior waiting must remain.
    $GLOBALS['xuiTestSavePaymentsInterceptor'] = static function($rows){
        if(count($rows) > 1){
            return false;
        }
        return true;
    };
    $blocked = instantPayCreate([
        'user' => 'buyer',
        'type' => 'خرید',
        'plan' => $planValue,
        'subname' => 'newcfg12',
        'sub' => '',
        'card' => '6037-0000-0000-0000',
        'plans' => $plans,
    ]);
    unset($GLOBALS['xuiTestSavePaymentsInterceptor']);
    assertCreateAtomic(empty($blocked['ok']), 'create fails when CSV cannot persist second row');
    $afterBlock = instantPayLoad();
    assertCreateAtomic(count($afterBlock) === 1 && ($afterBlock[0]['status'] ?? '') === 'waiting',
        'prior JSON waiting preserved after CSV failure');
    $csvAfterBlock = xuiLoadPayments();
    assertCreateAtomic(count($csvAfterBlock) === 1 && instantPayCsvRowPending($csvAfterBlock[0]),
        'prior CSV still pending after failed create');

    // JSON save failure after supersede: old waiting must be restored and
    // only the new CSV row rejected; an unsuccessful new order cannot cancel prior.
    $GLOBALS['instantPayTestSaveInterceptor'] = static function($items){
        foreach($items as $item){
            if(($item['subname'] ?? '') === 'newcfg34' && ($item['status'] ?? '') === 'waiting'){
                return false;
            }
        }
        return true;
    };
    $jsonFail = instantPayCreate([
        'user' => 'buyer',
        'type' => 'خرید',
        'plan' => $planValue,
        'subname' => 'newcfg34',
        'sub' => '',
        'card' => '6037-0000-0000-0000',
        'plans' => $plans,
    ]);
    unset($GLOBALS['instantPayTestSaveInterceptor']);
    assertCreateAtomic(empty($jsonFail['ok']) && ($jsonFail['code'] ?? '') === 'persist_failed',
        'persist_failed when JSON save fails');
    $rows = xuiLoadPayments();
    assertCreateAtomic(count($rows) === 2, 'CSV retains both rows');
    assertCreateAtomic(instantPayCsvRowPending($rows[0]), 'prior CSV restored to pending after failed replacement');
    assertCreateAtomic(!instantPayCsvRowPending($rows[1]), 'orphan new CSV rejected after JSON failure');
    $jsonRows = instantPayLoad();
    assertCreateAtomic(count($jsonRows) === 1 && ($jsonRows[0]['status'] ?? '') === 'waiting',
        'prior JSON waiting restored when replacement failed');

    echo $failed ? "$failed failed\n" : "All instant pay create atomic tests passed\n";
}finally{
    unset($GLOBALS['xuiTestSavePaymentsInterceptor'], $GLOBALS['instantPayTestSaveInterceptor']);
    @unlink($dir . '/payments.csv');
    @unlink($dir . '/instant_payments.json');
    @unlink($dir . '/locks/instant_pay_create.lock');
    @rmdir($dir . '/locks');
    @rmdir($dir);
}
exit($failed ? 1 : 0);

<?php

/**
 * Idempotency / concurrency tests (no 3x-ui network required).
 * php tests/payment_provision_idempotency_test.php
 */

$fail = 0;
$store = sys_get_temp_dir() . '/pnv_provision_test_' . getmypid() . '.json';
putenv('PNV_PROVISION_STORE_PATH=' . $store);
@unlink($store);

require_once dirname(__DIR__) . '/payment_provision_idempotency.php';

function provAssert($cond, $msg){
    global $fail;

    if($cond){
        echo "OK  {$msg}\n";
    }
    else{
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

$renewRow = [
    'JayForce',
    'https://vip4.boozhaan.ir:2096/sub/s0cwnfqm1cfv4zgj',
    '10 گیگ - 150 هزار تومان',
    'AUTO-1640',
    '',
    '',
    'درحال بررسی',
    '',
    1790870494,
    'تمدید',
    '',
    '',
    1491640,
];

$key = xuiPaymentProvisionIdKeyFromRow($renewRow);
provAssert(strpos($key, 'AUTO-1640') !== false && strpos($key, ':renew') !== false, 'key independent of csv index');
provAssert(strpos($key, '918') === false, 'key does not contain csv line index');

$intent = [
    'type' => 'تمدید',
    'payment_key' => $key,
    'server_id' => 'vip4',
    'sub_id' => 's0cwnfqm1cfv4zgj',
    'email' => 'Jasem_3936',
    'client_uuid' => 'uuid-test',
    'add_bytes' => 10737418240,
    'add_gb' => 10,
    'add_days' => 0,
    'baseline_total_bytes' => 32212254720,
    'expected_link' => 'https://vip4.boozhaan.ir:2096/sub/s0cwnfqm1cfv4zgj',
];

$GLOBALS['xuiPaymentProvisionTestIntentOverride'] = $intent;

$GLOBALS['xuiPaymentProvisionTestFetchClient'] = static function($in) use ($intent){
    static $total;

    if($total === null){
        $total = intval($intent['baseline_total_bytes']);
    }

    return [
        'server_id' => 'vip4',
        'email' => 'Jasem_3936',
        'sub_id' => 's0cwnfqm1cfv4zgj',
        'total_bytes' => $total,
    ];
};

$bulkAdjustCalls = 0;
$provisionOk = static function() use (&$bulkAdjustCalls, $intent){
    $bulkAdjustCalls++;
    $GLOBALS['xuiPaymentProvisionTestFetchClient'] = static function($in) use ($intent){
        return [
            'server_id' => 'vip4',
            'email' => 'Jasem_3936',
            'sub_id' => 's0cwnfqm1cfv4zgj',
            'total_bytes' => intval($intent['baseline_total_bytes']) + intval($intent['add_bytes']),
        ];
    };

    return [
        'ok' => true,
        'email' => 'Jasem_3936',
        'sub_id' => 's0cwnfqm1cfv4zgj',
        'server_id' => 'vip4',
        'link' => $intent['expected_link'],
        'method' => 'bulkAdjust',
    ];
};

// Two begins: second busy
$b1 = xuiPaymentProvisionBegin($key, $intent);
provAssert(($b1['state'] ?? '') === 'owned', 'begin first owned');
$b2 = xuiPaymentProvisionBegin($key, $intent);
provAssert(!empty($b2['busy']), 'begin second busy while running');
xuiPaymentProvisionAbort($key);

// Corrupt JSON
file_put_contents($store, '{not-json');
$c = xuiPaymentProvisionBegin($key, $intent);
provAssert(($c['state'] ?? '') === 'error', 'corrupt json fail-closed');
@unlink($store);

// API success → crash before commit → retry
$bulkAdjustCalls = 0;
provAssert(($b1 = xuiPaymentProvisionBegin($key, $intent))['state'] === 'owned', 'crash: begin owned');
$apiResult = $provisionOk();
provAssert($bulkAdjustCalls === 1, 'bulkAdjust = 1 on first provision');
provAssert(!empty(xuiPaymentProvisionMarkApiSucceeded($key, $apiResult, $intent)['ok']), 'mark api_succeeded');

$bulkAdjustCalls = 0;
$retryBegin = xuiPaymentProvisionBegin($key, $intent);
provAssert($bulkAdjustCalls === 0, 'bulkAdjust = 0 on retry after api_succeeded');
provAssert(($retryBegin['state'] ?? '') === 'done', 'retry begin returns done');
provAssert(!empty($retryBegin['result']['ok']), 'retry result ok');

// Reconcile: running stale, total already increased
@unlink($store);
xuiPaymentProvisionBegin($key, $intent);
xuiPaymentProvisionAbort($key);
$storeData = json_decode(file_get_contents($store), true) ?: [];
$storeData[$key] = [
    'state' => 'running',
    'started_at' => time() - 300,
    'intent' => $intent,
    'api_succeeded' => false,
];
file_put_contents($store, json_encode($storeData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$GLOBALS['xuiPaymentProvisionTestFetchClient'] = static function($in) use ($intent){
    return [
        'server_id' => 'vip4',
        'email' => 'Jasem_3936',
        'sub_id' => 's0cwnfqm1cfv4zgj',
        'total_bytes' => intval($intent['baseline_total_bytes']) + intval($intent['add_bytes']),
    ];
};

$bulkAdjustCalls = 0;
$rec = xuiPaymentProvisionExecute($renewRow, 'تمدید', $provisionOk);
provAssert($bulkAdjustCalls === 0, 'reconcile: bulkAdjust = 0');
provAssert(!empty($rec['ok']), 'reconcile execute ok');

// Commit failure → recoverable → retry without bulkAdjust
@unlink($store);
$bulkAdjustCalls = 0;
$GLOBALS['xuiPaymentProvisionTestFailCommit'] = true;
$r1 = xuiPaymentProvisionExecute($renewRow, 'تمدید', $provisionOk);
provAssert(
    empty($r1['ok']) && (!empty($r1['recoverable']) || ($r1['code'] ?? '') === 'commit_failed_marked'),
    'commit fail recoverable'
);
provAssert($bulkAdjustCalls === 1, 'commit fail: bulkAdjust once');

$bulkAdjustCalls = 0;
$r2 = xuiPaymentProvisionExecute($renewRow, 'تمدید', $provisionOk);
provAssert($bulkAdjustCalls === 0, 'after commit fail retry: bulkAdjust = 0');
provAssert(!empty($r2['ok']), 'after commit fail retry ok');

// fopen fail-closed (directory is file)
$badPath = sys_get_temp_dir() . '/pnv_provision_block_' . getmypid();
file_put_contents($badPath, 'x');
putenv('PNV_PROVISION_STORE_PATH=' . $badPath . '/nested/store.json');
$fc = xuiPaymentProvisionBegin($key, $intent);
provAssert(($fc['state'] ?? '') === 'error', 'fopen/mkdir fail-closed');
@unlink($badPath);

putenv('PNV_PROVISION_STORE_PATH=' . $store);
@unlink($store);

unset($GLOBALS['xuiPaymentProvisionTestFetchClient'], $GLOBALS['xuiPaymentProvisionTestIntentOverride']);

echo $fail === 0 ? "\nAll provision idempotency tests passed\n" : "\n{$fail} failed\n";
exit($fail === 0 ? 0 : 1);

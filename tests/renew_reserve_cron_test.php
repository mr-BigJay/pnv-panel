<?php

/**
 * Cron queue idempotency (isolated paths, mocked XUI).
 * php tests/renew_reserve_cron_test.php
 */

$fail = 0;

function cronTestAssert($name, $cond){
    global $fail;

    if($cond){
        echo "OK  {$name}\n";
    }
    else{
        echo "FAIL {$name}\n";
        $fail++;
    }
}

$tmp = sys_get_temp_dir() . '/pnv_renew_cron_' . getmypid();
@mkdir($tmp . '/locks', 0777, true);
putenv('PNV_RENEW_RESERVE_PATH=' . $tmp . '/renew_reservations.json');
putenv('PNV_LOCKS_DIR=' . $tmp . '/locks');
putenv('PNV_XUI_CONFIG_PATH=' . $tmp . '/xui.json');
putenv('PNV_PLANS_CATALOG_PATH=' . $tmp . '/plans.json');

file_put_contents($tmp . '/xui.json', json_encode([
    'enabled' => true,
    'sub_port' => 2096,
    'renew_server_ids' => ['vip4'],
    'servers' => [[
        'id' => 'vip4',
        'host' => 'vip4.example.com',
        'inbound_id' => 1,
        'base_url' => 'https://example.test/',
        'api_token' => 't',
    ]],
], JSON_UNESCAPED_UNICODE));
file_put_contents($tmp . '/plans.json', '[]');

require_once __DIR__ . '/../renew_reserve_lib.php';
require_once __DIR__ . '/../xui_lib.php';

$link = 'https://vip4.example.com:2096/sub/abc1234567890ab';
$anchorMs = 1700000000000;
$applyCalls = 0;

$GLOBALS['xuiTestRenewRuntimeState'] = [
    'ambiguous' => false,
    'time_unlimited' => false,
    'should_activate_now' => true,
    'volume_exhausted' => true,
];
$GLOBALS['xuiTestRenewLookupClient'] = static function($srv, $subId) use ($anchorMs){
    return [
        'status' => 'found',
        'client' => [
            'email' => 'cron_test',
            'subId' => $subId,
            'id' => 'client-1',
            '_inbound_id' => 1,
            'totalGB' => xuiGbToBytes(50),
            'expiryTime' => $anchorMs + (86400000 * 30),
        ],
    ];
};
$GLOBALS['xuiTestApplyRenewPlanAbsolute'] = static function() use (&$applyCalls){
    $applyCalls++;

    return ['ok' => true, 'method' => 'mock_absolute'];
};

file_put_contents(getenv('PNV_RENEW_RESERVE_PATH'), json_encode([[
    'id' => 'rsv_cron1',
    'status' => 'reserved',
    'username' => 'tester',
    'sub_id' => 'abc1234567890ab',
    'sub_link' => $link,
    'server_host' => 'vip4.example.com',
    'server_id' => 'vip4',
    'plan_gb' => 50,
    'plan_days' => 30,
    'queue_key' => renewReserveQueueKey('tester', 'vip4.example.com', 'abc1234567890ab'),
    'reserved_at' => time() - 60,
    'activation_anchor_ms' => $anchorMs,
]], JSON_UNESCAPED_UNICODE));

$s1 = renewReserveProcessQueue(5);
cronTestAssert('process queue activates once', ($s1['activated'] ?? 0) === 1 && $applyCalls === 1);
$after = renewReserveLoad();
cronTestAssert('status becomes activated', ($after[0]['status'] ?? '') === 'activated');

$s2 = renewReserveProcessQueue(5);
cronTestAssert('second queue pass does not re-apply', ($s2['activated'] ?? 0) === 0 && $applyCalls === 1);

cronTestAssert('cron script exists', is_file(__DIR__ . '/../scripts/renew_reserve_cron.php'));
cronTestAssert('inspect script exists', is_file(__DIR__ . '/../scripts/renew_reserve_inspect.php'));

unset(
    $GLOBALS['xuiTestRenewRuntimeState'],
    $GLOBALS['xuiTestRenewLookupClient'],
    $GLOBALS['xuiTestApplyRenewPlanAbsolute']
);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} cron test(s) failed\n");
    exit(1);
}

echo "All renew reserve cron tests passed\n";

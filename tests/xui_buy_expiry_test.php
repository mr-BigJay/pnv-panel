<?php

/**
 * New-client expiry tests. No live 3x-ui calls are made.
 * Run: php tests/xui_buy_expiry_test.php
 */

$fail = 0;

function buyExpiryAssert($condition, $message){
    global $fail;
    if($condition){
        echo "OK  {$message}\n";
    }
    else{
        echo "FAIL {$message}\n";
        $fail++;
    }
}

$tmp = sys_get_temp_dir() . '/pnv_buy_expiry_' . getmypid();
@mkdir($tmp, 0700, true);
$configPath = $tmp . '/xui_servers.json';
$plansPath = $tmp . '/plans.json';

putenv('PNV_XUI_CONFIG_PATH=' . $configPath);
putenv('PNV_PLANS_CATALOG_PATH=' . $plansPath);

$server = [
    'id' => 'test-vip',
    'name' => 'Test VIP',
    'base_url' => 'https://xui.example.test/panel/',
    'api_token' => 'test-token',
    'inbound_id' => 1,
    'host' => 'xui.example.test',
];
file_put_contents($configPath, json_encode([
    'enabled' => true,
    'buy_server_ids' => ['test-vip'],
    'servers' => [$server],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents($plansPath, json_encode([], JSON_UNESCAPED_UNICODE));

require_once __DIR__ . '/../xui_lib.php';

$GLOBALS['xuiTestPickBuyServer'] = static function($config) use ($server){
    return $server;
};

function runBuyExpiryCase($planText, $mode = 'modern'){
    $capturedModern = null;
    $capturedLegacy = null;
    $postCount = 0;

    $GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$capturedModern, &$capturedLegacy, &$postCount, $mode){
        if($method === 'GET'){
            return ['success' => false, 'msg' => 'test client not found'];
        }

        if($path === '/panel/api/clients/add'){
            $postCount++;
            $capturedModern = $body['client'] ?? null;
            if($mode === 'legacy'){
                return ['success' => false, 'msg' => 'force legacy fallback'];
            }
            return ['success' => true, 'msg' => 'created'];
        }

        if($path === '/panel/api/inbounds/addClient'){
            $postCount++;
            $settings = json_decode((string)($body['settings'] ?? ''), true);
            $capturedLegacy = $settings['clients'][0] ?? null;
            return ['success' => true, 'msg' => 'created'];
        }

        return ['success' => false, 'msg' => 'unexpected test request'];
    };

    $result = xuiProvisionBuy([
        'test-buyer',
        'test-config',
        $planText,
    ]);

    return [
        'result' => $result,
        'modern' => $capturedModern,
        'legacy' => $capturedLegacy,
        'post_count' => $postCount,
    ];
}

$before = (int)floor(microtime(true) * 1000);
$buy30 = runBuyExpiryCase('10 گیگ - 30 روز - 150 هزار تومان');
$after = (int)floor(microtime(true) * 1000);
$expected30 = 30 * 86400000;
$expiry30 = intval($buy30['modern']['expiryTime'] ?? 0);
buyExpiryAssert(!empty($buy30['result']['ok']), '30-day purchase succeeds through buy provisioning');
buyExpiryAssert(intval($buy30['result']['days'] ?? -1) === 30, '30-day duration is returned');
buyExpiryAssert($expiry30 >= $before + $expected30 && $expiry30 <= $after + $expected30, '30-day expiryTime is current epoch milliseconds plus 30 days');

$before = (int)floor(microtime(true) * 1000);
$buy90 = runBuyExpiryCase('10 گیگ - ۹۰ روز - 450 هزار تومان');
$after = (int)floor(microtime(true) * 1000);
$expected90 = 90 * 86400000;
$expiry90 = intval($buy90['modern']['expiryTime'] ?? 0);
buyExpiryAssert(!empty($buy90['result']['ok']), '90-day Persian-digit purchase succeeds');
buyExpiryAssert(intval($buy90['result']['days'] ?? -1) === 90, 'Persian digits in plan duration normalize to 90');
buyExpiryAssert($expiry90 >= $before + $expected90 && $expiry90 <= $after + $expected90, '90-day expiryTime is current epoch milliseconds plus 90 days');

$unlimited = runBuyExpiryCase('10 گیگ - نامحدود زمانی - 150 هزار تومان');
buyExpiryAssert(!empty($unlimited['result']['ok']), 'unlimited-time purchase succeeds');
buyExpiryAssert(intval($unlimited['modern']['expiryTime'] ?? -1) === 0, 'unlimited-time purchase keeps expiryTime zero');

file_put_contents($plansPath, json_encode([
    ['name' => 'ویژه ۱۰ گیگ', 'days' => '۹۰'],
], JSON_UNESCAPED_UNICODE));
$before = (int)floor(microtime(true) * 1000);
$catalog = runBuyExpiryCase('ویژه ۱۰ گیگ');
$after = (int)floor(microtime(true) * 1000);
$catalogExpiry = intval($catalog['modern']['expiryTime'] ?? 0);
buyExpiryAssert(!empty($catalog['result']['ok']), 'catalog-defined plan purchase succeeds');
buyExpiryAssert(intval($catalog['result']['days'] ?? -1) === 90, 'catalog Persian day count is normalized');
buyExpiryAssert($catalogExpiry >= $before + $expected90 && $catalogExpiry <= $after + $expected90, 'catalog-defined expiry is applied to new client');

$before = (int)floor(microtime(true) * 1000);
$legacy = runBuyExpiryCase('10 گیگ - ۹۰ روز', 'legacy');
$after = (int)floor(microtime(true) * 1000);
$legacyExpiry = intval($legacy['legacy']['expiryTime'] ?? 0);
buyExpiryAssert(!empty($legacy['result']['ok']), 'legacy inbounds/addClient fallback succeeds');
buyExpiryAssert($legacyExpiry >= $before + $expected90 && $legacyExpiry <= $after + $expected90, 'legacy fallback carries the same 90-day expiryTime');

$unknown = runBuyExpiryCase('10 گیگ - مدت نامشخص');
buyExpiryAssert(empty($unknown['result']['ok']) && ($unknown['result']['code'] ?? '') === 'invalid_plan_duration', 'unknown duration is rejected instead of creating a never-expiring client');
buyExpiryAssert(intval($unknown['post_count']) === 0, 'unknown duration sends no create request');

buyExpiryAssert(xuiParsePlanGb('۱۰ گیگ') === 10, 'Persian digits in volume are normalized');

unset(
    $GLOBALS['xuiTestPickBuyServer'],
    $GLOBALS['xuiTestApiRequest']
);
@unlink($configPath);
@unlink($plansPath);
@rmdir($tmp);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} test(s) failed\n");
    exit(1);
}

echo "All new-client expiry tests passed\n";

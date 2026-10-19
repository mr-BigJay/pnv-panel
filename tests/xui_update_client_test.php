<?php
/**
 * 3x-ui client update: modern clients/update first, legacy form-encoded updateClient fallback.
 */
require_once dirname(__DIR__) . '/xui_lib.php';

$failed = 0;
function updAssert($ok, $msg){
    global $failed;
    if(!$ok){
        fwrite(STDERR, "FAIL: $msg\n");
        $failed++;
    }
}

$server = ['id' => 'vip3', 'base_url' => 'https://vip3.example', 'inbound_id' => 7, 'api_token' => 'tok'];
$client = [
    'id' => '95e4e7bb-7796-47e7-e8a7-f4055194f776',
    'email' => 'user_demo',
    'subId' => 'sub1234567890abcd',
    'flow' => '',
    'totalGB' => xuiGbToBytes(90),
    'expiryTime' => 0,
    '_inbound_id' => 7,
];

$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body, $options = []) use (&$calls){
    $calls[] = [
        'method' => $method,
        'path' => $path,
        'body' => $body,
        'encode' => is_array($options) ? ($options['encode'] ?? 'json') : 'json',
    ];

    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => false, 'msg' => 'پاسخ نامعتبر از 3x-ui (HTTP 404)', 'http' => 404];
    }

    if(strpos($path, '/panel/api/inbounds/updateClient/') === 0){
        return ['success' => true, 'msg' => 'ok'];
    }

    return ['success' => false, 'msg' => 'unexpected path'];
};

$applied = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, 1700000000000);
updAssert(!empty($applied['ok']), 'legacy form update succeeds after modern 404');
updAssert(count($calls) === 2, 'modern then legacy attempted');
updAssert(strpos($calls[0]['path'], '/panel/api/clients/update/') === 0, 'first call is clients/update');
updAssert($calls[1]['encode'] === 'form', 'legacy updateClient uses form encoding');
updAssert(strpos($calls[1]['path'], '95e4e7bb-7796-47e7-e8a7-f4055194f776') !== false, 'legacy path uses client uuid');

unset($GLOBALS['xuiTestApiRequest']);
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body, $options = []) use (&$calls){
    $calls[] = $path;

    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => true, 'msg' => 'updated'];
    }

    return ['success' => false, 'msg' => 'should not reach legacy'];
};

$already = [
    'id' => $client['id'],
    'email' => 'user_demo',
    'subId' => 'sub1234567890abcd',
    '_inbound_id' => 7,
    'totalGB' => xuiGbToBytes(10),
    'expiryTime' => 1700000000000 + 86400000 * 30,
];
$skip = xuiApplyRenewPlanAbsolute($server, $already, 10, 30, 1700000000000);
updAssert(!empty($skip['ok']) && ($skip['method'] ?? '') === 'already_applied', 'skips API when plan already applied');
updAssert(count($calls) === 0, 'no HTTP when already applied');

unset($GLOBALS['xuiTestApiRequest']);
echo $failed ? "$failed failed\n" : "All xui update client tests passed\n";
exit($failed ? 1 : 0);

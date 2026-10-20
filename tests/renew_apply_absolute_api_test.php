<?php

/**
 * xuiApplyRenewPlanAbsolute API strategy + diagnostics.
 * php tests/renew_apply_absolute_api_test.php
 */

$fail = 0;

function raaAssert($name, $cond){
    global $fail;

    if($cond){
        echo "OK  {$name}\n";
    }
    else{
        echo "FAIL {$name}\n";
        $fail++;
    }
}

require_once __DIR__ . '/../xui_lib.php';

$server = [
    'id' => 'vip3',
    'host' => 'vip3.example.com',
    'inbound_id' => 3,
    'base_url' => 'https://example.test/',
    'api_token' => 'test-token',
];

$client = [
    'email' => 'e2e_user@test.local',
    'id' => 'uuid-1111-2222',
    'subId' => 'abc1234567890ab',
    '_inbound_id' => 3,
    'limitIp' => 0,
    'flow' => '',
];

$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$calls){
    $calls[] = ['kind' => 'json', 'method' => $method, 'path' => $path, 'body' => $body];

    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => true, 'msg' => 'ok', 'http' => 200];
    }

    return ['success' => false, 'msg' => 'modern failed', 'http' => 400];
};

$okModern = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, 1700000000000);
raaAssert('prefers modern clients/update when it succeeds', !empty($okModern['ok']) && ($okModern['method'] ?? '') === 'clients_update');
raaAssert('modern path attempted first', strpos($calls[0]['path'] ?? '', '/panel/api/clients/update/') === 0);

unset($GLOBALS['xuiTestApiRequest']);
$calls = [];

$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$calls){
    $calls[] = ['kind' => 'json', 'method' => $method, 'path' => $path, 'body' => $body];

    // Only a definite 404 allows changing the endpoint/encoding.
    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => false, 'msg' => 'modern endpoint missing', 'http' => 404];
    }

    return ['success' => false, 'msg' => 'json legacy failed', 'http' => 500];
};

$GLOBALS['xuiTestApiRequestForm'] = static function($srv, $method, $path, $fields) use (&$calls){
    $calls[] = ['kind' => 'form', 'method' => $method, 'path' => $path, 'fields' => $fields];

    if(strpos($path, '/panel/api/inbounds/updateClient/') === 0){
        return ['success' => true, 'msg' => 'ok', 'http' => 200];
    }

    return ['success' => false, 'msg' => 'form failed', 'http' => 500];
};

$okForm = xuiApplyRenewPlanAbsolute($server, $client, 5, 7, 1700000000000);
raaAssert('falls back to form-encoded inbounds/updateClient', !empty($okForm['ok']) && ($okForm['method'] ?? '') === 'updateClient_absolute_form');
$formCall = null;

foreach($calls as $c){
    if(($c['kind'] ?? '') === 'form'){
        $formCall = $c;
        break;
    }
}

raaAssert('form payload uses id and settings string', is_array($formCall) && isset($formCall['fields']['settings']) && is_string($formCall['fields']['settings']));

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestApiRequestForm']);
$calls = [];

$GLOBALS['xuiTestApiRequest'] = static function(){
    return ['success' => false, 'msg' => 'خطا در ارتباط با 3x-ui: timed out', 'http' => 0];
};
$GLOBALS['xuiTestApiRequestForm'] = static function(){
    return ['success' => false, 'msg' => 'Something went wrong', 'http' => 500];
};

$bad = xuiApplyRenewPlanAbsolute($server, $client, 5, 7, 1700000000000);
$summary = xuiApplyRenewDiagSummary($bad);
raaAssert('failure includes attempts diag summary', empty($bad['ok']) && strpos($summary, 'HTTP') !== false && strpos($summary, '/panel/api/') !== false);

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestApiRequestForm']);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} apply absolute test(s) failed\n");
    exit(1);
}

echo "All renew apply absolute API tests passed\n";

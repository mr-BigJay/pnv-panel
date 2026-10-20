<?php
/**
 * Regression tests for legacy 3x-ui renew: form encoding, 404 fallback and fail-closed.
 * Safe mocks only; no real 3x-ui or payment data touched.
 */
require_once __DIR__ . '/../xui_lib.php';

$failed = 0;
function legacyAssert($name, $condition){
    global $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $name . "\n";
    if(!$condition) $failed++;
}

$server = ['id' => 'mock', 'base_url' => 'https://invalid.example', 'inbound_id' => 3];
$client = [
    'id' => 'test-uuid-123',
    'email' => 'user@example.test',
    'subId' => 'testSub12345678',
    'totalGB' => xuiGbToBytes(12),
    'expiryTime' => 0,
    '_inbound_id' => 3,
];
$forms = [];
$jsonCalls = 0;

$GLOBALS['xuiTestApiRequestForm'] = static function($srv, $method, $path, $fields) use (&$forms){
    $forms[] = [$method, $path, $fields];
    return ['success' => true, 'http' => 200];
};
$GLOBALS['xuiTestApiRequest'] = static function() use (&$jsonCalls){
    $jsonCalls++;
    return ['success' => false, 'http' => 500];
};

$good = xuiAdjustClientTrafficLegacy($server, $client, 5);
legacyAssert('form success', !empty($good['ok']) && ($good['method'] ?? '') === 'updateClient_form');
legacyAssert('no JSON replay on form success', $jsonCalls === 0);
$sent = $forms[0] ?? [];
$settings = json_decode($sent[2]['settings'] ?? '', true);
legacyAssert('form fields have id and JSON settings', ($sent[2]['id'] ?? null) === '3' && ($sent[1] ?? '') === '/panel/api/inbounds/updateClient/test-uuid-123' && is_array($settings));
legacyAssert('increase requested volume once', intval($settings['clients'][0]['totalGB'] ?? -1) === xuiGbToBytes(17));

$forms = [];
$jsonCalls = 0;
$GLOBALS['xuiTestApiRequestForm'] = static function() use (&$forms){
    $forms[] = true;
    return ['success' => false, 'http' => 404, 'msg' => 'Not Found'];
};
$GLOBALS['xuiTestApiRequest'] = static function() use (&$jsonCalls){
    $jsonCalls++;
    return ['success' => true, 'http' => 200];
};
$fallback = xuiAdjustClientTrafficLegacy($server, $client, 5);
legacyAssert('definite HTTP 404 tries JSON exactly once', !empty($fallback['ok']) && ($fallback['method'] ?? '') === 'updateClient_json' && count($forms) === 1 && $jsonCalls === 1);

$forms = [];
$jsonCalls = 0;
$GLOBALS['xuiTestApiRequestForm'] = static function() use (&$forms){
    $forms[] = true;
    return ['success' => false, 'http' => 0, 'msg' => 'Operation timed out'];
};
$GLOBALS['xuiTestApiRequest'] = static function() use (&$jsonCalls){
    $jsonCalls++;
    return ['success' => true, 'http' => 200];
};
$ambiguous = xuiAdjustClientTrafficLegacy($server, $client, 5);
legacyAssert('timeout is fail-closed without retry', empty($ambiguous['ok']) && ($ambiguous['code'] ?? '') === 'ambiguous_update_response' && $jsonCalls === 0);

$forms = [];
$jsonCalls = 0;
$invalid = $client;
$invalid['id'] = '12345';
unset($invalid['uuid']);
$invalidResult = xuiAdjustClientTrafficLegacy($server, $invalid, 5);
legacyAssert('numeric stats id rejected without mutation', empty($invalidResult['ok']) && ($invalidResult['code'] ?? '') === 'invalid_client_identity' && count($forms) === 0 && $jsonCalls === 0);

$withUuid = $invalid;
$withUuid['uuid'] = 'actual-uuid-123';
$GLOBALS['xuiTestApiRequestForm'] = static function($srv, $method, $path, $fields) use (&$forms){
    $forms[] = $path;
    return ['success' => true, 'http' => 200];
};
$normalized = xuiAdjustClientTrafficLegacy($server, $withUuid, 2);
legacyAssert('numeric stats ID normalized to UUID', !empty($normalized['ok']) && in_array('/panel/api/inbounds/updateClient/actual-uuid-123', $forms, true));

$diag = xuiApiAttemptDiag('/panel/api/clients/update/user%40example.test', ['success' => false, 'http' => 404, 'msg' => 'not found']);
legacyAssert('sensitive email redacted in error path', strpos($diag['api_path'] ?? '', 'example.test') === false && strpos($diag['api_path'] ?? '', '{client}') !== false);
$diag2 = xuiApiAttemptDiag('/panel/api/inbounds/updateClient/test-uuid-123 (json)', ['success' => false, 'http' => 404, 'msg' => 'not found']);
legacyAssert('sensitive UUID redacted in error path', strpos($diag2['api_path'] ?? '', 'test-uuid') === false);

$GLOBALS['xuiTestApiRequestForm'] = static fn() => ['success' => false, 'http' => 404, 'msg' => 'not found'];
$GLOBALS['xuiTestApiRequest'] = static fn() => ['success' => false, 'http' => 404, 'msg' => 'not found'];
$abs = xuiApplyRenewPlanAbsolute($server, $client, 2, 30, 1700000000000);
legacyAssert('absolute failure gives route/status diagnostics without client identifiers',
    empty($abs['ok'])
    && strpos((string)($abs['error'] ?? ''), 'HTTP 404') !== false
    && strpos((string)($abs['error'] ?? ''), 'example.test') === false
    && strpos((string)($abs['error'] ?? ''), 'test-uuid') === false
);

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestApiRequestForm']);
if($failed > 0){
    fwrite(STDERR, "$failed xui legacy renewal test(s) failed\n");
    exit(1);
}
echo "All XUI legacy renewal form tests passed\n";

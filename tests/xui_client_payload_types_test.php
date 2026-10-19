<?php
/**
 * Regression: 3x-ui model.Client JSON field types (allowedIPs []string, etc.).
 */
require_once dirname(__DIR__) . '/xui_lib.php';

$failed = 0;

function payloadAssert($ok, $msg){
    global $failed;

    if(!$ok){
        fwrite(STDERR, "FAIL: $msg\n");
        $failed++;
    }
}

function payloadJsonHasStringAllowedIPs($json){
    return is_string($json) && preg_match('/"allowedIPs"\s*:\s*"/', $json) === 1;
}

function setReloadSequence(array $states){
    $i = 0;
    $GLOBALS['xuiTestReloadClientForPlanVerify'] = static function() use ($states, &$i){
        $idx = min($i, count($states) - 1);
        $i++;
        $fn = $states[$idx];

        return is_callable($fn) ? $fn() : $fn;
    };
}

$baseClient = [
    'id' => '95e4e7bb-7796-47e7-e8a7-f4055194f776',
    'email' => 'user_demo',
    'subId' => 'sub1234567890abcd',
    'flow' => 'xtls-rprx-vision',
    'enable' => true,
    'limitIp' => 0,
    'totalGB' => xuiGbToBytes(90),
    'expiryTime' => 0,
    'security' => 'auto',
];

// CSV string from ClientRecord GET → []string in write payload.
$csv = array_merge($baseClient, ['allowedIPs' => '10.66.0.2/32, fd00::2/128']);
$preparedCsv = xuiPrepareClientPayloadForWrite($csv);
payloadAssert(is_array($preparedCsv['allowedIPs'] ?? null), 'allowedIPs CSV becomes array');
payloadAssert(
    ($preparedCsv['allowedIPs'] ?? []) === ['10.66.0.2/32', 'fd00::2/128'],
    'allowedIPs CSV entries preserved'
);
$jsonCsv = json_encode($preparedCsv, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
payloadAssert(!payloadJsonHasStringAllowedIPs($jsonCsv), 'encoded allowedIPs is not a JSON string');

// JSON-text array string → []string.
$jsonText = array_merge($baseClient, ['allowedIPs' => '["10.66.0.3/32","10.66.0.4/32"]']);
$preparedJsonText = xuiPrepareClientPayloadForWrite($jsonText);
payloadAssert(
    ($preparedJsonText['allowedIPs'] ?? []) === ['10.66.0.3/32', '10.66.0.4/32'],
    'allowedIPs JSON-text array decoded'
);

// Already-valid array preserved.
$arrayIps = array_merge($baseClient, ['allowedIPs' => ['10.66.0.5/32']]);
$preparedArray = xuiPrepareClientPayloadForWrite($arrayIps);
payloadAssert(
    ($preparedArray['allowedIPs'] ?? []) === ['10.66.0.5/32'],
    'allowedIPs array preserved'
);

// allowedIPsByInbound map coercion.
$byInbound = array_merge($baseClient, [
    'allowedIPsByInbound' => [
        '7' => '10.66.0.6/32',
        8 => ['10.66.0.7/32', '10.66.0.8/32'],
    ],
]);
$preparedMap = xuiPrepareClientPayloadForWrite($byInbound);
payloadAssert(is_array($preparedMap['allowedIPsByInbound'][7] ?? null), 'allowedIPsByInbound values are arrays');
payloadAssert(
    ($preparedMap['allowedIPsByInbound'][7] ?? []) === ['10.66.0.6/32'],
    'allowedIPsByInbound CSV value coerced'
);

// reverse JSON string → object for API.
$reverseClient = array_merge($baseClient, ['reverse' => '{"tag":"rev-tag"}']);
$preparedReverse = xuiPrepareClientPayloadForWrite($reverseClient);
payloadAssert(is_array($preparedReverse['reverse'] ?? null), 'reverse JSON string becomes object');
payloadAssert(($preparedReverse['reverse']['tag'] ?? '') === 'rev-tag', 'reverse tag preserved');

// Modern update POST body uses []string allowedIPs (type-mismatch guard).
$server = ['id' => 'vip3', 'base_url' => 'https://vip3.example', 'inbound_id' => 7, 'api_token' => 'tok'];
$live = array_merge($baseClient, [
    'allowedIPs' => '10.66.0.9/32',
    '_inbound_id' => 7,
]);
$postedBodies = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$postedBodies){
    if(strpos($path, '/panel/api/clients/update/') === 0){
        $postedBodies[] = $body;
        return ['success' => true, 'msg' => 'ok'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
setReloadSequence([
    static fn() => $live,
    static fn() => array_merge($live, ['totalGB' => xuiGbToBytes(10), 'expiryTime' => 1700000000000 + 86400000 * 30]),
]);
xuiPushClientPlanUpdate($server, $live, ['totalGB' => xuiGbToBytes(10), 'expiryTime' => 1700000000000 + 86400000 * 30], [
    'mode' => 'absolute',
    'gb' => 10,
    'days' => 30,
    'anchor_ms' => 1700000000000,
    'require_enable' => true,
]);
$modernBody = $postedBodies[0] ?? [];
payloadAssert(is_array($modernBody['allowedIPs'] ?? null), 'modern POST carries allowedIPs array');
payloadAssert(
    ($modernBody['allowedIPs'] ?? []) === ['10.66.0.9/32'],
    'modern POST preserves allowedIPs value'
);
$modernJson = json_encode($modernBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
payloadAssert(!payloadJsonHasStringAllowedIPs($modernJson), 'modern POST JSON avoids string allowedIPs type mismatch');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// Legacy settings JSON also encodes allowedIPs as array.
$legacySettings = null;
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$legacySettings){
    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => false, 'msg' => 'Not Found', 'http' => 404];
    }

    if(strpos($path, '/panel/api/inbounds/updateClient/') === 0){
        $legacySettings = $body['settings'] ?? null;
        return ['success' => true];
    }

    return ['success' => false];
};
setReloadSequence([
    static fn() => $live,
    static fn() => array_merge($live, ['totalGB' => xuiGbToBytes(100)]),
]);
xuiPushClientPlanUpdate($server, $live, ['totalGB' => xuiGbToBytes(100)], [
    'mode' => 'bytes_at_least',
    'total_bytes' => xuiGbToBytes(100),
    'require_enable' => true,
]);
$settings = json_decode((string)$legacySettings, true);
$legacyClient = $settings['clients'][0] ?? [];
payloadAssert(is_array($legacyClient['allowedIPs'] ?? null), 'legacy settings JSON uses allowedIPs array');
payloadAssert(!payloadJsonHasStringAllowedIPs((string)$legacySettings), 'legacy settings avoid string allowedIPs');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

echo $failed ? "$failed failed\n" : "All xui client payload type tests passed\n";
exit($failed ? 1 : 0);

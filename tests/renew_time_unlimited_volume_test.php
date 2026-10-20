<?php
/**
 * Time-unlimited (expiryTime=0) volume-only renew: target = used + purchased.
 */

$fail = 0;

function tuAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

require_once __DIR__ . '/../xui_lib.php';

$server = ['id' => 'vip-test', 'base_url' => 'https://vip.example', 'inbound_id' => 7, 'api_token' => 'tok'];
$subId = 'testsub1234567890';
$client = [
    'id' => '95e4e7bb-7796-47e7-e8a7-f4055194f776',
    'email' => 'user_demo',
    'subId' => $subId,
    'flow' => 'xtls-rprx-vision',
    'enable' => true,
    'limitIp' => 0,
    'expiryTime' => 0,
    '_inbound_id' => 7,
];

function tuUsedClient($usedGb){
    global $client;
    $usedBytes = xuiGbToBytes($usedGb);

    return array_merge($client, [
        'up' => $usedBytes,
        'down' => 0,
        'totalGB' => xuiGbToBytes(200),
    ]);
}

function tuSetReload(array $states){
    $i = 0;
    $GLOBALS['xuiTestReloadClientForPlanVerify'] = static function() use ($states, &$i){
        $idx = min($i, count($states) - 1);
        $i++;
        $fn = $states[$idx];

        return is_callable($fn) ? $fn() : $fn;
    };
}

// 140GB used + 50GB purchased => 190GB cap, expiry stays 0.
$target190 = xuiGbToBytes(190);
$postBodies = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$postBodies){
    if(strpos($path, '/panel/api/clients/update/') === 0){
        $postBodies[] = $body;
        return ['success' => true, 'msg' => 'ok'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
tuSetReload([
    static fn() => tuUsedClient(140),
    static fn() => tuUsedClient(140),
    static fn() => array_merge(tuUsedClient(140), ['totalGB' => $target190, 'expiryTime' => 0]),
]);
$applied = xuiApplyRenewTimeUnlimitedVolumePack($server, tuUsedClient(140), 50);
tuAssert(!empty($applied['ok']), '140GB used + 50GB pack succeeds');
tuAssert(intval($applied['target_total_bytes'] ?? 0) === $target190, 'target total is 190GB');
$body = $postBodies[0] ?? [];
tuAssert(intval($body['totalGB'] ?? 0) === $target190, 'POST sets totalGB to used+purchased');
tuAssert(intval($body['expiryTime'] ?? -1) === 0, 'POST keeps expiryTime zero');
tuAssert(!array_key_exists('reset', $body), 'POST does not reset usage counters');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify'], $postBodies);

// Zero usage + 50GB => 50GB cap.
$target50 = xuiGbToBytes(50);
$postBodies = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$postBodies){
    if(strpos($path, '/panel/api/clients/update/') === 0){
        $postBodies[] = $body;
        return ['success' => true];
    }

    return ['success' => false];
};
tuSetReload([
    static fn() => array_merge($client, ['up' => 0, 'down' => 0, 'totalGB' => xuiGbToBytes(10)]),
    static fn() => array_merge($client, ['up' => 0, 'down' => 0, 'totalGB' => xuiGbToBytes(10)]),
    static fn() => array_merge($client, ['up' => 0, 'down' => 0, 'totalGB' => $target50, 'expiryTime' => 0]),
]);
$zeroUsed = xuiApplyRenewTimeUnlimitedVolumePack($server, array_merge($client, ['up' => 0, 'down' => 0]), 50);
tuAssert(!empty($zeroUsed['ok']) && intval($zeroUsed['target_total_bytes'] ?? 0) === $target50, 'zero usage + 50GB pack');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// Missing usage stats -> fail-closed, no POST.
$postBodies = [];
$GLOBALS['xuiTestApiRequest'] = static function() use (&$postBodies){
    $postBodies[] = 'blocked';
    return ['success' => true];
};
tuSetReload([static fn() => array_merge($client, ['totalGB' => xuiGbToBytes(50)])]);
$badUsage = xuiApplyRenewTimeUnlimitedVolumePack($server, $client, 50);
tuAssert(empty($badUsage['ok']) && ($badUsage['code'] ?? '') === 'usage_stats_invalid', 'missing usage stats blocks renew');
tuAssert(count($postBodies) === 0, 'no POST when usage stats invalid');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// Stored target idempotency: usage drift between attempts still targets 190GB, single POST.
$postBodies = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$postBodies){
    if(strpos($path, '/panel/api/clients/update/') === 0){
        $postBodies[] = $body;
        return ['success' => true];
    }

    return ['success' => false];
};
tuSetReload([
    static fn() => tuUsedClient(145),
    static fn() => array_merge(tuUsedClient(145), ['totalGB' => $target190, 'expiryTime' => 0]),
]);
$retry = xuiApplyRenewTimeUnlimitedVolumePack($server, tuUsedClient(140), 50, [
    'target_total_bytes' => $target190,
    'used_bytes_at_order' => xuiGbToBytes(140),
]);
tuAssert(!empty($retry['ok']), 'retry with stored target succeeds');
tuAssert(intval($retry['target_total_bytes'] ?? 0) === $target190, 'stored target preserved on retry');
tuAssert(count($postBodies) === 1, 'single POST for idempotent retry');
tuAssert(intval(($postBodies[0]['totalGB'] ?? 0)) === $target190, 'retry POST uses stored 190GB target');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// Identity mismatch on live read -> blocked.
tuSetReload([
    static function(){
        $c = tuUsedClient(140);
        $c['subId'] = 'other-sub-id';

        return $c;
    },
]);
$GLOBALS['xuiTestApiRequest'] = static fn() => ['success' => true];
$idMismatch = xuiApplyRenewTimeUnlimitedVolumePack($server, tuUsedClient(140), 50);
tuAssert(empty($idMismatch['ok']) && ($idMismatch['code'] ?? '') === 'client_identity_mismatch', 'identity mismatch blocks renew');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

echo $fail ? "\n{$fail} time-unlimited volume test(s) failed\n" : "All time-unlimited volume renew tests passed\n";
exit($fail ? 1 : 0);

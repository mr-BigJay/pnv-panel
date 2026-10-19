<?php
/**
 * Safe client update: conditional fallback, GET verify, post-success validation.
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
$anchor = 1700000000000;
$client = [
    'id' => '95e4e7bb-7796-47e7-e8a7-f4055194f776',
    'email' => 'user_demo',
    'subId' => 'sub1234567890abcd',
    'flow' => 'xtls-rprx-vision',
    'totalGB' => xuiGbToBytes(90),
    'expiryTime' => 0,
    'enable' => true,
    '_inbound_id' => 7,
];

function liveClient($gb, $days, $anchorMs){
    return [
        'id' => '95e4e7bb-7796-47e7-e8a7-f4055194f776',
        'email' => 'user_demo',
        'subId' => 'sub1234567890abcd',
        'flow' => 'xtls-rprx-vision',
        'totalGB' => xuiGbToBytes($gb),
        'expiryTime' => $days > 0 ? ($anchorMs + $days * 86400000) : 0,
        'enable' => true,
        '_inbound_id' => 7,
    ];
}

// 1) Definitive unsupported modern (JSON 404) -> legacy form, then verify.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body, $options = []) use (&$calls){
    $calls[] = ['path' => $path, 'encode' => is_array($options) ? ($options['encode'] ?? 'json') : 'json'];

    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => false, 'msg' => 'Not Found', 'http' => 404];
    }

    if(strpos($path, '/panel/api/inbounds/updateClient/') === 0){
        return ['success' => true, 'msg' => 'ok'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
$GLOBALS['xuiTestReloadClientForPlanVerify'] = static fn() => liveClient(10, 30, 1700000000000);

$r1 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(!empty($r1['ok']), 'legacy succeeds after definitive modern 404');
updAssert(count($calls) === 2, 'modern then legacy only');
updAssert($calls[1]['encode'] === 'form', 'legacy uses form encoding');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 2) Invalid HTML 404 on modern -> ambiguous, no second POST.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$calls){
    $calls[] = $path;

    return ['success' => false, 'msg' => 'پاسخ نامعتبر از 3x-ui (HTTP 404)', 'http' => 404];
};
$GLOBALS['xuiTestReloadClientForPlanVerify'] = static fn() => liveClient(90, 0, $anchor);

$r2 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(empty($r2['ok']) && ($r2['code'] ?? '') === 'ambiguous_provision', 'invalid 404 stops without legacy');
updAssert(count($calls) === 1, 'no legacy POST after ambiguous modern failure');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 3) HTTP 500 -> no legacy.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$calls){
    $calls[] = $path;

    return ['success' => false, 'msg' => 'internal error', 'http' => 500];
};
$GLOBALS['xuiTestReloadClientForPlanVerify'] = static fn() => liveClient(90, 0, $anchor);

$r3 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(empty($r3['ok']) && ($r3['code'] ?? '') === 'ambiguous_provision', '500 is ambiguous');
updAssert(count($calls) === 1, 'no legacy after 500');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 4) Timeout -> verify-only recovery when plan already applied.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$calls){
    $calls[] = $path;

    return ['success' => false, 'msg' => 'خطا در ارتباط با 3x-ui: Operation timed out', 'http' => 0];
};
$GLOBALS['xuiTestReloadClientForPlanVerify'] = static fn() => liveClient(10, 30, $anchor);

$r4 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(!empty($r4['ok']) && ($r4['method'] ?? '') === 'verify_after_modern_ambiguous_absolute', 'timeout + verified state succeeds');
updAssert(count($calls) === 1, 'timeout does not trigger legacy POST');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 5) API success but client unchanged -> verification_failed.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$calls){
    $calls[] = $path;

    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => true, 'msg' => 'updated'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
$GLOBALS['xuiTestReloadClientForPlanVerify'] = static fn() => liveClient(90, 0, $anchor);

$r5 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(empty($r5['ok']) && ($r5['code'] ?? '') === 'verification_failed', 'success without real change rejected');
updAssert(count($calls) === 1, 'no legacy when modern returned JSON success');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 6) Legacy payload preserves id/subId/flow in settings JSON.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body, $options = []) use (&$calls){
    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => false, 'msg' => 'Not Found', 'http' => 404];
    }

    if(strpos($path, '/panel/api/inbounds/updateClient/') === 0){
        $calls[] = $body;
        return ['success' => true];
    }

    return ['success' => false];
};
$GLOBALS['xuiTestReloadClientForPlanVerify'] = static fn() => liveClient(10, 30, $anchor);
xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
$settings = json_decode((string)($calls[0]['settings'] ?? ''), true);
$written = $settings['clients'][0] ?? [];
updAssert(($written['id'] ?? '') === $client['id'], 'legacy settings keep client id');
updAssert(($written['subId'] ?? '') === $client['subId'], 'legacy settings keep subId');
updAssert(($written['flow'] ?? '') === $client['flow'], 'legacy settings keep flow');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify'], $calls);

// 7) Already applied skips HTTP.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function() use (&$calls){
    $calls[] = 'should-not-run';
    return ['success' => true];
};
$skip = xuiApplyRenewPlanAbsolute($server, liveClient(10, 30, $anchor), 10, 30, $anchor);
updAssert(!empty($skip['ok']) && ($skip['method'] ?? '') === 'already_applied', 'already applied short-circuit');
updAssert(count($calls) === 0, 'no HTTP when already applied');
unset($GLOBALS['xuiTestApiRequest']);

echo $failed ? "$failed failed\n" : "All xui update client tests passed\n";
exit($failed ? 1 : 0);

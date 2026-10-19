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

function setReloadSequence(array $states){
    $i = 0;
    $GLOBALS['xuiTestReloadClientForPlanVerify'] = static function() use ($states, &$i){
        $idx = min($i, count($states) - 1);
        $i++;
        $fn = $states[$idx];
        return is_callable($fn) ? $fn() : $fn;
    };
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
setReloadSequence([
    fn() => liveClient(90, 0, $anchor),
    fn() => liveClient(10, 30, $anchor),
]);

$r1 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(!empty($r1['ok']), 'legacy succeeds after definitive modern 404');
updAssert(count($calls) === 2, 'modern then legacy only');
updAssert($calls[1]['encode'] === 'form', 'legacy uses form encoding');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 2) HTML/non-JSON 404 on modern -> endpoint missing, safe legacy fallback.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body, $options = []) use (&$calls){
    $calls[] = ['path' => $path, 'encode' => is_array($options) ? ($options['encode'] ?? 'json') : 'json'];

    if(strpos($path, '/panel/api/clients/update/') === 0){
        return [
            'success' => false,
            'msg' => 'پاسخ نامعتبر از 3x-ui (HTTP 404)',
            'http' => 404,
            'raw' => '<html><body>404 page not found</body></html>',
            'response_format' => 'non_json',
        ];
    }

    if(strpos($path, '/panel/api/inbounds/updateClient/') === 0){
        return ['success' => true, 'msg' => 'ok'];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
setReloadSequence([
    fn() => liveClient(90, 0, $anchor),
    fn() => liveClient(10, 30, $anchor),
]);

$r2 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(!empty($r2['ok']), 'HTML 404 on modern allows legacy fallback');
updAssert(count($calls) === 2, 'modern then legacy after HTML 404');
updAssert($calls[1]['encode'] === 'form', 'legacy form after HTML endpoint 404');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 2b) JSON 404 client missing on modern -> no legacy POST.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$calls){
    $calls[] = $path;

    if(strpos($path, '/panel/api/clients/update/') === 0){
        return ['success' => false, 'msg' => 'Client not found for email', 'http' => 404];
    }

    return ['success' => false, 'msg' => 'unexpected'];
};
setReloadSequence([fn() => liveClient(90, 0, $anchor), fn() => liveClient(90, 0, $anchor)]);

$r2b = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(empty($r2b['ok']) && ($r2b['code'] ?? '') === 'activation_failed', 'client-not-found JSON 404 skips legacy');
updAssert(count($calls) === 1, 'only modern POST when client missing');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 3) HTTP 500 -> no legacy.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$calls){
    $calls[] = $path;

    return ['success' => false, 'msg' => 'internal error', 'http' => 500];
};
setReloadSequence([fn() => liveClient(90, 0, $anchor), fn() => liveClient(90, 0, $anchor)]);

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
setReloadSequence([
    fn() => liveClient(90, 0, $anchor),
    fn() => liveClient(10, 30, $anchor),
]);

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
setReloadSequence([fn() => liveClient(90, 0, $anchor), fn() => liveClient(90, 0, $anchor)]);

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
setReloadSequence([
    fn() => liveClient(90, 0, $anchor),
    fn() => liveClient(10, 30, $anchor),
]);
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

// 8) Live GET shows plan already applied -> no POST (duplicate prevention).
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function() use (&$calls){
    $calls[] = 'blocked';
    return ['success' => true];
};
setReloadSequence([fn() => liveClient(10, 30, $anchor)]);
$r8 = xuiPushClientPlanUpdate($server, $client, ['totalGB' => xuiGbToBytes(10)], [
    'mode' => 'absolute',
    'gb' => 10,
    'days' => 30,
    'anchor_ms' => $anchor,
    'require_enable' => true,
]);
updAssert(!empty($r8['ok']) && ($r8['method'] ?? '') === 'already_applied_live', 'preflight GET skips writes');
updAssert(count($calls) === 0, 'duplicate renew blocked before POST');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 9) Identity drift after reload fails verification (no false success).
setReloadSequence([
    fn() => liveClient(90, 0, $anchor),
    static function(){
        $c = liveClient(10, 30, 1700000000000);
        $c['subId'] = 'different_sub_id';
        return $c;
    },
]);
$GLOBALS['xuiTestApiRequest'] = static fn() => ['success' => true, 'msg' => 'updated'];
$r9 = xuiPushClientPlanUpdate($server, $client, ['totalGB' => xuiGbToBytes(10)], [
    'mode' => 'absolute',
    'gb' => 10,
    'days' => 30,
    'anchor_ms' => $anchor,
    'require_enable' => true,
]);
updAssert(empty($r9['ok']) && ($r9['code'] ?? '') === 'verification_failed', 'subId drift fails post-success verify');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 10) Modern JSON body keeps locked identity/protocol fields.
$modernBodies = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path, $body) use (&$modernBodies){
    if(strpos($path, '/panel/api/clients/update/') === 0){
        $modernBodies[] = $body;
        return ['success' => true];
    }
    return ['success' => false];
};
setReloadSequence([fn() => liveClient(90, 0, $anchor), fn() => liveClient(10, 30, $anchor)]);
xuiPushClientPlanUpdate($server, $client, [
    'totalGB' => xuiGbToBytes(10),
    'email' => 'evil@example.com',
    'id' => '00000000-0000-0000-0000-000000000000',
    'subId' => 'hijacked',
], [
    'mode' => 'absolute',
    'gb' => 10,
    'days' => 30,
    'anchor_ms' => $anchor,
    'require_enable' => true,
]);
$mb = $modernBodies[0] ?? [];
updAssert(($mb['email'] ?? '') === $client['email'], 'modern update cannot override email');
updAssert(($mb['id'] ?? '') === $client['id'], 'modern update cannot override uuid');
updAssert(($mb['subId'] ?? '') === $client['subId'], 'modern update cannot override subId');
updAssert(($mb['flow'] ?? '') === $client['flow'], 'modern update keeps flow');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 11) Preflight GET failure -> no POST.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function() use (&$calls){
    $calls[] = 'blocked';
    return ['success' => true];
};
$GLOBALS['xuiTestReloadClientForPlanVerify'] = static fn() => null;
$r11 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(empty($r11['ok']) && ($r11['code'] ?? '') === 'client_read_failed', 'failed preflight GET stops renew');
updAssert(count($calls) === 0, 'no POST when preflight read fails');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 12) Preflight identity mismatch -> no POST.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function() use (&$calls){
    $calls[] = 'blocked';
    return ['success' => true];
};
setReloadSequence([
    static function(){
        $c = liveClient(90, 0, 1700000000000);
        $c['id'] = '00000000-0000-0000-0000-000000000001';
        return $c;
    },
]);
$r12 = xuiPushClientPlanUpdate($server, $client, ['totalGB' => xuiGbToBytes(10)], [
    'mode' => 'absolute',
    'gb' => 10,
    'days' => 30,
    'anchor_ms' => $anchor,
    'require_enable' => true,
]);
updAssert(empty($r12['ok']) && ($r12['code'] ?? '') === 'client_identity_mismatch', 'preflight identity mismatch blocks POST');
updAssert(count($calls) === 0, 'no POST on preflight identity mismatch');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 13) Preflight incomplete client record -> no POST.
$calls = [];
$GLOBALS['xuiTestApiRequest'] = static function() use (&$calls){
    $calls[] = 'blocked';
    return ['success' => true];
};
setReloadSequence([
    static function(){
        return [
            'email' => '',
            'subId' => 'sub1234567890abcd',
            'totalGB' => xuiGbToBytes(90),
            'enable' => true,
        ];
    },
]);
$r13 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(empty($r13['ok']) && ($r13['code'] ?? '') === 'client_read_incomplete', 'incomplete preflight read blocks POST');
updAssert(count($calls) === 0, 'no POST when preflight data incomplete');
unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestReloadClientForPlanVerify']);

// 14) Failed clients/get must not reuse stale local row as verified live (no POST).
$postCalls = [];
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$postCalls){
    if(strtoupper($method) === 'GET' && strpos($path, '/panel/api/clients/get/') === 0){
        return ['success' => false, 'msg' => 'خطا در ارتباط با 3x-ui: Connection refused', 'http' => 0];
    }

    $postCalls[] = $path;
    return ['success' => true];
};
unset($GLOBALS['xuiTestReloadClientForPlanVerify']);
$r14 = xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
updAssert(empty($r14['ok']) && ($r14['code'] ?? '') === 'client_read_failed', 'GET failure with stale local client blocks renew');
updAssert(count($postCalls) === 0, 'no POST when GET fails despite cached client row');
unset($GLOBALS['xuiTestApiRequest']);

echo $failed ? "$failed failed\n" : "All xui update client tests passed\n";
exit($failed ? 1 : 0);

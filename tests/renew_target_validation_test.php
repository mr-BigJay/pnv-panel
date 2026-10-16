<?php

/**
 * Acceptance-oriented tests for renew target validation (A–F where offline-safe).
 * Run: php tests/renew_target_validation_test.php
 */

require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../xui_lib.php';

$fail = 0;

function renewValAssert($cond, $msg){
    global $fail;
    if($cond){
        echo "OK  {$msg}\n";
    }
    else{
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

$notFoundMsg = xuiRenewTargetUserError('not_found');
renewValAssert(
    strpos($notFoundMsg, 'اشتراک جدید') !== false,
    'B: user-friendly not_found message'
);
renewValAssert(
    strpos($notFoundMsg, 'Sub ID') === false && strpos($notFoundMsg, 'کاربر با') === false,
    'B: no technical Sub ID error text'
);

$apiMsg = xuiRenewTargetUserError('api_error');
renewValAssert(strpos($apiMsg, 'حذف') === false, 'D: api_error does not say deleted');

$empty = xuiValidateRenewTarget('');
renewValAssert(empty($empty['ok']) && ($empty['reason'] ?? '') === 'invalid_link', 'invalid: empty link');

$garbage = xuiValidateRenewTarget('!!!');
renewValAssert(empty($garbage['ok']) && ($garbage['reason'] ?? '') === 'invalid_link', 'invalid: garbage link');

$offlineConfig = [
    'sub_port' => 2096,
    'renew_server_ids' => [],
    'servers' => [
        [
            'id' => 'renew-test-offline',
            'host' => 'renew-test-offline.local',
            'base_url' => 'http://127.0.0.1:1',
            'api_token' => 'offline-test-token',
        ],
    ],
];

$unknownHost = xuiValidateRenewTarget(
    'https://totally-unknown-host.example:2096/sub/abc1234567890ab',
    $offlineConfig
);
renewValAssert(
    empty($unknownHost['ok']) && ($unknownHost['reason'] ?? '') === 'server_not_found',
    'C: unknown host → server_not_found'
);

$apiDown = xuiValidateRenewTarget(
    'https://renew-test-offline.local:2096/sub/abc1234567890ab',
    $offlineConfig
);
renewValAssert(
    empty($apiDown['ok']) && ($apiDown['reason'] ?? '') === 'api_error',
    'D: unreachable API → api_error (not not_found)'
);
renewValAssert(
    ($apiDown['reason'] ?? '') !== 'not_found',
    'D: api_error distinct from not_found'
);

$src = file_get_contents(__DIR__ . '/../xui_lib.php');
$fnStart = strpos($src, 'function xuiValidateRenewTarget');
$fnEnd = strpos($src, 'function xuiEnsureClientUuidForLegacy', $fnStart);
$fnBody = $fnStart !== false && $fnEnd !== false ? substr($src, $fnStart, $fnEnd - $fnStart) : '';
renewValAssert(
    $fnBody !== '' && strpos($fnBody, 'xuiFindClientBySubId') !== false,
    'E: validates by Sub ID lookup'
);
renewValAssert(
    $fnBody !== '' && !preg_match('/FindClientBy(?:User|Email|Username)/i', $fnBody),
    'E: no username/email client fallback in validator'
);

$apiSrc = file_get_contents(__DIR__ . '/../instant-pay-api.php');
$createPos = strpos($apiSrc, "if(\$action === 'create'");
$createBlock = $createPos !== false ? substr($apiSrc, $createPos, 4000) : '';
$renewPos = strpos($createBlock, "if(\$typeNorm === 'تمدید')");
$couponPos = strpos($createBlock, 'if($hasCoupon)');
renewValAssert(
    $renewPos !== false && $couponPos !== false && $renewPos < $couponPos,
    'F: renew validation runs before coupon block'
);
$instantPos = strpos($createBlock, 'instantPayCreate');
renewValAssert(
    $renewPos !== false && $instantPos !== false && $renewPos < $instantPos,
    'B: renew validation before instantPayCreate (no payment row)'
);

if($fail > 0){
    fwrite(STDERR, "\n{$fail} test(s) failed\n");
    exit(1);
}

echo "\nAll renew target validation tests passed\n";

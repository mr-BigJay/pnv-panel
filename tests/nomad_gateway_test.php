<?php

$fail = 0;

function nomadAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmp = sys_get_temp_dir() . '/pnv_nomad_' . getmypid();
@mkdir($tmp, 0777, true);
putenv('PNV_NOMAD_CONFIG_PATH=' . $tmp . '/nomad.json');
putenv('PNV_NOMAD_INBOX_PATH=' . $tmp . '/inbox.json');
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $tmp . '/ip.json');
putenv('PNV_XUI_CONFIG_PATH=' . $tmp . '/xui.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $tmp . '/p.csv');

require_once __DIR__ . '/../bale_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';
require_once __DIR__ . '/../nomad_lib.php';

file_put_contents(getenv('PNV_XUI_CONFIG_PATH'), json_encode([
    'enabled' => false,
    'servers' => [],
], JSON_UNESCAPED_UNICODE));

$sample = "پست بانک\nواريز به کارت: 6156\n+1,495,160\n1405/07/29\n12:56\nمانده: 44,557,909 ريال";

$fields = nomadParsePostBankSmsFields($sample);
nomadAssert(($fields['card_last4'] ?? '') === '6156', 'card last4 parsed');
nomadAssert(in_array(1495160, $fields['amounts'] ?? [], true), 'deposit amount not balance');
nomadAssert(intval($fields['deposit_ts'] ?? 0) > 0, 'jalali date/time to timestamp');

$balanceOnly = baleExtractRialAmounts("مانده: 44,557,909 ريال");
nomadAssert(!in_array(44557909, $balanceOnly, true) || count($balanceOnly) === 0, 'balance line excluded when alone');

$now = time();

$gateway = nomadNormalizeGatewayPayload([
    'from' => '50001234567',
    'text' => $sample,
    'sentStamp' => ($now - 45) * 1000,
    'receivedStamp' => ($now - 30) * 1000,
    'sim' => 1,
    'message_id' => 'uniq_dry_1',
]);
nomadAssert(is_array($gateway) && ($gateway['text'] ?? '') !== '', 'nomad payload normalized');
nomadAssert(intval($gateway['deposit_ts'] ?? 0) > 0, 'gateway stamp converted');

nomadSaveConfig([
    'enabled' => true,
    'dry_run' => true,
    'webhook_token' => 'test_secret_token',
    'allowed_senders' => [],
    'accept_unknown_sender' => true,
    'max_inbox_entries' => 100,
]);

$amount = 1495160;
$code = $amount % 10000;
$order = [
    'id' => 'ord_test_1',
    'user' => 'u1',
    'card' => '6277601422986156',
    'amount' => $amount,
    'base_amount' => 149000000,
    'currency' => 'rial',
    'code' => $code,
    'status' => 'waiting',
    'created_at' => $now - 120,
    'expires_at' => $now + 1800,
    'type' => 'خرید',
];
instantPaySave([$order]);

$parse = nomadParsePostBankSmsFields($sample);
$parse['amount_candidates'] = instantPayExpandAmountCandidates($parse['amounts'], ['rial_only' => true]);
$parse['deposit_ts'] = $now - 60;

$match = instantPayFindNomadOrderMatches($parse);
nomadAssert(($match['status'] ?? '') === 'matched', 'order matched by card+amount+time');
nomadAssert(($match['match']['id'] ?? '') === 'ord_test_1', 'correct order id');

$r1 = nomadProcessInboundSms($gateway, nomadLoadConfig());
nomadAssert(!empty($r1['ok']) && ($r1['match_status'] ?? '') === 'dry_run', 'webhook flow dry_run stores match');

$r2 = nomadProcessInboundSms($gateway, nomadLoadConfig());
nomadAssert(!empty($r2['duplicate']), 'duplicate sms detected');

$inbox = nomadLoadInbox();
nomadAssert(count($inbox) >= 2, 'inbox persisted');

$applyBlocked = nomadApplyInboxMatch($inbox[0]['id'] ?? '', nomadLoadConfig());
nomadAssert(empty($applyBlocked['ok']), 'apply blocked while dry_run');

nomadSaveConfig(array_merge(nomadLoadConfig(), ['dry_run' => false]));
$sampleLive = str_replace('12:56', '12:57', $sample);
$gatewayFresh = nomadNormalizeGatewayPayload([
    'from' => '50001234567',
    'text' => $sampleLive,
    'receivedStamp' => ($now - 20) * 1000,
    'sim' => 1,
    'message_id' => 'uniq_live_1',
]);
$applyStillNeedsAdmin = nomadProcessInboundSms($gatewayFresh, array_merge(nomadLoadConfig(), ['dry_run' => false]));
nomadAssert(($applyStillNeedsAdmin['match_status'] ?? '') === 'matched', 'live mode still no auto apply from process');
nomadAssert(empty($applyStillNeedsAdmin['paid']), 'process never marks paid directly');

if($fail > 0){
    echo "\n{$fail} nomad test(s) failed\n";
    exit(1);
}

echo "All nomad gateway tests passed\n";

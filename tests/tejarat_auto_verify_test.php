<?php

/**
 * Tejarat auto-verify (mock provider) — بدون اتصال بانک
 * php tests/tejarat_auto_verify_test.php
 */

$fail = 0;

function tvAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/pnv_tejarat_' . getmypid();
mkdir($tmpDir, 0777, true);

$configPath = $tmpDir . '/tejarat_verify.json';
$statePath = $tmpDir . '/tejarat_state.json';
$jsonPath = $tmpDir . '/instant.json';
$usedPath = $tmpDir . '/used.json';
$csvPath = $tmpDir . '/payments.csv';
$locksDir = $tmpDir . '/locks';
mkdir($locksDir, 0777, true);

putenv('PNV_TEJARAT_VERIFY_CONFIG_PATH=' . $configPath);
putenv('PNV_TEJARAT_VERIFY_STATE_PATH=' . $statePath);
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $jsonPath);
putenv('PNV_USED_DEPOSITS_PATH=' . $usedPath);
putenv('PNV_PAYMENTS_CSV_PATH=' . $csvPath);
putenv('PNV_LOCKS_DIR=' . $locksDir);
putenv('PNV_TEJARAT_MOCK_FORCE_ERROR');
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON');

require_once __DIR__ . '/../tejarat_auto_verify_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';

$tejaratCard = '5859831111222233';
$postCard = '6277601422986156';
$code = 7890;
$amount = 1497890;
$now = time();

function tvWriteConfig($path, array $extra = []){
    global $tejaratCard;
    $cfg = array_merge(tejaratVerifyDefaultConfig(), [
        'enabled' => true,
        'provider' => 'mock',
        'dry_run' => true,
        'destination_cards' => [$tejaratCard],
    ], $extra);
    file_put_contents($path, json_encode($cfg, JSON_UNESCAPED_UNICODE));
}

function tvSeedOrder($id, $card, $bank, $amount, $code, $status = 'waiting', $expiresAt = null){
    global $now;
    $expiresAt = $expiresAt ?? ($now + 1800);
    $items = instantPayLoad();
    $items[] = [
        'id' => $id,
        'user' => 'buyer',
        'type' => 'خرید',
        'status' => $status,
        'amount' => $amount,
        'currency' => 'rial',
        'code' => $code,
        'card' => $card,
        'card_name' => 'تجارت - تست',
        'card_bank' => $bank,
        'created_at' => $now,
        'expires_at' => $expiresAt,
        'csv_index' => 0,
    ];
    instantPaySave($items);
}

// --- no transactions ---
tvWriteConfig($configPath);
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([]));
$res = tejaratVerifyPollOnce();
tvAssert(!empty($res['ok']) && intval($res['transactions'] ?? -1) === 0, 'empty mock transaction list');

// --- valid deposit dry-run ---
tvSeedOrder('ord-valid', $tejaratCard, 'tejarat', $amount, $code);
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([[
    'amount' => $amount,
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TJ-001',
    'occurred_at' => $now + 5,
]]));
$res = tejaratVerifyPollOnce();
tvAssert(!empty($res['ok']) && !empty($res['results'][0]['dry_run']), 'valid deposit dry-run match');

// --- wrong amount ---
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([[
    'amount' => $amount + 10,
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TJ-002',
    'occurred_at' => $now + 5,
]]));
$res = tejaratVerifyPollOnce();
tvAssert(empty($res['results'][0]['ok']), 'wrong amount does not confirm');

// --- two cards same amount ---
instantPaySave([]);
tvSeedOrder('ord-post', $postCard, 'post', $amount, $code);
tvSeedOrder('ord-tej', $tejaratCard, 'tejarat', $amount, $code);
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([[
    'amount' => $amount,
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TJ-003',
    'occurred_at' => $now + 5,
]]));
$res = tejaratVerifyPollOnce();
tvAssert(!empty($res['results'][0]['ok']), 'tejarat tx confirms tejarat order in dry-run');

// --- duplicate tx ---
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([[
    'amount' => $amount,
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TJ-DUP',
    'occurred_at' => $now + 5,
]]));
$meta = tejaratVerifyBuildDepositMeta([
    'amount' => $amount,
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TJ-DUP',
    'occurred_at' => $now + 5,
], tejaratVerifyLoadConfig());
instantPayMarkDepositUsed(instantPayDepositUsageKey(array_merge($meta, ['amount' => $amount])), 'ord-tej', $meta);
$res = tejaratVerifyPollOnce();
tvAssert(empty($res['results'][0]['ok']) && ($res['results'][0]['code'] ?? '') === 'deposit_already_used', 'duplicate transaction rejected');

// --- expired order ---
instantPaySave([]);
tvSeedOrder('ord-exp', $tejaratCard, 'tejarat', $amount, $code, 'waiting', $now - 99999);
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([[
    'amount' => $amount,
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TJ-EXP',
    'occurred_at' => $now + 5,
]]));
$res = tejaratVerifyPollOnce();
tvAssert(empty($res['results'][0]['ok']), 'expired order not confirmed');

// --- connection error ---
putenv('PNV_TEJARAT_MOCK_FORCE_ERROR=bank timeout');
$res = tejaratVerifyPollOnce();
tvAssert(empty($res['ok']), 'connection error does not report poll ok');
$state = tejaratVerifyLoadState();
tvAssert(($state['connection_status'] ?? '') === 'error', 'state records connection error');

putenv('PNV_TEJARAT_MOCK_FORCE_ERROR');
putenv('PNV_TEJARAT_MOCK_TRANSACTIONS_JSON=' . json_encode([]));

// --- api stub when enabled ---
tvWriteConfig($configPath, ['provider' => 'api_stub', 'dry_run' => true]);
$res = tejaratVerifyPollOnce();
tvAssert(empty($res['ok']) && ($res['code'] ?? '') === 'api_not_configured', 'api_stub does not fake success');

// --- disabled module ---
tvWriteConfig($configPath, ['enabled' => false]);
$res = tejaratVerifyPollOnce();
tvAssert(!empty($res['skipped']), 'disabled module skips poll');

// --- serialized apply under store lock: usage blocks second attempt ---
tvWriteConfig($configPath, ['enabled' => true, 'provider' => 'mock', 'dry_run' => true]);
instantPaySave([]);
tvSeedOrder('ord-race', $tejaratCard, 'tejarat', $amount, $code);
$metaRace = tejaratVerifyBuildDepositMeta([
    'amount' => $amount,
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TJ-RACE',
    'occurred_at' => $now + 5,
], tejaratVerifyLoadConfig());
$dry = instantPayTryDepositMatch($amount, $metaRace, ['dry_run' => true, 'apply' => false]);
tvAssert(!empty($dry['ok']), 'race: dry-run match succeeds');
instantPayMarkDepositUsed(
    instantPayDepositUsageKey(array_merge($metaRace, ['amount' => $amount])),
    'ord-race',
    $metaRace
);
$blocked = instantPayTryDepositMatch($amount, $metaRace, ['apply' => true]);
tvAssert(empty($blocked['ok']) && ($blocked['code'] ?? '') === 'deposit_already_used', 'race: second apply blocked after usage recorded');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

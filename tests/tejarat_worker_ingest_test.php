<?php

/**
 * Worker ingest + browser_worker provider (ایزوله)
 * php tests/tejarat_worker_ingest_test.php
 */

$fail = 0;

function twAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/pnv_twj_' . getmypid();
mkdir($tmpDir, 0777, true);

putenv('PNV_TEJARAT_VERIFY_CONFIG_PATH=' . $tmpDir . '/tejarat_verify.json');
putenv('PNV_TEJARAT_VERIFY_STATE_PATH=' . $tmpDir . '/tejarat_state.json');
putenv('PNV_TEJARAT_WORKER_INBOX_PATH=' . $tmpDir . '/inbox.json');
putenv('PNV_TEJARAT_WORKER_HEALTH_PATH=' . $tmpDir . '/health.json');
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $tmpDir . '/instant.json');
putenv('PNV_USED_DEPOSITS_PATH=' . $tmpDir . '/used.json');
putenv('PNV_LOCKS_DIR=' . $tmpDir . '/locks');
mkdir(getenv('PNV_LOCKS_DIR'), 0777, true);

require_once __DIR__ . '/../tejarat_auto_verify_lib.php';
require_once __DIR__ . '/../tejarat_worker_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';

$secret = 'test-ingest-secret-32chars-min';
$card = '5859831111222233';
$amount = 1497890;
$code = 7890;
$now = time();

function twWriteConfig(array $extra = []){
    global $tmpDir, $secret, $card;
    $cfg = array_merge(tejaratVerifyDefaultConfig(), [
        'enabled' => true,
        'provider' => 'browser_worker',
        'dry_run' => true,
        'destination_cards' => [$card],
        'worker_ingest_secret' => $secret,
        'worker_heartbeat_max_age_seconds' => 300,
    ], $extra);
    file_put_contents(getenv('PNV_TEJARAT_VERIFY_CONFIG_PATH'), json_encode($cfg, JSON_UNESCAPED_UNICODE));
}

function twSign($body){
    global $secret;
    return hash_hmac('sha256', $body, $secret);
}

function twIngest(array $payload){
    global $secret;
    $cfg = tejaratVerifyLoadConfig();
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if(!tejaratWorkerVerifySignature($body, twSign($body), $cfg)){
        return ['ok' => false, 'code' => 'bad_sig'];
    }
    return tejaratWorkerHandleIngestPayload($payload, $cfg);
}

twWriteConfig();
twAssert(!tejaratWorkerVerifySignature('{}', 'bad', tejaratVerifyLoadConfig()), '1: bad signature rejected');

$validTx = [
    'amount' => $amount,
    'destination_card' => $card,
    'transaction_id' => 'TJ-W1',
    'occurred_at' => $now + 1,
    'direction' => 'credit',
];
$res = twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now, 'worker_version' => 'test'],
    'transactions' => [$validTx],
]);
twAssert(!empty($res['ok']), '1: valid incoming credit ingested');

// session stale → fetch blocked
file_put_contents(getenv('PNV_TEJARAT_WORKER_HEALTH_PATH'), json_encode([
    'updated_at' => $now - 9999,
    'session_status' => 'logged_in',
    'last_fetch_ok_at' => $now - 9999,
    'last_error' => '',
    'stats' => ['pushes' => 1, 'transactions_received' => 1],
], JSON_UNESCAPED_UNICODE));
$stale = tejaratVerifyFetchTransactions(tejaratVerifyLoadConfig());
twAssert(empty($stale['ok']) && ($stale['code'] ?? '') === 'session_stale', '6: stale heartbeat blocks fetch');

// refresh health
twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now],
    'transactions' => [],
]);

// login required
twIngest([
    'health' => ['session_status' => 'login_required', 'last_error' => 'expired'],
    'transactions' => [],
]);
$loginReq = tejaratVerifyFetchTransactions(tejaratVerifyLoadConfig());
twAssert(empty($loginReq['ok']) && ($loginReq['code'] ?? '') === 'session_login_required', '6b: login_required blocks auto verify');

twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now],
    'transactions' => [$validTx],
]);

// 2: exact amount match dry-run
instantPaySave([]);
$items = [[
    'id' => 'ord-1',
    'user' => 'u',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => $amount,
    'currency' => 'rial',
    'code' => $code,
    'card' => $card,
    'card_bank' => 'tejarat',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 0,
]];
instantPaySave($items);
$poll = tejaratVerifyPollOnce();
twAssert(!empty($poll['ok']) && !empty($poll['results'][0]['dry_run']), '2: exact amount dry-run match');

// 3: two orders same amount — tejarat card scoped
instantPaySave([]);
instantPaySave([
    [
        'id' => 'ord-a',
        'user' => 'u',
        'type' => 'خرید',
        'status' => 'waiting',
        'amount' => $amount,
        'currency' => 'rial',
        'code' => $code,
        'card' => '6277601422986156',
        'card_bank' => 'post',
        'created_at' => $now,
        'expires_at' => $now + 1800,
        'csv_index' => 0,
    ],
    [
        'id' => 'ord-b',
        'user' => 'u',
        'type' => 'خرید',
        'status' => 'waiting',
        'amount' => $amount,
        'currency' => 'rial',
        'code' => $code,
        'card' => $card,
        'card_bank' => 'tejarat',
        'created_at' => $now,
        'expires_at' => $now + 1800,
        'csv_index' => 1,
    ],
]);
twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now],
    'transactions' => [[
        'amount' => $amount,
        'destination_card' => $card,
        'transaction_id' => 'TJ-AMBIG-ORD',
        'occurred_at' => $now + 2,
        'direction' => 'credit',
    ]],
]);
$poll2 = tejaratVerifyPollOnce();
twAssert(!empty($poll2['results'][0]['ok']), '3: same amount picks tejarat destination card');

// 4: duplicate ingest + poll
twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now],
    'transactions' => [[
        'amount' => $amount,
        'destination_card' => $card,
        'transaction_id' => 'TJ-DUP2',
        'occurred_at' => $now + 3,
        'direction' => 'credit',
    ]],
]);
instantPaySave([]);
instantPaySave([[
    'id' => 'ord-dup',
    'user' => 'u',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => $amount,
    'currency' => 'rial',
    'code' => $code,
    'card' => $card,
    'card_bank' => 'tejarat',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 0,
]]);
$p1 = tejaratVerifyPollOnce();
twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now],
    'transactions' => [[
        'amount' => $amount,
        'destination_card' => $card,
        'transaction_id' => 'TJ-DUP2',
        'occurred_at' => $now + 3,
        'direction' => 'credit',
    ]],
]);
$p2 = tejaratVerifyPollOnce();
twAssert(empty($p2['results'][0]['ok']) || ($p2['results'][0]['code'] ?? '') === 'deposit_already_used', '4: duplicate transaction not re-confirmed');

// 5: connection error path via poll fetch fail
twIngest(['health' => ['session_status' => 'error', 'last_error' => 'offline'], 'transactions' => []]);
$off = tejaratVerifyPollOnce();
twAssert(empty($off['ok']), '5: bank offline does not confirm payments');

// 10: payload without transaction_id must not enter inbox (no auto-confirm path)
$inboxBefore = count(tejaratWorkerLoadInbox()['transactions'] ?? []);
twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => time()],
    'transactions' => [[
        'amount' => $amount,
        'transaction_id' => '',
        'direction' => 'credit',
    ]],
]);
$inboxAfter = count(tejaratWorkerLoadInbox()['transactions'] ?? []);
twAssert($inboxAfter === $inboxBefore, '10: incomplete ingest not queued for auto match');

// 11: mark used blocks second apply
$meta = tejaratVerifyBuildDepositMeta([
    'amount' => $amount,
    'destination_card' => $card,
    'transaction_id' => 'TJ-BLOCK',
    'occurred_at' => $now + 4,
], tejaratVerifyLoadConfig());
$key = instantPayDepositUsageKey(array_merge($meta, ['amount' => $amount]));
instantPayMarkDepositUsed($key, 'ord-x', $meta);
$blocked = instantPayTryDepositMatch($amount, $meta, ['apply' => true]);
twAssert(empty($blocked['ok']) && ($blocked['code'] ?? '') === 'deposit_already_used', '11: no double provision');

// 9: parallel ingest merge same id single inbox row
twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now],
    'transactions' => [[
        'amount' => $amount,
        'destination_card' => $card,
        'transaction_id' => 'TJ-PAR',
        'occurred_at' => $now + 5,
        'direction' => 'credit',
    ]],
]);
twIngest([
    'health' => ['session_status' => 'logged_in', 'last_fetch_ok_at' => $now],
    'transactions' => [[
        'amount' => $amount,
        'destination_card' => $card,
        'transaction_id' => 'TJ-PAR',
        'occurred_at' => $now + 5,
        'direction' => 'credit',
    ]],
]);
$inbox = tejaratWorkerLoadInbox();
$par = 0;
foreach($inbox['transactions'] as $row){
    if(is_array($row) && ($row['transaction_id'] ?? '') === 'TJ-PAR'){
        $par++;
    }
}
twAssert($par === 1, '9: duplicate worker push dedupes inbox');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

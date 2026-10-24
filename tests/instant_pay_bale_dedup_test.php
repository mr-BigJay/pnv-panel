<?php

/**
 * Dedup پایدار واریز پست‌بانک (مستقل از message_id)
 * php tests/instant_pay_bale_dedup_test.php
 */

$fail = 0;

function baleDedupAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

require_once __DIR__ . '/../bale_lib.php';

$postSample = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/10\n9:47\nمانده: 44,108,899 ريال";
$amount = 998190;

$fp1 = baleBuildPostbankDepositUsageKey($postSample, $amount);
$fp2 = baleBuildPostbankDepositUsageKey($postSample, $amount);
baleDedupAssert(!empty($fp1['ok']) && ($fp1['key'] ?? '') === ($fp2['key'] ?? ''), '1-2: same notice → same bank usage key');

$tmpDir = sys_get_temp_dir() . '/pnv_bale_dedup_' . getmypid();
mkdir($tmpDir, 0777, true);
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $tmpDir . '/instant.json');
putenv('PNV_USED_DEPOSITS_PATH=' . $tmpDir . '/used.json');
putenv('PNV_LOCKS_DIR=' . $tmpDir . '/locks');
mkdir(getenv('PNV_LOCKS_DIR'), 0777, true);

require_once __DIR__ . '/../instant_pay_lib.php';

$postCard = '6277601422986156';
$tejaratCard = '5859831111222233';
$code = 8190;
$orderAmount = $amount;
$now = time();

function baleDedupSeed($id, $card, $bank, $amount){
    global $now;
    $items = instantPayLoad();
    $items[] = [
        'id' => $id,
        'user' => 'u',
        'type' => 'خرید',
        'status' => 'waiting',
        'amount' => $amount,
        'currency' => 'rial',
        'code' => 8190,
        'card' => $card,
        'card_bank' => $bank,
        'card_name' => 'test',
        'created_at' => $now,
        'expires_at' => $now + 1800,
        'csv_index' => 0,
    ];
    instantPaySave($items);
}

function baleDedupMeta($text, $chatId, $messageId, $amount){
    return instantPayEnrichBaleDepositMeta([
        'source' => 'bale',
        'text' => $text,
        'amount' => $amount,
        'bale_chat_id' => (string)$chatId,
        'bale_message_id' => (string)$messageId,
    ], $text);
}

// 1-3: double forward / two message_id / two chats → same key
$metaA = baleDedupMeta($postSample, '111', '9001', $amount);
$metaB = baleDedupMeta($postSample, '222', '9002', $amount);
$keyA = instantPayDepositUsageKey($metaA);
$keyB = instantPayDepositUsageKey($metaB);
baleDedupAssert($keyA !== '' && $keyA === $keyB, '1-3: different message/chat same deposit → one key');

instantPaySave([]);
baleDedupSeed('ord-post', $postCard, 'post', $orderAmount);
$dry1 = instantPayTryDepositMatch($orderAmount, $metaA, ['dry_run' => true, 'apply' => false]);
baleDedupAssert(!empty($dry1['ok']), 'postbank dry-run match for order amount');

instantPayMarkDepositUsed($keyA, 'ord-post', $metaA);
$dupForward = instantPayTryDepositMatch($orderAmount, $metaB, ['dry_run' => true, 'apply' => false]);
baleDedupAssert(empty($dupForward['ok']) && ($dupForward['code'] ?? '') === 'deposit_already_used', '1: re-forward blocked after first use');

// 4: two real deposits same amount but different time
$sampleB = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/10\n10:15\nمانده: 44,108,899 ريال";
$metaTime2 = baleDedupMeta($sampleB, '111', '9100', $amount);
baleDedupAssert(instantPayDepositUsageKey($metaTime2) !== $keyA, '4: same amount different time → different keys');

// 4b: bank reference wins over minute collision
$refSample = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/10\n9:47\nشماره پیگیری: 881122334455\nمانده: 44,108,899 ريال";
$refFp = baleBuildPostbankDepositUsageKey($refSample, $amount);
baleDedupAssert(!empty($refFp['ok']) && str_contains($refFp['key'] ?? '', '881122334455'), '4b: bank reference dedup key');

// 4c: same minute/amount without balance → manual review (ambiguous)
$ambig = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/10\n9:47";
$ambigFp = baleBuildPostbankDepositUsageKey($ambig, $amount);
baleDedupAssert(empty($ambigFp['ok']) && ($ambigFp['code'] ?? '') === 'ambiguous_bank_fingerprint', '4c: missing balance+seconds → ambiguous');
$metaAmbig = baleDedupMeta($ambig, '111', '9300', $amount);
$ambigApply = instantPayTryDepositMatch($amount, $metaAmbig, ['apply' => true]);
baleDedupAssert(empty($ambigApply['ok']) && ($ambigApply['code'] ?? '') === 'ambiguous_bank_fingerprint', '4c: apply blocked for ambiguous fingerprint');

// 5: two orders same amount different cards
instantPaySave([]);
baleDedupSeed('ord-post', $postCard, 'post', $orderAmount);
baleDedupSeed('ord-tej', $tejaratCard, 'tejarat', $orderAmount);
$hitPost = instantPayFindUniqueOrderForDeposit($orderAmount, $metaA);
baleDedupAssert(is_array($hitPost) && ($hitPost['id'] ?? '') === 'ord-post', '5: card-scoped match post order');

// 6: crash after claim before activation → release on failure
instantPaySave([[
    'id' => 'ord-crash',
    'user' => 'u',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => $orderAmount,
    'currency' => 'rial',
    'code' => 8190,
    'card' => $postCard,
    'card_bank' => 'post',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 99,
]]);
$crashSample = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/11\n8:30\nمانده: 44,108,899 ريال";
$metaCrash = baleDedupMeta($crashSample, '111', '9200', $amount);
$keyCrash = instantPayDepositUsageKey($metaCrash);
baleDedupAssert($keyCrash !== '', '6: crash usage key present');
$applied = instantPayApplyDepositMatchWithClaim(
    $keyCrash,
    'ord-crash',
    $metaCrash,
    static function(){
        return ['ok' => false, 'error' => 'simulated activation failure'];
    }
);
baleDedupAssert(empty($applied['ok']), '6: simulated activation failure returns not ok');
$claimRow = instantPayFindDepositClaimRow($keyCrash);
baleDedupAssert($claimRow === null, '6: claim released after failed activation');
$claimAgain = instantPayClaimDepositUsage($keyCrash, 'ord-crash', $metaCrash);
baleDedupAssert(!empty($claimAgain['ok']), '6: claim can be re-acquired after release');

// 7-8: complete claim then retry apply must not double provision
instantPaySave([[
    'id' => 'ord-done',
    'user' => 'u',
    'type' => 'خرید',
    'status' => 'payment_received_provision_failed',
    'amount' => $orderAmount,
    'currency' => 'rial',
    'code' => 8190,
    'card' => $postCard,
    'card_bank' => 'post',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 1,
]]);
$keyDone = instantPayDepositUsageKey($metaTime2);
instantPayCompleteDepositClaim($keyDone, 'ord-done');
baleDedupAssert(instantPayIsDepositUsed($keyDone, 'ord-done'), '7-8: completed claim blocks reuse');
instantPaySave([[
    'id' => 'ord-wait2',
    'user' => 'u',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => $orderAmount,
    'currency' => 'rial',
    'code' => 8190,
    'card' => $postCard,
    'card_bank' => 'post',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 2,
]]);
$retry = instantPayTryDepositMatch($orderAmount, $metaTime2, ['apply' => true]);
baleDedupAssert(empty($retry['ok']) && ($retry['code'] ?? '') === 'deposit_already_used', '7-8: duplicate apply rejected');

// 9: multiprocess parallel claim+stub activation — only one worker wins
if(function_exists('pcntl_fork')){
    $raceSample = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/12\n11:22\nمانده: 44,200,000 ريال";
    $metaRace = baleDedupMeta($raceSample, '111', '9400', $amount);
    $keyRace = instantPayDepositUsageKey($metaRace);
    $childPath = $tmpDir . '/race_child.json';
    @unlink($childPath);
    $stubOk = static function(){
        return ['ok' => true, 'item' => ['id' => 'ord-race', 'status' => 'paid']];
    };
    $child = pcntl_fork();
    if($child === 0){
        $r = instantPayApplyDepositMatchWithClaim($keyRace, 'ord-race-a', $metaRace, $stubOk);
        file_put_contents($childPath, json_encode(['ok' => !empty($r['ok']), 'code' => $r['code'] ?? '']));
        exit(0);
    }
    $parent = instantPayApplyDepositMatchWithClaim($keyRace, 'ord-race-b', $metaRace, $stubOk);
    pcntl_waitpid($child, $status);
    $childOut = json_decode(@file_get_contents($childPath) ?: '{}', true);
    $wins = intval(!empty($parent['ok'])) + intval(!empty($childOut['ok']));
    baleDedupAssert($wins === 1, '9: parallel claim exactly one success');
    $loserCode = empty($parent['ok']) ? ($parent['code'] ?? '') : ($childOut['code'] ?? '');
    baleDedupAssert($loserCode === 'deposit_already_used', '9: loser blocked as deposit_already_used');
}

// 10: restart simulation — processing claim same order resumes without double complete
instantPaySave([[
    'id' => 'ord-restart',
    'user' => 'u',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => $orderAmount,
    'currency' => 'rial',
    'code' => 8190,
    'card' => $postCard,
    'card_bank' => 'post',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 51,
]]);
$restartSample = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/13\n7:01\nمانده: 44,300,000 ريال";
$metaRestart = baleDedupMeta($restartSample, '111', '9500', $amount);
$keyRestart = instantPayDepositUsageKey($metaRestart);
instantPayClaimDepositUsage($keyRestart, 'ord-restart', $metaRestart);
$resumeClaim = instantPayClaimDepositUsage($keyRestart, 'ord-restart', $metaRestart);
baleDedupAssert(!empty($resumeClaim['ok']) && !empty($resumeClaim['resume']), '10: worker restart resumes same-order claim');
instantPayCompleteDepositClaim($keyRestart, 'ord-restart');
$afterComplete = instantPayTryDepositMatch($orderAmount, $metaRestart, ['apply' => true]);
baleDedupAssert(empty($afterComplete['ok']) && ($afterComplete['code'] ?? '') === 'deposit_already_used', '10: after complete restart cannot re-provision');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

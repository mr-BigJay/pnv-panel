<?php

/**
 * تطبیق واریز با کارت/بانک مقصد — جلوگیری از تأیید cross-card
 * php tests/instant_pay_card_match_test.php
 */

$fail = 0;

function cardMatchAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmpDir = sys_get_temp_dir() . '/pnv_card_match_' . getmypid();
mkdir($tmpDir, 0777, true);
$jsonPath = $tmpDir . '/instant.json';
$usedPath = $tmpDir . '/used.json';
$locksDir = $tmpDir . '/locks';
mkdir($locksDir, 0777, true);

putenv('PNV_INSTANT_PAYMENTS_PATH=' . $jsonPath);
putenv('PNV_USED_DEPOSITS_PATH=' . $usedPath);
putenv('PNV_LOCKS_DIR=' . $locksDir);

require_once __DIR__ . '/../instant_pay_lib.php';

$postCard = '6277601422986156';
$tejaratCard = '5859831234567890';
$code = 4560;
$amount = 1500000 - (1500000 % 10000) + $code; // build-like amount with last4
$amount = 1494560;
$now = time();

function cardMatchSaveWaiting($id, $card, $bank, $amount, $code, $created){
    $items = instantPayLoad();
    $items[] = [
        'id' => $id,
        'user' => 'u1',
        'type' => 'خرید',
        'status' => 'waiting',
        'amount' => $amount,
        'currency' => 'rial',
        'code' => $code,
        'card' => $card,
        'card_name' => $bank === 'post' ? 'پست بانک - تست' : 'تجارت - تست',
        'card_bank' => $bank,
        'created_at' => $created,
        'expires_at' => $created + 1800,
        'csv_index' => 0,
    ];
    instantPaySave($items);
}

// دو سفارش با مبلغ یکسان روی دو کارت
cardMatchSaveWaiting('ord-post', $postCard, 'post', $amount, $code, $now);
cardMatchSaveWaiting('ord-tej', $tejaratCard, 'tejarat', $amount, $code, $now - 1);

$hitPost = instantPayFindUniqueOrderForDeposit($amount, [
    'source' => 'tejarat',
    'bank_id' => 'tejarat',
    'destination_card' => $postCard,
]);
cardMatchAssert($hitPost === null, 'tejarat deposit to post card number does not match tejarat order');

$hitTej = instantPayFindUniqueOrderForDeposit($amount, [
    'source' => 'tejarat',
    'bank_id' => 'tejarat',
    'destination_card' => $tejaratCard,
]);
cardMatchAssert(is_array($hitTej) && ($hitTej['id'] ?? '') === 'ord-tej', 'tejarat deposit matches tejarat order only');

$postOnly = instantPayFindUniqueOrderForDeposit($amount, [
    'source' => 'bale',
    'bank_id' => 'post',
    'destination_card' => '6156',
    'text' => "پست بانک\nواريز به کارت: 6156\n+998,190",
]);
cardMatchAssert(is_array($postOnly) && ($postOnly['id'] ?? '') === 'ord-post', 'postbank bale hint matches post order');

$baleNoProof = instantPayFindUniqueOrderForDeposit($amount, [
    'source' => 'bale',
    'text' => 'واریز مبلغ',
]);
cardMatchAssert($baleNoProof === null, 'bale without bank/card proof does not match by amount alone');

$baleWrongBank = instantPayFindUniqueOrderForDeposit($amount, [
    'source' => 'bale',
    'bank_id' => 'tejarat',
    'destination_card' => $tejaratCard,
]);
cardMatchAssert(is_array($baleWrongBank) && ($baleWrongBank['id'] ?? '') === 'ord-tej', 'bale with tejarat proof matches tejarat only');

// مبلغ تنها کافی نیست وقتی دو کارت فعال‌اند
$ambiguous = instantPayFindUniqueOrderForDeposit($amount, [
    'source' => 'unknown',
]);
cardMatchAssert($ambiguous === null, 'amount-only without card is ambiguous → no match');

// تراکنش قدیمی‌تر از سفارش
$items = instantPayLoad();
$items[] = [
    'id' => 'ord-old',
    'user' => 'u2',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => 2004560,
    'currency' => 'rial',
    'code' => 4560,
    'card' => $tejaratCard,
    'card_bank' => 'tejarat',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 1,
];
instantPaySave($items);
$oldTx = instantPayFindUniqueOrderForDeposit(2004560, [
    'source' => 'tejarat',
    'bank_id' => 'tejarat',
    'destination_card' => $tejaratCard,
    'occurred_at' => $now - 3600,
]);
cardMatchAssert($oldTx === null, 'deposit before order created is rejected');

// dry-run + apply dedup
$items = instantPayLoad();
$items[] = [
    'id' => 'ord-dedup',
    'user' => 'u3',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => 3004560,
    'currency' => 'rial',
    'code' => 4560,
    'card' => $tejaratCard,
    'card_bank' => 'tejarat',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 2,
];
instantPaySave($items);

$meta = [
    'source' => 'tejarat',
    'bank_id' => 'tejarat',
    'destination_card' => $tejaratCard,
    'transaction_id' => 'TX-DEDUP-1',
    'occurred_at' => $now + 10,
];

instantPayMarkDepositUsed(instantPayDepositUsageKey(array_merge($meta, ['amount' => 3004560])), 'ord-dedup', $meta);
$again = instantPayTryDepositMatch(3004560, $meta, ['apply' => true, 'dry_run' => false]);
cardMatchAssert(empty($again['ok']) && ($again['code'] ?? '') === 'deposit_already_used', 'duplicate transaction id blocked');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

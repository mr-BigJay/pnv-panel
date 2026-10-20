<?php

/**
 * امنیت Webhook بله + regression فوروارد پست‌بانک
 * php tests/bale_webhook_security_test.php
 */

$fail = 0;

function baleSecAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

require_once __DIR__ . '/../bale_lib.php';

$postSample = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/10\n9:47\nمانده: 44,108,899 ريال";

$trustedForward = [
    'message_id' => 501,
    'forward_date' => 1700000000,
    'forward_from' => ['username' => 'postbank_bot', 'id' => 123, 'is_bot' => true],
    'text' => $postSample,
    'chat' => ['id' => '9001'],
];

$fakeCopy = [
    'message_id' => 502,
    'text' => $postSample,
    'chat' => ['id' => '9001'],
];

$fakeSender = [
    'message_id' => 503,
    'forward_date' => 1700000001,
    'forward_from' => ['username' => 'random_user', 'id' => 999, 'is_bot' => false],
    'text' => $postSample,
    'chat' => ['id' => '9001'],
];

$forgedPostbankUser = [
    'message_id' => 504,
    'forward_date' => 1700000002,
    'forward_from' => ['username' => 'postbank_bot', 'id' => 666, 'is_bot' => false],
    'text' => $postSample,
    'chat' => ['id' => '9001'],
];

baleSecAssert(baleMessageIsTrustedPostbankDeposit($trustedForward), 'trusted postbank forward accepted');
baleSecAssert(!baleMessageIsTrustedPostbankDeposit($fakeCopy), 'plain copied text rejected');
baleSecAssert(!baleMessageIsTrustedPostbankDeposit($fakeSender), 'forward from non-postbank rejected');
baleSecAssert(!baleMessageIsTrustedPostbankDeposit($forgedPostbankUser), 'forged postbank username without is_bot rejected');

$adminCfg = baleDefaultConfig();
$adminCfg['admin_chat_ids'] = '9001';

baleSecAssert(!empty(baleCanProcessPaymentDeposit($adminCfg, $trustedForward, '9001')['ok']), 'admin + trusted forward allowed');
baleSecAssert((baleCanProcessPaymentDeposit($adminCfg, $forgedPostbankUser, '9001')['code'] ?? '') === 'untrusted_deposit_source', 'forged forward blocked for payment');
baleSecAssert((baleCanProcessPaymentDeposit($adminCfg, $trustedForward, '9999')['code'] ?? '') === 'unauthorized_chat', 'wrong chat id blocked even with trusted forward');

// migration: secret exists but enforcement off → no header required
$pendingCfg = baleDefaultConfig();
$pendingCfg['webhook_secret'] = 'live-secret';
$pendingCfg['webhook_secret_pending'] = 'pending-secret';
$pendingCfg['webhook_secret_enforced'] = false;
$pendingCfg['webhook_secret_required'] = false;
unset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']);
$mig = baleVerifyWebhookRequest($pendingCfg);
baleSecAssert(!empty($mig['ok']) && !empty($mig['migration']), 'pending secret without enforce allows webhook (no sudden 401)');

$enforcedCfg = $pendingCfg;
$enforcedCfg['webhook_secret_enforced'] = true;
$enforcedCfg['webhook_secret_required'] = true;
baleSecAssert(baleVerifyWebhookRequest($enforcedCfg)['code'] === 'webhook_unauthorized', 'enforced mode rejects missing header');

$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'pending-secret';
baleSecAssert(!empty(baleVerifyWebhookRequest($enforcedCfg)['ok']), 'enforced accepts active/pending secret header');

$config = baleDefaultConfig();
$config['webhook_secret'] = 'test-secret-123';
$config['webhook_secret_enforced'] = true;
$config['webhook_secret_required'] = true;
$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'wrong';
baleSecAssert(baleVerifyWebhookRequest($config)['code'] === 'webhook_unauthorized', 'wrong secret rejected');

$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'test-secret-123';
baleSecAssert(!empty(baleVerifyWebhookRequest($config)['ok']), 'valid secret accepted');
unset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']);

baleSecAssert(!baleIsAdminChat('123', ['admin_chat_ids' => '']), 'empty admin list is not open');

// regression: enrich + match path (بدون webhook HTTP)
$tmpDir = sys_get_temp_dir() . '/pnv_bale_reg_' . getmypid();
mkdir($tmpDir, 0777, true);
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $tmpDir . '/instant.json');
putenv('PNV_USED_DEPOSITS_PATH=' . $tmpDir . '/used.json');
putenv('PNV_LOCKS_DIR=' . $tmpDir . '/locks');
mkdir(getenv('PNV_LOCKS_DIR'), 0777, true);

require_once __DIR__ . '/../instant_pay_lib.php';

$postCard = '6277601422986156';
$amount = 998190;
$now = time();
instantPaySave([[
    'id' => 'ord-pb',
    'user' => 'u',
    'type' => 'خرید',
    'status' => 'waiting',
    'amount' => $amount,
    'currency' => 'rial',
    'code' => 8190,
    'card' => $postCard,
    'card_bank' => 'post',
    'card_name' => 'پست بانک - تست',
    'created_at' => $now,
    'expires_at' => $now + 1800,
    'csv_index' => 0,
]]);

$depositMeta = instantPayEnrichBaleDepositMeta([
    'source' => 'bale',
    'text' => $postSample,
    'bale_chat_id' => '9001',
    'bale_message_id' => '501',
], $postSample);
$dry = instantPayTryDepositMatch($amount, $depositMeta, ['dry_run' => true, 'apply' => false]);
baleSecAssert(!empty($dry['ok']), 'postbank sample dry-run match');

$meta = instantPayEnrichBaleDepositMeta(['source' => 'bale', 'text' => $postSample], $postSample);
baleSecAssert(($meta['bank_id'] ?? '') === 'post', 'postbank bank_id inferred');
baleSecAssert(($meta['destination_card'] ?? '') === '6156', 'postbank card suffix extracted');

// prepare / activate helpers
$prepCfg = baleDefaultConfig();
$secret = balePrepareWebhookSecret($prepCfg);
baleSecAssert($secret !== '' && ($prepCfg['webhook_secret_pending'] ?? '') === $secret && empty($prepCfg['webhook_secret_enforced']), 'prepare secret keeps enforcement off');
baleSecAssert(baleActivateWebhookSecretEnforcement($prepCfg) && !empty($prepCfg['webhook_secret_enforced']), 'activate moves pending to active');
baleRollbackWebhookSecretEnforcement($prepCfg);
baleSecAssert(empty($prepCfg['webhook_secret_enforced']), 'rollback disables enforcement without clearing secret');

echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);

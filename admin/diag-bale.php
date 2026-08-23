<?php
/**
 * تشخیص ربات بله — بعد از رفع مشکل حذف کنید.
 * https://panel.ticketin.ir/bigjay_controller/diag-bale.php
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../bale_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';

$config = baleLoadConfig();
$webhookUrl = baleWebhookPublicUrl();

echo "=== bale diagnostics ===\n";
echo 'time: ' . date('c') . "\n";
echo 'php: ' . PHP_VERSION . "\n";
echo 'enabled: ' . (!empty($config['enabled']) ? 'yes' : 'no') . "\n";
echo 'has_token: ' . (trim((string)($config['bot_token'] ?? '')) !== '' ? 'yes' : 'no') . "\n";
echo 'admin_chat_ids: ' . ($config['admin_chat_ids'] ?? '') . "\n";
echo 'webhook_url: ' . $webhookUrl . "\n";

$me = baleGetMe($config);
echo 'getMe: ' . (!empty($me['ok']) ? 'OK @' . ($me['result']['username'] ?? '?') : ('FAIL ' . ($me['description'] ?? ''))) . "\n";

$info = baleGetWebhookInfo($config);
if(!empty($info['ok'])){
    $r = $info['result'] ?? [];
    echo 'webhook_live: ' . ($r['url'] ?? '—') . "\n";
    echo 'pending_updates: ' . intval($r['pending_update_count'] ?? 0) . "\n";
    $err = trim((string)($r['last_error_message'] ?? ''));
    if($err !== ''){
        echo 'last_error: ' . $err . "\n";
    }
}
else{
    echo 'webhook_info: FAIL ' . ($info['description'] ?? '') . "\n";
}

$sample = "پست بانک\nواريز به کارت: 6156\n+998,190\n1405/05/10\n9:47\nمانده: 44,108,899 ريال";
$amounts = baleExtractRialAmounts($sample);
echo 'parse_sample: ' . json_encode($amounts, JSON_UNESCAPED_UNICODE) . "\n";
echo 'ingest_file: ' . __DIR__ . '/../postbank-ingest.php exists=' . (is_file(__DIR__ . '/../postbank-ingest.php') ? 'yes' : 'no') . "\n";
echo 'ingest_secret: ' . (trim((string)($config['ingest_secret'] ?? '')) !== '' ? 'yes' : 'no') . "\n";
echo 'listener_env: ' . (is_file(__DIR__ . '/../db/postbank-listener.env') ? 'yes' : 'no') . "\n";
echo 'listener_session: ' . (is_file(__DIR__ . '/../db/bale_user_session.bale') ? 'yes' : 'no') . "\n";

$logPath = baleWebhookLogPath();
echo 'log_file: ' . $logPath . ' exists=' . (is_file($logPath) ? 'yes' : 'no') . "\n";

$tail = baleReadWebhookLogTail(8);
if(count($tail) > 0){
    echo "\n--- log tail ---\n";
    echo implode("\n", $tail) . "\n";
}

echo "\nRESULT: OK\n";

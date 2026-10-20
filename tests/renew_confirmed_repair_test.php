<?php
/**
 * Confirmed CSV renew: reserved ≠ activated; repair without duplicate apply when possible.
 */

$fail = 0;

function rcrAssert($ok, $msg){
    global $fail;
    echo ($ok ? 'OK  ' : 'FAIL ') . $msg . "\n";
    if(!$ok){
        $fail++;
    }
}

$tmp = sys_get_temp_dir() . '/pnv_rcr_' . getmypid();
@mkdir($tmp . '/locks', 0777, true);
@mkdir($tmp . '/renew_provisions', 0777, true);
putenv('PNV_PAYMENTS_CSV_PATH=' . $tmp . '/p.csv');
putenv('PNV_XUI_CONFIG_PATH=' . $tmp . '/c.json');
putenv('PNV_LOCKS_DIR=' . $tmp . '/locks');
putenv('PNV_RENEW_RESERVE_PATH=' . $tmp . '/r.json');
putenv('PNV_RENEW_PROVISION_DIR=' . $tmp . '/renew_provisions');

require_once __DIR__ . '/../xui_lib.php';
require_once __DIR__ . '/../renew_reserve_lib.php';

file_put_contents(getenv('PNV_XUI_CONFIG_PATH'), json_encode([
    'enabled' => true,
    'sub_port' => 2096,
    'renew_server_ids' => ['vip4'],
    'servers' => [['id' => 'vip4', 'host' => 'vip4.example.com', 'inbound_id' => 1, 'base_url' => 'https://t/', 'api_token' => 'x']],
], JSON_UNESCAPED_UNICODE));

$link = 'https://vip4.example.com:2096/sub/abc1234567890ab';
$row = ['ownerUser', $link, '50 گیگ - 30 روز', 'RCR-1', '', '', 'تایید شد', $link, 170200, 'تمدید', '', '', 1];
$fp = fopen(getenv('PNV_PAYMENTS_CSV_PATH'), 'w');
fputcsv($fp, $row);
fclose($fp);

$key = xuiPaymentRowProvisionKey($row);
xuiRenewProvisionStoreWrite($key, [
    'state' => 'done',
    'result' => [
        'ok' => true,
        'link' => $link,
        'renew_outcome' => 'reserved',
        'method' => 'renew_reserve',
    ],
]);

$again = xuiApprovePaymentIndex(0, 'تمدید');
rcrAssert(!empty($again['ok']) && ($again['renew_outcome'] ?? '') === 'reserved', 'confirmed CSV with reserved store is not treated as renewed');

xuiRenewProvisionStoreWrite($key, [
    'state' => 'done',
    'result' => [
        'ok' => true,
        'link' => $link,
        'renew_outcome' => 'renewed',
        'method' => 'absolute',
    ],
]);

$renewed = xuiApprovePaymentIndex(0, 'تمدید');
rcrAssert(!empty($renewed['ok']) && ($renewed['renew_outcome'] ?? '') === 'renewed', 'confirmed CSV with renewed store stays idempotent');

$row2 = ['ownerUser', $link, '50 گیگ - 30 روز', 'RCR-2', '', '', 'تایید شد', $link, 170201, 'تمدید', '', '', 1];
$fp2 = fopen(getenv('PNV_PAYMENTS_CSV_PATH'), 'w');
fputcsv($fp2, $row);
fputcsv($fp2, $row2);
fclose($fp2);

$key2 = xuiPaymentRowProvisionKey($row2);
$res = renewReserveCreateFromPayment($row2, ['host' => 'vip4.example.com', 'sub_id' => 'abc1234567890ab'], [
    'id' => 'vip4', 'host' => 'vip4.example.com', 'inbound_id' => 1,
], 50, 30, $row2[2]);

rcrAssert(is_array($res), 'reservation created for repair test');

$repair = xuiApprovePaymentIndex(1, 'تمدید');
rcrAssert(!empty($repair['ok']) && ($repair['renew_outcome'] ?? '') === 'reserved', 'confirmed CSV finds queue reservation without re-provision');

exit($fail > 0 ? 1 : 0);

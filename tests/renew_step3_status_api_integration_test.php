<?php
/**
 * Isolated GET status API regression after manual renewal approval.
 * Executes actual instant-pay-api.php handler in a CLI child process.
 * No XUI writes, web access, or real payment data.
 */
$errors = 0;
function step3ApiAssert($description, $condition){
    global $errors;
    echo ($condition ? "PASS " : "FAIL ") . $description . "\n";
    if(!$condition) $errors++;
}

$dir = sys_get_temp_dir() . '/pnv_step3_status_' . getmypid() . '_' . bin2hex(random_bytes(4));
mkdir($dir, 0700, true);
mkdir($dir . '/locks', 0700, true);
$jsonPath = $dir . '/orders.json';
$csvPath = $dir . '/payments.csv';
$locksDir = $dir . '/locks';
$src = realpath(__DIR__ . '/../instant-pay-api.php');
$now = time();

putenv('PNV_INSTANT_PAYMENTS_PATH=' . $jsonPath);
putenv('PNV_PAYMENTS_CSV_PATH=' . $csvPath);
putenv('PNV_LOCKS_DIR=' . $locksDir);
require_once __DIR__ . '/../instant_pay_lib.php';

$order = [
    'id' => 'test-step3-001', 'user' => 'step3_user', 'type' => 'تمدید',
    'sub' => 'https://vip3.boozhaan.ir/sub/demostep3',
    'plan' => '10 گیگ - نامحدود زمانی',
    'amount' => 1497070, 'code' => 7070, 'currency' => 'rial',
    'status' => 'waiting', 'created_at' => $now, 'expires_at' => $now + 1800,
];
$renewed = 'https://vip3.boozhaan.ir/sub/demostep3';
$row = [
    'step3_user', $order['sub'], $order['plan'], 'AUTO-7070',
    '', '', 'تایید شد', $renewed, $now, 'تمدید', '', '', 1497070, 7070,
];
$f = fopen($csvPath, 'w');
fputcsv($f, $row);
fclose($f);
file_put_contents($jsonPath, json_encode([$order], JSON_UNESCAPED_UNICODE));
$key = xuiPaymentRowProvisionKey($row);
$store = xuiRenewProvisionStorePath($key);
if(!is_dir(dirname($store))) mkdir(dirname($store), 0700, true);
file_put_contents($store, json_encode([
    'state'=>'done', 'transaction_id'=>xuiPaymentRowTransactionId($row),
    'result'=>['ok'=>true,'link'=>$renewed,'renew_outcome'=>'renewed'],
], JSON_UNESCAPED_UNICODE));

$runner = $dir . '/run_status.php';
$script = <<<'PHP'
<?php
session_save_path($argv[3]);
session_id('teststep3session'.getmypid());
session_start();
$_SESSION['user'] = $argv[1];
session_write_close();
$_GET = ['action'=>'status', 'id'=>$argv[2]];
require $argv[4];
PHP;
file_put_contents($runner, $script);

$fetch = static function($user, $id) use ($runner,$src,$dir,$jsonPath,$csvPath,$locksDir) {
    $command =
        'PNV_INSTANT_PAYMENTS_PATH=' . escapeshellarg($jsonPath)
        . ' PNV_PAYMENTS_CSV_PATH=' . escapeshellarg($csvPath)
        . ' PNV_LOCKS_DIR=' . escapeshellarg($locksDir)
        . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner)
        . ' ' . escapeshellarg($user)
        . ' ' . escapeshellarg($id)
        . ' ' . escapeshellarg($dir)
        . ' ' . escapeshellarg($src)
        . ' 2>&1';
    $out=[];
    $code=0;
    exec($command,$out,$code);
    return [$code,json_decode(implode("\n",$out),true),implode("\n",$out)];
};

[$status,$paid,$raw] = $fetch('step3_user','test-step3-001');
step3ApiAssert('authenticated user status returns JSON', $status === 0 && !empty($paid['ok']));
step3ApiAssert('verified manual approve returns paid on next status poll',
    ($paid['item']['status'] ?? '') === 'paid' && ($paid['item']['id'] ?? '') === 'test-step3-001');
step3ApiAssert('paid status contains renewed link', ($paid['item']['link'] ?? '') === $renewed);
$saved = instantPayLoad();
step3ApiAssert('paid state persisted for subsequent polls', ($saved[0]['status'] ?? '') === 'paid');
[$code2,$again] = $fetch('step3_user','test-step3-001');
step3ApiAssert('repeated poll idempotent', ($again['item']['status'] ?? '') === 'paid');

[$wrongCode,$wrong] = $fetch('different_user','test-step3-001');
step3ApiAssert('other account cannot access paid order', empty($wrong['ok']) && ($wrong['error'] ?? '') === 'سفارش پیدا نشد');

// Never claim paid based on CSV confirmation alone.
@unlink($store);
file_put_contents($jsonPath,json_encode([$order],JSON_UNESCAPED_UNICODE));
[$withoutEvidenceCode,$waiting] = $fetch('step3_user','test-step3-001');
step3ApiAssert('CSV confirmed but provision missing stays waiting',
    !empty($waiting['ok']) && ($waiting['item']['status'] ?? '') === 'waiting');

@unlink($runner);
@unlink($jsonPath);
@unlink($csvPath);
@unlink($store);
@rmdir(dirname($store));
@rmdir($locksDir);
@rmdir($dir);

if($errors){
    fwrite(STDERR,"$errors status API step3 tests failed\n");
    exit(1);
}
echo "Status API step3 integration tests passed\n";

<?php
// Exercise the real finalizer with a local notification double; never call Bale.
$root = sys_get_temp_dir() . '/pnv_bale_finalize_' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
putenv('PNV_PAYMENTS_CSV_PATH=' . $root . '/payments.csv');
putenv('PNV_LOCKS_DIR=' . $root . '/locks');
$notifications = [];
$throwOnNotify = false;
function baleConfigPath(){ return ''; } // Prevent loading the live transport.
function baleNotifyPaymentConfirmedRow($row, $kindHint = '', $opts = []){
    global $notifications, $throwOnNotify;
    $persisted = xuiLoadPayments();
    if(($persisted[0][6] ?? '') !== 'تایید شد'){
        throw new RuntimeException('notification before payment persistence');
    }
    $notifications[] = [$row, $kindHint, $opts];
    if($throwOnNotify){ throw new RuntimeException('simulated transport failure'); }
    return [['ok' => true]];
}
require_once __DIR__ . '/../xui_lib.php';
function check($ok, $message){
    if(!$ok){ throw new RuntimeException($message); }
    echo "OK  $message\n";
}
function fixture($kind, $tracking){
    return ['donya1380', 'donyaaa', '50 گیگ', $tracking, '', '', 'در انتظار تایید', '', time(), $kind, '', '', 2748690];
}
try{
    foreach(['خرید', 'تمدید'] as $kind){
        $notifications = [];
        $row = fixture($kind, 'AUTO-' . ($kind === 'خرید' ? '8690' : '8680'));
        xuiSavePayments([$row]);
        $result = ['ok' => true, 'link' => 'https://example.invalid/sub/test', 'email' => 'donyaaa_0053', 'server_id' => 'vip3'];
        $key = xuiPaymentRowProvisionKey($row);
        $done = xuiRenewApproveFinalizeFromResult(0, $row, $key, $result, false);
        check(!empty($done['ok']) && count($notifications) === 1, "$kind finalization notifies once");
        check($notifications[0][2]['provision'] === $result, 'notification receives actual provision account and panel');
        xuiRenewApproveFinalizeFromResult(0, $row, $key, $result, true);
        check(count($notifications) === 1, 'repeated confirmation does not send again');
    }
    $notifications = [];
    $row = fixture('خرید', 'AUTO-8670');
    xuiSavePayments([$row]);
    $GLOBALS['xuiTestSavePaymentsInterceptor'] = static function(){ return false; };
    $done = xuiRenewApproveFinalizeFromResult(0, $row, xuiPaymentRowProvisionKey($row), $result, false);
    unset($GLOBALS['xuiTestSavePaymentsInterceptor']);
    check(empty($done['ok']) && count($notifications) === 0, 'failed payment persistence never sends a success notification');
    $throwOnNotify = true;
    $done = xuiRenewApproveFinalizeFromResult(0, $row, xuiPaymentRowProvisionKey($row), $result, false);
    check(!empty($done['ok']), 'notification exception does not fail completed purchase');
    check(xuiLoadPayments()[0][6] === 'تایید شد', 'payment remains confirmed after notification failure');
}finally{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file){ $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
echo "All passed\n";

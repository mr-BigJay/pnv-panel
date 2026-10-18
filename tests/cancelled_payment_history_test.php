<?php
/**
 * Regression: cancelled payment requests remain in the admin audit trail.
 * Isolated CSV/JSON fixtures only; no live 3x-ui calls.
 */
function checkoutReleaseDiscountOrder($id){ return true; }

$dir = sys_get_temp_dir() . '/pnv-cancel-audit-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $dir . '/instant_payments.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $dir . '/payments.csv');

require_once dirname(__DIR__) . '/instant_pay_lib.php';

$failed = 0;
function checkCancelled($condition, $message){
    global $failed;
    if(!$condition){
        fwrite(STDERR, "FAIL: $message\n");
        $failed++;
    }
}

try{
    $now = time();
    $orders = [
        ['id'=>'audit-renew','user'=>'demo','type'=>'تمدید','code'=>'3830',
         'plan'=>'20 گیگ - 250 هزار تومان','sub'=>'https://example.com/sub/demo',
         'status'=>'waiting','created_at'=>$now-100,'expires_at'=>$now+1800,
         'amount'=>2493830,'csv_index'=>0],
        ['id'=>'audit-buy','user'=>'demo','type'=>'خرید','code'=>'7490',
         'plan'=>'10 گیگ - 150 هزار تومان','subname'=>'democonfig',
         'status'=>'waiting','created_at'=>$now-80,'expires_at'=>$now+1800,
         'amount'=>1497490,'csv_index'=>1],
    ];
    instantPaySave($orders);
    $csv = [
        ['demo','https://example.com/sub/demo','20 گیگ - 250 هزار تومان',
         'AUTO-3830','','','درحال بررسی','',$now-100,'تمدید','','',2493830,'3830'],
        ['demo','democonfig','10 گیگ - 150 هزار تومان',
         'AUTO-7490','','','درحال بررسی','',$now-80,'خرید','','',1497490,'7490'],
    ];
    checkCancelled(xuiSavePayments($csv), 'initial CSV saved');
    $changed = instantPaySupersedeUserWaiting('demo', null, null, $orders);
    checkCancelled(is_array($changed), 'new checkout archives all waiting types');
    $saved = instantPayLoad();
    checkCancelled(count($saved) === 2, 'JSON order count retained');
    checkCancelled(count(array_filter($saved, fn($x)=>($x['status']??'')==='cancelled')) === 2, 'both orders cancelled');
    $archived = xuiLoadPayments();
    checkCancelled(count($archived) === 2, 'CSV rows never deleted');
    checkCancelled(($archived[0][6]??'') === 'رد شد' && ($archived[1][6]??'') === 'رد شد', 'cancelled CSV rows marked rejected');
    checkCancelled(!instantPayCsvRowPending($archived[0]), 'cancelled order cannot match incoming bank deposit');
    checkCancelled(instantPayMatchAmountExact(2493830) === null, 'cancelled JSON cannot match');
    checkCancelled(instantPayAdminRowBucket($archived[0]) === 'cancelled', 'archive classification');
    checkCancelled(count(instantPayAdminCancelledHistory($archived,'تمدید')) === 1, 'one archived renewal');
    checkCancelled(count(instantPayAdminCancelledHistory($archived,'خرید')) === 1, 'one archived purchase');

    // Historical records removed from CSV by older versions remain visible.
    $older = ['id'=>'old-removed','user'=>'demo','type'=>'تمدید','code'=>'1880',
        'status'=>'cancelled','message'=>'لغو توسط کاربر','created_at'=>$now-300,
        'expires_at'=>$now-100,'amount'=>2491880,'csv_index'=>-1,
        'sub'=>'https://example.com/sub/demo','plan'=>'20 گیگ'];
    $saved[] = $older;
    instantPaySave($saved);
    $history = instantPayAdminCancelledHistory(xuiLoadPayments(), 'تمدید');
    checkCancelled(count($history) === 2, 'historical JSON-only cancellation restored to admin');
    checkCancelled(count(array_filter($history, fn($r)=>!empty($r['synthetic']))) === 1, 'missing CSV row is virtual only');
    checkCancelled(count(xuiLoadPayments()) === 2, 'virtual archive never writes a new CSV row');

    instantPayPurgeStaleAdminRows();
    checkCancelled(count(xuiLoadPayments()) === 2, 'admin refresh never purges payments');

    echo $failed ? "$failed failed\n" : "All cancelled history tests passed\n";
}finally{
    @unlink($dir . '/payments.csv');
    @unlink($dir . '/instant_payments.json');
    @rmdir($dir);
}
exit($failed ? 1 : 0);

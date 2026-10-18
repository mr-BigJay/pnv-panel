<?php
// An AUTO CSV row without JSON must never trigger 3x-ui provisioning.
$dir = sys_get_temp_dir() . '/pnv-auto-orphan-' . bin2hex(random_bytes(7));
mkdir($dir, 0700, true);
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $dir . '/instant_payments.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $dir . '/payments.csv');
require_once dirname(__DIR__) . '/instant_pay_lib.php';
try{
    $row=['orphan-test','configxyz','10 گیگ','AUTO-6420','','',
        'درحال بررسی','',time(),'خرید','','',1496420,6420];
    if(!xuiSavePayments([$row])){throw new RuntimeException('Cannot seed CSV');}
    $result=instantPayMarkPaidFromCsv(0,['amount'=>1496420]);
    if(!empty($result['ok']) || ($result['code']??'')!=='missing_order_json'){
        throw new RuntimeException('Orphan AUTO CSV must be refused without JSON');
    }
    $rows=xuiLoadPayments();
    if(count($rows)!==1 || !instantPayCsvRowPending($rows[0])){
        throw new RuntimeException('Orphan AUTO CSV must remain unapproved for manual review');
    }
    if(count(instantPayLoad())!==0){
        throw new RuntimeException('Guard must not synthesize a paid JSON order');
    }
    echo "Orphan AUTO CSV provisioning protection passed\n";
}finally{
    @unlink($dir . '/payments.csv');
    @unlink($dir . '/instant_payments.json');
    @rmdir($dir);
}

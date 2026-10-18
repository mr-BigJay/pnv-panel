<?php
/**
 * Regression: two independent PHP worker processes must not lose a checkout.
 * No Production files/3x-ui/HTTP connection is used.
 */
function checkoutReleaseDiscountOrder($id){return true;}
$dir = sys_get_temp_dir() . '/pnv-parallel-create-' . bin2hex(random_bytes(8));
if(!mkdir($dir, 0700, true)){
    fwrite(STDERR, "Cannot create isolated test dir\n"); exit(1);
}
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $dir . '/instant_payments.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $dir . '/payments.csv');
require_once dirname(__DIR__) . '/instant_pay_lib.php';

$errors=[];
try{
    $worker=__DIR__ . '/fixtures/instant_pay_create_worker.php';
    $processes=[];
    foreach([['parallelA',350],['parallelB',0]] as $item){
        [$config, $delay]=$item;
        $cmd=[PHP_BINARY,$worker,$dir,$config,(string)$delay];
        $descriptors=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
        $proc=proc_open($cmd,$descriptors,$pipes,__DIR__);
        if(!is_resource($proc)){throw new RuntimeException('Cannot start worker '.$config);}
        fclose($pipes[0]);
        $processes[]=[$config,$proc,$pipes];
        usleep(40000);
    }
    foreach($processes as [$config,$proc,$pipes]){
        $stdout=stream_get_contents($pipes[1]);
        $stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $rc=proc_close($proc);
        $result=json_decode($stdout,true);
        if($rc!==0 || !is_array($result) || empty($result['ok'])){
            $errors[]="Worker $config failed rc=$rc stderr=$stderr stdout=$stdout";
        }
    }
    $json=instantPayLoad();
    $csv=xuiLoadPayments();
    if(count($json)!==2){$errors[]='Expected two retained JSON order records';}
    if(count($csv)!==2){$errors[]='Expected two retained CSV records';}
    if(count(array_filter($json, static fn($x)=>($x['status']??'')==='waiting'))!==1){
        $errors[]='Exactly one latest order must remain waiting';
    }
    if(count(array_filter($json, static fn($x)=>($x['status']??'')==='cancelled'))!==1){
        $errors[]='Previous order must be cancelled';
    }
    if(count(array_filter($csv,'instantPayCsvRowPending'))!==1){
        $errors[]='Exactly one CSV row must be pending';
    }
    $codes=array_map(static fn($x)=>(string)($x['code']??''),$json);
    if(count(array_unique($codes))!==2){$errors[]='Unique payment codes required';}
    foreach($json as $order){
        $index=instantPayResolveCsvIndex($order);
        if($index<0){$errors[]='Order missing corresponding CSV row';continue;}
        $csvStatus=trim((string)($csv[$index][6]??''));
        if(($order['status']??'')==='waiting' && !instantPayCsvRowPending($csv[$index])){
            $errors[]='Waiting order has non-pending CSV';
        }
        if(($order['status']??'')==='cancelled' && $csvStatus!=='رد شد'){
            $errors[]='Cancelled order has non-rejected CSV';
        }
    }
    if($errors){
        fwrite(STDERR, implode("\n",$errors) . "\n");
        exit(1);
    }
    echo "Parallel checkout locking test passed\n";
}finally{
    @unlink($dir.'/payments.csv');
    @unlink($dir.'/instant_payments.json');
    @unlink($dir.'/locks/instant_pay_create.lock');
    @rmdir($dir.'/locks');
    @rmdir($dir);
}

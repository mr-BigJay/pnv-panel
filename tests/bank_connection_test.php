<?php
/** Test isolated signed Tejarat intake; never connects to live bank or XUI. */
$base = sys_get_temp_dir() . '/pnv-bank-' . bin2hex(random_bytes(5));
mkdir($base, 0700, true);
putenv('PNV_BANK_CONNECTION_CONFIG_PATH=' . $base . '/config.json');
putenv('PNV_BANK_CONNECTION_LEDGER_PATH=' . $base . '/ledger.json');
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $base . '/orders.json');
putenv('PNV_LOCKS_DIR=' . $base . '/locks');
mkdir($base . '/locks', 0700);
require_once __DIR__ . '/../bank_connection_lib.php';
require_once __DIR__ . '/../instant_pay_lib.php';
$count = 0; $fails = 0;
function bankCheck($name,$assert) { global $fails; echo ($assert ? 'OK ' : 'FAIL ') . $name . "\n"; if (!$assert) $fails++; }
$now = time();
$secret = str_repeat('a',64);
$card = '5859831122334455';
bankCheck('disabled by default',empty(pnvBankLoadConfig()['enabled']));
$cfg = array_merge(pnvBankDefaultConfig(),[
    'enabled'=>true,'dry_run'=>true,'destination_card'=>$card,'shared_secret'=>$secret
]);
bankCheck('config save', pnvBankSaveConfig($cfg));
bankCheck('card normalize', pnvBankDigits('۵۸۵۹ ۸۳۱۱-۲۲۳۳۴۴۵۵') === $card);
$item = [
    'id'=>'bank-test-1','user'=>'u','status'=>'waiting','amount'=>1499280,
    'currency'=>'rial','card'=>$card,'created_at'=>$now-70,
    'expires_at'=>$now+1200,'code'=>9280
];
bankCheck('save test order',instantPaySave([$item]));
$tx = ['transaction_id'=>'TJ-2026-98765','amount_rial'=>1499280,
    'destination_card'=>$card,'occurred_at'=>$now-15,'direction'=>'credit'];
$raw = json_encode($tx,JSON_UNESCAPED_UNICODE);
$sig = hash_hmac('sha256', $now."\n".$raw,$secret);
bankCheck('signature correct',pnvBankVerifySignature($raw,$now,$sig,$cfg,$now));
bankCheck('signature forgery rejected',!pnvBankVerifySignature($raw,$now,str_repeat('a',64),$cfg,$now));
bankCheck('old timestamp rejected',!pnvBankVerifySignature($raw,$now-601,hash_hmac('sha256',($now-601)."\n".$raw,$secret),$cfg,$now));
$preview = pnvBankHandleSignedDeposit($raw,$now,$sig,$now);
bankCheck('dry-run exact match',!empty($preview['ok']) && !empty($preview['dry_run']) && ($preview['matched_order_id']??'')==='bank-test-1');
bankCheck('dry-run does not create claim', !file_exists($base.'/ledger.json'));
$bad = $tx; $bad['direction']='debit'; $rawBad=json_encode($bad);
bankCheck('debit blocked', (pnvBankHandleSignedDeposit($rawBad,$now,hash_hmac('sha256',$now."\n".$rawBad,$secret),$now)['code']??'')==='invalid_transaction');
$bad = $tx; $bad['destination_card']='6277601111111111'; $rawBad=json_encode($bad);
bankCheck('wrong card blocked', (pnvBankHandleSignedDeposit($rawBad,$now,hash_hmac('sha256',$now."\n".$rawBad,$secret),$now)['code']??'')==='invalid_transaction');
$bad = $tx; $bad['amount_rial']=1499270; $rawBad=json_encode($bad);
bankCheck('approximate amount not matched', (pnvBankHandleSignedDeposit($rawBad,$now,hash_hmac('sha256',$now."\n".$rawBad,$secret),$now)['code']??'')==='no_match');
bankCheck('ambiguous same amount refused',(
    instantPaySave([$item,array_merge($item,['id'=>'bank-test-2'])]) &&
    (pnvBankHandleSignedDeposit($raw,$now,$sig,$now)['code']??'')==='ambiguous_match'
));
instantPaySave([$item]);
$cfg['dry_run']=false;
bankCheck('enable trusted live mode in test',pnvBankSaveConfig($cfg));
$GLOBALS['pnvBankTestMarkPaid']=static function($id,$matched) use (&$count) { $count++; return ['ok'=>true]; };
$applied=pnvBankHandleSignedDeposit($raw,$now,$sig,$now);
bankCheck('one verified apply',!empty($applied['ok']) && empty($applied['dry_run']) && $count===1);
$again=pnvBankHandleSignedDeposit($raw,$now,$sig,$now);
bankCheck('replay is idempotent',($again['code']??'')==='duplicate_transaction' && $count===1);
$ledger=json_decode(file_get_contents($base.'/ledger.json'),true);
bankCheck('confirmed ledger',count($ledger)===1 && reset($ledger)['state']==='confirmed');
$cfg['enabled']=false;pnvBankSaveConfig($cfg);
bankCheck('disable stops intake',(pnvBankHandleSignedDeposit($raw,$now,$sig,$now)['code']??'')==='disabled');
unset($GLOBALS['pnvBankTestMarkPaid']);
foreach (glob($base.'/*') as $p) {if(is_dir($p)) rmdir($p); else unlink($p);}
rmdir($base);
if ($fails) {fwrite(STDERR,"FAILED: $fails\n");exit(1);}
echo "ALL BANK CONNECTION TESTS PASSED\n";

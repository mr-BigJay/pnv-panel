<?php
/**
 * Manual-renew route compatibility and no-double-write regression tests.
 * No network or payments; mock 3x-ui API responses only.
 */
require_once __DIR__ . '/../xui_lib.php';

$failed = 0;
function xuiFallbackAssert($desc, $condition){
    global $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $desc . "\n";
    if(!$condition) $failed++;
}

$server = ['id'=>'mock', 'inbound_id'=>2, 'base_url'=>'https://invalid.example'];
$client = [
    'id'=>'b3dbeace-108f-4c70-9a54-c3ffcccf37aa',
    'email'=>'test@example.invalid',
    'subId'=>'testSubscription001',
    'totalGB'=>xuiGbToBytes(7),
    'expiryTime'=>0,
    'limitIp'=>2,
    'limitHwid'=>4,
    'password'=>'keep-existing-password',
    'group_name'=>'retain-existing-group',
    '_inbound_id'=>2,
];
$anchor = 1700000000000;
$calls = [];
$modern = ['success'=>false,'http'=>404,'msg'=>'route not found'];
$form = ['success'=>true,'http'=>200];
$json = ['success'=>false,'http'=>404];

$GLOBALS['xuiTestApiRequest'] = static function($s,$m,$p,$body) use (&$calls,&$modern,&$json){
    $calls[] = ['kind'=>'json','path'=>$p,'body'=>$body];
    return strpos($p, '/panel/api/clients/update/') === 0 ? $modern : $json;
};
$GLOBALS['xuiTestApiRequestForm'] = static function($s,$m,$p,$fields) use (&$calls,&$form){
    $calls[] = ['kind'=>'form','path'=>$p,'fields'=>$fields];
    return $form;
};

$run = static function() use (&$calls, $server, $client, $anchor){
    $calls=[];
    return xuiApplyRenewPlanAbsolute($server, $client, 10, 30, $anchor);
};
$out=$run();
xuiFallbackAssert('modern 404 falls back to form', !empty($out['ok']) && ($out['method']??'')==='updateClient_absolute_form');
xuiFallbackAssert('only modern + form called', count($calls)===2 && $calls[0]['kind']==='json' && $calls[1]['kind']==='form');

$payload=$calls[0]['body']??[];
xuiFallbackAssert('modern full-row preserves credential', ($payload['password']??'')==='keep-existing-password');
xuiFallbackAssert('modern full-row preserves hwid and group', ($payload['limitHwid']??0)===4 && ($payload['group_name']??'')==='retain-existing-group');
xuiFallbackAssert('modern payload has absolute size and expiration', ($payload['totalGB']??0)===xuiGbToBytes(10) && ($payload['expiryTime']??0)===$anchor+30*86400000);
$formPayload=json_decode($calls[1]['fields']['settings']??'',true);
xuiFallbackAssert('legacy form has correct inbound ID and volume', ($calls[1]['fields']['id']??'')==='2' && ($formPayload['clients'][0]['totalGB']??0)===xuiGbToBytes(10));

$modern=['success'=>false,'http'=>500,'msg'=>'server error'];
$out=$run();
xuiFallbackAssert('modern 5xx is ambiguous and never retries', empty($out['ok']) && ($out['code']??'')==='transport_error' && count($calls)===1);

$modern=['success'=>false,'http'=>0,'msg'=>'timed out'];
$out=$run();
xuiFallbackAssert('modern timeout is ambiguous and never retries', empty($out['ok']) && ($out['code']??'')==='transport_error' && count($calls)===1);

$modern=['success'=>false,'http'=>200,'msg'=>'Something went wrong'];
$out=$run();
xuiFallbackAssert('modern 200 success:false cannot cause a second mutation', empty($out['ok']) && ($out['code']??'')==='transport_error' && count($calls)===1);

$modern=['success'=>false,'http'=>404,'msg'=>'not found'];
$form=['success'=>false,'http'=>500,'msg'=>'error'];
$out=$run();
xuiFallbackAssert('form 5xx does not fall through to JSON', empty($out['ok']) && ($out['code']??'')==='transport_error' && count($calls)===2);

$form=['success'=>false,'http'=>0,'msg'=>'timeout'];
$out=$run();
xuiFallbackAssert('form timeout does not fall through to JSON', empty($out['ok']) && ($out['code']??'')==='transport_error' && count($calls)===2);

$form=['success'=>false,'http'=>200,'msg'=>'rejected'];
$out=$run();
xuiFallbackAssert('form 200 success:false does not double write', empty($out['ok']) && ($out['code']??'')==='transport_error' && count($calls)===2);

$form=['success'=>false,'http'=>404,'msg'=>'missing route'];
$json=['success'=>true,'http'=>200,'msg'=>'ok'];
$out=$run();
xuiFallbackAssert('definite 404 form rejection tries JSON', !empty($out['ok']) && ($out['method']??'')==='updateClient_absolute_json' && count($calls)===3);

$json=['success'=>false,'http'=>404,'msg'=>'route not found'];
$out=$run();
$diag=xuiApplyRenewDiagSummary($out);
xuiFallbackAssert('triple 404 gives safe API route diagnostics', empty($out['ok']) && ($out['code']??'')==='activation_failed'
 && count($calls)===3 && substr_count($diag,'HTTP 404')===3
 && strpos($diag,'test@example.invalid')===false);

$modern=['success'=>true,'http'=>200,'msg'=>'ok'];
$out=$run();
xuiFallbackAssert('modern success skips both legacy routes', !empty($out['ok']) && ($out['method']??'')==='clients_update' && count($calls)===1);

xuiFallbackAssert('5xx errors classified as transport', xuiApiFailureKind(['success'=>false,'http'=>502,'msg'=>'server error'])==='transport');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestApiRequestForm']);
if($failed){
    fwrite(STDERR, "$failed XUI write fallback regression test(s) failed\n");
    exit(1);
}
echo "All XUI write fallback tests passed\n";

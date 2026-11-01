<?php
/**
 * Standalone isolated tests of the historic expiry repair dry-run and guards.
 * php tests/old_buy_expiry_repair_test.php
 */
require_once __DIR__ . '/../scripts/repair-old-buy-expiry.php';

$failure = 0;
function repairTest($cond, $label){
    global $failure;
    echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n";
    if(!$cond){ $failure++; }
}

$tmp = sys_get_temp_dir() . '/pnv_old_expiry_repair_' . getmypid();
@mkdir($tmp, 0700, true);
@mkdir($tmp . '/invoices', 0700, true);
@mkdir($tmp . '/db', 0700, true);
foreach(['xui_lib.php', 'subscription_lib.php'] as $file){
    copy(__DIR__ . '/../' . $file, $tmp . '/' . $file);
}
putenv('PNV_XUI_CONFIG_PATH=' . $tmp . '/db/xui_servers.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $tmp . '/invoices/payments.csv');
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $tmp . '/db/instant_payments.json');
putenv('PNV_PLANS_CATALOG_PATH=' . $tmp . '/db/plans.json');
file_put_contents($tmp . '/db/plans.json', '[]');

require_once $tmp . '/xui_lib.php';
require_once $tmp . '/subscription_lib.php';
// Isolated InstantPay fixture, without loading Bale or live payment integrations.
$GLOBALS['testInstantPayRecords'] = [];
function instantPayLoad(){
    return $GLOBALS['testInstantPayRecords'];
}

$server = ['id'=>'vip3','host'=>'vip3.example.test','inbound_id'=>1,'base_url'=>'https://fake.test/custom/','api_token'=>'test'];
file_put_contents($tmp . '/db/xui_servers.json', json_encode([
    'enabled'=>true, 'servers'=>[$server],
]));
$now = time();
$link = 'https://vip3.example.test:2096/sub/abc1234567890ab';
$csv = fopen($tmp . '/invoices/payments.csv', 'w');
fputcsv($csv, ['alice','config1','10 گیگ - 30 روز','AUTO-1000','','','تایید شد',$link,$now - 86400*7,'خرید','','',1500000]);
fclose($csv);
$GLOBALS['testInstantPayRecords'] = [[
    'user'=>'alice','code'=>1000,'status'=>'paid','paid_at'=>$now - 86400*6,
    'type'=>'خرید','link'=>$link,
]];
$client = [
    'id'=>'e4e9df20-8a83-4e4a-990f-b696faf567c9',
    'subId'=>'abc1234567890ab',
    'email'=>'alice_test',
    'enable'=>true,
    'expiryTime'=>0,
    'totalGB'=>xuiGbToBytes(10),
    'password'=>'do-not-log',
    'reset'=>0,
    'flow'=>'xtls-rprx-vision',
];
$posts = 0;
$GLOBALS['xuiTestApiRequest'] = static function($srv, $method, $path) use (&$client){
    if($method === 'GET' && $path === '/panel/api/inbounds/list'){
        return ['success'=>true,'obj'=>[[
            'id'=>1,
            'settings'=>json_encode(['clients'=>[$client]], JSON_UNESCAPED_UNICODE),
        ]]];
    }
    return ['success'=>false,'http'=>404,'msg'=>'not found'];
};
$GLOBALS['xuiTestApiRequestForm'] = static function() use (&$posts){
    $posts++;
    return ['success'=>false,'http'=>404,'msg'=>'not found'];
};

ob_start();
pnvExpiryAuditMain(['audit-old-buy-expiry.php','--root=' . $tmp]);
$audit = json_decode(ob_get_clean(), true);
repairTest(($audit['mode'] ?? '') === 'READ_ONLY_NO_MUTATIONS','audit is read only');
repairTest(intval($audit['summary']['candidate_review_before_apply'] ?? 0) === 1,'test fixture contains one repair candidate');

$key = $audit['items'][0]['key'] ?? '';
$reportFile = $tmp . '/audit.json';
file_put_contents($reportFile, json_encode($audit));
$dry = pnvRepairRun(['repair-old-buy-expiry.php', '--root=' . $tmp, '--report=' . $reportFile, '--key=' . $key]);
repairTest(($dry['result'] ?? '') === 'SAFE_DRY_RUN_PASS','single-client dry-run passes');
repairTest(intval($dry['changes']['expiryTime'][1] ?? 0) > 0, 'target expiry in epoch milliseconds');
repairTest($posts === 0 && intval($client['expiryTime']) === 0, 'dry-run never mutates XUI');

$bad = $audit;
$bad['items'][0]['proposed_expiry_utc'] = '2030-01-01T00:00:00+00:00';
file_put_contents($reportFile, json_encode($bad));
$staleDenied = false;
try {
    pnvRepairRun(['repair-old-buy-expiry.php','--root=' . $tmp,'--report=' . $reportFile,'--key=' . $key]);
}
catch(RuntimeException $exc){
    $staleDenied = strpos($exc->getMessage(), 'evidence changed') !== false;
}
repairTest($staleDenied && $posts === 0,'mismatched saved audit is blocked before any POST');
file_put_contents($reportFile, json_encode($audit));

$client['expiryTime'] = intval((time() + 86400*10) * 1000);
$alreadyDenied = false;
try{
    pnvRepairRun(['repair-old-buy-expiry.php','--root=' . $tmp,'--report=' . $reportFile,'--key=' . $key]);
}
catch(RuntimeException $exc){
    $alreadyDenied = true;
}
repairTest($alreadyDenied && $posts === 0, 'already-expiring client blocked before any POST');

$old = $client; $new = $client;
$new['expiryTime'] = $old['expiryTime'] + 86400000;
repairTest(pnvRepairSameExceptExpiry($old, $new, $new['expiryTime']), 'readback recognizes expiry-only change');
$new['password'] = 'changed-password';
repairTest(!pnvRepairSameExceptExpiry($old, $new, $new['expiryTime']), 'readback detects any other client field change');

unset($GLOBALS['xuiTestApiRequest'], $GLOBALS['xuiTestApiRequestForm']);

if($failure){
    fwrite(STDERR, "{$failure} tests FAILED\n");
    exit(1);
}
echo "All historical expiry repair tests passed\n";

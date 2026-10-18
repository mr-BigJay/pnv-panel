<?php
// Isolated subprocess for a deterministic two-PHP-worker checkout race.
function checkoutReleaseDiscountOrder($id){ return true; }
if(count($argv) !== 4){
    fwrite(STDERR, "Usage: worker.php <fixture-dir> <config-name> <delay-ms>\n");
    exit(2);
}
[$script, $dir, $config, $delay] = $argv;
putenv('PNV_INSTANT_PAYMENTS_PATH=' . $dir . '/instant_payments.json');
putenv('PNV_PAYMENTS_CSV_PATH=' . $dir . '/payments.csv');
require_once dirname(__DIR__, 2) . '/instant_pay_lib.php';
$GLOBALS['instantPayTestCreateLockHook'] = static function() use ($delay){
    usleep(max(0, intval($delay)) * 1000);
};
$plans = [['name'=>'10 گیگ','price'=>150,'days'=>30,'traffic_gb'=>10]];
$result = instantPayCreate([
    'user'=>'parallel-user',
    'type'=>'خرید',
    'plan'=>pnvPlanOptionValue($plans[0]),
    'subname'=>$config,
    'sub'=>'',
    'card'=>'test-card',
    'plans'=>$plans
]);
echo json_encode($result, JSON_UNESCAPED_UNICODE), "\n";
exit(!empty($result['ok']) ? 0 : 1);

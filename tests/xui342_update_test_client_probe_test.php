<?php
/** Pure guards for disposable 3x-ui v3.4.2 probe. */
require_once __DIR__ . '/../scripts/probe-xui342-update-test-client.php';

$failed = 0;
function probeAssert($ok, $msg){
    global $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $msg . "\n";
    if(!$ok) $failed++;
}
$email = 'pnv-expiry-test-001';
$c = [
    'email' => $email,
    'uuid' => '734c8d4e-1122-4455-8899-123456789abc',
    'id' => '734c8d4e-1122-4455-8899-123456789abc',
    'subId' => 'abcd1234',
    'comment' => 'PNV_EXPIRY_TEST',
    'password' => 'secret-test',
    'auth' => 'test-auth',
    'security' => 'auto',
    'flow' => 'xtls-rprx-vision',
    'totalGB' => 1073741824,
    'enable' => true,
    'expiryTime' => 0,
    'reset' => 0,
    'resetDay' => 0,
    'resetMax' => 0,
];
try {
    pnvProbe342SafeOriginal($c, $email, 0);
    probeAssert(true, 'dummy-only zero-used precondition');
} catch(Throwable $e){ probeAssert(false, 'dummy-only zero-used precondition'); }
$target = 1795000000000;
$payload = pnvProbe342Payload($c, $target);
probeAssert($payload['expiryTime'] === $target, 'change exact expiry');
probeAssert($payload['id'] === $c['uuid'], 'preserve UUID in update');
probeAssert($payload['subId'] === $c['subId'], 'preserve subId');
probeAssert($payload['totalGB'] === $c['totalGB'], 'preserve quota');
probeAssert($payload['password'] === $c['password'] && $payload['auth'] === $c['auth'], 'preserve credentials');
probeAssert($payload['reset'] === 0 && $payload['resetDay'] === 0, 'no auto-renewal settings modification');
foreach([
    ['c' => array_merge($c, ['email' => 'actual-customer']), 'email' => 'actual-customer', 'used'=>0, 'label'=>'non-dummy rejected'],
    ['c' => array_merge($c, ['comment' => 'normal user']), 'email' => $email, 'used'=>0, 'label'=>'missing marker rejected'],
    ['c' => $c, 'email' => $email, 'used'=>5, 'label'=>'traffic-bearing client rejected'],
    ['c' => array_merge($c, ['expiryTime' => 1700000000]), 'email' => $email, 'used'=>0, 'label'=>'already expiring client rejected'],
    ['c' => array_merge($c, ['reset' => 1]), 'email' => $email, 'used'=>0, 'label'=>'auto-renewal client rejected'],
    ['c' => array_merge($c, ['totalGB' => 3221225472]), 'email' => $email, 'used'=>0, 'label'=>'greater-than-1GB rejected'],
] as $example){
    $rejected = false;
    try{ pnvProbe342SafeOriginal($example['c'], $example['email'], $example['used']);}
    catch(RuntimeException $e){$rejected = true;}
    probeAssert($rejected, $example['label']);
}
$source = file_get_contents(__DIR__ . '/../scripts/probe-xui342-update-test-client.php');
probeAssert(strpos($source, "fopen(\$marker, 'x')") !== false, 'one attempt marker before POST');
probeAssert(strpos($source, 'if(!$apply){') !== false, 'no-write default branch');
probeAssert(strpos($source, "pnvProbe342Count(\$server") !== false, 'duplicate checker is installed');
if($failed){fwrite(STDERR, "{$failed} tests failed\n");exit(1);}
echo "All dummy-only API probe tests passed\n";

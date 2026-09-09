<?php

require_once __DIR__ . '/../sub_usage_lib.php';
require_once __DIR__ . '/../reserved_renewal_lib.php';

function assertTrue($label, $cond){
    if(!$cond){
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
    echo "OK {$label}\n";
}

$activeUsage = [
    'ok' => true,
    'volume' => ['unlimited' => false, 'remain_pct' => 40],
    'time' => ['unlimited' => false, 'remain_pct' => 25, 'estimated' => false],
];

$depletedVol = [
    'ok' => true,
    'volume' => ['unlimited' => false, 'remain_pct' => 0],
    'time' => ['unlimited' => false, 'remain_pct' => 50, 'estimated' => false],
];

assertTrue('reserve when both remain', subUsageLimitedRenewShouldReserve($activeUsage) === true);
assertTrue('no reserve when vol depleted', subUsageLimitedRenewShouldReserve($depletedVol) === false);
assertTrue('depleted OR logic', subUsageViewLooksDepleted($depletedVol) === true);

echo "All reserved renewal tests passed.\n";

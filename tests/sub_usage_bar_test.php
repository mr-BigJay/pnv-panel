<?php

require_once __DIR__ . '/../sub_usage_lib.php';

$now = time();
$expire = $now + (20 * 86400 + 21 * 3600);

$view = subUsageBuildView(
    1000000,
    30 * 1024 * 1024 * 1024,
    $expire * 1000,
    ['plan_days' => 0, 'start_ts' => 0]
);

$timePct = floatval($view['time']['remain_pct'] ?? 0);

if($timePct >= 95){
    fwrite(STDERR, "Expected time bar below 95%, got {$timePct}\n");
    exit(1);
}

if($timePct < 60 || $timePct > 75){
    fwrite(STDERR, "Expected time bar around 66-70%, got {$timePct}\n");
    exit(1);
}

echo "ok time_pct={$timePct}\n";

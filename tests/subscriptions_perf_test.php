<?php
/**
 * Smoke test: subscriptions page should not block on panel API or sync QR generation.
 */
require_once __DIR__ . '/../subscription_lib.php';
require_once __DIR__ . '/../sub_usage_lib.php';

$sampleUser = 'test_subs_perf_user';

$start = microtime(true);
$subs = pnvLoadUserActiveSubscriptions($sampleUser, false);
$loadMs = (microtime(true) - $start) * 1000;

$usageItems = [];
foreach($subs as $sub){
    $link = trim((string)($sub['link'] ?? ''));
    if($link === '' || !pnvIsValidSubLink($link)){
        continue;
    }
    $usageItems[] = [
        'link' => $link,
        'plan' => trim((string)($sub['plan_text'] ?? '')),
        'date' => trim((string)($sub['date'] ?? '')),
        'time' => trim((string)($sub['time'] ?? '')),
    ];
}

$start = microtime(true);
$bundle = count($usageItems) > 0
    ? subUsageGetForItems($usageItems, 0, false)
    : ['items' => []];
$usageMs = (microtime(true) - $start) * 1000;

echo "subs_count=" . count($subs) . "\n";
echo "usage_items=" . count($usageItems) . "\n";
echo "load_subs_ms=" . round($loadMs, 2) . "\n";
echo "usage_cache_ms=" . round($usageMs, 2) . "\n";
echo "refreshed=" . intval($bundle['refreshed'] ?? 0) . "\n";

if(intval($bundle['refreshed'] ?? 0) !== 0){
    fwrite(STDERR, "expected zero panel refreshes on cache-only load\n");
    exit(1);
}

echo "ok\n";

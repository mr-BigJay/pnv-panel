<?php

require_once __DIR__ . '/../coupon_lib.php';

$path = couponReferralEventsPath();
$backup = $path . '.rebuild_test_bak';

if(is_file($path)){
    copy($path, $backup);
}

try{
    $first = couponRebuildReferralEvents();
    $second = couponRebuildReferralEvents();

    $ids1 = array_column($first['events'], 'id');
    $ids2 = array_column($second['events'], 'id');
    sort($ids1);
    sort($ids2);

    $ok = intval($first['count']) === intval($second['count'])
        && $ids1 === $ids2
        && count($ids1) === count(array_unique($ids1));
}
finally{
    if(is_file($backup)){
        copy($backup, $path);
        @unlink($backup);
    }
}

echo $ok ? "PASS rebuild idempotent\n" : "FAIL rebuild idempotent\n";
if(!$ok){
    echo 'count1=' . ($first['count'] ?? '?') . ' count2=' . ($second['count'] ?? '?') . "\n";
}

exit($ok ? 0 : 1);

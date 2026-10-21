<?php

require_once __DIR__ . '/../coupon_lib.php';

$path = couponReferralConfigPath();
$backup = $path . '.bak_test';

if(is_file($path)){
    rename($path, $backup);
}

try{
    $config = couponReferralDefaultConfig();
    $needs = array_column($config['tiers'], 'referrals');
    sort($needs, SORT_NUMERIC);

    $ok = $needs === [3, 5, 10, 20];

    $loaded = couponLoadReferralConfig();
    $loadedNeeds = array_column($loaded['tiers'], 'referrals');
    sort($loadedNeeds, SORT_NUMERIC);

    $ok = $ok && $loadedNeeds === [3, 5, 10, 20];
}
finally{
    if(is_file($backup)){
        rename($backup, $path);
    }
    elseif(is_file($path)){
        @unlink($path);
    }
}

echo $ok ? "PASS legacy default tiers on first load\n" : "FAIL legacy default tiers\n";
exit($ok ? 0 : 1);

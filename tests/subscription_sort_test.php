<?php

require_once __DIR__ . '/../subscription_lib.php';

$entries = [
    ['link' => 'a', 'created_ts' => 100],
    ['link' => 'b', 'created_ts' => 300],
    ['link' => 'c', 'created_ts' => 200],
];

usort($entries, static function($a, $b){
    return pnvSubscriptionActivityTs($b) <=> pnvSubscriptionActivityTs($a);
});

if(($entries[0]['link'] ?? '') !== 'b' || ($entries[2]['link'] ?? '') !== 'a'){
    fwrite(STDERR, "sort order wrong\n");
    exit(1);
}

echo "ok\n";

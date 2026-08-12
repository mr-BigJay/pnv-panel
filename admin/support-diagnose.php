<?php

require_once __DIR__ . '/auth.php';

if(!pnvAdminIsLoggedIn()){
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/../support_lib.php';

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$file = $root . '/db/support.json';

echo "Support diagnose\n";
echo "================\n\n";

$paths = [$file, $file . '.bak'];

foreach(glob($root . '/db/support.json*') ?: [] as $match){
    $paths[] = $match;
}

$paths = array_values(array_unique($paths));
$best = 0;
$bestPath = '';

foreach($paths as $path){
    if(!is_file($path)){
        continue;
    }

    $data = supportReadJsonFile($path);
    $count = is_array($data) ? count($data) : 0;
    $size = filesize($path);
    echo basename($path) . ": {$count} tickets ({$size} bytes)\n";

    if($count > $best){
        $best = $count;
        $bestPath = $path;
    }
}

echo "\nLoaded via supportLoad(): " . count(supportLoad($file)) . " tickets\n";
echo "Best source: " . ($bestPath !== '' ? basename($bestPath) : '-') . " ({$best})\n";

if($bestPath !== '' && $bestPath !== $file && $best > count(supportReadJsonFile($file) ?: [])){
    echo "\nRestoring from " . basename($bestPath) . "...\n";
    copy($bestPath, $file);
    echo "Restored. Reload support page.\n";
}

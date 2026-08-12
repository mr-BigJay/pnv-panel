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
$snapshot = $root . '/db/support.repo.json';
$doMerge = isset($_GET['merge']) || isset($_POST['merge']);
$doRestore = isset($_GET['restore']) || isset($_POST['restore']);

echo "Support diagnose\n";
echo "================\n\n";

$paths = [$file, $file . '.bak', $snapshot];

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

if($doRestore && $bestPath !== '' && $bestPath !== $file && $best > count(supportReadJsonFile($file) ?: [])){
    echo "\nRestoring from " . basename($bestPath) . "...\n";
    copy($bestPath, $file);
    echo "Restored. Reload support page.\n";
    echo "Tickets now: " . count(supportLoad($file)) . "\n";
    exit;
}

if($doMerge){
    echo "\nMerge requested.\n";

    if(!is_file($snapshot)){
        echo "Downloading snapshot to support.repo.json ...\n";
        $branch = getenv('PNV_SUPPORT_BRANCH') ?: 'cursor/fix-admin-support-send-b94c';
        $url = 'https://raw.githubusercontent.com/mr-BigJay/pnv-panel/' . rawurlencode($branch) . '/db/support.json';
        $json = @file_get_contents($url);

        if($json === false || trim($json) === ''){
            echo "Could not download snapshot from GitHub.\n";
            echo "Run on server: bash scripts/merge-support-data.sh\n";
            exit(1);
        }

        file_put_contents($snapshot, $json);
    }

    $before = count(supportReadJsonFile($file) ?: []);
    $merged = supportImportSnapshot($file, $snapshot);
    $after = count($merged);

    echo "Tickets before: {$before}\n";
    echo "Tickets after:  {$after}\n";

    if($after > $before){
        echo "Merged " . ($after - $before) . " ticket(s).\n";
    }
    else{
        echo "No new tickets to merge.\n";
    }

    exit;
}

echo "\nActions:\n";
echo "- Restore best backup: ?restore=1\n";
echo "- Merge repo snapshot: ?merge=1\n";

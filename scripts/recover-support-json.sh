#!/bin/bash
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
export ROOT
FILE="${ROOT}/db/support.json"

echo "=== Merge support.json from all backups ==="
echo "Target: ${FILE}"

php <<'PHP'
<?php
$root = getenv('ROOT') ?: '/var/www/html';
require $root . '/support_lib.php';
$file = $root . '/db/support.json';

$before = supportReadJsonFile($file);
$beforeCount = is_array($before) ? count($before) : 0;

echo "Current tickets: {$beforeCount}\n";

foreach (supportDiscoverBackupFiles($file) as $path) {
    $count = count(supportReadJsonFile($path) ?: []);
    echo basename($path) . ": {$count} tickets\n";
}

$data = supportLoad($file);
$afterCount = is_array($data) ? count($data) : 0;

echo "Merged tickets: {$afterCount}\n";

if ($afterCount <= 0) {
    fwrite(STDERR, "No support tickets found in any backup file.\n");
    exit(1);
}
PHP

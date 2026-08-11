#!/bin/bash
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
export ROOT
FILE="${ROOT}/db/support.json"

echo "=== Recover support.json from backups ==="
echo "Target: ${FILE}"

if [[ ! -f "$FILE" ]]; then
  echo "ERROR: ${FILE} not found"
  exit 1
fi

php <<'PHP'
<?php
$root = getenv('ROOT') ?: '/var/www/html';
require $root . '/support_lib.php';
$file = $root . '/db/support.json';
$current = supportReadTicketsFile($file);
$currentCount = is_array($current) ? count($current) : 0;
$best = $current;
$bestCount = $currentCount;
$bestSource = 'current';

foreach (supportBackupCandidates($file) as $backup) {
    if (!is_file($backup)) {
        continue;
    }
    $data = supportReadTicketsFile($backup);
    $count = is_array($data) ? count($data) : 0;
    echo "backup " . basename($backup) . ": {$count} tickets\n";
    if ($count > $bestCount) {
        $best = $data;
        $bestCount = $count;
        $bestSource = $backup;
    }
}

echo "Using {$bestSource} with {$bestCount} tickets\n";

if (!is_array($best) || $bestCount <= 0) {
    fwrite(STDERR, "No recoverable support data found\n");
    exit(1);
}

if ($bestSource !== 'current') {
    supportSave($file, $best, ['allow_shrink' => true]);
    echo "Restored support.json from backup\n";
} elseif ($currentCount > 0) {
    echo "Current file already has {$currentCount} tickets; nothing to do\n";
} else {
    fwrite(STDERR, "Current file is empty and no backup found\n");
    exit(1);
}
PHP

php -r '
$root = getenv("ROOT") ?: "/var/www/html";
$file = $root . "/db/support.json";
$data = json_decode(file_get_contents($file), true);
echo "Final ticket count: " . (is_array($data) ? count($data) : 0) . "\n";
'

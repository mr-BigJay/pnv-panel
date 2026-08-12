#!/bin/bash
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
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
$file = $root . '/db/support.json';

function countTickets($path) {
    if (!is_file($path)) {
        return 0;
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? count($data) : 0;
}

$currentCount = countTickets($file);
$bestPath = $file;
$bestCount = $currentCount;

foreach ([$file . '.bak', dirname($file) . '/support.json.backup'] as $backup) {
    $count = countTickets($backup);
    echo "backup " . basename($backup) . ": {$count} tickets\n";
    if ($count > $bestCount) {
        $bestPath = $backup;
        $bestCount = $count;
    }
}

echo "Using {$bestPath} with {$bestCount} tickets\n";

if ($bestCount <= 0) {
    fwrite(STDERR, "No recoverable support data found\n");
    exit(1);
}

if ($bestPath !== $file) {
    if (!copy($bestPath, $file)) {
        fwrite(STDERR, "Failed to restore support.json\n");
        exit(1);
    }
    echo "Restored support.json from backup\n";
} else {
    echo "Current file already has {$currentCount} tickets; nothing to do\n";
}
PHP

php -r '
$root = getenv("ROOT") ?: "/var/www/html";
$file = $root . "/db/support.json";
$data = json_decode(file_get_contents($file), true);
echo "Final ticket count: " . (is_array($data) ? count($data) : 0) . "\n";
'

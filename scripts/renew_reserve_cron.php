<?php

/**
 * Reserved renew activation worker.
 *
 * Suggested cron (every minute):
 * * * * * * php /path/to/pnv-panel/scripts/renew_reserve_cron.php >> /var/log/pnv-renew-reserve.log 2>&1
 *
 * Rollback: disable/remove cron entry; reservations remain in db/renew_reservations.json.
 */

require_once __DIR__ . '/../xui_lib.php';
require_once __DIR__ . '/../renew_reserve_lib.php';

$lockDir = function_exists('xuiLocksDir') ? xuiLocksDir() : (__DIR__ . '/../db/locks');
$lockPath = rtrim($lockDir, '/') . '/renew_reserve_cron.lock';
$fp = fopen($lockPath, 'c');

if($fp === false || !flock($fp, LOCK_EX | LOCK_NB)){
    if(is_resource($fp)){
        fclose($fp);
    }

    fwrite(STDERR, "renew_reserve_cron: another worker is running\n");
    exit(0);
}

try{
    $limit = intval(getenv('PNV_RENEW_RESERVE_BATCH') ?: 20);
    $summary = renewReserveProcessQueue($limit);
    echo json_encode([
        'ok' => true,
        'at' => date('c'),
        'summary' => $summary,
    ], JSON_UNESCAPED_UNICODE) . "\n";
}
finally{
    flock($fp, LOCK_UN);
    fclose($fp);
}

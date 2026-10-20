#!/usr/bin/env php
<?php

/**
 * Health check: Worker heartbeat + Tejarat verify state (CLI, بدون چاپ secret)
 * php scripts/tejarat-health-check.php
 */

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/tejarat_auto_verify_lib.php';
require_once $root . '/tejarat_worker_lib.php';

$config = tejaratVerifyLoadConfig();
$state = tejaratVerifyLoadState();
$worker = tejaratWorkerAdminSnapshot($config);

$ok = true;
$lines = [];

if(empty($config['enabled'])){
    $lines[] = 'module=disabled';
}
else{
    $lines[] = 'module=enabled dry_run=' . (!empty($config['dry_run']) ? '1' : '0');
    $lines[] = 'provider=' . ($config['provider'] ?? '');
    $lines[] = 'connection=' . ($state['connection_status'] ?? '');
    $lines[] = 'last_success_at=' . intval($state['last_success_at'] ?? 0);

    if(strtolower((string)($config['provider'] ?? '')) === 'browser_worker'){
        $lines[] = 'worker_session=' . ($worker['session_status'] ?? '');
        $lines[] = 'worker_heartbeat_fresh=' . (!empty($worker['heartbeat_fresh']) ? '1' : '0');
        $lines[] = 'worker_pending_inbox=' . intval($worker['pending_inbox'] ?? 0);
        $lines[] = 'ingest_configured=' . (!empty($worker['ingest_configured']) ? '1' : '0');

        if(empty($worker['ingest_configured'])){
            $ok = false;
        }

        if(!empty($config['enabled']) && empty($config['dry_run']) && empty($worker['heartbeat_fresh'])){
            $ok = false;
        }
    }
}

fwrite(STDOUT, implode("\n", $lines) . "\n");
exit($ok ? 0 : 1);

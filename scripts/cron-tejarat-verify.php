#!/usr/bin/env php
<?php

/**
 * Cron: تأیید خودکار واریز تجارت
 * نمونه crontab (هر دقیقه): php /path/to/pnv-panel/scripts/cron-tejarat-verify.php
 */

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/tejarat_auto_verify_lib.php';

$config = tejaratVerifyLoadConfig();

if(!tejaratVerifyIsRunnable($config)){
    fwrite(STDOUT, "tejarat verify: disabled\n");
    exit(0);
}

$interval = max(15, intval($config['poll_interval_seconds'] ?? 60));
$state = tejaratVerifyLoadState();
$last = intval($state['last_poll_at'] ?? 0);

if($last > 0 && (time() - $last) < $interval){
    fwrite(STDOUT, "tejarat verify: skipped (interval {$interval}s)\n");
    exit(0);
}

$result = tejaratVerifyPollOnce();

if(!empty($result['skipped'])){
    fwrite(STDOUT, "tejarat verify: skipped\n");
    exit(0);
}

if(empty($result['ok'])){
    fwrite(STDERR, 'tejarat verify error: ' . ($result['error'] ?? 'unknown') . "\n");
    exit(2);
}

$tx = intval($result['transactions'] ?? 0);
$confirmed = 0;

foreach((array)($result['results'] ?? []) as $row){
    if(!empty($row['ok']) && empty($row['dry_run'])){
        $confirmed++;
    }
}

fwrite(STDOUT, "tejarat verify: tx={$tx} confirmed={$confirmed} dry_run=" . (!empty($result['dry_run']) ? '1' : '0') . "\n");
exit(0);

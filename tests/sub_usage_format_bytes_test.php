<?php

require_once __DIR__ . '/../sub_usage_lib.php';

$gb = 1024 * 1024 * 1024;
$mb = 1024 * 1024;
$kb = 1024;

function assertFmt($bytes, $expected, $label){
    $got = subUsageFormatBytes($bytes);

    if($got !== $expected){
        fwrite(STDERR, "FAIL {$label}: expected \"{$expected}\", got \"{$got}\"\n");
        exit(1);
    }

    echo "ok {$label}: {$got}\n";
}

assertFmt(140 * $gb, '140 GB', '140 GB');
assertFmt(150 * $gb, '150 GB', '150 GB');
assertFmt(100 * $gb, '100 GB', '100 GB');
assertFmt(50 * $gb, '50 GB', '50 GB');
assertFmt(10 * $gb, '10 GB', '10 GB');

assertFmt((int) round(49.9 * $gb), '49.9 GB', '49.90 GB');
assertFmt(49 * $gb, '49 GB', '49.00 GB');
assertFmt((int) round(9.5 * $gb), '9.5 GB', '9.50 GB');
assertFmt((int) round(1.25 * $gb), '1.25 GB', '1.25 GB');

assertFmt(512, '512 B', 'bytes');
assertFmt(1536, '1.5 KB', 'KB decimal');
assertFmt(5 * $mb, '5 MB', 'MB whole');
assertFmt(1024 * $gb, '1 TB', 'TB');

echo "all format bytes tests passed\n";

<?php

require_once __DIR__ . '/../backup_lib.php';

$sections = pnvBackupSections();
if(count($sections) < 8){
    fwrite(STDERR, "expected at least 8 backup sections\n");
    exit(1);
}

$files = pnvBackupCollectFiles(['users', 'payments']);
$labels = pnvBackupSectionLabelsFa();

if(empty($labels['users']) || empty($labels['payments'])){
    fwrite(STDERR, "missing fa labels\n");
    exit(1);
}

echo "ok sections=" . count($sections) . " sample_files=" . count($files) . "\n";

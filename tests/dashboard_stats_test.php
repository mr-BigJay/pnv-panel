<?php

require_once __DIR__ . '/../subscription_lib.php';

$sampleUser = null;
$file = pnvPaymentsCsvPath();

if(is_file($file)){
    $handle = fopen($file, 'r');

    while(($row = fgetcsv($handle)) !== false){
        $u = trim((string)($row[0] ?? ''));

        if($u !== ''){
            $sampleUser = $u;
            break;
        }
    }

    fclose($handle);
}

if($sampleUser === null){
    echo "skip no payments\n";
    exit(0);
}

$stats = pnvDashboardUserPaymentStats($sampleUser);
$subs = pnvLoadUserActiveSubscriptions($sampleUser, false);

$pendingBuys = 0;
$pendingRenews = 0;

if(is_file($file)){
    $handle = fopen($file, 'r');

    while(($row = fgetcsv($handle)) !== false){
        if(($row[0] ?? '') !== $sampleUser){
            continue;
        }

        $status = trim((string)($row[6] ?? ''));
        $type = trim((string)($row[9] ?? ''));

        if($status !== 'تایید شد' && $status !== 'رد شد'){
            if($type === 'تمدید'){
                $pendingRenews++;
            }
            else{
                $pendingBuys++;
            }
        }
    }

    fclose($handle);
}

if((int)$stats['approved_subs'] !== count($subs)){
    fwrite(STDERR, "approved mismatch: {$stats['approved_subs']} vs " . count($subs) . "\n");
    exit(1);
}

if((int)$stats['pending_buys'] !== $pendingBuys){
    fwrite(STDERR, "pending buys mismatch: {$stats['pending_buys']} vs {$pendingBuys}\n");
    exit(1);
}

if((int)$stats['pending_renews'] !== $pendingRenews){
    fwrite(STDERR, "pending renews mismatch: {$stats['pending_renews']} vs {$pendingRenews}\n");
    exit(1);
}

echo "ok user={$sampleUser}\n";

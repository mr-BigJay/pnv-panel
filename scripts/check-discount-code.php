<?php

if(PHP_SAPI !== 'cli'){
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

$code = strtoupper(trim($argv[1] ?? 'THISISFORYOU'));
$root = dirname(__DIR__);

require_once $root . '/campaign_lib.php';
require_once $root . '/coupon_lib.php';
require_once $root . '/plan_ui_lib.php';

$path = campaignDataPath('discount_codes');
$status = campaignDataFileStatus('discount_codes');
$codes = campaignDiscountCodesLoad();
$row = campaignFindDiscountByCode($codes, $code);
$plans = pnvLoadPlans();

echo "file: {$path}\n";
echo "file_status: {$status}\n";
echo "loaded_codes: " . count($codes) . "\n";
echo "lookup: " . ($row ? 'found' : 'not_found') . "\n";

if(!$row){
    exit(1);
}

echo "code: " . ($row['code'] ?? '') . "\n";
echo "status: " . ($row['status'] ?? 'active') . "\n";
echo "type: " . ($row['type'] ?? 'percent') . "\n";
echo "value: " . ($row['value'] ?? 0) . "\n";
echo "starts_at: " . ($row['starts_at'] ?? 0) . "\n";
echo "expires_at: " . ($row['expires_at'] ?? 0) . "\n";
echo "active: " . (campaignDiscountIsActiveRow($row) ? 'yes' : 'no') . "\n";

if(!campaignDiscountIsActiveRow($row)){
    echo "inactive_reason: " . campaignDiscountInactiveReason($row) . "\n";
}

foreach(pnvPlansForStepUi($plans) as $planUi){
    $value = trim((string)($planUi['value'] ?? ''));

    if($value === ''){
        continue;
    }

    $result = checkoutCalculateDiscountCode('cli-test-user', $code, $value, $plans);
    $label = $planUi['name'] ?? $value;
    echo $label . ': ' . (!empty($result['ok']) ? 'ok' : ($result['error'] ?? 'fail')) . "\n";
}

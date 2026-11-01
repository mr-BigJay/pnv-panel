<?php
/**
 * Tests of the read-only historic timed-plan audit classifier.
 * No network calls, payment writes or 3x-ui mutations.
 */
require_once __DIR__ . '/../scripts/audit-old-buy-expiry.php';
require_once __DIR__ . '/../xui_lib.php';

$failed = 0;
function auditAssert($value, $label){
    global $failed;
    echo ($value ? 'PASS ' : 'FAIL ') . $label . "\n";
    if(!$value){
        $failed++;
    }
}

$paid = pnvExpiryAuditPaidIndex([[
    'user' => 'alice',
    'code' => '1000',
    'status' => 'paid',
    'paid_at' => 1700000000,
    'type' => 'خرید',
    'link' => 'https://example.test:2096/sub/abcd1234',
]]);
auditAssert(intval($paid['alice|AUTO-1000']['paid_at'] ?? 0) === 1700000000, 'unique paid timestamp indexed');

$duplicate = pnvExpiryAuditPaidIndex([
    ['user' => 'alice', 'code' => '1000', 'status' => 'paid', 'paid_at' => 1700000000],
    ['user' => 'alice', 'code' => '1000', 'status' => 'paid', 'paid_at' => 1700001000],
]);
auditAssert(array_key_exists('alice|AUTO-1000', $duplicate)
    && $duplicate['alice|AUTO-1000'] === null, 'duplicate paid records treated as untrustworthy');

$baseline = [
    'history_conflict' => false,
    'paid_source_ok' => true,
    'server_ok' => true,
    'matching_clients' => 1,
    'has_expiry_field' => true,
    'current_expiry_ms' => 0,
    'enabled' => true,
    'quota_matches' => true,
    'expected_ms' => 1800000000000,
    'now_ms' => 1750000000000,
];

auditAssert(pnvExpiryAuditClassification($baseline) === 'candidate_review_before_apply', 'one verified future timed plan is a review candidate');

foreach([
    ['history_conflict', true, 'history_conflict'],
    ['renewal_sources_unavailable', true, 'renewal_sources_unavailable'],
    ['renewal_history_unknown', true, 'renewal_history_unknown'],
    ['renewal_payment_detected', true, 'renewal_payment_detected'],
    ['renew_reservation_detected', true, 'renew_reservation_detected'],
    ['paid_source_ok', false, 'paid_timestamp_unverified'],
    ['server_ok', false, 'server_read_unavailable'],
    ['matching_clients', 0, 'client_not_found'],
    ['matching_clients', 2, 'duplicate_client'],
    ['has_expiry_field', false, 'expiry_field_unavailable'],
    ['current_expiry_ms', 1800000000000, 'already_has_expiry'],
    ['enabled', false, 'client_disabled'],
    ['quota_matches', false, 'quota_changed_or_unknown'],
    ['expected_ms', 1700000000000, 'historical_plan_already_elapsed'],
] as $case){
    $ctx = $baseline;
    $ctx[$case[0]] = $case[1];
    auditAssert(pnvExpiryAuditClassification($ctx) === $case[2], $case[2] . ' skip');
}

$link = 'https://vip3.example.test:2096/sub/abcd1234';
$renewOrder = [[
    'user'=>'ALICE', 'type'=>'تمدید', 'status'=>'paid', 'sub'=>$link,
]];
$reservation = [[
    'username'=>'alice', 'status'=>'reserved',
    'server_host'=>'vip4.example.test', 'sub_id'=>'abcd1234',
]];
$csvRenewal = [[
    'alice', 'https://vip-other.example.test:2096/sub/abcd1234', 'plan', '', '', '', 'تایید شد', '', '', 'تمدید',
]];
$signals = pnvExpiryAuditRenewalSignals($renewOrder, $reservation, $csvRenewal);
auditAssert(!empty($signals['instant_renewals']['alice|abcd1234']), 'renewals found even when host aliases differ');
auditAssert(!empty($signals['reservation_sub_ids']['abcd1234']), 'queued renewal detected via subId');
auditAssert(empty($signals['unknown_users']['alice']), 'no false missing linkage when renewal references are valid');
$missingLink = pnvExpiryAuditRenewalSignals([[
    'user'=>'alice', 'type'=>'تمدید', 'status'=>'paid', 'link'=>'',
]], [], []);
auditAssert(!empty($missingLink['unknown_users']['alice']), 'unlinked renewal of user blocks automatic repair');
$malformedQueue = pnvExpiryAuditRenewalSignals([], ['not-a-record']);
auditAssert(!empty($malformedQueue['ambiguous']), 'malformed reservation marks evidence ambiguous');
$clean = pnvExpiryAuditRenewalSignals([], [], []);
auditAssert(empty($clean['instant_renewals']) && empty($clean['reservation_sub_ids']), 'clean history leaves candidates unchanged');

$source = file_get_contents(__DIR__ . '/../scripts/audit-old-buy-expiry.php');
auditAssert(strpos($source, "'GET', '/panel/api/inbounds/list'") !== false, 'audit uses read-only inbound list');
auditAssert(strpos($source, "'POST', '/panel/api/inbounds/updateClient'") === false, 'no legacy mutation endpoint');
auditAssert(strpos($source, "'POST', '/panel/api/clients/update'") === false, 'no modern mutation endpoint');

if($failed){
    fwrite(STDERR, "FAIL {$failed} audit tests\n");
    exit(1);
}
echo "All historical expiry audit tests passed\n";

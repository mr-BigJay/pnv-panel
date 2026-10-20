#!/usr/bin/env python3
"""Test minimal step3 deploy on synthesized customized live files (never real production)."""
from pathlib import Path
import os
import runpy
import shutil
import subprocess
import tempfile

repo = Path(__file__).resolve().parent.parent
source = repo
installer = repo / 'scripts/deploy-renew-step3-safe.py'
helpers = runpy.run_path(str(installer))
portion = helpers['portion']
one = helpers['one']
fn_block = helpers['fn_block']

admin = (source / 'admin/renews.php').read_text()
new_admin = portion(
    admin,
    '// The subscription link is normally present after successful XUI approval.',
    '\n$renewOutcome =',
    'source admin',
)
old_admin = """if(
    $detailLink === ''
    && function_exists('instantPayMarkPaidFromCsv')
    && function_exists('instantPayCsvRowPending')
    && isset($payments[$index])
    && instantPayCsvRowPending($payments[$index])
){
$sync = instantPayMarkPaidFromCsv($index, []);

if(!empty($sync['ok'])){
if(is_array($sync['item'] ?? null)){
$detailLink = trim((string)($sync['item']['link'] ?? ''));
}

if($detailLink === '' && is_array($sync['provision'] ?? null)){
$detailLink = trim((string)($sync['provision']['link'] ?? ''));
}
}
}
"""
old_admin_file = one(admin, new_admin, old_admin, 'restore admin baseline')

lib = (source / 'instant_pay_lib.php').read_text()
addition = portion(
    lib,
    '    function instantPayRenewOrderBindsCsv(',
    '    function instantPayRenewProvisionEvidence(',
    'source helpers',
)
old_lib_file = one(lib, addition, '', 'restore lib baseline helpers')
new_fn = fn_block(old_lib_file, 'instantPaySyncPaidFromConfirmedCsvLocked')
source_selector = portion(
    new_fn,
    '        $items = instantPayLoad();\n        $matches = [];',
    '        $currentStatus =',
    'source order selection',
)
old_selector = """        $user = trim((string)($row[0] ?? ''));
        $tracking = trim((string)($row[3] ?? ''));
        $jsonItem = instantPayFindJsonByTracking($user, $tracking);

        if(!is_array($jsonItem) || trim((string)($jsonItem['id'] ?? '')) === ''){
            return ['ok' => true, 'skipped' => true];
        }

        $orderId = trim((string)($jsonItem['id'] ?? ''));
        $items = instantPayLoad();
        $idx = -1;

        foreach($items as $i => $item){
            if(($item['id'] ?? '') === $orderId){
                $idx = $i;
                break;
            }
        }

        if($idx < 0){
            return ['ok' => true, 'skipped' => true];
        }

"""
old_lib_file = one(old_lib_file, source_selector, old_selector, 'restore lib selection')

api = (source / 'instant-pay-api.php').read_text()
new_api = portion(
    api,
    '    $publicItem = instantPayPublicView($item);',
    '\n}\n\nif($action === \'cancel\')',
    'source status',
).rstrip('\n')
old_api = """    echo json_encode([
        'ok' => true,
        'item' => instantPayPublicView($item)
    ], JSON_UNESCAPED_UNICODE);
    exit;"""
old_api_file = one(api, new_api, old_api, 'restore API baseline')

with tempfile.TemporaryDirectory(prefix='pnv-step3-deploy-test-') as work:
    root = Path(work) / 'site'
    backups = Path(work) / 'backups'
    custom = {
        'admin/renews.php': old_admin_file,
        'instant_pay_lib.php': old_lib_file,
        'instant-pay-api.php': old_api_file,
    }
    for name, text in custom.items():
        target = root / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(text + '\n// CUSTOM_LIVE_PANEL_SENTINEL_PRESERVE\n')
    old_bytes = {name: (root / name).read_bytes() for name in custom}

    def run(*opts, expected=0):
        cmd = ['python3', str(installer), '--source', str(repo), '--root', str(root),
               '--backup-dir', str(backups), '--no-reload', *opts]
        p = subprocess.run(cmd, text=True, capture_output=True)
        if p.returncode != expected:
            raise AssertionError(f"{cmd} returned {p.returncode}\n{p.stdout}\n{p.stderr}")
        return p.stdout + p.stderr

    output = run()
    assert 'SAFE DRY RUN PASS' in output
    assert all((root / name).read_bytes() == old_bytes[name] for name in custom)
    print('PASS dry-run does not mutate live files')

    output = run('--apply')
    assert 'STEP3 FIX DEPLOYED' in output
    assert all('CUSTOM_LIVE_PANEL_SENTINEL_PRESERVE' in (root / name).read_text() for name in custom)
    assert 'instantPayRecoverConfirmedRenewStatus' in (root / 'instant-pay-api.php').read_text()
    assert 'instantPayRenewOrderBindsCsv' in (root / 'instant_pay_lib.php').read_text()
    assert 'instantPaySyncPaidFromConfirmedCsv($index)' in (root / 'admin/renews.php').read_text()
    assert len(list(backups.glob('pnv-step3-*/admin/renews.php'))) == 1
    print('PASS selective install preserves custom live additions and backups')

    installed = {name: (root / name).read_bytes() for name in custom}
    output = run('--apply')
    assert 'ALREADY DEPLOYED' in output
    assert all((root / name).read_bytes() == installed[name] for name in custom)
    print('PASS second application is idempotent')

    # A partially customized source that changes an expected anchor must stop
    # before any other production file can be modified.
    changed_admin = (root / 'admin/renews.php').read_text()
    (root / 'admin/renews.php').write_text(changed_admin.replace(
        '// The subscription link is normally present after successful XUI approval.',
        '// incomplete unknown custom approval implementation',
    ))
    before_failure = {name: (root / name).read_bytes() for name in custom}
    output = run('--apply', expected=1)
    assert 'STOP admin renew approval' in output
    assert all((root / name).read_bytes() == before_failure[name] for name in custom)
    print('PASS unexpected live structure fails before touching any files')

print('All selective step3 deployment safety tests passed')

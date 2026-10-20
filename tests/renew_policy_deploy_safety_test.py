#!/usr/bin/env python3
"""CI contract: safe selective deploy tolerates custom production files."""
from pathlib import Path
import runpy
import subprocess
import tempfile

repo = Path(__file__).resolve().parent.parent
installer = repo / "scripts/deploy-renew-policy-safe.py"
m = runpy.run_path(str(installer))
replace = m["replace_exact"]
fn = m["function_block"]
src_xui = (repo / "xui_lib.php").read_text()
src_reserve = (repo / "renew_reserve_lib.php").read_text()

# Synthesize exactly the pre-policy live version, preserving existing PR39.
pre_xui = src_xui
pre_xui = replace(pre_xui,
    """        if($addDays <= 0){
            // A volume-only renewal must not silently change subscription expiry.
            return abs(intval($snap['expiry_ms'] ?? 0) - intval($baseline['expiry_ms'] ?? 0)) <= 180000;
        }""",
    """        if($addDays <= 0){
            return true;
        }""",
    "reverse volume verification")
pre_xui = replace(pre_xui,
    """            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);
            // The policy depends on the purchased plan, not the current client's duration.
            $action = renewReserveDecideAction($runtime, $days);""",
    """            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);
            $action = renewReserveDecideAction($runtime);

            if(empty($duration['time_unlimited']) && $days > 0 && !empty($runtime['time_expired'])){
                $action = 'activate_now';
            }""",
    "reverse duration action")
pre_xui = replace(pre_xui,
    """            if($action === 'immediate_unlimited'){
                // 0 in 3x-ui means unlimited traffic or an incomplete client record.
                // Adding a finite cap would reduce that entitlement. Fail safely.
                if(xuiClientTotalBytes($client) <= 0){
                    return [
                        'ok' => false,
                        'error' => 'حجم فعلی اشتراک معتبر نیست؛ افزایش حجم بدون محدودسازی اشتراک ممکن نیست',
                        'code' => 'invalid_quota_baseline',
                    ];
                }

                $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];""",
    """            if($action === 'immediate_unlimited'){
                $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];""",
    "reverse quota guard")
pre_xui = replace(pre_xui,
    """                    if($adjustCode === 'transport_error'){
                        // An XUI timeout may occur AFTER quota was updated.
                        // Read back and verify first; never send the mutation again.
                        $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);""",
    """                    if($adjustCode === 'transport_error' && $days > 0){
                        $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);""",
    "reverse readback")

pre_res = src_reserve
new_decision = fn(src_reserve, "renewReserveDecideAction")
old_decision = """    function renewReserveDecideAction(array $runtime){
        if(!empty($runtime['ambiguous'])){
            return 'ambiguous';
        }

        // نامحدود زمانی + حجم باقی‌مانده → رزرو (تا اتمام حجم/زمان فعال شود).
        if(($runtime['time_unlimited'] ?? null) === true){
            return !empty($runtime['volume_exhausted']) ? 'immediate_unlimited' : 'reserve';
        }

        if(!empty($runtime['should_activate_now'])){
            return 'activate_now';
        }

        return 'reserve';
    }

"""
pre_res = replace(pre_res, new_decision, old_decision, "reverse decision")
pre_res = replace(pre_res, fn(src_reserve, "renewReserveActivateQueuedUnlimited"), "", "reverse queued helper")
new_rec_fn = fn(src_reserve, "renewReserveRecoverStaleActivating")
start = new_rec_fn.find("        $client = $lookup['client'];\n\n        // Legacy time-unlimited reservations")
stop = new_rec_fn.find("        if(xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs)){", start)
assert start >= 0 and stop > start
new_stale = new_rec_fn[start:stop] + "        if(xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs)){"
old_stale = """        $client = $lookup['client'];

        if(xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs)){"""
pre_res = replace(pre_res,new_stale,old_stale,"reverse queued stale recovery")
act = fn(src_reserve, "renewReserveActivateOne")
start = act.find("            $client = $lookup['client'] ?? null;\n\n            // Existing reservations")
stop = act.find("            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);", start)
assert start >= 0 and stop > start
new_queue = act[start:stop] + "            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);"
old_queue = """            $client = $lookup['client'] ?? null;
            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);"""
pre_res = replace(pre_res,new_queue,old_queue,"reverse queue switch")

with tempfile.TemporaryDirectory(prefix="pnv-policy-deploy-test-") as work:
    root=Path(work)/"site"
    root.mkdir()
    (root/"xui_lib.php").write_text(pre_xui+"\n// CUSTOM LIVE SENTINEL XUI PRESERVED\n")
    (root/"renew_reserve_lib.php").write_text(pre_res+"\n// CUSTOM LIVE SENTINEL RESERVE PRESERVED\n")
    backups=Path(work)/"backups"
    old={n:(root/n).read_bytes() for n in ("xui_lib.php","renew_reserve_lib.php")}

    def run(*extra, expected=0):
        p=subprocess.run(["python3",str(installer),"--source",str(repo),
            "--root",str(root),"--backup-dir",str(backups),"--no-reload",*extra],
            capture_output=True,text=True)
        if p.returncode != expected:
            raise AssertionError(f"unexpected exit {p.returncode}\n{p.stdout}\n{p.stderr}")
        return p.stdout+p.stderr

    out=run()
    assert "SAFE DRY RUN PASS" in out
    assert all((root/n).read_bytes()==old[n] for n in old)
    print("PASS safe dry-run leaves files unchanged")

    out=run("--apply")
    assert "RENEW POLICY DEPLOYED" in out
    new={n:(root/n).read_bytes() for n in old}
    assert all(b"CUSTOM LIVE SENTINEL" in new[n] for n in new)
    assert len(list(backups.glob("pnv-renew-policy-*/xui_lib.php")))==1
    assert len(list(backups.glob("pnv-renew-policy-*/renew_reserve_lib.php")))==1
    assert "renewReserveDecideAction($runtime, $days)" in new["xui_lib.php"].decode()
    assert "renewReserveActivateQueuedUnlimited" in new["renew_reserve_lib.php"].decode()
    print("PASS selective deploy preserves custom production changes and saves backups")

    out=run("--apply")
    assert "ALREADY DEPLOYED" in out
    assert all((root/n).read_bytes()==new[n] for n in old)
    print("PASS repeat deploy is idempotent")

    live=(root/"xui_lib.php").read_text()
    (root/"xui_lib.php").write_text(live.replace(
        "// The policy depends on the purchased plan, not the current client's duration.",
        "// unexpected partial custom policy",
    ))
    before={n:(root/n).read_bytes() for n in old}
    out=run("--apply",expected=1)
    assert "STOP mismatched production structure" in out
    assert all((root/n).read_bytes()==before[n] for n in before)
    print("PASS unknown live structure refuses all changes")

print("All renew-policy deployment safety tests passed")

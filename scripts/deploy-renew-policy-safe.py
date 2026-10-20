#!/usr/bin/env python3
"""Selective, fail-closed PNV renew-policy deploy for customized production.

Usage:
  sudo python3 scripts/deploy-renew-policy-safe.py --source /path/to/pr-source
  sudo python3 scripts/deploy-renew-policy-safe.py --source /path/to/pr-source --apply

Only xui_lib.php and renew_reserve_lib.php may change. Dry-run by default.
No XUI POSTs, payment writes, or queue processing during deployment.
"""
import argparse
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile


FILES = ("xui_lib.php", "renew_reserve_lib.php")


def replace_exact(text, old, new, desc):
    if old == new:
        raise RuntimeError("STOP invalid patch: " + desc)
    if new in text and old not in text:
        return text
    if text.count(old) != 1:
        raise RuntimeError("STOP mismatched production structure: " + desc)
    return text.replace(old, new, 1)


def function_block(text, name):
    match = re.search(r"(?m)^    function " + re.escape(name) + r"\(", text)
    if not match:
        raise RuntimeError("STOP missing function " + name)
    rest = re.search(r"(?m)^    function ", text[match.end():])
    end = match.end() + rest.start() if rest else len(text)
    return text[match.start():end]


def patch_xui(live, source):
    old = """        if($addDays <= 0){
            return true;
        }"""
    new = """        if($addDays <= 0){
            // A volume-only renewal must not silently change subscription expiry.
            return abs(intval($snap['expiry_ms'] ?? 0) - intval($baseline['expiry_ms'] ?? 0)) <= 180000;
        }"""
    fn = function_block(live, "xuiRenewVerifyIncrementalAdjust")
    patched_fn = replace_exact(fn, old, new, "volume verification")
    if patched_fn != fn:
        live = replace_exact(live, fn, patched_fn, "volume verifier function")

    old = """            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);
            $action = renewReserveDecideAction($runtime);

            if(empty($duration['time_unlimited']) && $days > 0 && !empty($runtime['time_expired'])){
                $action = 'activate_now';
            }"""
    new = """            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);
            // The policy depends on the purchased plan, not the current client's duration.
            $action = renewReserveDecideAction($runtime, $days);"""
    live = replace_exact(live, old, new, "duration-based route")

    old = """            if($action === 'immediate_unlimited'){
                $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];"""
    new = """            if($action === 'immediate_unlimited'){
                // 0 in 3x-ui means unlimited traffic or an incomplete client record.
                // Adding a finite cap would reduce that entitlement. Fail safely.
                if(xuiClientTotalBytes($client) <= 0){
                    return [
                        'ok' => false,
                        'error' => 'حجم فعلی اشتراک معتبر نیست؛ افزایش حجم بدون محدودسازی اشتراک ممکن نیست',
                        'code' => 'invalid_quota_baseline',
                    ];
                }

                $stored = xuiRenewProvisionStoreRead($provisionKey) ?? [];"""
    live = replace_exact(live, old, new, "unlimited baseline guard")

    old = """                    if($adjustCode === 'transport_error' && $days > 0){
                        $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);"""
    new = """                    if($adjustCode === 'transport_error'){
                        // An XUI timeout may occur AFTER quota was updated.
                        // Read back and verify first; never send the mutation again.
                        $verifyLookup = xuiRenewLookupClient($server, $parsed['sub_id'], $link);"""
    live = replace_exact(live, old, new, "readback on timeout")

    for marker in ["renewReserveDecideAction($runtime, $days)", "invalid_quota_baseline"]:
        if marker not in live:
            raise RuntimeError("STOP required XUI policy marker missing")
    return live


def patch_reserve(live, source):
    old_decision = function_block(live, "renewReserveDecideAction")
    new_decision = function_block(source, "renewReserveDecideAction")
    if old_decision != new_decision:
        if "function renewReserveDecideAction(array $runtime){" not in old_decision:
            raise RuntimeError("STOP unexpected existing reserve decision policy")
        live = replace_exact(live, old_decision, new_decision, "reserve action policy")

    helper_name = "renewReserveActivateQueuedUnlimited"
    marker = "    function " + helper_name + "("
    if marker not in live:
        new_helper = function_block(source, helper_name)
        live = replace_exact(
            live,
            "    function renewReserveActivateOne(",
            new_helper + "    function renewReserveActivateOne(",
            "legacy unlimited reserve helper",
        )

    old_recovery = """        $client = $lookup['client'];

        if(xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs)){"""
    new_recovery_start = """        $client = $lookup['client'];

        // Legacy time-unlimited reservations are additive, never absolute."""
    source_fn = function_block(source, "renewReserveRecoverStaleActivating")
    a = source_fn.find(new_recovery_start)
    b = source_fn.find("        if(xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs)){", a)
    if a < 0 or b < 0:
        raise RuntimeError("STOP missing safe stale recovery in source")
    new_recovery = source_fn[a:b] + "        if(xuiRenewVerifyAbsolutePlan($client, $gb, $days, $anchorMs)){"
    live = replace_exact(live, old_recovery, new_recovery, "stale queued volume verification")

    old_queue = """            $client = $lookup['client'] ?? null;
            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);"""
    source_activate = function_block(source, "renewReserveActivateOne")
    new_start = """            $client = $lookup['client'] ?? null;

            // Existing reservations created under the old policy:"""
    a = source_activate.find(new_start)
    b = source_activate.find("            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);", a)
    if a < 0 or b < 0:
        raise RuntimeError("STOP missing queue switch in source")
    new_queue = source_activate[a:b] + "            $runtime = xuiRenewAnalyzeRuntimeState($server, $client, $subLink);"
    live = replace_exact(live, old_queue, new_queue, "old unlimited queue activation")

    if "renewReserveActivateQueuedUnlimited($fresh, $server, $client, $subLink)" not in live:
        raise RuntimeError("STOP missing queued unlimited action")
    return live


def run(args):
    root = Path(args.root).resolve(strict=True)
    source = Path(args.source).resolve(strict=True)
    if not all((source / x).is_file() for x in FILES):
        raise RuntimeError("STOP incomplete source: required files missing")
    if not all((root / x).is_file() and not (root / x).is_symlink() for x in FILES):
        raise RuntimeError("STOP invalid live file(s)")

    old = {n:(root / n).read_bytes() for n in FILES}
    current = {n:old[n].decode("utf-8") for n in FILES}
    new = {
        "xui_lib.php": patch_xui(current["xui_lib.php"], (source / "xui_lib.php").read_text()),
        "renew_reserve_lib.php": patch_reserve(current["renew_reserve_lib.php"], (source / "renew_reserve_lib.php").read_text()),
    }
    changes = {n:new[n].encode("utf-8") for n in FILES}
    if "xuiApiCanRetryAuth" not in new["xui_lib.php"]:
        raise RuntimeError("STOP PR39 XUI safety patch not present on production")
    with tempfile.TemporaryDirectory(prefix="pnv-renew-policy-") as temp:
        for name in FILES:
            target = Path(temp) / name
            target.write_bytes(changes[name])
            r = subprocess.run(["php", "-l", str(target)], capture_output=True, text=True)
            if r.returncode:
                raise RuntimeError("STOP PHP lint failed: " + name + "\n" + r.stderr)
        print("PHP LINT: 2/2 PASS")
        changed = [n for n in FILES if changes[n] != old[n]]
        for name in FILES:
            print(name + ": " + ("PATCHED" if name in changed else "UNCHANGED"))
        if not changed:
            print("ALREADY DEPLOYED")
            return
        if not args.apply:
            print("SAFE DRY RUN PASS; no production files changed")
            return

        backup_parent = Path(args.backup_dir)
        backup_parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        backup = Path(tempfile.mkdtemp(prefix="pnv-renew-policy-", dir=str(backup_parent)))
        backup.chmod(0o700)
        for name in FILES:
            shutil.copy2(root / name, backup / name)
        print("BACKUP:", backup)

        if any((root / n).read_bytes() != old[n] for n in FILES):
            raise RuntimeError("STOP production files changed during validation")

        installed = []
        try:
            for name in changed:
                live = root / name
                stat = live.stat()
                fd, staging = tempfile.mkstemp(prefix=".pnv-renew-policy-", suffix=".php", dir=str(root))
                try:
                    with os.fdopen(fd, "wb") as out:
                        out.write(changes[name])
                    os.chown(staging, stat.st_uid, stat.st_gid)
                    os.chmod(staging, stat.st_mode)
                    os.replace(staging, live)
                    installed.append(name)
                finally:
                    if os.path.exists(staging):
                        os.unlink(staging)

            for name in changed:
                subprocess.run(["php", "-l", str(root / name)], check=True, stdout=subprocess.DEVNULL)
            if not args.no_reload:
                subprocess.run(["systemctl", "reload", "php8.3-fpm"], check=True)
        except Exception:
            for name in installed:
                shutil.copy2(backup / name, root / name)
            if not args.no_reload:
                subprocess.run(["systemctl", "reload", "php8.3-fpm"], check=False)
            print("ROLLBACK COMPLETED", file=sys.stderr)
            raise

        print("RENEW POLICY DEPLOYED")
        print("BACKUP:", backup)


if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("--source", required=True)
    p.add_argument("--root", default="/var/www/html")
    p.add_argument("--backup-dir", default="/root")
    p.add_argument("--apply", action="store_true")
    p.add_argument("--no-reload", action="store_true", help="For isolated test environment only")
    args = p.parse_args()
    try:
        run(args)
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)

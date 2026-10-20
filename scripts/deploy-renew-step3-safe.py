#!/usr/bin/env python3
"""Safely apply only the manual-renew step-3 sync fix to a customized PNV Panel.

Source must be a verified checkout of PR #40 (or a later main containing the
same source markers), transferred locally. No GitHub/network access required.

Dry run:
    sudo python3 scripts/deploy-renew-step3-safe.py --source /opt/pnv-step3-source

Apply:
    sudo python3 scripts/deploy-renew-step3-safe.py --source /opt/pnv-step3-source --apply
"""
import argparse
import hashlib
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime


FILES = ("admin/renews.php", "instant_pay_lib.php", "instant-pay-api.php")


def checksum(data):
    return hashlib.sha256(data).hexdigest()


def one(text, old, new, context):
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"STOP {context}: expected one old anchor, found {count}")
    return text.replace(old, new, 1)


def fn_block(text, name):
    start = re.search(r"(?m)^    function " + re.escape(name) + r"\(", text)
    if not start:
        raise RuntimeError(f"STOP: missing function {name}")
    after = re.search(r"(?m)^    function ", text[start.end():])
    end = start.end() + after.start() if after else len(text)
    return text[start.start():end]


def portion(text, start, end, context):
    a = text.find(start)
    if a < 0:
        raise RuntimeError(f"STOP {context}: opening anchor absent")
    b = text.find(end, a + len(start))
    if b < 0:
        raise RuntimeError(f"STOP {context}: closing anchor absent")
    return text[a:b]


def patch_admin(live, source):
    marker = "// The subscription link is normally present after successful XUI approval."
    if marker in live:
        return live
    begin = "if(\n    $detailLink === ''\n    && function_exists('instantPayMarkPaidFromCsv')"
    end = "\n$renewOutcome ="
    old = portion(live, begin, end, "admin renew approval")
    new = portion(source, marker, end, "admin verified paid sync")
    return one(live, old, new, "admin/renews.php")


def patch_instant_pay_lib(live, source):
    marker = "    function instantPayRenewOrderBindsCsv("
    if marker not in live:
        addition = portion(
            source,
            marker,
            "    function instantPayRenewProvisionEvidence(",
            "order binding and recovery",
        )
        anchor = "    function instantPayRenewProvisionEvidence("
        live = one(live, anchor, addition + anchor, "instant_pay_lib.php helpers")
    elif "    function instantPayRecoverConfirmedRenewStatus(" not in live:
        raise RuntimeError("STOP instant_pay_lib.php: partial patch detected")

    fn = "instantPaySyncPaidFromConfirmedCsvLocked"
    target_fn = fn_block(live, fn)
    source_fn = fn_block(source, fn)
    new_start = "        $items = instantPayLoad();\n        $matches = [];"
    old_start = "        $user = trim((string)($row[0] ?? ''));"
    end = "        $currentStatus ="
    updated_selector = portion(source_fn, new_start, end, "source exact-order selection")

    if new_start in target_fn:
        return live

    old_selector = portion(target_fn, old_start, end, "current order selection")
    changed_fn = one(target_fn, old_selector, updated_selector, "Instant Pay order selection")
    return one(live, target_fn, changed_fn, "Instant Pay sync function")


def patch_status_api(live, source):
    marker = "    $publicItem = instantPayPublicView($item);"
    if marker in live:
        return live
    old = """    echo json_encode([
        'ok' => true,
        'item' => instantPayPublicView($item)
    ], JSON_UNESCAPED_UNICODE);
    exit;"""
    new = portion(source, marker, "\n}\n\nif($action === 'cancel')", "API status response")
    # The snippet ends with exit; and a newline. Leave closing action braces unchanged.
    return one(live, old, new.rstrip("\n"), "instant-pay-api.php status")


def execute(args):
    source = Path(args.source).resolve(strict=True)
    root = Path(args.root).resolve(strict=True)
    patches = {
        "admin/renews.php": patch_admin,
        "instant_pay_lib.php": patch_instant_pay_lib,
        "instant-pay-api.php": patch_status_api,
    }
    original = {}
    modified = {}

    for relative in FILES:
        upstream = source / relative
        target = root / relative
        if not upstream.is_file() or not target.is_file() or target.is_symlink():
            raise RuntimeError(f"STOP missing or unsafe file: {relative}")
        if upstream.stat().st_size < 500 or target.stat().st_size < 500:
            raise RuntimeError(f"STOP unexpectedly short file: {relative}")
        source_text = upstream.read_text(encoding="utf-8")
        live_data = target.read_bytes()
        live_text = live_data.decode("utf-8")
        current = patches[relative](live_text, source_text)
        original[relative] = live_data
        modified[relative] = current.encode("utf-8")

    # Always stage and lint all files before making any production change.
    with tempfile.TemporaryDirectory(prefix="pnv-step3-stage-") as staging:
        for relative in FILES:
            path = Path(staging) / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(modified[relative])
            result = subprocess.run(
                ["php", "-l", str(path)], text=True, capture_output=True, check=False,
            )
            if result.returncode != 0:
                raise RuntimeError(f"STOP PHP lint failed: {relative}: {result.stderr.strip()}")

        changed = [rel for rel in FILES if original[rel] != modified[rel]]
        print("PHP LINT: 3/3 PASS")
        for relative in FILES:
            print(f"{relative}: {'PATCHED' if relative in changed else 'UNCHANGED'}")
        if not changed:
            print("ALREADY DEPLOYED")
            return

        if not args.apply:
            print("SAFE DRY RUN PASS; no production files changed. Use --apply to install.")
            return

        os.umask(0o077)
        backup_root = Path(args.backup_dir)
        backup_root.mkdir(mode=0o700, parents=True, exist_ok=True)
        backups = Path(tempfile.mkdtemp(prefix="pnv-step3-", dir=str(backup_root)))
        backups.chmod(0o700)

        for rel in FILES:
            dest = backups / rel
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(root / rel, dest)

        print("BACKUP:", backups)

        # If even one file changed since preflight, do not deploy.
        for rel in FILES:
            if checksum((root / rel).read_bytes()) != checksum(original[rel]):
                raise RuntimeError(f"STOP production file changed during validation: {rel}")

        installed = []
        try:
            for rel in changed:
                target = root / rel
                fd, temporary = tempfile.mkstemp(prefix=".pnv-step3-", suffix=".php", dir=str(target.parent))
                try:
                    with os.fdopen(fd, "wb") as out:
                        out.write(modified[rel])
                    st = target.stat()
                    os.chmod(temporary, st.st_mode)
                    os.chown(temporary, st.st_uid, st.st_gid)
                    os.replace(temporary, target)
                    installed.append(rel)
                finally:
                    if os.path.exists(temporary):
                        os.unlink(temporary)

            if not args.no_reload:
                subprocess.run(["systemctl", "reload", "php8.3-fpm"], check=True)

            for rel in changed:
                subprocess.run(["php", "-l", str(root / rel)], check=True, stdout=subprocess.DEVNULL)

        except Exception:
            for rel in installed:
                shutil.copy2(backups / rel, root / rel)
            if not args.no_reload:
                subprocess.run(["systemctl", "reload", "php8.3-fpm"], check=False)
            print("ROLLBACK COMPLETED", file=sys.stderr)
            raise

        print("STEP3 FIX DEPLOYED")
        print("Backup:", backups)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", required=True, help="Local PR source directory")
    parser.add_argument("--root", default="/var/www/html")
    parser.add_argument("--backup-dir", default="/root")
    parser.add_argument("--apply", action="store_true")
    parser.add_argument("--no-reload", action="store_true", help="Test staging only; production needs reload")
    options = parser.parse_args()
    try:
        execute(options)
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)

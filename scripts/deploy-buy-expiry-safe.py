#!/usr/bin/env python3
"""Fail-closed selective rollout of the new-purchase expiry fix (PR #54).

Only the four known new-purchase/plan parser functions and two added helpers
are patched in xui_lib.php. Payment, renewal and customized surrounding code
are preserved. Does not call any 3x-ui endpoint, handle payments or edit users.

Dry run:
  sudo python3 scripts/deploy-buy-expiry-safe.py --source /opt/pnv-buy-expiry-source
Apply:
  sudo python3 scripts/deploy-buy-expiry-safe.py --source /opt/pnv-buy-expiry-source --apply
"""
import argparse
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile

# Immutable base of PR #54, used to verify the exact original function bodies.
BASE_SHA = "214af436482b45d2bd0ebae3345bb4fd00c6e3f1"
CHANGED = (
    "xuiPlanDurationMeta",
    "xuiParsePlanGb",
    "xuiCreateClient",
    "xuiProvisionBuy",
)
NEW = (
    ("xuiNormalizePlanDigits", "xuiPlanDurationMeta"),
    ("xuiResolveNewClientPlanDuration", "xuiProvisionBuy"),
)


def function_block(text, name):
    start = re.search(r"(?m)^    function " + re.escape(name) + r"\\(", text)
    if not start:
        raise RuntimeError("STOP missing function: " + name)
    next_fn = re.search(r"(?m)^    function ", text[start.end():])
    end = start.end() + next_fn.start() if next_fn else len(text)
    return text[start.start():end]


def patch(live, base, new):
    for name in CHANGED:
        original = function_block(base, name)
        desired = function_block(new, name)
        current = function_block(live, name)
        if current == desired:
            continue
        if current != original:
            raise RuntimeError("STOP customized function differs; manual review required: " + name)
        if live.count(original) != 1:
            raise RuntimeError("STOP duplicate original function: " + name)
        live = live.replace(original, desired, 1)

    for name, before in NEW:
        desired = function_block(new, name)
        marker = "    function " + name + "("
        if marker in live:
            if function_block(live, name) != desired:
                raise RuntimeError("STOP pre-existing modified helper: " + name)
            continue
        anchor = "    function " + before + "("
        if live.count(anchor) != 1:
            raise RuntimeError("STOP helper insertion anchor missing: " + before)
        live = live.replace(anchor, desired + anchor, 1)

    if "xuiCreateClient($server, $email, $gb, '', intval($duration['days']))" not in live:
        raise RuntimeError("STOP timed purchase invocation not installed")
    if "function xuiResolveNewClientPlanDuration(" not in live:
        raise RuntimeError("STOP buy duration resolver not installed")
    if "function xuiProvisionRenew(" not in live:
        raise RuntimeError("STOP renewal function disappeared")
    return live


def checked_source(root):
    run = subprocess.run(
        ["git", "-C", str(root), "show", BASE_SHA + ":xui_lib.php"],
        capture_output=True, text=True, check=False,
    )
    if run.returncode:
        raise RuntimeError("STOP missing PR base git commit; fetch full repository first")
    return run.stdout


def php_lint(path):
    run = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
    if run.returncode:
        raise RuntimeError("STOP PHP syntax failed: " + run.stderr.strip())


def execute(args):
    source = Path(args.source).resolve(strict=True)
    root = Path(args.root).resolve(strict=True)
    live_path = root / "xui_lib.php"
    source_path = source / "xui_lib.php"
    if not live_path.is_file() or live_path.is_symlink():
        raise RuntimeError("STOP live xui_lib.php missing or symlinked")
    if not source_path.is_file() or source_path.is_symlink():
        raise RuntimeError("STOP source xui_lib.php missing or symlinked")
    base = checked_source(source)
    new = source_path.read_text(encoding="utf-8")
    before = live_path.read_bytes()
    after = patch(before.decode("utf-8"), base, new).encode("utf-8")

    with tempfile.TemporaryDirectory(prefix="pnv-buy-expiry-stage-") as temp:
        staged = Path(temp) / "xui_lib.php"
        staged.write_bytes(after)
        php_lint(staged)
        print("PHP LINT PASS")
        changed = before != after
        print("xui_lib.php:", "PATCHED" if changed else "UNCHANGED")
        if not changed:
            print("ALREADY DEPLOYED")
            return
        if not args.apply:
            print("SAFE DRY RUN PASS; production unchanged")
            return
        if os.geteuid() != 0:
            raise RuntimeError("STOP --apply requires root")

        backup_parent = Path(args.backup_dir)
        backup_parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        backup_dir = Path(tempfile.mkdtemp(prefix="pnv-buy-expiry-before-", dir=backup_parent))
        backup_dir.chmod(0o700)
        backup = backup_dir / "xui_lib.php"
        shutil.copy2(live_path, backup)
        print("BACKUP:", backup)
        if live_path.read_bytes() != before:
            raise RuntimeError("STOP production xui_lib.php changed before install")

        stat = live_path.stat()
        fd, tmp_path = tempfile.mkstemp(prefix=".pnv-buy-expiry-", suffix=".php", dir=root)
        installed = False
        try:
            with os.fdopen(fd, "wb") as handle:
                handle.write(after)
                handle.flush()
                os.fsync(handle.fileno())
            os.chown(tmp_path, stat.st_uid, stat.st_gid)
            os.chmod(tmp_path, stat.st_mode & 0o777)
            os.replace(tmp_path, live_path)
            installed = True
            php_lint(live_path)
            if not args.no_reload:
                subprocess.run(["systemctl", "reload", "php8.3-fpm"], check=True)
        except Exception:
            if installed:
                shutil.copy2(backup, live_path)
                if not args.no_reload:
                    subprocess.run(["systemctl", "reload", "php8.3-fpm"], check=False)
                print("ROLLBACK COMPLETE", file=sys.stderr)
            raise
        finally:
            if os.path.exists(tmp_path):
                os.unlink(tmp_path)
        print("BUY EXPIRY FIX DEPLOYED")
        print("BACKUP:", backup)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", required=True)
    parser.add_argument("--root", default="/var/www/html")
    parser.add_argument("--backup-dir", default="/root")
    parser.add_argument("--apply", action="store_true")
    parser.add_argument("--no-reload", action="store_true")
    try:
        execute(parser.parse_args())
    except Exception as err:
        print(str(err), file=sys.stderr)
        sys.exit(1)

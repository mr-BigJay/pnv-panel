#!/usr/bin/env python3
"""Validate bigjay patch: block must not nest inside /admin/."""

import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PATCH = ROOT / "scripts/patch-panel-ticketin-nginx.py"
FIXTURE = Path(__file__).parent / "fixtures/panel.ticketin.ir.nginx.conf"


def bigjay_inside_admin(text: str) -> bool:
    m = re.search(r"location\s+\^~\s+/admin/\s*\{", text)
    if not m:
        return False
    start = m.start()
    depth = 0
    i = text.find("{", start)
    admin_end = None
    while i < len(text):
        if text[i] == "{":
            depth += 1
        elif text[i] == "}":
            depth -= 1
            if depth == 0:
                admin_end = i
                break
        i += 1
    if admin_end is None:
        return False
    admin_body = text[start:admin_end]
    return bool(re.search(r"location\s+[^\n]*bigjay_controller", admin_body))


def main() -> None:
    tmp = Path("/tmp/panel.ticketin.ir.test.conf")
    tmp.write_text(FIXTURE.read_text(), encoding="utf-8")

    r = subprocess.run(
        [sys.executable, str(PATCH), "/run/php/php8.3-fpm.sock", str(tmp)],
        capture_output=True,
        text=True,
    )
    if r.returncode != 0:
        print("PATCH FAILED:", r.stderr or r.stdout)
        sys.exit(1)

    out = tmp.read_text(encoding="utf-8")
    if "PNV_BIGJAY_PATCHED" not in out:
        print("missing marker")
        sys.exit(1)
    if bigjay_inside_admin(out):
        print("FAIL: bigjay still inside /admin/")
        sys.exit(1)
    if not re.search(r"location\s+\^~\s+/bigjay_controller/", out):
        print("FAIL: missing bigjay location")
        sys.exit(1)

    print("OK: patch valid on fixture")
    print(r.stdout.strip())


if __name__ == "__main__":
    main()

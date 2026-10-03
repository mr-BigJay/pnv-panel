#!/usr/bin/env python3
"""Patch /etc/nginx/sites-enabled/panel.ticketin.ir bigjay_controller → working PHP routing."""

from __future__ import annotations

import re
import sys
from pathlib import Path

PHP_SOCK = sys.argv[1] if len(sys.argv) > 1 else "/run/php/php8.3-fpm.sock"
CONF = Path(sys.argv[2] if len(sys.argv) > 2 else "/etc/nginx/sites-enabled/panel.ticketin.ir")

MARKER = "PNV_BIGJAY_PATCHED"

SNIPPET = f"""
    # {MARKER} — bigjay_controller admin routing
    location = /bigjay_controller {{
        return 301 /bigjay_controller/;
    }}
    location ^~ /bigjay_controller/ {{
        try_files $uri $uri/ /bigjay_controller/index.php?$query_string;
        location ~ \\.php$ {{
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:{PHP_SOCK};
            include fastcgi_params;
        }}
    }}
""".strip(
    "\n"
)


def strip_bigjay_locations(text: str) -> str:
    lines = text.splitlines(keepends=True)
    out: list[str] = []
    i = 0
    while i < len(lines):
        line = lines[i]
        if re.search(r"location\s+[^\n]*bigjay_controller", line):
            depth = 0
            started = False
            while i < len(lines):
                chunk = lines[i]
                for ch in chunk:
                    if ch == "{":
                        depth += 1
                        started = True
                    elif ch == "}":
                        depth -= 1
                i += 1
                if started and depth <= 0:
                    break
            continue
        out.append(line)
        i += 1
    return "".join(out)


def insert_snippet(text: str) -> str:
    if MARKER in text and re.search(r"location\s+\^~\s+/bigjay_controller/", text):
        return text

    markers = [
        r"(\n\s*location \^~ /admin/\s*\{)",
        r"(\n\s*location = /admin\.php\s*\{)",
        r"(\n\s*client_max_body_size\s+\d+M;)",
    ]
    for pat in markers:
        m = re.search(pat, text)
        if m:
            pos = m.end()
            return text[:pos] + "\n" + SNIPPET + "\n" + text[pos:]

    m = re.search(r"(root\s+/var/www/html;\s*\n)", text)
    if m:
        pos = m.end()
        return text[:pos] + SNIPPET + "\n" + text[pos:]

    raise SystemExit("Could not find insertion point in nginx config")


def main() -> None:
    if not CONF.is_file():
        raise SystemExit(f"Config not found: {CONF}")

    original = CONF.read_text(encoding="utf-8", errors="replace")
    patched = insert_snippet(strip_bigjay_locations(original))
    if MARKER not in patched or not re.search(r"location\s+\^~\s+/bigjay_controller/", patched):
        raise SystemExit("Patch failed: bigjay block missing after edit")

    if patched == original:
        print(f"Already OK: {CONF}")
        return

    CONF.write_text(patched, encoding="utf-8")
    print(f"Patched: {CONF}")


if __name__ == "__main__":
    main()

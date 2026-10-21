"""Resolve PLAYWRIGHT_BROWSERS_PATH for VPS layout (/opt/tejarat-browser-test/browsers)."""

from __future__ import annotations

import os
from pathlib import Path


def apply_playwright_env() -> None:
    if os.environ.get("PLAYWRIGHT_BROWSERS_PATH", "").strip():
        return

    candidates: list[Path] = []
    test_root = os.environ.get("PNV_TEJARAT_TEST_ROOT", "").strip()
    storage = os.environ.get("PNV_WORKER_STORAGE", "").strip()

    if test_root:
        candidates.append(Path(test_root) / "browsers")
    if storage:
        p = Path(storage)
        candidates.append(p.parent / "browsers")
    candidates.append(Path("/opt/tejarat-browser-test/browsers"))

    for path in candidates:
        if path.is_dir() and any(path.iterdir()):
            os.environ["PLAYWRIGHT_BROWSERS_PATH"] = str(path.resolve())
            return

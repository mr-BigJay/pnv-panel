"""Chromium launch tuned for Linux VPS (Xvfb/VNC, no sandbox, crashpad off)."""

from __future__ import annotations

import os
from typing import Any


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def chromium_launch_kwargs(headless: bool) -> dict[str, Any]:
    args = [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-breakpad",
        "--disable-crash-reporter",
        "--disable-features=Crashpad",
        "--no-first-run",
        "--no-default-browser-check",
    ]
    if headless:
        args.append("--disable-gpu")

    extra = env("TEJARAT_CHROMIUM_ARGS")
    if extra:
        args.extend(part for part in extra.split() if part)

    opts: dict[str, Any] = {"headless": headless, "args": args}
    slow = env("TEJARAT_SLOW_MO_MS")
    if slow.isdigit() and int(slow) > 0:
        opts["slow_mo"] = int(slow)
    channel = env("TEJARAT_PLAYWRIGHT_CHANNEL")
    if channel:
        opts["channel"] = channel
    return opts


def launch_chromium(playwright: Any, headless: bool | None = None):
    if headless is None:
        headless = env("TEJARAT_HEADLESS", "true").lower() in ("1", "true", "yes")
    return playwright.chromium.launch(**chromium_launch_kwargs(headless))

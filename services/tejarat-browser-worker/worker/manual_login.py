"""
Interactive Tejarat session bootstrap — no OTP/CAPTCHA bypass.

Run on the VPS with a visible browser (Xvfb + VNC or local display):
  TEJARAT_HEADLESS=false DISPLAY=:99 python -m worker.manual_login

After you finish login and reach the statement page, press Enter in the terminal
(or set TEJARAT_LOGIN_DONE_SELECTOR to a locator that appears when logged in).
"""

from __future__ import annotations

import os
import sys
import time

from worker.browser import storage_state_path


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def main() -> int:
    bank_url = env("TEJARAT_BANK_URL", "https://hb.tejaratbank.ir")
    stmt_url = env("TEJARAT_STATEMENT_URL", bank_url)
    headless = env("TEJARAT_HEADLESS", "false").lower() in ("1", "true", "yes")
    done_sel = env("TEJARAT_LOGIN_DONE_SELECTOR")
    probe_sel = env("TEJARAT_LOGIN_PROBE_SELECTOR")
    wait_sec = max(30, int(env("TEJARAT_MANUAL_LOGIN_MAX_WAIT_SEC", "900")))

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("playwright not installed", file=sys.stderr)
        return 2

    state_file = storage_state_path()
    print(f"Storage will be saved to: {state_file}")
    print(f"Open bank URL, complete login + OTP yourself, then navigate to statement if needed.")
    print(f"Statement URL hint: {stmt_url}")

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=headless)
        context_kwargs: dict = {"locale": "fa-IR"}
        if state_file.is_file():
            context_kwargs["storage_state"] = str(state_file)
        context = browser.new_context(**context_kwargs)
        page = context.new_page()
        page.set_default_timeout(int(env("TEJARAT_PAGE_TIMEOUT_MS", "60000")))
        page.goto(stmt_url, wait_until="domcontentloaded")

        if done_sel:
            print(f"Waiting up to {wait_sec}s for selector: {done_sel}")
            try:
                page.wait_for_selector(done_sel, timeout=wait_sec * 1000)
            except Exception:
                print("Timed out waiting for login-done selector.", file=sys.stderr)
                context.close()
                browser.close()
                return 1
        else:
            if probe_sel and page.locator(probe_sel).count() > 0:
                print("Login page detected — complete authentication in the browser window.")
            input("When logged in and on the statement page, press Enter here… ")

        context.storage_state(path=str(state_file))
        context.close()
        browser.close()

    print("Session saved. Set TEJARAT_COLLECT_MODE=live and TEJARAT_HEADLESS=true for the worker.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

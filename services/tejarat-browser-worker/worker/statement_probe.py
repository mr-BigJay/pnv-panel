"""
After manual login: sample statement page structure for selector tuning (no secrets logged).
"""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

from worker.browser import storage_state_path


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def main() -> int:
    stmt_url = env("TEJARAT_STATEMENT_URL", "https://hb.tejaratbank.ir/account/log")
    row_sel = env("TEJARAT_PROBE_ROW_SELECTOR", "table tbody tr, .transaction-row, tr")
    storage = Path(env("PNV_WORKER_STORAGE", "/opt/tejarat-browser-test/session"))
    storage.mkdir(parents=True, exist_ok=True)
    out_json = storage / "statement_probe.json"
    out_png = storage / "statement_probe.png"

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("playwright not installed", file=sys.stderr)
        return 2

    state = storage_state_path()
    if not state.is_file():
        print("no playwright_storage.json — run manual_login first", file=sys.stderr)
        return 1

    headless = env("TEJARAT_HEADLESS", "true").lower() in ("1", "true", "yes")

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=headless)
        context = browser.new_context(locale="fa-IR", storage_state=str(state))
        page = context.new_page()
        page.goto(stmt_url, wait_until="networkidle", timeout=60000)
        page.screenshot(path=str(out_png), full_page=False)

        rows = page.locator(row_sel)
        count = rows.count()
        samples: list[dict] = []
        for i in range(min(count, 8)):
            row = rows.nth(i)
            text = row.inner_text().strip()
            if not text:
                continue
            samples.append({
                "index": i,
                "text_preview": text[:400].replace("\n", " | "),
            })

        probe = {
            "url": stmt_url,
            "title": page.title(),
            "row_selector": row_sel,
            "row_count": count,
            "samples": samples,
        }
        out_json.write_text(json.dumps(probe, ensure_ascii=False, indent=2), encoding="utf-8")
        context.close()
        browser.close()

    print(f"Wrote {out_json} rows={count} screenshot={out_png}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

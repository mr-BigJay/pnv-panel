"""
Read-only Tejarat internet banking statement collector.

Selectors are configurable because the bank UI changes and cannot be scraped
without an authenticated session. No transfers, payments, or settings changes.
"""

from __future__ import annotations

import json
import logging
import os
import re
import time
from pathlib import Path
from typing import Any

log = logging.getLogger("tejarat_worker.browser")

CREDIT_HINTS = ("واریز", "credit", "ورود")


def _env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def storage_state_path() -> Path:
    base = Path(_env("PNV_WORKER_STORAGE", "/data/storage"))
    base.mkdir(parents=True, exist_ok=True)
    return base / "playwright_storage.json"


def load_fixture_transactions() -> list[dict[str, Any]]:
    fixture = _env("TEJARAT_FIXTURE_JSON")
    if not fixture:
        return []
    path = Path(fixture)
    if not path.is_file():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    return data if isinstance(data, list) else []


def parse_amount_rial(raw: str) -> int:
    digits = re.sub(r"[^\d]", "", raw or "")
    return int(digits) if digits else 0


def collect_live_transactions() -> tuple[list[dict[str, Any]], dict[str, Any]]:
    """
    Returns (transactions, health). Never logs credentials or page HTML.
    """
    from playwright.sync_api import sync_playwright

    bank_url = _env("TEJARAT_BANK_URL", "https://hb.tejaratbank.ir")
    stmt_url = _env("TEJARAT_STATEMENT_URL", bank_url)
    headless = _env("TEJARAT_HEADLESS", "true").lower() in ("1", "true", "yes")
    login_required_flag = _env("TEJARAT_FORCE_LOGIN_REQUIRED", "").lower() in ("1", "true", "yes")

    health: dict[str, Any] = {
        "session_status": "unknown",
        "last_fetch_ok_at": 0,
        "last_error": "",
        "worker_version": _env("PNV_WORKER_VERSION", "0.1.0"),
    }

    if login_required_flag:
        health["session_status"] = "login_required"
        health["last_error"] = "manual login required"
        return [], health

    selectors = {}
    sel_raw = _env("TEJARAT_STMT_SELECTORS_JSON")
    if sel_raw:
        try:
            selectors = json.loads(sel_raw)
        except json.JSONDecodeError:
            health["session_status"] = "error"
            health["last_error"] = "invalid TEJARAT_STMT_SELECTORS_JSON"
            return [], health

    row_sel = selectors.get("row", "")
    credit_sel = selectors.get("credit_cell", "")
    ref_sel = selectors.get("reference_cell", "")
    amount_sel = selectors.get("amount_cell", "")
    date_sel = selectors.get("date_cell", "")

    if not row_sel:
        health["session_status"] = "error"
        health["last_error"] = "statement selectors not configured"
        return [], health

    state_file = storage_state_path()
    txs: list[dict[str, Any]] = []

    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=headless)
            context_kwargs: dict[str, Any] = {"locale": "fa-IR"}
            if state_file.is_file():
                context_kwargs["storage_state"] = str(state_file)
            context = browser.new_context(**context_kwargs)
            page = context.new_page()
            page.set_default_timeout(int(_env("TEJARAT_PAGE_TIMEOUT_MS", "30000")))
            page.goto(stmt_url, wait_until="domcontentloaded")

            if _env("TEJARAT_LOGIN_PROBE_SELECTOR"):
                if page.locator(_env("TEJARAT_LOGIN_PROBE_SELECTOR")).count() > 0:
                    health["session_status"] = "login_required"
                    health["last_error"] = "login page detected"
                    context.close()
                    browser.close()
                    return [], health

            rows = page.locator(row_sel)
            count = rows.count()
            for i in range(min(count, 200)):
                row = rows.nth(i)
                line = row.inner_text()
                if credit_sel:
                    credit_text = row.locator(credit_sel).inner_text()
                else:
                    credit_text = line
                if not any(h in credit_text for h in CREDIT_HINTS):
                    continue
                ref = row.locator(ref_sel).inner_text().strip() if ref_sel else f"row-{i}-{int(time.time())}"
                amount_raw = row.locator(amount_sel).inner_text() if amount_sel else credit_text
                date_raw = row.locator(date_sel).inner_text().strip() if date_sel else ""
                amount = parse_amount_rial(amount_raw)
                if amount <= 0:
                    continue
                card = _env("TEJARAT_DESTINATION_CARD", "")
                txs.append(
                    {
                        "amount": amount,
                        "destination_card": card,
                        "transaction_id": re.sub(r"\s+", "", ref)[:64],
                        "occurred_at": int(time.time()),
                        "description": date_raw[:120],
                        "direction": "credit",
                        "source_session_at": int(time.time()),
                    }
                )

            context.storage_state(path=str(state_file))
            context.close()
            browser.close()
    except Exception as exc:  # noqa: BLE001 — surface class only
        log.warning("browser collect failed: %s", exc.__class__.__name__)
        health["session_status"] = "error"
        health["last_error"] = exc.__class__.__name__
        return [], health

    health["session_status"] = "logged_in"
    health["last_fetch_ok_at"] = int(time.time())
    health["last_error"] = ""
    return txs, health


def collect_transactions() -> tuple[list[dict[str, Any]], dict[str, Any]]:
    mode = _env("TEJARAT_COLLECT_MODE", "fixture").lower()
    if mode == "fixture":
        txs = load_fixture_transactions()
        health = {
            "session_status": "logged_in" if txs else "login_required",
            "last_fetch_ok_at": int(time.time()) if txs else 0,
            "last_error": "" if txs else "fixture empty",
            "worker_version": _env("PNV_WORKER_VERSION", "0.1.0"),
        }
        return txs, health
    return collect_live_transactions()

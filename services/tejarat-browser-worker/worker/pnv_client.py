"""
Send read-only bank CREDIT rows via signed HTTPS to the PNV panel.
No credentials/cookies/OTP are sent to PNV. No mutating bank API requests.
"""
from __future__ import annotations
import hashlib
import hmac
import json
import logging
import os
import time
from urllib.parse import urlparse

import requests

log = logging.getLogger("tejarat_worker.signed_feed")

def sign(timestamp: int, body: bytes, secret: str) -> str:
    message = str(timestamp).encode("ascii") + b"\n" + body
    return hmac.new(secret.encode("utf-8"), message, hashlib.sha256).hexdigest()

def publish(url: str, secret: str, transaction: dict, *, now: int | None = None,
            session_ok: bool = False, timeout: int = 15) -> tuple[bool, str]:
    if not session_ok:
        return False, "session_not_verified"
    parsed = urlparse(url)
    if parsed.scheme != "https" or not parsed.hostname or parsed.username or parsed.password:
        return False, "https_endpoint_required"
    if len(secret) < 32:
        return False, "secret_not_configured"
    required = {"transaction_id", "amount_rial", "destination_card", "occurred_at", "direction"}
    if not required.issubset(transaction):
        return False, "incomplete_transaction"
    if transaction["direction"] != "credit":
        return False, "not_credit"
    timestamp = now or int(time.time())
    body = json.dumps({k: transaction[k] for k in required}, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    headers = {
        "Content-Type": "application/json",
        "X-PNV-Timestamp": str(timestamp),
        "X-PNV-Signature": sign(timestamp, body, secret),
    }
    try:
        response = requests.post(url, data=body, headers=headers, timeout=timeout, allow_redirects=False)
    except requests.RequestException as exc:
        log.warning("signed feed network failure class=%s", type(exc).__name__)
        return False, "network_failure"
    if response.status_code in (301, 302, 303, 307, 308):
        return False, "redirect_refused"
    try:
        result = response.json()
    except ValueError:
        return False, "non_json_response"
    if response.status_code == 200 and result.get("ok"):
        return True, "dry_run" if result.get("dry_run") else "confirmed"
    code = str(result.get("code") or "rejected")
    # These are expected when polling a statement that includes older rows.
    if code in ("duplicate_transaction", "no_match", "ambiguous_match"):
        return True, code
    return False, code

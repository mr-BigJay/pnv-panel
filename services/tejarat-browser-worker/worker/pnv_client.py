import hashlib
import hmac
import json
import logging
from typing import Any

import requests

log = logging.getLogger("tejarat_worker.pnv")


def sign_body(secret: str, body: bytes) -> str:
    return hmac.new(secret.encode("utf-8"), body, hashlib.sha256).hexdigest()


def push_ingest(
    ingest_url: str,
    secret: str,
    transactions: list[dict[str, Any]],
    health: dict[str, Any],
    timeout: float = 20.0,
) -> tuple[bool, str]:
    payload = {"transactions": transactions, "health": health}
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    headers = {
        "Content-Type": "application/json; charset=utf-8",
        "X-PNV-Tejarat-Worker-Signature": sign_body(secret, body),
    }

    try:
        resp = requests.post(ingest_url, data=body, headers=headers, timeout=timeout)
    except requests.RequestException as exc:
        log.warning("ingest request failed: %s", exc.__class__.__name__)
        return False, "network_error"

    if resp.status_code >= 400:
        log.warning("ingest HTTP %s", resp.status_code)
        return False, f"http_{resp.status_code}"

    try:
        data = resp.json()
    except ValueError:
        return False, "bad_json"

    if not data.get("ok"):
        return False, str(data.get("code") or "ingest_rejected")

    return True, "ok"

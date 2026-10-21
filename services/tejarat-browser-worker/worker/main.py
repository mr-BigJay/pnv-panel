"""Tejarat read-only browser polling. Manual human bank login is prerequisite.

IMPORTANT: fixture data is NEVER submitted to a live bank-connection endpoint.
Only data observed from an authenticated read-only statement page can be sent.
"""
import logging
import os
import sys
import time
from worker.browser import collect_transactions
from worker.pnv_client import publish

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("tejarat.worker")

def run_once() -> int:
    mode = os.getenv("TEJARAT_COLLECT_MODE", "fixture").strip().lower()
    if mode != "live":
        log.error("live ingestion blocked: TEJARAT_COLLECT_MODE must equal live")
        return 2
    url = os.getenv("PNV_BANK_FEED_URL", "").strip()
    secret = os.getenv("PNV_BANK_FEED_SECRET", "").strip()
    if not url or len(secret) < 32:
        log.error("trusted signed PNV endpoint/secret not configured")
        return 2
    transactions, health = collect_transactions()
    last = int(health.get("last_fetch_ok_at") or 0)
    if health.get("session_status") != "logged_in" or abs(time.time() - last) > 300:
        log.warning("bank session not current (status=%s)", health.get("session_status", "unknown"))
        return 2
    delivered = 0
    errors = 0
    for row in transactions:
        normalized = {
            "transaction_id": row.get("transaction_id"),
            "amount_rial": row.get("amount"),
            "destination_card": row.get("destination_card"),
            "occurred_at": row.get("occurred_at"),
            "direction": row.get("direction"),
        }
        ok, code = publish(url, secret, normalized, session_ok=True)
        if ok:
            delivered += 1
        else:
            errors += 1
            log.warning("bank feed rejected row code=%s", code)
    log.info("read-only statement rows=%d delivered=%d rejected=%d",len(transactions),delivered,errors)
    return 1 if errors else 0

if __name__ == "__main__":
    if os.getenv("TEJARAT_RUN_ONCE", "1") != "1":
        log.error("use a cron/systemd timer to run once; no permanent background loop")
        sys.exit(2)
    sys.exit(run_once())

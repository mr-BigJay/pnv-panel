import logging
import os
import random
import time

from worker.browser import collect_transactions
from worker.pnv_client import push_ingest

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
)
log = logging.getLogger("tejarat_worker")


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def run_once() -> int:
    ingest_url = env("PNV_TEJARAT_INGEST_URL")
    secret = env("PNV_TEJARAT_INGEST_SECRET")
    if not ingest_url or not secret:
        log.error("PNV_TEJARAT_INGEST_URL and PNV_TEJARAT_INGEST_SECRET are required")
        return 2

    txs, health = collect_transactions()
    ok, code = push_ingest(ingest_url, secret, txs, health)
    if not ok:
        log.warning("ingest failed code=%s txs=%s session=%s", code, len(txs), health.get("session_status"))
        return 1

    log.info("ingest ok txs=%s session=%s", len(txs), health.get("session_status"))
    return 0


def main() -> None:
    interval = max(30, int(env("TEJARAT_POLL_INTERVAL_SECONDS", "120")))
    backoff_max = max(interval, int(env("TEJARAT_BACKOFF_MAX_SECONDS", "600")))
    backoff = interval

    while True:
        code = run_once()
        if code == 0:
            backoff = interval
        else:
            backoff = min(backoff_max, max(interval, int(backoff * 1.5)))
            jitter = random.uniform(0, min(30.0, backoff * 0.1))
            sleep_for = backoff + jitter
            log.info("sleep %.0fs after failure", sleep_for)
            time.sleep(sleep_for)
            continue
        time.sleep(interval)


if __name__ == "__main__":
    if env("TEJARAT_RUN_ONCE", "").lower() in ("1", "true", "yes"):
        raise SystemExit(run_once())
    main()

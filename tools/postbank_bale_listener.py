#!/usr/bin/env python3
"""
شنونده تمام‌اتوماتیک پیام واریز پست‌بانک از اکانت بله صاحب کارت.

Bot API نمی‌تواند چت @postbank_bot را بخواند؛ این سرویس با سشن همان
اکانتی که اعلان واریز را می‌گیرد، پیام را می‌خواند و به پنل می‌فرستد.

نصب:
  pip3 install -r tools/requirements-postbank.txt

لاگین یک‌بار (تعاملی روی سرور):
  python3 tools/postbank_bale_listener.py --login \
    --session /var/www/html/db/bale_user_session.bale

اجرای دائم:
  export POSTBANK_INGEST_SECRET='...'
  python3 tools/postbank_bale_listener.py \
    --session /var/www/html/db/bale_user_session.bale \
    --ingest-url https://panel.ticketin.ir/postbank-ingest.php \
    --ingest-secret "$POSTBANK_INGEST_SECRET"
"""

from __future__ import annotations

import argparse
import asyncio
import json
import logging
import os
import re
import sys
from pathlib import Path
from typing import Optional, Set

try:
    import aiohttp
    from aiobale import Client, Dispatcher
    from aiobale.types import Message
except ImportError as exc:
    print("Missing dependency. Run: pip3 install -r tools/requirements-postbank.txt", file=sys.stderr)
    raise SystemExit(1) from exc


LOG = logging.getLogger("postbank-listener")

DEPOSIT_HINTS = (
    "واریز",
    "واريز",
    "پست بانک",
    "پست‌بانک",
    "مانده",
    "بستانکار",
)


def looks_like_deposit(text: str) -> bool:
    t = (text or "").strip()
    if not t:
        return False
    if re.search(r"\+\s*\d{1,3}(?:,\d{3})+", t):
        return True
    return any(h in t for h in DEPOSIT_HINTS)


async def post_ingest(url: str, secret: str, text: str, source: str = "aiobale-userbot") -> dict:
    headers = {
        "Content-Type": "application/json; charset=utf-8",
        "X-Postbank-Ingest-Secret": secret,
    }
    payload = {"text": text, "source": source}
    timeout = aiohttp.ClientTimeout(total=30)
    async with aiohttp.ClientSession(timeout=timeout) as session:
        async with session.post(
            url,
            headers=headers,
            data=json.dumps(payload, ensure_ascii=False).encode("utf-8"),
        ) as resp:
            body = await resp.text()
            try:
                data = json.loads(body) if body else {}
            except json.JSONDecodeError:
                data = {"ok": False, "error": "bad json from ingest", "raw": body[:300]}
            data["_http"] = resp.status
            return data


def run_login(session_file: Path) -> None:
    session_file.parent.mkdir(parents=True, exist_ok=True)
    dp = Dispatcher()
    client = Client(dp, session_file=str(session_file))
    LOG.info("Interactive login… session=%s", session_file)
    # اگر سشن معتبر نباشد، aiobale خودش PhoneLoginCLI را اجرا می‌کند
    client.run()


def run_listener(session_file: Path, ingest_url: str, ingest_secret: str) -> None:
    if not session_file.exists() or session_file.stat().st_size < 8:
        LOG.error("Session missing. Run with --login first: %s", session_file)
        raise SystemExit(2)

    dp = Dispatcher()
    client = Client(dp, session_file=str(session_file))
    recent: Set[str] = set()

    @dp.message()
    async def on_message(msg: Message):
        text = ""
        if getattr(msg, "text", None):
            text = str(msg.text or "").strip()
        if not text:
            text = str(getattr(msg, "caption", "") or "").strip()

        if not looks_like_deposit(text):
            return

        key = re.sub(r"\s+", " ", text)[:240]
        if key in recent:
            LOG.info("Duplicate ignored")
            return
        recent.add(key)
        if len(recent) > 300:
            recent.clear()

        LOG.info("Deposit candidate len=%s preview=%s", len(text), key[:120])
        try:
            result = await post_ingest(ingest_url, ingest_secret, text)
            LOG.info(
                "Ingest http=%s paid=%s err=%s",
                result.get("_http"),
                result.get("paid"),
                result.get("error"),
            )
        except Exception as exc:
            LOG.exception("Ingest failed: %s", exc)

    LOG.info("Listening… session=%s ingest=%s", session_file, ingest_url)
    client.run()


def parse_args(argv: Optional[list[str]] = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Auto-ingest PostBank Bale notices into pnv-panel")
    p.add_argument("--session", default=os.environ.get("POSTBANK_BALE_SESSION", "db/bale_user_session.bale"))
    p.add_argument(
        "--ingest-url",
        default=os.environ.get("POSTBANK_INGEST_URL", "https://panel.ticketin.ir/postbank-ingest.php"),
    )
    p.add_argument("--ingest-secret", default=os.environ.get("POSTBANK_INGEST_SECRET", ""))
    p.add_argument("--login", action="store_true", help="One-time interactive phone login")
    p.add_argument("--log-level", default="INFO")
    return p.parse_args(argv)


def main(argv: Optional[list[str]] = None) -> None:
    args = parse_args(argv)
    logging.basicConfig(
        level=getattr(logging, args.log_level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(message)s",
    )
    session_file = Path(args.session).expanduser().resolve()

    if args.login:
        run_login(session_file)
        print(f"Login/session ready: {session_file}")
        print("Next: run without --login (systemd/screen).")
        return

    if not args.ingest_secret:
        print("ERROR: --ingest-secret or POSTBANK_INGEST_SECRET required", file=sys.stderr)
        raise SystemExit(2)

    run_listener(session_file, args.ingest_url.rstrip("/"), args.ingest_secret)


if __name__ == "__main__":
    main()

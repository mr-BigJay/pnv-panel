#!/usr/bin/env python3
"""
شنونده تمام‌اتوماتیک پیام واریز پست‌بانک از اکانت بله صاحب کارت.

Bot API نمی‌تواند چت @postbank_bot را بخواند. این سرویس با سشن صاحب کارت:
  1) postbank-ingest.php را صدا می‌زند
  2) اگر تأیید نشد، همان متن را به bale-webhook.php می‌فرستد
     (معادل فوروارد دستی به @Jay24x7Pusbank_bot)
  3) در صورت خطا، forward/send_message از userbot هم امتحان می‌شود
"""

import argparse
import json
import logging
import os
import re
import sys
import time
from pathlib import Path

try:
    import aiohttp
    from aiobale import Client, Dispatcher
    import aiobale.enums as bale_enums
except ImportError as exc:
    print("Missing dependency. Run: pip3 install -r tools/requirements-postbank.txt", file=sys.stderr)
    raise SystemExit(1) from exc


LOG = logging.getLogger("postbank-listener")

POSTBANK_USERNAMES = {"postbank_bot", "postbank"}

DEPOSIT_HINTS = (
    "واریز",
    "واريز",
    "پست بانک",
    "پست‌بانک",
    "بستانکار",
    "deposit",
)


def looks_like_deposit(text):
    t = (text or "").strip()
    if not t:
        return False
    if re.search(r"\+\s*\d{1,3}(?:,\d{3})+", t):
        return True
    if ("پست" in t or "post" in t.lower()) and ("واریز" in t or "واريز" in t):
        return True
    return any(h in t for h in DEPOSIT_HINTS)


def message_sender_username(msg):
    for attr in ("from_user", "sender", "user"):
        obj = getattr(msg, attr, None)
        if obj is None:
            continue
        username = getattr(obj, "username", None)
        if username:
            return str(username).strip().lower().lstrip("@")
    peer = getattr(msg, "peer_id", None) or getattr(msg, "peer", None)
    if peer is not None:
        username = getattr(peer, "username", None)
        if username:
            return str(username).strip().lower().lstrip("@")
    return ""


def should_process_message(msg, text):
    sender = message_sender_username(msg)
    if sender in POSTBANK_USERNAMES:
        return True
    return looks_like_deposit(text)


def ingest_is_paid(result):
    if not isinstance(result, dict):
        return False
    if result.get("paid"):
        return True
    if result.get("_http") != 200:
        return False
    if result.get("ok") is True and not result.get("ignored"):
        return True
    return False


def load_admin_chat_id():
    env = os.environ.get("POSTBANK_ADMIN_CHAT_ID", "").strip()
    if env:
        return env

    config_path = Path(os.environ.get("POSTBANK_BALE_CONFIG", "/var/www/html/db/bale.json"))
    if config_path.is_file():
        try:
            data = json.loads(config_path.read_text(encoding="utf-8"))
            ids = str(data.get("admin_chat_ids", "")).replace(" ", "")
            for part in ids.split(","):
                part = part.strip()
                if part:
                    return part
        except Exception as exc:
            LOG.warning("Could not read admin chat from %s: %s", config_path, exc)

    return ""


async def post_ingest(url, secret, text, source="aiobale-userbot"):
    headers = {
        "Content-Type": "application/json; charset=utf-8",
        "X-Postbank-Ingest-Secret": secret,
    }
    payload = {"text": text, "source": source}
    timeout = aiohttp.ClientTimeout(total=45)
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


async def post_bot_webhook(webhook_url, admin_chat_id, text):
    """همان کاری که فوروارد دستی به @Jay24x7Pusbank_bot می‌کند — webhook پنل."""
    admin_chat_id = str(admin_chat_id or "").strip()
    if not admin_chat_id or not webhook_url:
        return {"ok": False, "error": "webhook or admin chat missing"}

    now = int(time.time())
    update = {
        "update_id": now,
        "message": {
            "message_id": now,
            "date": now,
            "chat": {"id": int(admin_chat_id), "type": "private"},
            "from": {"id": int(admin_chat_id), "is_bot": False},
            "forward_from_chat": {"id": 0, "type": "private", "username": "postbank_bot", "title": "postbank_bot"},
            "forward_date": now,
            "text": text,
        },
    }

    headers = {
        "Content-Type": "application/json; charset=utf-8",
        "X-Postbank-Listener": "1",
    }
    timeout = aiohttp.ClientTimeout(total=45)
    async with aiohttp.ClientSession(timeout=timeout) as session:
        async with session.post(
            webhook_url,
            headers=headers,
            data=json.dumps(update, ensure_ascii=False).encode("utf-8"),
        ) as resp:
            body = await resp.text()
            try:
                data = json.loads(body) if body else {}
            except json.JSONDecodeError:
                data = {"ok": False, "error": "bad json from webhook", "raw": body[:300]}
            data["_http"] = resp.status
            return data


class BotPeerCache:
    def __init__(self):
        self.chat_id = None
        self.chat_type = None


async def resolve_bot_peer(client, cache, bot_username):
    if cache.chat_id is not None and cache.chat_type is not None:
        return cache.chat_id, cache.chat_type

    username = (bot_username or "").strip().lstrip("@")
    if not username:
        raise ValueError("bot username empty")

    resp = await client.search_username(username)
    user = getattr(resp, "user", None)

    if user is None:
        raise ValueError("bot username not found: @" + username)

    chat_id = getattr(user, "id", None) or getattr(user, "user_id", None)
    if chat_id is None:
        raise ValueError("bot peer id missing for @" + username)

    cache.chat_id = int(chat_id)
    cache.chat_type = bale_enums.ChatType.BOT
    return cache.chat_id, cache.chat_type


async def deliver_deposit_to_bot(client, cache, msg, text, bot_username):
    if not bot_username:
        return False

    chat_id, chat_type = await resolve_bot_peer(client, cache, bot_username)

    try:
        await msg.forward_to(chat_id=chat_id, chat_type=chat_type)
        LOG.info("Forwarded deposit to @%s", bot_username.lstrip("@"))
        return True
    except Exception as exc:
        LOG.warning("Forward failed (%s), trying send_message", exc)

    send = getattr(client, "send_message", None)
    if send is None:
        LOG.error("client.send_message unavailable")
        return False

    try:
        try:
            await send(chat_id=chat_id, text=text, chat_type=chat_type)
        except TypeError:
            await send(chat_id, text)
        LOG.info("Sent deposit text to @%s via send_message", bot_username.lstrip("@"))
        return True
    except Exception as exc2:
        LOG.error("send_message to bot failed: %s", exc2)
        return False


def run_login(session_file):
    session_file.parent.mkdir(parents=True, exist_ok=True)
    dp = Dispatcher()
    client = Client(dp, session_file=str(session_file))
    LOG.info("Interactive login… session=%s", session_file)
    client.run()


def run_listener(session_file, ingest_url, ingest_secret, webhook_url, admin_chat_id, forward_bot_username):
    if not session_file.exists() or session_file.stat().st_size < 8:
        LOG.error("Session missing. Run with --login first: %s", session_file)
        raise SystemExit(2)

    if not admin_chat_id:
        LOG.error("POSTBANK_ADMIN_CHAT_ID missing (set env or db/bale.json admin_chat_ids)")
        raise SystemExit(2)

    dp = Dispatcher()
    client = Client(dp, session_file=str(session_file))
    recent = set()
    bot_cache = BotPeerCache()

    @dp.message()
    async def on_message(msg):
        text = ""
        if getattr(msg, "text", None):
            text = str(msg.text or "").strip()
        if not text:
            text = str(getattr(msg, "caption", "") or "").strip()

        if not should_process_message(msg, text):
            return

        key = re.sub(r"\s+", " ", text)[:240]
        if key in recent:
            LOG.info("Duplicate ignored")
            return
        recent.add(key)
        if len(recent) > 300:
            recent.clear()

        sender = message_sender_username(msg)
        LOG.info("Deposit from @%s len=%s preview=%s", sender or "?", len(text), key[:120])

        ingest_result = {}
        try:
            ingest_result = await post_ingest(ingest_url, ingest_secret, text)
            LOG.info(
                "Ingest http=%s paid=%s ok=%s err=%s",
                ingest_result.get("_http"),
                ingest_result.get("paid"),
                ingest_result.get("ok"),
                ingest_result.get("error"),
            )
            if ingest_result.get("_http") == 401:
                LOG.error("Ingest unauthorized — update POSTBANK_INGEST_SECRET in db/postbank-listener.env")
        except Exception as exc:
            LOG.exception("Ingest failed: %s", exc)

        paid = ingest_is_paid(ingest_result)

        if not paid and webhook_url:
            try:
                wh = await post_bot_webhook(webhook_url, admin_chat_id, text)
                LOG.info(
                    "Webhook http=%s paid=%s ok=%s err=%s",
                    wh.get("_http"),
                    wh.get("paid"),
                    wh.get("ok"),
                    wh.get("error"),
                )
                if wh.get("paid") or (wh.get("_http") == 200 and wh.get("ok") is True and not wh.get("ignored")):
                    paid = True
            except Exception as exc:
                LOG.exception("Webhook POST failed: %s", exc)

        if forward_bot_username and not paid:
            try:
                await deliver_deposit_to_bot(client, bot_cache, msg, text, forward_bot_username)
            except Exception as exc:
                LOG.error("Deliver to bot via userbot failed: %s", exc)

    LOG.info(
        "Listening… session=%s ingest=%s webhook=%s admin=%s forward_bot=%s",
        session_file,
        ingest_url,
        webhook_url,
        admin_chat_id,
        forward_bot_username or "(disabled)",
    )
    client.run()


def parse_args(argv=None):
    p = argparse.ArgumentParser(description="Auto-ingest PostBank Bale notices into pnv-panel")
    p.add_argument("--session", default=os.environ.get("POSTBANK_BALE_SESSION", "db/bale_user_session.bale"))
    p.add_argument(
        "--ingest-url",
        default=os.environ.get("POSTBANK_INGEST_URL", "https://panel.ticketin.ir/postbank-ingest.php"),
    )
    p.add_argument(
        "--webhook-url",
        default=os.environ.get("POSTBANK_WEBHOOK_URL", "https://panel.ticketin.ir/bale-webhook.php"),
    )
    p.add_argument("--admin-chat-id", default=os.environ.get("POSTBANK_ADMIN_CHAT_ID", ""))
    p.add_argument("--ingest-secret", default=os.environ.get("POSTBANK_INGEST_SECRET", ""))
    p.add_argument(
        "--forward-bot",
        default=os.environ.get("POSTBANK_FORWARD_BOT", "Jay24x7Pusbank_bot"),
        help="Fallback: userbot forward/send to bot when webhook did not pay",
    )
    p.add_argument("--login", action="store_true", help="One-time interactive phone login")
    p.add_argument("--log-level", default="INFO")
    return p.parse_args(argv)


def main(argv=None):
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

    admin_chat_id = (args.admin_chat_id or load_admin_chat_id()).strip()
    forward_bot = (args.forward_bot or "").strip()
    run_listener(
        session_file,
        args.ingest_url.rstrip("/"),
        args.ingest_secret,
        (args.webhook_url or "").rstrip("/"),
        admin_chat_id,
        forward_bot,
    )


if __name__ == "__main__":
    main()

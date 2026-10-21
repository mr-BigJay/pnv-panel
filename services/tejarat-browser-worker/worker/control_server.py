"""Local-only Tejarat browser login controller; no OTP or payment API.

A Unix socket is readable solely by the PHP worker user, and Linux SO_PEERCRED
is checked independently. Login credentials are passed transiently over that
socket, never written by this controller or returned in status/logs.
"""
from __future__ import annotations

import json
import os
import socket
import socketserver
import struct
import threading
import time
from pathlib import Path
from urllib.parse import urlparse

SOCKET = os.getenv("PNV_TEJARAT_CONTROL_SOCKET", "/run/pnv-tejarat-control/control.sock")
OFFICIAL = "hb.tejaratbank.ir"
START_URL = "https://hb.tejaratbank.ir/login"
STATEMENT_URL = "https://hb.tejaratbank.ir/account/log"
ALLOWED_ACTIONS = {"status", "start", "check"}

_state = {"state": "idle", "updated_at": 0}
_lock = threading.Lock()
_busy = False


def trusted(url: str) -> bool:
    p = urlparse(url)
    return p.scheme == "https" and p.hostname == OFFICIAL and p.port in (None, 443)


def classify(url: str, text: str) -> str:
    if not trusted(url):
        return "untrusted_redirect"
    path = urlparse(url).path.lower().rstrip("/")
    if path.startswith("/account/log") and "گردش حساب" in text and (
        "گزارش" in text or "تعداد گردش" in text
    ):
        return "session_verified"
    if path.startswith("/register"):
        return "awaiting_first_activation"
    if path.startswith("/login"):
        return "awaiting_bank_login"
    return "waiting_bank_auth"


def _status(state: str) -> None:
    with _lock:
        _state.update({"state": state, "updated_at": int(time.time())})


def status() -> dict:
    with _lock:
        return dict(_state)


def _probe(page) -> str:
    url = page.url
    if not trusted(url):
        return "untrusted_redirect"
    # Do not persist DOM or financial details.
    content = page.locator("body").inner_text(timeout=3000)[:18000]
    return classify(url, content)


def _fill_existing_login(page, username: str, password: str) -> None:
    """Fill+submit only on an HTTPS official password form, never registration."""
    if not trusted(page.url) or urlparse(page.url).path.startswith("/register"):
        return
    pw = page.locator('input[type="password"]:visible')
    if pw.count() != 1:
        return
    user = page.locator(
        'input[autocomplete="username"]:visible, input[name="username"]:visible, '
        'input[type="text"]:visible'
    )
    if user.count() != 1:
        return
    user.first.fill(username)
    pw.first.fill(password)
    # A single submit button of the form is needed; never click arbitrary controls.
    forms = pw.first.locator("xpath=ancestor::form")
    if forms.count() != 1:
        return
    submit = forms.first.locator('button[type="submit"]:visible, input[type="submit"]:visible')
    if submit.count() == 1:
        submit.first.click(timeout=10000)


def _session_job(username: str, password: str, check_only: bool) -> None:
    global _busy
    try:
        from playwright.sync_api import sync_playwright
        from worker.browser import storage_state_path
        from worker.chromium_launch import launch_chromium
        from worker.playwright_env import apply_playwright_env

        apply_playwright_env()
        state_file = storage_state_path()
        # Status probe is headless. First-login is visible and requires a
        # protected VPS desktop (VNC over SSH only); never collect an OTP in PNV.
        headless = check_only or os.getenv("TEJARAT_LOGIN_HEADLESS", "false").lower() in ("true", "1")
        _status("checking_session" if check_only else "opening_bank")
        with sync_playwright() as p:
            browser = launch_chromium(p, headless=headless)
            kwargs = {"locale": "fa-IR", "ignore_https_errors": False}
            if state_file.is_file():
                kwargs["storage_state"] = str(state_file)
            context = browser.new_context(**kwargs)
            page = context.new_page()
            page.set_default_timeout(12000)
            try:
                page.goto(STATEMENT_URL, wait_until="domcontentloaded", timeout=30000)
                page.wait_for_timeout(1500)
                status_code = _probe(page)
                if status_code != "session_verified" and not check_only:
                    _status("opening_bank")
                    page.goto(START_URL, wait_until="domcontentloaded", timeout=30000)
                    page.wait_for_timeout(1000)
                    _fill_existing_login(page, username, password)
                    status_code = _probe(page)
                if status_code == "session_verified":
                    # Persist only after authenticated statement interface check.
                    context.storage_state(path=str(state_file))
                    state_file.chmod(0o600)
                    _status("session_verified")
                elif check_only:
                    _status("login_required" if status_code != "untrusted_redirect" else status_code)
                else:
                    _status(status_code)
                    # Leave the bank browser interactive for the human to complete
                    # any bank-supplied SMS/CAPTCHA challenge. No OTP in PNV.
                    end = time.monotonic() + min(900, max(60, int(os.getenv("TEJARAT_LOGIN_WAIT_SEC", "600"))))
                    while time.monotonic() < end:
                        time.sleep(2)
                        new_code = _probe(page)
                        if new_code == "untrusted_redirect":
                            _status(new_code)
                            break
                        if new_code == "session_verified":
                            context.storage_state(path=str(state_file))
                            state_file.chmod(0o600)
                            _status("session_verified")
                            break
                        if new_code != status_code:
                            status_code = new_code
                            _status(status_code)
                    else:
                        _status("manual_login_timeout")
            finally:
                context.close()
                browser.close()
    except Exception:
        # Do not log raw URLs, browser content, sessions or credentials.
        _status("browser_error")
    finally:
        username = password = ""
        with _lock:
            _busy = False


def dispatch(msg: dict) -> dict:
    global _busy
    action = msg.get("action")
    if action not in ALLOWED_ACTIONS:
        return {"ok": False, "code": "invalid_action", **status()}
    if action == "status":
        return {"ok": True, **status()}
    username = msg.get("username", "")
    password = msg.get("password", "")
    if action == "start" and (
        not isinstance(username, str) or not isinstance(password, str)
        or not 0 < len(username) <= 150 or not 0 < len(password) <= 1024
    ):
        return {"ok": False, "code": "credentials_missing", **status()}
    with _lock:
        if _busy:
            return {"ok": False, "code": "already_running", **_state}
        _busy = True
    t = threading.Thread(target=_session_job, args=(username, password, action == "check"), daemon=True)
    t.start()
    return {"ok": True, "state": "starting", "updated_at": int(time.time())}


class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        try:
            raw = self.rfile.readline(4097)
            if not raw or len(raw) > 4096:
                return
            if hasattr(socket, "SO_PEERCRED"):
                uid = struct.unpack("3i", self.request.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, 12))[1]
                allowed = int(os.environ["PNV_TEJARAT_ALLOWED_UID"])
                if uid != allowed:
                    return
            request = json.loads(raw)
            if not isinstance(request, dict):
                return
            reply = dispatch(request)
            self.wfile.write((json.dumps(reply, separators=(",", ":")) + "\n").encode())
        except (ValueError, TypeError, OSError, KeyError):
            return


class Server(socketserver.ThreadingMixIn, socketserver.UnixStreamServer):
    daemon_threads = True
    allow_reuse_address = True


def main():
    path = Path(SOCKET)
    path.parent.mkdir(mode=0o770, parents=True, exist_ok=True)
    if path.is_symlink():
        raise RuntimeError("socket must not be a symlink")
    if path.exists():
        if not path.is_socket():
            raise RuntimeError("socket path blocked by non-socket file")
        path.unlink()
    with Server(str(path), Handler) as server:
        path.chmod(0o660)
        server.serve_forever(poll_interval=0.2)


if __name__ == "__main__":
    main()

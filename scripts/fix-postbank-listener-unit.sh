#!/bin/bash
# فقط systemd را به venv وصل می‌کند (وقتی venv OK است ولی ExecStart قدیمی است)
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
UNIT="/etc/systemd/system/postbank-listener.service"
VENV_PY="${ROOT}/tools/postbank-venv/bin/python"
LISTENER="${ROOT}/tools/postbank_bale_listener.py"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run as root" >&2
    exit 1
fi

if [[ ! -x "$VENV_PY" ]]; then
    echo "venv python not found: $VENV_PY" >&2
    echo "Run: bash <(curl -Ls .../setup-postbank-listener.sh)" >&2
    exit 1
fi

if [[ ! -f "$LISTENER" ]]; then
    echo "listener not found: $LISTENER" >&2
    exit 1
fi

"$VENV_PY" -m py_compile "$LISTENER" || {
    echo "listener syntax error — re-run fix-auto-payment.sh or curl fresh postbank_bale_listener.py" >&2
    exit 1
}

"$VENV_PY" -c "from aiobale import Client; import aiohttp" || {
    echo "venv missing deps — run setup-postbank-listener.sh" >&2
    exit 1
}

systemctl stop postbank-listener 2>/dev/null || true
systemctl reset-failed postbank-listener 2>/dev/null || true

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
curl -fsSL "${BASE}/tools/postbank-listener.service" -o "$UNIT" 2>/dev/null || {
    sed -i "s|^ExecStart=.*|ExecStart=${VENV_PY} ${LISTENER}|" "$UNIT"
}

sed -i "s|^ExecStart=.*|ExecStart=${VENV_PY} ${LISTENER}|" "$UNIT"

systemctl daemon-reload
systemctl enable postbank-listener >/dev/null 2>&1 || true
systemctl start postbank-listener
sleep 2

if systemctl is-active --quiet postbank-listener; then
    echo "OK postbank-listener active"
    systemctl --no-pager status postbank-listener | sed -n '1,8p'
    exit 0
fi

echo "FAILED — journal:" >&2
journalctl -u postbank-listener -n 15 --no-pager >&2
exit 1

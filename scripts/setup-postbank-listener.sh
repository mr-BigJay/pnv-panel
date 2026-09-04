#!/bin/bash
# PNV Panel — نصب/رفع postbank-listener (وابستگی‌های Python + systemd)
#
# یک خط (روی سرور با root):
#   bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/telegram-user-bot-058b/scripts/setup-postbank-listener.sh)
#
# فقط venv/deps (بدون deploy):
#   DEPLOY=0 bash <(curl -Ls .../setup-postbank-listener.sh)
#
set -euo pipefail

ROOT="${ROOT:-}"
REPO="${REPO:-mr-BigJay/pnv-panel}"
BR="${BR:-cursor/telegram-user-bot-058b}"
DEPLOY="${DEPLOY:-1}"
RUN_USER="${RUN_USER:-www-data}"
SERVICE_NAME="${SERVICE_NAME:-postbank-listener}"
VENV_DIR=""
PYTHON_BIN=""

say(){ echo -e "$*"; }
die(){ say "!! $*"; exit 1; }

if [[ "$(id -u)" -ne 0 ]]; then
    die "با root اجرا کنید: sudo bash ..."
fi

detect_root(){
    if [[ -n "$ROOT" && -f "${ROOT}/instant_pay_lib.php" ]]; then
        return 0
    fi

    local candidate found
    for candidate in \
        /var/www/html \
        /var/www/pnv-panel \
        /var/www/panel \
        /usr/share/nginx/html; do
        if [[ -f "${candidate}/instant_pay_lib.php" ]]; then
            ROOT="$candidate"
            return 0
        fi
    done

    found="$(find /var/www /home -maxdepth 6 -name 'instant_pay_lib.php' 2>/dev/null | head -n 1 || true)"
    if [[ -n "$found" ]]; then
        ROOT="$(dirname "$found")"
        return 0
    fi

    return 1
}

deploy_file(){
    local rel="$1"
    local dest="${ROOT}/${rel}"
    local url="https://raw.githubusercontent.com/${REPO}/${BR}/${rel}"
    mkdir -p "$(dirname "$dest")"
    curl -fsSL "$url" -o "$dest"
    chmod 644 "$dest" 2>/dev/null || true
    say "  deploy OK ${rel}"
}

maybe_deploy(){
    [[ "$DEPLOY" == "1" ]] || return 0

    say ">> deploy فایل‌های listener از ${BR}..."
    local files=(
        tools/postbank_bale_listener.py
        tools/postbank-listener.service
        tools/requirements-postbank.txt
        postbank-ingest.php
        bale_lib.php
        bale-webhook.php
        instant_pay_lib.php
        admin/bale.php
    )
    local rel
    for rel in "${files[@]}"; do
        deploy_file "$rel"
    done
}

install_os_packages(){
    say ">> بسته‌های سیستمی Python..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq \
        python3 \
        python3-venv \
        python3-pip \
        curl \
        ca-certificates
}

create_venv(){
    VENV_DIR="${ROOT}/tools/postbank-venv"
    PYTHON_BIN="${VENV_DIR}/bin/python"
    local req="${ROOT}/tools/requirements-postbank.txt"

    [[ -f "$req" ]] || die "فایل نیست: ${req}"

    say ">> ساخت venv در ${VENV_DIR}..."
    rm -rf "$VENV_DIR"
    python3 -m venv "$VENV_DIR"
    "${VENV_DIR}/bin/pip" install --upgrade pip wheel
    "${VENV_DIR}/bin/pip" install -r "$req"

    say ">> تست import..."
    "$PYTHON_BIN" - <<'PY'
import aiobale
import aiohttp
print("OK aiobale", getattr(aiobale, "__version__", "?"))
print("OK aiohttp", aiohttp.__version__)
PY
}

write_systemd_unit(){
    local unit="/etc/systemd/system/${SERVICE_NAME}.service"
    local listener="${ROOT}/tools/postbank_bale_listener.py"
    local env_file="${ROOT}/db/postbank-listener.env"

    [[ -f "$listener" ]] || die "listener پیدا نشد: ${listener}"

    cat > "$unit" <<EOF
[Unit]
Description=PostBank Bale auto-ingest listener for pnv-panel
After=network-online.target
Wants=network-online.target
StartLimitIntervalSec=300
StartLimitBurst=5

[Service]
Type=simple
User=${RUN_USER}
WorkingDirectory=${ROOT}
Environment=POSTBANK_BALE_SESSION=${ROOT}/db/bale_user_session.bale
Environment=POSTBANK_INGEST_URL=https://panel.ticketin.ir/postbank-ingest.php
Environment=POSTBANK_WEBHOOK_URL=https://panel.ticketin.ir/bale-webhook.php
Environment=POSTBANK_FORWARD_BOT=Jay24x7Pusbank_bot
Environment=POSTBANK_BALE_CONFIG=${ROOT}/db/bale.json
EnvironmentFile=-${env_file}
ExecStart=${PYTHON_BIN} ${listener}
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable "$SERVICE_NAME" >/dev/null 2>&1 || true
}

fix_permissions(){
    mkdir -p "${ROOT}/db"
    chmod 775 "${ROOT}/db" 2>/dev/null || true

    if id "$RUN_USER" >/dev/null 2>&1; then
        chown -R "$RUN_USER":"$RUN_USER" "${ROOT}/tools/postbank-venv" 2>/dev/null || true
        chown "$RUN_USER":"$RUN_USER" "${ROOT}/db" 2>/dev/null || true
        if [[ -f "${ROOT}/db/bale_user_session.bale" ]]; then
            chown "$RUN_USER":"$RUN_USER" "${ROOT}/db/bale_user_session.bale" 2>/dev/null || true
            chmod 600 "${ROOT}/db/bale_user_session.bale" 2>/dev/null || true
        fi
        if [[ -f "${ROOT}/db/postbank-listener.env" ]]; then
            chown "$RUN_USER":"$RUN_USER" "${ROOT}/db/postbank-listener.env" 2>/dev/null || true
            chmod 600 "${ROOT}/db/postbank-listener.env" 2>/dev/null || true
        fi
    fi
}

detect_root || die "مسیر پنل پیدا نشد. ROOT=/var/www/html bash $0"

if ! id "$RUN_USER" >/dev/null 2>&1; then
    RUN_USER="root"
    say ">> www-data نبود؛ سرویس با root"
fi

say "=== setup postbank-listener ==="
say "ROOT=${ROOT}"
say ""

# توقف حلقه crash
if systemctl is-active --quiet "$SERVICE_NAME" 2>/dev/null || systemctl is-failed --quiet "$SERVICE_NAME" 2>/dev/null; then
    say ">> توقف سرویس (رفع restart loop)..."
    systemctl stop "$SERVICE_NAME" 2>/dev/null || true
    systemctl reset-failed "$SERVICE_NAME" 2>/dev/null || true
fi

maybe_deploy
install_os_packages
create_venv
write_systemd_unit
fix_permissions

say ">> راه‌اندازی سرویس..."
systemctl start "$SERVICE_NAME"
sleep 2

if systemctl is-active --quiet "$SERVICE_NAME"; then
    say ">> سرویس فعال است"
else
    say "!! سرویس بالا نیامد:"
    journalctl -u "$SERVICE_NAME" -n 30 --no-pager || true
    say ""
    say "اگر سشن بله ندارید، یک‌بار login (تعاملی):"
    say "  cd ${ROOT}"
    say "  sudo -u ${RUN_USER} ${PYTHON_BIN} tools/postbank_bale_listener.py --login --session ${ROOT}/db/bale_user_session.bale"
    say "  systemctl restart ${SERVICE_NAME}"
    exit 1
fi

say ""
say "=== تمام ==="
systemctl --no-pager status "$SERVICE_NAME" | sed -n '1,10p' || true
say ""
say "لاگ: journalctl -u ${SERVICE_NAME} -f"
say ""
say "env لازم: ${ROOT}/db/postbank-listener.env"
say "  POSTBANK_INGEST_SECRET=...  (از ادمین → بله)"
say "  POSTBANK_ADMIN_CHAT_ID=..."
say "  POSTBANK_FORWARD_BOT=Jay24x7Pusbank_bot"
say ""
say "login تعاملی (اگر سشن ندارید):"
say "  cd ${ROOT} && sudo -u ${RUN_USER} ${PYTHON_BIN} tools/postbank_bale_listener.py --login --session ${ROOT}/db/bale_user_session.bale"

#!/bin/bash
# Deploy Telegram admin notify fixes — run ON THE SERVER as root
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-}"
SERVICE_NAME="${SERVICE_NAME:-pnv-telegram-poll}"

say(){ echo -e "$*"; }
die(){ say "!! $*"; exit 1; }

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

deploy_one(){
    local rel="$1"
    local dest="${ROOT}/${rel}"
    local tmp
    tmp="$(mktemp)"

    curl -fsSL "${BASE}/${rel}" -o "$tmp"
    mkdir -p "$(dirname "$dest")"
    cp -f "$tmp" "$dest"
    rm -f "$tmp"
    chmod 644 "$dest" 2>/dev/null || true
    say "  OK ${rel} -> ${dest}"
}

detect_root || die "مسیر پنل پیدا نشد. ROOT=/path/to/panel bash $0"

say "=== Deploy Telegram notify fix (${BR}) ==="
say "ROOT=${ROOT}"
say ""

files=(
    telegram_lib.php
    instant_pay_lib.php
    xui_lib.php
)

for rel in "${files[@]}"; do
    deploy_one "$rel"
done

if id www-data >/dev/null 2>&1; then
    chown www-data:www-data "${ROOT}/telegram_lib.php" "${ROOT}/instant_pay_lib.php" "${ROOT}/xui_lib.php" 2>/dev/null || true
fi

say ""
say ">> Poll process / service"

if systemctl list-unit-files "${SERVICE_NAME}.service" --no-legend 2>/dev/null | grep -q "${SERVICE_NAME}.service"; then
    systemctl restart "$SERVICE_NAME"
    say "  restarted ${SERVICE_NAME}"
elif pgrep -f "php .*telegram_poll.php" >/dev/null 2>&1; then
    pkill -f "php .*telegram_poll.php" || true
    sleep 1
    if [[ -f "${ROOT}/telegram_poll.php" ]] && command -v php >/dev/null 2>&1; then
        nohup php "${ROOT}/telegram_poll.php" --loop >> /var/log/telegram_poll.log 2>&1 &
        say "  restarted telegram_poll.php via nohup (no systemd unit)"
    fi
else
    say "  !! سرویس ${SERVICE_NAME} نصب نیست."
    say "  برای نصب polling:"
    say "  bash <(curl -Ls ${BASE}/scripts/setup-telegram-bot.sh)"
fi

say ""
say "Done."

#!/bin/bash
# PNV Panel — راه‌اندازی خودکار polling ربات تلگرام + cron اعلان انقضا/حجم
#
# یک خط (روی سرور، با root):
#   bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/telegram-user-bot-058b/scripts/setup-telegram-bot.sh)
#
# با مسیر مشخص:
#   ROOT=/var/www/html bash <(curl -Ls .../setup-telegram-bot.sh)
#
# فقط deploy فایل‌ها (بدون systemd/cron):
#   SETUP=0 bash <(curl -Ls .../setup-telegram-bot.sh)
#
set -euo pipefail

ROOT="${ROOT:-}"
CRON_HOUR="${CRON_HOUR:-9}"
CRON_MINUTE="${CRON_MINUTE:-0}"
SERVICE_NAME="${SERVICE_NAME:-pnv-telegram-poll}"
RUN_USER="${RUN_USER:-www-data}"
PHP_BIN="${PHP_BIN:-}"

REPO="${REPO:-mr-BigJay/pnv-panel}"
BR="${BR:-cursor/telegram-user-bot-058b}"
DEPLOY="${DEPLOY:-1}"
SETUP="${SETUP:-1}"

say(){ echo -e "$*"; }
die(){ say "!! $*"; exit 1; }

deploy_file(){
    local rel="$1"
    local dest="${ROOT}/${rel}"
    local url="https://raw.githubusercontent.com/${REPO}/${BR}/${rel}"
    mkdir -p "$(dirname "$dest")"
    curl -fsSL "$url" -o "$dest"
    say "  deploy OK ${rel}"
}

maybe_deploy(){
    [[ "$DEPLOY" == "1" ]] || return 0

    say ">> deploy فایل‌های ربات از branch ${BR}..."
    local files=(
        telegram_poll.php
        telegram_user_lib.php
        telegram_lib.php
        profile-api.php
        dashboard.php
        telegram.php
        telegram_ui.css
        form_validation_fa.js
        form_validation_fa.php
        support_lib.php
        xui_lib.php
        admin/payments.php
        admin/renews.php
        admin/telegram.php
        admin/campaign-announcements.php
        scripts/telegram_notify_expiry.php
        scripts/setup-telegram-bot.sh
    )
    local rel
    for rel in "${files[@]}"; do
        deploy_file "$rel"
    done
}

if [[ "$(id -u)" -ne 0 ]]; then
    die "این اسکریپت را با root اجرا کنید: sudo bash ..."
fi

detect_root(){
    if [[ -n "$ROOT" && ( -f "${ROOT}/telegram_poll.php" || -f "${ROOT}/dashboard.php" ) ]]; then
        return 0
    fi

    local candidate
    for candidate in \
        /var/www/html \
        /var/www/pnv-panel \
        /var/www/panel \
        /usr/share/nginx/html; do
        if [[ -f "${candidate}/telegram_poll.php" || -f "${candidate}/dashboard.php" ]]; then
            ROOT="$candidate"
            return 0
        fi
    done

    local found
    found="$(find /var/www -maxdepth 4 \( -name 'telegram_poll.php' -o -name 'dashboard.php' \) 2>/dev/null | head -n 1 || true)"

    if [[ -n "$found" ]]; then
        ROOT="$(dirname "$found")"
        return 0
    fi

    return 1
}

detect_php(){
    if [[ -n "$PHP_BIN" && -x "$PHP_BIN" ]]; then
        return 0
    fi

    for candidate in /usr/bin/php /usr/local/bin/php php; do
        if command -v "$candidate" >/dev/null 2>&1; then
            PHP_BIN="$(command -v "$candidate")"
            return 0
        fi
    done

    return 1
}

detect_root || die "مسیر پنل پیدا نشد. ROOT=/path/to/panel bash $0"
detect_php || die "php نصب نیست. apt install php-cli php-curl"

maybe_deploy

if [[ "$SETUP" != "1" ]]; then
    say ""
    say "=== deploy تمام شد (SETUP=0 — سرویس/cron نصب نشد) ==="
    exit 0
fi

POLL_FILE="${ROOT}/telegram_poll.php"
CRON_FILE="${ROOT}/scripts/telegram_notify_expiry.php"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
LOG_POLL="/var/log/${SERVICE_NAME}.log"
LOG_CRON="/var/log/telegram_notify_expiry.log"

[[ -f "$POLL_FILE" ]] || die "فایل پیدا نشد: $POLL_FILE (کد جدید را deploy کنید)"
[[ -f "${ROOT}/telegram_user_lib.php" ]] || die "telegram_user_lib.php نیست — ابتدا branch جدید را deploy کنید"
[[ -f "$CRON_FILE" ]] || die "فایل پیدا نشد: $CRON_FILE"

if ! id "$RUN_USER" >/dev/null 2>&1; then
    RUN_USER="root"
    say ">> کاربر www-data نبود؛ سرویس با root اجرا می‌شود"
fi

mkdir -p "${ROOT}/db"
chmod 775 "${ROOT}/db" 2>/dev/null || true

if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "${ROOT}/db" 2>/dev/null || true
fi

say "=== راه‌اندازی ربات تلگرام PNV ==="
say "مسیر پنل: ${ROOT}"
say "PHP: ${PHP_BIN}"
say ""

# توقف نمونه‌های قدیمی poll (بدون systemd)
if pgrep -f "php .*telegram_poll.php" >/dev/null 2>&1; then
    say ">> توقف اجرای قبلی telegram_poll..."
    pkill -f "php .*telegram_poll.php" || true
    sleep 1
fi

cat > "$SERVICE_FILE" <<EOF
[Unit]
Description=PNV Panel Telegram Bot Poll
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
WorkingDirectory=${ROOT}
ExecStart=${PHP_BIN} ${POLL_FILE} --loop
Restart=always
RestartSec=5
User=${RUN_USER}
StandardOutput=append:${LOG_POLL}
StandardError=append:${LOG_POLL}

[Install]
WantedBy=multi-user.target
EOF

touch "$LOG_POLL" "$LOG_CRON"
chmod 664 "$LOG_POLL" "$LOG_CRON" 2>/dev/null || true

if id "$RUN_USER" >/dev/null 2>&1; then
    chown "$RUN_USER":"$RUN_USER" "$LOG_POLL" 2>/dev/null || true
fi

systemctl daemon-reload
systemctl enable "$SERVICE_NAME" >/dev/null
systemctl restart "$SERVICE_NAME"

sleep 1

if systemctl is-active --quiet "$SERVICE_NAME"; then
    say ">> سرویس polling فعال شد: ${SERVICE_NAME}"
else
    say "!! سرویس بالا نیامد. لاگ:"
    journalctl -u "$SERVICE_NAME" -n 20 --no-pager || true
    die "systemctl status ${SERVICE_NAME}"
fi

CRON_LINE="${CRON_MINUTE} ${CRON_HOUR} * * * ${PHP_BIN} ${CRON_FILE} >> ${LOG_CRON} 2>&1"
CRON_MARKER="# pnv-telegram-notify-expiry"

TMP_CRON="$(mktemp)"
crontab -l 2>/dev/null | grep -v "$CRON_MARKER" | grep -v "telegram_notify_expiry.php" > "$TMP_CRON" || true
{
    cat "$TMP_CRON"
    echo "${CRON_LINE} ${CRON_MARKER}"
} | crontab -
rm -f "$TMP_CRON"

say ">> cron روزانه ثبت شد (${CRON_HOUR}:${CRON_MINUTE})"
say ""

# تست یک‌بار cron
say ">> تست اجرای cron..."
if "$PHP_BIN" "$CRON_FILE"; then
    say ">> تست cron موفق"
else
    say "!! تست cron ناموفق — db/telegram.json و توکن ربات را بررسی کنید"
fi

say ""
say "=== تمام ==="
say "وضعیت polling:"
systemctl --no-pager status "$SERVICE_NAME" | sed -n '1,8p' || true
say ""
say "cron:"
crontab -l | grep telegram_notify_expiry || true
say ""
say "لاگ polling: tail -f ${LOG_POLL}"
say "لاگ cron:     tail -f ${LOG_CRON}"
say ""
say "یادآوری: در ادمین پنل → تنظیمات بات تلگرام، ربات را فعال و توکن را وارد کنید."
say "ربات 3x-ui روی سرورها باید خاموش باشد (تداخل polling)."

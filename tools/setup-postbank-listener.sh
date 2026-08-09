#!/usr/bin/env bash
# Create venv and install PostBank listener deps (Debian/Ubuntu PEP 668 safe).
# Usage: bash tools/setup-postbank-listener.sh [/var/www/html]

set -euo pipefail

ROOT="${1:-/var/www/html}"
VENV="${ROOT}/tools/postbank-venv"
REQ="${ROOT}/tools/requirements-postbank.txt"

if [[ ! -f "$REQ" ]]; then
  echo "ERROR: requirements not found: $REQ"
  exit 1
fi

cd "$ROOT"

if ! command -v python3 >/dev/null 2>&1; then
  echo "ERROR: python3 not installed"
  exit 1
fi

if ! python3 -c 'import venv' >/dev/null 2>&1; then
  echo "Installing python3-venv..."
  apt-get update
  apt-get install -y python3-venv python3-full
fi

echo "Creating venv: $VENV"
python3 -m venv "$VENV"
"$VENV/bin/pip" install --upgrade pip
"$VENV/bin/pip" install -r "$REQ"

mkdir -p db
touch db/bale_webhook.log

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "$VENV" db 2>/dev/null || true
fi

echo
echo "OK. Python venv ready."
echo "Login (interactive, same Bale account as PostBank notices):"
echo "  cd $ROOT"
echo "  $VENV/bin/python tools/postbank_bale_listener.py --login --session db/bale_user_session.bale"
echo
echo "Then set ingest secret from Admin → بله:"
echo "  printf 'POSTBANK_INGEST_SECRET=PASTE_REAL_SECRET_HERE\\n' > db/postbank-listener.env"
echo "  chmod 600 db/postbank-listener.env"
echo "  chown www-data:www-data db/postbank-listener.env db/bale_user_session.bale 2>/dev/null || true"
echo
echo "Enable service:"
echo "  cp tools/postbank-listener.service /etc/systemd/system/"
echo "  systemctl daemon-reload"
echo "  systemctl enable --now postbank-listener"
echo "  systemctl status postbank-listener --no-pager"

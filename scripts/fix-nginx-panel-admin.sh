#!/usr/bin/env bash
set -euo pipefail

# Fix admin 404 on panel.ticketin.ir (patch primary vhost, remove duplicate).
#
#   sudo DOMAIN=panel.ticketin.ir ROOT=/var/www/html bash scripts/fix-nginx-panel-admin.sh

DOMAIN="${DOMAIN:-panel.ticketin.ir}"
ROOT="${ROOT:-/var/www/html}"
PRIMARY="${PRIMARY:-/etc/nginx/sites-enabled/panel.ticketin.ir}"
DUPLICATE="/etc/nginx/sites-enabled/pnv-panel-panel-ticketin-ir.conf"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

if [[ ! -f "$ROOT/bigjay_controller/index.php" ]]; then
  echo "ERROR: missing $ROOT/bigjay_controller/index.php" >&2
  exit 1
fi

PHP_FPM_SOCK="${PHP_FPM_SOCK:-$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' 2>/dev/null | sort -V | tail -1)}"
[[ -n "$PHP_FPM_SOCK" ]] || { echo "ERROR: PHP-FPM socket not found" >&2; exit 1; }

if [[ ! -f "$PRIMARY" ]]; then
  echo "ERROR: primary vhost not found: $PRIMARY" >&2
  echo "Set PRIMARY= path to your panel.ticketin.ir nginx file." >&2
  exit 1
fi

if [[ ! -f "$SCRIPT_DIR/patch-panel-ticketin-nginx.py" ]]; then
  echo "ERROR: run git pull in /opt/pnv-panel first (missing patch-panel-ticketin-nginx.py)" >&2
  exit 1
fi

echo ">> Patch bigjay_controller in $PRIMARY"
cp -a "$PRIMARY" "${PRIMARY}.bak.$(date +%Y%m%d-%H%M%S)"
python3 "$SCRIPT_DIR/patch-panel-ticketin-nginx.py" "$PHP_FPM_SOCK" "$PRIMARY"

if ! grep -q "PNV_BIGJAY_PATCHED" "$PRIMARY"; then
  echo "ERROR: patch did not apply — see manual block in scripts/nginx-bigjay-manual.conf" >&2
  exit 1
fi

if [[ -L "$DUPLICATE" || -f "$DUPLICATE" ]]; then
  echo ">> Remove duplicate vhost (causes conflicting server_name): $DUPLICATE"
  rm -f "$DUPLICATE"
  rm -f "/etc/nginx/sites-available/pnv-panel-panel-ticketin-ir.conf" 2>/dev/null || true
fi

nginx -t
systemctl reload nginx

echo ""
echo ">> Local HTTPS test"
curl -s -o /dev/null -w "  /bigjay_controller/index.php -> %{http_code}\n" \
  -k -H "Host: $DOMAIN" "https://127.0.0.1/bigjay_controller/index.php"
curl -s -o /dev/null -w "  /bigjay_controller/?page=support -> %{http_code}\n" \
  -k -H "Host: $DOMAIN" "https://127.0.0.1/bigjay_controller/index.php?page=support"

echo ""
echo "Done: https://${DOMAIN}/bigjay_controller/"

#!/bin/bash
# تعمیر یک‌مرحله‌ای خرید/تمدید خودکار — روی server3 اجرا کنید
set -euo pipefail

BR="${BR:-cursor/instant-pay-back-no-cancel-f8e6}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-}"

say(){ echo "[fix-auto] $*"; }

detect_root(){
  local candidate found
  for candidate in /var/www/html /var/www/pnv-panel /var/www/panel /usr/share/nginx/html; do
    if [[ -f "${candidate}/instant_pay_lib.php" ]]; then
      ROOT="$candidate"
      return
    fi
  done

  found="$(find /var/www /home -maxdepth 6 -name instant_pay_lib.php 2>/dev/null | head -n 1 || true)"
  [[ -n "$found" ]] || { say "ERROR: panel path not found; run with ROOT=/real/path"; exit 1; }
  ROOT="$(dirname "$found")"
}

if [[ -z "$ROOT" || ! -f "${ROOT}/instant_pay_lib.php" ]]; then
  detect_root
fi

say "=== Fix auto payment (branch ${BR}) ==="
say "Target: ${ROOT}"

files=(
  "bale_lib.php"
  "bale-webhook.php"
  "postbank-ingest.php"
  "instant_pay_lib.php"
  "xui_lib.php"
  "telegram_lib.php"
  "tools/postbank_bale_listener.py"
  "tools/postbank-listener.service"
  "tools/requirements-postbank.txt"
  "scripts/setup-postbank-listener.sh"
  "admin/diag-bale.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  say "OK ${rel}"
done

say "=== PHP syntax ==="
php -l "${ROOT}/bale_lib.php" >/dev/null
php -l "${ROOT}/instant_pay_lib.php" >/dev/null
php -l "${ROOT}/bale-webhook.php" >/dev/null
php -l "${ROOT}/postbank-ingest.php" >/dev/null
say "OK syntax"

say "=== Parser self-test ==="
php -r '
require "'"${ROOT}"'/bale_lib.php";
$sample = "پست بانک\nواريز به كارت: 6156\n+998,190\n1405/05/10\n9:47\nمانده: 44,108,899 ريال";
if(!baleLooksLikeDeposit($sample)) { fwrite(STDERR, "FAIL deposit detect\n"); exit(1); }
$am = baleExtractRialAmounts($sample);
if(empty($am) || intval($am[0]) !== 998190) { fwrite(STDERR, "FAIL amount parse\n"); exit(1); }
echo "OK parser amount=".intval($am[0])."\n";
'

say "=== Listener ==="
if systemctl cat postbank-listener >/dev/null 2>&1 && [[ -x "${ROOT}/tools/postbank-venv/bin/python" ]]; then
  systemctl daemon-reload
  systemctl restart postbank-listener
  systemctl is-active --quiet postbank-listener || {
    journalctl -u postbank-listener -n 30 --no-pager
    exit 1
  }
  say "OK listener restarted"
else
  ROOT="${ROOT}" BR="${BR}" bash "${ROOT}/scripts/setup-postbank-listener.sh"
fi

touch "${ROOT}/db/bale_webhook.log" 2>/dev/null || true
chmod 664 "${ROOT}/db/bale_webhook.log" 2>/dev/null || true

say "=== Endpoint ==="
curl -fsS "https://panel.ticketin.ir/postbank-ingest.php" | head -c 200 || true
echo ""

say ""
say "Done."
say "  1) https://panel.ticketin.ir/bigjay_controller/diag-bale.php (if wrapper exists)"
say "  2) systemctl status postbank-listener"
say "  3) journalctl -u postbank-listener -n 30 --no-pager"
say "  4) tail -30 ${ROOT}/db/bale_webhook.log"
say ""
say "If session expired:"
say "  cd ${ROOT} && sudo -u www-data tools/postbank-venv/bin/python tools/postbank_bale_listener.py --login --session ${ROOT}/db/bale_user_session.bale"

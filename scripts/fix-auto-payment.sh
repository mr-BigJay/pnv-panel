#!/bin/bash
# تعمیر یک‌مرحله‌ای خرید/تمدید خودکار — روی server3 اجرا کنید
set -euo pipefail

BR="${BR:-cursor/telegram-user-bot-058b}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

say(){ echo "[fix-auto] $*"; }

say "=== Fix auto payment (branch ${BR}) ==="
say "Target: ${ROOT}"

files=(
  "bale_lib.php"
  "bale-webhook.php"
  "postbank-ingest.php"
  "instant_pay_lib.php"
  "xui_lib.php"
  "telegram_lib.php"
  "telegram_user_lib.php"
  "tools/postbank_bale_listener.py"
  "tools/postbank-listener.service"
  "admin/diag-bale.php"
  "bigjay_controller/diag-bale.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}?v=$(date +%s)" -o "${dest}"
  say "OK ${rel}"
done

say "=== Python listener syntax ==="
if [[ -x "${ROOT}/tools/postbank-venv/bin/python" ]]; then
  "${ROOT}/tools/postbank-venv/bin/python" -m py_compile "${ROOT}/tools/postbank_bale_listener.py"
else
  python3 -m py_compile "${ROOT}/tools/postbank_bale_listener.py"
fi
say "OK listener syntax"

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

say "=== Listener unit + venv ==="
if [[ -x "${ROOT}/scripts/fix-postbank-listener-unit.sh" ]]; then
  ROOT="${ROOT}" BR="${BR}" bash "${ROOT}/scripts/fix-postbank-listener-unit.sh" || true
else
  curl -fsSL "${BASE}/scripts/fix-postbank-listener-unit.sh" | ROOT="${ROOT}" BR="${BR}" bash || true
fi

if [[ -x "${ROOT}/scripts/setup-postbank-listener.sh" ]] && [[ ! -x "${ROOT}/tools/postbank-venv/bin/python" ]]; then
  say "venv missing — run setup-postbank-listener.sh"
fi

touch "${ROOT}/db/bale_webhook.log" 2>/dev/null || true
chmod 664 "${ROOT}/db/bale_webhook.log" 2>/dev/null || true

say "=== Endpoints ==="
curl -fsS "${ROOT%/html}/html/postbank-ingest.php" 2>/dev/null | head -c 100 || curl -fsS "https://panel.ticketin.ir/postbank-ingest.php" | head -c 100 || true
echo ""

say ""
say "Done."
say "  1) https://panel.ticketin.ir/bigjay_controller/diag-bale.php"
say "  2) systemctl status postbank-listener"
say "  3) journalctl -u postbank-listener -n 30 --no-pager"
say "  4) tail -30 ${ROOT}/db/bale_webhook.log"
say ""
say "If session expired:"
say "  cd ${ROOT} && sudo -u www-data tools/postbank-venv/bin/python tools/postbank_bale_listener.py --login --session ${ROOT}/db/bale_user_session.bale"

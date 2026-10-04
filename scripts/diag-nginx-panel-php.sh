#!/usr/bin/env bash
# Quick nginx routing diagnosis for panel.ticketin.ir (run on server as root).
set -euo pipefail

DOMAIN="${DOMAIN:-panel.ticketin.ir}"
PRIMARY="${PRIMARY:-/etc/nginx/sites-enabled/panel.ticketin.ir}"
ROOT="${ROOT:-/var/www/html}"

echo "=== PHP-FPM socket ==="
ls -la /run/php/php*-fpm.sock 2>/dev/null || echo "MISSING php-fpm socket"

echo ""
echo "=== panel vhost file ==="
[[ -f "$PRIMARY" ]] && echo "OK $PRIMARY" || { echo "MISSING $PRIMARY"; exit 1; }

echo ""
echo "=== bigjay + php locations (grep) ==="
grep -nE "bigjay_controller|location ~ \\\\.php|proxy_pass|PNV_BIGJAY" "$PRIMARY" || true

if grep -q "location\s\+[^\n]*bigjay_controller" "$PRIMARY" && \
   python3 - "$PRIMARY" <<'PY'
import re, sys
from pathlib import Path
text = Path(sys.argv[1]).read_text()
m = re.search(r"location\s+\^~\s+/admin/\s*\{", text)
if not m:
    sys.exit(0)
start = m.start()
depth = i = 0
i = text.find("{", start)
while i < len(text):
    if text[i] == "{": depth += 1
    elif text[i] == "}":
        depth -= 1
        if depth == 0:
            body = text[start:i]
            if re.search(r"location\s+[^\n]*bigjay_controller", body):
                sys.exit(1)
            break
    i += 1
sys.exit(0)
PY
then
  echo "WARN: bigjay_controller may be NESTED inside location /admin/ — nginx will 404"
fi

echo ""
echo "=== HTTPS localhost ==="
for path in /bigjay_controller/ /bigjay_controller/index.php /index.php; do
  code="$(curl -s -o /dev/null -w '%{http_code}' -k -H "Host: $DOMAIN" "https://127.0.0.1${path}" 2>/dev/null || echo err)"
  echo "  $path -> HTTP $code"
done

echo ""
echo "If bigjay is 404 but files exist under $ROOT, re-apply patch:"
echo "  cd /opt/pnv-panel && git pull && sudo bash scripts/fix-nginx-panel-admin.sh"

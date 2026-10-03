#!/usr/bin/env bash
set -euo pipefail

# Run ON server3 — test whether nginx serves /var/www/html for panel.ticketin.ir
DOMAIN="${DOMAIN:-panel.ticketin.ir}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Files on disk (ROOT=$ROOT) ==="
for f in index.php bigjay_controller/index.php admin/index.php; do
  if [[ -f "$ROOT/$f" ]]; then
    echo "  OK $ROOT/$f ($(stat -c%s "$ROOT/$f") bytes)"
  else
    echo "  MISSING $ROOT/$f"
  fi
done

echo ""
echo "=== Local HTTP (Host: $DOMAIN) ==="
for path in / /index.php /bigjay_controller/ /bigjay_controller/index.php; do
  code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" "http://127.0.0.1${path}" 2>/dev/null || echo "err")"
  echo "  $path -> HTTP $code"
done

echo ""
echo "=== Nginx server_name + root for $DOMAIN ==="
if [[ -d /etc/nginx/sites-enabled ]]; then
  grep -rl "panel.ticketin.ir\|ticketin" /etc/nginx/sites-enabled/ 2>/dev/null | while read -r f; do
    echo "--- $f ---"
    awk '
      /server\s*\{/ { inserver=1; buf=$0 ORS; next }
      inserver { buf=buf $0 ORS }
      inserver && /\}/ {
        if (buf ~ /panel\.ticketin\.ir/ || buf ~ /ticketin/) print buf
        inserver=0; buf=""
      }
    ' "$f" 2>/dev/null | head -c 4000
    echo ""
  done
else
  echo "No /etc/nginx/sites-enabled"
fi

echo ""
echo "If local curl is 200 but public URL is 404, DNS/CDN points to another server."
echo "If local curl is 404, fix nginx root to $ROOT or remove broken alias for /bigjay_controller"

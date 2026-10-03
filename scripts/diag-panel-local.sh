#!/usr/bin/env bash
set -euo pipefail

# Run ON the web server — panel.ticketin.ir 404/301 diagnosis
DOMAIN="${DOMAIN:-panel.ticketin.ir}"
ROOT="${ROOT:-/var/www/html}"

echo "=== This server public IPv4 ==="
for cmd in "curl -4 -fsS --max-time 5 icanhazip.com" "curl -4 -fsS --max-time 5 api.ipify.org"; do
  if ip="$($cmd 2>/dev/null)"; then
    echo "  $ip  ($cmd)"
    break
  fi
done

echo ""
echo "=== DNS $DOMAIN ==="
if command -v dig >/dev/null 2>&1; then
  dig +short A "$DOMAIN" | sed 's/^/  /'
else
  echo "  (dig not installed)"
fi

echo ""
echo "=== Files (ROOT=$ROOT) ==="
for f in index.php bigjay_controller/index.php admin/index.php; do
  [[ -f "$ROOT/$f" ]] && echo "  OK $ROOT/$f" || echo "  MISSING $ROOT/$f"
done

echo ""
echo "=== HTTP localhost (follow redirects) ==="
for path in / /index.php /bigjay_controller/index.php; do
  out="$(curl -s -o /dev/null -w 'final=%{url_effective} code=%{http_code} redirects=%{num_redirects}' \
    -L -H "Host: $DOMAIN" "http://127.0.0.1${path}" 2>/dev/null || echo "curl failed")"
  echo "  $path -> $out"
done

echo ""
echo "=== HTTPS localhost (SNI, insecure) ==="
if curl -s -o /dev/null -k --max-time 5 "https://127.0.0.1/" 2>/dev/null; then
  for path in / /bigjay_controller/index.php; do
    out="$(curl -s -o /dev/null -w 'code=%{http_code}' -k \
      -H "Host: $DOMAIN" "https://127.0.0.1${path}" 2>/dev/null || echo "fail")"
    echo "  $path -> $out"
  done
else
  echo "  (no HTTPS on 127.0.0.1 or connection refused)"
fi

echo ""
echo "=== Nginx: search all configs ==="
if [[ -d /etc/nginx ]]; then
  sudo grep -r "server_name\|ticketin\|bigjay_controller\|root " /etc/nginx/ 2>/dev/null \
    | grep -v ".default" | head -40 || echo "  (no matches — try: sudo nginx -T | less)"
else
  echo "  /etc/nginx not found (Apache?)"
  [[ -d /etc/apache2 ]] && sudo grep -r "ticketin\|DocumentRoot" /etc/apache2/sites-enabled/ 2>/dev/null | head -20
fi

echo ""
echo "=== If DNS IP != this server IP ==="
echo "  panel.ticketin.ir is served by ANOTHER machine; fix deploy/nginx THERE."
echo "=== If local HTTPS code=404 ==="
echo "  Fix ssl server block root -> $ROOT (see scripts/nginx-snippet-panel-root.example)"

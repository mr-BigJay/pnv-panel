#!/usr/bin/env bash
set -euo pipefail

# Fix admin 404: install/repair nginx vhost for panel.ticketin.ir -> ROOT (/var/www/html).
# Must run as root on the machine whose IP matches DNS for the domain.
#
#   sudo DOMAIN=panel.ticketin.ir ROOT=/var/www/html bash scripts/fix-nginx-panel-admin.sh

DOMAIN="${DOMAIN:-panel.ticketin.ir}"
ROOT="${ROOT:-/var/www/html}"
SITE_NAME="${SITE_NAME:-pnv-panel-${DOMAIN//./-}}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo DOMAIN=$DOMAIN ROOT=$ROOT bash $0" >&2
  exit 1
fi

if [[ ! -f "$ROOT/bigjay_controller/index.php" || ! -f "$ROOT/admin/index.php" ]]; then
  echo "ERROR: admin files missing under $ROOT — deploy code first." >&2
  exit 1
fi

PHP_FPM_SOCK="${PHP_FPM_SOCK:-}"
if [[ -z "$PHP_FPM_SOCK" ]]; then
  PHP_FPM_SOCK="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' 2>/dev/null | sort -V | tail -1 || true)"
fi
[[ -n "$PHP_FPM_SOCK" ]] || { echo "ERROR: PHP-FPM socket not found under /run/php" >&2; exit 1; }

echo ">> Domain: $DOMAIN"
echo ">> Root:   $ROOT"
echo ">> PHP:    $PHP_FPM_SOCK"

SERVER_IP="$(curl -4 -fsS --max-time 6 icanhazip.com 2>/dev/null || true)"
DNS_IP="$(dig +short A "$DOMAIN" 2>/dev/null | head -1 || true)"
echo ">> Server public IP: ${SERVER_IP:-unknown}"
echo ">> DNS A record:     ${DNS_IP:-unknown}"
if [[ -n "$SERVER_IP" && -n "$DNS_IP" && "$SERVER_IP" != "$DNS_IP" ]]; then
  echo "WARNING: DNS does not point to this server. Fix DNS or run this script on $DNS_IP host." >&2
fi

if ! command -v nginx >/dev/null 2>&1; then
  echo "ERROR: nginx not installed. Use Apache fix or install nginx." >&2
  exit 1
fi

SSL_CERT="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
SSL_KEY="/etc/letsencrypt/live/${DOMAIN}/privkey.pem"
HAS_SSL=0
[[ -f "$SSL_CERT" && -f "$SSL_KEY" ]] && HAS_SSL=1

AVAIL="/etc/nginx/sites-available/${SITE_NAME}.conf"
ENABLED="/etc/nginx/sites-enabled/${SITE_NAME}.conf"

mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled

write_http_only() {
  cat > "$AVAIL" <<NGX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${ROOT};
    index index.php index.html;
    client_max_body_size 32m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ^~ /bigjay_controller/ {
        try_files \$uri \$uri/ /bigjay_controller/index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }

    location ~ /\\. {
        deny all;
    }
}
NGX
}

write_ssl() {
  cat > "$AVAIL" <<NGX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${DOMAIN};

    root ${ROOT};
    index index.php index.html;
    client_max_body_size 32m;

    ssl_certificate     ${SSL_CERT};
    ssl_certificate_key ${SSL_KEY};
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ^~ /bigjay_controller/ {
        try_files \$uri \$uri/ /bigjay_controller/index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }

    location ~ /\\. {
        deny all;
    }
}
NGX
}

if [[ "$HAS_SSL" == "1" ]]; then
  echo ">> Writing HTTPS vhost (Let's Encrypt certs found)"
  write_ssl
else
  echo ">> Writing HTTP-only vhost (no certs at $SSL_CERT)"
  write_http_only
fi

ln -sf "$AVAIL" "$ENABLED"

# Disable stale default only if it conflicts (optional soft)
# rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t
systemctl reload nginx || systemctl restart nginx

echo ""
echo ">> Local test"
curl -s -o /dev/null -w "  HTTP  /bigjay_controller/index.php -> %{http_code}\n" \
  -H "Host: $DOMAIN" "http://127.0.0.1/bigjay_controller/index.php" || true
if [[ "$HAS_SSL" == "1" ]]; then
  curl -s -o /dev/null -w "  HTTPS /bigjay_controller/index.php -> %{http_code}\n" \
    -k -H "Host: $DOMAIN" "https://127.0.0.1/bigjay_controller/index.php" || true
fi

echo ""
echo "Done. Open: https://${DOMAIN}/bigjay_controller/"
echo "Config: $AVAIL"

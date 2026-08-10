#!/bin/bash
# تمدید SSL برای panel.ticketin.ir — روی سرور اجرا کنید: bash renew-ssl-panel.sh
set -euo pipefail

DOMAIN="${1:-panel.ticketin.ir}"
EMAIL="${2:-}"

echo "=== SSL renew: ${DOMAIN} ==="

if ! command -v certbot >/dev/null 2>&1; then
  echo "certbot نصب نیست. نصب: apt install -y certbot python3-certbot-nginx"
  exit 1
fi

if ! command -v nginx >/dev/null 2>&1; then
  echo "nginx پیدا نشد."
  exit 1
fi

if certbot certificates 2>/dev/null | grep -q "Certificate Name: ${DOMAIN}"; then
  echo "[1/3] تمدید گواهی موجود..."
  certbot renew --nginx --cert-name "${DOMAIN}" --quiet
else
  echo "[1/3] گواهی نبود — صدور جدید..."
  if [[ -n "${EMAIL}" ]]; then
    certbot certonly --nginx -d "${DOMAIN}" -m "${EMAIL}" --agree-tos --no-eff-email
  else
    certbot certonly --nginx -d "${DOMAIN}"
  fi
fi

echo "[2/3] اتصال گواهی به nginx..."
certbot install --nginx --cert-name "${DOMAIN}" --redirect || true

echo "[3/3] ری‌لود nginx..."
nginx -t
systemctl reload nginx

echo ""
echo "=== وضعیت ==="
certbot certificates | sed -n "/Certificate Name: ${DOMAIN}/,/Certificate Path/p" || true
echo ""
echo "HTTPS:"
echo | openssl s_client -servername "${DOMAIN}" -connect "${DOMAIN}:443" 2>/dev/null \
  | openssl x509 -noout -dates -subject

echo ""
echo "تمام. مرورگر: Ctrl+Shift+R"

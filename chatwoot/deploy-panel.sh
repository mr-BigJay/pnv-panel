#!/bin/bash
set -euo pipefail

BR="${1:-cursor/chatwoot-integration-af0f}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/$BR"
HTML="/var/www/html"
NGINX_AVAIL="/etc/nginx/sites-available/panel.ticketin.ir"
NGINX_ENABLED="/etc/nginx/sites-enabled/panel.ticketin.ir"

echo "==> Deploy from branch: $BR"

mkdir -p "$HTML/bigjay_controller"

for f in index.php users.php chatwoot-settings.php plans.php downloads.php user-profile.php support-api.php; do
  curl -fL -o "$HTML/bigjay_controller/$f" "$BASE/bigjay_controller/$f"
done

for f in auth.php index.php support.php support-api.php; do
  curl -fL -o "$HTML/admin/$f" "$BASE/admin/$f"
done

curl -fL -o "$HTML/support.php" "$BASE/support.php"
curl -fL -o "$HTML/support_lib.php" "$BASE/support_lib.php"
curl -fL -o "$HTML/support_ui.css" "$BASE/support_ui.css"
curl -fL -o "$HTML/support_ui.js" "$BASE/support_ui.js"
curl -fL -o "$HTML/dashboard.php" "$BASE/dashboard.php"

curl -fL -o "$NGINX_AVAIL" "$BASE/chatwoot/nginx-panel.ticketin.ir.ssl.conf"

if [ ! -e "$NGINX_ENABLED" ]; then
  ln -sf "$NGINX_AVAIL" "$NGINX_ENABLED"
fi

if [ -f "$NGINX_ENABLED" ] && ! cmp -s "$NGINX_AVAIL" "$NGINX_ENABLED"; then
  echo "WARNING: sites-enabled differs from sites-available — fixing symlink"
  ln -sf "$NGINX_AVAIL" "$NGINX_ENABLED"
fi

echo "==> Nginx bigjay_controller block:"
grep -A5 'location = /bigjay_controller/' "$NGINX_AVAIL" || true

if grep -q 'rewrite.*/admin/index.php' "$NGINX_AVAIL"; then
  echo "ERROR: old nginx config still has /admin/ rewrite. Aborting."
  exit 1
fi

nginx -t
systemctl restart nginx

echo ""
echo "Done. Open: https://panel.ticketin.ir/bigjay_controller/"
echo "Files:"
ls -la "$HTML/bigjay_controller/"

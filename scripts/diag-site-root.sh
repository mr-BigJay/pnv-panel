#!/usr/bin/env bash
set -euo pipefail

# Find nginx document root for panel.ticketin.ir and check admin paths.
# Run on the server: bash scripts/diag-site-root.sh

DOMAIN="${DOMAIN:-panel.ticketin.ir}"
GUESS_ROOTS=(
  /var/www/html
  /var/www/pnv-panel
  /var/www/pnv-panel/public
  /var/www/ticketin
  /var/www/html/panel
)

echo "=== Nginx config for $DOMAIN ==="
if [[ -d /etc/nginx ]]; then
  grep -r "server_name\|root \|alias " /etc/nginx/sites-enabled/ 2>/dev/null \
    | grep -F "$DOMAIN" -B0 -A0 2>/dev/null || true
  grep -r "server_name.*$DOMAIN" /etc/nginx/sites-enabled/ 2>/dev/null \
    | while read -r line; do
      file="${line%%:*}"
      echo "--- file: $file ---"
      sed -n '/server_name/,/^[[:space:]]*}/p' "$file" 2>/dev/null | head -40
    done
else
  echo "(no /etc/nginx — run on web server)"
fi

echo ""
echo "=== Check guessed roots ==="
for r in "${GUESS_ROOTS[@]}"; do
  [[ -d "$r" ]] || continue
  idx="$r/index.php"
  bj="$r/bigjay_controller/index.php"
  adm="$r/admin/index.php"
  mark=""
  [[ -f "$idx" && -f "$bj" ]] && mark="  <-- looks like full panel"
  echo "$r"
  echo "  index.php: $([[ -f $idx ]] && echo OK || echo MISSING)"
  echo "  bigjay_controller/index.php: $([[ -f $bj ]] && echo OK || echo MISSING)"
  echo "  admin/index.php: $([[ -f $adm ]] && echo OK || echo MISSING)$mark"
done

echo ""
echo "=== Recent deploy backups next to /var/www/html ==="
ls -dt /var/www/html.deploy.* /var/www/html.rollback.* 2>/dev/null | head -5 || echo "(none found)"

echo ""
echo "If nginx root != where you ran deploy, set ROOT= that path and deploy again."
echo "If bigjay_controller MISSING at nginx root, restore backup or re-run deploy-code-from-ref.sh"

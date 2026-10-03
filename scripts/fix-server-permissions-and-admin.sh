#!/usr/bin/env bash
# Fix admin black/404 from logs:
#   - stat() Permission denied on /var/www/html/...
#   - stray bigjay_controller/dashboard.php (not in repo) breaking entry
#
#   cd /opt/pnv-panel && git pull && sudo ROOT=/var/www/html bash scripts/fix-server-permissions-and-admin.sh
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
WEB_USER="${WEB_USER:-www-data}"

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo"; exit 1; }

echo ">> Remove legacy bigjay_controller/dashboard.php (use index.php only)"
legacy="$ROOT/bigjay_controller/dashboard.php"
if [[ -f "$legacy" ]]; then
  dest="${legacy}.removed.$(date +%Y%m%d-%H%M%S)"
  mv "$legacy" "$dest"
  echo "   moved aside: $dest"
fi

if [[ ! -f "$ROOT/bigjay_controller/index.php" ]]; then
  echo ">> Restore bigjay_controller/index.php from git checkout"
  mkdir -p "$ROOT/bigjay_controller"
  cp "$SRC/bigjay_controller/index.php" "$ROOT/bigjay_controller/index.php"
fi

echo ">> Filesystem permissions (nginx/php-fpm user: $WEB_USER)"
for d in /var /var/www "$ROOT" "$ROOT/bigjay_controller" "$ROOT/admin"; do
  [[ -d "$d" ]] || continue
  chmod o+rx "$d" 2>/dev/null || true
done

chown -R "$WEB_USER:$WEB_USER" "$ROOT/db" "$ROOT/uploads" "$ROOT/invoices" "$ROOT/temp" 2>/dev/null || true
chown -R root:root "$ROOT" 2>/dev/null || true
find "$ROOT" -type d -exec chmod 755 {} \;
find "$ROOT" -type f -exec chmod 644 {} \;
chmod 775 "$ROOT/db" "$ROOT/uploads" "$ROOT/temp" 2>/dev/null || true
chmod -R 775 "$ROOT/uploads" 2>/dev/null || true

echo ">> www-data read test"
if ! sudo -u "$WEB_USER" test -r "$ROOT/bigjay_controller/index.php"; then
  echo "ERROR: $WEB_USER still cannot read bigjay_controller/index.php" >&2
  ls -la "$ROOT/bigjay_controller/" >&2
  exit 1
fi
echo "   OK"

if [[ -f "$SRC/scripts/hotfix-admin-support-black.sh" ]]; then
  echo ">> Support PHP hotfix (supportGetUserProfileSummary)"
  ROOT="$ROOT" SRC="$SRC" bash "$SRC/scripts/hotfix-admin-support-black.sh"
fi

echo ""
echo "Done. Test: https://panel.ticketin.ir/bigjay_controller/"
echo "Must hit index.php -> admin/index.php (not dashboard.php)"

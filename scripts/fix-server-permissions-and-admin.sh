#!/usr/bin/env bash
# Permissions + remove stray bigjay dashboard.php + support PHP hotfix
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
WEB_USER="${WEB_USER:-www-data}"

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo"; exit 1; }

legacy="$ROOT/bigjay_controller/dashboard.php"
if [[ -f "$legacy" ]]; then
  dest="${legacy}.removed.$(date +%Y%m%d-%H%M%S)"
  mv "$legacy" "$dest"
  echo ">> Moved legacy dashboard: $dest"
fi

if [[ ! -f "$ROOT/bigjay_controller/index.php" ]]; then
  mkdir -p "$ROOT/bigjay_controller"
  cp "$SRC/bigjay_controller/index.php" "$ROOT/bigjay_controller/index.php"
fi

echo ">> Paths readable by nginx ($WEB_USER)"
for d in /var /var/www "$ROOT" "$ROOT/bigjay_controller" "$ROOT/admin"; do
  [[ -d "$d" ]] && chmod o+rx "$d" 2>/dev/null || true
done
find "$ROOT" -type d -exec chmod 755 {} \;
find "$ROOT" -type f -exec chmod 644 {} \;

echo ">> Writable data (support messages, uploads, payments)"
mkdir -p "$ROOT/db" "$ROOT/uploads/support" "$ROOT/invoices" "$ROOT/temp"
touch "$ROOT/db/support.json" 2>/dev/null || true
chown -R "$WEB_USER:$WEB_USER" "$ROOT/db" "$ROOT/uploads" "$ROOT/invoices" "$ROOT/temp"
chmod 775 "$ROOT/db" "$ROOT/uploads" "$ROOT/temp"
chmod 664 "$ROOT/db/support.json" 2>/dev/null || true
chmod -R 775 "$ROOT/uploads" 2>/dev/null || true

if ! sudo -u "$WEB_USER" test -r "$ROOT/bigjay_controller/index.php"; then
  echo "ERROR: $WEB_USER cannot read bigjay_controller/index.php" >&2
  exit 1
fi
if ! sudo -u "$WEB_USER" test -w "$ROOT/db/support.json"; then
  echo "ERROR: $WEB_USER cannot write db/support.json (messages will break)" >&2
  exit 1
fi

if [[ -f "$SRC/scripts/hotfix-admin-support-black.sh" ]]; then
  ROOT="$ROOT" SRC="$SRC" bash "$SRC/scripts/hotfix-admin-support-black.sh"
fi

if command -v php >/dev/null 2>&1 && [[ -f "$ROOT/db/support.json" ]]; then
  php -r '
$f=getenv("ROOT")."/db/support.json";
$d=json_decode(file_get_contents($f),true);
$n=is_array($d)?count($d):0;
echo ">> support.json tickets: $n\n";
'
fi

echo "Done: /bigjay_controller/?page=support"

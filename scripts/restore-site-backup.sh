#!/usr/bin/env bash
set -euo pipefail

# Restore /var/www/html from a deploy/rollback backup directory.
#   ls -dt /var/www/html.deploy.* | head
#   BACKUP=/var/www/html.deploy.abc123.20261003-120000 ROOT=/var/www/html bash scripts/restore-site-backup.sh

ROOT="${ROOT:-/var/www/html}"
BACKUP="${BACKUP:?Set BACKUP to backup dir (e.g. /var/www/html.rollback.xxx)}"

if [[ ! -d "$BACKUP" ]]; then
  echo "ERROR: not a directory: $BACKUP" >&2
  exit 1
fi

echo "This will replace code under $ROOT from backup:"
echo "  $BACKUP"
echo "db/invoices/uploads in ROOT will be overwritten from backup too."
read -r -p "Type YES to continue: " ok
if [[ "$ok" != "YES" ]]; then
  echo "Aborted."
  exit 1
fi

TS="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo ">> Safety copy current -> ${ROOT}.before-restore.${TS}"
  cp -a "$ROOT" "${ROOT}.before-restore.${TS}"
fi

mkdir -p "$ROOT"
rsync -a "$BACKUP/" "$ROOT/"

echo "Done. Test https://panel.ticketin.ir/bigjay_controller/"

#!/bin/bash
# Deploy v02.1.0 backup + SMS — run ON THE SERVER as root
set -euo pipefail

BR="${BR:-cursor/v02-backup-sms-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"
TMP="${TMP:-/tmp/pnv-deploy-$$}"

echo "=== Deploy backup + SMS (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "backup_lib.php"
  "sms_lib.php"
  "admin/backup.php"
  "admin/sms.php"
  "admin/admin_nav.php"
  "admin/dashboard.php"
  "admin/index.php"
  "register.php"
  "scripts/install.sh"
  "bigjay_controller/backup.php"
  "bigjay_controller/sms.php"
  "db/sms.example.json"
)

mkdir -p "$TMP"
trap 'rm -rf "$TMP"' EXIT

failed=0
for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  tmp="${TMP}/$(basename "$rel")"

  if ! curl -fsSL "${BASE}/${rel}" -o "$tmp"; then
    echo "  FAIL download ${rel}" >&2
    failed=1
    continue
  fi

  if ! php -l "$tmp" >/dev/null 2>&1; then
    echo "  FAIL syntax ${rel}" >&2
    php -l "$tmp" 2>&1 | sed 's/^/    /' >&2
    failed=1
    rm -f "$tmp"
    continue
  fi

  if ! mv "$tmp" "$dest"; then
    echo "  FAIL write ${dest} (disk full or permission?)" >&2
    failed=1
    continue
  fi

  echo "  OK ${rel}"
done

mkdir -p "${ROOT}/db/backups" "${ROOT}/temp"
chmod 775 "${ROOT}/db/backups" "${ROOT}/temp" 2>/dev/null || true

if [[ ! -f "${ROOT}/db/sms.json" && -f "${ROOT}/db/sms.example.json" ]]; then
  cp "${ROOT}/db/sms.example.json" "${ROOT}/db/sms.json"
  echo "  INIT db/sms.json"
fi

if ! php -m 2>/dev/null | grep -qi zip; then
  echo ""
  echo "WARNING: php-zip not installed — backup ZIP export needs it:"
  echo "  apt install php-zip && systemctl reload apache2"
fi

if id www-data >/dev/null 2>&1; then
  chown -R www-data:www-data "${ROOT}/db/backups" "${ROOT}/temp" 2>/dev/null || true
  chown www-data:www-data "${ROOT}/db/sms.json" 2>/dev/null || true
fi

echo ""
if [[ "$failed" -ne 0 ]]; then
  echo "Deploy finished with errors." >&2
  exit 1
fi

echo "Done. Test:"
echo "  /bigjay_controller/backup.php"
echo "  /bigjay_controller/sms.php"

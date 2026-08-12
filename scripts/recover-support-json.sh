#!/bin/bash
# بازیابی db/support.json — ادغام snapshot ریپو با دادهٔ فعلی سرور
set -euo pipefail

BR="${BR:-cursor/fix-admin-support-send-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"
FILE="${ROOT}/db/support.json"
SNAPSHOT="${ROOT}/db/support.repo.json"
STAMP="$(date +%Y%m%d-%H%M%S)"

echo "=============================================="
echo " بازیابی پیام‌های پشتیبانی"
echo "=============================================="
echo "مسیر: ${ROOT}"
echo ""

if [[ ! -d "${ROOT}" ]]; then
  echo "خطا: پوشه ${ROOT} وجود ندارد."
  echo "ROOT=/path/to/site bash $0"
  exit 1
fi

mkdir -p "${ROOT}/db"

if [[ -f "${FILE}" ]]; then
  BACKUP="${FILE}.before-recover-${STAMP}"
  cp "${FILE}" "${BACKUP}"
  echo "✓ بکاپ فعلی: ${BACKUP}"
  BEFORE=$(php -r 'echo count(json_decode(file_get_contents($argv[1]), true) ?: []);' "${FILE}")
else
  BEFORE=0
  echo "⚠ فایل support.json وجود نداشت — از نو ساخته می‌شود."
fi

echo ""
echo "→ دانلود کد و snapshot از GitHub..."
for rel in support_lib.php admin/support-diagnose.php; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
done

curl -fsSL "${BASE}/db/support.json" -o "${SNAPSHOT}"
SNAP_COUNT=$(php -r 'echo count(json_decode(file_get_contents($argv[1]), true) ?: []);' "${SNAPSHOT}")
echo "✓ snapshot ریپو: ${SNAP_COUNT} تیکت"

echo ""
echo "→ ادغام (JayForce و تیکت‌های جدید سرور حفظ می‌شوند)..."
php -r '
require "'"${ROOT}"'/support_lib.php";
$file = "'"${FILE}"'";
$snapshot = "'"${SNAPSHOT}"'";
$merged = supportImportSnapshot($file, $snapshot);
echo count($merged);
' | {
  read -r AFTER
  echo ""
  echo "=============================================="
  echo " قبل:  ${BEFORE} تیکت"
  echo " بعد:  ${AFTER} تیکت"
  echo "=============================================="
  if [[ "${AFTER}" -le 2 && "${SNAP_COUNT}" -gt 10 ]]; then
    echo ""
    echo "⚠ ادغام انجام شد ولی هنوز تعداد کم است."
    echo "  احتمالاً support_lib.php قدیمی است. این را بزنید:"
    echo "  curl -fsSL ${BASE}/scripts/deploy-support-send.sh | bash"
    exit 1
  fi
  if [[ "${AFTER}" -le "${BEFORE}" ]]; then
    echo ""
    echo "تیکت جدیدی اضافه نشد."
    if [[ -f "${FILE}.bak" ]]; then
      BAK_COUNT=$(php -r 'echo count(json_decode(@file_get_contents($argv[1]), true) ?: []);' "${FILE}.bak")
      echo "support.json.bak روی سرور: ${BAK_COUNT} تیکت"
      if [[ "${BAK_COUNT}" -gt "${BEFORE}" ]]; then
        echo "→ بازیابی از .bak ..."
        cp "${FILE}.bak" "${FILE}"
        echo "✓ از .bak بازیابی شد. صفحه را رفرش کنید."
      fi
    fi
    echo ""
    echo "اگر هنوز ۲ تیکت دارید، db/support.json را از بکاپ cPanel/hosting برگردانید."
  else
    echo ""
    echo "✓ بازیابی موفق. Ctrl+F5 بزنید و عدد کنار «پیام‌های کاربران» را چک کنید."
  fi
}

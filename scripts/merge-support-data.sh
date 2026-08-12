#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-support-send-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"
FILE="${ROOT}/db/support.json"
SNAPSHOT="${ROOT}/db/support.repo.json"

echo "=== Merge support data (branch: ${BR}) ==="
echo "Target: ${ROOT}"

mkdir -p "${ROOT}/db"

echo "-> Download repo snapshot to ${SNAPSHOT}"
curl -fsSL "${BASE}/db/support.json" -o "${SNAPSHOT}"

php -r '
require "'"${ROOT}"'/support_lib.php";
$file = "'"${FILE}"'";
$snapshot = "'"${SNAPSHOT}"'";
$before = count(supportReadJsonFile($file) ?: []);
$merged = supportImportSnapshot($file, $snapshot);
$after = count($merged);
echo "Tickets before: {$before}\n";
echo "Tickets after:  {$after}\n";
if($after <= $before){
    echo "No new tickets merged (production may already be up to date).\n";
}
else{
    echo "Merged " . ($after - $before) . " ticket(s) from repo snapshot.\n";
}
'

echo ""
echo "Done. Hard refresh admin support page and check the number beside «پیام‌های کاربران»."

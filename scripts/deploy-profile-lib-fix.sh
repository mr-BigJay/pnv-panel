#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-500-error-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy profile_lib fix (HTTP 500: profileGetAdminAvatar) ==="
echo "Target: ${ROOT}"

mkdir -p "${ROOT}/uploads/avatars" "${ROOT}/admin" "${ROOT}/bigjay_controller"

curl -fsSL "${BASE}/profile_lib.php" -o "${ROOT}/profile_lib.php"
curl -fsSL "${BASE}/admin/profile-api.php" -o "${ROOT}/admin/profile-api.php"

dest="${ROOT}/bigjay_controller/profile-api.php"
if ! curl -fsSL "${BASE}/bigjay_controller/profile-api.php" -o "${dest}" 2>/dev/null; then
  cat > "$dest" <<'PHP'
<?php
require dirname(__DIR__) . '/admin/' . basename(__FILE__);
PHP
fi

chmod 777 "${ROOT}/uploads/avatars" 2>/dev/null || true

php -l "${ROOT}/profile_lib.php"
php -l "${ROOT}/admin/profile-api.php"

echo ""
echo "Done. Reload: https://panel.ticketin.ir/bigjay_controller/"

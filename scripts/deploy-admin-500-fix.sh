#!/bin/bash
set -euo pipefail

BR="${BR:-cursor/fix-admin-500-error-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy full admin panel fix (branch: ${BR}) ==="
echo "Target: ${ROOT}"
echo ""

mkdir -p "${ROOT}/admin" "${ROOT}/bigjay_controller" "${ROOT}/uploads/avatars"

# Core admin PHP
admin_files=(
  "admin/index.php"
  "admin/admin_nav.php"
  "admin/auth.php"
  "admin/functions.php"
  "admin/payments.php"
  "admin/renews.php"
  "admin/downloads.php"
  "admin/health-check.php"
  "admin/profile-api.php"
)

# bigjay_controller wrappers (nginx blocks /admin/ direct access)
wrapper_files=(
  "bigjay_controller/index.php"
  "bigjay_controller/auth.php"
  "bigjay_controller/functions.php"
  "bigjay_controller/payments.php"
  "bigjay_controller/renews.php"
  "bigjay_controller/downloads.php"
  "bigjay_controller/health-check.php"
  "bigjay_controller/profile-api.php"
  "bigjay_controller/sw-cleanup.js"
)

# Date bootstrap (prevents 500 if auth.php date helpers missing)
root_files=(
  "pnv_date_bootstrap.php"
  "date_lib.php"
  "profile_lib.php"
)

fetch_file(){
  local rel="$1"
  local dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  if ! curl -fsSL "${BASE}/${rel}" -o "${dest}" 2>/dev/null; then
    echo "WARNING: could not fetch ${rel} — keeping existing file if present"
    if [[ ! -f "$dest" ]]; then
      echo "ERROR: ${rel} missing and no local copy"
      exit 1
    fi
  fi
}

for rel in "${root_files[@]}" "${admin_files[@]}"; do
  fetch_file "$rel"
done

for rel in "${wrapper_files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "-> ${dest}"
  if ! curl -fsSL "${BASE}/${rel}" -o "${dest}" 2>/dev/null; then
    case "$rel" in
      bigjay_controller/index.php|bigjay_controller/auth.php|bigjay_controller/functions.php|bigjay_controller/payments.php|bigjay_controller/renews.php|bigjay_controller/downloads.php|bigjay_controller/health-check.php|bigjay_controller/profile-api.php)
        echo "   (inline wrapper fallback)"
        cat > "$dest" <<PHP
<?php

require dirname(__DIR__) . '/admin/' . basename(__FILE__);
PHP
        ;;
      bigjay_controller/sw-cleanup.js)
        echo "   (inline JS fallback)"
        cat > "$dest" <<'JS'
(function(){
  if(!('serviceWorker' in navigator)){return;}
  navigator.serviceWorker.getRegistrations().then(function(r){r.forEach(function(x){x.unregister();});});
  if(window.caches&&caches.keys){caches.keys().then(function(k){k.forEach(function(x){caches.delete(x);});});}
})();
JS
        ;;
      *)
        echo "ERROR: missing ${rel}"
        exit 1
        ;;
    esac
  fi
done

echo ""
echo "=== Syntax checks ==="
php -l "${ROOT}/admin/index.php"
php -l "${ROOT}/admin/downloads.php"
php -l "${ROOT}/bigjay_controller/index.php"

echo ""
echo "=== Verify auth stubs are not empty ==="
for f in "${ROOT}/bigjay_controller/auth.php" "${ROOT}/bigjay_controller/functions.php"; do
  size=$(wc -c < "$f" | tr -d ' ')
  if [[ "$size" -lt 10 ]]; then
    echo "ERROR: $f is empty (${size} bytes) — this causes HTTP 500"
    exit 1
  fi
  echo "OK ${f} (${size} bytes)"
done

echo ""
echo "=== Done ==="
echo "Open: https://panel.ticketin.ir/bigjay_controller/"
echo "If still 500, run: php ${ROOT}/admin/health-check.php"

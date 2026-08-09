#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-/var/www/html}"
COMMIT="${DEPLOY_COMMIT:?Set DEPLOY_COMMIT e.g. export DEPLOY_COMMIT=HEAD_SHA}"

BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${COMMIT}"

echo "Emergency admin deploy to $ROOT (commit $COMMIT)"

mkdir -p "$ROOT/admin" "$ROOT/bigjay_controller"

curl -fsSL "$BASE/pnv_date_bootstrap.php" -o "$ROOT/pnv_date_bootstrap.php"
curl -fsSL "$BASE/date_lib.php" -o "$ROOT/date_lib.php"

curl -fsSL "$BASE/admin/auth.php" -o "$ROOT/admin/auth.php"
curl -fsSL "$BASE/admin/functions.php" -o "$ROOT/admin/functions.php"
curl -fsSL "$BASE/admin/admin_nav.php" -o "$ROOT/admin/admin_nav.php"
curl -fsSL "$BASE/admin/index.php" -o "$ROOT/admin/index.php"
curl -fsSL "$BASE/admin/dashboard.php" -o "$ROOT/admin/dashboard.php"
curl -fsSL "$BASE/admin/payments.php" -o "$ROOT/admin/payments.php"
curl -fsSL "$BASE/admin/renews.php" -o "$ROOT/admin/renews.php"
curl -fsSL "$BASE/admin/users.php" -o "$ROOT/admin/users.php"
curl -fsSL "$BASE/support_lib.php" -o "$ROOT/support_lib.php"

curl -fsSL "$BASE/bigjay_controller/index.php" -o "$ROOT/bigjay_controller/index.php"
curl -fsSL "$BASE/bigjay_controller/auth.php" -o "$ROOT/bigjay_controller/auth.php"
curl -fsSL "$BASE/bigjay_controller/functions.php" -o "$ROOT/bigjay_controller/functions.php"
curl -fsSL "$BASE/bigjay_controller/payments.php" -o "$ROOT/bigjay_controller/payments.php"
curl -fsSL "$BASE/bigjay_controller/renews.php" -o "$ROOT/bigjay_controller/renews.php"

echo "Done. Hard-refresh /bigjay_controller/ and log in again."

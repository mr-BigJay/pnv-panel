#!/usr/bin/env bash
set -euo pipefail

# Admin support chat + send fix (PR #3)
#
# Private repo: use local git clone (recommended on server):
#   cd /opt/pnv-panel && git pull origin cursor/fix-support-admin-send-cbea
#   ROOT=/var/www/html bash scripts/deploy-admin-support-fix.sh
#
# Public raw GitHub (only if repo is public):
#   DEPLOY_COMMIT=40b9e26 ROOT=/var/www/html USE_CURL=1 bash scripts/deploy-admin-support-fix.sh

ROOT="${ROOT:-/var/www/html}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="${SRC:-$(cd "$SCRIPT_DIR/.." && pwd)}"
USE_CURL="${USE_CURL:-0}"

FILES=(
  support_lib.php
  support_ui.css
  support_ui.js
  admin/index.php
  admin/support.php
  admin/support-api.php
  admin/support-check.php
  bigjay_controller/support-check.php
)

deploy_from_src() {
  local rev=""
  if command -v git >/dev/null 2>&1 && git -C "$SRC" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    rev="$(git -C "$SRC" rev-parse --short HEAD 2>/dev/null || true)"
  fi
  echo "Deploy admin support fix -> $ROOT (copy from $SRC${rev:+ @ $rev})"

  mkdir -p "$ROOT/admin" "$ROOT/bigjay_controller" "$ROOT/db" "$ROOT/uploads/support"

  for f in "${FILES[@]}"; do
    if [[ ! -f "$SRC/$f" ]]; then
      echo "ERROR: missing $SRC/$f" >&2
      exit 1
    fi
    echo "  $f"
    cp "$SRC/$f" "$ROOT/$f"
  done
}

deploy_from_curl() {
  local commit="${DEPLOY_COMMIT:?Set DEPLOY_COMMIT for USE_CURL=1}"
  local base="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${commit}"
  echo "Deploy admin support fix -> $ROOT (curl commit $commit)"

  mkdir -p "$ROOT/admin" "$ROOT/bigjay_controller" "$ROOT/db" "$ROOT/uploads/support"

  for f in "${FILES[@]}"; do
    echo "  $f"
    curl -fsSL "$base/$f" -o "$ROOT/$f"
  done
}

if [[ "$USE_CURL" == "1" ]]; then
  deploy_from_curl
else
  deploy_from_src
fi

if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/support_lib.php" >/dev/null
  php -l "$ROOT/admin/support.php" >/dev/null
  php -l "$ROOT/admin/index.php" >/dev/null
  echo "PHP syntax OK"
fi

echo ""
echo "Permissions (adjust user if not www-data):"
echo "  chown www-data:www-data $ROOT/db/support.json 2>/dev/null || true"
echo "  chmod 664 $ROOT/db/support.json 2>/dev/null || true"
echo "  chown -R www-data:www-data $ROOT/uploads/support"
echo ""
echo "Verify (logged in as admin):"
echo "  /bigjay_controller/support-check.php"
echo "Chat:"
echo "  /bigjay_controller/?page=support&user=USERNAME"

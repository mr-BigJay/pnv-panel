#!/usr/bin/env bash
# Safe deploy: verify before -> restore snapshot -> verify after
#   cd /opt/pnv-panel && git pull && sudo bash scripts/deploy-admin-ui-with-verify.sh
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
LOG_DIR="${LOG_DIR:-/opt/pnv-panel/deploy-logs}"
mkdir -p "$LOG_DIR"

export ROOT SRC REF="${REF:-origin/cursor/restore-admin-ui-cbea}"

echo "=== BEFORE ==="
PHASE=before REPORT="$LOG_DIR/verify-before-$STAMP.txt" bash "$DIR/verify-admin-deploy.sh"

echo ""
echo "=== RESTORE ==="
bash "$DIR/restore-admin-ui-snapshot.sh"

echo ""
echo "=== AFTER ==="
PHASE=after REPORT="$LOG_DIR/verify-after-$STAMP.txt" bash "$DIR/verify-admin-deploy.sh"

echo ""
echo "Logs saved:"
echo "  $LOG_DIR/verify-before-$STAMP.txt"
echo "  $LOG_DIR/verify-after-$STAMP.txt"

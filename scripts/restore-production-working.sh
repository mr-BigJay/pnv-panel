#!/usr/bin/env bash
# Restore panel.ticketin.ir to last backup before agent nginx/code changes.
# Run on server3 as root:
#   cd /opt/pnv-panel && git pull && sudo bash scripts/restore-production-working.sh
set -euo pipefail

PRIMARY="${PRIMARY:-/etc/nginx/sites-enabled/panel.ticketin.ir}"
ROOT="${ROOT:-/var/www/html}"
RESTORE_CODE="${RESTORE_CODE:-1}"

[[ "$(id -u)" -eq 0 ]] || { echo "Use: sudo bash $0"; exit 1; }

echo "=== 1) Nginx: restore $PRIMARY from backup (before PNV patches) ==="
shopt -s nullglob
baks=( "${PRIMARY}.bak."* )
if (( ${#baks[@]} == 0 )); then
  echo "No ${PRIMARY}.bak.* — skip nginx restore (edit by hand if needed)"
else
  chosen=""
  for bak in $(ls -t "${baks[@]}" 2>/dev/null); do
    if ! grep -q "PNV_BIGJAY_PATCHED" "$bak" 2>/dev/null; then
      chosen="$bak"
      break
    fi
  done
  if [[ -z "$chosen" ]]; then
    chosen="$(ls -t "${baks[@]}" | tail -1)"
    echo "All backups contain PNV patch; using oldest: $chosen"
  else
    echo "Using backup without PNV patch: $chosen"
  fi
  cp -a "$PRIMARY" "${PRIMARY}.before-restore.$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true
  cp -a "$chosen" "$PRIMARY"
fi

rm -f /etc/nginx/sites-enabled/pnv-panel-panel-ticketin-ir.conf \
      /etc/nginx/sites-available/pnv-panel-panel-ticketin-ir.conf 2>/dev/null || true

if nginx -t; then
  systemctl reload nginx
  echo "nginx OK"
else
  echo "ERROR: nginx -t failed after restore — fix $PRIMARY manually" >&2
  exit 1
fi

if [[ "$RESTORE_CODE" == "1" ]]; then
  echo ""
  echo "=== 2) Site code: restore $ROOT from rollback/deploy backup ==="
  code_bak=""
  for pattern in "${ROOT}.rollback."* "${ROOT}.deploy."* "${ROOT}.before-restore."*; do
    [[ -d "$pattern" ]] || continue
    code_bak="$pattern"
    break
  done
  if [[ -z "$code_bak" ]]; then
    newest="$(ls -dt "${ROOT}".rollback.* "${ROOT}".deploy.* 2>/dev/null | head -1 || true)"
    [[ -n "$newest" && -d "$newest" ]] && code_bak="$newest"
  fi

  if [[ -n "$code_bak" && -d "$code_bak" ]]; then
    echo "Restoring from: $code_bak"
    rsync -a --delete \
      --exclude 'db/' \
      --exclude 'invoices/' \
      --exclude 'uploads/' \
      --exclude 'temp/' \
      "$code_bak/" "$ROOT/"
    echo "Code restored (db/invoices/uploads kept from current $ROOT)"
  else
    echo "No code backup dir found — restoring PHP from git origin/main into $ROOT"
    SRC="${SRC:-/opt/pnv-panel}"
    if git -C "$SRC" rev-parse origin/main >/dev/null 2>&1; then
      TMP="$(mktemp -d)"
      git -C "$SRC" archive origin/main | tar -x -C "$TMP"
      rsync -a --delete \
        --exclude 'db/' --exclude 'invoices/' --exclude 'uploads/' --exclude 'temp/' \
        "$TMP/" "$ROOT/"
      rm -rf "$TMP"
      echo "Code synced to origin/main (data dirs unchanged)"
    else
      echo "Skip code: no backup and no git at $SRC"
    fi
  fi
fi

echo ""
echo "=== Done ==="
echo "Admin (try with trailing slash if needed): https://panel.ticketin.ir/bigjay_controller/"
echo "To skip code restore: RESTORE_CODE=0 sudo bash $0"

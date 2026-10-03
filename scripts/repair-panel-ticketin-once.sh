#!/usr/bin/env bash
# One-shot repair for panel.ticketin.ir admin 404 (nginx only).
# Run on server3 as root:
#   cd /opt/pnv-panel && git pull && sudo bash scripts/repair-panel-ticketin-once.sh
set -euo pipefail

PRIMARY="${PRIMARY:-/etc/nginx/sites-enabled/panel.ticketin.ir}"
DOMAIN="${DOMAIN:-panel.ticketin.ir}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

[[ "$(id -u)" -eq 0 ]] || { echo "Use sudo"; exit 1; }

if ! nginx -t >/dev/null 2>&1; then
  latest="$(ls -t "${PRIMARY}.bak."* 2>/dev/null | head -1 || true)"
  if [[ -n "$latest" ]]; then
    echo ">> nginx broken; restore $latest"
    cp -a "$latest" "$PRIMARY"
  else
    echo "ERROR: nginx -t fails and no ${PRIMARY}.bak.* — fix config by hand" >&2
    nginx -t || true
    exit 1
  fi
fi

exec "$SCRIPT_DIR/fix-nginx-panel-admin.sh"

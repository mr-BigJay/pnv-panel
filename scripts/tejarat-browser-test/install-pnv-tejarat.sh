#!/usr/bin/env bash
#
# نصب واحد server3: Worker در /opt/tejarat-browser-test + (اختیاری) افزودن Tejarat به پنل.
#
# فقط Worker (پنل دست‌نخورده):
#   curl -fsSL "…/install-pnv-tejarat.sh" | sudo bash
#
# Worker + اضافه به پنل:
#   curl -fsSL "…/install-pnv-tejarat.sh" | sudo PNV_PANEL_ROOT=/var/www/html \
#     PNV_PUBLIC_URL=https://YOUR-DOMAIN bash
#
set -euo pipefail

SCRIPT_SELF="${PNV_TEJARAT_INSTALL_SCRIPT:-}"
if [[ -z "${SCRIPT_SELF}" ]]; then
  SCRIPT_SELF="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/install-pnv-tejarat.sh"
fi

REPO_URL="${PNV_GIT_REPO:-https://github.com/mr-BigJay/pnv-panel.git}"
BRANCH="${PNV_TEJARAT_GIT_BRANCH:-cursor/tejarat-browser-worker-cbea}"
INSTALL_DIR="${PNV_TEJARAT_INSTALL_DIR:-/root/pnv-panel-tejarat}"
CONFIRM="${PNV_TEJARAT_CONFIRM_SERVICES:-0}"
PANEL="${PNV_PANEL_ROOT:-}"
PUBLIC_URL="${PNV_PUBLIC_URL:-}"

log() { echo "==> $*"; }

ensure_repo() {
  command -v git >/dev/null 2>&1 || { echo "git نصب نیست" >&2; exit 1; }
  local setup="${INSTALL_DIR}/scripts/tejarat-browser-test/setup-isolated.sh"
  if [[ -f "${setup}" ]]; then
    git -C "${INSTALL_DIR}" fetch origin "${BRANCH}" 2>/dev/null || true
    git -C "${INSTALL_DIR}" checkout "${BRANCH}" 2>/dev/null || true
    git -C "${INSTALL_DIR}" pull --ff-only origin "${BRANCH}" 2>/dev/null || true
  else
    log "دریافت کد PR #34 → ${INSTALL_DIR}"
    rm -rf "${INSTALL_DIR}" 2>/dev/null || true
    git clone -b "${BRANCH}" --depth 1 "${REPO_URL}" "${INSTALL_DIR}"
  fi
}

main() {
  ensure_repo

  export PNV_TEJARAT_SOURCE="${INSTALL_DIR}"
  export PNV_TEJARAT_CONFIRM_SERVICES="${CONFIRM}"
  bash "${INSTALL_DIR}/scripts/tejarat-browser-test/setup-isolated.sh"

  if [[ -n "${PANEL}" ]]; then
    log "افزودن ماژول به پنل: ${PANEL}"
    export PNV_TEJARAT_SOURCE="${INSTALL_DIR}"
    export PNV_PANEL_ROOT="${PANEL}"
    export PNV_PUBLIC_URL="${PUBLIC_URL}"
    bash "${INSTALL_DIR}/scripts/tejarat-browser-test/deploy-to-panel.sh"

    if [[ -n "${PUBLIC_URL}" ]] && [[ -f /etc/pnv/tejarat-worker-test.env ]]; then
      INGEST="${PUBLIC_URL%/}/tejarat-worker-ingest.php"
      if grep -q '^PNV_TEJARAT_INGEST_URL=' /etc/pnv/tejarat-worker-test.env; then
        sed -i "s|^PNV_TEJARAT_INGEST_URL=.*|PNV_TEJARAT_INGEST_URL=${INGEST}|" /etc/pnv/tejarat-worker-test.env
      else
        echo "PNV_TEJARAT_INGEST_URL=${INGEST}" >>/etc/pnv/tejarat-worker-test.env
      fi
      log "Worker ingest → ${INGEST}"
      warn_restart="systemctl restart tejarat-browser-worker-test"
      log "در صورت فعال بودن worker: ${warn_restart}"
    fi
  else
    log "پنل deploy نشد (PNV_PANEL_ROOT خالی). فقط Worker/ایزوله آماده است."
  fi

  if [[ "${CONFIRM}" != "1" ]]; then
    echo ""
    log "برای systemd/env: sudo PNV_TEJARAT_CONFIRM_SERVICES=1 bash ${INSTALL_DIR}/scripts/tejarat-browser-test/install-pnv-tejarat.sh"
  fi
}

main "$@"

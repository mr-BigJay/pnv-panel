#!/usr/bin/env bash
#
# نصب یک‌مرحله‌ای Tejarat روی VPS (server3 و مشابه) — بدون دست زدن به /var/www/html.
# خودش repo PR #34 را می‌گیرد (clone یا pull) و setup-isolated را اجرا می‌کند.
#
# استفاده:
#   curl -fsSL "https://raw.githubusercontent.com/mr-BigJay/pnv-panel/cursor/tejarat-browser-worker-cbea/scripts/tejarat-browser-test/bootstrap-vps.sh" | sudo bash
#
# یا بعد از داشتن repo:
#   sudo bash scripts/tejarat-browser-test/bootstrap-vps.sh
#
set -euo pipefail

REPO_URL="${PNV_GIT_REPO:-https://github.com/mr-BigJay/pnv-panel.git}"
BRANCH="${PNV_TEJARAT_GIT_BRANCH:-cursor/tejarat-browser-worker-cbea}"
INSTALL_DIR="${PNV_TEJARAT_INSTALL_DIR:-/root/pnv-panel-tejarat}"
TARGET="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
USER_NAME="${PNV_TEJARAT_USER:-tejarat-browser}"
DISPLAY_NUM="${PNV_TEJARAT_DISPLAY:-:91}"
SESSION_DIR="${PNV_WORKER_STORAGE:-${TARGET}/session}"
CONFIRM="${PNV_TEJARAT_CONFIRM_SERVICES:-0}"

log() { echo "==> $*"; }
warn() { echo "!! $*" >&2; }
die() { warn "$*"; exit 1; }

if [[ "$(id -u)" -ne 0 ]]; then
  die "با root اجرا کنید: sudo bash bootstrap-vps.sh"
fi

if [[ "${INSTALL_DIR}" == "/var/www/html" ]] || [[ "${TARGET}" == "/var/www/html" ]]; then
  die "مسیر Production /var/www/html مجاز نیست."
fi

need_git() {
  command -v git >/dev/null 2>&1 || die "git نصب نیست: apt install -y git"
}

setup_script_path() {
  local dir="$1"
  echo "${dir}/scripts/tejarat-browser-test/setup-isolated.sh"
}

ensure_repo() {
  need_git
  local setup
  setup="$(setup_script_path "${INSTALL_DIR}")"

  if [[ -f "${setup}" ]]; then
    log "Repo موجود: ${INSTALL_DIR} — به‌روزرسانی شاخه ${BRANCH}"
    git -C "${INSTALL_DIR}" fetch origin "${BRANCH}" --depth 1 2>/dev/null || git -C "${INSTALL_DIR}" fetch origin "${BRANCH}"
    git -C "${INSTALL_DIR}" checkout "${BRANCH}"
    git -C "${INSTALL_DIR}" pull --ff-only origin "${BRANCH}" 2>/dev/null || true
    return 0
  fi

  # اگر ~/pnv-panel همان شاخه را دارد، از همان استفاده کن (بدون clone دوباره)
  local alt="${PNV_TEJARAT_ALT_REPO:-$HOME/pnv-panel}"
  if [[ -d "${alt}/.git" ]]; then
    setup="$(setup_script_path "${alt}")"
    if git -C "${alt}" rev-parse --verify "origin/${BRANCH}" >/dev/null 2>&1 \
      || git -C "${alt}" fetch origin "${BRANCH}" 2>/dev/null; then
      git -C "${alt}" checkout "${BRANCH}" 2>/dev/null || git -C "${alt}" checkout -B "${BRANCH}" "origin/${BRANCH}"
      git -C "${alt}" pull --ff-only origin "${BRANCH}" 2>/dev/null || true
      if [[ -f "$(setup_script_path "${alt}")" ]]; then
        INSTALL_DIR="${alt}"
        log "استفاده از ${INSTALL_DIR} (شاخه ${BRANCH})"
        return 0
      fi
    fi
  fi

  log "Clone جدید → ${INSTALL_DIR} (کنار Production، بدون /var/www/html)"
  install -d -m 0755 "$(dirname "${INSTALL_DIR}")"
  if [[ -d "${INSTALL_DIR}/.git" ]]; then
    git -C "${INSTALL_DIR}" fetch origin "${BRANCH}"
    git -C "${INSTALL_DIR}" checkout "${BRANCH}"
  else
    rm -rf "${INSTALL_DIR}" 2>/dev/null || true
    git clone -b "${BRANCH}" --depth 1 "${REPO_URL}" "${INSTALL_DIR}"
  fi
}

run_setup() {
  local setup
  setup="$(setup_script_path "${INSTALL_DIR}")"
  [[ -f "${setup}" ]] || die "setup-isolated پیدا نشد: ${setup} — شاخه ${BRANCH} را چک کنید."

  export PNV_TEJARAT_SOURCE="${INSTALL_DIR}"
  export PNV_TEJARAT_TEST_ROOT="${TARGET}"
  export PNV_TEJARAT_USER="${USER_NAME}"
  export PNV_TEJARAT_DISPLAY="${DISPLAY_NUM}"
  export PNV_WORKER_STORAGE="${SESSION_DIR}"
  export PNV_TEJARAT_CONFIRM_SERVICES="${CONFIRM}"

  log "اجرای setup-isolated (CONFIRM_SERVICES=${CONFIRM})"
  bash "${setup}"
}

main() {
  log "Tejarat VPS bootstrap · branch=${BRANCH} · install=${INSTALL_DIR} · target=${TARGET}"
  ensure_repo
  run_setup

  setup="$(setup_script_path "${INSTALL_DIR}")"
  if [[ "${CONFIRM}" != "1" ]]; then
    echo ""
    log "مرحله ۱ تمام شد. برای اعمال systemd/env در صورت تعارض:"
    echo "  sudo PNV_TEJARAT_CONFIRM_SERVICES=1 bash ${setup}"
    echo "  — یا دوباره:"
    echo "  sudo PNV_TEJARAT_CONFIRM_SERVICES=1 bash ${INSTALL_DIR}/scripts/tejarat-browser-test/bootstrap-vps.sh"
  else
    log "مرحله ۲ (CONFIRM) تمام شد."
    echo ""
    log "Dry-run: sudo -u ${USER_NAME} bash ${INSTALL_DIR}/scripts/tejarat-browser-test/run-dry-run-e2e.sh"
  fi
}

main "$@"

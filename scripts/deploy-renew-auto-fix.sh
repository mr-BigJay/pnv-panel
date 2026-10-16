#!/bin/bash
# Deploy VPN auto-renew fix (xui_lib + helpers) — run ON THE SERVER
# Private repo: do NOT use raw.githubusercontent.com; use git clone + this script.
#
# One-time:
#   git clone git@github.com:mr-BigJay/pnv-panel.git /opt/pnv-panel
#   cd /opt/pnv-panel && git fetch origin && git checkout cursor/fix-vpn-auto-renew-cbea
#
# Deploy / update:
#   cd /opt/pnv-panel && git pull origin cursor/fix-vpn-auto-renew-cbea
#   ROOT=/var/www/html bash scripts/deploy-renew-auto-fix.sh
#
# Or from any checkout:
#   SRC=/path/to/pnv-panel ROOT=/var/www/html bash scripts/deploy-renew-auto-fix.sh

set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
BR="${BR:-cursor/fix-vpn-auto-renew-cbea}"
REPO_URL="${REPO_URL:-https://github.com/mr-BigJay/pnv-panel.git}"
CLONE_DIR="${CLONE_DIR:-/opt/pnv-panel}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

say(){ echo "$*"; }
die(){ echo "ERROR: $*" >&2; exit 1; }

resolve_source_dir(){
  if [[ -n "${SRC:-}" ]]; then
    [[ -f "${SRC}/xui_lib.php" ]] || die "SRC=${SRC} has no xui_lib.php"
    echo "$(cd "${SRC}" && pwd)"
    return
  fi

  local candidate
  candidate="$(cd "${SCRIPT_DIR}/.." && pwd)"
  if [[ -f "${candidate}/xui_lib.php" && -d "${candidate}/.git" ]]; then
    echo "${candidate}"
    return
  fi

  if [[ -d "${CLONE_DIR}/.git" ]]; then
    echo "${CLONE_DIR}"
    return
  fi

  die "No source tree. Clone first:\n  git clone ${REPO_URL} ${CLONE_DIR}\n  cd ${CLONE_DIR} && git checkout ${BR}\nThen re-run this script."
}

sync_branch(){
  local dir="$1"
  say "=== Source: ${dir} (branch ${BR}) ==="
  git -C "${dir}" fetch origin "${BR}" 2>/dev/null || git -C "${dir}" fetch origin
  git -C "${dir}" checkout "${BR}" 2>/dev/null || die "Branch ${BR} not found. Run: git -C ${dir} fetch origin"
  git -C "${dir}" pull --ff-only origin "${BR}" 2>/dev/null || true
}

clone_if_needed(){
  if [[ -n "${SRC:-}" ]] || [[ -f "${SCRIPT_DIR}/../xui_lib.php" && -d "${SCRIPT_DIR}/../.git" ]]; then
    return
  fi

  if [[ -d "${CLONE_DIR}/.git" ]]; then
    return
  fi

  command -v git >/dev/null 2>&1 || die "git not installed (apt install git)"

  say "=== Cloning into ${CLONE_DIR} ==="
  mkdir -p "$(dirname "${CLONE_DIR}")"

  if [[ -n "${GITHUB_TOKEN:-}" ]]; then
    git clone --depth 1 -b "${BR}" "https://oauth2:${GITHUB_TOKEN}@github.com/mr-BigJay/pnv-panel.git" "${CLONE_DIR}"
    return
  fi

  git clone --depth 1 -b "${BR}" "${REPO_URL}" "${CLONE_DIR}" || die "git clone failed. Use SSH (git@github.com:...) or export GITHUB_TOKEN=..."
}

FILES=(
  "xui_lib.php"
  "subscription_lib.php"
  "plan_ui_lib.php"
  "instant_pay_lib.php"
  "bale_lib.php"
  "bale-webhook.php"
  "payment_provision_idempotency.php"
  "admin/payments.php"
  "admin/renews.php"
  "tests/xui_renew_resolve_test.php"
  "tests/instant_pay_amount_test.php"
  "tests/payment_provision_idempotency_test.php"
  "scripts/diag-renew-provision.php"
)

clone_if_needed
SRC_DIR="$(resolve_source_dir)"
sync_branch "${SRC_DIR}"

[[ -d "${ROOT}" ]] || die "ROOT ${ROOT} does not exist"

say "=== Deploy renew fix → ${ROOT} ==="

for rel in "${FILES[@]}"; do
  src="${SRC_DIR}/${rel}"
  dest="${ROOT}/${rel}"
  [[ -f "${src}" ]] || die "missing in source: ${rel}"
  mkdir -p "$(dirname "${dest}")"
  cp -a "${src}" "${dest}"
  say "  OK ${rel}"
done

if [[ -f "${ROOT}/bigjay_controller/xui_lib.php" ]]; then
  if ! cmp -s "${ROOT}/xui_lib.php" "${ROOT}/bigjay_controller/xui_lib.php" 2>/dev/null; then
    cp -a "${ROOT}/xui_lib.php" "${ROOT}/bigjay_controller/xui_lib.php"
    say "  OK bigjay_controller/xui_lib.php (synced from root)"
  fi
fi

PROVISION_STORE="${ROOT}/db/locks/payment_provision_idempotency.json"
mkdir -p "${ROOT}/db/locks"
touch "${PROVISION_STORE}" 2>/dev/null || true

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data \
    "${ROOT}/xui_lib.php" \
    "${ROOT}/subscription_lib.php" \
    "${ROOT}/instant_pay_lib.php" \
    "${ROOT}/bale_lib.php" \
    "${ROOT}/bale-webhook.php" \
    "${ROOT}/payment_provision_idempotency.php" \
    2>/dev/null || true
  chown -R www-data:www-data "${ROOT}/db/locks" 2>/dev/null || true
  chmod 775 "${ROOT}/db/locks" 2>/dev/null || true
  chmod 664 "${PROVISION_STORE}" 2>/dev/null || true
  say "  OK db/locks + payment_provision_idempotency.json (www-data)"
fi

say ""
say "Verify:"
grep -q 'xuiResolveRenewContext' "${ROOT}/xui_lib.php" && say "  marker xuiResolveRenewContext: OK" || die "patch marker missing in xui_lib.php"

if command -v php >/dev/null 2>&1; then
  php "${ROOT}/tests/xui_renew_resolve_test.php" && say "  php tests: OK"
else
  say "  php not in PATH — skip test (install php-cli to verify)"
fi

say ""
say "Done. Optional diag:"
say "  cd ${ROOT} && php scripts/diag-renew-provision.php --link \"https://HOST:2096/sub/SUB_ID\" \"10 گیگ - 250 هزار تومان\""

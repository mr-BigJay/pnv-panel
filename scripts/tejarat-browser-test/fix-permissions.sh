#!/usr/bin/env bash
# Root-only: tejarat-browser must run venv + playwright (fixes Permission denied).
set -euo pipefail

TARGET="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
USER_NAME="${PNV_TEJARAT_USER:-tejarat-browser}"
REQ="${TARGET}/services/tejarat-browser-worker/requirements.txt"
BROWSERS="${TARGET}/browsers"

[[ "$(id -u)" -eq 0 ]] || { echo "Run with sudo" >&2; exit 1; }
id "${USER_NAME}" &>/dev/null || { echo "User ${USER_NAME} missing" >&2; exit 1; }

echo "==> Fixing ownership under ${TARGET} for ${USER_NAME}"
for name in venv .venv browsers vnc session; do
  [[ -e "${TARGET}/${name}" ]] || continue
  chown -R "${USER_NAME}:${USER_NAME}" "${TARGET}/${name}"
done
chmod 700 "${TARGET}/session" 2>/dev/null || true

PY=""
for candidate in \
  "${TARGET}/venv/bin/python" \
  "${TARGET}/.venv/bin/python"; do
  if [[ -f "${candidate}" ]]; then
    PY="${candidate}"
    chmod u+rx "${candidate}" 2>/dev/null || true
    break
  fi
done

if [[ -z "${PY}" ]]; then
  echo "!! No venv python under ${TARGET}/venv — create venv first or PNV_TEJARAT_CREATE_VENV=1 on setup"
  exit 1
fi

install -d -o "${USER_NAME}" -g "${USER_NAME}" -m 0755 "${BROWSERS}"
PIP_CACHE="${TARGET}/session/pip-cache"
install -d -o "${USER_NAME}" -g "${USER_NAME}" -m 0700 "${PIP_CACHE}"
# Root-owned pip cache from old runs breaks tejarat-browser pip
if [[ -d "${TARGET}/.cache" ]]; then
  chown -R "${USER_NAME}:${USER_NAME}" "${TARGET}/.cache" 2>/dev/null || rm -rf "${TARGET}/.cache"
fi

pip_as_user() {
  sudo -u "${USER_NAME}" env \
    HOME="${TARGET}" \
    PIP_CACHE_DIR="${PIP_CACHE}" \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    "${PY}" -m pip install --no-cache-dir -q "$@"
}

echo "==> pip install -r requirements.txt as ${USER_NAME} (no cache dir)"
pip_as_user -r "${REQ}"

if ! sudo -u "${USER_NAME}" "${PY}" -c "import playwright, requests" 2>/dev/null; then
  echo "!! venv imports failed after pip" >&2
  exit 1
fi

if [[ -d "${BROWSERS}" ]]; then
  echo "==> chmod chromium bundle for ${USER_NAME}"
  find "${BROWSERS}" -type f \( -name chrome -o -name 'chrome_*' -o -name 'chromium*' \) -exec chmod a+rx {} \; 2>/dev/null || true
fi

if [[ "${PNV_TEJARAT_REINSTALL_CHROMIUM:-0}" == "1" ]]; then
  echo "==> Reinstall Chromium bundle (PNV_TEJARAT_REINSTALL_CHROMIUM=1)"
  rm -rf "${BROWSERS}"/chromium-* 2>/dev/null || true
fi

echo "==> Ensuring Chromium for Playwright (PLAYWRIGHT_BROWSERS_PATH=${BROWSERS})"
sudo -u "${USER_NAME}" env \
  HOME="${TARGET}" \
  PLAYWRIGHT_BROWSERS_PATH="${BROWSERS}" \
  "${PY}" -m playwright install chromium

if grep -q '^PLAYWRIGHT_BROWSERS_PATH=' /etc/pnv/tejarat-worker-test.env 2>/dev/null; then
  sed -i "s|^PLAYWRIGHT_BROWSERS_PATH=.*|PLAYWRIGHT_BROWSERS_PATH=${BROWSERS}|" /etc/pnv/tejarat-worker-test.env
else
  echo "PLAYWRIGHT_BROWSERS_PATH=${BROWSERS}" >>/etc/pnv/tejarat-worker-test.env
fi
if ! grep -q '^PNV_TEJARAT_TEST_ROOT=' /etc/pnv/tejarat-worker-test.env 2>/dev/null; then
  echo "PNV_TEJARAT_TEST_ROOT=${TARGET}" >>/etc/pnv/tejarat-worker-test.env
fi

echo "==> Verify"
sudo -u "${USER_NAME}" "${PY}" -c "import playwright, requests; print('playwright OK'); print('requests OK')"
sudo -u "${USER_NAME}" test -x "${PY}" && echo "python executable OK"

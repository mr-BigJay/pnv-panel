#!/usr/bin/env bash
# Root-only: tejarat-browser must run venv + playwright (fixes Permission denied).
set -euo pipefail

TARGET="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
USER_NAME="${PNV_TEJARAT_USER:-tejarat-browser}"
REQ="${TARGET}/services/tejarat-browser-worker/requirements.txt"

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

if ! sudo -u "${USER_NAME}" "${PY}" -c "import playwright" 2>/dev/null; then
  echo "==> Installing Python deps + playwright in venv"
  sudo -u "${USER_NAME}" env HOME="${TARGET}" "${PY}" -m pip install -q -r "${REQ}"
  if [[ -d "${TARGET}/browsers" ]]; then
    sudo -u "${USER_NAME}" env HOME="${TARGET}" PLAYWRIGHT_BROWSERS_PATH="${TARGET}/browsers" \
      "${PY}" -m playwright install chromium
  else
    sudo -u "${USER_NAME}" env HOME="${TARGET}" "${PY}" -m playwright install chromium
  fi
fi

echo "==> Verify"
sudo -u "${USER_NAME}" "${PY}" -c "import playwright; print('playwright OK')"
sudo -u "${USER_NAME}" test -x "${PY}" && echo "python executable OK"

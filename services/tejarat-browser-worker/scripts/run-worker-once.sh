#!/usr/bin/env bash
set -euo pipefail
ROOT="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
cd "${ROOT}/services/tejarat-browser-worker"
PY="${ROOT}/venv/bin/python"
REQ="${PWD}/requirements.txt"
[[ -x "${PY}" ]] || { echo "missing ${PY}" >&2; exit 1; }
set -a
# shellcheck disable=SC1091
source /etc/pnv/tejarat-worker-test.env
set +a
unset PYTHONHOME
export PYTHONPATH="${PWD}"
export TEJARAT_RUN_ONCE="${TEJARAT_RUN_ONCE:-1}"

if ! "${PY}" -c "import requests" 2>/dev/null; then
  echo "Installing venv deps (requests/playwright)…" >&2
  "${PY}" -m pip install -q -r "${REQ}"
fi

exec "${PY}" -m worker.main

#!/usr/bin/env bash
set -euo pipefail
ROOT="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
cd "${ROOT}/services/tejarat-browser-worker"
PY="${ROOT}/venv/bin/python"
REQ="${PWD}/requirements.txt"
[[ -x "${PY}" ]] || { echo "missing ${PY}" >&2; exit 1; }
if [[ -f /etc/pnv/tejarat-worker-test.env ]]; then
  set -a
  # shellcheck disable=SC1091
  source /etc/pnv/tejarat-worker-test.env
  set +a
fi
unset PYTHONHOME
export PYTHONPATH="${PWD}"
export PNV_WORKER_STORAGE="${PNV_WORKER_STORAGE:-${ROOT}/session}"
export TEJARAT_STATEMENT_URL="${TEJARAT_STATEMENT_URL:-https://hb.tejaratbank.ir/account/log}"
if ! "${PY}" -c "import requests" 2>/dev/null; then
  "${PY}" -m pip install -q -r "${REQ}"
fi
exec "${PY}" -m worker.statement_probe

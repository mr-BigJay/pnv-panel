#!/usr/bin/env bash
set -euo pipefail
ROOT="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
cd "${ROOT}/services/tejarat-browser-worker"
export PYTHONPATH="${PWD}"
export PNV_WORKER_STORAGE="${PNV_WORKER_STORAGE:-${ROOT}/session}"
export TEJARAT_STATEMENT_URL="${TEJARAT_STATEMENT_URL:-https://hb.tejaratbank.ir/account/log}"
PY="${ROOT}/venv/bin/python"
[[ -x "${PY}" ]] || PY=python3
exec "${PY}" -m worker.statement_probe

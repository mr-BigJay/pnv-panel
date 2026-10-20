#!/usr/bin/env bash
set -euo pipefail
ROOT="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
cd "${ROOT}/services/tejarat-browser-worker"
export PYTHONPATH="${PWD}"
PY="${ROOT}/venv/bin/python"
[[ -x "${PY}" ]] || { echo "missing ${PY}" >&2; exit 1; }
set -a
# shellcheck disable=SC1091
source /etc/pnv/tejarat-worker-test.env
set +a
export TEJARAT_RUN_ONCE="${TEJARAT_RUN_ONCE:-1}"
exec "${PY}" -m worker.main

#!/usr/bin/env bash
# Systemd entry: pick existing venv under /opt/tejarat-browser-test (non-destructive layout).
set -euo pipefail

ROOT="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
WORKER="${ROOT}/services/tejarat-browser-worker"

if [[ -d "${ROOT}/browsers" ]]; then
  export PLAYWRIGHT_BROWSERS_PATH="${ROOT}/browsers"
fi

if [[ -f /etc/pnv/tejarat-worker-test.env ]]; then
  set -a
  # shellcheck disable=SC1091
  source /etc/pnv/tejarat-worker-test.env
  set +a
fi

export PNV_WORKER_STORAGE="${PNV_WORKER_STORAGE:-${ROOT}/session}"
export DISPLAY="${DISPLAY:-:91}"

cd "${WORKER}"
export PYTHONPATH="${WORKER}${PYTHONPATH:+:${PYTHONPATH}}"

for py in \
  "${ROOT}/venv/bin/python" \
  "${ROOT}/.venv/bin/python" \
  "${WORKER}/.venv/bin/python" \
  "${WORKER}/venv/bin/python"; do
  if [[ -x "${py}" ]]; then
    exec "${py}" -m worker.main
  fi
done

echo "No Python venv found under ${ROOT} (expected venv/ or services/.../.venv)." >&2
exit 127

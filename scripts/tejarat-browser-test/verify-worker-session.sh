#!/usr/bin/env bash
# Quick checks after adopt-bank-session (session file + headless Chromium as tejarat-browser).
set -euo pipefail

TARGET="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
USER_NAME="${PNV_TEJARAT_USER:-tejarat-browser}"
SESSION="${PNV_WORKER_STORAGE:-${TARGET}/session}"
PY="${TARGET}/venv/bin/python"
WORKER="${TARGET}/services/tejarat-browser-worker"

[[ "$(id -u)" -eq 0 ]] || { echo "sudo only" >&2; exit 1; }

echo "==> Session file"
if [[ -f "${SESSION}/playwright_storage.json" ]]; then
  ls -la "${SESSION}/playwright_storage.json"
else
  echo "MISSING ${SESSION}/playwright_storage.json"
  echo "Run: sudo bash ${TARGET}/scripts/tejarat-browser-test/adopt-bank-session.sh"
  echo "Or root manual_login then cp from /data/storage/"
  exit 1
fi

echo "==> Headless Chromium smoke (tejarat-browser)"
sudo -u "${USER_NAME}" bash -lc "
  set -a; source /etc/pnv/tejarat-worker-test.env; set +a
  export TEJARAT_HEADLESS=true
  export DISPLAY=${PNV_TEJARAT_DISPLAY:-:91}
  cd '${WORKER}'
  export PYTHONPATH=\$PWD
  '${PY}' -c \"
from playwright.sync_api import sync_playwright
from worker.chromium_launch import launch_chromium
from worker.playwright_env import apply_playwright_env
apply_playwright_env()
with sync_playwright() as p:
    b = launch_chromium(p, headless=True)
    pg = b.new_page()
    pg.goto('about:blank')
    print('headless_ok', pg.title())
    b.close()
\"
"

echo "==> Optional one-shot ingest (needs TEJARAT_STMT_SELECTORS_JSON + TEJARAT_DESTINATION_CARD in env)"
echo "sudo -u ${USER_NAME} TEJARAT_RUN_ONCE=1 TEJARAT_COLLECT_MODE=live bash -lc 'source /etc/pnv/tejarat-worker-test.env; cd ${WORKER}; PYTHONPATH=. ${PY} -m worker.main'"

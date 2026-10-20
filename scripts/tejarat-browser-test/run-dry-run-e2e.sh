#!/usr/bin/env bash
# End-to-end dry run: worker ingest -> inbox -> tejaratVerifyPollOnce (no provisioning).
set -euo pipefail

ROOT="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
if [[ ! -d "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
  export PNV_TEJARAT_VERIFY_CONFIG_PATH="${ROOT}/db/tejarat_verify.json"
  export PNV_TEJARAT_WORKER_INBOX_PATH="${ROOT}/db/tejarat_worker_inbox.json"
  export PNV_TEJARAT_WORKER_HEALTH_PATH="${ROOT}/db/tejarat_worker_health.json"
fi

cd "${ROOT}"

CFG="${PNV_TEJARAT_VERIFY_CONFIG_PATH:-${ROOT}/db/tejarat_verify.json}"
INBOX="${PNV_TEJARAT_WORKER_INBOX_PATH:-${ROOT}/db/tejarat_worker_inbox.json}"
HEALTH="${PNV_TEJARAT_WORKER_HEALTH_PATH:-${ROOT}/db/tejarat_worker_health.json}"
export PNV_TEJARAT_VERIFY_CONFIG_PATH="${CFG}"
export PNV_TEJARAT_WORKER_INBOX_PATH="${INBOX}"
export PNV_TEJARAT_WORKER_HEALTH_PATH="${HEALTH}"

if [[ ! -f "${CFG}" ]]; then
  echo "Missing tejarat_verify.json — run setup-isolated.sh on VPS or copy db/tejarat_verify.example.json" >&2
  exit 1
fi

PHP_PID=""
stop_php() {
  if [[ -n "${PHP_PID}" ]]; then kill "${PHP_PID}" 2>/dev/null || true; fi
}
trap stop_php EXIT

ensure_local_ingest() {
  local url="$1"
  if [[ "${url}" != http://127.0.0.1:* && "${url}" != http://localhost:* ]]; then
    return 0
  fi
  if curl -sf -o /dev/null -m 2 "${url%%tejarat-worker-ingest.php*}" 2>/dev/null; then
    return 0
  fi
  echo "==> Starting PHP ingest on ${url} (env-isolated db paths)"
  env PNV_TEJARAT_VERIFY_CONFIG_PATH="${CFG}" \
    PNV_TEJARAT_WORKER_INBOX_PATH="${INBOX}" \
    PNV_TEJARAT_WORKER_HEALTH_PATH="${HEALTH}" \
    php -S 127.0.0.1:8765 -t "${ROOT}" >/tmp/pnv-tejarat-php-test.log 2>&1 &
  PHP_PID=$!
  sleep 1
}

echo "==> PHP unit tests (Tejarat)"
php tests/tejarat_auto_verify_test.php
php tests/tejarat_worker_ingest_test.php

echo "==> Worker fixture push (one shot)"
WORKER="${ROOT}/services/tejarat-browser-worker"
SECRET="$(php -r '$c=json_decode(file_get_contents(getenv("PNV_TEJARAT_VERIFY_CONFIG_PATH")?:'"'"'db/tejarat_verify.json'"'"'),true); echo $c["worker_ingest_secret"]??"";')"
INGEST="$(php -r '$c=json_decode(file_get_contents(getenv("PNV_TEJARAT_VERIFY_CONFIG_PATH")?:'"'"'db/tejarat_verify.json'"'"'),true); echo $c["worker_ingest_url"]??"http://127.0.0.1:8765/tejarat-worker-ingest.php";')"

ensure_local_ingest "${INGEST}"

export PNV_TEJARAT_INGEST_URL="${INGEST}"
export PNV_TEJARAT_INGEST_SECRET="${SECRET}"
export TEJARAT_COLLECT_MODE=fixture
export TEJARAT_FIXTURE_JSON="${ROOT}/tests/fixtures/tejarat_worker_fixture.json"
export TEJARAT_RUN_ONCE=1
export PNV_WORKER_STORAGE="${PNV_WORKER_STORAGE:-/tmp/pnv-tejarat-worker-storage}"

(
  cd "${WORKER}"
  if [[ -x .venv/bin/python ]] && .venv/bin/python -c "import requests" 2>/dev/null; then
    PY=.venv/bin/python
  else
    PY=python3
  fi
  PYTHONPATH=. "${PY}" -m worker.main
)

echo "==> Poll once (dry_run from config)"
php -r '
require "tejarat_auto_verify_lib.php";
$r = tejaratVerifyPollOnce();
echo json_encode($r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), "\n";
if (empty($r["ok"])) { exit(1); }
'

echo "==> Health check"
php scripts/tejarat-health-check.php
echo "OK dry-run E2E"

#!/usr/bin/env bash
# Install an isolated Tejarat test stack under /opt/tejarat-browser-test.
# Does NOT modify /var/www/html. Requires sudo for /opt and systemd.
set -euo pipefail

TARGET="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
REPO="${PNV_TEJARAT_SOURCE:-$(cd "$(dirname "$0")/../.." && pwd)}"
USER_NAME="${PNV_TEJARAT_USER:-pnv}"
STORAGE="${PNV_WORKER_STORAGE:-/var/lib/pnv/tejarat-worker-test}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root (sudo) so files can land in ${TARGET}." >&2
  exit 1
fi

echo "==> Sync panel code to ${TARGET} (Tejarat-related + instant pay deps)"
install -d -o "${USER_NAME}" -g "${USER_NAME}" -m 0755 "${TARGET}" "${STORAGE}"

RSYNC_EX=(
  --delete
  --exclude '.git'
  --exclude 'node_modules'
  --exclude 'services/tejarat-browser-worker/.venv'
  --exclude 'db/tejarat_verify.json'
  --exclude 'db/tejarat_verify_state.json'
  --exclude 'db/tejarat_worker_inbox.json'
  --exclude 'db/tejarat_worker_health.json'
)

rsync -a "${RSYNC_EX[@]}" "${REPO}/" "${TARGET}/"

SECRET_FILE="${STORAGE}/ingest.secret"
if [[ ! -f "${SECRET_FILE}" ]]; then
  openssl rand -hex 32 | tee "${SECRET_FILE}" >/dev/null
  chown "${USER_NAME}:${USER_NAME}" "${SECRET_FILE}"
  chmod 0600 "${SECRET_FILE}"
fi
INGEST_SECRET="$(cat "${SECRET_FILE}")"

CFG="${TARGET}/db/tejarat_verify.json"
if [[ ! -f "${CFG}" ]]; then
  cat >"${CFG}" <<EOF
{
  "enabled": true,
  "provider": "browser_worker",
  "poll_interval_seconds": 60,
  "dry_run": true,
  "destination_cards": [],
  "max_deposit_age_seconds": 86400,
  "worker_ingest_secret": "${INGEST_SECRET}",
  "worker_heartbeat_max_age_seconds": 300,
  "worker_ingest_url": "http://127.0.0.1:8765/tejarat-worker-ingest.php",
  "notes": "Isolated test — dry_run true until operator disables it in admin."
}
EOF
  chown "${USER_NAME}:${USER_NAME}" "${CFG}"
fi

ENV_FILE="/etc/pnv/tejarat-worker-test.env"
install -d -m 0755 /etc/pnv
cat >"${ENV_FILE}" <<EOF
PNV_TEJARAT_INGEST_URL=http://127.0.0.1:8765/tejarat-worker-ingest.php
PNV_TEJARAT_INGEST_SECRET=${INGEST_SECRET}
TEJARAT_COLLECT_MODE=fixture
TEJARAT_POLL_INTERVAL_SECONDS=120
TEJARAT_BACKOFF_MAX_SECONDS=600
PNV_WORKER_STORAGE=${STORAGE}
TEJARAT_RUN_ONCE=0
PNV_WORKER_VERSION=0.1.0-test
EOF
chmod 0640 "${ENV_FILE}"
chown root:"${USER_NAME}" "${ENV_FILE}"

WORKER_DIR="${TARGET}/services/tejarat-browser-worker"
sudo -u "${USER_NAME}" bash -lc "cd '${WORKER_DIR}' && python3 -m venv .venv && .venv/bin/pip install -q -r requirements.txt && .venv/bin/playwright install chromium"

UNIT=/etc/systemd/system/tejarat-browser-worker-test.service
cat >"${UNIT}" <<EOF
[Unit]
Description=PNV Tejarat browser worker (isolated test, read-only)
After=network-online.target pnv-tejarat-php-test.service

[Service]
Type=simple
User=${USER_NAME}
Group=${USER_NAME}
WorkingDirectory=${WORKER_DIR}
EnvironmentFile=${ENV_FILE}
ExecStart=${WORKER_DIR}/.venv/bin/python -m worker.main
Restart=on-failure
RestartSec=30
NoNewPrivileges=true
PrivateTmp=true
ReadWritePaths=${STORAGE}

[Install]
WantedBy=multi-user.target
EOF

PHP_UNIT=/etc/systemd/system/pnv-tejarat-php-test.service
cat >"${PHP_UNIT}" <<EOF
[Unit]
Description=PNV Tejarat isolated PHP ingest (127.0.0.1:8765)
After=network.target

[Service]
Type=simple
User=${USER_NAME}
Group=${USER_NAME}
WorkingDirectory=${TARGET}
Environment=PNV_TEJARAT_VERIFY_CONFIG_PATH=${CFG}
Environment=PNV_TEJARAT_WORKER_INBOX_PATH=${TARGET}/db/tejarat_worker_inbox.json
Environment=PNV_TEJARAT_WORKER_HEALTH_PATH=${TARGET}/db/tejarat_worker_health.json
ExecStart=/usr/bin/php -S 127.0.0.1:8765 -t ${TARGET}
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable pnv-tejarat-php-test.service tejarat-browser-worker-test.service
systemctl restart pnv-tejarat-php-test.service

echo "==> Done. Secret in ${SECRET_FILE} (also in ${ENV_FILE})."
echo "    Start worker: systemctl start tejarat-browser-worker-test"
echo "    Dry-run E2E:  ${TARGET}/scripts/tejarat-browser-test/run-dry-run-e2e.sh"
echo "    Live fetch:   set TEJARAT_COLLECT_MODE=live + selectors in ${ENV_FILE}, manual_login first."

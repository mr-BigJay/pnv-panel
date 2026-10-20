#!/usr/bin/env bash
# Non-destructive, idempotent Tejarat isolated stack under /opt/tejarat-browser-test.
# Does NOT touch /var/www/html or production PNV config under /var/www/html.
#
# Preserves on re-run: venv/, .venv/, browsers/, vnc/, session/, db state, secrets.
# Applies systemd/env changes only when PNV_TEJARAT_CONFIRM_SERVICES=1 (after backup + diff).
set -euo pipefail

TARGET="${PNV_TEJARAT_TEST_ROOT:-/opt/tejarat-browser-test}"
REPO="${PNV_TEJARAT_SOURCE:-$(cd "$(dirname "$0")/../.." && pwd)}"
USER_NAME="${PNV_TEJARAT_USER:-tejarat-browser}"
SESSION_DIR="${PNV_WORKER_STORAGE:-${TARGET}/session}"
DISPLAY_NUM="${PNV_TEJARAT_DISPLAY:-:91}"
ENV_FILE="${PNV_TEJARAT_ENV_FILE:-/etc/pnv/tejarat-worker-test.env}"
BACKUP_ROOT="${TARGET}/.setup-backups"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="${BACKUP_ROOT}/${STAMP}"
CONFIRM_SERVICES="${PNV_TEJARAT_CONFIRM_SERVICES:-0}"

log() { echo "==> $*"; }
warn() { echo "!! $*" >&2; }

if [[ "$(id -u)" -ne 0 ]]; then
  warn "Run with sudo (root). Production /var/www/html is never modified by this script."
  exit 1
fi

if [[ "${TARGET}" == "/var/www/html" ]] || [[ "${REPO}" == "/var/www/html" ]]; then
  warn "Refusing to use /var/www/html as test root or source."
  exit 1
fi

install -d -m 0755 "${TARGET}" "${BACKUP_ROOT}" "${SESSION_DIR}"
chmod 0700 "${SESSION_DIR}" 2>/dev/null || true

if ! id "${USER_NAME}" &>/dev/null; then
  log "Creating system user ${USER_NAME} (home=${TARGET})"
  useradd --system --home "${TARGET}" --shell /usr/sbin/nologin "${USER_NAME}" 2>/dev/null || useradd -r -d "${TARGET}" -s /usr/sbin/nologin "${USER_NAME}"
fi

chown "${USER_NAME}:${USER_NAME}" "${SESSION_DIR}" || true

fix_worker_tree_permissions() {
  for name in venv .venv browsers vnc session; do
    [[ -e "${TARGET}/${name}" ]] || continue
    chown -R "${USER_NAME}:${USER_NAME}" "${TARGET}/${name}"
  done
  chmod 700 "${SESSION_DIR}" 2>/dev/null || true
  if [[ -f "${TARGET}/venv/bin/python" ]]; then
    chmod u+rx "${TARGET}/venv/bin/python" 2>/dev/null || true
  fi
}

RSYNC_PROTECT=(
  --exclude '.git/'
  --exclude 'node_modules/'
  --exclude 'venv/'
  --exclude '.venv/'
  --exclude 'browsers/'
  --exclude 'vnc/'
  --exclude 'session/'
  --exclude 'playwright_storage.json'
  --exclude '.setup-backups/'
  --exclude 'db/tejarat_verify.json'
  --exclude 'db/tejarat_verify_state.json'
  --exclude 'db/tejarat_worker_inbox.json'
  --exclude 'db/tejarat_worker_health.json'
  --exclude 'services/tejarat-browser-worker/.venv/'
  --exclude 'services/tejarat-browser-worker/venv/'
)

log "Sync panel/worker code into ${TARGET} (no --delete; protected dirs unchanged)"
rsync -a "${RSYNC_PROTECT[@]}" "${REPO}/" "${TARGET}/"

# Own synced application trees only — never recurse into vnc/ or browsers/
for rel in scripts services admin db tests docs assets tejarat-worker-ingest.php tejarat_auto_verify_lib.php tejarat_worker_lib.php instant_pay_lib.php xui_lib.php; do
  if [[ -e "${TARGET}/${rel}" ]]; then
    chown -R "${USER_NAME}:${USER_NAME}" "${TARGET}/${rel}" 2>/dev/null || true
  fi
done
chown "${USER_NAME}:${USER_NAME}" "${TARGET}/scripts/tejarat-browser-test/run-worker.sh" 2>/dev/null || true
chmod +x "${TARGET}/scripts/tejarat-browser-test/run-worker.sh" 2>/dev/null || true
chmod +x "${TARGET}/scripts/tejarat-browser-test/run-dry-run-e2e.sh" 2>/dev/null || true

SECRET_FILE="${SESSION_DIR}/ingest.secret"
if [[ ! -f "${SECRET_FILE}" ]]; then
  openssl rand -hex 32 >"${SECRET_FILE}"
  chmod 0600 "${SECRET_FILE}"
  chown "${USER_NAME}:${USER_NAME}" "${SECRET_FILE}"
  log "Created ingest secret ${SECRET_FILE}"
else
  log "Keeping existing ingest secret ${SECRET_FILE}"
fi
INGEST_SECRET="$(cat "${SECRET_FILE}")"

CFG="${TARGET}/db/tejarat_verify.json"
install -d -o "${USER_NAME}" -g "${USER_NAME}" -m 0755 "${TARGET}/db"
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
  "notes": "Isolated test — dry_run stays true until operator disables in admin (manual confirm always available)."
}
EOF
  chown "${USER_NAME}:${USER_NAME}" "${CFG}"
  chmod 0640 "${CFG}"
  log "Created ${CFG} (dry_run=true)"
else
  log "Keeping existing ${CFG} (dry_run / admin manual flow unchanged)"
fi

# --- env file: merge missing keys only ---
install -d -m 0755 /etc/pnv
ENV_NEW="${BACKUP_DIR}/tejarat-worker-test.env.new"
ENV_MERGED="${BACKUP_DIR}/tejarat-worker-test.env.merged"
install -d -m 0700 "${BACKUP_DIR}"

declare -A ENV_MAP=(
  [PNV_TEJARAT_INGEST_URL]="http://127.0.0.1:8765/tejarat-worker-ingest.php"
  [PNV_TEJARAT_INGEST_SECRET]="${INGEST_SECRET}"
  [TEJARAT_COLLECT_MODE]="fixture"
  [TEJARAT_POLL_INTERVAL_SECONDS]="120"
  [TEJARAT_BACKOFF_MAX_SECONDS]="600"
  [PNV_WORKER_STORAGE]="${SESSION_DIR}"
  [TEJARAT_RUN_ONCE]="0"
  [PNV_WORKER_VERSION]="0.1.0-test"
  [DISPLAY]="${DISPLAY_NUM}"
  [PNV_TEJARAT_TEST_ROOT]="${TARGET}"
  [PLAYWRIGHT_BROWSERS_PATH]="${TARGET}/browsers"
)

merge_env_file() {
  local dest="$1"
  local tmp="$2"
  : >"${tmp}"
  declare -A existing=()
  if [[ -f "${dest}" ]]; then
    cp -a "${dest}" "${BACKUP_DIR}/$(basename "${dest}").bak"
    while IFS= read -r line || [[ -n "${line}" ]]; do
      [[ "${line}" =~ ^[[:space:]]*# ]] && continue
      [[ "${line}" =~ ^[A-Za-z_][A-Za-z0-9_]*= ]] || continue
      key="${line%%=*}"
      existing["${key}"]="${line#*=}"
    done <"${dest}"
  fi
  for key in "${!ENV_MAP[@]}"; do
    if [[ -n "${existing[${key}]+x}" ]]; then
      printf '%s=%s\n' "${key}" "${existing[${key}]}" >>"${tmp}"
    else
      printf '%s=%s\n' "${key}" "${ENV_MAP[${key}]}" >>"${tmp}"
    fi
  done
  # Preserve custom keys from old file
  for key in "${!existing[@]}"; do
    if [[ -z "${ENV_MAP[${key}]+x}" ]]; then
      printf '%s=%s\n' "${key}" "${existing[${key}]}" >>"${tmp}"
    fi
  done
}

ENV_APPLIED=0
merge_env_file "${ENV_FILE}" "${ENV_MERGED}"
if [[ ! -f "${ENV_FILE}" ]]; then
  cp "${ENV_MERGED}" "${ENV_FILE}"
  chmod 0640 "${ENV_FILE}"
  chown root:"${USER_NAME}" "${ENV_FILE}"
  ENV_APPLIED=1
  log "Installed ${ENV_FILE}"
elif ! cmp -s "${ENV_FILE}" "${ENV_MERGED}"; then
  cp "${ENV_MERGED}" "${ENV_NEW}"
  warn "Env drift detected. Proposed merge saved: ${ENV_NEW}"
  warn "Apply with: PNV_TEJARAT_CONFIRM_SERVICES=1 sudo -E bash $0"
  if [[ "${CONFIRM_SERVICES}" == "1" ]]; then
    cp "${ENV_FILE}" "${BACKUP_DIR}/tejarat-worker-test.env.pre-apply"
    cp "${ENV_MERGED}" "${ENV_FILE}"
    chmod 0640 "${ENV_FILE}"
    chown root:"${USER_NAME}" "${ENV_FILE}"
    ENV_APPLIED=1
    log "Applied merged ${ENV_FILE} (backup in ${BACKUP_DIR})"
  fi
else
  log "Env file unchanged ${ENV_FILE}"
fi

# --- venv: never delete; optional gentle pip if venv exists ---
resolve_venv_python() {
  for py in \
    "${TARGET}/venv/bin/python" \
    "${TARGET}/.venv/bin/python" \
    "${TARGET}/services/tejarat-browser-worker/.venv/bin/python" \
    "${TARGET}/services/tejarat-browser-worker/venv/bin/python"; do
    if [[ -x "${py}" ]]; then
      echo "${py}"
      return 0
    fi
  done
  return 1
}

fix_worker_tree_permissions

if VPY="$(resolve_venv_python)"; then
  log "Using existing venv: ${VPY}"
  if ! sudo -u "${USER_NAME}" test -x "${VPY}" 2>/dev/null; then
    warn "venv not executable as ${USER_NAME} — re-running fix_worker_tree_permissions"
    fix_worker_tree_permissions
  fi
  if ! sudo -u "${USER_NAME}" "${VPY}" -c "import playwright" 2>/dev/null; then
    log "playwright missing — pip install as ${USER_NAME}"
    sudo -u "${USER_NAME}" env HOME="${TARGET}" "${VPY}" -m pip install -q \
      -r "${TARGET}/services/tejarat-browser-worker/requirements.txt"
    if [[ -d "${TARGET}/browsers" ]]; then
      sudo -u "${USER_NAME}" env HOME="${TARGET}" PLAYWRIGHT_BROWSERS_PATH="${TARGET}/browsers" \
        "${VPY}" -m playwright install chromium 2>/dev/null || true
    fi
  fi
  if [[ "${PNV_TEJARAT_PIP_SYNC:-0}" == "1" ]]; then
    sudo -u "${USER_NAME}" env DISPLAY="${DISPLAY_NUM}" HOME="${TARGET}" \
      bash -lc "'${VPY}' -m pip install -q -r '${TARGET}/services/tejarat-browser-worker/requirements.txt'"
  fi
else
  warn "No venv found under ${TARGET}. Skipping venv/playwright install (preserve your layout)."
  warn "Create venv manually or set PNV_TEJARAT_CREATE_VENV=1 to create ${TARGET}/venv"
  if [[ "${PNV_TEJARAT_CREATE_VENV:-0}" == "1" ]]; then
    log "Creating ${TARGET}/venv (one-time)"
    sudo -u "${USER_NAME}" python3 -m venv "${TARGET}/venv"
    sudo -u "${USER_NAME}" "${TARGET}/venv/bin/pip" install -q -r "${TARGET}/services/tejarat-browser-worker/requirements.txt"
    if [[ "${PNV_TEJARAT_INSTALL_BROWSERS:-0}" == "1" ]] && [[ ! -d "${TARGET}/browsers" ]]; then
      sudo -u "${USER_NAME}" env PLAYWRIGHT_BROWSERS_PATH="${TARGET}/browsers" \
        "${TARGET}/venv/bin/playwright" install chromium
    fi
  fi
fi

SYSTEMD_TOUCHED=0

write_unit_if_needed() {
  local path="$1"
  local content="$2"
  local name
  name="$(basename "${path}")"
  install -d -m 0700 "${BACKUP_DIR}"
  if [[ -f "${path}" ]]; then
    cp -a "${path}" "${BACKUP_DIR}/${name}.bak"
    if cmp -s <(printf '%s\n' "${content}") "${path}"; then
      log "systemd ${name} unchanged"
      return 0
    fi
    printf '%s\n' "${content}" >"${BACKUP_DIR}/${name}.proposed"
    warn "systemd ${name} differs — proposed unit in ${BACKUP_DIR}/${name}.proposed"
    if [[ "${CONFIRM_SERVICES}" != "1" ]]; then
      warn "Not applying ${name}. Set PNV_TEJARAT_CONFIRM_SERVICES=1 to apply after review."
      return 1
    fi
    cp "${BACKUP_DIR}/${name}.bak" "${BACKUP_DIR}/${name}.pre-apply"
  fi
  printf '%s\n' "${content}" >"${path}"
  SYSTEMD_TOUCHED=1
  log "Installed ${path}"
  return 0
}

WORKER_DIR="${TARGET}/services/tejarat-browser-worker"
RUN_WRAPPER="${TARGET}/scripts/tejarat-browser-test/run-worker.sh"

WORKER_UNIT_CONTENT="[Unit]
Description=PNV Tejarat browser worker (isolated test, read-only)
After=network-online.target pnv-tejarat-php-test.service

[Service]
Type=simple
User=${USER_NAME}
Group=${USER_NAME}
WorkingDirectory=${WORKER_DIR}
EnvironmentFile=-${ENV_FILE}
Environment=DISPLAY=${DISPLAY_NUM}
Environment=PNV_WORKER_STORAGE=${SESSION_DIR}
Environment=PNV_TEJARAT_TEST_ROOT=${TARGET}
ExecStart=${RUN_WRAPPER}
Restart=on-failure
RestartSec=30
NoNewPrivileges=true
PrivateTmp=true
ReadWritePaths=${SESSION_DIR} ${TARGET}/browsers

[Install]
WantedBy=multi-user.target"

PHP_UNIT_CONTENT="[Unit]
Description=PNV Tejarat isolated PHP ingest (127.0.0.1:8765 only)
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
WantedBy=multi-user.target"

UNITS_SKIPPED=0
write_unit_if_needed /etc/systemd/system/pnv-tejarat-php-test.service "${PHP_UNIT_CONTENT}" || UNITS_SKIPPED=1
write_unit_if_needed /etc/systemd/system/tejarat-browser-worker-test.service "${WORKER_UNIT_CONTENT}" || UNITS_SKIPPED=1

if [[ "${SYSTEMD_TOUCHED}" == "1" ]] || [[ "${ENV_APPLIED}" == "1" ]]; then
  systemctl daemon-reload
  systemctl enable pnv-tejarat-php-test.service tejarat-browser-worker-test.service 2>/dev/null || true
fi

if [[ "${SYSTEMD_TOUCHED}" == "1" ]] || [[ "${ENV_APPLIED}" == "1" ]]; then
  if systemctl is-active --quiet pnv-tejarat-php-test.service 2>/dev/null; then
    systemctl restart pnv-tejarat-php-test.service
  else
    systemctl start pnv-tejarat-php-test.service || true
  fi
  log "PHP test ingest (re)started — worker NOT auto-started (start after manual login if live)."
fi

log "Done. Session + bank storage: ${SESSION_DIR}/playwright_storage.json"
log "User: ${USER_NAME} · DISPLAY=${DISPLAY_NUM} · dry_run stays in ${CFG}"
log "Backups (if any): ${BACKUP_DIR}"
log "Dry-run E2E: ${TARGET}/scripts/tejarat-browser-test/run-dry-run-e2e.sh"
if [[ "${UNITS_SKIPPED}" == "1" ]]; then
  warn "Some systemd units were not applied. Review ${BACKUP_DIR} and re-run with PNV_TEJARAT_CONFIRM_SERVICES=1"
elif [[ -f "${ENV_NEW:-}" ]] && [[ "${ENV_APPLIED}" != "1" ]]; then
  warn "Env merge pending. Review ${ENV_NEW} and re-run with PNV_TEJARAT_CONFIRM_SERVICES=1"
fi

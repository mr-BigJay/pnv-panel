#!/usr/bin/env bash
# Overlay غیرمخرب Tejarat روی پنل Production (پیش‌فرض /var/www/html).
# فقط فایل‌های manifest کپی می‌شوند؛ db/tejarat_verify.json موجود overwrite نمی‌شود.
set -euo pipefail

PANEL="${PNV_PANEL_ROOT:-/var/www/html}"
SOURCE="${PNV_TEJARAT_SOURCE:-/root/pnv-panel-tejarat}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP="${PANEL}/.backups-tejarat-${STAMP}"
MANIFEST="${SOURCE}/scripts/tejarat-browser-test/panel-manifest.txt"
SESSION_SECRET="${PNV_WORKER_STORAGE:-/opt/tejarat-browser-test/session}/ingest.secret"
PUBLIC_URL="${PNV_PUBLIC_URL:-}"

log() { echo "==> $*"; }
warn() { echo "!! $*" >&2; }
die() { warn "$*"; exit 1; }

[[ "$(id -u)" -eq 0 ]] || die "با sudo/root اجرا کنید."
[[ -d "${PANEL}" ]] || die "پنل پیدا نشد: ${PANEL}"
[[ -f "${MANIFEST}" ]] || die "manifest نیست — اول bootstrap را اجرا کنید: ${SOURCE}"

if [[ "${PANEL}" == "${SOURCE}" ]]; then
  die "SOURCE و PANEL یکی است — مسیر را اصلاح کنید."
fi

install -d -m 0750 "${BACKUP}"
log "بکاپ فایل‌های جایگزین‌شونده → ${BACKUP}"

while IFS= read -r rel || [[ -n "${rel}" ]]; do
  rel="${rel%%#*}"
  rel="$(echo "${rel}" | xargs)"
  [[ -z "${rel}" ]] || [[ "${rel}" == \#* ]] && continue
  src="${SOURCE}/${rel}"
  dst="${PANEL}/${rel}"
  [[ -f "${src}" ]] || { warn "رد شد (در repo نیست): ${rel}"; continue; }
  if [[ -f "${dst}" ]]; then
    install -D -m "$(stat -c '%a' "${dst}" 2>/dev/null || echo 644)" "${dst}" "${BACKUP}/${rel}"
  fi
  install -D -m 0644 "${src}" "${dst}"
  log "copied ${rel}"
done <"${MANIFEST}"

# db/runtime — فقط اگر نیست
init_json_from_example() {
  local example="$1"
  local target="$2"
  if [[ -f "${target}" ]]; then
    log "نگه داشتن ${target}"
    return 0
  fi
  if [[ -f "${PANEL}/${example}" ]]; then
    cp "${PANEL}/${example}" "${target}"
    log "ساخت ${target} از example"
  fi
}

init_json_from_example "db/tejarat_verify.example.json" "${PANEL}/db/tejarat_verify.json"
init_json_from_example "db/tejarat_worker_inbox.example.json" "${PANEL}/db/tejarat_worker_inbox.json"
init_json_from_example "db/tejarat_worker_health.example.json" "${PANEL}/db/tejarat_worker_health.json"

# پیکربندی اولیه verify (بدون خاموش کردن dry_run)
if [[ -f "${SESSION_SECRET}" ]] && [[ -f "${PANEL}/db/tejarat_verify.json" ]]; then
  php <<'PHP' "${PANEL}/db/tejarat_verify.json" "${SESSION_SECRET}" "${PUBLIC_URL}"
<?php
$path = $argv[1];
$secretFile = $argv[2];
$public = rtrim((string)($argv[3] ?? ''), '/');
$secret = trim(@file_get_contents($secretFile) ?: '');
$data = json_decode(file_get_contents($path), true) ?: [];
if ($secret !== '' && trim((string)($data['worker_ingest_secret'] ?? '')) === '') {
    $data['worker_ingest_secret'] = $secret;
}
if ($public !== '') {
    $data['worker_ingest_url'] = $public . '/tejarat-worker-ingest.php';
}
$data['provider'] = $data['provider'] ?? 'browser_worker';
if (!isset($data['enabled'])) {
    $data['enabled'] = true;
}
$data['dry_run'] = true;
file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
echo "tejarat_verify.json merged (dry_run=true preserved)\n";
PHP
fi

log "Deploy تمام. بکاپ: ${BACKUP}"
echo ""
echo "── cron (root) ──"
echo "* * * * * php ${PANEL}/scripts/cron-tejarat-verify.php >> /var/log/pnv-tejarat-cron.log 2>&1"
echo ""
echo "── ادمین ──"
echo "منوی ادمین → «تجارت» — Dry Run روشن بگذارید تا Live."
if [[ -z "${PUBLIC_URL}" ]]; then
  warn "PNV_PUBLIC_URL تنظیم نشد — در ادمین→تجارت آدرس ingest را دستی بگذارید."
fi

#!/usr/bin/env bash
# Move playwright session from root run (/data/storage) into worker session dir.
set -euo pipefail

TARGET="${PNV_WORKER_STORAGE:-/opt/tejarat-browser-test/session}"
USER_NAME="${PNV_TEJARAT_USER:-tejarat-browser}"
DST="${TARGET}/playwright_storage.json"

[[ "$(id -u)" -eq 0 ]] || { echo "sudo only" >&2; exit 1; }

for SRC in /data/storage/playwright_storage.json "${HOME}/.cache/ms-playwright/../playwright_storage.json"; do
  [[ -f "${SRC}" ]] || continue
  install -d -o "${USER_NAME}" -g "${USER_NAME}" -m 0700 "${TARGET}"
  cp -a "${SRC}" "${DST}"
  chown "${USER_NAME}:${USER_NAME}" "${DST}"
  chmod 600 "${DST}"
  echo "Session adopted: ${DST} (from ${SRC})"
  exit 0
done

echo "No session found. Run manual_login as root or tejarat-browser first." >&2
exit 1

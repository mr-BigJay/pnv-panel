#!/usr/bin/env bash
# Root: allow tejarat-browser to open Chromium on DISPLAY (e.g. :91 / VNC).
set -euo pipefail

USER_NAME="${PNV_TEJARAT_USER:-tejarat-browser}"
DISPLAY_NUM="${PNV_TEJARAT_DISPLAY:-:91}"
export DISPLAY="${DISPLAY_NUM}"

[[ "$(id -u)" -eq 0 ]] || { echo "Run with sudo" >&2; exit 1; }
id "${USER_NAME}" &>/dev/null || { echo "User ${USER_NAME} missing" >&2; exit 1; }

USER_HOME="$(getent passwd "${USER_NAME}" | cut -d: -f6)"
install -d -m 0755 "${USER_HOME}"

if command -v xhost >/dev/null 2>&1; then
  xhost +SI:localuser:"${USER_NAME}" 2>/dev/null || xhost "local:${USER_NAME}" 2>/dev/null || true
  echo "xhost updated for ${USER_NAME}"
fi

SRC_AUTH="${XAUTHORITY:-}"
if [[ -z "${SRC_AUTH}" && -f /root/.Xauthority ]]; then
  SRC_AUTH=/root/.Xauthority
fi
if [[ -n "${SRC_AUTH}" && -f "${SRC_AUTH}" ]]; then
  for dest in "${USER_HOME}/.Xauthority" "/opt/tejarat-browser-test/.Xauthority"; do
    install -d -m 0755 "$(dirname "${dest}")"
    cp "${SRC_AUTH}" "${dest}"
    chown "${USER_NAME}:${USER_NAME}" "${dest}"
    chmod 600 "${dest}"
  done
  echo "Copied Xauthority to ${USER_HOME}/.Xauthority and /opt/tejarat-browser-test/.Xauthority"
fi

if command -v xdpyinfo >/dev/null 2>&1; then
  if ! sudo -u "${USER_NAME}" env DISPLAY="${DISPLAY_NUM}" XAUTHORITY="${USER_HOME}/.Xauthority" \
    xdpyinfo >/dev/null 2>&1; then
    echo "!! xdpyinfo failed for ${USER_NAME} on ${DISPLAY_NUM} — is Xvfb/VNC running?" >&2
    exit 1
  fi
  echo "xdpyinfo OK for ${USER_NAME} on ${DISPLAY_NUM}"
else
  echo "xdpyinfo not installed — skip display test"
fi

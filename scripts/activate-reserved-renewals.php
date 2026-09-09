#!/bin/bash
set -euo pipefail

ROOT="${ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
export ROOT

if command -v php >/dev/null 2>&1; then
  php "${ROOT}/scripts/activate-reserved-renewals-runner.php"
else
  echo "php not found" >&2
  exit 1
fi

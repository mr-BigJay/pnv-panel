#!/usr/bin/env bash
set -euo pipefail

# Roll back site code to origin/main (see deploy-code-from-ref.sh).

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKUP="${BACKUP:-1}"
REF=origin/main

export ROOT SRC BACKUP REF
exec "$(dirname "${BASH_SOURCE[0]}")/deploy-code-from-ref.sh"

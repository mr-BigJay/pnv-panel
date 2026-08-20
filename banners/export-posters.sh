#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
CHROME="${CHROME:-google-chrome-stable}"
OUT="${OUT:-$ROOT/output}"
mkdir -p "$OUT"

export_png() {
  local html="$1"
  local png="$2"
  "$CHROME" \
    --headless=new \
    --disable-gpu \
    --hide-scrollbars \
    --window-size=1080,1920 \
    --screenshot="$png" \
    "file://$html"
  echo "Wrote $png"
}

export_png "$ROOT/campaign-a-page1.html" "$OUT/poster-page1-campaign.png"
export_png "$ROOT/campaign-a-page2.html" "$OUT/poster-page2-plans.png"

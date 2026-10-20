#!/usr/bin/env bash
# Three-way merge helper: PR branch vs live /var/www/html (custom production tree).
set -euo pipefail

REPO="${REPO:-$(cd "$(dirname "$0")/../.." && pwd)}"
WWW="${WWW:-/var/www/html}"
OUT="${OUT:-/tmp/pnv-admin-tabs-merge}"
REF="${REF:-HEAD}"

mkdir -p "$OUT"

merge_one() {
  local rel="$1"
  local base="$OUT/$(basename "$rel")"
  local prod="${WWW}/${rel}"
  local pr="${base}.pr"
  local live="${base}.prod"
  local merged="${base}.merged"

  if [[ ! -f "$prod" ]]; then
    echo "SKIP (no prod): $rel"
    return 0
  fi

  git -C "$REPO" show "${REF}:${rel}" > "$pr"
  git -C "$REPO" show "origin/main:${rel}" > "${base}.base" 2>/dev/null || git -C "$REPO" merge-base "${REF}" origin/main | xargs -I{} git -C "$REPO" show {}:"${rel}" > "${base}.base"
  cp "$prod" "$live"
  merge-file -p "$live" "${base}.base" "$pr" > "$merged" || true
  echo "Wrote $merged (review before copying to $prod)"
}

FILES=(
  "admin/payments.php"
  "admin/renews.php"
  "instant_pay_lib.php"
)

for f in "${FILES[@]}"; do
  merge_one "$f"
done

NEW="instant_pay_admin_tabs_lib.php"
git -C "$REPO" show "${REF}:${NEW}" > "${OUT}/${NEW}.pr"
echo "New file template: ${OUT}/${NEW}.pr → copy to ${WWW}/${NEW}"

echo "Done. Output directory: $OUT"

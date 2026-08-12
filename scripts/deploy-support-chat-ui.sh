#!/bin/bash
# Deploy admin support chat UI fixes — run ON THE SERVER
set -euo pipefail

BR="${BR:-cursor/admin-support-chat-ui-b94c}"
BASE="https://raw.githubusercontent.com/mr-BigJay/pnv-panel/${BR}"
ROOT="${ROOT:-/var/www/html}"

echo "=== Deploy support chat UI (branch: ${BR}) ==="
echo "Target: ${ROOT}"

files=(
  "support_lib.php"
  "support_ui.css"
  "support_ui.js"
  "admin/support.php"
)

for rel in "${files[@]}"; do
  dest="${ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "${dest}"
  echo "  OK ${rel}"
done

echo ""
grep -c "supportAvatarLightbox" "${ROOT}/support_ui.css" || true
grep -c "supportRenderHeaderAvatarHtml" "${ROOT}/support_lib.php" || true
echo ""
echo "Done. Hard-refresh admin support (Ctrl+Shift+R). CSS/JS v38."

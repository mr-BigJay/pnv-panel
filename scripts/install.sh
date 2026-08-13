#!/bin/bash
# PNV Panel — نصب روی Ubuntu (Apache + PHP)
# استفاده:
#   curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh | sudo bash
#   curl -fsSL .../install.sh | sudo bash -s -- --version v1.0.0
#   curl -fsSL .../install.sh | sudo ROOT=/var/www/pnv bash
set -euo pipefail

REPO="${REPO:-mr-BigJay/pnv-panel}"
VERSION="${VERSION:-main}"
ROOT="${ROOT:-/var/www/html}"
REF="${REF:-${VERSION}}"
SKIP_APT="${SKIP_APT:-0}"
BACKUP="${BACKUP:-1}"

usage(){
    cat <<'EOF'
PNV Panel installer (Ubuntu)

Options (env or args):
  --version v1.0.0   Tag/branch to install (default: main)
  --root /var/www/html   Web root
  --repo owner/name    GitHub repo
  --skip-apt           Skip apt package install
  --no-backup          Do not backup existing web root

Examples:
  curl -fsSL https://raw.githubusercontent.com/mr-BigJay/pnv-panel/main/scripts/install.sh | sudo bash
  curl -fsSL .../install.sh | sudo bash -s -- --version v1.0.0 --root /var/www/html
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --version) VERSION="$2"; REF="$2"; shift 2 ;;
        --root) ROOT="$2"; shift 2 ;;
        --repo) REPO="$2"; shift 2 ;;
        --skip-apt) SKIP_APT=1; shift ;;
        --no-backup) BACKUP=0; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1"; usage; exit 1 ;;
    esac
done

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Please run as root (sudo)." >&2
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    apt-get update -qq
    apt-get install -y curl ca-certificates
fi

echo "=== PNV Panel install ==="
echo "Repo:    ${REPO}"
echo "Version: ${REF}"
echo "Root:    ${ROOT}"
echo ""

if [[ "$SKIP_APT" != "1" ]]; then
    echo ">> Installing system packages..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y \
        apache2 \
        libapache2-mod-php \
        php \
        php-cli \
        php-curl \
        php-gd \
        php-mbstring \
        php-xml \
        php-zip \
        unzip \
        curl \
        ca-certificates
    a2enmod rewrite headers 2>/dev/null || true
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ARCHIVE_URL="https://github.com/${REPO}/archive/refs/tags/${REF}.tar.gz"
BRANCH_URL="https://github.com/${REPO}/archive/refs/heads/${REF}.tar.gz"

echo ">> Downloading source..."
if curl -fsSL "$ARCHIVE_URL" -o "$TMP/src.tar.gz" 2>/dev/null; then
    :
elif curl -fsSL "$BRANCH_URL" -o "$TMP/src.tar.gz" 2>/dev/null; then
    :
else
    echo "Download failed for tag/branch: ${REF}" >&2
    echo "Check that the release exists on GitHub." >&2
    exit 1
fi

tar -xzf "$TMP/src.tar.gz" -C "$TMP"
SRC_DIR="$(find "$TMP" -maxdepth 1 -type d -name 'pnv-panel-*' | head -1)"

if [[ -z "$SRC_DIR" || ! -d "$SRC_DIR" ]]; then
    echo "Extracted source folder not found." >&2
    exit 1
fi

if [[ -d "$ROOT" && "$BACKUP" == "1" ]]; then
    BK="${ROOT}.bak.$(date +%Y%m%d-%H%M%S)"
    echo ">> Backup existing root -> ${BK}"
    cp -a "$ROOT" "$BK"
fi

mkdir -p "$ROOT"
echo ">> Copying files to ${ROOT}..."
rsync -a --delete \
    --exclude 'db/bale.json' \
    --exclude 'db/telegram.json' \
    --exclude 'db/xui_servers.json' \
    --exclude 'db/xui_state.json' \
    --exclude 'db/instant_payments.json' \
    --exclude 'invoices/payments.csv' \
    --exclude '.git' \
    "$SRC_DIR/" "$ROOT/"

echo ">> Initializing data files..."
mkdir -p "$ROOT/db" "$ROOT/invoices" "$ROOT/uploads/support" "$ROOT/uploads/avatars" "$ROOT/temp"

init_json_array(){
    local f="$1"
    if [[ ! -f "$f" ]]; then
        echo '[]' > "$f"
    fi
}

for name in support discount_codes discount_code_usages dashboard_announcements dashboard_announcement_reads; do
    init_json_array "$ROOT/db/${name}.json"
done

if [[ ! -f "$ROOT/db/xui_servers.json" && -f "$ROOT/db/xui_servers.example.json" ]]; then
    cp "$ROOT/db/xui_servers.example.json" "$ROOT/db/xui_servers.json"
    echo "  Created db/xui_servers.json from example (edit tokens/passwords)"
fi

if [[ ! -f "$ROOT/invoices/payments.csv" ]]; then
    touch "$ROOT/invoices/payments.csv"
fi

if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "$ROOT/db" "$ROOT/invoices" "$ROOT/uploads" "$ROOT/temp" 2>/dev/null || true
    find "$ROOT/db" -type f -name '*.json' -exec chown www-data:www-data {} + 2>/dev/null || true
    chmod 775 "$ROOT/db" "$ROOT/uploads" "$ROOT/uploads/support" "$ROOT/uploads/avatars" "$ROOT/temp" 2>/dev/null || true
fi

if [[ "$SKIP_APT" != "1" ]] && systemctl is-active --quiet apache2 2>/dev/null; then
    systemctl reload apache2 || systemctl restart apache2 || true
fi

echo ""
echo "=== Done ==="
echo "Panel path: ${ROOT}"
echo ""
echo "Next steps:"
echo "  1) Edit ${ROOT}/db/xui_servers.json (3x-ui servers + tokens)"
echo "  2) Configure Apache vhost / SSL for your domain"
echo "  3) Open /index.php and admin /bigjay_controller/"
echo ""
echo "Upgrade later:"
echo "  curl -fsSL https://raw.githubusercontent.com/${REPO}/main/scripts/install.sh | sudo bash -s -- --version v01.1.0"

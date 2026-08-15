#!/bin/bash
# PNV Panel v02.01.01 — one-line installer (Nginx or Apache)
#
#   bash <(curl -Ls https://raw.githubusercontent.com/mr-BigJay/pnv-panel/v02.01.01/scripts/install.sh)
#
# Non-interactive:
#   curl -fsSL .../install.sh | sudo bash -s -- --yes --web nginx --domain panel.example.com --email admin@example.com --version v02.01.01
#
set -euo pipefail

REPO="${REPO:-mr-BigJay/pnv-panel}"
VERSION="${VERSION:-v02.01.01}"
ROOT="${ROOT:-/var/www/pnv-panel}"
REF="${REF:-${VERSION}}"
WEB="${WEB:-nginx}"
SKIP_APT="${SKIP_APT:-0}"
BACKUP="${BACKUP:-1}"
INTERACTIVE="${INTERACTIVE:-1}"
DOMAIN="${DOMAIN:-}"
EMAIL="${EMAIL:-}"
IMPORT_ZIP="${IMPORT_ZIP:-}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-}"

say(){ echo -e "$*"; }

apt_update_safe(){
    if apt-get update -qq 2>/dev/null; then
        return 0
    fi

    say "!! apt update failed — momkene yek repo third-party kharab bashe (mesle ookla/speedtest)"
    local f base disabled=0
    for f in /etc/apt/sources.list.d/*.list; do
        [[ -f "$f" ]] || continue
        base="$(basename "$f")"
        case "$base" in
            *.disabled-by-pnv-install) continue ;;
        esac
        if grep -qiE 'packagecloud|ookla|speedtest' "$f" 2>/dev/null; then
            mv "$f" "${f}.disabled-by-pnv-install" && disabled=1
            say "   disabled: $base"
        fi
    done

    if [[ "$disabled" == "1" ]] && apt-get update -qq 2>/dev/null; then
        say ">> apt update OK (bad repo ha disable shodan)"
        return 0
    fi

    say "!! Hanooz apt update error dare. Ino dasti fix kon:"
    say "   ls /etc/apt/sources.list.d/"
    say "   sudo mv /etc/apt/sources.list.d/ookla*.list /tmp/  # ya file kharab"
    say "   sudo apt-get update"
    return 1
}

ask(){
    local prompt="$1"
    local default="${2:-}"
    local reply=""
    if [[ "$INTERACTIVE" != "1" ]]; then
        echo "$default"
        return
    fi
    if [[ -n "$default" ]]; then
        read -r -p "$prompt [$default]: " reply || true
        echo "${reply:-$default}"
    else
        read -r -p "$prompt: " reply || true
        echo "$reply"
    fi
}

usage(){
    cat <<'EOF'
PNV Panel installer

  --yes              Non-interactive (use defaults / env)
  --web nginx|apache Web server (default: nginx)
  --domain NAME      Panel domain (for SSL)
  --email ADDR       Email for Let's Encrypt
  --version TAG      Release tag (default: v02.01.01)
  --root PATH        Install path (default: /var/www/pnv-panel)
  --import PATH      Restore ZIP after install
  --repo owner/name  GitHub repo
  --skip-apt         Skip apt packages
  --no-backup        Skip backup of existing root
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes|-y) INTERACTIVE=0; shift ;;
        --web) WEB="$2"; shift 2 ;;
        --domain) DOMAIN="$2"; shift 2 ;;
        --email) EMAIL="$2"; shift 2 ;;
        --version) VERSION="$2"; REF="$2"; shift 2 ;;
        --root) ROOT="$2"; shift 2 ;;
        --import) IMPORT_ZIP="$2"; shift 2 ;;
        --repo) REPO="$2"; shift 2 ;;
        --skip-apt) SKIP_APT=1; shift ;;
        --no-backup) BACKUP=0; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown: $1"; usage; exit 1 ;;
    esac
done

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Lotfan ba root ejra kon: sudo bash ..." >&2
    exit 1
fi

if [[ "$INTERACTIVE" == "1" ]]; then
    say "\n=== PNV Panel Installer v02.01.01 ===\n"
    WEB="$(ask "Web server? (nginx/apache)" "$WEB")"
    VERSION="$(ask "Version/tag" "$VERSION")"
    REF="$VERSION"
    ROOT="$(ask "Install path" "$ROOT")"
    DOMAIN="$(ask "Domain panel (mesle panel.example.com, khali = bedune SSL)" "$DOMAIN")"
    if [[ -n "$DOMAIN" && -z "$EMAIL" ]]; then
        EMAIL="$(ask "Email baraye SSL (Let's Encrypt)" "")"
    fi
    IMPORT_ZIP="$(ask "Backup ZIP baraye import? (khali = skip)" "$IMPORT_ZIP")"
fi

WEB="$(echo "$WEB" | tr '[:upper:]' '[:lower:]')"

if ! command -v curl >/dev/null 2>&1; then
    apt_update_safe || exit 1
    apt-get install -y curl ca-certificates
fi

say "\n>> Repo: $REPO | Version: $REF | Web: $WEB | Root: $ROOT"

if [[ "$SKIP_APT" != "1" ]]; then
    say ">> Installing packages..."
    export DEBIAN_FRONTEND=noninteractive
    apt_update_safe || exit 1
    if [[ "$WEB" == "nginx" ]]; then
        apt-get install -y nginx php-fpm php-cli php-curl php-gd php-mbstring php-xml php-zip unzip curl ca-certificates rsync
        if [[ -z "$PHP_FPM_SOCK" ]]; then
            PHP_FPM_SOCK="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' 2>/dev/null | sort -V | tail -1 || true)"
        fi
    else
        apt-get install -y apache2 libapache2-mod-php php php-cli php-curl php-gd php-mbstring php-xml php-zip unzip curl ca-certificates rsync
        a2enmod rewrite headers ssl 2>/dev/null || true
    fi
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

ARCHIVE_URL="https://github.com/${REPO}/archive/refs/tags/${REF}.tar.gz"
BRANCH_URL="https://github.com/${REPO}/archive/refs/heads/${REF}.tar.gz"

say ">> Downloading source..."
if curl -fsSL "$ARCHIVE_URL" -o "$TMP/src.tar.gz" 2>/dev/null; then
    :
elif curl -fsSL "$BRANCH_URL" -o "$TMP/src.tar.gz" 2>/dev/null; then
    :
else
    echo "Download failed for: $REF" >&2
    exit 1
fi

tar -xzf "$TMP/src.tar.gz" -C "$TMP"
SRC_DIR="$(find "$TMP" -maxdepth 1 -type d -name 'pnv-panel-*' | head -1)"
[[ -d "$SRC_DIR" ]] || { echo "Source dir not found"; exit 1; }

if [[ -d "$ROOT" && "$BACKUP" == "1" && "$(ls -A "$ROOT" 2>/dev/null || true)" != "" ]]; then
    BK="${ROOT}.bak.$(date +%Y%m%d-%H%M%S)"
    say ">> Backup existing -> $BK"
    cp -a "$ROOT" "$BK"
fi

mkdir -p "$ROOT"
say ">> Deploying files..."
rsync -a --delete \
    --exclude 'db/bale.json' \
    --exclude 'db/telegram.json' \
    --exclude 'db/xui_servers.json' \
    --exclude 'db/xui_state.json' \
    --exclude 'db/instant_payments.json' \
    --exclude 'db/sms.json' \
    --exclude 'invoices/payments.csv' \
    --exclude '.git' \
    "$SRC_DIR/" "$ROOT/"

mkdir -p "$ROOT/db/backups" "$ROOT/db" "$ROOT/invoices" "$ROOT/uploads/support" "$ROOT/uploads/avatars" "$ROOT/temp/import-preview"
touch "$ROOT/invoices/payments.csv"

for name in support users admins plans discount_codes discount_code_usages dashboard_announcements dashboard_announcement_reads cleared_subscriptions renews; do
    f="$ROOT/db/${name}.json"
    [[ -f "$f" ]] || echo '[]' > "$f"
done

[[ -f "$ROOT/db/xui_servers.json" ]] || [[ ! -f "$ROOT/db/xui_servers.example.json" ]] || cp "$ROOT/db/xui_servers.example.json" "$ROOT/db/xui_servers.json"

if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "$ROOT/db" "$ROOT/invoices" "$ROOT/uploads" "$ROOT/temp" 2>/dev/null || true
    chmod -R 775 "$ROOT/db" "$ROOT/uploads" "$ROOT/temp" 2>/dev/null || true
fi

if [[ "$WEB" == "nginx" ]]; then
    say ">> Configuring Nginx..."
    [[ -n "$PHP_FPM_SOCK" ]] || PHP_FPM_SOCK="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' 2>/dev/null | sort -V | tail -1 || true)"
    [[ -n "$PHP_FPM_SOCK" ]] || { echo "PHP-FPM socket peida nashod"; exit 1; }

    SITE="/etc/nginx/sites-available/pnv-panel.conf"
    if [[ -n "$DOMAIN" ]]; then
        cp "$ROOT/scripts/nginx-pnv-panel.conf.example" "$SITE"
        sed -i "s|PNV_DOMAIN|$DOMAIN|g; s|PNV_ROOT|$ROOT|g; s|PNV_PHP_FPM_SOCK|$PHP_FPM_SOCK|g" "$SITE"
    else
        cat > "$SITE" <<NGX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root $ROOT;
    index index.php;
    client_max_body_size 32m;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:$PHP_FPM_SOCK; }
}
NGX
    fi
    ln -sf "$SITE" /etc/nginx/sites-enabled/pnv-panel.conf
    rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
    nginx -t
    systemctl enable nginx php*-fpm 2>/dev/null || true
    systemctl reload nginx || systemctl restart nginx
else
    say ">> Configuring Apache..."
    APACHE_SITE="/etc/apache2/sites-available/pnv-panel.conf"
    cat > "$APACHE_SITE" <<APX
<VirtualHost *:80>
    ServerName ${DOMAIN:-localhost}
    DocumentRoot $ROOT
    <Directory $ROOT>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
APX
    a2ensite pnv-panel.conf 2>/dev/null || true
    a2dissite 000-default.conf 2>/dev/null || true
    systemctl reload apache2 || systemctl restart apache2 || true
fi

if [[ -n "$DOMAIN" && -n "$EMAIL" ]]; then
    say ">> SSL (Let's Encrypt)..."
    apt-get install -y certbot 2>/dev/null || true
    if [[ "$WEB" == "nginx" ]]; then
        apt-get install -y python3-certbot-nginx 2>/dev/null || true
        certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect || say "!! Certbot failed — SSL ro dasti setup kon"
    else
        apt-get install -y python3-certbot-apache 2>/dev/null || true
        certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect || say "!! Certbot failed"
    fi
fi

if [[ -n "$IMPORT_ZIP" && -f "$IMPORT_ZIP" ]]; then
    say ">> Import backup (full)..."
    php -r "
require '$ROOT/backup_lib.php';
\$r = pnvBackupImportZip('$IMPORT_ZIP');
if (empty(\$r['ok'])) { fwrite(STDERR, \$r['error'] ?? 'import failed'); exit(1); }
echo 'imported ' . (int)(\$r['count'] ?? 0) . \" files\n\";
" || say "!! Import failed — az admin/backup.php ham mitoni import koni"
fi

say "\n=== Done ==="
if [[ -n "$DOMAIN" ]]; then
    say "Panel URL: https://$DOMAIN/"
else
    say "Panel path: $ROOT"
    say "Ba IP server va port 80 test kon."
fi
say "Admin: /bigjay_controller/"
say "Backup/Import: admin → Backup"
say "\nUpgrade:"
say "  bash <(curl -Ls https://raw.githubusercontent.com/${REPO}/v02.01.01/scripts/install.sh) --yes --version v02.01.01 --root $ROOT --web $WEB"

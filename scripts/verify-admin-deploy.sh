#!/usr/bin/env bash
# Pre/post deploy verification for server3 (no UI changes).
# Exits 0 unless a critical failure occurs (missing ROOT, cannot write report).
# Optional checks (no log matches, missing markers) only add to "Remaining issue".
#
# Usage:
#   ROOT=/var/www/html SRC=/opt/pnv-panel bash scripts/verify-admin-deploy.sh
#   PHASE=before|after (default: after)
set -euo pipefail

ROOT="${ROOT:-/var/www/html}"
SRC="${SRC:-/opt/pnv-panel}"
REF="${REF:-origin/cursor/restore-admin-ui-cbea}"
SUPPORT_UI_REF="${SUPPORT_UI_REF:-71dce0d4186e2a16ddccec812b5ab54c9ed8cf93}"
DOMAIN="${DOMAIN:-panel.ticketin.ir}"
PHASE="${PHASE:-after}"
WEB_USER="${WEB_USER:-www-data}"

REPORT="${REPORT:-}"

branch="unknown"
commit="unknown"
nginx_root="unknown"
support_ui_ver="unknown"
payments_tabs="no"
renewals_tabs="no"
css_http="unknown"
js_http="unknown"
php_errors="none scanned"
nginx_errors="none scanned"
files_restored="n/a"
remaining="none"
php_fpm_user="$WEB_USER"
php_fpm_group="$WEB_USER"
db_support_mode="n/a"
db_locks_mode="n/a"
db_support_owner="n/a"
db_locks_owner="n/a"

note(){ remaining="${remaining:+$remaining; }$1"; }

log_has_runtime_errors() {
  local blob="$1"
  [[ -z "$blob" || "$blob" == "none recent" || "$blob" == "none scanned" ]] && return 1
  echo "$blob" | grep -qiE 'Permission denied|Fatal|headers already sent'
}

# grep pipeline safe under pipefail (no match => empty, not exit)
log_grep_tail() {
  local file="$1" pattern="$2" lines="${3:-5}"
  [[ -r "$file" ]] || return 0
  {
    grep -E "$pattern" "$file" 2>/dev/null | tail -n "$lines" | tr '\n' ' ' || true
  }
}

# --- 1) Git clone ---
if [[ -d "$SRC/.git" ]]; then
  branch="$(git -C "$SRC" branch --show-current 2>/dev/null || echo detached)"
  commit="$(git -C "$SRC" rev-parse --short HEAD 2>/dev/null || echo '?')"
  git_status="$(git -C "$SRC" status --short 2>/dev/null | head -20 || true)"
else
  note "SRC not a git repo: $SRC"
  git_status=""
fi

# --- 2) File markers on disk ---
check_marker() {
  local file="$1" marker="$2"
  [[ -f "$ROOT/$file" ]] && grep -q "$marker" "$ROOT/$file" 2>/dev/null || return 1
  return 0
}

if check_marker admin/payments.php "payViewTabs"; then payments_tabs="yes"; else note "payments.php missing payViewTabs"; fi
if check_marker admin/renews.php "renewViewTabs"; then renewals_tabs="yes"; else note "renews.php missing renewViewTabs"; fi

support_ok="no"
if check_marker admin/support.php "msgSidebar" \
  && check_marker admin/support.php "supportMessages" \
  && check_marker admin/support.php "supportSendbox"; then
  support_ok="yes (msgSidebar, supportMessages, supportSendbox)"
else
  note "support.php missing messenger markers"
fi

# Compare checksums to git (if refs exist)
file_match() {
  local ref="$1" path="$2"
  [[ -f "$ROOT/$path" ]] || return 1
  git -C "$SRC" rev-parse --verify "$ref" >/dev/null 2>&1 || return 2
  local a b
  a="$(sha256sum "$ROOT/$path" | awk '{print $1}')"
  b="$(git -C "$SRC" show "$ref:$path" 2>/dev/null | sha256sum | awk '{print $1}')"
  [[ "$a" == "$b" ]]
}

if [[ -d "$SRC/.git" ]]; then
  if file_match "$SUPPORT_UI_REF" support_ui.css && file_match "$SUPPORT_UI_REF" support_ui.js; then
    support_ui_ver="$(git -C "$SRC" rev-parse --short "$SUPPORT_UI_REF") (css/js match)"
  elif file_match "$REF" support_ui.css; then
    support_ui_ver="$(git -C "$SRC" rev-parse --short "$REF") (css/js from REF)"
  else
    support_ui_ver="on-disk (no git match)"
    note "support_ui.css/js differ from $SUPPORT_UI_REF and $REF"
  fi
fi

# --- 3) nginx root / symlinks / duplicates ---
if [[ -d /etc/nginx ]]; then
  nginx_root="$(grep -r "^\s*root\s" /etc/nginx/sites-enabled/ 2>/dev/null | grep -v "#" | head -1 | awk '{print $2}' | tr -d ';' || true)"
  [[ -z "$nginx_root" ]] && nginx_root="(not found in sites-enabled)"
fi

root_real="$(readlink -f "$ROOT" 2>/dev/null || echo "$ROOT")"
nginx_root_real="$nginx_root"
if [[ -d "$nginx_root" ]]; then
  nginx_root_real="$(readlink -f "$nginx_root" 2>/dev/null || echo "$nginx_root")"
fi
if [[ "$root_real" != "$nginx_root_real" && "$nginx_root" != "(not found in sites-enabled)" ]]; then
  note "ROOT=$ROOT != nginx root=$nginx_root"
fi

for dup in /var/www/pnv-panel /var/www/html/panel /opt/pnv-panel/public; do
  [[ -d "$dup" && "$dup" != "$ROOT" ]] && note "other install dir exists: $dup"
done

if [[ -L "$ROOT" ]]; then
  note "ROOT is symlink -> $(readlink "$ROOT" 2>/dev/null || true)"
fi

# --- 4) Asset HTTP + path in HTML ---
css_path="../support_ui.css"
js_path="../support_ui.js"
css_href=""
js_href=""
if [[ -f "$ROOT/admin/support.php" ]]; then
  css_href="$(grep -oE 'support_ui\.css[^"\047]*' "$ROOT/admin/support.php" 2>/dev/null | head -1 || true)"
  js_href="$(grep -oE 'support_ui\.js[^"\047]*' "$ROOT/admin/support.php" 2>/dev/null | head -1 || true)"
  [[ -n "$css_href" ]] && css_path="${css_href%%\?*}"
  [[ -n "$js_href" ]] && js_path="${js_href%%\?*}"
fi

resolve_asset() {
  local rel="$1"
  if [[ "$rel" == ../* ]]; then
    echo "$ROOT/${rel#../}"
  else
    echo "$ROOT/$rel"
  fi
}

css_file="$(resolve_asset "$css_path")"
js_file="$(resolve_asset "$js_path")"

if command -v curl >/dev/null 2>&1; then
  css_url="/${css_path#../}"
  js_url="/${js_path#../}"
  css_http="$(curl -s -o /dev/null -w '%{http_code}' -k -H "Host: $DOMAIN" "https://127.0.0.1${css_url}" 2>/dev/null || echo err)"
  js_http="$(curl -s -o /dev/null -w '%{http_code}' -k -H "Host: $DOMAIN" "https://127.0.0.1${js_url}" 2>/dev/null || echo err)"
  [[ ! -f "$css_file" ]] && note "css file missing on disk: $css_file"
  [[ ! -f "$js_file" ]] && note "js file missing on disk: $js_file"
else
  css_http="curl missing"
  js_http="curl missing"
fi

# --- PHP-FPM pool user (when config exists) ---
for pool in /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.2/fpm/pool.d/www.conf; do
  if [[ -r "$pool" ]]; then
    u="$(grep -E '^\s*user\s*=' "$pool" 2>/dev/null | tail -1 | sed -E 's/.*=\s*//; s/\s//g' || true)"
    g="$(grep -E '^\s*group\s*=' "$pool" 2>/dev/null | tail -1 | sed -E 's/.*=\s*//; s/\s//g' || true)"
    [[ -n "$u" ]] && php_fpm_user="$u"
    [[ -n "$g" ]] && php_fpm_group="$g"
    break
  fi
done
[[ "$php_fpm_user" != "$WEB_USER" ]] && note "WEB_USER=$WEB_USER but php-fpm pool user=$php_fpm_user"

# --- 5) Permissions ---
stat_path() {
  local p="$1"
  [[ -e "$p" ]] || return 0
  stat -c '%U:%G %a' "$p" 2>/dev/null || stat -f '%Su:%Sg %OLp' "$p" 2>/dev/null || true
}

if [[ -e "$ROOT/db/support.json" ]]; then
  db_support_owner="$(stat_path "$ROOT/db/support.json")"
  db_support_mode="$(stat -c '%a' "$ROOT/db/support.json" 2>/dev/null || echo '?')"
fi
if [[ -d "$ROOT/db/locks" ]]; then
  db_locks_owner="$(stat_path "$ROOT/db/locks")"
  db_locks_mode="$(stat -c '%a' "$ROOT/db/locks" 2>/dev/null || echo '?')"
fi

runtime_write_user="${php_fpm_user:-$WEB_USER}"
if id "$runtime_write_user" >/dev/null 2>&1; then
  for w in db db/support.json db/locks uploads/support; do
    [[ -e "$ROOT/$w" ]] || continue
    if command -v sudo >/dev/null 2>&1; then
      sudo -u "$runtime_write_user" test -w "$ROOT/$w" 2>/dev/null || note "$runtime_write_user cannot write $w"
    elif ! su -s /bin/sh "$runtime_write_user" -c "test -w '$ROOT/$w'" 2>/dev/null; then
      note "$runtime_write_user cannot write $w"
    fi
  done
fi

if id "$WEB_USER" >/dev/null 2>&1; then
  for f in admin/support.php support_ui.css support_ui.js admin/payments.php admin/renews.php bigjay_controller/index.php; do
    if [[ -f "$ROOT/$f" ]]; then
      if command -v sudo >/dev/null 2>&1; then
        sudo -u "$WEB_USER" test -r "$ROOT/$f" 2>/dev/null || note "$WEB_USER cannot read $f"
      elif ! su -s /bin/sh "$WEB_USER" -c "test -r '$ROOT/$f'" 2>/dev/null; then
        note "$WEB_USER cannot read $f"
      fi
    fi
  done
else
  note "user $WEB_USER not found"
fi

# --- 6) PHP syntax ---
if command -v php >/dev/null 2>&1; then
  pe=""
  for f in support_lib.php admin/support.php admin/support-api.php admin/index.php admin/payments.php admin/renews.php instant_pay_lib.php plan_ui_lib.php; do
    [[ -f "$ROOT/$f" ]] || continue
    if ! out="$(php -l "$ROOT/$f" 2>&1)"; then
      pe="${pe}${f}: ${out}; "
    fi
  done
  [[ -n "$pe" ]] && php_errors="$pe" || php_errors="none"
else
  php_errors="php CLI not installed"
fi

# --- 7) Recent logs — Permission denied / Fatal / headers already sent ---
LOG_ERR_PATTERN='Permission denied|Fatal|headers already sent|support|bigjay'

if [[ -r /var/log/nginx/error.log ]]; then
  nginx_tail="$(log_grep_tail /var/log/nginx/error.log "$LOG_ERR_PATTERN" 8)"
  [[ -n "$nginx_tail" ]] && nginx_errors="$nginx_tail" || nginx_errors="none recent"
  log_has_runtime_errors "$nginx_errors" && note "nginx log: Permission denied / Fatal / headers already sent"
fi

for log in /var/log/php8.3-fpm.log /var/log/php8.2-fpm.log; do
  fpm_tail="$(log_grep_tail "$log" "$LOG_ERR_PATTERN" 5)"
  if [[ -n "$fpm_tail" ]]; then
    php_errors="${php_errors}; fpm: $fpm_tail"
    log_has_runtime_errors "$fpm_tail" && note "php-fpm log: Permission denied / Fatal / headers already sent"
  fi
done

# Manifest from last restore
if [[ -f "$ROOT/.pnv-admin-ui-manifest.txt" ]]; then
  files_restored="$(head -20 "$ROOT/.pnv-admin-ui-manifest.txt" | tr '\n' '; ' || true)"
fi

# --- Report ---
[[ -d "$ROOT" ]] || { echo "CRITICAL: ROOT missing: $ROOT" >&2; exit 1; }

output() {
  cat <<EOF
=== PNV deploy verify ($PHASE) ===
Current branch: $branch
Current commit: $commit
Git status:
${git_status:-(clean or n/a)}

Nginx root: $nginx_root
Deploy ROOT: $ROOT ($root_real)
PHP-FPM user/group: $php_fpm_user / $php_fpm_group
db/support.json owner/mode: $db_support_owner (mode $db_support_mode)
db/locks owner/mode: $db_locks_owner (mode $db_locks_mode)
Support UI markers: $support_ok
Support UI version: $support_ui_ver
Payments tabs: $payments_tabs
Renewals tabs: $renewals_tabs
CSS href in support.php: ${css_href:-../support_ui.css}
CSS on disk: $css_file
CSS HTTP status (local HTTPS): $css_http
JS href: ${js_href:-../support_ui.js}
JS on disk: $js_file
JS HTTP status (local HTTPS): $js_http
PHP syntax: $php_errors
Nginx errors (tail): $nginx_errors
Files restored (manifest): $files_restored
Remaining issue: $remaining
EOF
}

output | tee "${REPORT:-/dev/stdout}"
exit 0

#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PKG="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
APP="${1:-/var/www/html}"
command -v php >/dev/null
[[ -f "$APP/dashboard.php" && -f "$APP/campaign_lib.php" && -f "$APP/announcement-api.php" ]]
[[ ! -L "$APP/dashboard.php" && ! -L "$APP/pnv_dashboard_announcements.php" ]]
grep -q 'function campaignUserAnnouncements(' "$APP/campaign_lib.php"
exec 9>"$APP/.pnv-announcement-deploy.lock"
flock -n 9 || { echo 'STOP: another deployment is running'; exit 1; }
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
cp -p "$APP/dashboard.php" "$STAGE/before.php"
php "$PKG/scripts/patch-announcement-dashboard.php" "$STAGE/before.php" "$STAGE/dashboard.php"
php -l "$STAGE/dashboard.php"
php -l "$PKG/pnv_dashboard_announcements.php"
if [[ -f "$APP/pnv_dashboard_announcements.php" ]] && ! cmp -s "$APP/pnv_dashboard_announcements.php" "$PKG/pnv_dashboard_announcements.php"; then
    [[ "$(sha256sum "$APP/pnv_dashboard_announcements.php" | cut -d' ' -f1)" == "27e596fd1ef834b57bcea28bf7950d1c7e3983c5a2d5b3e2d130d07a32861582" ]] || { echo 'STOP: component differs from reviewed version; no files changed.'; exit 1; }
fi
if cmp -s "$STAGE/dashboard.php" "$APP/dashboard.php" && cmp -s "$APP/pnv_dashboard_announcements.php" "$PKG/pnv_dashboard_announcements.php"; then
    echo 'Already installed.'; exit 0
fi
SERVICE=''
for candidate in php8.3-fpm apache2; do
    if systemctl is-active --quiet "$candidate"; then SERVICE="$candidate"; break; fi
done
[[ -n "$SERVICE" ]] || { echo 'STOP: PHP service not identified; no files changed.'; exit 1; }
BACKUP="$(mktemp -d "${PNV_BACKUP_BASE:-/root}/pnv-announcement-backup-XXXXXXXX")"
cp -p "$APP/dashboard.php" "$BACKUP/dashboard.php"
if [[ -f "$APP/pnv_dashboard_announcements.php" ]]; then
    cp -p "$APP/pnv_dashboard_announcements.php" "$BACKUP/pnv_dashboard_announcements.php"
fi
rollback(){
    trap - ERR INT TERM
    set +e
    cp -p "$BACKUP/dashboard.php" "$APP/dashboard.php"
    if [[ -f "$BACKUP/pnv_dashboard_announcements.php" ]]; then
        cp -p "$BACKUP/pnv_dashboard_announcements.php" "$APP/pnv_dashboard_announcements.php"
    else rm -f -- "$APP/pnv_dashboard_announcements.php"; fi
    systemctl reload "$SERVICE"
    echo "Deployment failed; previous files restored. Backup: $BACKUP"
    exit 1
}
# Refuse to overwrite a dashboard edited while this script was preparing.
cmp -s "$APP/dashboard.php" "$STAGE/before.php" || { echo 'STOP: dashboard changed during preparation'; exit 1; }
trap rollback ERR INT TERM
for name in pnv_dashboard_announcements.php dashboard.php; do
    src="$PKG/$name"
    if [[ "$name" == dashboard.php ]]; then src="$STAGE/$name"; fi
    tmp="$(mktemp "$APP/.pnv-notice-XXXXXXXX")"
    cp -p "$APP/dashboard.php" "$tmp"
    cat "$src" > "$tmp"
    mv -f -- "$tmp" "$APP/$name"
done
systemctl reload "$SERVICE"
trap - ERR INT TERM
echo 'Dashboard announcement modal installed.'
echo "Backup: $BACKUP"

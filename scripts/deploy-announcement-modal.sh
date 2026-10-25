#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PKG="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
APP="${1:-/var/www/html}"
command -v php >/dev/null
FILES=(announcement_display_lib.php admin/campaign-announcements.php pnv_dashboard_announcements.php dashboard.php)
[[ -f "$APP/dashboard.php" && -f "$APP/campaign_lib.php" && -f "$APP/admin/campaign-announcements.php" ]]
grep -q 'function campaignUserAnnouncements(' "$APP/campaign_lib.php"
exec 9>"$APP/.pnv-announcement-deploy.lock"
flock -n 9 || { echo 'STOP: another deployment is running'; exit 1; }
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/before/admin" "$STAGE/after/admin"
for name in "${FILES[@]}"; do
    [[ ! -L "$APP/$name" ]] || { echo "STOP: symlink $name"; exit 1; }
    if [[ -f "$APP/$name" ]]; then cp -p "$APP/$name" "$STAGE/before/$name"; else touch "$STAGE/before/$name.absent"; fi
done
php "$PKG/scripts/patch-announcement-dashboard.php" "$STAGE/before/dashboard.php" "$STAGE/after/dashboard.php"
php "$PKG/scripts/patch-announcement-admin.php" "$STAGE/before/admin/campaign-announcements.php" "$STAGE/after/admin/campaign-announcements.php"
for name in announcement_display_lib.php pnv_dashboard_announcements.php; do
    cp "$PKG/$name" "$STAGE/after/$name"
    if [[ -f "$APP/$name" ]] && ! cmp -s "$APP/$name" "$PKG/$name"; then
        hash="$(sha256sum "$APP/$name" | cut -d' ' -f1)"
        if [[ "$name" != pnv_dashboard_announcements.php ]] || [[ "$hash" != 27e596fd1ef834b57bcea28bf7950d1c7e3983c5a2d5b3e2d130d07a32861582 && "$hash" != bfdeb54166bfee154001d46bb1841a5dfe201057176fd31bb50b544eaf84f312 ]]; then
            echo "STOP: $name differs from reviewed versions; no files changed."; exit 1
        fi
    fi
done
changed=0
for name in "${FILES[@]}"; do
    php -l "$STAGE/after/$name"
    if ! cmp -s "$APP/$name" "$STAGE/after/$name"; then changed=1; fi
done
if [[ "$changed" == 0 ]]; then echo 'Already installed.'; exit 0; fi
SERVICE=''
for candidate in php8.3-fpm apache2; do
    if systemctl is-active --quiet "$candidate"; then SERVICE="$candidate"; break; fi
done
[[ -n "$SERVICE" ]] || { echo 'STOP: PHP service not identified; no files changed.'; exit 1; }
BACKUP="$(mktemp -d "${PNV_BACKUP_BASE:-/root}/pnv-announcement-backup-XXXXXXXX")"
cp -a "$STAGE/before/." "$BACKUP/"
for name in "${FILES[@]}"; do
    if [[ -f "$STAGE/before/$name.absent" ]]; then
        [[ ! -e "$APP/$name" ]] || { echo "STOP: $name appeared during preparation"; exit 1; }
    else cmp -s "$APP/$name" "$STAGE/before/$name" || { echo "STOP: $name changed during preparation"; exit 1; }; fi
done
rollback(){
    trap - ERR INT TERM
    set +e
    for name in dashboard.php pnv_dashboard_announcements.php admin/campaign-announcements.php announcement_display_lib.php; do
        if [[ -f "$BACKUP/$name.absent" ]]; then rm -f -- "$APP/$name"; else cp -p "$BACKUP/$name" "$APP/$name"; fi
    done
    systemctl reload "$SERVICE"
    echo "Deployment failed; previous files restored. Backup: $BACKUP"
    exit 1
}
trap rollback ERR INT TERM
for name in "${FILES[@]}"; do
    tmp="$(mktemp "$APP/$(dirname "$name")/.pnv-notice-XXXXXXXX")"
    ref="$APP/dashboard.php"
    if [[ -f "$APP/$name" ]]; then ref="$APP/$name"; fi
    cp -p "$ref" "$tmp"
    cat "$STAGE/after/$name" > "$tmp"
    mv -f -- "$tmp" "$APP/$name"
done
systemctl reload "$SERVICE"
trap - ERR INT TERM
echo 'Announcement display limits and modal list installed.'
echo "Backup: $BACKUP"

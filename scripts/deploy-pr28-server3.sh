#!/usr/bin/env bash
# Production-specific PR28 deployment for server3. Requires explicit authorization.
set -Eeuo pipefail
umask 077

BASE=67b17c138aafc4ed4d8744e8f570bb60819c7c26
STAGE=/opt/pnv-panel-staging
PROD=/var/www/html
EXPECTED="${1:-}"
MODE="${2:-check}"

if [[ $EUID != 0 ]]; then echo 'STOP: run as root' >&2; exit 1; fi
if [[ ! "$EXPECTED" =~ ^[a-f0-9]{40}$ ]]; then
  echo 'STOP: a pinned 40-character PR commit SHA is required' >&2; exit 1
fi
if [[ "$MODE" != check && "$MODE" != apply ]]; then
  echo 'Usage: bash script.sh <PR-commit> [check|apply]' >&2; exit 1
fi
for command_name in git php python3 tar cp cmp mv stat flock; do
  command -v "$command_name" >/dev/null || { echo "STOP: missing $command_name"; exit 1; }
done
exec 9>/run/lock/pnv-pr28-deploy.lock
flock -n 9 || { echo 'STOP: PR28 deployment already running'; exit 1; }
test -d "$STAGE/.git" || { echo 'STOP: staging repository missing'; exit 1; }
test -f "$PROD/instant_pay_lib.php" || { echo 'STOP: wrong production directory'; exit 1; }
[[ "$(git -C "$STAGE" rev-parse HEAD)" == "$BASE" ]] || {
  echo 'STOP: staging base has changed'; exit 1
}
origin=$(git -C "$STAGE" remote get-url origin)
[[ "$origin" == *'mr-BigJay/pnv-panel'* ]] || { echo 'STOP: unexpected origin'; exit 1; }

FILES=(instant_pay_lib.php instant-pay-api.php buy.php renew.php
       admin/payments.php admin/renews.php xui_lib.php)
for file in "${FILES[@]}"; do
  [[ -f "$PROD/$file" && ! -L "$PROD/$file" ]] || {
    echo "STOP: Production file missing or symlink: $file"; exit 1
  }
done

# Fetch into FETCH_HEAD only, without changing the existing dirty Staging.
git -C "$STAGE" fetch --no-tags origin refs/pull/28/head
actual=$(git -C "$STAGE" rev-parse FETCH_HEAD)
[[ "$actual" == "$EXPECTED" ]] || {
  echo "STOP: PR changed: expected $EXPECTED, found $actual"; exit 1
}
git -C "$STAGE" merge-base --is-ancestor "$BASE" "$EXPECTED" || {
  echo 'STOP: unexpected PR ancestry'; exit 1
}

WORK="/root/pnv-pr28-safe-$(date +%Y%m%d-%H%M%S)-$$"
mkdir -m 700 -p "$WORK"/{base,production,merged,backup/code}
printf 'base=%s\nhead=%s\n' "$BASE" "$EXPECTED" > "$WORK/manifest.txt"

APPLIED=()
on_exit() {
  rc=$?
  trap - EXIT
  if (( rc != 0 )) && (( ${#APPLIED[@]} > 0 )); then
    echo "ROLLBACK: restoring ${#APPLIED[@]} code files" >&2
    for file in "${APPLIED[@]}"; do
      restore="$PROD/$file.pnv-pr28-rollback-$$"
      cp -p "$WORK/backup/code/$file" "$restore"
      mv -f "$restore" "$PROD/$file"
    done
    systemctl reload php8.3-fpm || true
    echo 'Payment data has NOT been rolled back.' >&2
  fi
  if (( rc != 0 )); then echo "STOP: inspect $WORK" >&2; fi
  exit "$rc"
}
trap on_exit EXIT

git -C "$STAGE" worktree add --detach "$WORK/pr" "$EXPECTED"

for file in "${FILES[@]}"; do
  mkdir -p "$WORK/base/$(dirname "$file")" "$WORK/production/$(dirname "$file")" \
           "$WORK/merged/$(dirname "$file")" "$WORK/backup/code/$(dirname "$file")"
  git -C "$STAGE" show "$BASE:$file" > "$WORK/base/$file"
  cp -p "$PROD/$file" "$WORK/production/$file"
  cp -p "$PROD/$file" "$WORK/backup/code/$file"
  if cmp -s "$WORK/base/$file" "$WORK/production/$file"; then
    cp -p "$WORK/pr/$file" "$WORK/merged/$file"
    echo "DIRECT: $file"
  else
    rc=0
    git merge-file -p "$WORK/production/$file" "$WORK/base/$file" "$WORK/pr/$file" \
        > "$WORK/merged/$file" || rc=$?
    (( rc < 128 )) || { echo "STOP: invalid merge: $file"; exit 1; }
    echo "MERGE: $file (${rc} conflict groups)"
  fi
done

python3 "$WORK/pr/scripts/pnv_pr28_merge_production.py" \
    --merged "$WORK/merged" --output "$WORK/resolved"
for file in "${FILES[@]}"; do
  php -l "$WORK/resolved/$file"
done

# All regression tests operate on a private copy, never Production/staging.
mkdir -m 700 "$WORK/test-tree"
cp -a "$WORK/pr/." "$WORK/test-tree/"
for file in "${FILES[@]}"; do
  cp "$WORK/resolved/$file" "$WORK/test-tree/$file"
done
(
  cd "$WORK/test-tree"
  for f in tests/*_test.php; do
    echo "REGRESSION: $f"
    php "$f" > "$WORK/$(basename "$f").log" 2>&1 || {
      cat "$WORK/$(basename "$f").log"
      exit 1
    }
  done
)
echo 'All PHP regressions passed on the resolved Production code.'

python3 - "$WORK/resolved" <<'PY'
from pathlib import Path
import sys
p=Path(sys.argv[1])
for file in ('admin/payments.php','admin/renews.php'):
    code=(p/file).read_text()
    for status in ('approved','pending','expired','rejected','cancelled'):
        if "'"+status+"'" not in code:
            raise SystemExit('STOP: lost status tab '+file+' '+status)
    if "if($statusFilter !== 'cancelled')" not in code:
        raise SystemExit('STOP: archive action guard missing '+file)
print('All five admin status tabs present.')
PY

# Fail closed if Production code changed while integration was being tested.
for file in "${FILES[@]}"; do
  cmp -s "$WORK/backup/code/$file" "$PROD/$file" || {
    echo "STOP: Production changed during tests: $file"; exit 1
  }
done

if [[ "$MODE" == check ]]; then
  echo "CHECK COMPLETE. Production unchanged. Workdir: $WORK"
  exit 0
fi

# Runtime backup for manual recovery only: never restore live payment data.
RUNTIME=()
for file in db/instant_payments.json invoices/payments.csv db/renew_reservations.json; do
  [[ -f "$PROD/$file" ]] && RUNTIME+=("$file")
done
(( ${#RUNTIME[@]} >= 2 )) || { echo 'STOP: financial data files missing'; exit 1; }
tar -C "$PROD" -czf "$WORK/backup/runtime-snapshot.tar.gz" "${RUNTIME[@]}"

if [[ "${PNV_APPROVE_LIVE_DEPLOY:-}" != YES ]]; then
  echo "CHECKS PASSED. Live deployment not authorized. Workdir: $WORK"
  exit 0
fi

# Atomic replace per file, retaining owner and permissions. Roll back code only.
for file in "${FILES[@]}"; do
  cmp -s "$WORK/backup/code/$file" "$PROD/$file" || {
    echo "STOP: Production changed immediately before writing: $file"; exit 1
  }
  next="$PROD/$file.pnv-pr28-next-$$"
  cp "$WORK/resolved/$file" "$next"
  chown --reference="$PROD/$file" "$next"
  chmod --reference="$PROD/$file" "$next"
  mv -f "$next" "$PROD/$file"
  APPLIED+=("$file")
done
for file in "${FILES[@]}"; do php -l "$PROD/$file" >/dev/null; done
systemctl reload php8.3-fpm
systemctl is-active --quiet php8.3-fpm
systemctl is-active --quiet nginx
APPLIED=()
echo "DEPLOY SUCCESS: $EXPECTED"
echo "Backups: $WORK/backup"
echo 'Verify five tabs and a test purchase/renewal in the browser.'

# Reserved renew activation (cron)

## Run

Every minute (adjust path to your install):

```bash
* * * * * php /var/www/pnv-panel/scripts/renew_reserve_cron.php >> /var/log/pnv-renew-reserve.log 2>&1
```

Optional batch size:

```bash
PNV_RENEW_RESERVE_BATCH=20 php scripts/renew_reserve_cron.php
```

## Data

Reservations persist in `db/renew_reservations.json` (override in tests with `PNV_RENEW_RESERVE_PATH`).

## Rollback

1. Remove/disable the cron entry.
2. Existing reservations stay in JSON for manual review; no automatic deletion.
3. Revert application code to previous release if needed.

## Notes

- Worker uses file locks (`db/locks/renew_reserve_*.lock` and `renew_reserve_cron.lock`) to avoid duplicate activation.
- If XUI is unreachable, reservations remain `reserved` and are retried on the next run.

# Dashboard announcement modal and per-user display limits

Admin → Dashboard messages now has «تعداد نمایش خودکار برای هر کاربر». The default is 1; 0 means manual-only; the supported range is 0–1000.

Each dashboard response reserves at most one automatic display, using the active notices' existing priority order. A notice with a limit of 3 can open automatically on three separate page visits, rather than three times consecutively on the same page. Counts are per username and announcement ID, not per browser/device. Raising the limit allows additional displays; lowering it or editing text does not erase counts. Create a new notice for a fresh campaign. Legacy read acknowledgements count as one display.

All active notices remain as collapsed title buttons above the purchase/renewal grid, including notices whose quota has been exhausted. Clicking a title reopens its modal without incrementing the automatic count. The button is exactly «ادامه». Continue and Escape close the modal immediately; no repeated API request is needed. Inactive, future and expired notices are excluded by campaignUserAnnouncements.

Automatic counts are reserved while rendering the dashboard (not when pressing Continue). A response whose browser never renders JavaScript still consumes its reservation. Native dialog support and JavaScript are required for opening the modal. Counts are stored in db/dashboard_announcement_views.json under a shared lock, using an atomic replacement to prevent simultaneous tabs exceeding the limit. Include this file when backing up or migrating announcement data. If the count store is unavailable or invalid, automatic opening is suppressed and manual title buttons remain available.

## Deployment

From an extracted copy of this commit run:

```sh
sudo bash scripts/deploy-announcement-modal.sh /var/www/html
```

The installer supports the original dashboard, the delivered banner, and the first modal version. It patches the existing dashboard and admin template, preserving other local content. Unknown component implementations or template layouts cause a stop. It backs up every changed file, validates PHP, and restores prior files on reload failure. It does not replace payment libraries or modify payment/announcement records during installation.

After installation, refresh the admin and user pages. Set the per-user count in the create/edit announcement form. An existing notice defaults to one display. A prior reader will not get another automatic display until its limit is increased, but can always open the active notice manually.

## Validation

```sh
php tests/announcement_display_test.php
php tests/dashboard_announcements_test.php
php -l admin/campaign-announcements.php
bash -n scripts/deploy-announcement-modal.sh
```

Local validation also covers twelve concurrent requests competing for a limit of three, fresh/banner/modal upgrades, idempotent installation and rollback, and browser visits demonstrating automatic quota exhaustion with unlimited manual reopening and the exact Continue label. Production deployment is a separate step.

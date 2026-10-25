# Dashboard announcement modal

The user dashboard did not render the announcement records managed by the admin page. It now includes a server-rendered native dialog for each active unread notice, in the order supplied by campaignUserAnnouncements. The button label is exactly «ادامه».

Continue posts the notice ID to the existing authenticated announcement-api.php dismiss action, then opens the next unread notice or restores dashboard access. Escape closes without marking read. Failed acknowledgement does not trap the user: the dialog closes after a bounded request, and the notice may appear again on the next visit. Previously read notices remain suppressed. Editing an existing notice retains its existing read state; create a new notice when it must be shown again.

Long messages scroll inside the dialog while its footer remains visible. Content is HTML-escaped. Native dialog support and JavaScript are required.

## Deploy onto the existing production installation

From an extracted copy of this commit run:

```sh
sudo bash scripts/deploy-announcement-modal.sh /var/www/html
```

The installer supports both the original dashboard and the earlier delivered banner package. It preserves local dashboard content, inserts only the component include, validates PHP syntax, and backs up files before replacement. Unknown component content/layout causes a stop. Reload failure restores previous files. It does not replace payment libraries or modify announcement/payment data.

Do not replace the entire production directory with this repository: production contains other deployed changes. After installation refresh the dashboard, using an active unread announcement to verify the dialog and Continue button.

## Validation

```sh
php tests/dashboard_announcements_test.php
php -l dashboard.php
php -l pnv_dashboard_announcements.php
bash -n scripts/deploy-announcement-modal.sh
```

Additional local checks passed: upgrade from the delivered banner, fresh/idempotent installation, unknown-layout refusal, reload-failure rollback, and Chromium rendering/Continue/Escape at 360×640 and 1280×800. Production installation is performed separately.

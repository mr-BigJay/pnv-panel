# Bank Tejarat live login control (PR 46)

## Security and status

This code is **not evidence of a working bank connection**. It was not
executed against the user's live Tejarat account, and no OTP was handled.
Payment auto-approval is intentionally NOT enabled here.

The existing dashboard already encrypts account username/password outside the
webroot (Sodium). Step 2 now has **Start connection**, **Check session** and
**Refresh status**. These actions talk only to a Linux Unix socket restricted
to the PHP worker UID and the `www-data` group; the browser service runs as
`tejarat-browser`. Neither PHP nor the controller receives OTP codes.

Start connection checks the existing cookie state against the real authenticated
statement UI, and if necessary opens the official bank login URL in the
server's existing `:91` browser display. It attempts password autofill only
if there is precisely one regular visible username/password login form on the
official HTTPS host. The bank's `/register` page needs a human to complete
activation/SMS **on the bank website inside that server browser**.

- The browser's `XDG_CONFIG_HOME` is now writable in the worker session
  storage, fixing the Chromium Crashpad issue observed on this VPS.
- Session state is replaced atomically with mode `0600` only after the
  authenticated account statement UI is confirmed.
- The controller never assumes that a previous successful probe remains valid;
  an old success becomes `session_stale`.
- Empty statement reports are valid only when the authenticated account
  statement UI was actually seen. Without proven DOM selectors and account
  identity, the collector fails closed.
- There is no accessible direct SSH to server3 from this assistant. A person
  with authorized server access must review and install these files.

## Production preflight (no payment changes)

The existing `/opt/tejarat-browser-test` workspace and user
`tejarat-browser` were confirmed present by the owner. The separate
`tejarat-browser-worker-test.service` and
`pnv-tejarat-php-test.service` must NOT be replaced or overwritten.

From a checked-out reviewed revision of this PR, back up and selectively
deploy the following worker files to the matching location under
`/opt/tejarat-browser-test/services/tejarat-browser-worker/worker/`:

- `control_server.py` (new)
- `chromium_launch.py`
- `browser.py`
- `manual_login.py`

Before changing any service, check:

```bash
id -u www-data
id -u tejarat-browser
stat -c '%a %U %G' /opt/tejarat-browser-test/session
systemctl is-active pnv-tejarat-php-test.service
```

Run `python3 scripts/deploy-bank-connection-safe.py --source <checked-out-source>`
in dry-run before `--apply` for the dashboard, preserving the production
customized `admin/index.php`, payment code, subscriptions, XUI, renewal, and
bank credentials. The selective PHP deploy includes `bank_control_lib.php`.

Copy the reviewed sample
`services/tejarat-browser-worker/systemd/pnv-tejarat-control.service`
to `/etc/systemd/system/pnv-tejarat-control.service`, replacing
`PNV_TEJARAT_ALLOWED_UID=33` with the real `id -u www-data` value.
Systemd's `RuntimeDirectory=pnv-tejarat-control` must be owned by
`tejarat-browser:www-data` with mode `0770`. The service's Unix socket
is mode `0660`. Preserve `DISPLAY=:91` and **do not enable PrivateTmp**,
as the X11 socket is in `/tmp/.X11-unix`. Use only a secured display
connection (VNC via an SSH tunnel); never publish VNC to the open internet.

After `systemctl daemon-reload`, start the dedicated
`pnv-tejarat-control.service` and check its `ActiveState` without exposing
service EnvironmentFiles, cookies, credentials, or HTTP bank requests.

In the dashboard, go to **اتصال بانک → بانک تجارت → مرحله ۲**:
- Check session: never sends the bank password.
- Start connection: sends the already encrypted-at-rest password transiently
  to the local controller. It must not send it to the browser user's computer.
- Follow actual bank challenge through the server browser. When prompted to
  confirm account activation, enter SMS only on the official bank site.
- An authenticated session is *not* proof that the collector can reliably
  identify account credits.

## Statement extraction and finance safety

`worker/browser.py` requires `TEJARAT_STMT_SELECTORS_JSON` for the real
visible statement report. These selectors **remain unknown/unverified** for
the user's real bank version. Do not guess them or enable automatic activation
by fabricating a reference, source account, timestamp or amount. Multiple
bank accounts must be mapped to the correct destination card/account.

When available, prefer an authorized official read-only account statement API
or a bank export with authoritative transaction IDs.

Keep the panel in `dry_run=true` and manually confirm an authentic small
bank credit in a test order, including exact rial amount, destination,
reference, and banking timestamp, before considering live auto-approval.
Never enable a recurring live worker or the `enable_live` checkbox
before passing the integration test. Existing `run-worker.sh` was found on
server3; it is a **transaction collector**, not the login controller.

## Test suite

```bash
php tests/bank_connection_test.php
php tests/bank_setup_wizard_test.php
python3 tests/bank_connection_deploy_safety_test.py
cd services/tejarat-browser-worker
PYTHONPATH=. python3 -m unittest discover -s tests -p 'test_*.py' -v
```

The tests are isolated and do not connect to the live bank.

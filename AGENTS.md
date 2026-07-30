# pnv-panel

## Cursor Cloud specific instructions

### Stack
- Plain PHP application (PHP 8.3), server-rendered. No build step, no `composer.json`, and no `package.json` — there is nothing to compile or bundle.
- Data is stored as flat files under `db/`: JSON files (`users.json`, `plans.json`, `cards.json`, `admins.json`, `support.json`, ...), a SQLite database (`db/database.db`), and CSV files (`db/vip*.csv`, `invoices/payments.csv`). `phpqrcode/` is a bundled (vendored) QR library.
- Required PHP extensions (already installed in the VM snapshot): `sqlite3`, `gd` (QR PNG generation), `mbstring`, `curl`.

### Run (development)
- From the repo root: `php -S 0.0.0.0:8000 -t .`
- User panel: `http://localhost:8000/index.php` (login) and `register.php` (sign up). The login/register CAPTCHA is rendered in plaintext on the page, so registration/login can be fully automated in a browser.
- Admin panel: `http://localhost:8000/admin/index.php`. Admin credentials are hardcoded in `admin/index.php` (and mirrored in `db/admins.json`): user `BigJay`, password `603240@BigJayX`.

### Lint / test
- No automated test suite exists. Lint a file with `php -l <file>`.
- Pre-existing (not caused by env setup): `index_old.php` and `admin/downloads.php` have PHP parse errors on the base branch. `index_old.php` is an unused backup; do not treat these as regressions.

### Gotchas
- Runtime writes go to `temp/` (QR images), `uploads/support/` (support attachments), `down/` (uploaded software), and `db/`/`invoices/`. These dirs must exist and be writable; the update script recreates them.
- Manual/browser testing mutates tracked data files (e.g. registering a user rewrites `db/users.json`). Revert with `git checkout -- db/` before committing so test data is not committed.

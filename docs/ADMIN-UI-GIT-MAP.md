# Admin UI — Git map (restore reference)

## Support (Telegram / Messenger style)

| File | Last UI commit | Notes |
|------|----------------|--------|
| `support_ui.css` | `71dce0d` | Layout overlap fix; **no diff** vs current branch |
| `support_ui.js` | `71dce0d` | Same |
| `admin/support.php` | `71dce0d` markup | Branch adds: `supportGetUserProfileSummary`, POST `formAction`, username match, flash errors — **does not replace UI HTML** |
| `support_lib.php` | branch | Send/receive, tickets, CSRF |

Commit **`71dce0d4186e2a16ddccec812b5ab54c9ed8cf93`** — "Fix admin support message layout overlap".

## Admin payments / renews (3 tabs + card list)

| File | Commits |
|------|---------|
| `admin/payments.php` | `34f3569` card UI; **`b931fb8`** tabs: تایید شده / درحال بررسی / منقضی |
| `admin/renews.php` | same pattern |
| `instant_pay_lib.php` | **`instantPayAdminRowListBucket()`** required for tabs |

**`origin/main` does not include `payViewTabs`** (verified: 0 matches). Rollback to main removes tab UI.

## Deploy on server

```bash
cd /opt/pnv-panel
git pull origin cursor/fix-support-admin-send-cbea
sudo bash scripts/restore-admin-ui-snapshot.sh
```

Do not use `rollback-code-to-main.sh` if you need this UI.

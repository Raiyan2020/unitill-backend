# V2 Account Closure — Mobile Integration Guide

Two new endpoints split account closure into **Deactivate** (reversible) and
**Delete** (permanent).

**Nothing in V1 changed.** `DELETE /api/delete-account` still behaves exactly as
it always has — a reversible soft delete with its OTP step. Published builds keep
working untouched; adopt the endpoints below when you are ready.

---

## 1. Deactivate — reversible

```http
POST /api/v2/deactivate-account
Authorization: Bearer {token}
Accept: application/json
lang: en            // or "ar"
```

No request body.

**Response 200**

```json
{
    "status": true,
    "message": "Your account has been deactivated. Signing in again will restore it.",
    "data": {
        "deactivated_at": "2026-08-11T09:00:00+00:00",
        "restorable": true
    }
}
```

**What happens:** the account, its ads and its conversations are hidden
everywhere. All data stays in the database.

**How the user comes back:** normal sign-in with email + password. No special
endpoint, no extra flag — the existing login already restores the account, its
ads and its chats. Content the user had deleted themselves *before* deactivating
stays deleted.

**After the call:** all tokens are revoked. Clear local session state and send
the user to the login screen.

---

## 2. Delete — permanent

```http
DELETE /api/v2/delete-account
Authorization: Bearer {token}
Accept: application/json
lang: en            // or "ar"
```

No request body.

**Response 200**

```json
{
    "status": true,
    "message": "Your account and data have been permanently deleted. This cannot be undone.",
    "data": {
        "deleted_at": "2026-08-11T09:00:00+00:00",
        "restorable": false
    }
}
```

**This is irreversible.** Please put a clear confirmation screen in front of it —
the API performs the deletion as soon as it is called.

**Erased permanently**

- the user record itself (hard delete — the row is gone)
- their ads, including every uploaded photo removed from disk
- profile picture, saved devices, favourites, notifications
- support messages and trusted-seller applications
- ratings the account *received*
- all tokens and sessions

**Kept, with the link to the person removed (`NULL`)** — for legal and security
reasons:

| Record | Why it stays |
|---|---|
| Orders, coupon redemptions | Financial records (tax, accounting) |
| The other participant's conversations and messages | Their own record of a chat they did not close |
| Ad reports, chat reports | Safety and moderation |
| Ratings the user *left* on others | Another member's public score |
| Login logs | Security and fraud history |

So a buyer still sees the conversation and its messages; the sender simply
appears as an empty/unknown user. Handle a `null` sender or seller in chat and
order screens.

**After the call:** the token is dead immediately. Wipe all local data and return
to the launch/login screen — there is no account to go back to.

**Signing in again:** impossible. The account is not found, exactly as if it had
never existed. The email address is freed, so the same person may register a
brand-new account with it — it will be empty, with no previous history.

---

## Summary

| | Deactivate | Delete |
|---|---|---|
| Endpoint | `POST /api/v2/deactivate-account` | `DELETE /api/v2/delete-account` |
| Body | none | none |
| Reversible | Yes — just sign in | **No** |
| Data | Kept in full | Erased (except the records above) |
| Can log in after | Yes | No |
| `restorable` in response | `true` | `false` |

Both need a valid bearer token, and both revoke every session when they succeed.

## Public Google Play deletion request

A logged-out user can now request deletion at `{{web_url}}/delete-account`. Add this
URL to Google Play Console and expose it as an external-browser link in mobile
Settings. The public form creates a pending request; completing it from the admin
dashboard invokes the same permanent V2 purge behaviour described above.

---

## Postman

Both requests are in the **Profile & Account** folder of the collection, named
*Deactivate Account (V2)* and *Delete Account — Permanent (V2)*.

## Backend note

Deploy requires `php artisan migrate` — a migration converts the foreign keys
listed above from `CASCADE` to `SET NULL` so the retained records survive the
hard delete. It does not change any V1 behaviour.

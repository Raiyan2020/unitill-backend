# Backend updates for mobile — 2026-08-13

Everything below is on `feature/transactions-restructuring`. All of it is **additive** —
no existing V1 endpoint, request field, or response field was removed or renamed. Where
something is genuinely new behaviour on a V1 route (student re-verification blocking new
listings), it reuses an error shape the app already handles.

---

## TL;DR — what mobile has to do

| Area | App change needed? |
|---|---|
| Category listing prices | **No** — same fields, correct values now |
| Listing extension | **Yes, if** you want the extend flow — new V2 endpoint |
| "Start listing immediately" confirmation | **Yes, if** you want this UX — new V2 endpoints, optional |
| Cancellation / refund requests | **Yes** — new endpoints, no UI exists yet on your side |
| Report a user directly | **Yes, if** you want profile-level reporting (not just chat/ad) |
| Report priority | **No** — admin-side only |
| Moderation (warn/suspend/appeal) | **Yes** — suspended-account error now carries an appeal ID you need to keep |
| Student re-verification changes | **No new fields**, but one behaviour fix + one new restriction — read carefully |
| New-listing blocked for lapsed students | **Yes** — a 403 you may now see on `POST /ads`, `POST /ads/draft`, `POST /my-ads/{id}/sell-again` |

---

## 1. Category listing prices

No request/response shape changed. What changed is the actual price charged:
Standard listings **£0.99**, Cars and Accommodation **£2.99**, drafts still free (was a
flat £5 for everything before).

`GET /categories` main-category objects now also include:

```json
{ "id": 8, "name": "Cars", "listing_fee": 2.99, "formatted_listing_fee": "£2.99", ... }
```

Use this if you want to show the real price before someone starts posting instead of a
hardcoded value.

---

## 2. Listing extension — new V2 endpoint

`POST /v2/my-ads/{id}/extend` — extends an expired (or paused-past-expiry) listing for
another 30 days at a flat **£0.99**, regardless of category.

```json
// request (form-data)
{ "confirm_publish_immediately": true, "coupon_code": "OPTIONAL" }
```

422 if the ad isn't eligible (still published, or paused but still inside its paid
period — use the existing `POST /my-ads/{id}/activate` for that case, unchanged).
Response shape is the same `publication` block you already parse for payments
(`published`, `payment_required`, `amount`, `currency`, `payment_status`,
`payment_intent_id`, `client_secret`).

The old `POST /my-ads/{id}/activate` still works exactly as before and also handles
extension internally when the ad is expired — the new endpoint exists for a clean,
purpose-named action if/when you want to build a dedicated "Extend" button. No rush to
switch.

---

## 3. "Start my listing immediately" confirmation — new V2 endpoints

Per the compliance notes: before charging for a listing or an extension, the seller must
actively confirm they want it to go live immediately.

- `POST /v2/ads/{id}/publish` — same as the existing `POST /ads/{id}/publish`, but
  **requires** `confirm_publish_immediately: true` in the request. 422 if missing or not
  `true`.
- `POST /v2/my-ads/{id}/extend` — same requirement (see #2).

The existing V1 `POST /ads/{id}/publish` is **untouched** and takes no such field —
nothing breaks if you don't adopt this yet. When you do build the confirmation
checkbox/button, switch those two calls to the `/v2/` paths and start sending the field.

---

## 4. Cancellation / refund requests — new endpoints, no UI on your side yet

**User-facing:**

- `POST /my-ads/{id}/refund-request` — `{ "reason": "..." }`. 422 if the ad isn't
  paid, or a request already exists for it.
- `GET /my-ads/refund-requests` — the user's own request history, paginated:
  ```json
  { "id": 101, "public_id": "ABCD123", "title": "...", "listing_fee": 0.99,
    "currency": "GBP", "refund_status": "requested", "refund_requested_at": "...",
    "refund_request_reason": "...", "refund_reason": null, "refunded_at": null,
    "refund_declined_at": null }
  ```
  `refund_status` is one of `requested` / `refunded` / `declined` (or absent/null if
  never requested).

Every ad object from `MyAdResource` (the `my-ads` list/detail responses you already use)
now also carries a `refund_status` field — same values, `null` if nothing was ever
requested.

Admin reviews and accepts/declines from the dashboard; the user gets a push notification
(and an in-app notification row) either way — nothing you need to poll for beyond the
existing notifications endpoints, but you'll want a screen to show `refund_status` and,
ideally, let users submit a request from an ad's detail/management screen.

---

## 5. Report a user directly — new V2 endpoint

`POST /v2/users/{id}/reports` — `{ "reason": "...", "description": "optional" }`.
Same reason values as chat reports (`ChatReportReason`). 409 if you already have a
pending report against that user. This works **without an existing conversation** —
useful for a "Report this seller" button on a profile/listing page, not just inside chat.

---

## 6. Moderation & appeals — read this before you ship anything that touches account status

Admin can now warn, temporarily suspend (time-boxed), or permanently suspend an account,
in addition to the account states you already know. **Suspension reuses the existing
`status === '3'` / `account_disabled` login error** — no new status value, no new error
code. What's new: **that error now carries extra data you need to keep around:**

```json
{
  "status": false,
  "message": "Your account has been disabled",
  "data": {
    "moderation_action_id": 42,
    "moderation_reason": "Repeated scam reports",
    "suspended_until": "2026-09-15T00:00:00+00:00"
  }
}
```

`suspended_until` is `null` for a permanent suspension. **Keep `moderation_action_id`** —
it's required to submit an appeal:

`POST /v2/moderation-appeals` — `{ "email": "...", "password": "...", "moderation_action_id": 42, "message": "..." }`.
This re-authenticates with email/password (the user has no valid session at this point),
so it works from the same "account disabled" screen. 409 if an appeal already exists for
that decision — **one appeal per suspension**, so show the existing appeal's status
instead of letting them submit twice.

If a temporary suspension's `suspended_until` passes, the account reactivates
automatically on the next login attempt — no separate action needed.

**Backend TODO surfaced by this work, not yet done:** admin can currently warn/suspend/
reactivate an account, but cannot yet hide/remove a specific piece of content or restrict
a single feature (e.g. posting only) without a full suspension. Flagging so you don't
design UI around a granularity that doesn't exist yet.

---

## 7. Student re-verification — one behaviour fix, one new restriction, no new fields

Same fields as before (`student_verified_at`, `student_reverify_due_at`, the OTP flow) —
just corrected logic:

- **Re-verification is now genuinely annual per user** (`student_verified_at + 12
  months`), not shared calendar dates. This was already the intent; it's now actually
  computed that way.
- **Routine monthly login no longer silently renews the 12-month clock.** Previously,
  succeeding the regular login OTP (sent to the student email) reset the annual due date
  every time — meaning an active user's re-verification would never actually come due.
  Now it only resets when it's genuinely bringing a lapsed (logged-out, see below) account
  back, not on an ordinary login.
- **During the 60-day grace period**, the account stays fully active (login, existing
  listings, existing conversations all keep working) — the only thing blocked is
  creating a **new** listing (next point). The user gets a push/in-app notification when
  the due date arrives.
- **After the 60-day grace period**, the account is fully logged out — same as before:
  `status` flips to the existing "verification required" state and every token
  (access/refresh/biometric) is revoked. The user must go through re-verification to log
  in again, exactly like the existing "verify your student email" flow you already have
  for new registrations. No new response shape here.

**New:** `POST /ads`, `POST /ads/draft`, and `POST /my-ads/{id}/sell-again` can now
return a 403 you haven't seen from those endpoints before, starting from the moment
annual re-verification comes due (i.e. during the 60-day grace window, before the full
logout above kicks in):

```json
{ "status": false, "message": "Please re-verify your student status before creating a new listing",
  "data": { "needs_reverify": true } }
```

This is the **exact same shape** `POST /conversations` and `POST /conversations/{id}/messages`
already return when `needs_reverify` is true — if you already handle that error there
(prompting the user to the re-verify screen), the same handler covers these three new
call sites.

---

## Verify

- Post a listing in each of Standard/Cars/Accommodation categories, confirm the charged
  amount.
- Extend an expired ad via `/v2/my-ads/{id}/extend`, confirm it charges £0.99 regardless
  of category and pushes `expires_at` out 30 days.
- Call `/v2/ads/{id}/publish` without `confirm_publish_immediately` — expect 422.
- Request a refund, then check `GET /my-ads/refund-requests` shows it; have admin
  accept/decline from the dashboard and confirm a notification arrives.
- Report a user profile directly with no conversation open.
- Have admin issue a temporary suspension from the dashboard; attempt login; confirm the
  `data.moderation_action_id` is present; submit an appeal with it.
- As a student within their 60-day grace window (past due date, not yet 60 days), try
  creating a listing (expect 403 `needs_reverify`) and confirm you can still open/manage
  an existing listing and continue an existing chat.
- As a student past the full 60-day grace period, confirm login now requires
  re-verification (same flow as a new registration) and that a prior session's token no
  longer works.
- Log in normally a few times as an active, already-verified student and confirm
  `student_reverify_due_at` does **not** move — only a genuine re-verification (or
  recovering a logged-out account) should push it forward.

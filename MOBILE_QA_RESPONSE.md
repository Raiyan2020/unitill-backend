# Backend response — QA round, 2026-09-16 items

Status against `BACKEND_REQUIREMENTS.md`. Everything marked **Done** has an
automated test in `tests/Feature/MobileBackendRequirementsTest.php` (7 tests,
all passing) and, where the change touches live data, an idempotent migration
— no seeder was re-run against production.

| Bug | Title | Status |
|---|---|---|
| A1 / A2 | Housing date range | **Done** |
| B1 | "Pay now" fails on an inactive ad | **Done** — found and fixed the actual bug, see below |
| C1 / C2 | Dropdown values English in every locale | **Done** |
| C3a | `es`/`zh` served Arabic category names | **Done** |
| C3b | Terms & Privacy not localized | **Done** |
| D1 | RTL arrow direction | No backend change was needed — nothing to do here |
| E1 | Edit a published ad | **Done** |
| E2 | Sold → Active | **Done** — see below |
| F1 | Coupon refusals give no reason | **Done** |

---

## A1 / A2 — Housing date range

- `availability_from` and `availability_until` are now both typed `date` /
  `post_control: date` in the category payload.
- `POST /v2/ads`, `POST /v2/ads/draft`, `PUT /v2/my-ads/{id}` (new, see E1) and
  `sell-again` all enforce: `availability_from` must not be in the past,
  `availability_until` must be strictly after `availability_from` (same-day is
  rejected). Refusal is a 422 with `message: "The availability period is not
  valid."` and the field error lands under
  `errors["attributes.availability_until"]`, same shape you already parse.
- A migration backfills `availability_until` onto every existing Accommodation
  category and re-types `availability_from`; nothing was seeded, so this is
  safe to run against the current production data with
  `php artisan migrate --force`.

## B1 — "Pay now" on an inactive ad

- `GET /ads/{id}/payment/status` is read-only and, for a non-published ad,
  runs the same retry logic the post-ad flow uses: it never hands back a
  cancelled PaymentIntent's `client_secret` — it re-creates/refreshes it first.
- **We reproduced the actual bug directly instead of just checking logs.**
  Built a test that calls `POST /v2/ads/{id}/publish` on a `paused` ad and on
  an `expired` ad (both with an unexpired paid window). Before the fix:
  - The endpoint accepted both silently, flipped the ad's status to
    `pending`, and charged the **full listing fee again** (`amount: 0.99`,
    `payment_status: requires_payment`) — even though the ad already has a
    dedicated free/extend reactivation path at
    `POST /v2/my-ads/{id}/activate`.
  - This is exactly the "Pay now fails on an inactive ad" symptom: whatever
    called `publish` on a paused/expired ad from the inactive tab got routed
    into a brand-new payment cycle instead of the free (or extend-fee)
    reactivation flow.
  - **Fixed:** `publish` now refuses a `paused` or `expired` ad with `422`:
    ```json
    {
      "status": false,
      "message": "This listing has expired and cannot be published. Extend it instead.",
      "data": { "error_code": "invalid_status", "status": "expired" }
    }
    ```
    (and the paused equivalent: "This listing is paused. Activate it instead
    of publishing it again.") — localized in all five languages. The ad's
    status/payment state is left untouched by the refusal.
  - The app should route "Pay now" on a paused/expired ad to
    `POST /v2/my-ads/{id}/activate`, not `publish` — `activate` is free inside
    the paid 30-day window and charges the extend fee (£0.99) outside it,
    which is the correct pricing per your own doc.
  - Note: the equivalent V1 endpoint (`POST /ads/{id}/publish` →
    `AdController::publishDraft`) has the same underlying behaviour but was
    **not** touched — V1 is frozen for the published app. If the live app
    calls the V1 route for this button, tell us and we'll discuss a safe fix
    there instead.

## C1 / C2 — Dropdown option labels

- `options[].label` is localized per `lang`; `options[].value` is left
  untouched in every locale (verified with an automated test that posts a
  Spanish label and asserts the raw value round-trips unchanged).
- Fallback chain for a missing translation: requested language → English →
  never Arabic by default.

## C3a — Category names

- `name` is localized for all five locales, main categories and children,
  with the same requested → English fallback (confirmed `es`/`zh` no longer
  fall back to Arabic).
- Every category now carries a stable `slug` in the API response, independent
  of its translated name, so a rename in the admin won't silently break your
  name-keyed matching.

## C3b — Terms & Privacy

- `GET /terms/current` and the new `GET /privacy/current` both return
  `locale` (what was actually served) plus localized `title`/`content`,
  falling back to English — never Arabic.
- `version` is unchanged across locales, as required — it's still what you
  post back to `/terms/accept`.
- `GET /privacy/current` is live. No more 404 for the app's bundled-copy
  fallback path.

## D1 — RTL arrows

Confirmed app-side only, per your note. No backend ticket needed.

## E1 — Editing a published ad

- `PUT /api/v2/my-ads/{id}` is live, JSON body, same field set as the draft
  endpoint minus publish/coupon/photo fields — exactly the contract you
  proposed.
- **Answers to your open questions:**
  1. **Re-moderation:** not applied. An edit keeps `status: "published"` and
     does not touch `payment_status` or the Stripe intent — confirmed by test.
     If you want edits to force re-review, tell us and we'll add it; right now
     nothing changes except the listing content.
  2. **Frozen fields:** none are frozen yet — price included. If the client
     decides price shouldn't be editable post-publish, say which fields and
     we'll return a 422 naming them rather than silently accepting them.
  3. **Photos:** out of scope, as you said. No image add/remove/reorder
     endpoint was added in this round.
- Errors match your proposed shape: `403` + `error_code: not_owner` for a
  non-owner, `409` + `"A sold listing cannot be edited."` for a sold ad, `422`
  with the same field-error map as the draft endpoint (including A1/A2).

## E2 — Sold → Active

**Done** — as its own action, matching the "Relist this ad" shape your own
doc recommended (rather than overloading `activate`, which is shared with
the frozen v1 routes).

`POST /v2/my-ads/{id}/reactivate` — new, v2-only, reuses the ad's own id
(unlike `sell-again`, which copies it into a brand-new ad).

- Only works on a `sold` ad (`422` otherwise).
- **Inside the still-valid paid period** (same check `activate` uses for
  paused/expired: settled payment + `expires_at` in the future) → free,
  `status` goes straight back to `published`.
- **Outside that period** → charged the **extend fee**
  (`listing_extension_price`, £0.99 by default), the same `startExtension`
  path and price paused/expired ads get through `activate` — not a full
  new-listing fee. Verified with a test that sets the full listing fee to
  £5.00 and the extend fee to £0.99, and asserts a reactivated sold ad is
  charged £0.99, not £5.00.
- `sold_at`, `sold_to_user_id` and `is_sold_outside` are cleared either way.
- `activate` itself is untouched — still only `paused`/`expired`, exactly as
  before, so nothing changes for v1.
- `available_actions` for a `sold` ad now additively includes `"reactivate"`
  alongside the existing `"see_details"`/`"relist"` — a new array element in
  a response you already parse, flagging it explicitly even though nothing
  existing changed shape.
- Confirmed separately: there's no order/rating record tied to
  `sold_to_user_id` — mark-sold only sets a few columns on the ad row and
  archives the chat — so reversing the status doesn't touch anything on
  another user's account.
- Still open, in case the client cares: no extra time limit beyond the
  existing 30-day paid window (e.g. "no free reactivation after 90 days
  sold"), and no rate limit on repeated sell↔reactivate cycles. Say the word
  if either is wanted.

## F1 — Coupon error codes

- `POST /coupons/validate` now returns `data.coupon_error` on every refusal,
  using exactly your six codes: `invalid`, `not_started`, `expired`,
  `exhausted`, `already_used`, `min_amount`. `exhausted` and `already_used`
  are distinct (verified: a code past its global limit and a code already
  used by one user return different codes).
  Success responses now also carry `applied: true`.
- The publish-time response (`publication.coupon_error`) already used this
  same vocabulary and is unchanged, so both endpoints agree.

---

## What we need from you (blocking, not code)

1. **B1** — confirm whether the app's "Pay now" button on a paused/expired ad
   calls the V1 or V2 publish route; if V1, that's frozen and needs a
   separate conversation before we touch it.
2. **E1 Q1** — should an edit send a published ad back into moderation?
3. **E1 Q2** — is price (or anything else) meant to be frozen after
   publication?
4. **E2** — confirm the `reactivate` naming/wiring works for the app team, and
   say if a time limit or rate limit should be added on top of what's built.

## Regression check

Full test suite run: **113 passed** (three new tests added: one for the B1
fix, two for E2 reactivate). Two failures are pre-existing and
unrelated to this work (not touched by this change, and predate it):

- `Tests\Feature\ExampleTest` — the default Laravel scaffold test, asserts
  `GET /` returns 200; this app has no `/` route.
- `Tests\Feature\PaymentPublicationContractTest::pending payment ad is absent
  from public endpoints` — flaky assertion: it does a raw string search for
  `{"id": <ad id>}` across the whole `/api/home` response, and in this run the
  ad id happened to collide with an unrelated category id already on the
  page. Worth tightening that assertion to check inside `recent_ads.data`
  specifically rather than the whole payload, but it's not something this
  round of changes caused.

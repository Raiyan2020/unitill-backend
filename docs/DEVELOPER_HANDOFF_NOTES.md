# Handoff notes — Salman/Takwa reconciliation

Branch: `integration/salman-contract-takwa-features`

Two developers built the same project in two repositories. This branch merges
them into one, using the Salman tree as the reference for existing API
responses and keeping the integrations Takwa added.

There are two audiences below. **Part A** is for the backend developer,
**Part B** is for the Flutter developer. Part B is the one that decides whether
the current app keeps working — read it first if you are short on time.

## How this was checked

Not by reading the diff. 74 real responses were captured from the Salman tree
and the same 74 were captured here, then compared field by field.

| | scenarios identical | differences |
|---|---|---|
| before this work | 32 / 74 | 1182 |
| now | 39 / 74 | 186 |

All 186 remaining differences are deliberate and listed in Part B. Zero are
unexplained. The harness lives in `tests/Contract/` — see its README to re-run.

This matters because four regressions were found by measurement after a careful
manual review of the same code had missed them, and one of those returns HTTP
200 with an empty list rather than an error.

---

# Part A — backend developer

## What is on the branch

Three commits:

1. `docs:` the migration plan, revised after auditing it against both trees
2. `fix:` restoring the Salman response contract on shared endpoints
3. `feat:` Salman-only features, security fixes, contract harness

## Decisions that were made for you

Two came from the product owner and shape everything else.

**No new API versioning.** The plan originally proposed a `/api/v2/ads` layer
so Takwa's paid-listing flow could live beside an unchanged V1. That was
rejected. `/api/v2/*` continues to exist for OTP auth only — those four routes
were already there. Stripe applies to the existing endpoints, and mobile
changes to match (Part B).

This also closed a hole. `Ad::scopePublished` filters on `status` alone;
`payment_status` is not in any scope or constraint. Had V1 kept Salman's free
publish, `POST /api/ads` with `confirm_publish=true` would have written
`status='published'` directly and bypassed payment entirely — without even
consuming the free quota. Keeping Takwa's controllers on V1 means every write
path goes through `HandlesListingPayments::startPublication()`, so there is no
bypass. If you ever add another path that sets `status='published'`, it must go
through that method too.

**Location masking stays.** Non-owners get an outward postcode (`LS2`) and
coordinates rounded to ~1.1km. Reverting to Salman would have re-exposed exact
home addresses of student sellers to unauthenticated callers.

## Schema

Two new migrations, both guarded and forward-only:

- `2026_07_27_000100` — `student_verified_at`, `student_reverify_due_at`,
  `reverify_notified_at` on `users`, plus a backfill marking already-active
  student accounts as verified at signup so the first scheduled run does not
  flag everyone at once.
- `2026_07_27_000200` — `filter_control`, `post_control`, `config`,
  `is_filterable`, `is_postable` on `category_attribute_definitions`, and swaps
  the unique `(ad_id, definition_id)` index for a plain one so multiselect
  attributes can store a row per value.

Salman's own migrations were **not** copied. They cannot run:

- `2026_07_26_000000` creates `university_domains` unguarded, and this tree
  already owns that table. It aborts *after* the `users` ALTER has landed, and
  MySQL cannot roll back DDL, so the retry then fails with `1060 Duplicate
  column`.
- its index swap fails with `1553` because `adv_val_ad_cad_uniq` is the
  leftmost-prefix index backing the `ad_id` foreign key. You must create the
  replacement index before dropping the unique one. The Salman tree cannot
  `migrate:fresh` on MariaDB at all because of this.

Renumbering those files earlier makes it worse, not better: this tree's
`hasTable` guard would then skip its own `university_domains` creation and you
would end up with the table missing `university_id` and **no error at all**.

Verified both directions:

- `migrate:fresh --seed` from zero — clean
- the two new migrations applied to a populated pre-upgrade snapshot — clean,
  data preserved, index swapped, backfill applied
- seeders run three times — idempotent, row counts stable

## Canonical choices where the trees disagreed

| area | winner | why |
|---|---|---|
| `universities` / `university_domains` | Takwa | referentially correct, already deployed |
| `coupons` / `coupon_redemptions` | Takwa | Stripe flow depends on it; the enum physically cannot store Salman's `'percent'` |
| category + attribute taxonomy | **Salman** | matches production data and the mobile filter UI; also removes seeders that truncate live ads |
| filter/post engine | **Salman** | `filter_control` etc. drive the mobile filter panel |
| payment / listing fees | Takwa | the feature being adopted |
| location privacy | Takwa | product decision |

Note the seeder swap is load-bearing. Takwa's `CategorySeeder` called
`truncateCategoryTree()`, which disables foreign key checks and truncates
`ads`, `ad_images` and `ad_attribute_values`. Its own docblock warned never to
run it against production. Takwa's `CategoryAttributeSeeder` also pruned by
slug, which would have hard-deleted every Salman-only attribute definition and
cascaded away their values. Both are gone; the Salman versions are idempotent
`updateOrCreate`.

## Security — action required before deploy

**Rotate these two credentials.** They were inlined as `env()` defaults in
`config/services.php` and are in the git history of both repositories.
Removing them from `HEAD` does not invalidate them.

- `MY_FATOORAH_TOKEN` — `SK_KWT_vVZ...` (and a second, commented-out one)
- `WAWP_API_TOKEN` — `rhS3eD...`

Both now read from the environment with no default, so **set them or those
integrations silently stop working.**

**The V2 login OTP was an authentication bypass.** `login_otp_test_code`
defaulted to `'123456'` and was read in every environment, so unless the env
var was explicitly set to an empty string, `123456` logged in as any account.
The default is now null and the fixed code is honoured only under
`APP_ENV=testing`. The re-verification OTP had the same flaw hard-coded as a
literal and now generates a random code outside testing.

New environment variables to set: `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`,
`VEHICLE_API_KEY`, `CORS_ALLOWED_ORIGINS`.

## Check these two settings before enabling payments

The seeded values are `free_ads_per_user = 0` and `post_price = 100`. Taken
literally that means **no free listings and £100 per ad** — every publish would
demand a £100 Stripe payment and nobody could post.

`post_price = 100` looks like a placeholder inherited from the earlier
MyFatoorah integration, where the currency was not GBP. `StripeService` charges
`round(listing_fee * 100)` minor units in `STRIPE_CURRENCY` (default `gbp`), so
it will read as £100.00.

Set both deliberately in the settings table before turning the payment flow on.
Until they are correct, the safest position is `post_price = 0`, which makes
`startPublication()` publish immediately with `payment_status = 'waived'` and
keeps current mobile behaviour intact.

## Pre-existing defects, not caused by this merge

Found while verifying. Present in **both** trees, left alone to keep this
branch reviewable — worth separate tickets.

1. **`routes/admin.php` references 12 controllers that do not exist**
   (`Admin\DashboardController`, `Admin\CouponController`, and so on — only
   `Admin\AuthController` is present). The file is loaded, so
   `php artisan route:list` crashes with a `ReflectionException` and any
   request to `/{locale}/admin/*` would 500. The blade admin appears to have
   been replaced by the React dashboard and these routes were never removed.
   Use `tests/Contract/` or a tinker route dump until this is fixed.
2. Orphaned admin code referencing columns that do not exist in this schema:
   `CouponDataTable` (`used_count`, `type === 'percent'`),
   `StoreCouponRequest` / `UpdateCouponRequest` (`max_uses`,
   `max_uses_per_user`, `used_count`), `PostDataTable` and
   `UpdatePostRequest` (treating `payment_status` as `0|1` when it is a string
   enum). All are currently unreachable because of defect 1. Do not wire them
   back up without fixing the column names first.
3. `max_uses_per_user > 1` is not supported. Takwa's schema enforces one
   redemption per user with a unique index, which is also its only protection
   against a concurrent double-redeem. Supporting multi-use coupons means
   dropping that index and enforcing the limit transactionally instead.

## Still to do

- Write paths are not contract-tested: create ad, publish, pay, send message.
  They mutate state so they need setup/teardown per scenario. The publish and
  payment flows are the priority, since that is where the Stripe work landed.
- Stripe webhook idempotency has not been load-tested. Signature verification
  is sound (`hash_equals`, 300s replay window, fails closed with no secret).
- The frontend has not been rebuilt or smoke-tested against this branch.
- `php artisan test` passes, but the suite is still only the two default
  example tests.

---

# Part B — Flutter developer

Read this before the next release. Some of these changes are additive and safe
to ignore; others will break a typed model or blank a screen.

## 1. Breaking — coupon validation payload renamed

`POST /api/coupons/validate` now returns the Stripe flow's field names.

```jsonc
// before                        // now
{ "code": "SAVE10",              { "code": "SAVE10",
  "type": "percent",               "type": "percentage",   // renamed value
  "value": 10,                     // removed
  "listing_fee": 100,              "original_amount": 100, // renamed
  "discount": 10,                  "discount_amount": 10,  // renamed
  "total": 90,                     "final_amount": 90,     // renamed
  "formatted_discount": "£10.00",  "formatted_discount": "£10.00",
  "formatted_total": "£90.00" }    "formatted_final": "£90.00" }  // renamed
```

`value` is gone entirely. `type` is now `percentage`, not `percent` — if you
switch on that string, update it.

## 2. Breaking — publishing an ad can now require payment

`POST /api/ads` and `POST /api/ads/{id}/publish` no longer always publish. The
response carries a `publication` object:

```jsonc
"publication": {
  "published": false,
  "payment_required": true,
  "amount": 5.0,
  "currency": "GBP",
  "payment_intent_id": "pi_...",
  "client_secret": "pi_..._secret_...",
  "coupon": null
}
```

When `payment_required` is true the ad stays `pending`. You must confirm the
PaymentIntent with the Stripe SDK using `client_secret`, then call
`POST /api/ads/{id}/payment/complete` to publish it.

Note the `message` still reads "Ad published successfully" even when payment is
outstanding — trust `publication.published`, not the message.

Users get `free_ads_per_user` free listings first (read it from
`GET /api/settings`); within that quota `payment_required` is false and nothing
changes for them. `confirm_publish` is still accepted but ignored.

## 3. Breaking — location is approximated for other people's ads

On every ad in every list, and on ad detail, when you are **not** the owner:

- `postcode` is the outward code only — `"LS2 9JT"` becomes `"LS2"`
- `latitude` / `longitude` are rounded to 2dp (~1.1km)
- new `is_approximate_location: true`

Owners still get exact values. Map pins will be about a kilometre off for other
people's listings — show the "general area" hint rather than a precise marker.

`latitude` and `longitude` are still **strings** (`"53.8000000"`), as before.
An intermediate version emitted them as JSON numbers; that was corrected
specifically so your parsing does not change.

## 4. Breaking — new 403 on messaging

Starting a conversation or sending a message can now return:

```json
{ "status": false,
  "message": "Please re-verify your student status before messaging",
  "data": { "needs_reverify": true } }
```

Handle `data.needs_reverify` by sending the user through
`POST /api/reverify/send-otp` then `POST /api/reverify/confirm`
(`{"activation_code": "123456"}`). Both are new. Existing conversations are
never cut off mid-thread — only starting new ones and sending is gated.

`GET /api/show-profile` (own profile) exposes `needs_reverification`,
`student_verified_at` and `student_reverify_due_at` so you can prompt before
the user hits the 403.

## 5. Possibly breaking — chat report reason is now required

`reason` on the chat report endpoint was `nullable|string|max:80` free text. It
is now **required** and must be one of the values from
`GET /api/chat-report-reasons`. Sending free text, or omitting it, returns 422.
When `reason` is `other`, `description` becomes required.

## 6. Possibly breaking — stricter ad validation

- `postcode` was `max:12` free text, now validated against a UK postcode
  pattern. Previously-accepted values may now 422.
- `license_plate` is **required** when posting in Cars, and must match
  `^[A-Z]{2}[0-9]{2}[A-Z]{3}$` — uppercase, no spaces. Normalise before
  sending: `ab12 cde` is rejected, `AB12CDE` is accepted.

## 7. New fields — additive, safe to ignore

If your models reject unknown keys, add these; otherwise nothing to do.

| endpoint | new |
|---|---|
| `GET /api/settings` | `free_ads_per_user`, `app.url` |
| ad list / detail | `region`, `is_approximate_location` |
| ad list | `active_filter_count` |
| own profile, login | `settings.privacy.*`, `settings.notifications.*` |
| `GET /api/conversations` | `search` |

## 8. Things that were broken and are fixed

You may have coded around some of these. They now behave as originally
specified again.

- **Profile and login lost `average_rating`, `total_reviews` and
  `is_trusted_seller`.** Restored. If you added null-guards, they are no longer
  needed.
- **Guest ad detail was missing 17 fields** — `description`, `subtitle`,
  `attributes`, `seller`, city and location, `published_at`, `is_favorited` —
  replaced by `requires_auth` / `hidden_for_guests`. Restored; the response
  shape no longer depends on whether you are logged in, and those two keys are
  gone. Location is protected by masking values, not by removing keys.
- **Ad lists returned zero results if you sent `near_lat` / `near_lng`.**
  Silent — HTTP 200 with an empty array. Fixed. Any unrecognised query
  parameter is now ignored rather than matched.
- **`filter_options` lost `filter_control`, `post_control`, `config`,
  `is_filterable`, `is_postable`, `is_multi`, `is_range`** and the synthetic
  `price` and `location` entries. Restored — the filter panel needs them.
- **`GET /api/languages` returned inactive languages.** Filtered again.
- **`GET /api/legal-affairs` returned Arabic when no `lang` header was sent.**
  English again.
- **Unknown `/api/*` paths returned `{"message": "Not Found."}`** with no
  `status` field. Back to the standard envelope.
- **Multiselect attributes appeared multiple times** on ad detail. Grouped
  again, values joined with `, `.
- **Auto-expired ads had a null `inactive_reason`**, so "My Ads → Inactive"
  showed no reason. Set to `auto_expired` again.
- Login message is `"login success"` again, not `"Login successful"`.

## 9. Unchanged — verified, not assumed

- V1 access token lifetime is still 60 minutes; refresh still 30 days.
- All 138 endpoints you use still exist at the same method and path.
- Response envelope is still `{status, message, data}`.
- Validation error shape is unchanged: `message` is the first error,
  `data` maps field to messages, status 422.
- Arabic responses still work via the `lang: ar` header. Some validation
  messages that used to come back in English are now correctly translated —
  if you display them verbatim that is an improvement, but the strings differ.

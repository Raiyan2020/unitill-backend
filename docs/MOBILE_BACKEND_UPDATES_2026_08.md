# Backend updates for mobile — August 2026

**Status:** all ten items from `BACKEND_REQUIRED_CHANGES.md` are implemented and
covered by `tests/Feature/BackendRequiredChangesTest.php`.

This document is written for the mobile team: what changed on the wire, what you
need to do (mostly nothing), and how to verify each item.

---

## TL;DR — what mobile has to do

| # | Item | App change needed? |
|---|------|--------------------|
| 1 | Public page for shared ad links | **No** — links work as soon as this deploys |
| 2 | `social_links` in `GET /settings` | **No** — already data-driven |
| 3 | `value_label` on ad attributes | **No** — already forward-compatible |
| 4 | Contact details removed from other users' profiles | **No**, but see the note on `phone`/`email` below |
| 4b | New `GET /sellers/{id}` + `GET /sellers/{id}/ads` | **Yes, if** you build the seller-profile screen |
| 5 | Localized `options[].label` | **No** |
| 6 | `created_at` + `active_ads_count` on the profile | **No** |
| 7 | `reverify/send-otp` behaviour documented | **No** |
| 8 | fr / es / zh content translated + fallbacks fixed | **No** |
| 9 | Contact Us now emails support; response carries `mail_sent` | **No**, optional |
| 10 | Paid, in-period ads reactivate free | **No** |

The only **breaking** change is item 4: `phone` and `email` are no longer
returned for *other* users. They are unchanged on your own profile.

---

## 1. Shared ad links — public page with previews

`GET {app.url}/ads/{public_id}` is now a real, no-auth HTML page. It serves the
title, price, photo gallery, description, category path, location, and the
localized attribute grid.

- **Open Graph / Twitter cards** are emitted (`og:title` with the price,
  `og:description`, `og:image`, `product:price:*`), so a pasted link unfurls into
  a preview card.
- **404** for an unknown, deleted, expired, or pending-payment ad — a friendly
  page, not the admin dashboard. Pending-payment ads stay invisible publicly.
- Optional `?lang=` query parameter localizes the page; it defaults to English.

`data.app.url` in `GET /api/settings` is unchanged — the page lives on the API
domain, so no settings edit is needed.

**Not done (deliberately):** `/.well-known/assetlinks.json` and
`apple-app-site-association` for app deep links. As agreed, this needs the app to
register the domain first — tell us the package name / SHA-256 fingerprints and
the Team ID + bundle ID, and we will serve them.

**Verify:** open `https://<api-domain>/ads/<public_id>` on a phone with no app;
paste it into WhatsApp; then open a deleted ad's link.

---

## 2. `social_links` in `GET /api/settings`

New key in `data`:

```json
"social_links": {
  "instagram": "https://instagram.com/unitill",
  "x": "https://x.com/unitill"
}
```

- Always an **object**, never an array — `{}` when nothing is configured.
- Only platforms with a URL appear. Recognised keys: `facebook`, `instagram`,
  `x`, `linkedin`, `tiktok`, `youtube`.
- The URLs are ordinary settings rows (`social_facebook`, `social_instagram`,
  `social_x`, `social_linkedin`, `social_tiktok`, `social_youtube`), editable in
  the admin dashboard. **They ship empty** — an admin must fill in the real
  profile URLs before any icon appears.

---

## 3. `value_label` on ad attributes

`GET /api/ads/{id}` → `data.attributes[]` now carries a localized display value
beside the raw one:

```json
{"slug": "condition", "label": "الحالة", "value": "Like New", "value_label": "شبه جديد"}
```

- `value` is unchanged and still the raw English key you filter with.
- `value_label` is always present. For free-text and numeric attributes it equals
  `value` (the user's own input needs no translation).
- Multi-select attributes join both fields with `, ` in the same order.

---

## 4. Profile privacy + the new seller endpoint

### Breaking: contact details are owner-only

`GET /api/show-profile/{user_id}` no longer returns `phone`, `email` or
`student_email` when `user_id` is someone else. They are still returned on your
own profile (`GET /api/show-profile` with no id). `student_email_masked` for
other users is unchanged.

If any screen reads `data.email` or `data.phone` from another user's profile, it
will now get a missing key.

### New: public-safe seller profile

```
GET /api/sellers/{id}          # no auth required
GET /api/sellers/{id}/ads      # paginated, standard ads shape, ?per_page= (max 50)
```

`GET /api/sellers/{id}` returns:

```json
{
  "id": 12,
  "name": "John",                     // respects the seller's "show last name" setting
  "first_name": "John",
  "image": "https://.../users/abc.jpg",
  "is_verified_student": true,
  "is_trusted_seller": false,
  "average_rating": 4.6,
  "total_reviews": 18,
  "active_ads_count": 3,
  "created_at": "2026-01-15T09:30:00+00:00",
  "member_since": "January 2026"
}
```

No email, no phone, no city id. `GET /sellers/{id}/ads` returns only published,
unexpired listings using the same `AdResource` shape as `GET /ads`, so the
existing ad-card widget works unchanged.

`404` with `api.auth.user_not_found` for an unknown id.

---

## 5. Localized attribute option labels

`GET /api/categories` → `attributes[].options[]` and `GET /api/ads` →
`filter_options[].options[]` now localize `label` from the `lang` header:

```json
{"value": "Sofa", "label": "أريكة"}
```

`value` is untouched — keep submitting and filtering with it.

The two synthetic filter controls (`price`, `location`) are now localized in all
five languages too; they used to be English/Arabic only.

**Where the translations live:** a `value => label` map on each attribute's
translation row (`category_attribute_definition_translations.options`). A value
with no entry falls back to English and then to the value itself — which is why
brand names, sizes and numbers ("Dell", "128GB", "4+") correctly stay as-is.

---

## 6. `created_at` + `active_ads_count` on the profile

`GET /api/show-profile` (self) now includes:

```json
{"created_at": "2026-01-15T09:30:00+00:00", "active_ads_count": 3}
```

- `member_since` (pre-formatted "January 2026") is unchanged and still there.
- `ads_count` is unchanged; `active_ads_count` is the same number under the name
  the app looks for.
- Both count **published** ads, matching the My Ads → Active tab.

---

## 7. `POST /api/reverify/send-otp` while already verified

**Decision: the request is accepted.** Reconfirming early is harmless —
`confirmReverify` simply pushes the due date to the next term deadline — so
there is no reason to refuse it. You may show the button whenever you like; the
current behaviour of showing it only when `needs_reverification` is true is fine
and needs no change.

Responses:

| Case | Status | Body |
|---|---|---|
| OK (due or early) | 200 | `student_email_masked`, `activation_expires_at`, `needs_reverification`, `student_reverify_due_at` |
| No university email on file | 422 | localized `message` |
| Within the 60-second resend cooldown | 429 | localized `message` + `data.retry_after_seconds` |

All three messages are now localized in **all five languages** (they were
English/Arabic only, which is the "unexplained error" you saw on other locales).

`POST /api/reverify/confirm` takes `activation_code` (6 digits) and is unchanged.

---

## 8. fr / es / zh content

**Route taken: translate, not deactivate.** All five languages stay active in
`GET /api/languages`.

### 8.1 Fallback chain fixed (the bug on its own)

Every translated model now resolves
`requested language → English → default language → any row`, and a row whose text
is blank is skipped rather than returned. Concretely:

- `GET /contact-reasons` never returns `name: ""`.
- Attribute labels never return the raw slug. Worst case is a humanised slug
  (`property_type` → `Property type`).
- `GET /legal-affairs` and the `policies` block in `GET /settings` honour the
  full `lang` header — they previously collapsed every locale to `en` or `ar`,
  which is why French, Spanish and Chinese always showed English policies.

### 8.2 Content added

Translated into fr / es / zh:

- all 10 categories and every subcategory,
- every category attribute **label**,
- every attribute **option** label (except brand names / sizes / numbers, which
  stay as-is by design),
- all 5 legal policies — title, subtitle and every bullet point,
- contact reasons (es and zh were missing).

Deployment runs a single additive seeder, `MultilingualContentSeeder`. It only
inserts missing translation rows and fills columns that are still `NULL` — it
never updates or deletes anything, so admin edits to existing content survive.

Your shipped mitigations (English refetch on blank reasons, slug humanising,
option-label fallback) are now redundant but harmless — they will simply never
trigger.

---

## 9. Contact Us now reaches the support inbox

`POST /api/contact-us` previously only wrote a database row; **no mail was ever
dispatched**. That was the whole bug — the request contract was correct.

Now:

- A `ContactUsMail` is sent synchronously (not queued, so no worker is needed and
  the answer is not a guess) to the `contact_email` from `GET /settings`, with
  `Reply-To` set to the sender so support can reply directly.
- The response adds a flag:

```json
{"status": true, "data": {"id": 41, "mail_sent": true}, "message": "..."}
```

`mail_sent: false` means **stored but not mailed** — the row is still saved and
visible in the dashboard. You can keep showing the current success message
either way; the flag is there so a failure is diagnosable rather than silent.

- The row records `mail_sent_at` / `mail_error`, and the admin Contact Us list
  surfaces `mail_sent`, `mail_sent_at` and `mail_error`.
- The success message is now localized in all five languages.

**Ops note:** the test environment must have real SMTP credentials and a valid
`contact_email` setting. With a `log` mail driver, `mail_sent` will report `true`
(the mailer succeeded) but nothing arrives — check `mail_error` and the driver if
that happens.

---

## 10. Reactivating a paused ad

**Decision: a pause does not consume the posting period you already paid for.**

`POST /api/my-ads/{id}/activate`:

- If the ad's period is **settled and still running** (`payment_status` is one of
  `paid`, `free`, `waived`, `coupon`, and `expires_at` is in the future), the ad
  returns straight to `published`. No fee, no payment step, `expires_at`
  untouched. The response carries the usual `publication` block with
  `published: true`, `payment_required: false`.
- Otherwise (an expired ad, or a pause that outlived its 30 days), reactivating
  starts a **new** paid period exactly as before: the ad goes to `pending` with
  `payment_required: true` and a fresh `client_secret`.

`POST /api/ads/{id}/publish` now explicitly accepts `draft`, `pending`, `paused`,
`expired` and `published` ads, so it can settle an outstanding fee on a
reactivated ad — this is the call that used to error. `sold` and `rejected` ads
return a localized 422 with `data.status`. A `published`, paid ad returns its
current state idempotently instead of erroring.

Your fix (reading the `publication` block instead of trusting the 200, and
re-reading the list from the server) is exactly right and stays correct.

---

## Deployment (backend) — safe on the existing production database

> **16 August compliance addition:** versioned Terms acceptance, independent
> posting/messaging restrictions, and the public Google Play deletion page are
> documented in
> [`MOBILE_COMPLIANCE_UPDATES_2026_08_16.md`](MOBILE_COMPLIANCE_UPDATES_2026_08_16.md).

```bash
git pull
composer install --no-dev --optimize-autoloader

php artisan migrate --force
php artisan db:seed --class=MultilingualContentSeeder --force

php artisan optimize:clear
```

**That is the whole list.** Do **not** run `db:seed` with no `--class`, and do
not run `CategorySeeder`, `CategoryAttributeSeeder` or `LegalAffairSeeder` on
production — they are fresh-install seeders and would overwrite content admins
have edited since.

`MultilingualContentSeeder` is additive only: it inserts missing `fr`/`es`/`zh`
translation rows and fills the new `options` column where it is still `NULL`.
Existing rows are never updated or deleted, and it is safe to run repeatedly.

New migrations:

| Migration | Effect |
|---|---|
| `..._add_options_to_category_attribute_definition_translations` | adds a nullable `options` JSON column |
| `..._add_social_link_settings` | inserts six `social_*` settings rows if missing |
| `..._add_mail_delivery_to_contact_us_messages` | adds nullable `mail_sent_at`, `mail_error` |

All three are additive — no column is dropped or retyped, and no existing row is
modified.

Then, in the dashboard: fill in the social profile URLs and confirm
`contact_email` points at the real support inbox.

### Verify after deploying

```bash
curl -H "lang: es" https://<api>/api/categories   | head -c 400   # Spanish names + option labels
curl -H "lang: zh" https://<api>/api/contact-reasons             # no empty "name"
curl -H "lang: fr" https://<api>/api/settings     | head -c 400   # French policies + social_links
curl -i https://<api>/ads/<public_id>             | head -20      # 200 + og: tags
```

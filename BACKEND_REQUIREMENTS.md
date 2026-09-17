# Backend requirements — QA round, 2026-09-16

Everything below was verified against the **live** API on 2026-09-16, not against
a spec. Where a response is quoted it is the response the server actually sent.
Requests were made with `Accept: application/json` and the `lang` header set to
the locale under test.

Each item says whether the app needed anything from you, and the ones that do
carry a full request/response contract. Items marked **App-side only** are
already fixed in the Flutter app and are listed so you know they are closed.

| Bug | Title | Backend change needed? |
|---|---|---|
| A1 / A2 | Housing date range accepted backwards or same-day | **Yes** — validation rules, plus an attribute type fix |
| B1 | "Pay now" fails on an inactive ad | **Probably** — needs a log check; app now degrades gracefully either way |
| C1 / C2 | Dropdown values English in every locale | **Yes** — translated option labels |
| C3 | Categories and Terms not localized for `fr`/`es`/`zh` | **Yes** — and one is serving Arabic to Spanish users |
| D1 | RTL arrow direction | **No** — app-side only |
| E1 | No way to edit a published ad | **Yes** — the endpoint does not exist |
| E2 | Sold → Active | Investigation only; no change requested yet |
| F1 | Coupon refusals give no reason | **Yes** — machine-readable error codes |

---

## A1 / A2 — Housing (سكن) date range

**Reported:** a housing ad can be published with an end date *before* the start
date (A1), or with both on the same day (A2). Both were previously reported and
re-opened.

### What we found

Two separate causes, one of which is yours.

`GET /categories` describes the Accommodation availability attribute like this:

```json
{
  "slug": "availability_from",
  "label": "Available from",
  "input_type": "string",
  "post_control": null,
  "is_required": false,
  "options": []
}
```

`input_type: "string"` means the app renders a **free-text box**, not a date
picker — so none of the picker's floors apply and any string at all is accepted.
The app now recognises the attribute as a date from its slug and forces the
picker on, so this is no longer blocking. But the type is still wrong, and the
next client to integrate will hit the same thing.

There is also **no `availability_until` attribute at all** in the current
Accommodation definition, only `availability_from`. Whatever environment the
tester was on is serving an end date that production is not.

### What we need

**1. Type the attribute correctly.**

| Field | Current | Required |
|---|---|---|
| `input_type` | `"string"` | `"date"` |
| `post_control` | `null` | `"date"` |

**2. Serve the pair.** Accommodation should define both:

| slug | label | input_type | post_control |
|---|---|---|---|
| `availability_from` | Available from | `date` | `date` |
| `availability_until` | Available until | `date` | `date` |

The app pairs a range by slug suffix (`_from`/`_until`, `_from`/`_to`,
`_start`/`_end`), so any of those spellings works — but the two halves must use
matching suffixes on the same stem.

**3. Enforce the rules server-side.** Front-end validation is not enough: the
same endpoints can be called from anywhere, and the app's picker only guards the
app.

Applies to `POST /v2/ads`, `POST /v2/ads/draft`, `POST /v2/my-ads/{id}/sell-again`
and the proposed `PUT /v2/my-ads/{id}` (E1 below).

Validation rules:

```php
'attributes.availability_from' => ['nullable', 'date', 'after_or_equal:today'],
'attributes.availability_until' => [
    'nullable',
    'date',
    'required_with:attributes.availability_from',
    'after:attributes.availability_from',   // strictly after — NOT after_or_equal
],
```

`after`, not `after_or_equal`: an ad that is available from and until the same
day advertises a period of nothing, which is bug A2.

Expected refusal:

```http
HTTP/1.1 422 Unprocessable Content
```
```json
{
  "status": false,
  "message": "The availability period is not valid.",
  "errors": {
    "attributes.availability_until": [
      "The end date must be at least one day after the start date."
    ]
  }
}
```

The app already renders a 422 field-error map, so no new shape is needed — the
key just has to be the attribute path so it lands under the right field.

### Note on past start dates — behaviour reported, not changed

The app **does** refuse a start date in the past: the picker's `firstDate` is
today, and `PostAdCubit._validateDateRanges` rejects any date before today with
`date_cannot_be_in_past`. This was left exactly as it is, as instructed. Worth
knowing: it means a landlord cannot advertise a tenancy that started last week
and still has rooms free. If the client wants that, the change is one line in the
app **and** `after_or_equal:today` must come off the start date here.

---

## B1 — "Pay now" fails on an inactive ad

**Reported:** in My Ads, pressing "Pay now" on an inactive ad shows an error.

### What we found

Most of this was ours and is fixed. The My Ads button ran a cut-down copy of the
post-ad payment sequence and skipped two things the post-ad flow does:

1. It handed Stripe the `client_secret` straight off the publish response.
   On an ad created some time ago that PaymentIntent is usually dead — Stripe
   cancels an abandoned one — and the sheet fails with *"cannot set up a
   PaymentIntent in status canceled"*. The app now re-reads
   `GET /ads/{id}/payment/status` before opening the sheet, the same as the
   post-ad flow does.
2. It had no hosted-checkout branch, so a response carrying `checkout_url` and no
   `client_secret` was read as "no PaymentIntent" and reported as *"Card payment
   is unavailable"*. It now opens the checkout page.

### What we need from you

**1. Confirm `GET /ads/{id}/payment/status` recreates a dead intent.** The app
now depends on this being both read-only (no side effects the user pays for) and
willing to hand back a *usable* `client_secret` when the previous one was
cancelled. If it just echoes the stored intent, the fix is incomplete.

```http
GET /api/ads/{id}/payment/status
Authorization: Bearer {token}
```
```json
{
  "status": true,
  "data": {
    "publication": {
      "published": false,
      "payment_required": true,
      "amount": 2.99,
      "currency": "GBP",
      "payment_intent_id": "pi_3QxxxxFRESH",
      "client_secret": "pi_3QxxxxFRESH_secret_yyy",
      "payment_status": "requires_payment"
    }
  }
}
```

**2. Check your logs for `POST /v2/ads/{id}/publish` on ads whose status is
`paused` or `expired`.** The tester's ad was in the "inactive" tab, which holds
`pending`, `draft`, `paused` and `expired`. If publish refuses some of those, we
need to know with what — and the refusal should name the reason:

```http
HTTP/1.1 422 Unprocessable Content
```
```json
{
  "status": false,
  "message": "This listing has expired and cannot be published. Extend it instead.",
  "data": { "error_code": "invalid_status", "status": "expired" }
}
```

The app now shows a 422's `message` verbatim rather than a generic error, so a
sentence like the above reaches the user as-is. An `error_code` would let us map
it to the right localized copy and, where relevant, point at the right button.

**3. Status refresh after payment.** No change needed — `GET /my-ads?status=`
is re-read by the app on success *and* on "paid, waiting for the webhook", so
the status updates without restarting. Please make sure the webhook flips
`status` and `payment_status` promptly; until it does, the app shows "we received
your payment" rather than a success it cannot verify.

---

## C1 / C2 — Dropdown values are English in every locale

**Reported:** on the add-product screen in Arabic, dropdown values show in
English — product condition ("Like new"), housing property type, furniture type.
Contract period and property type on the housing screen are untranslated.

### What we found

`GET /categories` localizes attribute **labels** for `ar` but never localizes
option **values or labels**. Measured on the live API:

```
GET /api/categories    (lang: ar)
  "slug": "property_type", "label": "نوع العقار"        ← label translated
  options: [
    {"value": "Flat",  "label": "Flat"},                 ← label English
    {"value": "House", "label": "House"},
    {"value": "Studio","label": "Studio"}
  ]
```

**All 114 option labels come back in English, in all four non-English locales.**
Same for `contract_type` (`Short-term` / `Long-term`), `condition`
(`New` / `Like new` / `Refurbished` / `Used`) and Furniture's `item_type`
(`Beds & mattresses` / `Desks & chairs` / …) — exactly the examples in the bug
report.

### What we need

`options[].label` localized from the `lang` header, with `options[].value` left
**unchanged** in every locale. The app posts `value` back as the attribute
answer and filters on it, so a translated `value` would break both filtering and
the stored data.

```http
GET /api/categories
lang: ar
```
```json
{
  "slug": "property_type",
  "label": "نوع العقار",
  "input_type": "select",
  "options": [
    { "value": "Flat",   "label": "شقة" },
    { "value": "House",  "label": "منزل" },
    { "value": "Studio", "label": "استوديو" }
  ]
}
```

The same applies to `GET /ads` → `filter_options`, which uses the identical
shape, and to `GET /ads/{id}` → `attributes[].value_label`.

### Interim app-side fix (already shipped)

The app carries its own copy of the option vocabulary
(`lib/core/localization/attribute_label_localizer.dart`) and falls back to your
label for anything it does not recognise — a brand, or an option an admin adds
after this release. **Brands are deliberately not translated.** Once the API
serves translated labels this class and its `attr_option_*` /`attr_label_*` keys
can be deleted.

A consequence worth knowing: for the 47 attribute slugs the app now ships labels
for, **the app's label wins over yours**. Renaming an attribute in the admin will
not show through in the app until the API is fixed and the override removed.

---

## C3 — Categories, Terms and Privacy not localized for `fr` / `es` / `zh`

**Reported:** in French and Spanish (and what the tester called "Japanese" — see
the note at the end), categories on the home and sell screens are untranslated,
as are Terms & Conditions and Privacy Policy including their detail content.

### C3a — Category names: `es` and `zh` are being served **Arabic**

This is the serious one. Same request, four locales:

```
GET /api/categories

lang: en → ["Accommodation", "Cars", "Electronics", "Furniture & Home", ...]
lang: ar → ["السكن", "سيارات", "الكترونيات", "الأثاث والمنزل", ...]
lang: fr → ["Hébergement", "Voitures", "Electronics", "Meubles et maison", ...]
lang: es → ["عقارات", "مركبات", "Electronics", "Furniture & Home", ...]
lang: zh → ["عقارات", "مركبات", "Electronics", "Furniture & Home", ...]
```

Three distinct faults:

1. **`es` and `zh` fall back to Arabic** for categories 7 and 8. A Spanish user
   sees Arabic tiles on the home screen. This looks like a missing-translation
   fallback chain that lands on `default_language` (`GET /settings` reports
   `"default_language": "ar"`) instead of on English.
2. **`fr` is partial** — "Electronics" and "Services" are untranslated.
3. **Attribute labels are English for `fr`, `es` and `zh`** — only `ar` has them.

Also note the two Arabic spellings disagree with each other: the `ar` response
says `السكن` and `سيارات`, the `es`/`zh` fallback says `عقارات` and `مركبات`.

**What we need:** `name` localized from `lang` for all five locales (`en`, `ar`,
`fr`, `es`, `zh`), on main categories *and* children, with the fallback chain
ending at **English**, never at Arabic.

**Interim app-side fix (already shipped):** the app translates the ~44 category
and subcategory names it knows, keyed on the name, with aliases for the Arabic
and French spellings the API actually returns
(`lib/core/localization/category_label_localizer.dart`). A category added after
this release keeps your name. **A category with no `slug` in the response is
matched by name, so renaming one in the admin will silently drop it back to the
backend name** — please add a stable `slug` to the category resource whether or
not you fix the translations:

```json
{ "id": 7, "slug": "accommodation", "name": "Alojamiento", "listing_fee": 2.99 }
```

### C3b — Terms & Conditions and Privacy Policy content

```http
GET /api/terms/current
lang: fr
```
```json
{
  "status": true,
  "data": {
    "version": "1.0",
    "title": "Terms and Conditions of Use",
    "content": "Terms & Conditions (EN)",
    "effective_at": "2026-08-19T08:12:15+00:00",
    "accepted": null
  }
}
```

English for every `lang` value tried. `GET /settings` → `terms_conditions`
returns **Arabic** regardless of `lang`.

This is legal prose and cannot be translated in the app — the app must show what
you send, or it would be presenting an unapproved translation as the terms the
user is agreeing to. **This one is entirely yours.**

**What we need:**

```http
GET /api/terms/current
lang: fr
```
```json
{
  "status": true,
  "data": {
    "version": "1.0",
    "locale": "fr",
    "title": "Conditions générales d'utilisation",
    "content": "<translated body>",
    "effective_at": "2026-08-19T08:12:15+00:00",
    "accepted": null
  }
}
```

Requirements:

- `title` and `content` localized from `lang`, falling back to **English**.
- `version` must stay the **same string across locales** — it is what the app
  sends back to `POST /terms/accept`, and a per-locale version would make an
  acceptance recorded in French look stale in English.
- Add `locale` to the response so the app can tell the user when it is showing a
  fallback translation rather than their own language.
- Same for the Privacy Policy. There is currently **no privacy endpoint at all**:
  `GET /api/policies` and `GET /api/privacy-policy` both answer
  `{"status": false, "message": "The route ... could not be found."}`, and the
  app is showing bundled copy. A `GET /api/privacy/current` mirroring
  `terms/current` would let it show the real thing.

### Note: there is no Japanese in this app

The bug report lists Japanese. The app ships **English, Arabic, French, Spanish
and Simplified Chinese** (`en`, `ar`, `fr`, `es`, `zh`) and has no `ja` locale —
see `lib/main.dart`. We have assumed the tester meant Chinese and fixed `zh`.
**Please confirm with the client whether Japanese is actually wanted**; adding it
means a new `ja.json` in the app *and* Japanese translations for categories,
attribute options and the terms on your side.

---

## D1 — RTL arrow direction

**No backend change needed.**

Root cause was app-side and is fixed: Material's chevron icons already carry
`matchTextDirection: true` and mirror themselves under RTL, and the Account
screen was picking `chevron_left` by hand *on top of* that, mirroring it twice.

---

## E1 — Editing a published ad

**Reported:** after publishing an ad there is no way to edit it.

### What we found

**There is no update endpoint.** Probed on 2026-09-16:

| Request | Response |
|---|---|
| `PUT /api/ads/1` | `405` — "Supported methods: GET, HEAD." |
| `POST /api/ads/1` | `405` — "Supported methods: GET, HEAD." |
| `PUT /api/v2/ads/1` | `405` — "Supported methods: GET, HEAD." |
| `POST /api/v2/ads/1` | `405` — "Supported methods: GET, HEAD." |
| `PUT /api/my-ads/1` | `405` — "Supported methods: GET, HEAD, DELETE." |
| `POST /api/my-ads/1` | `405` — "Supported methods: GET, HEAD, DELETE." |

So a seller's only option today is to delete the ad and post a new one — paying
the listing fee again.

### What we need — proposed endpoint

The Edit flow is **built and shipped in the app**. It reuses the post-ad form,
prefilled from `GET /ads/{id}`, runs the identical validation (including the A1/A2
date rules), and calls the endpoint below. Until it exists the app catches the
404/405 and tells the user editing is not available yet rather than showing a raw
error — so this can ship on your side without an app release.

```http
PUT /api/v2/my-ads/{id}          ← PROPOSED, does not exist yet
Authorization: Bearer {token}
Content-Type: application/json
lang: ar
```

JSON, not multipart: the body carries a nested `attributes` object and no file.
(The same reason `POST /v2/ads/draft` is JSON.)

**Request body** — the same field set as the draft endpoint, minus everything to
do with publishing:

```json
{
  "main_category_id": 7,
  "sub_category_id": 34,
  "title": "Room in shared flat — now with parking",
  "price": "180",
  "description": "Bright double room, five minutes from campus.",
  "currency": "GBP",
  "city_id": 1,
  "postcode": "LS2 9JT",
  "location_name": "Leeds",
  "latitude": 53.81,
  "longitude": -1.49,
  "is_negotiable": true,
  "attributes": {
    "property_type": "Flat",
    "availability_from": "2026-10-01",
    "availability_until": "2027-06-30",
    "features": "Parking,Garden"
  }
}
```

The app deliberately does **not** send `confirm_publish`, `coupon_code`, or any
photo. Editing must not re-run the publish flow, must not spend a coupon, and
must not charge anything — the listing is already paid for.

**Success:**

```http
HTTP/1.1 200 OK
```
```json
{
  "status": true,
  "message": "Ad updated successfully",
  "data": { "ad": { "id": 123, "status": "published", "...": "full AdResource" } }
}
```

Returning the full ad resource lets the app refresh the list without a second
read.

**Errors:**

| Status | Body | Meaning |
|---|---|---|
| `403` | `{"status": false, "message": "...", "data": {"error_code": "not_owner"}}` | Not the seller's ad |
| `409` | `{"status": false, "message": "A sold listing cannot be edited."}` | See E2 |
| `422` | `{"status": false, "message": "...", "errors": {"attributes.availability_until": ["..."]}}` | Validation, including the A1/A2 rules |

**Validation the server must enforce:** everything `POST /v2/ads/draft` enforces
— title, price bounds, UK postcode format, required attributes, and the
availability date rules from A1/A2 above.

### Open questions on E1

1. **Does an edit need re-moderation?** If a published ad going back into review
   is the intended behaviour, return the ad with `status: "pending"` and the app
   will show it accordingly — but say so, because the seller needs telling that
   their listing just went offline.
2. **Which fields may be edited after publication?** Changing the price on a live
   listing someone is already negotiating over may not be acceptable. If some
   fields are frozen, refuse them with a 422 naming the field rather than
   silently ignoring them.
3. **Photos.** The current scope does not edit photos — there is no endpoint to
   remove one (`POST /ads/{id}/images` adds; nothing deletes). If the client wants
   photo editing, we need `DELETE /api/ads/{id}/images/{imageId}` and a way to
   reorder (`PUT /api/ads/{id}/images` with an ordered id list).

---

## E2 — Sold → Active (investigation only, no change requested)

**Reported:** an ad changed to "Sold" cannot be changed back to Active or
republished. Still under discussion with the client — **nothing was changed.**

### Current status flow

```
draft ──publish──▶ pending ──fee paid──▶ published ──pause───▶ paused
                                              │                  │
                                              │                  └─activate─▶ published   (free)
                                              │
                                              ├──30 days───────▶ expired ──extend──▶ published  (£0.99)
                                              │
                                              └──mark-sold─────▶ sold ──sell-again──▶ new ad    (full fee)
```

### Where the restriction lives

**Mostly the backend, mirrored in the app.**

- The app renders the action buttons from `available_actions`, which you send on
  each `MyAdResource`. For a sold ad that list does not contain `activate`, so no
  Activate button is drawn. The app adds no rule of its own here —
  `MarketplaceListing.canActivate` is literally
  `availableActions.contains('activate')`.
- The app has exactly two sold-specific rules of its own:
  `canDelete => status != 'sold'` and `canSellAgain => ... || status == 'sold'`.
  The second is what puts the "Sell again" button on a sold ad.
- `POST /v2/my-ads/{id}/activate` is the free path back for a **paused** ad
  inside its paid period. Per the existing code comments it answers 422 for
  states it does not accept.
- `POST /v2/my-ads/{id}/sell-again` copies a sold ad into a **new listing with a
  new id**, and charges a **full new listing fee**.

So today, "un-selling" costs the seller the full fee again and loses the ad's
id, URL, `public_id` and share links.

### What supporting Sold → Active would take

**Backend**

1. Decide whether `activate` becomes valid from `sold`. Cheapest version:
   accept `sold` in `POST /v2/my-ads/{id}/activate` when the listing is still
   inside its paid 30-day window, and add `activate` to `available_actions` for
   those ads.
2. Decide what happens to the sale record. `POST /my-ads/{id}/mark-sold` takes a
   `buyer_id` and may have created an order/rating prompt. Reversing the status
   without reversing that leaves a buyer credited with a purchase that did not
   happen — and the rating flow hangs off it.
3. Decide the time limit. A listing sold two months ago is outside its paid
   period; reactivating it for free would be giving away a listing.

**Payment implications**

| Case | Fee |
|---|---|
| Sold, still inside the paid 30 days | Should be **free** — the seller already paid for that window |
| Sold, paid window expired | Should cost the **extend** fee (£0.99), not a new listing fee |
| Sold, seller wants a fresh 30 days | The existing `sell-again` full fee is right |

If reactivation is free inside the window, a seller could mark-sold and reactivate
repeatedly. That is not obviously abusable — the window is fixed either way — but
it is worth a rate limit.

**App**

Small, once the backend allows it: the Activate button already renders from
`available_actions`, so adding `"activate"` to a sold ad's list would light it up
with no app release. The only app change needed is a confirmation dialog — going
from Sold back to Active should ask, because it may undo a buyer's record.

### Recommendation

If the client wants this, the cleanest shape is **an explicit "Relist this ad"
action on a sold listing** that reuses the ad's own id, is free inside the paid
window and costs the extend fee outside it — rather than overloading `activate`.
That keeps the ad's URL and share links working, which `sell-again` does not.

---

## F1 — Coupon refusals give no reason

**Reported:** after using a discount code three times it stops working, and the
user is not told why.

### What we found

`POST /coupons/validate` (the call the app makes when a code is typed before a
draft exists) answers a refusal as an ordinary 4xx with no machine-readable
reason. The app had nothing to map, so it showed the generic validation message.
Note that the *publish* endpoint already does this correctly — it sends
`publication.coupon_error` with a reason code — so this is an inconsistency
between two endpoints rather than a missing concept.

### What we need

`POST /coupons/validate` must return a distinct code **and** a message for each
failure reason.

```http
POST /api/coupons/validate
Authorization: Bearer {token}
Content-Type: application/json
lang: ar
```
```json
{ "code": "STUDENT10", "ad_id": 123 }
```

**Success (unchanged):**

```json
{
  "status": true,
  "data": {
    "code": "STUDENT10",
    "applied": true,
    "discount_amount": 0.30,
    "formatted_discount": "£0.30",
    "formatted_final": "£2.69"
  }
}
```

**Refusal — proposed shape:**

```http
HTTP/1.1 422 Unprocessable Content
```
```json
{
  "status": false,
  "message": "لقد استخدمت هذا الكود بالفعل.",
  "data": {
    "coupon_error": "already_used",
    "code": "STUDENT10"
  }
}
```

`data.coupon_error` must be one of:

| Code | Meaning | App shows (key) |
|---|---|---|
| `invalid` | No such code, or inactive | `coupon_error_invalid` |
| `not_started` | Valid, but its start date has not arrived | `coupon_error_not_started` |
| `expired` | Past its end date | `coupon_error_expired` |
| `exhausted` | **Global** usage limit reached across all users | `coupon_error_exhausted` |
| `already_used` | **This user** has used it up to their per-user limit | `coupon_error_already_used` |
| `min_amount` | Listing fee is below the coupon's minimum | `coupon_error_min_amount` |

`exhausted` and `already_used` must be told apart — they are the difference
between "come back never" and "this one is not for you", and the tester's bug
(three uses then silence) is specifically the second.

The app also reads these codes from the publish response, which already sends
them — please keep the two vocabularies identical:

```json
{
  "publication": {
    "published": false,
    "payment_required": true,
    "amount": 2.99,
    "coupon": { "applied": false },
    "coupon_error": "already_used",
    "coupon_warning": "This code has already been used on your account."
  }
}
```

**Validation rules the server must enforce:** per-user redemption count against
the coupon's per-user limit, global redemption count against its total limit,
`starts_at` / `expires_at` window, and minimum listing-fee amount — each mapping
to its own code above rather than collapsing into one refusal.

### App-side (already shipped)

The app maps all six codes to localized copy in all five languages, and accepts a
handful of synonyms (`usage_limit_reached`, `limit_reached`, `already_redeemed`,
`minimum_not_met`, `not_found`) in case your naming differs. Until the code
arrives it falls back to classifying your English sentence — a stopgap that only
matches unambiguous phrases like "usage limit" and "already used", and one we
would like to delete as soon as the codes land.

---

## Summary of what is blocking the client

| Priority | Item | Why |
|---|---|---|
| 1 | C3a — `es`/`zh` served Arabic category names | Visibly broken on the home screen for two languages |
| 2 | A1/A2 server-side date validation | Front-end guard alone; API is open |
| 3 | E1 — `PUT /v2/my-ads/{id}` | Feature is built and waiting; sellers currently repay to fix a typo |
| 4 | F1 — coupon error codes | App has a sentence-matching stopgap in place |
| 5 | C1/C2 — translated option labels | App has a full override in place; this removes ~130 keys of duplication |
| 6 | C3b — localized Terms/Privacy | Legal text; cannot be fixed in the app at all |

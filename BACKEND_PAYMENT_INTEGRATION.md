# Backend Requirements — Listing-Fee Payment (Stripe)

**Audience:** backend team.
**Status:** the Flutter app fully implements the payment flow described here and ships with it today. Nothing below requires an app release — every gap is server-side. Findings were verified live against `https://test-api.unitill.uk/api` on **2026-08-02**.

---

## TL;DR — what is broken today

Publishing a paid ad from the app currently dead-ends on a **"Card payment isn't available in this build"** screen. The backend correctly creates the PaymentIntent and returns its `client_secret`, but the app has no Stripe **publishable key** to open the payment sheet with, and the backend does not serve one.

| # | Gap (verified live) | Fix | Priority |
|---|---|---|---|
| 1 | `GET /api/settings` returns no Stripe publishable key | Add `stripe.publishable_key` to the settings payload | **Blocker** |
| 2 | `GET /api/ads/{id}/payment/status` → `404 Not Found` | Implement the endpoint (contract below) | **High** |
| 3 | `publication` block has no `payment_status` field | Add it (vocabulary below) | Medium |
| 4 | Publish response `message` says *"Ad published successfully"* while `published: false` | Make the message truthful | Low (cosmetic — the app ignores the message) |

---

## 1. Serve the Stripe publishable key in `GET /api/settings` — **Blocker**

The app deliberately ships **without** a compiled-in Stripe key so the backend owns the payment configuration end to end (same pattern already used for Pusher, which `GET /api/settings` does serve). On every settings load the app picks the key up at runtime and configures the Stripe SDK — no rebuild, no store release.

Add to the `data` object of `GET /api/settings`:

```json
{
  "data": {
    "stripe": {
      "publishable_key": "pk_test_..."
    }
  }
}
```

The app accepts any of these shapes (first non-empty wins), so pick whichever fits your serializer:

1. `data.stripe_publishable_key`
2. `data.stripe.publishable_key` ← recommended, mirrors the existing `pusher` object
3. `data.payment.publishable_key`

**Rules:**

- The publishable key **must belong to the same Stripe account (and same live/test mode)** as the secret key that creates the PaymentIntents. A `pk_test_…` key cannot confirm a PaymentIntent created with a live secret key, and vice versa — the sheet fails at confirmation.
- Publishable keys are not secrets; serving them in a public settings endpoint is Stripe's intended model.
- Serve it unconditionally (guests included) — the settings endpoint is fetched before login.

**Effect in the app:** the moment settings arrive with a key, the blocked screen turns into the normal **Pay Now** flow using the `client_secret` you already return. This alone unblocks payments.

---

## 2. Implement `GET /api/ads/{id}/payment/status` — **High**

Currently returns `404 Not Found`. The app calls it (owner-authenticated) in two situations:

- **Re-opening a pending ad** ("My Ads" → unpaid ad → pay step): the Stripe webhook may have settled the ad while the user was away, so the app re-reads reality instead of trusting stale local state.
- **After any publish response that lacks a `publication` block**: the app never assumes "no block = published"; it asks.

### Contract

`GET /api/ads/{id}/payment/status` — auth required, owner only (`403` otherwise, `404` for a missing ad).

```json
{
  "status": true,
  "data": {
    "publication": {
      "published": false,
      "payment_required": true,
      "amount": 6,
      "currency": "GBP",
      "payment_intent_id": "pi_...",
      "client_secret": "pi_..._secret_...",
      "payment_status": "requires_payment",
      "coupon": null
    }
  },
  "message": "..."
}
```

- Must be **read-only and side-effect free** (no new PaymentIntent, no publish attempt). The app calls it freely on screen entry.
- Reuse the existing PaymentIntent; include its current `client_secret` so a retry can pay without a new intent.
- Once the ad is live: `published: true`, `payment_required: false`, `payment_status: "paid"` (or `"free"` / `"waived"` / `"coupon"`).

---

## 3. The `publication` block — full contract

Returned by `POST /api/ads`, `POST /api/ads/{id}/publish`, `POST /api/ads/{id}/payment/complete`, and `GET /api/ads/{id}/payment/status`. This is the **only** thing the app trusts about publication state — never the `message`, never the ad's presence in a list.

| Field | Type | Required | Meaning |
|---|---|---|---|
| `published` | bool | ✅ | The single source of truth for "the ad is live". |
| `payment_required` | bool | ✅ | Fee outstanding; the ad stays `draft`/`pending` until settled. |
| `amount` | number | ✅ when payment due | Major units (`6` = £6.00). |
| `currency` | string | ✅ when payment due | ISO code, e.g. `GBP`. |
| `payment_intent_id` | string | ✅ when payment due | Reused across retries — never create a second intent for the same ad. |
| `client_secret` | string | ✅ when payment due* | Confirmed on-device by the Stripe SDK. |
| `checkout_url` | string | optional* | Hosted-payment alternative — see §5. Also accepted as `payment_url` / `hosted_url` / `redirect_url`. |
| `payment_status` | string | recommended | `requires_payment` while unsettled; `paid` / `free` / `waived` / `coupon` once settled. |
| `coupon` | object\|null | optional | Echo of the applied coupon (`formatted_final` is displayed as the total). |

\* At least one of `client_secret` or `checkout_url` must be present when `payment_required` is true. If both are missing the app treats it as a hard error ("payment intent missing") and blocks the pay button — it will never silently pretend success.

**Why `payment_status` matters (gap #3):** it lets the app distinguish *"paid — waiting for the webhook to flip the ad"* (shows "payment received, publishing…" + a re-check button) from *"never paid"* (shows Pay Now). Without it, a user who paid milliseconds before the webhook lands could be shown a pay button again. The app already parses it; today the field is simply absent.

---

## 4. Payment completion — webhook first, `payment/complete` as fallback

Order of truth:

1. **Stripe webhook** (`payment_intent.succeeded` → publish the ad) is the primary settlement path. Idempotent, signed, survives the user killing the app mid-payment.
2. **`POST /api/ads/{id}/payment/complete`** is the app's fallback right after the sheet succeeds, so the user doesn't stare at a pending screen waiting for the webhook.

Requirements for `payment/complete` (already responds — `422` observed for an unpaid intent, which is correct):

- Body: `payment_intent_id` (optional — sent as form data when the app has it). Never trust it blindly: **retrieve the PaymentIntent from Stripe server-side and check it succeeded and matches this ad** before publishing.
- **Idempotent**: calling it twice, or after the webhook already published, must return the current `publication` block with `published: true` — never an error, never a second charge.
- Unpaid intent → `422` with a truthful message (current behaviour — keep it).
- Success → the full `publication` block (§3).

---

## 5. Optional alternative: hosted checkout (`checkout_url`)

If you ever prefer collecting the card on your own page (Stripe Checkout Session / Payment Link), return the URL in the `publication` block as `checkout_url`. The app then:

1. opens it in the browser (no publishable key needed on the device at all),
2. shows an **"I have paid"** re-check that calls `payment/complete` / `payment/status`,
3. publishes only when the backend confirms.

This is *instead of*, not in addition to, §1 — but §1 (in-app sheet) is the better UX and is what the app leads with. If both `client_secret` and `checkout_url` are returned, the hosted page wins.

---

## 6. End-to-end flow (what the app actually does)

```
POST /ads/draft            → ad {id, status: "draft"}
POST /ads/{id}/images      → one per photo
POST /ads/{id}/publish     → publication block
   ├─ published: true                    → success screen
   ├─ payment_required + client_secret   → Stripe payment sheet (needs §1 key)
   │     └─ sheet OK → POST /ads/{id}/payment/complete → published: true → success
   │        (webhook may beat it — endpoint must be idempotent, §4)
   ├─ payment_required + checkout_url    → browser → "I have paid" → payment/complete
   └─ no publication block               → GET /ads/{id}/payment/status (§2) decides
Re-entering a pending ad   → GET /ads/{id}/payment/status (§2)
```

Retry safety on the app side (for context): once a draft exists the app never re-submits it — the pay button only retries payment or confirmation, so a charged card can never produce a duplicate ad or a double charge. The backend must uphold the mirror guarantees: one PaymentIntent per ad, idempotent completion.

---

## 7. Acceptance checklist

With Stripe test keys (`4242 4242 4242 4242`, any future expiry/CVC) and `stripe listen --forward-to {base_url}/stripe/webhook`:

- [ ] `GET /api/settings` contains `stripe.publishable_key`, same account/mode as the secret key.
- [ ] Publish a paid ad in the app → Stripe sheet opens (no "unavailable" screen) → pay → ad goes `active`.
- [ ] Cancel the sheet, pay again → same `payment_intent_id`, single charge.
- [ ] Kill the app after paying, before `payment/complete` → webhook publishes the ad; reopening the ad shows it live.
- [ ] `GET /api/ads/{id}/payment/status` answers per §2 for: unpaid draft, paid-awaiting-webhook, published, someone else's ad (403).
- [ ] `POST /api/ads/{id}/payment/complete` twice in a row → both return `published: true`, one charge.
- [ ] Free-quota / 100%-coupon publish → `published: true` immediately, `payment_required: false`.
- [ ] Response `message` no longer claims success for an unpublished ad (#4).

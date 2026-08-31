# Coupon-aware payment retry — mobile integration notes (2026-08-31)

## What changed and why

Previously, once a listing had a Stripe PaymentIntent attached, retrying payment (after a
decline, a cancelled sheet, or just checking status) silently reused the same intent forever.
The discount-code UI could disappear on retry with no explanation, a cancelled intent could be
handed back to the PaymentSheet and crash it, and there was no way to apply a different coupon
after a failed attempt.

All of that is now handled server-side. **No mobile changes are required to keep working as
before** — every endpoint below is backward compatible if you never send `coupon_code`. The
notes below are for anyone who wants to build the "change/remove coupon" UX on top of it.

Endpoints affected:
- `POST ads/{id}/publish`
- `POST ads/{id}/payment/complete`
- `GET ads/{id}/payment/status`
- `POST my-ads/{id}/extend` (extension flow)

## The `publication` object

All four endpoints return the same shape inside `publication`, now with two additional optional
fields:

```jsonc
{
  "publication": {
    "published": false,
    "payment_required": true,
    "amount": 2.99,
    "currency": "GBP",
    "payment_status": "requires_payment",
    "payment_intent_id": "pi_...",
    "client_secret": "pi_..._secret_...",
    "coupon": {
      "applied": true,
      "code": "SAVE20",
      "discount_amount": 0.60
    },
    "coupon_warning": null,
    "coupon_error": null
  }
}
```

| Field | Type | Meaning |
|---|---|---|
| `coupon` | object \| `null` | The coupon currently applied to this attempt, if any. **Always present now** — no more silently disappearing on retry. |
| `coupon_warning` | string \| `null` | Human-readable (translated) message when something coupon-related needs the user's attention — an expired coupon was auto-removed, or a requested code failed to apply. Show this as a toast/banner. |
| `coupon_error` | string \| `null` | Machine-readable reason code, only set when a coupon you explicitly submitted failed. One of: `invalid`, `not_started`, `expired`, `exhausted`, `already_used`, `min_amount`. Use this instead of parsing `coupon_warning` if you want your own copy per locale. |

`payment_intent_id` / `client_secret` may change between calls if the backend had to recreate the
Stripe intent (declined card, cancelled coupon, dead intent). **Always re-initialize the
PaymentSheet with whatever `client_secret` came back on the latest call** — don't cache and reuse
an old one across retries.

## How to trigger each behavior

### 1. Plain retry / status check — leave `coupon_code` out of the request entirely

```
GET ads/{id}/payment/status
POST ads/{id}/payment/complete
{ "payment_intent_id": "pi_..." }
```

The backend re-validates whatever coupon is already attached:
- Still valid → returned as-is in `coupon`, nothing changes.
- Expired / deactivated since it was applied → automatically detached, price falls back to the
  original amount, `coupon_warning` explains why. Show this to the user so the price change isn't
  a surprise.

### 2. Apply a different coupon on retry — send `coupon_code` with a new value

```json
{ "coupon_code": "NEWCODE20" }
```

Only takes effect if the previous attempt has actually concluded (declined or already cancelled).
If a charge could still be in flight (`processing`, or mid-3DS `requires_action`), the backend
will **not** touch anything and just returns the current state — retry again shortly.

### 3. Remove the coupon on retry — send `coupon_code` as `null` or an empty string, explicitly

```json
{ "coupon_code": null }
```
or
```json
{ "coupon_code": "" }
```

**The key must be present in the JSON body.** Omitting the field entirely means "don't change
anything" (case 1 above); sending it as `null`/`""` means "remove the coupon, I mean it." Same
distinction, same endpoints.

## Edge cases worth knowing about

- **Coupon discounts below Stripe's minimum charge** (e.g. a coupon brings £0.99 down to a few
  pence — GBP's floor is £0.30): the backend treats this as fully covered and publishes the ad
  immediately with no Stripe step at all. `publication.published` will be `true`, `amount: 0`,
  `payment_status: "paid"`. No `client_secret` will be present — don't try to open a payment
  sheet in this case, just show success.
- **A fully coupon-covered ad has `payment_status: "paid"`**, same as a real Stripe payment,
  for consistency across the app. The only place this is distinguished internally is refund
  eligibility (a coupon-covered ad has no `stripe_payment_intent_id`, so there's nothing to
  refund) — nothing you need to handle differently on the client.
- **A cancelled/dead PaymentIntent is never handed back to you anymore.** If `GET
  ads/{id}/payment/status` used to occasionally return a `client_secret` for an intent Stripe
  considers `canceled` (causing `PaymentSheet cannot set up a PaymentIntent in status "canceled"`),
  that's fixed — the backend now recreates it transparently before responding.

## Suggested UI flow for a "change coupon" affordance

1. On the payment retry/failure screen, if `coupon.applied` is true, show it as an editable chip
   ("SAVE20 applied — Change / Remove").
2. "Change" → text field for a new code → call the retry endpoint with `coupon_code: "<new code>"`.
3. "Remove" → call the retry endpoint with `coupon_code: null`.
4. Always re-read `amount` and `client_secret` from the response and reinitialize the payment
   sheet before showing it again.
5. If `coupon_warning` is present on any response (including a plain status check), surface it —
   most commonly this fires when a coupon quietly expired between the first attempt and now.

## Testing this in Postman

The existing `ads/{id}/payment/complete` and `ads/{id}/publish` requests in the collection still
work unchanged. To exercise the new coupon behavior, add `coupon_code` to the request body — the
collection itself doesn't need new requests, just a body edit on the ones you already have.

**Body tab → raw → JSON** (not form-data — a disabled/unchecked form field is the same as
omitting the key entirely, which will *not* trigger a coupon change):

```
POST {{base_url}}/ads/:id/payment/complete
Content-Type: application/json
Authorization: Bearer {{token}}
```
```json
{
  "payment_intent_id": "pi_3UAUn1LLcWlWwAF70qPg3EWK",
  "coupon_code": "NEWCODE20"
}
```

To remove the coupon instead of swapping it, same request with:
```json
{
  "coupon_code": null
}
```

To just retry/check status without touching the coupon, drop the `coupon_code` key entirely (not
send it as `""`/`null` — that counts as an explicit removal, see above).

**What to look for in the response** — the fields that are new since this update, all inside
`data.publication`:

| Before this update | After this update |
|---|---|
| `coupon` was `null` on every retry, even if a coupon really was applied | `coupon` now reflects the real state: `{"applied": true, "code": "...", "discount_amount": ...}` or `null` if none |
| No `coupon_warning` field existed | `coupon_warning` — a message to show the user when the price just changed unexpectedly (expired coupon auto-removed, or the code you just sent didn't apply) |
| No `coupon_error` field existed | `coupon_error` — the raw reason code (`invalid`, `expired`, `exhausted`, `already_used`, `not_started`, `min_amount`) when a submitted `coupon_code` failed |
| A `canceled` PaymentIntent could be returned as-is, breaking the mobile PaymentSheet | `payment_intent_id`/`client_secret` are now always for a usable intent — re-read both from every response |
| Retrying never reduced the price below what Stripe could actually charge | If a coupon drops the fee below Stripe's minimum (e.g. a few pence), the response comes back with `"published": true, "amount": 0, "payment_status": "paid"` and no `client_secret` at all — treat that as an immediate success, not a payment step |

Quick end-to-end test sequence in Postman:
1. Create/publish an ad with a coupon that gives a small discount → note the `client_secret`.
2. Fail the payment on purpose (a Stripe test card that declines, e.g. `4000000000000002`).
3. Call `payment/complete` again with a **different** valid `coupon_code` in the body → confirm
   `coupon.code` changed and `payment_intent_id`/`client_secret` are both different from step 1.
4. Call it again with `"coupon_code": null` → confirm `coupon` is now `null` and `amount` is back
   to the full listing fee.
5. Manually expire/deactivate a coupon from the admin dashboard while it's still attached to a
   pending ad, then call `GET ads/{id}/payment/status` with no body → confirm `coupon` becomes
   `null` on its own and `coupon_warning` explains why.

# Mobile payment backend update

Date: 2026-07-26  
Branch reviewed: `integration/salman-contract-takwa-features`

This document describes the backend payment changes made for the mobile
application. The `/storage` deployment issue is intentionally outside the scope
of this update.

## Configuration choice

The backend uses Stripe PaymentIntents and the native mobile Stripe SDK. It does
not use a hosted Stripe Checkout page.

`GET /api/settings` now includes:

```json
{
  "status": true,
  "data": {
    "post_price": "5.00",
    "free_ads_per_user": 2,
    "stripe_publishable_key": "pk_test_..."
  }
}
```

The mobile app should initialize Stripe from `stripe_publishable_key`. The
publishable key can be null if `STRIPE_KEY` is not configured on the server; in
that case the app must not open the payment sheet.

Required server environment variables:

```dotenv
STRIPE_KEY=pk_test_or_live_key
STRIPE_SECRET=sk_test_or_live_key
STRIPE_WEBHOOK_SECRET=whsec_webhook_secret
STRIPE_CURRENCY=gbp
```

The default seed configuration is now:

```text
post_price = 5.00
free_ads_per_user = 2
```

Production values can still be changed through the settings table/dashboard.

## Authoritative publication block

The mobile app must continue treating `publication.published` as the only
authority on whether an ad is live.

The publication block has a consistent shape:

```json
{
  "published": false,
  "payment_required": true,
  "amount": 5.0,
  "currency": "GBP",
  "payment_status": "requires_payment"
}
```

Possible settled payment statuses are:

- `paid`
- `free`
- `waived`
- `coupon`

An ad is publicly visible only after its ad status becomes `published`.

## Create or publish an ad

These endpoints use the same publication rules:

```http
POST /api/ads
POST /api/ads/{id}/publish
```

When payment is required:

```json
{
  "status": true,
  "message": "Ad saved. Payment is required before it can be published.",
  "data": {
    "publication": {
      "published": false,
      "payment_required": true,
      "amount": 5.0,
      "currency": "GBP",
      "payment_intent_id": "pi_...",
      "client_secret": "pi_..._secret_..."
    },
    "ad": {
      "status": "pending"
    }
  }
}
```

When the free quota, a full coupon discount, or a zero listing price covers the
listing:

```json
{
  "status": true,
  "message": "Ad published successfully",
  "data": {
    "publication": {
      "published": true,
      "payment_required": false
    },
    "ad": {
      "status": "published"
    }
  }
}
```

The message no longer says that an unpaid ad was published.

Calling the publish endpoint again for a pending ad reuses its existing
PaymentIntent and returns its client secret. It does not create a second charge.

## Complete/check Stripe payment

```http
POST /api/ads/{id}/payment/complete
Authorization: Bearer <access token>
```

The optional `payment_intent_id` sent by the app is not trusted. The backend
uses the PaymentIntent already attached to the authenticated owner's ad and
retrieves its current status directly from Stripe.

Successful payment:

```json
{
  "status": true,
  "message": "Ad published successfully",
  "data": {
    "publication": {
      "published": true,
      "payment_required": false,
      "amount": 5.0,
      "currency": "GBP",
      "payment_status": "paid"
    },
    "ad": {
      "status": "published"
    }
  }
}
```

Payment not settled yet:

```json
{
  "status": true,
  "message": "Payment has not completed yet. The ad is still not published.",
  "data": {
    "publication": {
      "published": false,
      "payment_required": true,
      "amount": 5.0,
      "currency": "GBP",
      "payment_status": "requires_payment"
    },
    "ad": {
      "status": "pending"
    }
  }
}
```

An unsettled Stripe payment now returns HTTP 200 with the true publication
state. It is not treated as an API failure.

The endpoint is idempotent:

- Repeating it after a successful payment returns the same published state.
- It does not create another PaymentIntent or charge.
- A published/paid ad can return success without contacting Stripe again.
- An unsettled, cancelled, or failed intent never publishes the ad.
- Stripe amount and currency must match the listing before publication.

The signed Stripe webhook uses the same idempotent publication service.

## Read-only payment status

A new owner-only endpoint is available:

```http
GET /api/ads/{id}/payment/status
Authorization: Bearer <access token>
```

Example:

```json
{
  "status": true,
  "data": {
    "publication": {
      "published": false,
      "payment_required": true,
      "amount": 5.0,
      "currency": "GBP",
      "payment_status": "requires_payment"
    }
  }
}
```

The endpoint accepts either the numeric ad ID or its public ID. It only returns
ads owned by the authenticated user.

Suggested mobile behavior:

1. Call this endpoint when reopening a pending-payment screen.
2. If `published` is true, show the success/live state.
3. If `payment_required` is true and a new client secret is needed, call
   `POST /api/ads/{id}/publish` to resume the existing PaymentIntent.
4. After Stripe confirmation, call the completion endpoint again.

## My Ads

Draft and pending-payment ads are returned under:

```http
GET /api/my-ads?status=inactive
```

Pending-payment item fields now include:

```json
{
  "status": "pending",
  "payment_status": "requires_payment",
  "listing_fee": 5.0,
  "payment_required": true,
  "available_actions": ["see_details", "pay", "delete"]
}
```

Draft item actions:

```json
{
  "status": "draft",
  "payment_required": false,
  "available_actions": ["see_details", "publish", "delete"]
}
```

Both statuses can be deleted with:

```http
DELETE /api/my-ads/{id}
```

Deletion remains owner-scoped. Sold ads cannot be deleted.

## Public visibility

Draft, pending, rejected, paused, sold, and expired ads remain excluded from:

- `GET /api/ads`
- Search and filter combinations
- `GET /api/home`
- Non-owner `GET /api/ads/{id}`

The owner can still open their own non-public ad.

The admin API can no longer change an unpaid ad directly to `published`.
Publishing through the admin status endpoint requires one of the settled
payment states listed above.

## Multiselect ad attributes

Draft and full ad creation now accept array-valued attributes:

```json
{
  "attributes": {
    "condition": "used",
    "features": ["Parking", "Garden"]
  }
}
```

Each multiselect value is stored separately. This allows public filters to match
any selected value and lets ad detail responses display all selected values.

The existing migration
`2026_07_27_000200_extend_category_attributes_for_filter_engine.php` provides
the non-unique attribute index required for multiple values.

## Authentication confirmation

Successful login responses include:

- `access_token`
- `access_token_expires_at`
- `refresh_token`
- `refresh_token_expires_at`

Initial registration does not issue tokens because the account is not verified
yet. Successful email verification issues both the access and refresh tokens.

## Deployment steps

After deploying the code:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Confirm that the server environment contains all four Stripe variables, then
verify:

```http
GET /api/settings
GET /api/ads/{pending-ad-id}/payment/status
```

No `/storage` configuration or deployment change is included in this update.

## Automated verification

The added test coverage verifies:

- Pending Stripe payments remain unpublished.
- Successful payments publish exactly once.
- Completion retries are idempotent.
- Payment status is owner-readable.
- Pending ads are absent from public search, home, and non-owner detail.
- Owners can see and delete abandoned drafts.
- Multiselect draft attributes store each selected value.
- Draft/pending My Ads actions expose the correct controls.
- The public ad scope requires `published` status and a valid expiry.

# Salman API Contract + Takwa Feature Integration Plan

## 1. Objective

Use `takwa-backend/unitill-backend` as the only Git repository and deployment
source, while:

1. preserving the existing Salman mobile API contract exactly for every endpoint
   already present in Salman;
2. retaining Takwa's backend integrations, admin features, localization, and
   frontend work;
3. adding Salman-only business features that are missing from Takwa;
4. resolving the incompatible university, coupon, ad, authentication, and
   migration implementations without duplicating tables or changing legacy JSON;
5. implementing and delivering all work on a dedicated Takwa branch.

This document is a plan only. The only repository change made with this plan is
this document.

> **Revision 2.** The first draft was audited against both trees. Its factual
> claims held up, but the audit found 24 verified response deltas (§6.1), three
> decisions that must be settled before implementation (§4.1), and four defects
> in the plan itself: a migration sequence that aborts mid-run (§8 Phase 2.0), a
> contract-freeze phase that could not be executed as written (§8 Phase 0.5), a
> seeder instruction that would destroy the data copy it was run against
> (§8 Phase 2.6), and a V1/V2 split that leaves paid listings unenforceable
> (D1). File counts in §4 were also corrected. Sections not mentioned here are
> unchanged from revision 1.

## 2. Repositories and Git baseline

| Role | Path | State |
|---|---|---|
| Salman compatibility source | `salman-backend/unitill-main` | Unpacked source tree; no `.git` directory |
| Takwa target repository | `takwa-backend/unitill-backend` | Git repository with `origin` configured |
| Target base | `origin/main` | Commit `759ea998f457172d41f32a3556bdacb7884e8c1c` |
| Working branch | `integration/salman-contract-takwa-features` | Created from the current `origin/main` |

Because the Salman directory has no Git history, it cannot be merged or
cherry-picked. It must be treated as a reference implementation and imported
through reviewed, file-level patches.

Takwa's `origin/main` already contains the Takwa feature history, including:

- V2 OTP authentication and university administration;
- postcode and vehicle lookup;
- location privacy work;
- Stripe listing payments and coupons;
- soft account deletion;
- ad and chat report administration;
- account settings and personal-data export;
- five-language API/dashboard localization;
- React dashboard pages for reports, coupons, and universities;
- production SPA routing and CORS changes.

## 3. Non-negotiable compatibility rule

An endpoint is a **legacy endpoint** when the same HTTP method and URI exists in
`salman-backend/unitill-main/routes/api.php`.

For every legacy endpoint, Salman is the source of truth for all observable
behavior:

- HTTP status codes;
- JSON envelope (`status`, `data`, and optional `message`);
- field names and nesting;
- fields that are omitted versus present with `null`;
- string, boolean, integer, decimal, and timestamp types;
- pagination shape;
- English and Arabic response/validation text;
- validation rules and error keys;
- authentication requirements;
- token payload and V1 token lifetime;
- resource output;
- important state transitions that the mobile application relies on.

Do not assume that adding fields is harmless. The requirement is strict response
compatibility, so existing responses must not gain, lose, rename, or reinterpret
fields unless the mobile application is explicitly upgraded first.

Takwa behavior that conflicts with a Salman legacy response must be exposed
through a new endpoint or a versioned resource. It must not silently replace the
legacy behavior.

## 4. Current comparison findings

Static comparison of `app`, `config`, `database`, `routes`, and `tests` found:

- Salman: 287 files in those areas;
- Takwa: 312 files in those areas;
- 8 implementation files exist only in Salman;
- 33 implementation/migration files exist only in Takwa;
- 54 common files differ (42 in `app`, 6 in `database`, 3 in `config`,
  3 in `routes`);
- roughly 2,300 changed lines across those 54 files, of which a meaningful
  fraction in `AdFilters`/`AdSort` is reformatting rather than semantic change;
- neither copy has meaningful feature/contract tests; both only contain the
  default example tests;
- neither copy currently has `vendor/`, so runtime route/test verification
  requires `composer install`;
- both implementations changed the same high-risk controllers and models rather
  than implementing isolated modules;
- the two implementations contain incompatible migrations for both
  `university_domains` and `coupons`/`coupon_redemptions`;
- only 2 Salman routes are absent from Takwa (`POST reverify/send-otp`,
  `POST reverify/confirm`). The route table is largely intact; the exposure is
  in response bodies and validation rules, not the URI surface.

This is therefore a behavioral reconciliation, not a directory-copy operation.

Good news that reduces scope:

- `config/sanctum.php`, `config/auth.php`, `config/filesystems.php`, and
  `config/app.php` are byte-identical. No token-expiry or storage drift.
- All 179 `__('api.*')` keys used in Takwa's `app/` resolve in all five locale
  files.
- `MobileAuthTokenService` changed additively (optional `$accessTtlMinutes`);
  V1 token lifetime is unaffected.
- No frontend source file exists only in Salman.

## 4.1 Blocking decisions required before implementation

Three questions must be answered before Phase 2 begins. Each one changes the
shape of later phases, and two of them invalidate parts of this document as
currently written.

### D1 — Payment enforcement (invalidates §6 "Ad creation/publishing" as written)

`Ad::scopePublished` filters on `status` alone. `payment_status` appears in
`$fillable` and nowhere else: no scope, no accessor, no database constraint.
Enforcement lives entirely inside `HandlesListingPayments::startPublication()`.

Therefore keeping V1 Salman-exact leaves the paid-listing feature bypassable:

- `POST /api/ads` with `confirm_publish=true` writes `status='published'`
  directly and leaves `is_free_listing=false`, so it does not even consume the
  free quota. Repeatable without limit.
- `POST /api/my-ads/{id}/activate` grants a fresh free 30-day period.

This document currently contains two instructions that cannot both hold:
"do not trust a client-only payment complete signal" (§8 Phase 5.4) and "keep
the legacy Salman ad publication payload and status behavior unchanged"
(§8 Phase 5.4). `confirm_publish=true` *is* a client-only publish signal.

Pick one:

- **(a) Enforce.** Add `whereIn('payment_status', ['paid','free','coupon','waived'])`
  to `Ad::scopePublished`, and route V1 `store()`/`activate()` through
  `startPublication()`. V1 response bodies stay Salman-exact; V1 publication
  semantics change. Mobile behaviour changes for unpaid listings.
- **(b) Defer.** Ship V1 unchanged and accept that paid listings are advisory
  until the mobile app moves to V2. Document it as a known open hole with an
  end date.

Do not proceed on the assumption that a V2-only payment path is enforceable.
It is not.

### D2 — Which schema is actually in production

This document assumes Takwa's migrations are "potentially deployed". The
evidence points the other way: Salman's `.env.example` targets
`api.unitill.uk`, Takwa's targets `test-api.unitill.uk`.

If production runs Salman's schema, Phase 2 and Phase 9 invert — Takwa's
migrations get applied onto Salman's schema, not the reverse — and the
"forward-only, never edit deployed migrations" rule attaches to a different set
of files. Confirm against the live `migrations` table before writing any
reconciliation migration.

### D3 — Privacy revert sign-off

Reverting `AdResource` to Salman-exact re-exposes full postcode and exact
latitude/longitude of student sellers to unauthenticated callers. Takwa masked
these deliberately (`ApproximatesLocation`), for a UK student marketplace where
the coordinates are frequently a home address.

§8 Phase 4 currently instructs "do not mask/remove legacy postcode or
coordinates" without recording the consequence. That instruction is correct for
contract stability and wrong for user safety, and the trade-off needs a named
owner's sign-off rather than an implicit default. Consider shipping the mask
behind an opt-in `v1.1` header so the mobile app can adopt it without a
breaking release.

## 5. Target architecture

```mermaid
flowchart LR
    Mobile["Existing mobile app"] --> Legacy["Legacy /api endpoints"]
    NewClient["Opt-in/new clients"] --> V2["New or /api/v2 endpoints"]
    Dashboard["Takwa admin SPA"] --> Admin["Takwa /api/admin endpoints"]

    Legacy --> LegacyResources["Salman-compatible controllers/resources"]
    V2 --> TakwaResources["Takwa integration controllers/V2 resources"]
    Admin --> TakwaServices["Takwa admin controllers/services"]

    LegacyResources --> Domain["Shared superset domain models and schema"]
    TakwaResources --> Domain
    TakwaServices --> Domain
```

The legacy and Takwa paths may share models and domain services, but they must
use separate response presenters when their JSON contracts differ.

## 6. Feature and collision decisions

| Area | Salman behavior to preserve | Takwa feature to retain | Integration decision |
|---|---|---|---|
| V1 authentication | Existing login/register/refresh/biometric responses, messages, token payload, and 60-minute access-token default | Soft-deleted account restoration and user blocking | Keep Salman V1 controller contract; call Takwa domain services internally only when the resulting response is identical |
| V2 authentication | Does not exist in Salman | Two-step OTP login and 30-day access token | Retain under `/api/v2/*`; do not route V1 mobile calls into it |
| Student verification | Term-based re-verification, due dates, OTP endpoints, notification command | University/domain admin and V2 login OTP | Add Salman lifecycle fields and endpoints; use one unified university/domain model |
| University domains | Direct active UK-domain allowlist with subdomain matching | `universities` parent table, domain status, admin CRUD/UI | Keep Takwa parent model; make `UniversityDomain::allows()` preserve Salman registration behavior |
| Public ad resources | Salman `AdResource` and `AdDetailResource` fields, including `subtitle`, verification, raw location fields, grouped multiselect attributes, and publish-time fallback | Guest field hiding, approximate location, privacy settings | Legacy resources remain Salman-exact; use V2 presenters for privacy/masked variants |
| Ad listing/filtering | Salman typed filter/post engine, multiselect values, radius/distance support, Salman sort labels and response metadata | Postcode, vehicle plate, region, payment fields | Salman query/output contract wins; store Takwa-only fields additively and expose new lookup/payment flows separately |
| Ad creation/publishing | Existing request rules, success messages, payload, listing fee fields, and state transitions | Coupon redemption, free listing quota, Stripe PaymentIntent, sell-again | Keep legacy flow unchanged; expose the Takwa paid-publication flow through versioned ad endpoints |
| Coupon validation | Salman request limit, messages, reason payload, field names (`discount`, `total`, etc.), and `percent` type | Takwa admin CRUD, min amount, max discount, redemption amounts, Stripe use | Use a superset schema and adapter; legacy validation presenter remains Salman-exact; Takwa payment preview gets a V2 endpoint |
| Account deletion | Salman endpoint response | Reversible soft deletion/restoration | Use Takwa deletion service behind the legacy controller, returning Salman-exact JSON |
| Data export | Salman `POST account/data-request` response | Takwa reusable exporter, throttling, inline download | Preserve Salman POST response; retain new GET download; throttle only if its 429 contract is explicitly tested/accepted |
| Account/privacy settings | No Salman endpoints | Takwa GET/PUT settings endpoints | Retain as additive routes; do not add a `settings` object to legacy `UserResource` |
| Chat/report moderation | Salman chat endpoint responses and re-verification gating | Takwa chat report reasons/admin CRUD and trusted-user privacy | Keep legacy chat presenter/messages; retain additive report/admin endpoints; version behavior that adds new legacy errors |
| API localization | Salman legacy `lang` behavior, primarily English/Arabic literals | Takwa `en/ar/fr/es/zh` translations and middleware | Do not prepend locale middleware globally to legacy routes; apply it to V2/additive routes and dashboard APIs |
| Dashboard | Salman has no extra report/coupon/university pages | Takwa React pages and localized dashboard | Keep Takwa frontend as baseline and update API calls only where reconciliation changes an integration route |
| Expiring ads | Salman `ads:expire-old` and scheduled state/reason update | Takwa `ads:expire`, dry-run, hourly scheduling | Share one expiry service, keep command aliases if operational scripts use either name, and schedule one non-overlapping job |
| Deployment | Salman backend route behavior | Takwa SPA fallback, production build, split-domain CORS | Keep Takwa deployment setup; configure origins through environment variables |

## 6.1 Verified response-delta register

Every row below was verified against both trees. "Breaks" means the observable
Salman contract changes. Each row needs an explicit ruling — keep Salman, keep
Takwa, or version — before the corresponding controller is merged.

This register is not assumed complete. It is the output of hand comparison,
which is exactly the method that missed items 18, 19, 23, and 24 on the first
pass. Treat it as a checklist to verify mechanically against captured Salman
golden masters (§8 Phase 1), not as a substitute for that capture.

### Structural / architectural

| # | Delta | Breaks | Notes |
|---|---|---|---|
| 1 | `Ad::scopePublished` keys on `status` only; paid listings unenforceable via V1 | Yes | See D1. Blocking. |
| 2 | Salman's `create('university_domains')` and `create('coupon_redemptions')` are unguarded; Takwa already created both | n/a | Migration aborts mid-run. See §8 Phase 2. |
| 3 | `coupons.type` enum `percent` vs `percentage`; `used_count` vs `redemptions_count` | Yes | Money bug, not just DDL. A 50% coupon applies as £50 flat. |
| 4 | Takwa `UNIQUE(coupon_id,user_id)` is the only race protection; Salman's `max_uses_per_user > 1` is unimplementable under it | Yes | Cannot satisfy both. |
| 5 | Takwa adds `SoftDeletes` to `users`/`ads`; Salman has zero existence guards or `withTrashed` calls | Yes | Reverting controllers while keeping soft-delete reintroduces null-reference crashes. |

### Response bodies

| # | Delta | Breaks | Notes |
|---|---|---|---|
| 6 | `AdResource`: `published_at` loses the `?? created_at` fallback (now nullable) | Yes | Also affects `/home`, `/favorited`, `/my-ads`. |
| 7 | `AdResource`: postcode/lat/lng masked for non-owners; `+region`, `+is_approximate_location` | Yes | See D3. |
| 8 | `AdDetailResource`: guests get a stripped payload; multi-value attribute grouping removed | Yes | |
| 9 | `UserResource`: `average_rating`, `total_reviews`, `is_trusted_seller` commented out | Yes | Affects login, register-verify, `GET/PUT /profile`. |
| 10 | `UserResource`: `name` truncated and `last_name` → `null` for non-owners; student lifecycle keys replaced by a `settings` object | Yes | Field type changes `string` → `string\|null`. |
| 11 | `GET /api/settings` gains `url` and `free_ads_per_user` | Maybe | `free_ads_per_user` is required by the payment feature. Coupled to D1. |
| 12 | `CouponController`: `discount`/`total`/`formatted_total` → `discount_amount`/`final_amount`/`formatted_final`; error payload `{reason:}` → `{code:}` | Yes | |
| 13 | `DELETE /delete-account` returns `{deleted_at}` instead of `UserResource` | Yes | |
| 14 | `MyAdController::activate` returns `{ad, publication}` instead of a bare `MyAdResource`, and is now billable | Yes | |
| 15 | Data export returns 500 on mail failure where Salman returned 200 silently | Yes | |

### Validation and error surface

| # | Delta | Breaks | Notes |
|---|---|---|---|
| 16 | Chat report `reason`: `nullable\|string\|max:80` → `required` + enum | Yes | Existing free-text reasons now 422. |
| 17 | `StoreAdRequest`: postcode `max:12` → strict UK regex; `license_plate` required for Cars with `^[A-Z]{2}[0-9]{2}[A-Z]{3}$` (no `i` flag, no space tolerance); `city_id == 1` fallback commented out | Yes | Payloads that pass today start returning 422. |
| 18 | `GET /api/languages` no longer filters `is_active` | Yes | Public endpoint returns inactive languages. |
| 19 | `GET /api/legal-affairs` default locale flipped `en` → `ar`; arbitrary header values pass straight into the lookup | Yes | `header('lang') === 'ar' ? 'ar' : 'en'` became `header('lang', 'ar')`. |
| 20 | Unmatched `/api/*` returns bare `{"message":"Not Found."}` instead of the `sendError` envelope | Yes | `routes/web.php` fallback — outside this document's original diff scope. |
| 21 | `ChatService` adds `seller_unavailable`, `buyer_not_verified`, `participant_unavailable` | Yes | New 403/422 on legacy chat routes. |
| 22 | `POST /account/data-request` gains a 5/hour 429 | Yes | Envelope preserved via `sendError`; the status code is new. |

### Silent / knock-on

| # | Delta | Breaks | Notes |
|---|---|---|---|
| 23 | `AdFilters` inverted fail-open → fail-closed, and `near_lat`/`near_lng` are no longer reserved | Yes | A Salman-era client sending `near_lat` gets **zero results**, not degraded results. Silent; worst failure mode on this list. |
| 24 | `ads:expire` stopped writing `inactive_reason` | Yes | `MyAdResource.inactive_reason` and `inactive_reason_label` now `null` for auto-expired ads. |

### Deliberate Takwa fixes that a Salman revert would undo

These were not accidents — Takwa's source comments explain each as a correctness
fix. Reverting them is defensible under the contract rule, but each should be
logged as an accepted regression with an owner rather than reverted silently:

- `published_at` fallback (reporting an unpaid ad as "published seconds ago");
- location masking (#7, see D3);
- soft-delete participant guards (#5, #21);
- `scopePublished` gaining `notExpired()` — reverting leaves expired ads visible
  between scheduled runs;
- the `HttpResponseException` pass-through, which stops throttle and
  FormRequest responses being rewritten as 500.

## 7. Routes

### 7.1 Legacy route set

The full Salman route table in `routes/api.php` must remain available with the
same middleware and methods. The most sensitive families are:

- public settings, languages, home, categories, cities, ads, ad details, report
  reasons, and legal affairs;
- V1 register/login/refresh/student verification/password reset;
- all existing `/api/admin/*` routes that Salman already defines;
- ad creation, draft, image upload, publish, reporting, and My Ads actions;
- orders, ratings, favorites, sessions, biometric tokens, and FCM/device
  registration;
- notification inbox;
- profile, deletion, data request, security, trusted seller, and contact;
- conversations and messages;
- Salman re-verification and coupon validation routes.

Generate a route manifest from Salman before implementation and compare it with
the final Takwa route manifest. The final manifest may contain additional Takwa
routes, but no Salman method/URI/middleware combination may disappear or change.

Current gap is small and specific. Only two Salman routes are missing from
Takwa:

- `POST /api/reverify/send-otp`
- `POST /api/reverify/confirm`

Both were removed along with `StudentTerm`, `RequireStudentReverification`,
`User::needsReverification()`, and the two `needs_reverify` 403 gates in
`ConversationController::store()` and `::sendMessage()`. Restoring the routes
without those gates restores the URI but not the behaviour.

The route table is otherwise intact, and middleware is largely unchanged: the
global `throttleApi('api')` 60/min limit is identical, no route lost
`auth:sanctum`, and prefixes are unchanged apart from the new `v2`. Treat the
route manifest as a cheap regression check, not as the main risk. The exposure
is in response bodies and validation rules — see §6.1.

One operational dependency: the new admin routes are gated on
`ad_reports.*`, `chat_reports.*`, `coupons.*`, and `universities.*` permissions.
Those rows must exist in the merged database or every one of those routes 403s.

### 7.2 Takwa-only routes to retain

These do not collide with Salman routes and can remain additive:

- `POST /api/stripe/webhook`
- `GET /api/chat-report-reasons`
- `POST /api/v2/login`
- `POST /api/v2/login/verify-otp`
- `POST /api/v2/login/resend-otp`
- `POST /api/v2/auth/refresh`
- `GET|POST|PUT|DELETE /api/admin/universities...`
- `GET|POST|PUT|DELETE /api/admin/coupons...`
- `GET|PUT /api/admin/ad-reports...`
- `GET|PUT /api/admin/chat-reports...`
- `POST /api/ads/{id}/payment/complete`
- `POST /api/my-ads/{id}/sell-again`
- `GET /api/account/data-download`
- `GET|PUT /api/account/settings`
- `GET /api/vehicles/lookup`
- `GET /api/postcode/lookup`

### 7.3 Conflicting Takwa flows that need versioning

Takwa changes the behavior and response of existing ad and coupon endpoints.
Keep the Salman routes unchanged and add opt-in endpoints for the Takwa flow:

- `POST /api/v2/ads`
- `POST /api/v2/ads/draft`
- `POST /api/v2/ads/{id}/publish`
- `POST /api/v2/ads/{id}/payment/complete`
- `POST /api/v2/coupons/validate`

These endpoints can return PaymentIntent/client-secret, publication, coupon,
privacy, or localization fields without changing the mobile contract on V1.
Feature flags should allow the V2 payment flow to be disabled independently.

## 8. Detailed implementation phases

### Phase 0 — Branch and evidence preservation

1. Work only on `integration/salman-contract-takwa-features`.
2. Never copy `.env`, storage contents, caches, built dependencies, or generated
   runtime files from Salman.
3. Record the Salman snapshot checksum or archive checksum in the eventual PR so
   reviewers know which source tree supplied the compatibility behavior.
4. Save the pre-change Takwa route manifest and schema dump as review artifacts.
5. Do not edit or delete old, potentially deployed Takwa migrations. Use new
   forward-only reconciliation migrations.

### Phase 0.5 — Stand up a runnable Salman and capture golden masters

This phase is a hard prerequisite and was missing from the first draft of this
plan. Without it Phase 1 cannot be executed.

Contract tests cannot be authored directly in Takwa. Takwa's behaviour already
differs from Salman's in at least the 24 ways catalogued in §6.1, so tests
written against Takwa would fail on the first run and would freeze nothing. The
reference behaviour has to be captured from the Salman tree itself.

The Salman tree has no `.git`, no `vendor/`, and no `.env`. Budget for this:

1. `composer install` in `salman-backend/unitill-main`.
2. Create a `.env` from `.env.example` pointing at a scratch MySQL database.
3. `php artisan migrate:fresh --seed`, then seed deterministic fixture data
   (fixed IDs, fixed timestamps) so captures are reproducible.
4. Write a capture harness that walks every route in
   `routes/api.php` and records, per endpoint and per scenario: HTTP status,
   full response body, and relevant headers.
5. Run each endpoint under the scenario matrix below, with `lang: en` and
   `lang: ar`, authenticated and unauthenticated.
6. Commit the captures to `tests/Fixtures/contracts/` in the Takwa branch as
   the golden masters. Record the Salman snapshot checksum alongside them.

The captures — not this document, and not hand comparison — are the
authoritative definition of the Salman contract. §6.1 is a starting checklist
for reviewers, and its own history shows why: four of its entries were missed
on the first hand pass, one of which (#23) fails silently in production.

### Phase 1 — Assert Takwa against the captured contract

With golden masters committed, add the Takwa-side suite that diffs live
responses against them. Every mismatch is either a bug to fix or a delta to
promote into §6.1 with an explicit ruling.

Rank the work by mobile blast radius rather than attempting uniform coverage.
`AdResource`, `AdDetailResource`, `MyAdResource`, and `UserResource` plus V1
auth cover most mobile screens; snapshot those exhaustively and spot-check the
long tail. A uniform 10-scenario sweep across ~136 routes is 1,300+ cases before
any feature work lands, which will stall the branch.

Suggested structure:

```text
tests/
  Feature/
    Contracts/
      PublicApiContractTest.php
      AuthV1ContractTest.php
      AdContractTest.php
      ProfileAndSecurityContractTest.php
      ConversationContractTest.php
      NotificationFavoriteRatingContractTest.php
      AdminContractTest.php
    Integrations/
      AuthV2Test.php
      PostcodeLookupTest.php
      VehicleLookupTest.php
      ListingPaymentTest.php
      StripeWebhookTest.php
      AccountSettingsTest.php
  Fixtures/
    contracts/
```

For each Salman endpoint, cover:

1. success;
2. validation failure;
3. unauthenticated request;
4. missing resource;
5. forbidden/ownership failure where applicable;
6. English response;
7. Arabic response;
8. pagination and empty collection;
9. nullable/optional fields;
10. exact resource data types.

Normalize only truly dynamic values such as IDs, signed tokens, timestamps, and
temporary file URLs. Do not normalize field names, messages, status codes, nulls,
or numeric/string types.

High-priority golden-master assertions:

- `AdResource`, `AdDetailResource`, `MyAdResource`, and `UserResource`;
- V1 login, register, refresh, biometric login, verification, and password reset;
- ad list/detail/create/draft/publish;
- `coupons/validate`;
- profile show/update and account security;
- conversation list/detail/message/report;
- favorites, ratings, notifications, orders;
- API exception and FormRequest error envelope.

Use MySQL for integration tests because the code contains MySQL-specific enum,
`FIELD`, casts, and raw distance SQL. SQLite alone will produce false confidence.

### Phase 2 — Reconcile database schema using additive migrations

#### 2.0 Do not copy Salman's migration files

Salman's three migrations cannot be added to this repository as-is, and
renumbering them makes the failure worse rather than better.

`2026_07_26_000000` calls `Schema::create('university_domains')` **unguarded**,
and `2026_07_26_020000` calls `Schema::create('coupon_redemptions')` unguarded.
Takwa created both tables at `2026_07_16_100000` and `2026_07_20_120000`.
Filename ordering places Salman's last, so on a fresh merged database:

1. `2026_07_26_000000` adds `student_verified_at`, `student_reverify_due_at`,
   and `reverify_notified_at` to `users` **successfully**;
2. then aborts with `1050 Table 'university_domains' already exists`.

MySQL has no transactional DDL, so the three columns persist while the
migration row is never written. Re-running then fails differently, with
`1060 Duplicate column name 'student_verified_at'`. Manual repair required.

Renumbering Salman's files earlier does not fix this. Takwa's `hasTable` guard
would then skip its own `university_domains` creation, leaving the table
without `university_id` and with an empty `universities` parent — **no error at
all**. Reordering converts a loud failure into a silent one.

Required approach: author new forward-only migrations that add only the columns
Salman contributes. Specifically, take the `users` `student_*` block from
`2026_07_26_000000` and discard its `create` block entirely; discard
`2026_07_26_020000` entirely; `2026_07_26_010000` can be carried over largely
as-is, since its added columns do not collide and its
`dropUnique('adv_val_ad_cad_uniq')` targets an index Takwa still has.

Canonical schema choice: Takwa's `universities`/`university_domains` and
`coupons`/`coupon_redemptions` win. They are referentially correct, richer, and
already deployed. Salman's *behaviour* is then ported onto Takwa's column names
(`status`, `university_id`, `redemptions_count`, `'percentage'`) rather than
Salman's schema being recreated.

Also note `2026_07_26_010000::down()` re-creates the unique index and will throw
`1062` once any multiselect row exists. Rollback of that migration is one-way in
practice.

#### 2.1 Student verification and V2 login OTP

Add a new migration that conditionally adds missing user columns:

- `student_verified_at`
- `student_reverify_due_at`
- `reverify_notified_at`
- `login_otp`
- `login_otp_expires_at`
- `login_otp_sent_at`

Retain Takwa soft-delete and privacy/notification columns. Add all corresponding
casts to the merged `User` model.

#### 2.2 Universities and domains

Keep Takwa's `universities` table and relation. Reconcile
`university_domains` into a superset with:

- `university_id`;
- `domain`;
- Takwa `status`;
- Salman-compatible active semantics;
- a unique normalized domain.

Do not create a second `university_domains` table. Update the Salman domain
seeder to create/find a parent university and then insert related domains.
Backfill any unparented domains into a safe university record before enforcing a
non-null foreign key.

Implement `UniversityDomain::allows(string $email)` using an indexed exact/parent
domain lookup, requiring both the domain and parent university to be active. Its
boolean result and Salman registration validation message must remain unchanged.

#### 2.3 Coupon schema

Keep one `coupons` and one `coupon_redemptions` table. Reconcile them as a
superset:

- preserve Salman fields: `max_uses`, `max_uses_per_user`, `used_count`;
- retain Takwa fields: `max_discount`, `min_amount`, redemption amount audit
  columns;
- keep the **stored** enum as Takwa's `percentage|fixed` (canonical per §2.0 —
  it is already deployed, and `enum('percentage','fixed')` physically cannot
  store `'percent'`: strict MySQL rejects it with `1265`, non-strict writes an
  empty string);
- translate to Salman's API-facing `percent` in the legacy presenter only, so
  `CouponController` still emits `percent` without a schema rewrite;
- reconcile the counter rename explicitly: Salman reads/increments `used_count`,
  Takwa reads/increments `redemptions_count`. Neither column exists in the other
  schema, so an unreconciled merge throws `1054 Unknown column`;
- reconcile `coupon_redemptions` NOT NULL columns: Salman's insert supplies only
  `coupon_id`, `user_id`, `ad_id` and will throw
  `1364 Field 'original_amount' doesn't have a default value` against Takwa's
  table. Either widen Salman's insert or default the columns;
- note `code` length differs (Salman `varchar(50)`, Takwa `varchar(40)`) and
  `value` nullability differs (Salman `DEFAULT 0`, Takwa NOT NULL);
- reconcile redemption timing: Salman burns the coupon at **draft** time,
  Takwa at **publish** time. A naive merge double-redeems or leaks single-use
  codes;
- retain per-user default of one use;
- use transactions/row locking for counters;
- do not keep a database uniqueness rule that contradicts a configured
  `max_uses_per_user > 1`; use an index plus transactional enforcement;
- backfill both old counters from redemption rows and then update them together
  in the domain service.

The legacy `CouponController` must still output Salman's exact keys:
`code`, `type`, `value`, `listing_fee`, `discount`, `total`,
`formatted_discount`, and `formatted_total`.

#### 2.4 Category filter engine

Takwa already adds `date` and `multiselect` input types. Add Salman fields:

- `filter_control`
- `post_control`
- `config`
- `is_filterable`
- `is_postable`

Drop the old one-value-per-ad/definition unique index so multiselect values can
be stored as multiple rows. Add a non-unique lookup index instead. Verify both
fresh migration and upgrade from the current Takwa schema.

#### 2.5 Ads and payments

Retain the superset of:

- Salman location, publication, expiration, and status fields;
- Takwa `license_plate`, `region`, listing fee, payment status,
  `stripe_payment_intent_id`, and free-listing fields;
- Takwa soft deletes.

Add indexes for public status/expiry queries, owner/status queries, payment intent
lookup, and latitude/longitude access where justified by query plans.

#### 2.6 Migration verification

Test both:

1. a clean database running every migration from zero;
2. a copy of the current Takwa schema/data running only the new migrations.

**Do not run the seeders against a data-bearing copy.** Several are destructive,
and the earlier instruction to "run seeders twice against a copy of the current
Takwa schema/data" would have destroyed that copy:

- `CategorySeeder::truncateCategoryTree()` disables foreign key checks and
  truncates `ads`, `ad_images`, `ad_attribute_values`, and the whole category
  tree. Its own docblock warns never to run it against production data.
- Both `SettingSeeder` variants call `Setting::truncate()`. Keeping Salman's
  version wipes the `free_ads_per_user` and `app_url` rows inserted by Takwa's
  migrations — after which the payment logic silently reads zero free ads and
  **every listing becomes paid**.
- Takwa's `CategoryAttributeSeeder::pruneRemovedDefinitions()` deletes every
  definition whose slug it does not know, which includes all Salman-only
  definitions, cascading away their `ad_attribute_values`.
- Takwa's `LanguageSeeder` truncates `Language` with FK checks off, orphaning
  every `*_translations.language_id`.

Seeder idempotency testing belongs on a throwaway database only. Before then,
the seeders themselves need reconciling:

- `DatabaseSeeder` registers `UniversityDomainSeeder` (Salman) vs
  `UniversitySeeder` (Takwa), targeting the same table with incompatible
  columns. 29 domains overlap; both use `updateOrCreate` keyed on `domain`, so
  they will not double-insert but will overwrite each other's column set.
- `CategorySeeder` naming drift creates duplicate categories through literal
  string matching: `Cars` vs `Cars only`, `IT / tech help` vs `IT/tech help`,
  `Freelance / student services` vs `Freelance/student services`.
- `CategoryAttributeSeeder` has same-slug contradictions (`availability`,
  `condition`, `features`) and near-miss slugs (`availability_from` vs
  `available_from`, `subject` vs `subject_field`, `item_type` vs
  `furniture_type`).
- `RolePermissionSeeder`: Takwa seeds 16 permissions Salman lacks
  (`universities.*`, `coupons.*`, `ad_reports.*`, `chat_reports.*`). Keeping
  Salman's version 403s every Takwa admin route.

#### 2.7 Multiselect: two incompatible designs, not one feature

Takwa declares multiselect as an `input_type` enum value and stores a single row
via `(string) $value` — handed an array that produces the literal string
`"Array"`. `grep -rn multiselect` across Takwa's `app/` returns zero hits: the
runtime has no multiselect handling, only the enum and the seeder know the word.

Salman keeps `input_type='select'` and declares multiselect through
`filter_control`/`post_control`, storing one row per selected value, which is
why it must drop `adv_val_ad_cad_uniq`.

The two migrations do not collide at the SQL level, so this will not surface as
an error. The read path partly survives by luck —
`CategoryAttributeDefinition::resolvedFilterControl()` returns
`$this->filter_control ?: $this->input_type`, so a Takwa-seeded row still
resolves. The write path does not. Pick one design explicitly and migrate the
seeder data to match.

#### 2.8 Delete `CouponTrait.php`

Byte-identical in both repos and dead in both (no `use` site). It is a schema
landmine: it reads `min_amount` (Takwa-only), `max_uses`/`used_count`/
`max_uses_per_user` (Salman-only), and tests `type === 'percent'` (Salman
spelling). It cannot work against either schema. Remove it rather than carry it
forward.

### Phase 3 — Merge domain models and services

#### 3.1 Salman-only files to add

- `app/Console/Commands/ExpireOldAds.php`
- `app/Console/Commands/RequireStudentReverification.php`
- `app/Support/GeoDistance.php`
- `app/Support/StudentTerm.php`
- the Salman student/filter reconciliation migrations, rewritten as new
  forward-only migrations per §2.0 (not merely renumbered);
- an adapted `UniversityDomainSeeder`.

These files must land **before** the Phase 4 controller merges, not alongside
them. Salman's `AdController`, `AdSort`, and `AuthController` reference
`GeoDistance`, `StudentTerm`, and `RequireStudentReverification` directly, so a
controller ported without its Support class is a fatal error rather than a
degraded response.

Porting `RequireStudentReverification.php` is also not sufficient on its own.
Salman registers its schedule in `bootstrap/app.php`
(`students:require-reverification` daily at 06:00, `ads:expire-old` daily at
00:15); Takwa's `bootstrap/app.php` schedules nothing and registers only
`ads:expire` hourly in `routes/console.php`. The schedule entry has to be added
explicitly.

#### 3.2 Takwa-only application files to retain

Retain the Takwa controllers/services/resources for:

- V2 authentication;
- account settings;
- ad/chat/coupon/university administration;
- postcode and vehicle lookup;
- Stripe webhook and listing payments;
- account deletion and data export;
- coupon redemption;
- location approximation;
- report reason support;
- payment handling.

#### 3.3 Models requiring manual superset merges

Do not copy either version wholesale. Manually merge:

- `app/Models/User.php`: keep `SoftDeletes`, Takwa privacy/login OTP fields, and
  Salman student re-verification methods/casts;
- `app/Models/Ad.php`: keep `SoftDeletes`, Takwa payment/plate/region fields, and
  Salman publication, expiration, scope, relation, and status behavior;
- `app/Models/UniversityDomain.php`: Takwa relation plus Salman `allows()`;
- `app/Models/Coupon.php`: compatibility methods plus Takwa payment/admin
  calculations;
- `app/Models/CouponRedemption.php`: all relations and audit fields;
- `app/Models/CategoryAttributeDefinition.php`: Salman filter/post controls plus
  Takwa input types;
- `app/Models/Conversation.php`: preserve Salman mobile behavior while retaining
  Takwa moderation fields;
- related casts/fillable lists must cover the reconciled schema.

#### 3.4 Shared services

Extract or retain domain services for:

- account deletion/restoration;
- personal-data export;
- coupon preview/redemption;
- listing payment creation/completion;
- ad expiration;
- postcode and vehicle provider access.

Controllers should adapt service results into either a Salman legacy presenter
or a Takwa/V2 presenter. Services must not return HTTP responses.

### Phase 4 — Restore Salman-exact legacy controllers and resources

Use Salman as the starting point for common legacy controllers, then add Takwa
domain calls without changing their returned response.

High-risk manual merges:

- `AuthController`
- `AdController`
- `ConversationController`
- `CouponController`
- `MyAdController`
- `UserController`
- `AccountSecurityController`
- ad report, favorite, order, notification, rating, and device controllers
- all changed FormRequests

Legacy resource classes must match Salman:

- `app/Http/Resources/AdResource.php`
- `app/Http/Resources/AdDetailResource.php`
- `app/Http/Resources/MyAdResource.php`
- `app/Http/Resources/UserResource.php`

Create V2 resource classes where Takwa needs a different representation, for
example:

```text
app/Http/Resources/V2/AdResource.php
app/Http/Resources/V2/AdDetailResource.php
app/Http/Resources/V2/UserResource.php
```

Specific contract decisions:

- keep Salman's `published_at ?? created_at` legacy fallback;
- keep Salman's complete legacy ad detail fields for guests;
- keep Salman's grouped multiselect attributes;
- keep Salman's full `UserResource` rating/trusted-seller and student lifecycle
  fields;
- do not inject Takwa's privacy `settings` object into legacy profile output;
- do not mask/remove legacy postcode or coordinates;
- apply Takwa privacy and guest-field hiding only in V2 presenters;
- keep Salman's literal English/Arabic response text on legacy routes.

### Phase 5 — Integrate Takwa features without legacy response changes

#### 5.1 V2 OTP authentication

- **P0, do this first, independently of the rest of this plan.**
  `config/mobile_auth.php` sets
  `'login_otp_test_code' => env('MOBILE_LOGIN_OTP_TEST_CODE', '123456')`, and
  `Api/V2/AuthController` uses it as
  `config('mobile_auth.login_otp_test_code') ?: random_int(...)`. The default is
  live: unless the environment variable is explicitly set to an empty value,
  **`123456` authenticates as any account**. This is a full authentication
  bypass shipping in the default configuration, not a testing convenience. Flip
  the default to null and gate the fixed code behind
  `app()->environment('testing')`.
- Retain Takwa `Api/V2/AuthController`.
- Keep V2 token configuration separate from V1.
- Add resend throttling and one-time consumption tests.
- Verify blocked, soft-deleted/reactivated, biometric, expired, replayed, and
  wrong-code cases.

#### 5.2 Postcode integration

- Retain `PostcodeController` and `PostcodeService`.
- `PostcodeService` calls `Http::get()` with **no timeout and no retry** — a
  slow provider will hold a worker for the default socket timeout. Add both,
  plus `Http::fake()` tests.
- Normalize UK postcodes and map the city without requiring an exact
  case-sensitive translation match.
- Preserve the additive endpoint's response and distinguish invalid postcode
  from provider outage.
- Never call the provider during normal ad-resource serialization.

#### 5.3 Vehicle integration

- Retain `VehicleLookupController` and `VehicleApiService`.
- Already correct, no action needed: the key reads from
  `config('services.vehicle_api.key')` (environment-only, no hard-coded
  default), and the call already has `Http::timeout(10)->retry(2, 200)`. An
  earlier draft of this plan wrongly listed both as outstanding work.
- Test not-found, provider failure, mapping fallback, and plate normalization.
- Note the plate regex in `StoreAdRequest` is `^[A-Z]{2}[0-9]{2}[A-Z]{3}$` with
  no `i` flag and no space tolerance, so `ab12 cde` is rejected. Decide whether
  that is intended before it reaches mobile clients.
- Store `license_plate` and mapped vehicle attributes additively.
- Do not add vehicle fields to legacy response resources unless they already
  appear through Salman's existing dynamic `attributes` contract.

#### 5.4 Stripe/listing payments

- Retain `StripeService`, `ListingPaymentService`, webhook verification, free
  listing quota, and sell-again behavior.
- Put the paid publication start flow on V2 ad endpoints.
- Make webhook completion idempotent and transactional.
- Verify amount, currency, ad ownership/metadata, and PaymentIntent identity
  before publishing.
- Do not trust a client-only "payment complete" signal.
- Ensure repeated webhooks or verification calls cannot double-redeem a coupon
  or republish/extend an ad twice.
- Keep the legacy Salman ad publication payload and status behavior unchanged.

#### 5.5 Account deletion/export/settings

- Use Takwa soft-delete/restore services behind the Salman deletion endpoint,
  preserving the Salman response.
- Keep the Takwa download and settings routes additive.
- Prevent settings/privacy changes from changing legacy `UserResource`.
- Apply a named rate limiter to expensive export routes, with explicit contract
  tests for the 429 envelope and headers.

#### 5.6 Reports and dashboard administration

- Retain Takwa ad report, chat report, coupon, and university controllers.
- Keep Takwa React pages:
  `AdReportsPage.tsx`, `ChatReportsPage.tsx`, `CouponsPage.tsx`, and
  `UniversitiesPage.tsx`.
- Reconcile permissions in `RolePermissionSeeder`.
- Test that a fresh permission seed grants the intended admin navigation and API
  permissions without deleting custom production roles.

### Phase 6 — Middleware, exceptions, localization, and deployment

1. Remove the global API prepend of `SetApiLocale`.
2. Register it as a named middleware and apply it to V2/additive APIs that use
   Takwa translation files.
3. Leave Salman legacy locale/message behavior unchanged.
4. Keep the `HttpResponseException` pass-through fix so FormRequest and throttle
   responses are not converted into 500 responses. This sits in tension with
   item 5 and the rule in §3, so resolve it with an explicit test rather than
   leaving both instructions standing. Verified low-risk: both trees handle
   `ValidationException` identically via `sendError(..., 422)`, and the
   pass-through branch only catches cases Salman would have returned 500 for.
   The Takwa source comment claiming `FormRequest::failedValidation` throws
   `HttpResponseException` is inaccurate for Laravel 11 — it throws
   `ValidationException`.
5. Preserve the Salman API exception envelope for authentication, validation,
   404, 405, and server errors. Note this includes the `routes/web.php`
   fallback, which currently returns a bare `{"message":"Not Found."}` for
   unmatched `/api/*` (§6.1 item 20) and additionally runs those paths through
   the `web` middleware group.
6. Retain Takwa's SPA fallback and split-domain deployment behavior.
7. Keep CORS origins environment-driven; test allowed and rejected origins.
8. Rebuild the frontend only after backend route reconciliation.

### Phase 7 — Security gate

Static inspection found tracked PHP configuration/helper code containing
hard-coded or default third-party credentials. Before deployment:

1. remove all credential defaults and hard-coded tokens from source;
2. use environment variables or the deployment secret store;
3. rotate every exposed credential, because removing it from the latest commit
   does not invalidate the old value;
4. scan the branch and Git history with a secret scanner;
5. ensure `.env`, Firebase credentials, database dumps, and storage exports are
   not committed;
6. keep sanitized placeholders in `.env.example`.

Treat credential rotation as a separate, clearly labeled commit so it is easy to
audit.

### Phase 8 — Verification

#### Backend

Run:

```bash
composer install
php artisan optimize:clear
php artisan migrate:fresh --seed --env=testing
php artisan route:list
php artisan test
```

Also run:

- PHP formatter/static analysis configured by the project;
- an upgrade migration against a Takwa schema snapshot;
- queue and scheduler smoke tests;
- storage upload/URL tests;
- mail, Firebase, postcode, vehicle, and Stripe tests using fakes;
- webhook signature and idempotency tests;
- coupon concurrency tests;
- authorization tests for every new admin permission.

#### Frontend

Run:

```bash
cd frontend
npm ci
npm run build
```

Then smoke-test login, navigation, reports, coupons, universities, settings, and
localized pages against the reconciled backend.

#### Mobile contract comparison

For every Salman legacy endpoint, compare old and new responses after
normalizing only approved dynamic values. The comparison must fail on:

- missing or additional keys;
- different nesting;
- different null/omission behavior;
- different scalar types;
- different status codes;
- different messages;
- different pagination metadata.

Run the final contract suite with both `lang: en` and `lang: ar`, authenticated
and unauthenticated where relevant.

### Phase 9 — Deployment and rollback

1. Deploy the branch to staging with a production-like MySQL copy.
2. Back up the production database before applying reconciliation migrations.
3. Run migrations and data backfills with counts logged before and after.
4. Run legacy mobile smoke tests first.
5. Run Takwa integration/dashboard smoke tests second.
6. Enable external integrations through feature flags:
   - V2 OTP auth;
   - postcode lookup;
   - vehicle lookup;
   - listing payments;
   - V2 privacy presenters.
7. Roll out one integration at a time and monitor HTTP 4xx/5xx rates, provider
   errors, payment mismatches, and queue failures.
8. Roll back application code by reverting the release commit. Database
   migrations should be additive so old code can continue to run during a code
   rollback; do not depend on destructive down migrations in production.

## 9. File-level work list

Generate the authoritative list rather than working from the categories below,
which are a summary and not a checklist:

```bash
S=../../salman-backend/unitill-main
diff -rq "$S/app" app | grep '^Files'          # 42 shared app files differ
diff -rq "$S/database" database | grep '^Files'
diff -rq "$S/config" config | grep '^Files'
diff -rq "$S/routes" routes | grep '^Files'
```

Note that `bootstrap/app.php` and `routes/web.php` are **outside** that diff
scope and both carry contract-affecting changes (§6.1 items 20 and the
`HttpResponseException` pass-through). Check them explicitly.

Of the 42 differing `app` files, roughly half differ only in message
localization (`FavoritedController`, `FcmController`, `UserNotificationController`,
`UserRatingController`, `OrderController`, `UserDeviceController`,
`AccountSecurityController`, `AdReportController`, and four FormRequests) and
carry no structural risk. Concentrate review on the fifteen named below.

### Preserve Salman contract in these shared files

- `routes/api.php`
- `bootstrap/app.php`
- `app/Providers/RouteServiceProvider.php`
- changed API controllers under `app/Http/Controllers/Api`
- changed FormRequests under `app/Http/Requests`
- the four changed mobile resources
- `app/Support/AdFilters.php`
- `app/Support/AdSort.php`
- `app/Services/ChatService.php`
- `config/mobile_auth.php`

### Retain Takwa integration implementations

- `app/Http/Controllers/Api/V2/AuthController.php`
- `app/Http/Controllers/Api/PostcodeController.php`
- `app/Http/Controllers/Api/VehicleLookupController.php`
- `app/Http/Controllers/Api/StripeWebhookController.php`
- `app/Http/Controllers/Api/AccountSettingsController.php`
- Takwa-only dashboard controllers
- `app/Services/AccountDeletionService.php`
- `app/Services/PersonalDataExportService.php`
- `app/Services/PostcodeService.php`
- `app/Services/VehicleApiService.php`
- `app/Services/StripeService.php`
- `app/Services/ListingPaymentService.php`
- `app/Services/CouponRedemptionService.php`
- `app/Traits/HandlesListingPayments.php`
- Takwa API translation files and dashboard frontend

### Manual merge only; never wholesale-copy

- `app/Models/User.php`
- `app/Models/Ad.php`
- `app/Models/UniversityDomain.php`
- `app/Models/Coupon.php`
- `app/Models/CouponRedemption.php`
- `app/Models/CategoryAttributeDefinition.php`
- `app/Models/Conversation.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/AdController.php`
- `app/Http/Controllers/Api/ConversationController.php`
- `app/Http/Controllers/Api/MyAdController.php`
- `app/Http/Controllers/Api/UserController.php`
- `app/Http/Requests/RegisterRequest.php`
- `app/Http/Requests/StoreAdRequest.php`
- category, attribute, language, permission, setting, university, and coupon
  seeders

### Frontend policy

No frontend source file exists only in Salman. Keep Takwa as the frontend
baseline. Do not overlay Salman's common React files over Takwa, because that
would remove Takwa localization and admin integrations. Make only the API client
and UI adjustments required by the reconciled backend.

## 10. Recommended commit sequence

Keep commits small and independently reviewable:

0. `security: disable the default V2 login OTP test code` — ship ahead of the
   rest of the sequence; it is a live authentication bypass (§8 Phase 5.1).
1. `docs: add Salman contract and Takwa integration plan`
2. `test: freeze Salman legacy API contracts` — golden masters captured from the
   Salman tree per Phase 0.5, committed before any behaviour change
3. `feat: add forward-only reconciliation migrations`
4. `feat: merge superset domain models and services`
5. `feat: restore Salman-compatible legacy API behavior`
6. `feat: expose Takwa auth lookup privacy and payment integrations`
7. `feat: reconcile admin APIs permissions and dashboard`
8. `security: remove embedded credentials and harden integrations`
9. `test: add migration integration and end-to-end verification`

Do not mix generated frontend build output, dependency lock changes, schema
changes, and controller reconciliation in one commit.

## 11. Branch and push workflow

The branch for this work is:

```text
integration/salman-contract-takwa-features
```

Workflow:

```bash
git fetch origin
git switch integration/salman-contract-takwa-features
git status
# implement and verify in the commit sequence above
git push -u origin integration/salman-contract-takwa-features
```

Do not force-push after review begins. Open a pull request into `main` and require
the contract suite, backend suite, migration checks, frontend build, and secret
scan to pass before merge.

## 12. Definition of done

The reconciliation is complete only when all of the following are true:

- decisions D1, D2, and D3 (§4.1) are recorded with a named owner;
- every entry in the §6.1 delta register has an explicit keep-Salman /
  keep-Takwa / version ruling, and every deliberate Takwa fix that was reverted
  is logged as an accepted regression;
- golden masters captured from the Salman tree are committed, and the Takwa
  suite diffs against them mechanically rather than by hand review;
- the default V2 login OTP test code is removed;
- every Salman method/URI still exists, including the two reverify routes and
  their `needs_reverify` gates;
- every Salman legacy contract test passes without approved response changes;
- the Salman student re-verification and filter/post engine work in Takwa;
- Takwa V2 auth, university admin, postcode, vehicle, Stripe, coupons, soft
  deletion, exports, settings, reports, localization, and dashboard work;
- fresh and upgrade migrations both pass;
- coupon and university data are migrated without duplicate tables or lost rows;
- legacy V1 and new V2 resources are intentionally separated where required;
- no credentials are embedded in tracked source;
- the dashboard builds successfully;
- staging mobile regression passes in English and Arabic;
- the final implementation commits are pushed to
  `origin/integration/salman-contract-takwa-features`;
- a pull request documents response compatibility evidence, migration counts,
  feature-flag state, and rollback steps.

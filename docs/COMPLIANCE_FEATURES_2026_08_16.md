# Compliance features: terms, public deletion, feature restrictions

## Terms acceptance versioning

- `GET /api/terms/current` is public and returns the current localized version.
- `POST /api/terms/accept` requires Sanctum auth and accepts `terms_version` plus `accepted: true`.
- `GET /api/terms/history` returns the authenticated user's acceptance audit trail.
- `GET /api/admin/terms-versions` lists versions and acceptance counts.
- `POST /api/admin/terms-versions` publishes a new current version. It requires the `legal_affairs.update` permission.

Registration remains backward compatible: `terms_version` is optional for old clients. If supplied, it must be current. Every new registration is recorded in `terms_acceptances`; legacy `terms_accepted_at` values are backfilled to version `1.0` during migration.

## Public account deletion

- `GET /delete-account` is a public, login-free Google Play account deletion page.
- `POST /delete-account` creates a pending request without revealing whether an email is registered.
- `GET /api/admin/account-deletion-requests?status=pending` lists requests.
- `PUT /api/admin/account-deletion-requests/{id}` with `status: completed|rejected` resolves one. Completing a matched request invokes the existing permanent V2 purge service.

## Single-feature restrictions

Supported feature keys are `posting` and `messaging`.

- `GET /api/admin/users/{id}/feature-restrictions` lists restriction history.
- `POST /api/admin/users/{id}/feature-restrictions` accepts `feature`, `reason`, and optional `duration_days`.
- `DELETE /api/admin/users/{id}/feature-restrictions/{restrictionId}` lifts a restriction; optional body: `reason`.

Posting restrictions guard listing creation, draft publication, activation, relisting, and V2 publication/extension. Messaging restrictions guard starting a conversation and sending a message. They do not disable login or unrelated account features.

Run the migration before deploying:

```shell
php artisan migrate --force
```

# Mobile handoff — Terms versions, public deletion, feature restrictions

Base API URL below is `/api`. Authenticated calls require the normal Sanctum bearer token and `Accept: application/json`.

## 1. Terms acceptance is now versioned

Before registration, call public `GET /terms/current`. Display its `title` and `content`, and retain its `version`.

```json
{
  "status": true,
  "data": {
    "version": "1.0",
    "title": "Terms and Conditions of Use",
    "content": "...",
    "effective_at": "2026-08-16T12:00:00+00:00",
    "accepted": null
  }
}
```

Continue sending `terms_accepted: true` to `POST /register`, and now also send the version shown:

```json
{ "terms_accepted": true, "terms_version": "1.0" }
```

`terms_version` remains optional for old released builds. New builds must send it. If a newer version was published while registration was open, the API returns `422` with `data.terms_version`; reload `/terms/current` and show the new text.

### Existing signed-in users

When `GET /terms/current` is called with a valid Sanctum bearer token, `accepted`
is `true` or `false` for that user and current version. Without a token it is
`null` because there is no user to check.

After login or app resume:

1. Call `GET /terms/current` with the bearer token.
2. If `accepted` is `false`, show a non-dismissable acceptance screen.
3. Optionally call authenticated `GET /terms/history` when the user needs their full audit trail.
4. Submit authenticated `POST /terms/accept`:

```json
{ "terms_version": "2.0", "accepted": true }
```

Successful response:

```json
{
  "status": true,
  "message": "Terms accepted",
  "data": { "version": "2.0", "accepted_at": "2026-08-16T12:30:00+00:00" }
}
```

An outdated version returns `422`; reload current terms instead of retrying it. The authenticated own-profile response also contains `data.terms.current_version`, `accepted`, and `accepted_at`.

## 2. Feature-specific restrictions

An account may remain active while only one capability is disabled. The own-profile response now includes:

```json
{
  "capabilities": { "can_post": false, "can_message": true },
  "feature_restrictions": [
    {
      "id": 12,
      "feature": "posting",
      "reason": "Repeated prohibited listings.",
      "ends_at": "2026-08-23T12:00:00+00:00"
    }
  ]
}
```

Use `capabilities` to disable the relevant UI. The backend remains authoritative. A blocked action returns `403`:

```json
{
  "status": false,
  "message": "This feature is temporarily unavailable for your account.",
  "data": {
    "error_code": "feature_restricted",
    "feature": "posting",
    "reason": "Repeated prohibited listings.",
    "restricted_until": "2026-08-23T12:00:00+00:00",
    "restriction_id": 12
  }
}
```

Handle `data.error_code == feature_restricted` globally:

- `posting`: disable creation, publish, activate, sell-again, and extension actions.
- `messaging`: disable starting conversations and sending messages. Reading remains allowed.
- Do not sign the user out or treat the whole account as suspended.
- `restricted_until: null` means indefinite until an administrator lifts it.

## 3. Public account deletion URL

Google Play users can request deletion without logging in at:

```text
{{web_url}}/delete-account
```

The page automatically detects Arabic browsers and also supports explicit language
links: `?lang=en` and `?lang=ar`.

Add an “Account deletion website” link in mobile Settings and open it in the external browser. This is separate from the authenticated V2 permanent deletion endpoint, which remains unchanged.

The page creates a pending request. An administrator reviews it in the React dashboard and may permanently purge the matched account. If deletion occurs while the app still has a token, the next authenticated call returns `401`; clear local credentials and return to sign-in.

## Mobile verification checklist

- Register against current version `1.0`; confirm it appears in `/terms/history`.
- Publish `2.0`, resume an existing session, and confirm the acceptance screen appears.
- Submit `1.0` after `2.0` is current; reload terms on the resulting 422.
- Restrict posting: browsing and chat work, while creating/publishing returns `feature_restricted`.
- Restrict messaging: listings work, while starting/sending chat returns `feature_restricted`.
- Lift a restriction and refresh the own profile; the capability becomes available.
- Open the public deletion URL from a logged-out device and submit a request.

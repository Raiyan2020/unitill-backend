# Backend changes shipped — ad view counter & chat replies

**Shipped:** 2026-09-21. Both features described below are live and covered by
automated tests. This doc summarizes what changed against the two request
docs (`BACKEND_REQUEST_AD_VIEWS.md`, `BACKEND_REQUEST_CHAT_REPLIES.md`) so you
can wire up the client side.

---

## 1. Ad view counter

### What changed

`GET /api/ads/{ad}` now counts the read as a view (deduplicated per viewer)
and returns `views_count` on the ad resource. `{ad}` still accepts either a
numeric id or a `public_id`, unchanged.

```json
{
  "status": true,
  "data": {
    "id": 4,
    "public_id": "RQWNHHGLHL",
    "title": "test",
    "price": 122,
    "is_negotiable": false,
    "views_count": 42,
    "published_ago": "6 minutes ago"
  }
}
```

- `views_count` is always present and always an integer — `0` is a real
  answer, never omitted.
- The count already includes the view from the request that just returned it
  (a seller opening their own listing right after a new view sees the number
  the buyer's visit just produced).
- The owner's own visits are never counted, on any endpoint.
- Also added to `GET /api/my-ads` (list) for the same field, same meaning —
  useful for the seller's own ads list.

### Dedup rule

- One row per **(ad, registered user)** — permanent. Reopening the same ad
  next week still doesn't move the count.
- Guests are deduped by an IP + user-agent fingerprint **for the current
  calendar day** — a new day (or a different network/device) counts as a new
  viewer. This is the "rolling ~24h, under-count rather than double-count"
  behaviour asked for, implemented as a daily bucket rather than a literal
  sliding window.
- A write race (two simultaneous opens by a brand-new viewer) never fails the
  read — the duplicate insert is swallowed, the response is still 200 with
  the correct count.

### Not covered (and why)

There is no `GET /api/v2/my-ads/{id}` — no such route exists in this backend.
The owner's edit screen reads the ad through the same `GET /api/ads/{ad}`
endpoint, which already excludes owner visits from the count, so this is
handled automatically rather than needing a second endpoint.

### Tested by

`tests/Feature/AdViewCounterTest.php` — 7 tests covering every step in the
request doc's "how to verify end to end" list (first view, repeat view today
and after `+1 day`, second distinct viewer, owner never counted, concurrent
duplicate insert doesn't 500, guest fingerprint dedup, `views_count` on
`GET /api/my-ads`).

---

## 2. Chat replies

### What changed

**Sending a reply** — `POST /api/v2/conversations/{conversation}/messages`
(and the unversioned `POST /api/conversations/{conversation}/messages`, same
handler) now accepts an optional field:

| Field | Type | Rules |
|---|---|---|
| `reply_to_message_id` | integer | nullable; must be a message that belongs to `{conversation}` |

Omit it, or send `null`, for an ordinary message — both are treated the same.

If it points at a message from a different conversation (or one that doesn't
exist), the request is rejected with **422**:

```json
{
  "status": false,
  "message": "The selected reply target is invalid.",
  "errors": { "reply_to_message_id": ["The message is not in this conversation."] }
}
```

**Every serialized message** — the send response, `GET
/api/conversations/{id}/messages`, and the private `conversation.{id}`
`message.sent` broadcast — now carries a `reply_to` key:

```json
{
  "id": 91,
  "sender_id": 12,
  "body": "Yes, it is still available",
  "type": "text",
  "created_at": "2026-09-21T09:08:09+00:00",
  "client_message_id": "62f1b0d4c88-9f2a1c7e5b",
  "read_at": null,
  "reply_to": {
    "id": 84,
    "sender_id": 20,
    "sender_name": "Jane Doe",
    "body": "Is the bike still available?",
    "attachment_type": null,
    "is_deleted": false
  }
}
```

`reply_to` is `null` for an ordinary message — the key is always present,
never omitted, so you can rely on a presence check matching your existing
"absent = not a reply" parsing (`null` behaves the same way for you).

| Key | Type | Notes |
|---|---|---|
| `id` | integer | The quoted message's real id — safe to scroll to. |
| `sender_id` | integer | Compute "You" vs. the other person from this (not `is_mine`, which isn't computable on a broadcast). |
| `sender_name` | string | Display name at read time; empty string if the original sender's account no longer resolves. |
| `body` | string | Not truncated server-side currently; `""` for an attachment with no caption. |
| `attachment_type` | string\|null | `image`, `file`, or `null`. |
| `is_deleted` | bool | `true` if the original has since been deleted — `reply_to` is still returned in that case, not dropped. |

### Not covered (and why)

There is currently **no endpoint to delete a single message** in this
backend (only whole-conversation removal for one side, which is unrelated).
The `is_deleted` flag and the underlying soft-delete column are in place and
tested (a directly soft-deleted message correctly still resolves via
`reply_to` with `is_deleted: true`), but nothing today can produce that state
through the API. If/when a delete-message endpoint is needed, say so and it
can be added as a separate piece of work.

### Tested by

`tests/Feature/ChatReplyTest.php` — 7 tests: reply summary shape on send, the
`reply_to: null` key on an ordinary message, the reply surviving a re-fetch
of the transcript, the `message.sent` broadcast carrying `reply_to`, a
cross-conversation reply target rejected with 422, a non-existent reply
target rejected with 422, and a deleted original still rendering `reply_to`
with `is_deleted: true`.

---

## Rollout

Both features are additive — no existing field changed shape, nothing was
removed. Ship whenever convenient on your side; no coordinated release
needed for either.

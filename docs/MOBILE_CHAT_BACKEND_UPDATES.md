# Chat / Conversations backend update

Date: 2026-07-27
Branch: `feature/mobile-payment-updates`
Answers: `BACKEND_CHAT_REQUIREMENTS.md` (written 2026-07-26 from the mobile side)

Everything code-side in that document is done. One item is a **server
configuration change that is not yet applied** — see §1 below, it is the one
that actually makes realtime work.

Several items in the original document were already implemented and were
mis-reported because the Postman examples did not match the real API. The
Postman collection has been regenerated from live responses, so those examples
are now trustworthy.

---

## Summary

| § | Item | Status |
| --- | --- | --- |
| 1.1 | Pusher credentials on the server | **Action required — not applied** |
| 1.2 | A broadcast failure must not fail the write | Done |
| 1.3 | `pusher.key` / `pusher.cluster` in `GET /settings` | Already correct — blocked by §1.1 |
| 2 | Realtime channel + event contract | Confirmed below |
| 3 | `client_message_id` idempotency | Done |
| 4 | Message pagination parameters | Done + clarified |
| 5 | Send returns the full message resource | Was already true |
| 6 | Authoritative field names | Clarified — the app's names are the real ones |
| 7 | Optional opening message on `POST /conversations` | Done |
| 8.1 | Duplicate report response | Was already true |
| 8.2 | `DELETE` semantics + broadcast | Done |
| 8.3 | Archived threads read-only | Was already true |
| 8.4 | `needs_reverify` | Was already true |
| 8.5 | Attachment mime types and size cap | Done |

---

## 1. The 500 on send — fixed, plus one thing still outstanding

### What was actually wrong

Two independent faults, which is why it looked worse than it was.

**Fault A — the broadcaster was never enabled.** `config/broadcasting.php` still
read the Laravel 10 environment variable:

```php
'default' => env('BROADCAST_DRIVER', 'null'),   // BROADCAST_DRIVER=log
```

This project runs Laravel 11, where the variable is `BROADCAST_CONNECTION`. The
`.env` set both, and the wrong one won, so every event went to
`storage/logs/laravel.log` and nothing was ever sent to Pusher. Fixed.

**Fault B — the broadcast could fail the request.** In `ChatService::sendMessage`
the broadcast ran *inside* the database transaction and was not guarded, so a
Pusher error propagated out as a 500 after the message row had already been
committed. That is exactly the behaviour reported: the row exists, the response
is a 500.

### What changed

Delivery now happens **after** the commit and can never fail the call:

```php
// The row is already durable. A Pusher outage or a dead FCM token is logged
// and dropped; it must not surface to the caller as a failed send.
$this->broadcastQuietly(new MessageSent($message));
```

The same guard is applied to `conversation.updated`, `conversation.archived`,
and the FCM push. **A 2xx now means the message is stored, unconditionally.**

Verified by pointing the broadcaster at a non-existent Pusher app to reproduce
the original `Pusher error: 404 NOT FOUND .`:

```
send survives a dead Pusher app          PASS
the row was still written                PASS
a Message is returned to the caller       PASS
archive survives a dead Pusher app       PASS
```

### Still outstanding — server configuration

`GET /settings` already returns `pusher.key` and `pusher.cluster` straight from
config; that code was never wrong. It returned `""` because `test-api` has no
Pusher variables set. Until someone applies this on the server, sends will
succeed but **nothing will be delivered in realtime** — the errors just go quiet
instead of loud.

```dotenv
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=eu
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
```

Then `php artisan config:clear`. Remove any leftover `BROADCAST_DRIVER` line so
the two keys cannot disagree again.

To confirm it took, `GET /settings` must return a non-empty `pusher.key`. The
app should keep its existing behaviour of not attempting a socket connection
while the key is empty.

---

## 2. Realtime contract — confirmed

Channel authorization is `POST {{base_url}}/broadcasting/auth` with the bearer
token.

| Channel | Events |
| --- | --- |
| `private-conversation.{id}` | `message.sent`, `conversation.archived` |
| `private-user.{id}` | `conversation.updated` |

`message.sent` carries **exactly one item of `GET /conversations/{id}/messages`**,
including `client_message_id`:

```json
{
  "id": 23,
  "conversation_id": 11,
  "body": "Hello, is it available?",
  "type": "text",
  "client_message_id": "e3f1c9de-7b42-4a11-9c33-8f0d6b2a41ce",
  "attachment_url": null,
  "attachment_type": null,
  "sender_id": 6,
  "sender": { "id": 6, "name": "John Doe", "initials": "JD", "image": null, "is_online": false },
  "is_mine": true,
  "read_at": null,
  "created_at": "2026-07-27T07:06:54+00:00"
}
```

One caveat: **`is_mine` is meaningless in a broadcast** and will be `false`. A
broadcast has no viewer, so the field cannot be computed. Deriving ownership
from `sender_id`, as the app already does, is correct — keep doing that.

`conversation.updated` carries the full conversation resource, so it can be
merged directly; re-reading the list on arrival is also fine.

`conversation.archived` is delivered on the conversation channel, which both
participants are subscribed to, and is accompanied by `conversation.updated` to
each participant's user channel.

---

## 3. `client_message_id` — implemented

`POST /conversations/{id}/messages` now accepts an optional
`client_message_id` (string, max 64 chars). It is unique per
`(conversation_id, sender_id)`, enforced by a database index rather than only in
application code, so two retries racing each other still cannot both write.

- Resending the same id returns the **existing** message. No second row.
- It is echoed back in the message resource and in the `message.sent` broadcast,
  so an optimistic bubble can be matched exactly instead of by body text.
- Omitting it keeps the old behaviour.

`POST /conversations` accepts it too, for the optional opening message (§7).

Verified end to end through the controller — two identical POSTs, one row:

```
replay returns the same row              PASS   (id 23 both times)
replay writes exactly one row            PASS
client_message_id echoed in the resource PASS
client_message_id echoed in message.sent PASS
a distinct id still creates a new row    PASS
```

The retry heuristic that re-reads the thread and matches on body can be retired
once the app sends this field.

---

## 4. Message pagination — answers to the checklist

- **Is `page` honoured?** Yes. **`per_page`?** Yes, capped at **100**, default 30.
- **Is the order newest-first?** Yes. Page 1 is the newest slice. Continue
  reversing each page for display.
- **Are `current_page` / `last_page` / `total` present?** Yes, and they are now
  emitted **both** at the top level of `data` (what the app reads) and under
  `data.meta` (Laravel's default). Both list endpoints do this, so the two are
  consistent. Nothing needs to change on the app side.
- **Is there a cursor form?** **Yes — `before_id` was never ignored.** It is
  implemented and returns only messages with a lower id.

**Recommendation: prefer `before_id` over `page=` for loading history.** A page
offset shifts every time a new message arrives, so paging with `page=2` during an
active conversation can skip or repeat rows. `before_id` cannot.

---

## 5 & 6. Response shape and field names

The send response was already the full resource — see §2 above for the exact
payload. The documented `{ "id": 2, "body": "..." }` example was fiction in the
Postman collection, not something the API ever returned. That example, and every
other one in the Conversations folder, has now been replaced with a captured
live response.

**These are the real field names.** `other_user`, `last_message`, `unread` and
`attachment` have never existed in this API — those branches can be deleted.

| Meaning | Real field |
| --- | --- |
| the other participant | `participant` |
| last message preview | `last_message_preview` |
| relative time for the list | `last_message_ago` |
| unread messages in the thread | `unread_count` |
| message attachment | `attachment_url` + `attachment_type` |
| archived state | `status == "archived"`, plus `can_send_messages` |

`unread_count` and `last_message_ago` were already present on every list item.

The global unread total (`GET /conversations/unread-count`) was **not** built —
it was marked nice-to-have and was deprioritised. Say the word if the bottom-nav
badge needs it.

---

## 7. `POST /conversations` — opening message now works

`message` was previously accepted by the documentation but **silently discarded**
by the backend; only `ad_id` was ever read. It is now implemented.

- `message` is genuinely optional. Omitting the key is correct; do not send `""`.
- When present it is routed through the same path as any other message, so it
  gets the same guards, the same `message.sent` broadcast and the same push.
- Starting a conversation twice on the same ad returns the **existing** thread.
- Both `multipart/form-data` and `application/json` are accepted.
- `client_message_id` may accompany it.

If the thread is created but the opening message is rejected (for example the ad
sells in the same instant), the call still succeeds with the conversation and the
rejection is logged server-side. Read the thread to confirm the message landed.

---

## 8. Smaller confirmations

1. **Report** — an omitted `description` is accepted for every reason except
   `other`, where it is required. A second pending report of the same type
   returns **422** with a user-readable `message`; showing it verbatim is correct.

2. **Delete** — hides the thread **for the caller only**. The other participant
   keeps it. They are now sent `conversation.updated` on `private-user.{id}`, so
   the list stays consistent on their side. The thread reappears for the caller
   if either side sends a new message.

3. **Archive** — sending into an archived conversation is refused server-side
   with 422 and `data.can_send_messages = false`. `conversation.archived` reaches
   both participants, plus `conversation.updated` on each user channel.

4. **`needs_reverify`** — unchanged, still present as `data.needs_reverify` on
   403 from both `POST /conversations` and `POST /conversations/{id}/messages`.

5. **Attachments** — sent as `attachment` in the multipart body. Previously
   **any** file type was accepted; that is now a whitelist:

   ```
   jpg, jpeg, png, gif, webp, heic, heif, pdf, doc, docx, xls, xlsx, txt
   ```

   Size cap is **10 MB**. Both rejections return 422 with a translated,
   user-readable message in all five supported locales:

   ```json
   {
     "status": false,
     "message": "This file type is not supported.",
     "data": { "attachment": ["This file type is not supported."] }
   }
   ```

   **One caveat the backend cannot fix in application code:** a request larger
   than PHP's `post_max_size` never reaches Laravel — PHP discards the body, so
   it surfaces as an empty-message 422 rather than the size message. The 10 MB
   rule only produces the correct error if `post_max_size` and
   `upload_max_filesize` on the server are set above 10 MB. Worth checking
   alongside the Pusher variables.

   The `/storage` issue noted in `BACKEND_PAYMENT_REQUIREMENTS.md` §0 is still
   open and still blocks chat images from rendering. Out of scope here.

---

## Postman collection

Both `Unitill API.postman_collection.json` and `Unitill.postman_collection.json`
have had their **Conversations** folder regenerated. Nothing outside that folder
was touched.

- Every saved example is now a **captured live response**, not a hand-written
  one. This is what caused the field-name confusion in §6.
- **Send Message** is now `form-data` (matching what the app sends) with `body`,
  `client_message_id` and an optional `attachment` file field, and carries three
  examples: a normal send, a replayed `client_message_id` returning the same row,
  and a rejected attachment type.
- **List Messages** documents `page`, `per_page` and `before_id`.
- **Start Conversation** documents the optional `message` and `client_message_id`.
- The folder description carries the channel/event table from §2.

Re-import both files.

---

## Acceptance checklist

| Check | Status |
| --- | --- |
| Send returns 2xx with the full message while Pusher is misconfigured | Verified |
| `GET /settings` → real `pusher.key` / `pusher.cluster` | **Blocked on server config** |
| Message appears on the other device without refresh | Blocked on server config |
| Conversation list updates on the other device | Blocked on server config |
| `?page=2&per_page=30` returns history with correct counters | Verified |
| Same `client_message_id` twice creates one message | Verified |
| Sending into an archived conversation refused with a readable message | Verified |

The three blocked rows are all the same root cause: the Pusher variables in §1.
They cannot be verified until that is applied on `test-api`.

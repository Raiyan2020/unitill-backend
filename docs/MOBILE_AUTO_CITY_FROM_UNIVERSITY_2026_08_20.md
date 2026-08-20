# Mobile update — city is now auto-filled from the student's university — 2026-08-20

No request or response **shape** changed. `POST /register` still accepts the exact same
fields, and `city` in the user object is still the same `CityResource` shape it's always
been. What changed is **where the value comes from**.

> **TL;DR:** You can stop asking students to manually pick their city at sign-up. Their
> city is now derived automatically from their university (resolved from their
> `student_email` domain, the same domain check that's always gated registration). If you
> still send `city_id` in the request, it's used only as a fallback for the rare case
> where a university has no city on file yet — otherwise it's ignored in favour of the
> university's city.

---

## What actually changed

`POST /register` already required `student_email` to match a domain in our approved
university list — that hasn't changed. What's new is that once that match happens, the
backend now also pulls that university's **city** and saves it as the student's
`city_id`, instead of leaving it to whatever (if anything) the app sent manually.

```
Before: city_id = whatever the client sent (or null)
Now:    city_id = the matched university's city_id, falling back to whatever the
                  client sent only if that university has no city on file
```

Nothing about the `student_email` domain validation itself changed — the same domain
allowlist, the same 422 (`invalid_university_email`) for an email that doesn't match any
active university.

---

## What this means for the app

- **You can remove the manual city picker from the registration screen**, if you have
  one — it's no longer needed for the student's own city to be correct. (You can still
  keep a city field elsewhere in the app for other purposes, e.g. listing location, which
  is unrelated to this.)
- If you still send `city_id`, it's harmless to keep sending — it's just no longer the
  primary source of truth once a university match is found.
- `GET /show-profile`, `GET /v2/show-profile`, and any other endpoint that already
  returns the user object will simply start showing the correct city automatically —
  same `city` field shape you already parse (`{ id, name, ... }` via `CityResource`), no
  new parsing needed.

## What this does NOT change

- Login is unaffected — this only applies at registration.
- The domain-matching rules (exact domain or subdomain of an approved university, e.g.
  `stcatz.ox.ac.uk` matching via `ox.ac.uk`) are unchanged.
- If a university genuinely has no city configured yet on our side (a data-completeness
  gap we're closing separately), registration still succeeds — `city_id` just falls back
  to whatever you sent, or stays `null` if you sent nothing. It never blocks
  registration.

---

## Verify

- Register a new student using a `student_email` at a university with a known city (e.g.
  a `*.ox.ac.uk` address) and confirm the returned/profile `city` is that university's
  city — even if you don't send a `city_id` at all.
- Register the same way but also send a `city_id` for a *different* city — confirm the
  university's city still wins.
- Register with a `student_email` domain that isn't in the approved list — confirm you
  still get the existing 422 `invalid_university_email` error, unchanged.

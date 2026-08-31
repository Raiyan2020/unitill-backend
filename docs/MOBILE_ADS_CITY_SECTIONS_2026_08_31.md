# Mobile update — city-based ad sections on `GET /home` and `GET /ads` — 2026-08-31

No existing field was removed or renamed. Both endpoints gained **one new
additive key** in the response: `recent_ads_all_cities`. Everything you
already parse keeps working unchanged.

> **TL;DR:** Both endpoints now return two ad bands instead of one — ads in
> the viewer's own city, and a separate general "all cities" feed — so you can
> render two sections ("Ads near you" / "Recent ads") from a single response.
> Each band paginates independently.

---

## What actually changed

Previously, both endpoints returned a single list of ads, optionally filtered
down to one city. Now they return **two parallel lists** built from the same
filters/sort, differing only in whether a city constraint is applied:

| Band | City filter | Response key |
|---|---|---|
| Primary | Viewer's city (see resolution below) | `recent_ads` (home) / top-level `data` (ads) — **unchanged keys** |
| Secondary | None — every city | `recent_ads_all_cities` — **new key** |

### How the "viewer's city" is resolved (both endpoints)

1. Explicit `city_id` query param, if sent and valid, **or else**
2. The authenticated user's own `city_id` (from their profile), **or else**
3. Nothing — for a guest with no `city_id` param, there is no city to filter
   by, so the primary band is effectively the same as the all-cities band.

This resolution didn't exist before for `GET /ads` — previously `city_id` was
purely an explicit, optional filter with no fallback to the logged-in user's
city. `GET /home` already had this fallback; it's unchanged there.

---

## `GET /home`

No new required params. `city_id` is still optional and still validated
(`nullable|integer|exists:cities,id`).

```json
{
  "location": { "city_id": 3, "city_name": "Oxford", "display_name": "OXFORD" },
  "categories": [ ... ],
  "recent_ads": {
    "data": [ ...AdResource... ],
    "links": { ... },
    "meta": { "current_page": 1, "per_page": 20, ... },
    "sort_options": [ ... ],
    "current_sort": "newest"
  },
  "recent_ads_all_cities": {
    "data": [ ...AdResource... ],
    "links": { ... },
    "meta": { "current_page": 1, "per_page": 20, ... },
    "sort_options": [ ... ],
    "current_sort": "newest"
  }
}
```

- `recent_ads` — same key, same shape as before. Filtered to the viewer's
  city when known; otherwise unfiltered (same as before this change for
  guests with no `city_id`).
- `recent_ads_all_cities` — **new**. Same sort, no city restriction. Ads
  already shown in `recent_ads` are **not excluded** here — it's a
  straightforward "latest across every city" feed, so there can be overlap
  between the two bands.

### Pagination

Each band paginates independently using a different page-query-param name, so
paging one doesn't affect the other:

- `recent_ads` → `?page=2`
- `recent_ads_all_cities` → `?all_cities_page=2`

`per_page` (shared, optional, default 20, max 50) applies to both bands
equally.

---

## `GET /ads`

Same idea, applied to the full filtered/searched/sorted ads listing.

```json
{
  "data": [ ...AdResource... ],
  "links": { ... },
  "meta": { "current_page": 1, "per_page": 20, ... },
  "sort_options": [ ... ],
  "current_sort": "newest",
  "prioritized_city_id": 3,
  "recent_ads_all_cities": {
    "data": [ ...AdResource... ],
    "links": { ... },
    "meta": { "current_page": 1, "per_page": 20, ... }
  },
  "applied_filters": { ... },
  "active_filter_count": 2,
  "filter_options": { ... }
}
```

- Top-level `data`/`links`/`meta`/`sort_options`/`current_sort` — **same keys
  as before**. This is now the "city band": filtered by `city_id` (explicit
  param, or the authenticated user's city as a fallback — this fallback is
  new). All your other filters (`search`, `category_id`/`main_category_id`,
  `sub_category_id`, `price_min`/`price_max`, `is_negotiable`, `filters[...]`
  attribute filters, `postcode`/`radius_km`) apply exactly as before, on top
  of the city constraint.
- `recent_ads_all_cities` — **new**. Same filters and sort as the main list
  above, minus the city constraint — spans every city. Paginated
  independently via `all_cities_page`.
- `prioritized_city_id` — still present, same key. Previously this only
  affected result *ordering* (ads from your city sorted first, rest after).
  Now it reflects the city id actually used to *filter* the main `data`
  list (`null` if no city could be resolved). If you were using this field
  just to label a "near you" band header, no change needed on your end.

### Important behavior change to be aware of

Before this change, `GET /ads` with no `city_id` param returned ads from
**all cities**, with the signed-in user's own city merely sorted first. Now,
if the viewer is authenticated (or you pass `city_id` explicitly), the main
`data` list is **filtered down to that city** — other cities' ads no longer
appear there at all. If you want the old "everything, city just floated to
the top" behavior for some screen, that's now what `recent_ads_all_cities`
gives you (unsorted-by-city, general feed) — you'd need to merge/reorder
client-side, or keep using the main list understanding it's now city-scoped.

### Pagination

- Main list → `?page=2` (unchanged param name)
- `recent_ads_all_cities` → `?all_cities_page=2`

---

## What this does NOT change

- Request params: no new required params on either endpoint. `city_id` was
  and still is optional on both.
- `AdResource` item shape: unchanged on every ad object in both bands.
- Sorting logic/options (`AdSort`): unchanged, just applied to two query
  branches instead of one.
- Categories list on `GET /home`: unaffected by any of this.

---

## Verify

- **Guest, no `city_id`**: `GET /home` and `GET /ads` — confirm the primary
  band and `recent_ads_all_cities` return the same/overlapping content (no
  city known to filter by), and `location`/`prioritized_city_id` are `null`.
- **Authenticated user with a city, no `city_id` param**: confirm the primary
  band only contains ads from that user's city, and `recent_ads_all_cities`
  contains ads from other cities too.
- **Explicit `city_id` param** (authenticated or not): confirm it overrides
  the user's own city for the primary band, while `recent_ads_all_cities`
  still spans every city.
- **Paging**: fetch `?page=2` and confirm it only advances the primary band;
  fetch `?all_cities_page=2` and confirm it only advances the secondary band.
- **`GET /ads` with other filters** (`search`, `category_id`, `price_min`,
  etc.): confirm both bands respect them identically, differing only by city.

# Mobile API contract harness

The mobile app is a shipped binary. Any change to an existing endpoint's JSON
breaks users who have not updated, so this directory exists to make "did we
change the contract?" a question with a mechanical answer instead of a
judgement call.

`golden/salman/` holds 74 captured responses from the Salman tree — the
implementation the current mobile build was written against. They are the
reference. Everything here compares live responses to those files.

Hand review is not a substitute. Four of the regressions this harness caught
were missed by a careful file-by-file reading of the same diff, and one of them
(`AdFilters` returning zero ads for a legacy query parameter) fails silently:
the endpoint returns 200 with an empty list, so nothing looks wrong.

## Running it

Requires MySQL/MariaDB and a seeded database.

```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="require 'tests/Contract/fixtures.php';"
php artisan tinker --execute="\$OUT='tests/Contract/golden/current'; require 'tests/Contract/capture.php';"
python3 tests/Contract/compare.py tests/Contract/golden/salman tests/Contract/golden/current
```

The last command prints per-scenario differences and a summary line. Narrow to
one scenario by passing a substring as a third argument:

```bash
python3 tests/Contract/compare.py tests/Contract/golden/salman tests/Contract/golden/current public_ads
```

## Reading the output

`STATUS` `REMOVED` `ADDED` `TYPE` `VALUE` `LENGTH` — each is something a client
can observe. `REMOVED` and `TYPE` are the dangerous ones: a missing key or a
string that became a number will crash a typed Dart model, where an `ADDED` key
is usually ignored.

## Expected differences

`CURRENT_DIFF.txt` is the accepted baseline: 39/74 scenarios identical and 186
differences, every one of them a deliberate decision recorded in
`docs/DEVELOPER_HANDOFF_NOTES.md`. They break down as:

| count | reason |
|---|---|
| 114 | location masking — postcode/lat/lng approximated for non-owners |
| 30 | `region`, a new column on ads |
| 11 | `active_filter_count`, additive |
| 10 | coupon payload renamed by the Stripe flow |
| 6 | validation messages now translated |
| 6 | `free_ads_per_user` / `app.url` for the payment flow |
| 4 | privacy `settings` object on own profile |
| 2 | `search` on the conversation list |
| 2 | capture artifact — session row count varies per run |
| 1 | coupon error payload shape |

**A finding outside those categories is a regression.** Re-check it before
merging; do not update the baseline to make it disappear.

## Fixtures

`fixtures.php` builds a deterministic world — fixed ids (9001+), fixed
timestamps, one city, two users, four ads in different states, one coupon. It
also deletes seeder-created demo ads and cities, because the two trees ship
different demo data and that would otherwise read as a contract difference.

It writes only columns present in both trees, so it can run unchanged against
the Salman tree to regenerate the reference set.

## Known gaps

Coverage is 74 scenarios over the highest-traffic endpoints, not all 138 routes.
Write paths (create ad, publish, pay, send message) are not captured — they
mutate state, so they need per-scenario setup and teardown. The publish and
payment flows are the obvious next additions, since that is where the Stripe
work landed.

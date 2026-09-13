# Mobile reading progress

This API stores daily Athkar repetitions and checked Quran verses separately from
mobile preferences. It requires an enabled mobile API and a valid bearer token
with the `mobile:account` ability. An unverified mobile email can use it; admin
tokens and web sessions cannot. The server always derives the account from the
token. No account identifier is accepted in requests or returned in entries.

## Entries and validation

Each entry has exactly these public fields:

```json
{"kind":"athkar","itemKey":"morning:1","day":"2026-09-13","count":1,"revision":42}
```

- `kind`: exactly `athkar` or `quran`.
- `itemKey`: one of the 218 exact IDs from the mobile Athkar catalogue, or a
  canonical Quran `surah:verse` identifier such as `87:19`. Leading zeroes,
  whitespace, non-ASCII digits, unknown entries and out-of-range verses fail.
- `day`: a valid Gregorian calendar date, exactly `YYYY-MM-DD`, from
  `2000-01-01` through `2100-12-31`. The client chooses its local calendar day;
  the server does not reinterpret this date in another timezone.
- `count`: a JSON integer. Quran allows 0 or 1. Athkar allows 0 through the
  entry's repetition target (1, 3, 7, 10, 33 or 100). An entry without an explicit
  repetition instruction has a tracking target of 1. This fallback does not add
  a religious repetition instruction to the displayed text.
- `revision`: a server-assigned, monotonically increasing integer per account.
  Clients cannot send revisions or timestamps to control conflict ordering.

Numeric strings, floating point values, booleans, nulls, missing fields and extra
fields fail validation. POST accepts only a JSON object, not form data. The
original JSON is checked before normalization by global string-trimming middleware.
The existing mobile API body-size limit is 64 KiB.

`config/mobile_reading_items.php` contains only IDs and numeric limits, generated
from `zabi/assets/athkar/catalog.json` and
`zabi/assets/quran/surah_list.json`. Source SHA256 hashes are recorded in its
header. The Quran catalogue contains 114 chapters and 6,236 verses. Arabic
chapter contents were cross-checked against these counts; an existing duplicate
86 in `ar/s0087.json` was reported and corrected separately by the mobile owner.
Backend tests compare the generated limits with the source assets when the mobile
sibling checkout is present. Updating the mobile catalogue requires regenerating
and verifying these limits before enabling new IDs in synchronization.

## Write a batch

`POST /api/mobile/reading-progress/batch`

```json
{
  "operations": [
    {
      "id": "ef1b7957-32a9-42ec-8c5c-d6cf03105385",
      "kind": "quran",
      "itemKey": "87:19",
      "day": "2026-09-13",
      "count": 1
    }
  ]
}
```

One to 100 operations are accepted. Each operation needs an immutable UUID,
generated once when the local change is recorded. Save that UUID and payload
durably before sending; retries must resend the same payload with the same UUID.
UUID comparison is case insensitive, while acknowledgements retain the spelling
sent by the client. Repeated identical IDs in a batch are acknowledged once.

```json
{
  "data": {
    "acknowledged": ["ef1b7957-32a9-42ec-8c5c-d6cf03105385"],
    "entries": [
      {"kind":"quran","itemKey":"87:19","day":"2026-09-13","count":1,"revision":42}
    ]
  }
}
```

Counts are absolute values, not increments. New operations are processed in
server transaction order under a lock on the mobile account. Within a batch,
array order applies. The last newly accepted operation for an entry wins,
regardless of the originating device's clock. Each newly accepted operation gets
a revision, even when its count is unchanged. A retry never reapplies an older
count and does not advance the revision. Reusing a UUID for a different target,
date or count returns HTTP 409 with `code: reading_operation_conflict`; the entire
batch rolls back, including operations preceding that conflict.

The response contains only sent UUIDs and the latest state of each distinct
affected entry, sorted by revision. Thus retrying an older operation after a
newer update acknowledges the retry and returns the newer state. Clients should
remove only acknowledged operations that they actually sent, preserve newer
pending local edits, and compare revisions before merging server entries.

**The POST response has no cursor.** Its affected entries are not a complete list
of changes made by other devices. Advancing the GET cursor from a POST entry's
revision could permanently skip unrelated changes.

## Read incremental changes

`GET /api/mobile/reading-progress?after=0&limit=500`

`after` defaults to 0 and must be a canonical nonnegative integer no larger than
9,007,199,254,740,991. `limit` defaults to 500 and must be 1 through 500. Unknown
query parameters are rejected.

```json
{
  "data": {
    "entries": [
      {"kind":"quran","itemKey":"87:19","day":"2026-09-13","count":0,"revision":43}
    ],
    "cursor": 43,
    "hasMore": false
  }
}
```

Rows are ordered by revision and include only each entry's current state with
`revision > after`. This is a synchronization feed, not an audit of every tap.
Persist the entries and cursor together locally after each successful GET;
continue with the returned cursor while `hasMore` is true. With no results,
`cursor` remains the supplied `after`. Keep a separate cursor for every account
and start at 0 on a fresh local account store.

Unchecked entries remain stored with `count: 0` and their latest revision, so
other devices can clear previously checked readings. Updating a row during
pagination moves it to a later revision; a subsequent page can still discover
it. The client derives daily totals and statistics from synchronized day/entry
records. No statistics endpoint is needed.

## Errors, retention and installation

- 401: `unauthenticated` for absent, expired or unrelated bearer credentials.
- 503: `auth_unavailable` when the existing mobile API feature flag is off.
- 422: JSON `message` and field-keyed `errors` for malformed or invalid input.
- 409: `reading_operation_conflict` for a UUID reused with different content.
- 413: the existing mobile body-size limit was exceeded.
- 429: the existing `mobile-preferences` throttle, shared by both reading routes
  and preference writes (30 requests per minute per account), plus the outer API
  limiter. Retry temporary failures without creating replacement operation IDs.

Migration `2026_09_13_020000_create_mobile_reading_progress_tables.php` adds an
account revision column and two tables: `mobile_reading_entries` (unique account,
kind, item key and day) and `mobile_reading_operations` (unique account and UUID).
The entry table also has an indexed, unique account/revision pair for incremental
pagination. All writes in a batch are transactional. Foreign keys cascade both
tables when an account is deleted. Operation deduplication records and zero-count
entries are retained until account deletion: pruning them independently would
allow an old retry to reapply a removed change or prevent another device from
seeing an uncheck.

Apply the additive migration before enabling these routes in a deployed release.
This feature does not import existing accounts, backfill readings, send mail or
modify preference documents. Rolling the migration back removes reading data and
the revision column only; it leaves the account and preference tables intact.

Run the isolated tests with:

```bash
APP_CONFIG_CACHE=/tmp/salatime-reading-tests-unused-config.php \
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: \
MAIL_MAILER=array CACHE_DRIVER=array LOG_CHANNEL=null \
php vendor/bin/phpunit tests/Feature/MobileReadingProgressApiTest.php
```

The suite selects SQLite `:memory:` before service providers boot, enables foreign
keys, fakes notifications and rejects stray HTTP requests. It performs no
production migration, network request or email delivery.

## Deployment verification — 13 September 2026

The reading controller, item registry, additive migration and mobile routes on
`salatime.net` match the SHA256 fingerprints of the locally reviewed files and
the previous activation receipt. No additional deployment or migration was
necessary when publishing these sources to Git.

Public readback returned HTTP 200 JSON for `/api/mobile/auth/config` and HTTP 401
JSON (`unauthenticated`) for `/api/mobile/reading-progress?after=0&limit=1`,
`/api/mobile/auth/me` and `/api/mobile/preferences`. No real account was used to
write a test reading. The isolated reading, account API and welcome-mail suites
passed together: 43 tests and 627 assertions. This verifies the backend contract;
mobile release availability and synchronization on physical devices are separate
checks.

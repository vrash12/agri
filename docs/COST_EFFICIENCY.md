# Cost and efficiency follow-up

Status: deployed October 19, 2026 in runtime `aeb8eb5`, with the complete
modular-monolith refactor and satellite-index expansion. Public map assets
match the live HTTPS copies. Read-only owner/staff visibility, module/report
and data/configuration/budget-preservation checks passed. Browser performance
and provider billing were not benchmarked. See [the release receipt](GITHUB_DEPLOYMENT.md).

## Measurements

The System Owner / Super Admin provider panel now includes daily/monthly
efficiency figures. `Platform\Observability\UsageMetrics` is shared technical
infrastructure with no business-module dependencies. Its provider observes
Laravel HTTP-client, database and completed HTTP-response events.

The panel shows provider attempts/failures/mean times, reported AI input/output
tokens, cache hits/misses, server response averages/maxima and database query
counts/slow counts/maxima by eight fixed workflows. Slow defaults: 200 ms per
query, 1,000 ms per server response. These are server measurements, not browser
load times, percentiles or cost estimates. AI attempts are SDK invocations;
internal retries and tokens missing from failed responses cannot be reconstructed.
Received tokens count even if generation fails grounding. Cached help has no
new tokens. Weather retries and CHIRPS polling count separately; OAuth remains
separate from satellite processing. Cache reuse describes initial lookups/forced
refreshes, not lock rechecks; a miss need not cause a paid request.

Only fixed dimensions are accepted. SQL/bindings, URLs, credentials, messages and
record/municipality/user IDs are never persisted. URL inspection occurs only in
memory. Aggregates merge into the existing cache after HTTP responses or at
command termination under non-waiting locks. Contention/cache errors can drop
batches without failing business work. UTC day/month keys expire two days after
the period ends. This is not a durable accounting ledger.
`USAGE_METRICS_ENABLED=false` disables recording. No subscription, Redis, table
or new worker is required. Do not clear the general cache: it also holds budgets.

`POST /usage/map-events` accepts fixed purposes and created/reused events. Office
authentication, idle/scope restrictions, Farmer `viewAny`, CSRF and a separate
named `map-usage` 60/minute limit apply. It bypasses mutation locks because it
changes no operational records. Extra fields are discarded. Browser reports may
be missing/duplicated/spoofed; they are not Google's billable events or allowance
reservations. Google Cloud usage/Billing links remain available. No automatic
billing access or invented charges are introduced.

## Map lifetime

The main 3D map starts near the viewport or on an explicit map/farmer action.
Without IntersectionObserver the previous fallback applies. Disclosure, selection
and fullscreen interactions reuse the existing startup promise/map.

`reusable-google-map.js` retains the crop editor's detached canvas/map for this
page's lifetime. Each modal fetches fresh authorized HTML/data. Release removes
parcel/crop overlays, badges, guides and the map click listener before detaching;
remount applies fresh options/bounds. Late library/form responses cannot mount a
closed/superseded editor and late saves cannot close another modal. Unsaved
drawings are discarded on close as before. The 2D/3D map types remain separate;
instance reuse trades bounded page memory for less initialization.

The satellite modal retains only its last successful iframe. Closing cancels
client requests without automatic paid retries. Reopening the same parcel hides
content while a fresh no-store GET reauthorizes and compares configuration.
Unchanged context resumes the map. Changed geometry/settings, different parcels,
expiry, denial or failed verification reloads/discards it. This never authorizes
from browser cache or automatically runs NDVI. Leaving the page releases maps.
No Google imagery/tile cache is added.

## Historical satellite reuse

Sentinel cache v2 includes parcel/office ownership, canonical geometry/grid, UTC
dates, cloud filter, layer, evalscript/processing revision and credential hash.
Changes to those inputs invalidate reuse; unrelated plot-name edits do not.
Policies still run before every statistics/image cache read. PNG responses remain
private/no-store.

Usable statistics and valid images older than a 14-day settlement window retain
30 days by default (configuration bounded to 1–90). Recent results, empty and
wholly masked statistics keep 12 hours for late processing. Historical readings
are not claimed immutable; they refresh after the TTL. Invalid/failed results
are never cached. Coalescing locks and budgets remain in force; cache hits reserve
no processing. New keys warm on authorized requests under existing limits.

Results use the existing private cache. Eviction/disk availability may shorten
retention; expired file-cache entries may remain on disk until touched. Monitor
disk use with hosting tools and preserve budget keys during targeted housekeeping.
Automatic Sentinel scheduling remains the existing capped opt-in. No provider
quota, credential or account change ran.

## Verification and release

Tests use synthetic records/fake providers: attempts/failures, reported tokens,
cache reuse, secret-free aggregates, rollover/failure, middleware/database events,
role visibility, bounded map events, TTL/scope/budget checks, real lazy startup,
canvas reuse and retained-view reauthorization. Existing crop-rendering checks
remain included. These are not production billing/latency or live provider
visual-acceptance evidence.

Local checks pass 275 JavaScript tests and 131 isolated PHP tests / 1,946
assertions. The PHP run retains 126 existing `.env.testing` warning marks.
Pint (full rules for changed services/new files; import rules for two legacy
adapters), PHP syntax, Blade compilation, 154 routes and whitespace checks pass.
The broader run also attempted four legacy `WeatherAdvisoryAccessTest` cases;
they require an imported disposable schema and fail before exercising the
endpoint because the in-memory database has no `provinces` table. They are
unchanged and excluded from the isolated acceptance count. A synthetic browser
preview confirms panel content renders; screenshots were unavailable, so no
visual acceptance or production latency/billing benchmark is claimed.

Release modules/platform/provider registration/middleware/routes, configs,
views/styles and map scripts with the refactor. Regenerate optimized autoloading
and configuration/route/view caches; mirror public assets to Hostinger's served
directory. No dependency update, migration, account/environment change, allowance
reset or general-cache clear is required. After release check roles, repeated
modal opens, revoked access, crop saves/colors/icons and an explicitly authorized
satellite check. Compare metrics with provider reports before claiming savings.

References: [Google cost guidance](https://developers.google.com/maps/billing-and-pricing/manage-costs),
[Laravel database events](https://laravel.com/docs/12.x/database#listening-for-query-events),
[Laravel HTTP-client events](https://laravel.com/docs/12.x/http-client#events).

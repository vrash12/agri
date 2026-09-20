# Assistance coverage map

Current status: deployed to Hostinger on September 20, 2026 in release 54cc9e4. Earlier local-only statements below describe implementation-stage checks. See FULL_DEPLOYMENT_2026_09_20.md for the deployment receipt and remaining limits.

## Staff workflow

Open **Agriculture & Fisheries → Assistance coverage map**. Choose an authorized
municipality, assistance type, program/sheet reference, and planting period, then
Apply filters. System Owner can switch province or independent-city scope using a
separate scope form. Provincial and municipal users remain inside their assignment.

The report immediately shows municipality figures. **Show map** loads Google Maps
and active boundaries only when needed. **View area** focuses a municipality and
shows its recorded totals. Colors use release-count ranges: zero, 1–9, 10–49,
50–199, and 200+. Gray means no matching records, not confirmed absence of assistance.

## Meaning of the filters and figures

- `rice_distribution_batches.reference` is the program/sheet reference available
  today. Matching is equality under the database's text collation; no substring,
  naming inference, or program-name consolidation is applied. Up to 200 recorded
  suggestions are supplied, and an exact reference can also be typed.
- Planting season/year come only from a rice release's sheet in the same owning
  municipality. Dry and Wet require a year. All seasons with a blank year includes
  all releases. Not recorded includes absent, incomplete, invalid, or inconsistent
  sheet periods and ignores the year. Release dates, harvest seasons, and parcel
  crop classifications are never used as substitutes.
- Non-rice and older releases often have no sheet/planting period. They remain
  visible in All seasons / All years and Not recorded. This feature adds no new
  classification fields and performs no data backfill.
- Releases are transactions. Beneficiaries are distinct existing farmer IDs whose
  ownership matches the release municipality. Missing/foreign farmer links do not
  increase beneficiary counts. Counts refer to registry records, not deduplicated
  real-world identities or an eligible-population denominator.
- Nonnegative recorded quantities are summed only within recognized stored units.
  Unknown units, missing quantities, and negative legacy quantities are disclosed
  and excluded from quantity totals. A recorded zero remains zero. There is no
  conversion or total combining kilograms, pieces, and other units.
- Reported location is the release's owning municipality, not the farmer's declared
  farm address or the location of any particular parcel. No release-to-parcel link
  exists in the current schema, so parcel coloring is intentionally unavailable.

## Architecture and limits

`AssistanceCoverageRequest` authorizes the existing release `viewAny` policy before
filter validation. `AssistanceCoverageController` handles HTTP and scope selection;
`AssistanceCoverage` handles scoped aggregate queries and minimal GeoJSON output.
Both routes remain inside authentication, idle, account-scope, veterinary-scope,
and synchronization middleware. Geometry also authorizes the release policy before
validating IDs. The endpoint cannot return another account's municipality geometry.

Only active municipalities supervised by an active province appear. A report is
bounded to 250 municipality rows with a truncation message. Program suggestions
are bounded to 200, and the geometry endpoint accepts at most 10 IDs per request,
at 60 requests/minute. Boundary records exceeding 10,000 recorded vertices or
1.5 MB of serialized geometry are omitted. Multiple active boundaries for one
municipality are treated as ambiguous. Missing/omitted shapes stay in the table.
The server uses a fixed number of grouped aggregate queries, not one per municipality.

The initial HTML contains aggregates but no geometry, farmer identity, contact
details, or parcel records. Google Maps requests and boundary batches are deferred
until Show map or View area. Requests time out after 20 seconds, concurrent opens
share one load, and a failed partial load clears all drawn shapes before retry.
The same displayed totals drive the table, colors, and text-only map popup.
Filters use ordinary GET navigation; the complete table works without JavaScript.

## Verification

Seventeen focused PHP tests passed across the new nine coverage cases and existing
assistance-directory/province-isolation suites. These cover exact periods and
references, duplicate release versus beneficiary counts, separate units, recorded
zero, incomplete and inconsistent links, active scope, roles, validation, geometry
limits, unchanged records, minimal responses, and fixed query count.

Nine JavaScript tests passed: legend thresholds, request batching, deferred load,
duplicate clicks, partial failure/retry, timeout/session failure, unavailable map,
text-only popup content, and year/season control behavior. PHP formatting, syntax,
Blade compilation, route verification, and scoped whitespace checks passed.

Local browser verification uses synthetic SQLite fixtures outside the repository
and actual Google Maps. Dry/wet filtering, unknown periods, zero-release colors
and popups, manual area focus, and empty program results were checked. Desktop and
phone screenshots were reviewed; 320, 390, 768, and 1440-pixel widths had no page
overflow. No console warnings/errors were captured. A guarded read-only check on
the loopback MySQL database also passed for 18 municipalities and bounded GeoJSON.
No operational test records were inserted into MySQL. Provider failure/retry and
expired-session handling are covered by JavaScript tests, not a forced live
provider outage. Production behavior and staff acceptance remain unverified.

## Deployment

This feature is implemented locally and has not been deployed. No migration,
dependency, environment variable, or operational data update is required. Existing
rice-sheet schema and the configured Maps JavaScript API key are prerequisites.

Deploy the three new PHP classes, authenticated routes, coverage Blade view,
assistance-register entry link, and sidebar route matching. Deploy both coverage
assets to `public` and Hostinger's `public_html`, then rebuild route/view caches.
Do not bundle unrelated working-tree changes. Revert those files together to
roll back; there is no coverage database state to remove.

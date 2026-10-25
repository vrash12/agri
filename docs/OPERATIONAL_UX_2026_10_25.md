# Operational UX follow-up — October 25, 2026

Deployed in runtime `cdbc85c` on October 25, 2026; see [live release evidence](OPERATIONAL_RELEASE_2026_10_25.md).
The local verification and acceptance limits below describe the development checkpoints.

## Registry-insight redesign — subsequent local change, not deployed

The Farmers Registry insights disclosure now has a filtered total and applied-filter
labels, then separate gender and ranked farm-location panels. Each row combines its
label, exact count, percentage of the filtered farmer total and a proportional bar.
Values are server-rendered; no chart library, extra request or duplicate table is
needed. Five locations appear first; the remaining supplied top-ten entries use a
native keyboard-accessible disclosure. The remainder is labeled other/unrecorded,
and blank/UNKNOWN labels are excluded from the ranked location display.

The existing controller queries, role policies, municipality ownership, registry
filters and separately unfiltered map scope are unchanged. No migration, data write
or environment change is needed. Ship `farmers/index.blade.php`, the
`registry-insights` / `registry-insight-row` partials and
`public/css/farmer-registry-insights.css` together, mirror the stylesheet where
hosting uses a separate public root, and rebuild compiled views.

Verification: 28 focused PHP tests / 324 assertions passed for presentation and
filtered geographic scope; 15 related finder/workspace JavaScript tests passed.
Tests cover empty/missing breakdowns, exact percentages, very small shares, escaped
labels, bounded ranked rows, local filters and preserving independent map scope.
Pint, changed syntax, Blade compilation, route checks and diff checks passed.

A normal signed-in synthetic MariaDB page at `mapping=unmapped` showed one matching
farmer with an unrecorded location and the correct filter label. A preview generated
from the same shared partial with synthetic figures was checked at measured 1,253px
and 389px viewport widths: panels fit without horizontal overflow, labels/counts
remain visible, and Enter opens/closes extra locations while preserving focus.
This is local browser evidence, not a physical-phone or staff acceptance sign-off.
The screenshot is `output/acceptance-2026-10-25/registry-insights-preview.png` (ignored).
The change has not been committed, pushed or deployed.

The subsequent requested color treatment adds violet/blue gender categories,
neutral unspecified values and a five-color location-rank cycle, including the
expanded rows. The summary has a warm-yellow surface; panels, tracks and count/rank
badges use soft matching fills. Colors supplement the visible labels, percentages
and rank numbers, and do not indicate record status or permanent location identity.
The scoped palette consumes shared primary, info, accent and text tokens, with local
violet/teal/darker-gold chart colors. Stored parcel colors are untouched.
After this follow-up the presentation suite passed 10 tests / 93 assertions.
Browser computed styles confirmed the category colors and no horizontal overflow
at measured 389px and 1,253px widths. Fresh screenshot capture was unavailable;
the earlier screenshot above predates the added colors. No deployment ran.

## Follow-up refinements

- Map and beneficiary pickers share a scoped, bounded literal-name matcher. Names
  can span first/middle/last/suffix fields or be entered surname first. Canonical
  IDs remain supported; same-name choices keep their identifiers. Percent,
  underscore, exclamation and apostrophe characters do not change query semantics.
- Clearing or changing map search invalidates any in-flight response immediately,
  including during the next debounce period. An older reply cannot restore results.
- Satellite defaults and Last 14/30 days now use one captured UTC clock and end
  on the last complete UTC day. Today's incomplete day remains selectable manually.
  Browser Back cancels pending work, restores usable controls and saved readings,
  and offers explicit image retry without extra provider calls or lost cooldowns.
- Current-only Assistant reports explain their lack of historical snapshots when
  asked for a year. Additional English/Filipino phrases resolve to the same fixed,
  scoped readers. See [Assistant details](ASSISTANT_LGU_RECORDS.md).

The broad PHP checkpoint passed **2,093 tests / 36,374 assertions**, with seven
MySQL/MariaDB-only logical-backup tests skipped on SQLite. All **357 JavaScript
tests** passed. Six read-only checks against the isolated synthetic MariaDB database
also passed for full/reordered names, canonical IDs, literal input and LGU isolation.
No external requests were made. The broad run began before the last refinements;
the final affected run passed **1,078 tests / 9,442 assertions** across Assistant,
picker/workspace, satellite/history and architecture checks. Changed PHP syntax,
Pint and `git diff --check` also passed. Blade compilation in isolated storage
and registration of the five affected Assistant/finder/satellite routes passed.

Browser access was disabled in Work settings during this follow-up. No fresh
visual inspection is claimed for these refinements; prior screenshots and browser
checks below describe the earlier synthetic checkpoint. There was no model training,
provider configuration change, production data write, push or deployment.

## LGU dashboards

The existing scoped statistics now power a visible “Your LGU at a glance” strip,
immediately after the key figures. Staff no longer have to open the reports
disclosure to find a graph. Native HTML/CSS charts and readable figures work
without Chart.js or additional requests.

- Current registry: farmers with and without at least one saved parcel.
- Selected report year: distinct farmers with a valid linked assistance release,
  measured against the current registry, with unlinked/undated-record notices.
- Current calendar year: monthly recorded input weight, in kilograms only.
  Pieces, sacks and other units are excluded; inputs are not harvest or yield.

Each chart identifies its period and links to the appropriate register. A missing
aggregate is unavailable, and an empty registry has no calculable coverage
percentage. Zero monthly weight means no weight was recorded for that month.
Existing role restrictions and detailed reports remain intact. These are saved
administrative records, not a claim about eligibility, unmet needs or survey accuracy.

## Farmer parcel workspace

The workflow has direct shortcuts to farmer search, saved parcels and crop period.
Saved parcels now appear before optional registry and rice-seed details. Each
parcel shows its saved area, applied crop year/season and saved crop status. An
unavailable area is not replaced with an unlabelled browser estimate. Missing
crop records do not imply unplanted land.

Actions are grouped into parcel review and boundary/copy tasks. Labels explain
what opens: Show on map, Seasonal crops & drawings, Satellite field check, and
Crop guidance & field checks. Existing permission and concurrency checks remain.
New-boundary fields explicitly describe drafts; deleting names the parcel.

No selection, loading, confirmed empty and failed retrieval are separate states.
A failed request offers Retry parcels and cannot become “No saved parcels.”
Crop-label/selection updates retain expanded parcel cards. Optional information
uses keyboard-accessible native disclosures, and map height now responds to the
mobile breakpoint. Map failure links back to the registry; interactive selection
and the map-linked parcel list still require successful map initialization.

## Satellite checks

A visible three-step guide explains what to do and what imagery can establish.
Check names describe their purpose before their acronym. Recent 14/30-day
shortcuts only fill dates; they do not request imagery. Index, date and cloud
changes cancel pending browser work and clear old results. Existing provider
limits and cooldowns remain enforced.

Results identify their UTC observation date and separate a usable reading from
successful picture loading. A result shortcut and recovery control help users
reach the result or adjust a cloudy/empty search. Interrupted retained-modal
work leaves an explicit stopped/retry state. Reopening never auto-retries.

Satellite signals remain dated planning observations, not live images, measured
soil moisture, field diagnoses or official drought classifications. Blank/cloudy
pixels are unavailable, not evidence of crop damage. Geometry, saved colors,
quotas, provider calls and access rules are unchanged.

## Checks and remaining limits

- Dashboard suites: 52 tests / 457 assertions passed; the presentation suite
  passed again after the final copy refinement.
- Satellite/history suites: 49 PHP tests / 407 assertions passed; 29 focused
  JavaScript checks passed, including invalidation, retry, cooldown and late replies.
- Parcel presentation: 7 PHP tests / 65 assertions passed. Focused map/parcel
  JavaScript checks cover geometry, unavailable states, saved values, permissions
  and preserving open cards.
- All current JavaScript suites: 353 tests passed. Pint, changed syntax checks,
  Blade compilation, route inspection and `git diff --check` passed.
- A normal signed-in synthetic MariaDB browser session showed 2 scoped farmers,
  1 with parcels, 1 assisted farmer and 540 kg of annual releases. Foreign-LGU
  records were excluded. Enter opened the monthly figure table.
- At measured 389px viewport width, all three dashboard chart cards had matching
  client/scroll widths, and the satellite page had no horizontal overflow. The
  satellite index selection, 14-day shortcut and keyboard disclosure worked.
- The parcel page exposed the revised hierarchy, keyboard section shortcuts,
  scoped farmer search and explicit unavailable-map fallback.

The local harness uses synthetic data with providers disabled or faked. Live
Google Maps geometry interaction and fetched Copernicus image appearance were
not visually retested. Automated state/geometry/provider-contract tests passed;
they do not replace live-provider acceptance. Physical phones, screen-reader audio,
true browser zoom and staff sign-off remain unverified.

Local screenshots are in the ignored `output/acceptance-2026-10-25/` directory;
they contain only synthetic records. No customer data, credentials or private
provider configuration belongs in source control.

## Release requirements

No migration, new dependency, environment setting, operational data change or
additional provider use is required. Ship these groups together:

1. Dashboard view, dashboard styles and `dashboard/partials/lgu-insights`.
2. Farmer map view/crop controls, `farmers-maps.js`, `farmer-finder.js`,
   `FarmerController`, `FarmerPicker` and `FarmerIdentitySearch`.
3. Shared satellite view, `ParcelSatelliteController`, `SatelliteIndex`
   presentation and satellite CSS/JavaScript.

Refresh compiled views and normal deployment caches, mirror public assets when
hosting uses a separate web root, and retain the existing general cache/provider
counters. This change does not authorize production changes or the separate
fresh-installation baseline migration.

Related: [LGU Assistant changes](ASSISTANT_LGU_RECORDS.md),
[satellite documentation](SATELLITE_INDICES.md),
[parcel statistics](FARMER_PARCEL_STATISTICS.md),
[design implementation](../DESIGN_IMPLEMENTATION.md).

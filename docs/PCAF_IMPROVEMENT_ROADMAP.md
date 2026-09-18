# PCAF–IICTU Improvement Roadmap

Persistent progress tracker for the Agriculture Information System, derived from the
PCAF Interim ICT Unit (IICTU) stakeholder evaluation.

**Update this file at the end of every milestone** so a later session can continue
from it without re-deriving the evidence.

---

## How to read this document

The IICTU letter is an **initial observation of the user interface and currently
visible features**. It says so twice, and closes by offering a demonstration
because "a more comprehensive assessment of the system's full functionality,
workflows, data processing, integrations, security implementation, and operational
processes would require an actual system demonstration and walkthrough."

Two consequences govern this roadmap:

1. **A recommendation is not proof that a feature is missing.** Several items the
   letter raises already exist and were simply not visible in a UI review. Each one
   is checked against the code before any work is proposed.
2. **Nothing in the letter approves security.** IICTU did not assess security; they
   listed it as an area to strengthen. This document must never be summarised as
   PCAF approval, certification, or a security sign-off.

Status vocabulary: `Not started` · `In progress` · `Blocked` · `Verified complete`.

| Milestone | Title | Status |
| --- | --- | --- |
| 0 | Evidence-based assessment | **Verified complete** |
| 1 | Dashboard and municipality comparisons | **Verified complete** (graph 2 added 2026-09-19) |
| 2 | Data quality, farmer profiles, assistance history | **Complete pending decision 6** |
| 3 | GIS analysis | **Verified complete** |
| 4 | Reporting and mobile workflows | **Complete pending decision 2** |
| 5 | Verification and office demonstration | Not started |

Baseline at the time of writing: **375 automated tests passing (3,998 assertions)**,
plus 11 JavaScript regression tests. Branch `feature/rice-seed-distribution-sheet`.

As of 2026-09-19 on `main`: **418 automated tests passing (4,255 assertions)**, plus
32 JavaScript regression tests.

---

## Milestone 0 — Evidence-based assessment

**Status: Verified complete**

### Office feedback addressed

The whole letter, mapped item by item. IICTU raised four graded sections (UI/UX,
Dashboard, Data Management, GIS), eight specific graphs, and nine overall
improvement areas.

### Feedback matrix

Classifications: **A** already implemented and verified · **B** implemented but
needs improvement · **C** missing · **D** cannot assess without a demonstration or
an operational decision.

#### Graded sections

| # | IICTU observation | Class | Evidence |
| --- | --- | --- | --- |
| 1 | UI/UX "Good"; green/yellow praised; "Attention Needed" praised | **A** | `GREEN_YELLOW_THEME.md`, `partials/operations-ui-styles`, shared `module-*` families. **Preserve as-is.** |
| 1a | Future: consistency across modules | **B** | `<x-module.field>` exists with a tested contract, but only the cooperative form is migrated; 9 forms still hand-write the wrapper. |
| 1b | Future: accessibility | **B** | Labels and `aria-describedby` are correct where the component is used; unmigrated forms are inconsistent. No audit of the whole surface yet. |
| 1c | Future: responsiveness | **B** | Viewport meta present; **24** distinct `max-width` breakpoints (counted in Milestone 4; this row originally said 6) plus `prefers-reduced-motion`. Never verified on real devices. |
| 1d | Future: feedback, validation messages, status indicators | **B** | `partials/form-feedback`, per-field messages, `role="status"` regions exist. Coverage is uneven across modules. |
| 2 | Dashboard "Good, with recommended graph enhancements" | **B** | See the eight graphs below. |
| 3 | Data management "Good foundation"; strengthen completeness, consistency, validation, standardisation | **B** | Controlled vocabularies exist as model constants; several fields remain free text. See Milestone 2. |
| 4 | GIS "High potential" | **B** | Substantial GIS already exists (geofences, parcel classification, snapshot export). See Milestone 3. |

#### The eight requested graphs

| # | Requested graph | Class | Evidence and constraint |
| --- | --- | --- | --- |
| 1 | Farmers by municipality | **B** | Data fully supports it: 1,665 farmers with `municipality_id`. Chart infrastructure exists. |
| 2 | Agricultural production trend by commodity | **B (built 2026-09-19)** | Originally **C / blocked**: the only harvest fields were rice-specific and attached to a seed release (`total_production_bags`, `avg_weight_per_bag_kg`, `avg_area_harvested_ha`), with **1 of 41 releases** carrying any of them, and no commodity-level record at all for corn, vegetables, fisheries or livestock. `harvest_records` now provides that record across nine commodities. The chart is built and tested; it will stay near-empty until production is actually recorded, which remains decision 1. |
| 3 | Assistance releases and beneficiaries by program/municipality | **B** | 41 releases across 9 input categories. Must count **unique beneficiaries separately from release transactions**, and must not sum mixed units (kg, piece, sack, pack, set are all in use). |
| 4 | Farm/parcel mapping coverage | **B (redefined)** | 1,665 farmers, **4** with any parcel, 4 parcels total. Mapped vs unmapped **farmers** is computable. **Unmapped parcel count is not knowable** — there is no total parcel inventory, so a "mapped vs unmapped parcels" chart would be fabricated. |
| 5 | Animal health services | **B** | `anti_rabies_vaccinations` models generalised services (`service_type`, `animal_count`). Currently 0 rows locally. |
| 6 | Fisheries assistance and beneficiaries | **B** | Fisheries categories are first-class (`fish_fingerlings`, `fish_feed`, `fishing_gear`, `aquaculture_input`, `other_fisheries`); fingerlings are enforced as `piece`. |
| 7 | Machinery status by type | **B** | The model already separates `condition_status` from `availability_status`. IICTU's phrasing ("available, operational, and unavailable") **conflates two dimensions**; the chart must keep them separate. |
| 8 | Municipality performance comparison | **B / decision needed** | **Already exists as an HTML table** with client-side filter/sort (`dashboard.blade.php:403-487`), fed by a grouped per-domain rollup (`DashboardController.php:444-650`), built only for `canOverseeSystem()`. So this is a visualisation of an existing rollup. A single composite "performance score" or ranking is **not** supportable — it would be invented. Needs an office decision on which indicators are comparable. |

#### The nine overall improvement areas

| # | Area | Class | Evidence |
| --- | --- | --- | --- |
| 1 | GIS capabilities and spatial analysis | **B** | Geofences, parcel classification, snapshot export all exist. Milestone 3. |
| 2 | Data quality, validation, standardisation | **B** | Milestone 2. |
| 3 | Analytical dashboards and visual reporting | **B** | Milestone 1. |
| 4 | Automated reporting | **D** | "Automated" is undefined — on-demand generation versus scheduled generation and delivery are different projects. A scheduler now runs (`db:backup` nightly), but **no mail transport or queue worker is confirmed available**. Requires an office decision. |
| 5 | Farmer profiling and agricultural records | **B** | Rich farmer profile exists including eligibility flags and historical snapshots on releases. Milestone 2. |
| 6 | Assistance and intervention history | **B** | Per-farmer release history exists (`farmers.records` route). Milestone 2. |
| 7 | Municipality-level comparisons | **B** | See graph 8. |
| 8 | Mobile data collection | **B + C** | Responsive layout exists (**B**). **Offline collection is absent** (**C**): no service worker, no manifest. Treat offline as a separate dependency. |
| 9 | Security and access controls | **B — and not assessed by PCAF** | Substantial controls exist and were hardened recently: policy-per-model with municipality isolation, sign-in rate limiting, security response headers, session encryption, breach-checked passwords, audit trail with secret redaction. **IICTU did not review any of this.** Do not present it as approved. |

### What the Rice Seed Distribution Sheet already provides

Recent work that later milestones must **build on, not duplicate**:

- **`rice_distribution_batches`** groups existing releases for one programme
  reference, planting season and year. A batch never re-counts a release;
  `batch_id` is nullable and legacy releases remain usable without one.
- **Explicit season and year as stored data** (`planting_season`/`planting_year`,
  `harvest_season`/`harvest_year`), rendered dynamically — never hardcoded. This is
  the foundation any production-trend reporting would need.
- **A grouped report pattern**: `App\Support\RiceSeedDistributionSheet` (column
  groups, widths, totals) and `RiceSeedDistributionSheetWorkbook` (Excel with
  repeated headers, merged bands, blank signature column). **Milestone 4 should
  reuse this rather than inventing a second export mechanism.**
- **A seed/harvest separation already modelled**: `seed_bag_kg` (seed bag) is
  deliberately distinct from `avg_weight_per_bag_kg` (harvest bag), and
  `registered_rice_area_ha` is distinct from `farm_area_ha` (total farm area).
- **Formula-injection safety** on every exported cell via `App\Support\CsvExport`
  plus explicit string typing.

### Metric definitions

Required before any chart is built. "Not recorded" is never rendered as zero.

| Metric | Source | Meaning | Unit | Period | Municipality scope | Missing data |
| --- | --- | --- | --- | --- | --- | --- |
| Registered farmers | `farmers` | Count of farmer records | farmers | Cumulative as-of | `farmers.municipality_id` | A farmer without a municipality is excluded and reported separately, never assigned to one |
| Assistance releases | `rice_seed_distributions` | Count of release **transactions** | releases | By `date_received` | `municipality_id` | Release without a date excluded from period charts, shown in an "undated" count |
| Unique beneficiaries | `rice_seed_distributions.farmer_id` | `COUNT(DISTINCT farmer_id)` | farmers | By `date_received` | `municipality_id` | Release with no linked farmer counted as transaction only, never as a beneficiary |
| Quantity released | `kgs_received` + `quantity_unit` | Quantity per unit | **per unit, never summed across units** | By `date_received` | `municipality_id` | Zero is a real value; absent unit shown as "Not recorded" |
| **"Total kg distributed" (existing)** | `SUM(kgs_received)` filtered to `quantity_unit IN (NULL,'','kg')` | **Kilogram releases only.** No unit conversion exists anywhere in the system, so sacks, packs, boxes and litres are silently excluded from every kg figure (`DashboardController.php:665-672`, `RiceSeedDistributionController.php:881-888`, `FarmerController.php:94-98`) | kg | By `date_received` | `municipality_id` | **Must be labelled "kg releases", not "total distributed"** — the current label overstates coverage |
| Mapped farmers | `farm_plots.farmer_id` | Farmers with ≥1 parcel | farmers | Cumulative | via `farmers.municipality_id` | Unmapped = total − mapped. **Unmapped *parcels* is undefined and must not be charted** |
| Mapped area | `farm_plots.area_ha` | Sum of parcel area | hectares | Cumulative | via farmer | Parcel with null area excluded and counted as "area not recorded" |
| Animal-health services | `anti_rabies_vaccinations` | Count by `service_type` | services / `animal_count` | `vaccination_date` | `municipality_id` | Service count and animal count are separate metrics |
| Machinery by condition | `condition_status` | Operating condition | units | As-of | `municipality_id` | Distinct from availability |
| Machinery by availability | `availability_status` | Whether currently available | units | As-of | `municipality_id` | **Never merged with condition** |
| Rice production | `total_production_bags` × `avg_weight_per_bag_kg`, `avg_area_harvested_ha` | Reported rice harvest | bags, kg, hectares | `harvest_season` + `harvest_year` | `municipality_id` | **Currently 1 of 41 releases populated — not reportable.** Rice only; not "agricultural production" |

### Demonstration-data constraint

Four tables hold **zero rows** in the local database: `anti_rabies_vaccinations`,
`farmers_cooperatives`, `agricultural_machineries`, `rice_distribution_batches`.
`farm_plots` holds **4 rows against 1,665 farmers**.

Consequence: graphs 5, 6, 7 and every parcel/geofence statistic can be *built* and
unit-tested, but cannot be **seen working** locally. Milestone 5 needs safe
synthetic demonstration data — never real farmer personal data — and the existing
`TarlacMunicipalityDemoSeeder` is the precedent to follow.

### Blocking dependency: production data

PCAF asked for an "Agricultural Production Trend … by major commodity". The system
has no such record. What exists is rice harvest reported against a seed release,
populated once in 41 rows.

Charting seed or fertiliser releases as "production" would be **factually wrong** —
it reports what was handed out, not what was grown. This roadmap therefore treats
production as a **data dependency, not a chart task**. It needs an office decision
(see below) before any production visualisation is proposed.

### Unresolved decisions for the office

These are genuine questions, not implementation blockers to guess around:

1. **Production recording.** Will the office record actual harvest? For which
   commodities, at what level (farmer, parcel, municipality), in what units, and on
   what reporting cycle?

   *Partly answered by building, 2026-09-19.* Rather than wait on the whole
   question, `harvest_records` was built to hold a harvest at farmer or parcel
   level across nine commodities in six units, and the Rice Seed Distribution
   Sheet's existing production section now projects into it, so rice production
   the office already writes down needs no second entry. What is still genuinely
   open: whether non-rice commodities will be recorded at all (nothing writes
   them today — there is no entry screen for a standalone harvest), and on what
   cycle. The chart is honest while unanswered: it shows what has been recorded
   and counts what has not.
2. **"Automated reporting."** On-demand generation, or scheduled generation and
   delivery? If delivery: to whom, by what transport? No mail service or queue
   worker is confirmed.
3. **Municipality comparison.** Which indicators may be compared, and is any
   composite score acceptable? This roadmap will not invent a ranking.
4. **Mobile data collection.** Responsive entry in a browser, or offline capture?
   Offline requires decisions on device storage of farmer personal data, privacy,
   synchronisation, and edit-conflict resolution.
5. **Seed-class codes, "KP", and consent wording** — still outstanding from the
   Rice Seed Distribution Sheet, pending the complete paper form.
6. **Identifier uniqueness across municipalities.** When two municipalities each
   register a farmer and both records carry the same FFRS or RSBSA number, is that
   always a mistake — the same person recorded twice — or can it legitimately happen,
   for example when a farmer moves or farms in two towns? The system currently answers
   "always a mistake" and refuses the second record. Nobody has confirmed that is the
   office's rule. Nothing will be changed until it is, because loosening a uniqueness
   constraint is easy and undoing it once duplicates exist is not. Raised in
   Milestone 2.
7. **Barangay reference list.** Grouping assistance and farmer records by barangay
   properly needs the list of barangays for each municipality, which this system does
   not hold. `farm_location` is free text and currently clean — 77 distinct values
   with no spelling variants — so nothing is broken, but a report by barangay cannot
   be trusted to be complete without the reference list. Can the office supply it?
   Raised in Milestone 2.

### Acceptance criteria and verification

- [x] The actual letter read and every recommendation mapped to a class.
- [x] Each of the 8 graphs classified with data evidence and stated constraints.
- [x] Each of the 9 overall areas classified.
- [x] Rice Seed Distribution Sheet contribution identified so later work extends it.
- [x] Metric definitions recorded with source, meaning, unit, period, scope and
      missing-data handling.
- [x] Blocking dependencies and office decisions stated rather than assumed.

Verification: row counts and field population read directly from the local
`ag_system` database with read-only queries; code paths read from the working tree
with file-and-line citations, independently produced by the read-only review agent;
322 tests passing as the baseline. No file was modified during assessment.

### Migration / configuration implications

None. Milestone 0 is assessment only; no schema, configuration or behaviour changed.

---

## Milestone 1 — Dashboard and municipality comparisons

**Status: Verified complete** — graphs 1, 3, 4, 5, 6, 7 and a per-indicator 8 are
built, tested and seen rendering. Graph 2 remains deferred behind decision 1.

### What was built

| File | Role |
| --- | --- |
| `app/Support/DashboardMetrics.php` | Ten scoped aggregate methods, one per indicator. This is where the measurement rules live and where they are argued for in comments. |
| `app/Http/Controllers/DashboardController.php` | Injects the service and passes `$dashboardMetrics` to the view. No aggregation was moved out of the existing 27 statistics, so nothing previously on the page changed. |
| `resources/views/partials/dashboard-metric.blade.php` | One indicator card: heading, optional canvas, figures disclosure. |
| `resources/views/partials/metric-figures.blade.php` | Multi-series figures table. The shared `operational-report-figures` renders only one series; releases against beneficiaries needs two columns. |
| `resources/views/dashboard.blade.php` | The indicator grid, the comparison panel with its indicator picker, and the chart drawing routines. |
| `resources/views/dashboard/partials/styles.blade.php` | Styles for the indicator grid. |
| `tests/Feature/DashboardMetricsTest.php` | 19 tests, 136 assertions — one per measurement rule. |

Every metric returns the same shape, and each series' `values` is the same length as
`labels`:

```
['title' => string, 'labels' => string[],
 'series' => [['name','unit','decimals','values'], ...],
 'not_recorded' => null | ['label' => string, 'count' => int]]
```

### Decisions taken while building

- **`quantityByUnit` is rendered as figures only, with no chart.** Local data has
  355 kg beside 5,000 pieces. Drawing those as bars on one axis would invite exactly
  the comparison the "never sum across units" rule forbids, so the units are listed
  as rows and the card says why.
- **`fisheriesAssistance` dropped its piece-count series.** Releases and unique
  beneficiaries are both counts and share an axis honestly; a 5,000-piece fingerling
  figure on the same axis flattened them to nothing. The quantity is reported under
  quantity-by-unit, where the unit is named.
- **The comparison shows one indicator at a time** through a `<select>`. Confirmed
  in the browser: registered farmers peaks at 1,612 and assistance releases at 11, so
  a combined chart would render releases invisible. No score, index, weighting or
  rank is produced anywhere.
- **The comparison lists only active municipalities**, the same set
  `buildMunicipalityOverview()` puts in the table below it, so the chart and that
  table cannot disagree. It is offered to any account that can see two or more
  municipalities — which includes `provincial_staff`, who never had the existing
  `canOverseeSystem()` table.
- **Charts with no data render no canvas at all**, only "Not recorded". An empty
  plot area reads as a broken chart; a sentence reads as an empty register.
- **Chart colours were validated, not chosen by eye.** The brand green `#236344`
  against the info blue `#346a8a` failed colour-vision separation (ΔE 11.3, below the
  15 floor, both reading grey). The pair actually used is `#2e7d52` / `#2f6fb5`, which
  passes every check against the white panel. Condition is an ordered severity scale
  so it runs green-to-red through a neutral middle; availability is a set of states,
  not a severity, so it does not.

### Defects found and fixed during implementation

- `pluck(DB::raw('COUNT(farmers.id)'), ...)` **fatally errors** in Laravel 9.
  `Builder::stripTableForPluck()` splits the raw expression on its last dot and looks
  for a result property named `id)`. Reproduced against the live database; every
  aggregate now uses `selectRaw('... as alias')`, which is also the idiom
  `DashboardController` already used.
- An inline `@php(...)` directive placed between two later `@php … @endphp` blocks in
  the same file gets paired with the wrong closing tag and silently swallows the
  markup in between — here, 130 lines including an `@endif`. The variable is now
  assigned in the top block. `view:cache` does not catch this; compiling the file and
  running `php -l` over the output does.
- `MunicipalityAccess::scope()` runs an existence query on every call. Scoping twenty
  aggregates cost ~30 identical round trips; the service now resolves the visible
  municipality ids once per request. Provincial accounts went from 57 queries to 23,
  municipal from 53 to 19.
- `ProvinceReportingScopeTest`'s hand-built fixture had no
  `rice_seed_distributions.farmer_id`, though the column is real and already in the
  model. Added — unique beneficiaries cannot be counted without it.

*Original milestone plan follows.*

**Office feedback addressed:** section 2 in full (all eight graphs), plus overall
areas 3 (analytical dashboards) and 7 (municipality comparisons).

**Current implementation and evidence** (verified in code, not from documentation):

- The dashboard already computes ~27 statistics in `DashboardController.php:362-414`,
  including mapped/unmapped farmers, mapping coverage, fisheries releases,
  fingerlings, animals served, machinery availability and a maintenance-attention
  scope, plus monthly figures.
- **The dashboard renders exactly one Chart.js chart** — `chartRiceMonthly`
  (`dashboard.blade.php:247`), a monthly kg line built at `DashboardController.php:244-260`.
- That chart does **not** use the shared loader. It is a bespoke inline IIFE
  (`dashboard.blade.php:567-628`) that injects `chart.js@4.4.1` on first disclosure
  toggle. The `renderOperationalCharts` + `partials.operational-report-loader`
  pattern exists only on three module index pages (assistance, animal health,
  machinery). `farmers/index.blade.php:415` has its own injector, and
  `farmers/records.blade.php:117-124` loads Chart.js from an **unpinned URL**.
  So three different loading mechanisms and three versions (4.4.1, 4.4.3, unpinned)
  are in play. Consolidating on the shared loader is part of this milestone.
- "Municipality performance" already exists — but as an HTML table with a
  client-side filter/sort (`dashboard.blade.php:403-487`), fed by a per-domain
  grouped rollup in `buildMunicipalityOverview()` (`DashboardController.php:444-650`),
  built only for `canOverseeSystem()`. Graph 8 is therefore a *visualisation* of an
  existing rollup, not new aggregation.
- Scope is applied before every aggregate via `MunicipalityAccess::scope()`
  (`DashboardController.php:64-102`), which fails closed with `whereRaw('1 = 0')`
  when an account has no usable scope. `FarmPlot` has no `municipality_id` and is
  scoped through `whereHas('farmer')`.
- Lazy loading defers the browser library and rendering, **not the database work**
  (`DESIGN_IMPLEMENTATION.md`).

**Required changes:** implement graphs 1, 3, 4, 5, 6, 7 and a per-indicator version
of 8, following the measurement rules in the metric table. Graph 2 is deferred
pending decision 1. Every chart uses scoped aggregate queries, keeps the underlying
figures readable as a table, and reuses the existing lazy loader.

**Dependencies and unresolved decisions:** decision 1 blocks graph 2 entirely;
decision 3 blocks any composite municipality score. Animal-health and machinery
tables are locally empty, so those charts need demonstration data to be verified
visually (Milestone 5).

**Backend / frontend responsibilities**

- *Backend:* scoped aggregate query methods, one per metric, returning labelled
  series with units; municipal accounts see their own scope and provincial accounts
  their province; feature tests for scope isolation and for "Not recorded" handling.
- *Frontend:* chart blocks inside existing report disclosures; the readable table
  beside every chart; empty and failure states; no new theme — green/yellow stays.

**Acceptance criteria and verification**

- [x] No chart labels seed or fertiliser releases as production. — every title and
      series name is asserted against `/production|yield|harvested|output/i`.
- [x] Release transactions and unique beneficiaries are separate series. — a farmer
      collecting three times is asserted to be 3 releases and 1 beneficiary; a release
      with no linked farmer is 1 release and 0 beneficiaries.
- [x] Quantities never sum across units; each unit is its own series or axis. — kg,
      sacks and pieces are asserted to stay on separate rows with no combined total,
      and the card is figures-only so no shared axis exists.
- [x] Mapping coverage reports **farmers**, not parcels; no unmapped-parcel figure. —
      three parcels on one farmer are asserted to be one mapped farmer.
- [x] Machinery condition and availability render as two distinct breakdowns. — a unit
      that is `excellent`/`in_use` is asserted to appear in both, differently.
- [x] No composite municipality score or ranking appears. — the four series names are
      asserted against `/score|index|rank|overall|total/i`.
- [x] Missing values render "Not recorded", never 0. — two releases with a blank
      category are asserted to appear as a `not_recorded` count of 2, not as a zero
      bar and not dropped. A value outside the vocabulary is shown under its raw key.
- [x] A municipal account sees only its municipality; a provincial account only its
      province — proven by feature tests, not by hiding controls. — both asserted
      against a sibling municipality and a foreign-province municipality. Confirmed
      against live data: a `super_admin` on a province with no records reads 0 farmers
      while the system owner reads 1,665.
- [x] Charts fail soft: tabular figures remain when the library does not load. — the
      figures table is always rendered and the canvas starts `hidden`; the shared
      loader reveals it on success and re-hides it on failure or after its 10s
      timeout.
- [x] Query count and timing measured on the dashboard before and after. — the ten
      metrics cost 19–23 queries and 9–21 ms of SQL. Whole page: 63–71 queries,
      34–46 ms SQL, 75–94 ms wall across system-owner, provincial and municipal
      accounts. (First measurement was 53–57 queries for the metrics alone; see the
      scope-resolution fix above.)

**Verification run:** full suite green — 341 tests, 3,778 assertions. Pint clean. The
dashboard was rendered for `system_owner`, `super_admin`, `provincial_staff`,
`municipal_head` and `municipal_staff`: all 200, the comparison panel present for the
first three and absent for the last two. Opened in a browser against live data — seven
charts drew, no console errors, the indicator picker re-scaled the axis, and the
machinery and animal-health cards showed "Not recorded".

**Known limitations after this milestone**

- Graph 2 (production trend by commodity) is still blocked by decision 1. Nothing in
  this milestone works around it.
- `agricultural_machineries` and `anti_rabies_vaccinations` are empty in this
  database, so graphs 5 and 7 have been proven only by tests with synthetic rows. They
  have never been seen populated in a browser — that belongs to Milestone 5.
- Chart.js is now consistent on the dashboard (4.4.3, via the shared loader), but
  `farmers/index.blade.php` still has its own injector and `farmers/records.blade.php`
  still loads an **unpinned** Chart.js URL. Neither was in this milestone's scope.
- The comparison chart is capable of 56 rows for the system owner. It renders as
  horizontal bars and the page grows rather than truncating, but it has not been
  reviewed on a phone.

### Addendum, 2026-09-19 — graph 2, production trend by commodity

Graph 2 was deferred because production had nowhere to live, not because a chart
was hard to draw. This addendum records what was built to give it one, and what
remains genuinely unanswered.

**Schema.** `harvest_records` (migration `2026_09_19_000100`) holds a harvest as a
record in its own right: municipality, farmer, parcel, commodity, variety, season,
year, date, area harvested, quantity and unit. It is not a field on a release,
because a farmer who planted their own seed still has a harvest and a harvest of
corn or tilapia is not a property of a rice seed hand-out. Nine commodities and six
units, both as model constants. Additive and reversible per AGENTS.md section 9.

**Entry, without asking for it twice.** The Rice Seed Distribution Sheet already has
a production monitoring section, and staff already fill it in on paper. Asking them
to re-enter the same harvest on a second screen so it could appear on a chart would
be the surest way to have it never entered at all. `App\Support\HarvestFromRelease`
projects that section into a harvest record whenever a release is saved, and
migration `2026_09_19_000200` gives the link a unique index so the projection stays
a projection: re-saving updates the same row, clearing the production fields removes
it, and deleting the release takes its harvest with it. Releases recorded before any
of this existed are reached by `php artisan harvests:backfill-from-releases`
(`--dry-run` supported, safe to run twice).

**Measurement rules, and why.**

- *Bags are stored, not kilograms.* `avg_weight_per_bag_kg` sits beside the bag
  count and multiplying the two would give a tidier unit, but that weight is an
  average the office wrote down rather than a weighed total. The counted number is
  what was observed; the bag weight is kept in the record's note, so a kilogram
  estimate can still be worked out and is seen to be an estimate when it is.
- *One series per commodity-and-unit pair.* Sacks and kilograms of one crop are two
  measurements. A single line through both would invent a total. This is the same
  rule graph 3 already follows for mixed assistance units.
- *Both a year and a quantity, or nothing.* A release naming a planted variety is a
  plan, not a harvest; projecting it would put a zero on the chart for a field
  nobody has cut yet.
- *What is missing is counted, not hidden.* Harvest records lacking a year or a
  quantity are reported through the shared `not_recorded` note rather than dropped
  silently from the aggregate.

**Defect found and fixed while building.** The production section of the release
form expanded when the bag, area, weight or planted-variety field was filled, but
not when only the harvest season and year were — so a release whose harvest period
had been recorded looked as though nothing had been. Now covered by a test that was
checked against the unfixed view to confirm it fails there.

**Verification.** Full suite green: **418 tests, 4,255 assertions**, plus 32
JavaScript regression tests. The chart was seen rendering against demonstration rows
spanning 2024–2026 with three commodities in two units, which were then removed.

**What is still open.**

- Decision 1 is only partly answered. Rice production now has a path that costs
  staff nothing extra, but **no non-rice commodity has an entry screen** — nothing
  writes corn, vegetables, fisheries or livestock production today, so those series
  will stay absent until the office says whether it will record them and on what
  cycle. The chart states what it has rather than implying more.
- **The NRP import workbook has no harvest-year column.** The spreadsheet import now
  projects like a save does, and re-running it refreshes a release whose year was
  already entered. But a fresh import carries bag counts with no period to report
  them in, and the import will not derive one — `date_of_sowing_label` is a label and
  the import attaches no sheet, so any year it produced would be invented. Imported
  production therefore stays on the release, visible there, until someone enters a
  harvest year. Adding that column to the workbook would close the gap; that is an
  office decision about the form, not a code change.
**Migration / configuration implications:** none expected — aggregates over
existing columns. If query cost grows, consider caching or separate report
endpoints (per `DESIGN_IMPLEMENTATION.md`), not eager chart loading.

---

## Milestone 2 — Data quality, farmer profiles, and assistance history

**Status: Complete pending decision 6.** Defects 3, 5, 6 and the fixable half of 1
are fixed and tested; 2 and 4 were overstated and are corrected below; the review
surface already existed and is now proven consistent with the figures that link to it.

### Done so far

**Defect 6 — kilogram totals that were not kilogram totals.** This was worse than the
assessment recorded. `kgs_received` holds a *quantity* whose unit lives in
`quantity_unit`, and two places summed it with no unit predicate at all:

- `RiceDistributionBatchController` totalled every release in a sheet and printed the
  result under a column headed **"Total seed (kg)"**.
- `RiceSeedDistributionSheet` did the same for the **printed sheet an officer signs**.

A sheet holding one 5,000-piece fingerling release would have printed "5,000.00 kg of
seed" on a signed document. The system already holds 28 releases in pack, piece, sack
and set. Nothing had gone wrong yet only because **no batch existed** — the feature
had not been used, which made this the cheapest possible moment to fix it.

Also found: `StoreRiceSeedDistributionRequest` derived `kgs_received` from bag count ×
bag weight *regardless of the unit*, so recording 5 bags × 40 kg against a release
counted in pieces wrote 200 into a piece count.

Fixed by giving the rule one home in `App\Support\SeedReleaseQuantity`
(`isKilogramUnit()`, `onlyKilograms()`, `kilogramSumExpression()`) and reaching it from
every caller:

- The derivation now runs only for releases recorded in kilograms.
- A release in another unit is refused when it is attached to a sheet, with a message
  pointing at bag count and bag weight, which is how bagged seed belongs on the sheet.
- Both totals count kilograms only — defence in depth, so a legacy or directly-written
  row cannot corrupt a signed document.
- A non-kilogram row on a sheet prints its unit beside the figure instead of a bare
  number, so the column still adds up to its own total. A total that silently excluded
  a row printed above it would be a worse defect than the one being fixed.
- The sheet list says how many releases the kilogram total leaves out.

**Defect 5 — gender vocabulary drift.** `StoreFarmerRequest` accepted four values;
the assistance directory's filter and dropdown offered three. A farmer saved as
"Unspecified" could never be found again from that page, and a hand-typed
`?gender=Unspecified` fell through the `in_array` check and silently returned *every*
row rather than none. One `Farmer::GENDERS` constant now drives the write validation,
both directory filters and both dropdowns.

**Defect 3 — the "UNKNOWN" drift. The assessment's claim was wrong and is corrected
here.** It recorded that "the same farmer can be counted differently in two places on
one page". That is **not true today**: `farmers.farm_location` carries the
`utf8mb4_unicode_ci` collation, so the plain `= 'UNKNOWN'` comparison and the
`UPPER(...) = 'UNKNOWN'` comparison match the same rows, and both return the same
count against the live database. No figure the office has read was wrong.

What was real is that one rule was written out three separate ways, and they would
stop agreeing the moment the column moved to a case-sensitive collation. A number a
municipal officer reports upward should not depend on a collation setting, so the rule
now lives once in `App\Support\FarmerDataQuality` and the dashboard headline, the
per-municipality rollup and the farmer directory drill-down all reach it from there.

**Tests added:** `tests/Feature/DataQualityRulesTest.php` (4 tests) pins that the
missing-location rule matches every capitalisation of the sentinel, that the query
scope and the grouped expression count identically, that the list behind the dashboard
count holds exactly the records it counted, and that every gender the system will save
can also be filtered for in both directories.
`tests/Feature/RiceSeedDistributionSheetTest.php` gained 4 tests covering the unit
rules above. Full suite: 355 tests, 3,850 assertions, green.

### Defects 2 and 4 — the assessment overstated both. Corrected here.

Both were filed as "free text used as a `GROUP BY` key". That is true of the columns
and turned out not to matter, for two reasons found by looking at the data and at the
queries rather than at the column types.

**The data carries no variants.** Across `farmers.farm_location` (77 distinct values),
`rice_seed_distributions.farm_location` (40) and `seed_variety_claimed` (11), the
number of distinct values is **exactly** the number that survives normalising case and
punctuation. There is not one "Brgy. Uno" beside a "BRGY UNO". Nothing needs cleaning,
and there is no drift to stop.

**Every chart that groups these columns already narrows correctly.** The two "leading
varieties" charts — `DashboardController:264-278` and
`RiceSeedDistributionController:253-264` — both filter to `input_category` rice seed
and both go through `kilogramReleases()`. The assistance module already titles them
"Leading rice seed varieties". The dashboard panel said only "Leading varieties" and
now says the same thing as the module.

**`seed_variety_claimed` is deliberately generic, not accidentally free text.** Ten of
the eleven recorded values are not rice varieties at all — "Agricultural lime",
"Gill net and handline set", "Tilapia fingerlings", "Complete fertilizer 14-14-14".
The field carries the item description for all eleven input categories, the form
labels it "Item, species, or variety", the table column header is "Input issued", and
the filter label is "Item". Constraining it to the thirteen-item rice variety list
would reject the majority of what the office actually records. **No validation should
be added to this field.**

What is genuinely left for `farm_location` is not a validation problem: grouping by
barangay properly needs the barangay list for each municipality, which this system
does not hold. That is a reference-data dependency and an office question, not a code
fix, and it is recorded as decision 7 below rather than guessed at.

### Defect 1 — identifiers: the fixable half is done, the other half is your call

Both claims in the assessment are confirmed. `farmers_ffrs_unique` and
`farmers_rsbsa_no_unique` are **global** unique indexes, not scoped to a municipality.
And the trim ran *after* validation, in `farmerData()`.

**Fixed:** the two identifiers are now trimmed in `prepareForValidation()`, before any
rule sees them. This was reachable and produced a bad failure: submitting
`" FFRS-123 "` when `FFRS-123` already existed passed the unique rule — nothing stored
matched the string *with* its leading space — and was only then trimmed to a value
that did exist, so the insert hit the database's unique index and the officer saw a
database error rather than a message on the field. Whitespace-only input now stores as
absent rather than as an empty string, so two blank identifiers can never collide.
Both are covered by tests.

**Not fixed, because it is not ours to decide (decision 6):**

> When two municipalities each register a farmer and both records carry the same FFRS
> or RSBSA number, is that always a mistake — the same person recorded twice — or can
> it legitimately happen, for example when a farmer moves or farms in two towns?

Today the system answers "always a mistake": the second municipality cannot save the
record at all. Nobody has confirmed that is the office's rule. Until it is confirmed,
**nothing is changed** — the current behaviour stays, because loosening a uniqueness
constraint is easy to do and very hard to undo once duplicate identifiers exist.

For the record, the live database holds 1 farmer with no FFRS and 3 with no RSBSA
number, and no duplicate-identifier conflict has occurred.

### The data-quality review surface already existed

The assessment treated this as something to build. It is already there: all four
"Attention needed" figures on the dashboard are links
(`dashboard.blade.php:205-208`), each opening the list of records behind it —
`?mapping=unmapped`, `?quality=missing_ffrs`, `?quality=missing_location`, and
`?maintenance=attention` on the machinery inventory. None of them was a dead end.

What was missing was any guarantee that a figure and the list it opens agree. A count
that opens a list of a different size is worse than no link, because the officer has
no way to tell which number to believe. That is now pinned by test for all three
farmer figures, and the machinery figure uses the same `needsMaintenanceAttention()`
scope for the count and the list, so it cannot drift by construction.

**Verification:** full suite 358 tests, 3,862 assertions, green. The dashboard, both
farmer drill-downs, the assistance directory filtered by a gender that previously
could not be filtered for, and the sheet list all return 200 for system-owner,
provincial and municipal accounts.

### Still open for Milestone 2

Only decision 6 (identifier uniqueness across municipalities), which is the office's
to make. Everything in the assessment's defect list has been either fixed or shown not
to be a defect.

**Office feedback addressed:** section 3, plus overall areas 2, 5 and 6.

**Current implementation and evidence:** controlled vocabularies exist as model
constants — `INPUT_CATEGORY_LABELS` (11), `QUANTITY_UNIT_LABELS` (12),
`CONSENT_STATUS_LABELS`, machinery `CATEGORIES`/`CONDITIONS`/`AVAILABILITY_STATUSES`,
`SERVICE_TYPE_LABELS`, `ANIMAL_TYPE_LABELS` (13), `SEASONS`. Releases store a
historical identity snapshot. `municipality_id` is never taken from the form, and
`farm_municipality`/`farm_province` are overwritten server-side from the resolved
municipality. Recent work made legacy values editable rather than rejected
(`allowedWithStoredValue()`).

**Specific defects found during assessment** (each needs a decision or a fix):

1. **Identifier uniqueness is global, not municipality-scoped.** `ffrs` and
   `rsbsa_no` carry database-level UNIQUE indexes across all municipalities
   (`StoreFarmerRequest.php:56-57`). Whether two municipalities may legitimately
   hold the same identifier is an **office question**. Also, trim-to-null runs
   *after* the unique rule, so `''` and `'  '` are compared before normalisation.
2. **`farm_location` is free text but is a reporting grouping key** — it drives the
   dashboard data-quality count and two top-10 charts, and the importer writes the
   literal string `'UNKNOWN'` when blank (`RiceSeedDistributionController.php:509`).
3. **Case-sensitivity drift on the same concept**: `DashboardController.php:172-178`
   compares `'UNKNOWN'` case-sensitively while the province rollup (:472) and
   `FarmerController.php:1100` use `UPPER(...)`. The same farmer can be counted
   differently in two places on one page.
4. **`seed_variety_claimed` is free text but is `GROUP BY`-ed** for the "Leading
   varieties" charts. A 13-item variety list exists in the controller as *form
   suggestions only, never validated*. Same for `seed_variety_planted`, `barangay`,
   `pet_breed`, `dosage`, `administration_route`.
5. **Gender vocabulary drift**: the assistance filter hard-codes
   `['Male','Female','Other']` (`RiceSeedDistributionController.php:822`), omitting
   `'Unspecified'`, which `StoreFarmerRequest` does allow.
6. **No unit conversion exists.** Every "kg" total filters to
   `quantity_unit IN (NULL,'','kg')`, silently excluding sacks, packs, boxes and
   litres. Labels must say "kg releases", not "total distributed".

**Required changes:** actionable quality indicators, authorised correction paths,
and label corrections where a figure currently overstates its coverage.

**Dependencies and unresolved decisions:** duplicate-farmer review needs an office
decision on who may merge and on what evidence. **Never auto-merge farmers, rewrite
historical snapshots, or guess missing values.**

**Backend / frontend responsibilities**

- *Backend:* quality-indicator queries, correction endpoints behind policies, audit
  coverage for every correction, tests for "unknown stays unknown".
- *Frontend:* a review surface that shows what is incomplete and why it matters,
  using `<x-module.field>`; no bulk destructive action without confirmation.

**Acceptance criteria and verification**

- [ ] No automatic merge, rewrite, or inferred value anywhere.
- [ ] The same seasonal harvest is not counted again when a farmer receives another
      assistance item — proven by a test with two releases in one season.
- [ ] Historical snapshots on completed releases remain unchanged by later profile
      edits.
- [ ] Every correction is audited and authorised by policy.

**Migration / configuration implications:** possible additive nullable columns for
quality flags. `AGENTS.md` §9 applies — no complete migration history exists, so
additive and reversible only, after `php artisan db:backup`.

---

## Milestone 3 — GIS analysis

**Status: Verified complete** — both defects fixed. Defect 3 was a precedent to
preserve and is untouched. The parcel map's farmer payload is gone entirely; the
`/farmers` page for a provincial account went from **2,287,590 to 329,460 bytes, an 86%
reduction**, and from roughly 400 ms to 81 ms.

### Defect 2 — personal data in the map payload. Fixed, and it was larger than recorded.

Measured against the live database, the `/farmers` page for a provincial account was
**2,287,590 bytes**. Three separate problems, all now fixed:

1. **The farmer payload was emitted twice.** `farmers/index.blade.php` and
   `farmers/maps.blade.php` each serialised the whole workspace, and the page includes
   the second from the first. The `|| ` guard meant the second assignment never even
   took effect — so **886 KB crossed the wire to be discarded**. One emission remains,
   in the map partial that owns the data.
2. **Six of the twenty-two fields were read by nothing.** Checked field by field
   against `public/js/farmers-maps.js` and the map views, and confirmed there is no
   dynamic property access that a name search would miss. The unread six were
   `date_of_birth`, `contact_number`, `rsbsa_no`, `gender`, `registry_id` and
   `last_received` — four of them personal data or government identifiers, for every
   farmer in the province, sitting in the page source. Removed.
3. **The query selected `farmers.*`.** Those columns were read out of the database and
   held in memory for 1,665 farmers even though the map shows fifteen fields. The map
   query now selects only what it renders, so the sensitive columns never leave the
   table for this screen at all.

**Result: 2,287,590 → 1,116,914 bytes, a 51% reduction**, with no personal data in the
page source. Warm render: ~400 ms, 25 queries. Verified for provincial and municipal
accounts; 31 farmer, isolation and map tests pass.

### Defect 1 — unbounded payloads. Parcel endpoint fixed; farmers workspace measured.

**`farm-plots.all` is now capped.** It returned every parcel in scope with no limit —
and for a system owner "in scope" is every parcel in every province. It now returns at
most `config('map.max_plots_per_request')` (default 2,000, set per deployment because
the right number differs by province) and reports `total`, `returned`, `truncated` and
`limit`. The map shows a notice naming both numbers. A cap without that notice would be
worse than no cap: a partial map looks complete, and nothing on screen reveals the
omission. Covered by `tests/Feature/MapPayloadBoundsTest.php`, including that municipality
isolation still holds with the cap active and that a misconfigured cap of zero degrades
to one parcel rather than to a blank map claiming none exist.

**`municipality-boundaries.data` is deliberately left uncapped.** It is already bounded
to a single municipality, and it classifies every parcel against the active boundary to
produce the geofence exception counts. Capping it would not shrink a payload so much as
make those counts wrong, which is worse than a large response. Its real scaling cost is
that the classification is recomputed per request; the snapshot frame beside it is
already cached, and the same approach would apply.

**The farmers workspace now searches instead of pre-loading.** This was the largest
payload in the system and the one the office actually feels. The page used to carry
every farmer in the account's scope so the finder could filter them in the browser —
for a provincial account, 1,665 records serialised into the HTML on every load,
whether or not anyone opened the map.

A new `farmers.lookup` endpoint takes a search term and returns at most fifty matches.
Two properties it must have, both tested:

- **The municipality scope is applied before the search term.** Reversed, the endpoint
  would become a way for one municipality to confirm whether a farmer exists in
  another simply by searching for their name.
- **A wildcard character in the term is searched for literally.** An unescaped `%`
  would otherwise match every farmer in scope.

It also refuses terms under two characters rather than returning an arbitrary slice of
everyone, clamps a caller-supplied `limit` to fifty however large the request, and
reports `total`, `returned` and `truncated` so a capped result can never be read as
the complete answer.

The change was smaller than it looks, because the map already had the machinery:
`ensureFarmerData(id)` fetched a single farmer on demand and populated the caches, and
`__openFarmer3d(id)` already called it first. The inline payload was pre-warming a
cache that had a lazy path all along, and `farmersById` was only ever read by key —
never iterated — so nothing depended on the whole set being present.

**The finder moved to `public/js/farmer-finder.js`**, following the same extraction as
`farmers-maps.js` and `municipality-boundaries.js`: configuration arrives on `window`,
the file holds no template syntax, and it can therefore be cached, linted and tested.
`tests/JavaScript/farmer-finder.test.cjs` covers what the user is told — that a
truncated result names both numbers, that a short query asks for more input rather
than reporting no matches, and that an exact label match wins over the substring
matches containing it, which is what keeps the Locate button usable after a pick.

**What this cost:** a provincial account's `/farmers` page went from 2,287,590 bytes
to 329,460, and its farmer `<option>` count from 1,736 to 71. An `isolation` assertion
in `MunicipalitySeparationTest` that checked the old `mapFarmers` view data was
rewritten rather than removed — it now proves the same property against the lookup
endpoint, which is a stronger check than the original.

### A correction for whoever picks this up

A viewport or bounding-box filter **cannot** bound the farmer payload. The `farmers`
table has no coordinates; `centroid_lat` and `centroid_lng` exist on `farm_plots` and
`municipality_boundaries` only, and the map payload carries no coordinates at all —
`farmers-maps.js` uses it purely as an id-to-farmer lookup. A geographic bound is
available only if the thing being bounded is parcels rather than farmers.

### Defect 3 — preserved

The snapshot export is still deliberately map-only, with no legend, labels or farmer
names. Untouched.

**Office feedback addressed:** section 4 and overall area 1.

**Current implementation and evidence:** more exists than a UI review would show —
municipality geofences with draw/import, one active official boundary per
municipality, parcel classification against a boundary (inside, near, crossing,
outside, invalid, unconfigured) via `MunicipalityBoundaryGuard` and `GeoGeometry`,
a field-review list, and a municipality snapshot export. Parcel colours are stored
per parcel. Only **4 parcels exist locally**, so spatial features are structurally
present but barely populated.

**Required changes:** define the operational questions first, then add supported
parcel filters and summaries (commodity, season, mapping status, geofence
exceptions).

**Defects found during assessment:**

1. **Unbounded map payloads.** `farm-plots.all` returns *every* plot in scope with
   no pagination or limit (`FarmPlotController.php:41-73`), and
   `municipality-boundaries.data` loads all plots for a municipality
   (`MunicipalityBoundaryController.php:109-116`). Harmless at 4 parcels; it will
   not stay harmless. `AGENTS.md` requires bounded map payloads.
2. **Privacy surface on the farmers map.** `window.__farmersMapData` ships the
   entire municipality workspace to the browser including `contact_number` and
   `date_of_birth` (`FarmerController.php:254-264`), deliberately unfiltered by the
   registry search. Scoped correctly, but worth an explicit decision on whether
   contact and birth data belong in a map payload.
3. Good existing precedent to preserve: the **snapshot export is deliberately
   map-only** — no legend, labels or farmer names (`municipality-boundaries.js:381-382`).

**Dependencies and unresolved decisions:** **parcel-level attribution.** A release
is recorded against a *farmer*, not a parcel. A farmer-level release must not be
drawn on every parcel as though attribution were known. Linking production or
assistance to a specific parcel requires either new data capture or an explicit
office decision.

**Backend / frontend responsibilities**

- *Backend:* bounded, scoped parcel queries; no point-in-polygon logic outside
  `GeoGeometry`.
- *Frontend:* filters and summaries on the existing map workspace; stored parcel
  colours preserved; bounded map payloads.

**Acceptance criteria and verification**

- [ ] Each GIS addition answers a written staff question using traceable data.
- [ ] No farmer-level record is attributed to a parcel without supporting data.
- [ ] Municipality isolation holds on every map endpoint.
- [ ] Boundary-source limitation still stated: geoBoundaries references are
      approximate planning boundaries, not cadastral or survey-grade.

**Migration / configuration implications:** none expected unless parcel-level
attribution is approved, which would need new columns and a migration.

---

## Milestone 4 — Reporting and mobile workflows

**Status: Complete for everything not gated on decision 2.** The three recorded gaps
are fixed; scheduled delivery remains blocked because "automated" is still undefined
and no mail transport or queue worker is confirmed. The mobile assessment is done and
its result is recorded below rather than acted on, which is what the criterion asked.

### Gap 1 — exports that left no trace. Fixed, and it was two exports, not one.

The assessment recorded that `rice-seed-distributions.export` wrote no audit event.
`farmers-cooperatives.export-excel` did not either — `AuditTrail` appeared in that
controller only for membership changes.

Between them these two files carry every recipient's **date of birth, gender and six
eligibility flags** — 4Ps, IP, PWD, senior citizen, OFW and agrarian reform
beneficiary. Those say things about a household the household did not choose to
publish, and once the file is on someone's laptop the system has no further say in
where it goes. The audit entry is the only record that it left at all.

Both now record before the download begins, so an abandoned download is still an
export that happened. The assistance export also records **which filters were used**,
because "who exported the register" and "who exported the twelve 4Ps recipients in one
barangay" are different events and a row count cannot tell them apart. Unused filters
are stripped, so an unfiltered export does not read as if a dozen blank filters were
set.

### Gap 2 — the cooperative workbook. Fixed, and it was worse than recorded.

The assessment noted it loaded all members into memory and wrote cells directly. The
second half was the serious one: `setCellValue()` **infers the cell type**, so a value
beginning with `=` becomes a live formula in the delivered file. Confirmed directly —
`setCellValue('=1+1')` produces a cell of type `f`; the guarded write produces type
`s`. A farmer's name is free text, and a workbook that executes something because of
what was typed into a registry field is a way into whichever machine opened it. The
three CSV exports had this guard through `App\Support\CsvExport`; the one workbook
written by hand did not.

Every cell now goes through the same `put()` guard the Rice Seed Distribution Sheet
uses — `setCellValueExplicit(..., CsvExport::value($v), DataType::TYPE_STRING)` — and
the members are read in chunks of 500 rather than loaded at once.

### Gap 3 — a register that could not say where its rows belonged

Not in the original list, found while checking the "states its municipality scope"
criterion. The assistance export had **no municipality column**. Barangay names repeat
across municipalities, so a provincial account exporting its whole province produced
rows that could not be told apart. It now carries Municipality and Province, taken
from the snapshot columns on the release rather than joined from the farmer — a
register should say where the hand-over belonged at the time, and the snapshot costs
no query.

### Mobile assessment — done, not acted on

The criterion asks for an assessment before any offline work, so this is the result
rather than a change.

**Better than recorded.** The assessment counted a handful of breakpoints; there are **24**
distinct `max-width` breakpoints. `module-table-scroll` is used in 13 views, numeric
fields carry `type="number"` (which is what raises a numeric keypad on a phone —
`inputmode` would be largely redundant beside it), the viewport meta is correct, and
a 44px minimum control height — the accessibility touch-target floor — is declared.

**The one real gap.** Three screens have a horizontally scrolling table with no mobile
reflow at all: `audit_logs/index`, `backups/index` and `farmers/records`. The machinery
and cooperative lists look like gaps by a `data-label` search but are not — both carry
bespoke mobile card markup. Of the three, only **`farmers/records`** — a farmer's own
assistance history — is a screen field staff would plausibly open on a phone. The other
two are office-desk screens. That is the mobile finding worth acting on, and it is one
screen, not a programme of work.

**What this assessment cannot tell you:** whether the parcel map is usable on a phone,
real touch-target sizes as rendered, and behaviour on a rural connection. Those need
a device, and belong to Milestone 5.

### Acceptance criteria

- [x] A report and the dashboard disagree nowhere — each export shares the filtered
      query of its own on-screen list; no new report query was introduced.
- [x] Every report states its period, units and municipality scope — the assistance
      register carries a per-row date, a Unit column beside every quantity, and now
      Municipality and Province.
- [x] Exports stay scoped and authorised; sensitive fields excluded — every export
      calls `authorize('export', ...)` and inherits its list's municipality scope, and
      both exports carrying personal data are now audited.
- [x] Mobile data entry assessed on real viewport sizes before any offline work — done
      statically; the three screens without a mobile reflow are named above, and what
      needs a real device is stated rather than guessed.

**Tests:** `tests/Feature/ExportSafetyTest.php` (5 tests, 87 assertions) covers both
audit entries, the filter metadata, the municipality column, and — by loading the
delivered workbook and inspecting every cell's data type — that a name which looks
like a formula leaves as text.

### Still blocked

Scheduled report delivery, behind decision 2. Nothing here assumes email or a worker.

**Office feedback addressed:** overall areas 4 (automated reporting) and 8 (mobile
data collection).

**Current implementation and evidence:** five export paths exist —
`rice-seed-distributions.export` (CSV, 22 columns), `machinery-inventory.export`
(CSV), `audit-logs.export` (CSV), `farmers-cooperatives.export-excel` (XLSX), and
`rice-distribution-batches.export` (XLSX sheet) plus its print view. The three CSV
exports use `App\Support\CsvExport` and chunk their queries; each shares the same
filtered query as its on-screen list. A scheduler is configured (nightly
`db:backup`). Responsive layout exists with four breakpoints. **No service worker or
manifest — no offline capability.**

**Gaps found during assessment:**

1. **`rice-seed-distributions.export` records no audit event**, while the machinery
   and audit-trail exports do. `AGENTS.md` requires auditing export actions, and
   this export carries dates of birth, gender and six eligibility flags.
2. **`farmers-cooperatives.export-excel` loads all members into memory** with no
   chunking, and writes cells directly rather than through the shared guard.
3. **No export exists** for the farmer directory, animal-health records, the
   cooperative list, or the dashboard itself — the most likely office requests.

**Required changes:** filtered reports that agree with dashboard definitions and
totals, show period and units, and preserve scope. Reuse the sheet's workbook
pattern.

**Dependencies and unresolved decisions:** decision 2 defines "automated". **Do not
assume email delivery or a background worker exists.** Offline collection is a
separate project gated on decision 4.

**Backend / frontend responsibilities**

- *Backend:* report queries sharing the dashboard's definitions; export
  authorisation and audit; every cell through `CsvExport`.
- *Frontend:* filter surfaces, print styles, mobile entry review.

**Acceptance criteria and verification**

- [ ] A report and the dashboard disagree nowhere — same filtered query.
- [ ] Every report states its period, units and municipality scope.
- [ ] Exports stay scoped and authorised; sensitive fields excluded.
- [ ] Mobile data entry assessed on real viewport sizes before any offline work.

**Migration / configuration implications:** scheduled delivery would need a
confirmed worker and mail transport, plus `.env` configuration. None assumed.

---

## Milestone 5 — Verification and office demonstration

**Status: Not started**

**Office feedback addressed:** IICTU's closing offer of a demonstration and
walkthrough, and the UI/UX follow-ups (accessibility, responsiveness, feedback).

**Required changes:** review accessibility, mobile layouts, validation feedback,
loading/empty/error states, performance, permissions, municipality isolation and
concurrent editing across everything changed.

**Backend / frontend responsibilities:** the read-only review agent reports evidence
and concrete failure scenarios; backend and frontend resolve confirmed defects.

**Acceptance criteria and verification**

- [ ] Focused automated tests **and actual browser checks** — automated tests alone
      are not sufficient for a UI demonstration.
- [ ] A walkthrough mapped back to each item of the original letter.
- [ ] A verified feature checklist, with known limitations and deferred work stated
      plainly.
- [ ] Safe demonstration data — synthetic, never real farmer personal data.
- [ ] Migration and deployment instructions written down.
- [ ] **The output is not described as PCAF approval or security certification.**

---

## Recommended first implementation milestone

**Milestone 1, minus graph 2 (production trend).**

Why this one first:

- It is what IICTU asked for most concretely — eight named visualisations.
- The infrastructure already exists (lazy chart loader with tabular fallback), so
  this is mostly scoped aggregate queries, not new architecture.
- Six of the eight graphs are fully supported by current data.
- It needs **no migration**, so it cannot endanger a database whose schema cannot be
  rebuilt from migrations.
- It forces the metric definitions to be settled, which Milestones 2–4 all depend on.

Sequence after that: **Milestone 2** (data quality feeds every chart's
trustworthiness), then **4** (reporting reuses the settled definitions), then **3**
(GIS depends on the parcel-attribution decision), with **5** closing.

Do not start graph 2 until the office answers decision 1. Charting releases as
production would put a wrong number in front of management, which is worse than an
absent chart.

---

## Change log

| Date | Milestone | Change |
| --- | --- | --- |
| 2026-09-17 | 0 | Roadmap created; evidence-based assessment completed against the IICTU letter. |
| 2026-09-18 | 1 | Graphs 1, 3, 4, 5, 6, 7 and per-indicator 8 built on `App\Support\DashboardMetrics`; dashboard consolidated onto the shared chart loader; 19 measurement-rule tests added. Graph 2 still blocked by decision 1. |
| 2026-09-18 | 4 | Both exports carrying personal data now audited with their filters; the cooperative workbook chunked and every cell written through the formula-injection guard; Municipality and Province added to the assistance register. Mobile entry assessed — three screens lack a mobile reflow, one of which field staff would use. Scheduled delivery still blocked by decision 2. |
| 2026-09-18 | 1 | Follow-up: `not_recorded` on the two municipality-grouped metrics was an unsatisfiable predicate (`municipality_id IN (...) AND IS NULL`), so orphan rows were invisible. Now reported only to an account whose view is unrestricted, gated on the resolved scope rather than the role so a deactivated owner stays fail-closed. |
| 2026-09-18 | 3 | Parcel map payloads bounded: `farm-plots.all` capped with an explicit truncation signal; the farmers workspace payload replaced by a scoped `farmers.lookup` search endpoint and `public/js/farmer-finder.js`. Personal data removed from the map payload. The `/farmers` page fell from 2,287,590 to 329,460 bytes. |
| 2026-09-18 | 2 | Kilogram totals corrected on the sheet list and the printed sheet (defect 6); gender vocabulary unified on `Farmer::GENDERS` (defect 5); missing-location and missing-FFRS rules unified in `App\Support\FarmerDataQuality` (defect 3); farmer identifiers trimmed before validation (defect 1, fixable half). Defects 2 and 4 examined and found overstated. Decisions 6 and 7 raised. |
| 2026-09-19 | 2 | Repeat assistance claims warned on at entry (`App\Support\DuplicateClaimCheck`), and the third-party CDN assets pinned by version with subresource-integrity hashes (`App\Support\Cdn`, `config/cdn.php`). |
| 2026-09-19 | 3 | Negros Island Region municipality geofences added for Negros Occidental, Negros Oriental and Siquijor, from the pinned geoBoundaries revision, verified against PSA areas. |
| 2026-09-19 | 1 | **Graph 2 built.** `harvest_records` gives production a record of its own across nine commodities and six units. The Rice Seed Distribution Sheet's production section projects into it on save (`App\Support\HarvestFromRelease`), so rice production the office already writes down is never entered twice; `harvests:backfill-from-releases` reaches releases nobody will re-open. Bags are stored rather than derived kilograms, because the bag weight beside them is an average rather than a weighed total. One chart series per commodity-and-unit pair, so two units are never summed into an invented total. The spreadsheet import projects too, though the NRP workbook has no harvest-year column, so a fresh import leaves the bag count on the release until a year is entered rather than inventing a period. Decision 1 remains open for non-rice commodities, which have no entry screen yet. |

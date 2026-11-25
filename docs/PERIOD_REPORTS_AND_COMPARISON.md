# Period reports, municipality comparison and harvest reminder — November 24, 2026

Status: **local, not deployed.** Built at the owner's request after the office
reporting decisions were settled in chat on November 24, 2026:

- Automated reporting is an **in-app archive**: on-demand generation plus an
  automatic monthly report. **No email** and no records leave AgriLGU.
- Municipality comparison uses **rates for one consistent year and single-indicator
  ordering, with no composite score or ranking**.
- Non-rice harvest recording is made routine with an **Excel template/import plus a
  dashboard reminder** (the import is documented in [HARVEST_IMPORT.md](HARVEST_IMPORT.md)).

## Period report archive (`/reports`)

| Piece | Location |
| --- | --- |
| Routes | `reports.index`, `reports.create`, `reports.store` (throttle 6/min), `reports.show`, `reports.download` (throttle 20/min) |
| Controller | `App\Http\Controllers\GeneratedReportController` |
| Archive/scope service | `App\Modules\Reporting\Application\PeriodReports` |
| Aggregates | `App\Modules\Reporting\Application\PeriodReportBuilder` |
| Model / policy | `App\Models\GeneratedReport`, `App\Policies\GeneratedReportPolicy` |
| Monthly command | `php artisan reports:generate-monthly [--month=YYYY-MM]`, scheduled on the 1st at 04:30 office time |
| Migration | `2026_11_24_000100_create_generated_reports_table.php` (additive; new table only) |

**Content.** A report covers one month, quarter or year and stores, per covered
municipality and in total:

- Registry and mapping *as of generation*: registered farmers, farmers added in the
  period, farmers with a mapped parcel, mapping coverage, missing FFRS and missing
  farm location counts.
- Assistance released in the period by category: releases, unique beneficiaries
  (valid same-municipality farmer links only) and quantity per unit. Releases with
  no release date are counted separately because no period can be assigned.
- Harvests dated in the period, per commodity **and** unit (never added across
  units). Harvests for the period's year with no harvest date are counted, not placed.
- Animal Health services and animals covered in the period, by service type.
- Machinery units by current availability.
- For multi-municipality reports, an alphabetical side-by-side table of counts.
  No score, index or ranking is produced.

Reports hold aggregates only — no farmer names, identifiers, addresses, contact
details or record text. A report generated before its period ends is marked
**partial**. Archived reports never change when records are edited later; generate
the period again for current figures.

**Scope and access.** Scope comes from `MunicipalityAccess`; a request may narrow a
report to one municipality the account can already see but never widen it.

| Account | Whole-area report | Can open |
| --- | --- | --- |
| System Owner | All municipalities | Every report |
| Regional Head | Assigned region | Reports for municipalities, provinces and the region inside its scope |
| Super Administrator / Provincial Agriculture Staff | Assigned province | Municipality and province reports inside the province |
| Municipal Head / Staff | Assigned municipality | That municipality's reports |
| Provincial Veterinary, GIS Evaluator, Visitor | — | No access (route allowlists and policy) |

Opening a report rechecks **every covered municipality id** against the viewer's
current scope, so a stored scope column never grants access and a reassigned account
loses access to its former area's reports. Generating a report is a read-only
snapshot, so read-only oversight roles may generate them. Generation and downloads
are audited with counts only.

**Monthly run.** One report per active municipality and one per active province
for the previous month (office time zone). A `dedupe_key` unique index makes the run
idempotent: re-running a month, or two overlapping runs, add nothing. Queries are
grouped by municipality within each report (about ten aggregate queries per report;
the total run scales with the number of offices). Inactive supervising provinces
are excluded. Runs with
the existing Hostinger `schedule:run` cron; no queue worker is needed.

## Municipality comparison (dashboard)

`DashboardMetrics::municipalityComparison()` now reports:

| Indicator | Period | Unit |
| --- | --- | --- |
| Registered farmers / farmers with a mapped parcel | Current | farmers |
| Mapping coverage | Current | % of registered farmers |
| Assistance releases *year* | Reporting year (`date_received`) | releases |
| Unique beneficiaries *year* | Reporting year, valid same-municipality links | farmers |
| Assistance reach *year* | Reporting year | % of registered farmers |
| Beneficiaries with a recorded harvest *year* | Reporting year | farmers |
| Harvest follow-up *year* | Reporting year | % of that year's beneficiaries |
| Production *year* · commodity | Reporting year | each harvest unit separately |

This fixes an inconsistency: releases and beneficiaries previously counted all
recorded dates while production used the reporting year. A rate with a zero
denominator is **Not recorded** (null), never 0%. Releases with no date are reported
under their own note. The dashboard adds an **Order** control (municipality name,
highest first, lowest first) that orders by the one selected indicator only, keeping
Not recorded values last (`DashboardChartPages.orderMetric`), and a **Download CSV**
link (`dashboard.comparison.export`, audited, aggregates only, same query as the
screen). The comparison still appears only for accounts that see two or more active
municipalities.

## Harvest recording reminder (dashboard)

`DashboardMetrics::harvestRecordingStatus()` compares saved parcel crop seasons
(`parcel_crop_seasons.crop_year/season/crop`) with harvest records for the same
reporting year, season and commodity inside the account's scope. Seasons with
plantings and no harvest records are flagged **No harvest recorded yet**; harvests
without a season are counted separately. Mixed and not-recorded crops are excluded.
It is a recording reminder, not a measurement of missing production, and infers no
quantity. Operational accounts get Import harvests / Record harvest shortcuts.

## Verification (local)

- `tests/Feature/GeneratedReportTest.php` — 5 tests: own-area figures, period and
  unit separation, no names in summary/CSV, scope widening refused, policy recheck
  across roles, future period refused, veterinary redirect, idempotent monthly run,
  current-month refusal, comparison CSV scope and 404 for single-municipality accounts.
- `tests/Feature/DashboardMetricsTest.php` — updated comparison expectations plus
  rate, valid-link, undated-release and reminder isolation tests.
- `tests/JavaScript/dashboard-chart-pages.test.cjs` — ordering keeps series aligned
  and Not recorded last.

## Release notes

Deploy together after explicit authorization: the migration (apply by explicit path
after a verified backup), the new controllers/service/model/policy/command, the
`Console\Kernel` schedule entry, `routes/web.php`, `layouts/app.blade.php` (Reports
navigation), `dashboard.blade.php`, `dashboard/partials/styles.blade.php`,
`partials/metric-figures.blade.php`, `resources/views/reports/*`, and
`public/js/dashboard-chart-pages.js` mirrored to both Hostinger public directories.
Refresh route/view/config caches. No dependency, environment or account change, and
no email transport is required. Browser verification against a real database was not
performed for this local change.

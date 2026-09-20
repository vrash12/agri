# Dashboard graph enhancements — 19 September 2026

## Staff workflow

Open Dashboard and choose **View graphs and reports**, or expand Reports and office
details. The existing overview remains compact and charts load on demand.

- Machinery: separate stacked bar graphs show condition and availability for each
  equipment type. Each inventory row represents one physical unit. Unknown or
  missing legacy classifications remain visible.
- Animal health: stacked monthly bars count service records by type. A service
  covering 30 animals counts as one service. The separate all-time service/animal
  totals remain available.
- Fisheries: municipality bars show fingerling quantities in pieces. Release
  transactions and distinct beneficiaries remain separate indicators.
- Municipality comparison: select production for a commodity and unit, alongside
  existing farmer, mapped-farmer, release, and beneficiary indicators. At least two
  active municipalities must be in the account's scope.
- Production trend: choose one commodity/unit series; different units never share
  a comparison axis. Missing harvest entries create gaps, while entered zero
  production remains zero. Tables retain three decimal places for harvest amounts.

## Reporting rules

`report_year` is a validated integer from 1900 through 2100. Its default is the
current year. Available years are collected from scoped harvest years, service
dates, and assistance dates, plus the current and explicitly selected year.
It affects monthly animal-health services, fingerling quantities, and production
in municipality comparisons. Other indicators explicitly retain all recorded
dates, the current month/year, or current inventory as appropriate.

Services use `vaccination_date`; fingerling releases use `date_received`; harvests
use `harvest_year`. Undated records are counted separately without assigning a
guessed year. Fingerling quantities require category `fish_fingerlings` and unit
`piece`. Other units and missing amounts are excluded with an explanatory count.
If only incomplete releases exist for a municipality, its quantity is Not recorded.
No releases yields zero. Production without a valid record is Not recorded.

All aggregates apply municipality/province scope before grouping. Municipality
comparison and fingerling municipality graphs list active authorized municipalities.
Machinery and monthly services retain the existing scope of historical records.
No endpoint, role, write permission, or database schema changes are introduced.

## Verification and deployment

Focused metric, presentation, and route/scope tests use isolated in-memory SQLite,
with no operational database writes. Tests cover year boundaries and validation,
municipal/provincial/owner scope, service counts, incompatible units, incomplete
records, actual zero versus missing production, and chart/table rendering.

Verification passed: 52 focused tests / 465 assertions, Pint on all five changed
PHP files, PHP syntax checks, Blade compilation, dashboard route verification,
and `git diff --check`.

Browser checks used temporary synthetic data. The new graphs rendered with the
real pinned Chart.js asset; production selectors changed the displayed graph and
the reporting-year form retained the selected year and opened Reports after reload.
Desktop and 390px phone screenshots were reviewed. Long equipment labels wrap.
There was no horizontal page overflow at 320, 390, or 768px. A simulated missing
chart asset displayed the fallback message while the figures table stayed readable.
These checks do not substitute for a signed-in live office walkthrough.

Deploy the dashboard controller, metric service, dashboard view, dashboard styles,
and the two metric partials together, then run `php artisan view:clear`.
No migration, new environment setting, or public asset build is required.
This enhancement has not been deployed to Hostinger in this task.

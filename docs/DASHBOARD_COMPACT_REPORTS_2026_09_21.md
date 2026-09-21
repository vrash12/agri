# Compact dashboard reports — September 21, 2026

Status: deployed to Hostinger September 21, 2026 at 00:38 UTC. See
[the production receipt](CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md) for live checks,
verified backups and preservation results. The local verification below records
the implementation stage.

Large scopes previously expanded each horizontal municipality chart by 44px per
municipality and displayed the full comparison table immediately. Hundreds of
municipalities therefore made Reports exceptionally long.

The registered-farmer, assistance, fingerling and indicator-comparison charts now
show eight municipalities per page, keeping each chart at most 400px high. Search
matches municipality names without case or accent sensitivity. Previous/Next
controls and a result range keep all municipalities accessible; changing the
comparison indicator retains the search and page. Search resets to the first
matching page. No results hide the canvas and show a clear message.

All metric figures, including the comparison, use closed native disclosures.
Tables have a 360px maximum height, local scrolling, sticky column headings and
keyboard focus. Full figures stay in the HTML and are usable without JavaScript
or the chart CDN. Pagination changes only presentation; authorization, underlying
totals, municipality scope, reporting periods, zero/null distinctions and units
are unchanged. No database changes or migration are needed.

## Verification

- DashboardPresentationTest and DashboardMetricsTest: 42 tests passed using
  isolated SQLite. A 305-municipality fixture retains the final row in all four
  complete figures tables and checks closed, named, focusable disclosures.
- `node --test tests/JavaScript/dashboard-chart-pages.test.cjs`: 15 tests passed
  for aligned paging/search, duplicate names, zero/null preservation, immutable
  source arrays, navigation, no results, clearing and indicator changes.
- Synthetic browser preview: paging, last-municipality search, indicator switch,
  no match and keyboard clearing worked. The no-script preview opened a styled
  figures table. At 320, 390, 768, 1024 and 1440px, no horizontal page overflow
  was detected and municipality charts remained 394px high. A full 305-row table
  scrolled within its 360px outer limit.
- Pint, Blade compilation and `git diff --check` passed. Local PHP is 8.4.10 and
  reports existing vendor deprecations; the production PHP 8.3 runtime was not
  exercised in this check.

## Deployment

The authorized production release deployed these files together:

- `resources/views/dashboard.blade.php`
- `resources/views/dashboard/partials/styles.blade.php`
- `resources/views/partials/dashboard-metric.blade.php`
- `resources/views/partials/dashboard-municipality-chart.blade.php`
- `resources/views/partials/dashboard-metric-figures.blade.php`
- `public/js/dashboard-chart-pages.js`

Mirror the new JavaScript asset into both Hostinger public directories before
refreshing compiled views. Verify a multi-province account and a municipal
account, search and page navigation, year/indicator changes, and figures access.
Keep the previous views and asset available for rollback. This release requires
no seeder or migration; CAR boundary imports are a separate operation documented
in `REMAINING_CAR_LOCAL_SETUP_2026_09_21.md`.

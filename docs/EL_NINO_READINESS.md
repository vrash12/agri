# El Niño readiness dashboard

## Deployed release — October 14, 2026

Runtime `166cdcb` was pushed to GitHub main and installed on Hostinger through
`git pull --ff-only origin main`. The targeted migration created two empty
climate-registry tables. Existing row/schema fingerprints, environment,
session/cache namespaces and NDVI budgets were preserved. All six agriculture
role dashboard renders passed; staff form renders and read-only oversight
policies passed; veterinary and evaluator policies denied access. Ramos rainfall
provider access succeeded. Static asset copies/hashes and private/unauthenticated
HTTP checks passed. No account, synthetic assessment or provider-setting change
ran. See [deployment receipt](GITHUB_DEPLOYMENT.md).

Local verification passed **68 PHP tests / 467 assertions** (climate dashboard,
satellite, weather and dashboard presentation), **6 focused JavaScript tests**,
Pint, syntax, Blade compilation, route and whitespace checks. Real signed-in map
and NDVI visual acceptance remains pending because the browser tool rejected
the local tab under its URL policy.

## Implementation and data boundaries — October 14, 2026

The office dashboard and sidebar link to `/climate-risk`. Agriculture office
roles see only their authorized active municipalities/provinces/region. System
Owner, Regional Head and Super Admin have read-only oversight; provincial
agriculture staff and municipal head/staff can record, update and withdraw
assessments. Visitors, GIS evaluators, veterinary accounts and farmer sessions
cannot access these pages or data routes.

The module combines four kinds of evidence:

- **Office response priority:** Normal, Watch, Alert or Critical is explicitly
  recorded by staff with a source/inspection reference, observation date and
  review date. Normal means routine monitoring; Watch means verify conditions;
  Alert means prepare local response; Critical means prioritize field response.
  These are AgriLGU office priorities, not the PAGASA ENSO alert system.
- **Drought and seasonal rainfall categories:** staff record the source's dry
  condition, dry spell or drought category and below-/near-/above-normal rainfall
  outlook. PAGASA references require a direct HTTPS PAGASA URL; field reviews are
  labeled separately. A seven-day forecast never establishes a seasonal rainfall
  category or official drought declaration.
- **Forecast rainfall:** explicit **Load forecast** uses the existing Open-Meteo
  service at the selected municipality's saved active-boundary reference point.
  Geometry-specific cache keys prevent sharing forecasts after that point
  changes. Existing weather pages and their cache keys are retained. Retrieval
  date, forecast period, missing days and stale results are visible.
- **NDVI:** successful existing parcel satellite checks write a bounded cache
  summary of their most recent usable observation. Zero NDVI remains a valid
  value; masked days cannot replace a usable reading. The newest observation
  wins under a cache lock. Summaries expire after 31 days and are marked older
  after 14 days. Ownership/boundary changes invalidate them. The dashboard never
  launches a Copernicus analysis or interprets greenness as confirmed damage.

## Map, register and workflow

An area has one editable assessment keyed by municipality and a normalized
barangay name (or municipality-wide). Assessments expire by their review date,
at most 31 days after observation. The municipality summary uses the highest
current office priority among its area assessments. Missing/expired assessments
show **Not assessed**, including gray map coverage; no absence is called Normal.
Barangay polygons get their own current status and do not inherit the parent
municipality status. The available planning references remain Ramos (9) and
Baguio (129); other areas retain their barangay register without fabricated
polygons. Staff must use a reference name where an outline list exists.

Staff may explicitly link up to 100 affected parcels in the same municipality.
The server independently rechecks and locks parent farmers/parcels before saving
links. Cross-office IDs are rejected. Parcel ownership changes exclude stale
links from the old office's map/counts. Duplicate links across current area
assessments count once. An affected parcel link denotes a report about that
parcel, not a loss area, loss quantity or insurance/assistance eligibility.

Assessment writes use policies, CSRF, synchronized requests, municipality and
record row locks, transaction retries and optimistic record versions. Duplicate
first submissions cannot replace an existing area; staff must open its update
form. Location cannot be transferred through an update. Withdrawing an incorrect
assessment requires a version and confirmation. Audit records contain area
ownership, level/date and safe counts, never notes, source URLs or parcel paths.

Area rows and reported parcels paginate (30/15/15). The map loads explicitly,
returns at most 80 municipalities and 80 reported parcels, and explains total
coverage. Municipality shapes exceeding 100 KB use saved reference points;
parcel geometry exceeding 500 KB stays listed for field review. Combined map
geometry has a 1 MB budget. Barangay files retain their existing checksum and
100 KB bound. Data and forecast routes use distinct named account throttles
(`climate-risk-data`: 30/minute, `climate-risk-forecast`: 12/minute), separate
from satellite searches/images and ordinary map traffic. Failed requests never
automatically retry. Maps and rainfall controls prevent duplicate in-flight
loads. Tables remain available without Maps JavaScript or a configured key.

## Database and release

Apply only `2026_10_14_000200_create_climate_risk_assessments.php` after a verified
database backup. It creates `climate_risk_assessments` and
`climate_risk_assessment_plots`, with ownership/actor foreign keys, unique area
keys, composite impact keys and current-assessment indexes. It modifies no
existing operational table/record, account or boundary. Rollback drops only
these new tables and their new assessment records.

Release controller/request/model/policy/support, policy registration, routes,
named limits, the weather-service refactor, satellite-summary integration,
sidebar/dashboard links, both views and `public/js/climate-risk.js` /
`public/css/climate-risk.css` together. Mirror the assets into Hostinger's
served `public_html/js` and `public_html/css` directories with 0644 permissions.
Rebuild config, route and view caches; never clear the general cache or reset
provider budgets. There are no new dependencies, credentials or environment
variables. No statuses or disaster reports are automatically seeded.

## Authoritative sources

- [PAGASA ENSO advisories](https://www.pagasa.dost.gov.ph/climate/el-nino-la-nina/advisories)
- [PAGASA seasonal forecasts](https://www.pagasa.dost.gov.ph/climate/climate-prediction/seasonal-forecast)
- [PAGASA drought and dry-spell assessments](https://pubfiles.pagasa.dost.gov.ph/pagasaweb/files/climate/dryspelldrought/)
- [Open-Meteo forecast documentation](https://open-meteo.com/en/docs)
- [Copernicus Sentinel Hub Statistical API](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Statistical.html)

PAGASA bulletins currently require an office review and dated entry; no official
municipality-level drought API or automatic bulletin parser is claimed. Forecast
and NDVI availability remain subject to existing provider access. The browser
tool rejected the local tab under its URL policy; signed-in visual/provider
acceptance must be recorded separately from synthetic tests.

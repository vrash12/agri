# CAR, compact dashboards and sign-in notice — production release

Deployed to `agritarlac.online` with the owner's explicit authorization on
September 21, 2026, **00:38:01–00:38:07 UTC (08:38 Philippine time)**.
Hostinger PHP was **8.3.33**. This was a scoped file deployment, not a Git push.
The unrelated pending primary farmer-ID changes were not included.

## Installed scope

- Dashboard municipality charts show eight rows per page with search and
  Previous/Next controls. Complete figures open in bounded scrollable tables.
- Four named CAR reference imports added **53 municipalities and 53 active
  geofences**, with 53 attributed import audit events. Existing CAR coverage was
  preserved, giving **77 municipality/city references**.
- The office sign-in form displays the confidentiality and testing notice below
  the submit button. It is informational; no signed NDA or acceptance is recorded.
- Eighteen file targets were checked, including mirrored public CSS/JavaScript.
  Compiled views were rebuilt. No migration, environment, dependency, credential,
  account or regional assignment change was made.

| CAR scope | Active geographic references |
| --- | ---: |
| Abra | 27 |
| Apayao | 7 |
| Benguet municipalities | 13 |
| Ifugao | 11 |
| Kalinga, including Tabuk City | 8 |
| Mountain Province | 10 |
| Separate Baguio City | 1 |
| **Total** | **77** |

The legacy Benguet office workspace remains separate from the thirteen geographic
municipalities. The imports do not assign province/city scopes to a Regional Head.
These approximate 2020 planning references require LGU/NAMRIA validation before
official use; they are not cadastral or surveyed boundaries.

## Preservation and recovery

Read-only preflight matched all runtime baselines and 15 dependency fingerprints.
The live database had 83 users, zero farmers, six farm plots and 43 assistance
records. Existing rows were fingerprinted inside the import transaction and
verified unchanged across all tables with an `id` column; no additions were
allowed outside provinces, municipalities, geofences and audit records.

A verified private backup covered **20 tables**, 408,786 bytes, plus copies of
every replaced application file. SHA-256 of the database backup:
`40658f357ee2792dd9b4eeef817928a2344986c6a935494c81beb72cfb84acc5`.
Private recovery files and receipts are in the site's nonpublic application-root
directory `car-dashboard-release-20260921/`. No database contents or credentials
are included in this document.

The installer used maintenance mode during file activation, view compilation,
transactional imports and checks, then returned the site online. Temporary
preflight and installer cron jobs were removed after completion. CLI-only scripts
and private receipts remain for traceability, guarded against repeated execution.

Package manifest SHA-256 identifier:
`80def889f3816724d19d46c482e9f62cf901838b1d2c464853f9d8fb22a67426`.

## Verification

- All **77** production map-controller responses matched the selected municipality
  and returned exactly one active boundary with populated geometry and positive
  area. Provincial and municipal scope checks passed.
- Repeating the four imports changed no geographic rows or audit events.
- Actual production dashboard views rendered for owner, provincial and municipal
  accounts. Figures remained closed by default, owner comparison retained all
  authorized municipalities, and municipal comparison stayed unavailable.
- All 18 installed file checksums matched the package; the environment was unchanged.
- Live `/login` returned HTTP 200, displayed the notice in the browser and used
  the expected styling. Both public CSS/JS assets returned HTTP 200 with hashes
  identical to the local release.
- Prior focused local verification: 42 dashboard PHP tests, 15 dashboard JavaScript
  tests, 26 CAR tests (1,479 assertions) and seven shared presentation tests passed.
  Pint, Blade compilation, PHP syntax and whitespace checks passed.

Signed-in interactive Google Maps rendering and a live browser dashboard session
were not repeated because the office session had expired. Production controller,
authorization and rendered-view checks passed without creating accounts or data
fixtures. Local browser checks covered chart paging/search, indicator changes,
no-script figures, responsive layouts and sign-in notice placement.

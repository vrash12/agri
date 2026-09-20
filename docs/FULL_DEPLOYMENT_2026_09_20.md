# Hostinger deployment — September 20, 2026

## Result

The owner explicitly authorized pushing and deploying all reviewed local work to [AgriGOV](https://agritarlac.online/). The main release of commit `54cc9e45d7d243265cb746b8d956d934edb12622` completed at **12:50:24 UTC** on September 20, 2026. The subsequently requested Mountain Province import completed at **13:11:04 UTC**. Both production receipts report `complete`.

The deployed features include the 20-photo welcome collage and microinteractions, dashboard charts and production comparisons, assistance coverage, seasonal parcel crops, Ramos barangay references and the reviewed mapping fixes. The existing farmer portal remains installed; this release creates no farmer accounts. Public assets were installed in both Hostinger public directories and application caches refreshed.

## Geography and record preservation

- Region II: 93 municipality/city references, including the separately authorized Santiago City scope.
- Negros Island Region: 63 references, including Siquijor and the separate Bacolod City scope. Six Siquijor references already existed; 57 references were added.
- Mountain Province: ten new municipalities and ten active references under one province scope. Bontoc is province-qualified to avoid Southern Leyte collisions.
- Ramos: nine barangay planning references available through the authorized map workflow.

The main release added 150 municipalities and boundaries, bringing its verified totals to 435 municipalities, 426 boundary records and 26 province scopes. Mountain Province subsequently added ten municipalities/boundaries and its supervising scope. The deployment preserved existing geography and operational rows, including the main release's 60 users, 1,667 farmers and 43 assistance releases. It created no accounts or operational samples and changed no existing account assignments. Mountain Province verification fingerprinted existing rows, checked province isolation and confirmed that a repeat import changed no rows.

No schema migrations were required: prerequisite tables were already present. Legacy baseline migrations and demo seeders were not run. Production environment configuration remained unchanged. Geography was imported explicitly after verified private database backups; these named seeders remain outside `DatabaseSeeder` and automatic deployments.

## Verification

| Check | Result |
| --- | --- |
| Main focused PHP suites | 153 tests, 5,299 assertions passed |
| JavaScript suites | 110 tests passed |
| Mountain Province isolated SQLite suite | 9 tests, 241 assertions passed |
| Formatting and static checks | Pint, changed PHP syntax, Blade compilation, routes and Git whitespace checks passed |
| Main production application checks | 203 read-only checks passed on PHP 8.3.33 |
| Installed main release files | 459 target checksums verified; 138 changed runtime/public targets installed |
| Mountain Province production checks | Ten active boundaries and ten map responses verified; existing rows preserved, repeat import unchanged, province isolation passed |
| Public HTTP | Homepage, office login, farmer login and activation returned 200; protected guest map/coverage requests redirected to sign-in |
| Public assets | Six checked CSS/JS responses matched local hashes; all twenty collage images returned valid JPEG responses and rendered in the browser |

The production checks covered role-scoped dashboard and boundary views, assistance coverage across four roles and four season selections, Ramos references, Region II/Negros map responses, independent-city scope separation, oversight/veterinary restrictions, routes and a PhpSpreadsheet XLSX round trip. The live homepage was visually checked. CDN processing changes the delivered JPEG bytes, so HTTP image hashes are not expected to match the source; origin file checksums were verified separately.

## Recovery and cleanup

Private database and application backups were verified before the main installation. The first application verification attempt failed because the CLI view harness lacked the shared validation error bag; application files rolled back successfully. Geography had already committed and was retained. The corrected read-only harness supplied `ViewErrorBag`, and the second release passed all 203 checks. No operational records were removed or reset.

The Mountain Province runner used a fresh database connection after the backup service's raw read-only snapshot, then performed its import and data-preservation checks in one transaction. Its two installed source files and unchanged environment were verified. The private receipts are `full-release-20260920-v2/status.json` and `mountain-province-release-20260920/status.json`, outside the public document root. Temporary deployment schedules were removed after execution. Backups and deployment artifacts remain private and are not committed.

## Remaining limitations

- Geofences are simplified planning references, not cadastral surveys or evidence of land ownership. LGU/NAMRIA validation remains necessary, including reconciliation with barangay or parcel outlines.
- A real-farmer portal pilot and end-to-end validation with the responsible office remain pending; this deployment does not issue farmer credentials.
- Existing Composer advisories outside the updated PhpSpreadsheet package remain. The release does not claim to resolve every framework/dependency advisory.
- Local PHP was 8.4.10; production verification used the supported PHP 8.3.33 runtime. Supported production PHP remains 8.1–8.3.
- Read-only production checks do not replace real-user field validation, provider quota testing or a full mobile/browser acceptance pass.

Source details and explicit commands are in [Mountain Province](MOUNTAIN_PROVINCE_BOUNDARY_SOURCES.md), [Region II](REGION_II_BOUNDARY_SOURCES.md), [Negros Island](NEGROS_ISLAND_BOUNDARY_SOURCES.md), [Ramos barangays](RAMOS_BARANGAY_BOUNDARIES.md), [assistance coverage](ASSISTANCE_COVERAGE_MAP.md), and [welcome page](WELCOME_PAGE.md).

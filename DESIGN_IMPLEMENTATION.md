# Design and UX implementation record

Updated: September 8, 2026. Roadmap: [DESIGN_SYSTEM.md, section 18](DESIGN_SYSTEM.md#18-implementation-milestones).

The main interfaces now use simpler defaults and shared management-system styling. This is a local implementation awaiting staff and staging review. It does not change role permissions or municipality ownership, and it has not been deployed.

## Green/yellow theme update

The current color companion is [GREEN_YELLOW_THEME.md](GREEN_YELLOW_THEME.md). Shared tokens now include warm yellow, pale yellow, dark text on yellow, a dark green overview, and an inverse focus color. Ordinary primary actions remain green; the dashboard overview uses a yellow primary action. Active navigation and the farmer workspace tab use pale yellow, form section numbers use yellow, and the shell, login, and public map use a thin green/yellow strip. Work surfaces remain white. Semantic warning/error colors and saved parcel colors are preserved.

The background refinement uses the almost-white `--ui-bg` value `#F8FAF8` and a shared `--ui-page-background`: green at 3.5% opacity in the upper-left corner and yellow at 4.5% in the upper-right corner, fading near the top. The application shell, dashboard, login, and public map consume this treatment; forms, tables, and dialogs keep solid white work surfaces so the tint stays secondary to the task. Six rendered screen states passed browser checks at five widths from 320 to 1440 pixels, including the background colors, existing action/focus colors, and absence of page overflow or script errors. The desktop dashboard and mobile login were visually reviewed; Blade compilation and the whitespace check passed.

Theme verification passed: 17 existing focused presentation tests with 120 assertions, Blade compilation, and `git diff --check`. Synthetic browser previews checked dashboard variants, shared form, farmer directory, login, and public map at 320, 390, 768, 1024, and 1440 pixels. Rendered button/hover/focus and active-state colors matched the tokens; assistance section-number colors were also checked. Shared validation recovery, mobile focus handling, password visibility, and retained-value checks passed again. Key opaque text pairs meet the project's contrast targets as recorded in the color companion. These checks do not replace the outstanding full accessibility, staff, or staging reviews below. No migration or deployment was performed.

## Milestone evidence

### Municipality geofence scope and Baguio reference — 2026-09-07

Municipal heads and staff open their assigned geofence workspace directly. Municipality search/select, province overview copy, and province reset are omitted for these roles; their reset fits their boundary and parcels, and failed workspace loads offer a retry. Provincial roles retain the finder. The shared sidebar now displays the assigned province instead of appending Tarlac.

The explicit Baguio reference importer was applied to the local loopback MySQL environment: one active city planning/reference boundary, approximately 5,861.586 hectares with 33 vertices, one attributed import event, and no duplicate on a second run. No sample operational records were created. Source provenance and limitations are in `database/seeders/data/README.md`; this import does not certify a legal or survey boundary.

Verification passed: 43 focused tests / 324 assertions in isolated SQLite, PHP formatting and syntax, Blade compilation, the nine existing geofence routes, and whitespace checks. Synthetic browser checks covered four roles at 320, 390, 768, and 1440 pixels, automatic Baguio loading, municipal reset coordinates, and retry after a simulated failure. Google Maps was mocked for those browser checks; live provider imagery and export were not revalidated. No schema migration or production deployment was performed.

### La Trinidad, Atok, and Tublay references — 2026-09-07

The explicit `BenguetMunicipalityBoundarySeeder` was applied to the local loopback MySQL environment. La Trinidad, Atok, and Tublay each have one active planning/reference geofence and one attributed import event. Their computed areas are 7,109.2414, 17,910.3503, and 7,671.2429 hectares respectively. A second run created no duplicates, and the existing Baguio boundary remained unchanged. No sample operational records were added.

The new isolated boundary suites passed 13 tests / 160 assertions; the existing Baguio, geofence, and policy regressions passed another 34 tests / 263 assertions. These cover identity and source integrity, adjacent boundaries, atomic rollback, cache invalidation, audit attribution, idempotence, and municipality isolation. PHP formatting, syntax, and whitespace checks passed. Source attribution and explicit import instructions are in `database/seeders/data/README.md`. No interface, schema migration, or production deployment changed; live provider imagery was not revalidated.

### Geofence visibility and opacity — 2026-09-08

The municipality geofence workspace now uses thicker saved-color outlines with pale casings and readable municipality label badges. Map tools contains a labeled 0–100% color-opacity slider, initially 20%. It adjusts active fills immediately and draft fills at half strength, while keeping outlines opaque. The value remains in effect across municipality changes on the same page. It is a display preference for that page session; boundary data and snapshot exports are unchanged.

Verification passed: 18 geofence/policy tests / 163 assertions, PHP formatting, Blade compilation, and whitespace checks. Browser previews checked the slider with keyboard input and 0%, 50%, and 100% values; unchanged outline and saved-color properties; no network requests on slider changes; retained opacity after workspace reload; municipal reset/retry and scope; and four roles at four widths from 320 to 1440 pixels. Desktop and mobile control layouts were visually reviewed. Google Maps was simulated for browser checks; live satellite rendering was not revalidated. No database changes, migration, or production deployment were made.

### Remaining Tarlac geofences — 2026-09-08

The explicit `TarlacRemainingMunicipalityBoundarySeeder` was applied to local loopback MySQL, adding twelve active planning/reference boundaries and twelve attributed import events. All 18 Tarlac workspaces (17 municipalities and Tarlac City) now have exactly one active boundary. The eleven pre-existing boundary records, including the six Tarlac references and archived Moncada history, remained unchanged. Workspace records and operational table counts were preserved. A second import made no boundary or audit changes, and every target boundary cache was cleared. These boundaries use the existing geofence map visibility and opacity controls.

The Benguet and remaining-Tarlac importers now share `ReferenceMunicipalityBoundaryImporter`, preserving the existing validation, global activation lock, transaction, conservative identity matching, cache invalidation, and audit behavior. Verification passed: 58 focused tests / 1,051 assertions in isolated SQLite, including all 18 Tarlac geometries and Bulacan with no overlapping interiors, identity checks, atomic rollback, preservation, idempotence, and municipality access regressions. Source provenance and explicit import instructions are in `database/seeders/data/README.md`. No schema migration or production deployment was performed; live satellite rendering was not revalidated. The source remains an approximate planning reference requiring LGU/NAMRIA verification before official use.

### Remaining Benguet geofences — 2026-09-08

The explicit `BenguetRemainingMunicipalityBoundarySeeder` was applied to local loopback MySQL for Bakun, Bokod, Buguias, Itogon, Kabayan, Kapangan, Kibungan, Mankayan, Sablan, and Tuba. It created ten missing municipality workspaces, ten active planning/reference boundaries, and ten attributed import events. All thirteen Benguet municipalities now have exactly one active boundary; the separate Baguio City reference remains unchanged. All 23 pre-existing boundary records and existing workspace records were preserved, as were operational table counts. A second run made no workspace, boundary, or audit changes, and target caches were cleared. No users or sample operational records were created.

Verification passed: 13 new tests in isolated SQLite and 31 existing Benguet/geofence/policy regressions. The checks cover identity and source integrity, preservation of existing boundaries and archived history, late-conflict rollback, idempotence, cache invalidation, audit attribution, and municipality access. A separate geometry check validated all 33 reference geometries across Benguet, Baguio, Tarlac, and Bulacan: 528 pairs have no overlapping interiors. PHP formatting, syntax, and whitespace checks passed. Source provenance and the explicit import command are in `database/seeders/data/README.md`. The new boundaries use the existing map visibility and opacity controls. No schema migration or production deployment was performed; live satellite rendering was not revalidated. These are approximate planning references requiring LGU/NAMRIA verification before official use.

### Original interface milestones

| Milestone | Implemented locally | Remaining acceptance work |
| --- | --- | --- |
| M1 — Foundation | Shared `--ui-*` tokens; readable controls and labels; shared focus and error behavior; native disclosures that preserve values; form and directory fixtures | Full staff baseline, manual contrast/screen-reader audit, and browser zoom review |
| M2 — Starting point | Daily-work/office/admin navigation; accessible mobile menu; consistent login; four dashboard metrics, three common actions, five recent releases, separate reports and municipality comparison | Staff recognition of scope, common tasks, and attention links; authenticated staging session checks |
| M3 — Farmers | Five directory columns; explicit history, ID, edit and map actions; optional contact/photo/classification sections; errors reveal affected sections; reports and map startup on demand | Real profile save/photo/ID/import workflows on synthetic staging records |
| M4 — Assistance and Animal Health | Five-to-six-column directories; common filters first; grouped assistance-category select; optional NRP/production/service details; lazy charts and readable figures; preserved legacy values and formatted edit date | Staging lookup/save/retry checks, cross-municipality rejection, mixed-unit totals, and stale-edit rejection |
| M5 — Cooperatives and machinery | Optional form details; simplified listings and reports; membership picker discards canceled selections, restores focus, and displays names as text; retained maintenance warnings | Persist membership and holder changes; confirm transfer restrictions, uniqueness, and maintenance workflows |
| M6 — Maps and geofences | Distinct map entry preserving anchors; deferred Google Maps/all-plots startup; KMZ library on demand; labeled map tools; boundary history disclosure; readable public map styles | Bound server payloads; verify live drawing/editing/imports, geofence activation, weather, satellite exports, and QR privacy/attribution in staging |
| M7 — Supporting tools | Five-column file directory, upload disclosure and technical file details; simplified accounts with optional password changes; six-column audit ledger and advanced filters | Real authorized upload/preview/download and account/audit/export checks in staging |
| M8 — Verification | Isolated presentation tests; synthetic browser checks; documented remaining work and release procedure | Measured staff usability, approved disposable database integration tests, full zoom/screen-reader checks, release decision |

“Ready for review locally” means the interface changes can be reviewed. It does not mean all milestone acceptance criteria have passed.

## Shared implementation rules

- `resources/views/partials/design-tokens.blade.php` defines the common palette and type family. The layout, operations styles, dashboard, and standalone login/public map consume these tokens.
- `resources/views/partials/operations-ui-styles.blade.php` defines the shared component family. Use 16px entry controls, 14px labels/body/actions, and readable metadata; preserve local layouts needed by maps and document previews.
- `resources/views/partials/form-feedback.blade.php` reveals ancestor disclosures for native and server validation errors, associates error text with controls, focuses the error summary, and links to the first affected field. Existing server-rendered errors remain available without JavaScript.
- Keep optional controls inside their form. Collapsing a section must not disable fields or discard values. Do not hide required fields behind an unopened section on initial entry.
- Report pages register `renderOperationalCharts` before including `partials.operational-report-loader`. The loader runs once after opening the disclosure. When chart loading fails, chart bodies are hidden and tabular figures remain visible.
- Do not move authorization into view code. Existing policies, scoped controller queries, CSRF tokens, record versions, and mutation synchronization continue to govern writes.

## Local verification

The five presentation suites contain **39 tests and 206 assertions**. They cover role-specific navigation/actions, required fields remaining visible on create forms, optional sections and errors, retained values and record versions, five-column farmer/file directories, report defaults, and supporting workflow rendering. Tests use unsaved models and in-memory SQLite; operational data is not modified. Laravel Pint passed for the six added test/support files, Blade compilation succeeded, 109 compiled views passed PHP syntax checks, 96 routes loaded successfully, and `git diff --check` passed.

Synthetic browser checks covered 22 rendered screens or states: municipal/super-admin/empty dashboards; farmer directory/create/error; geofences and public map; assistance directory/edit/import; Animal Health directory/edit; cooperative directory/edit/member selection; machinery directory/edit; accounts directory/edit; audit directory; and file directory. Page overflow was checked at 320, 390, 720, 768, 1024, and 1440 CSS pixels. Shared form/login fixtures were also checked.

Interactive checks include:

- native/server errors reveal collapsed sections, preserve input, and focus the error summary/field;
- the mobile menu traps focus, closes with Escape, and returns focus to its opener;
- password visibility toggles preserve the entered value;
- reports and map startup are deferred until opened, including map bookmarks; charts also initialized successfully on dashboard, farmer, assistance, Animal Health, and machinery report fixtures;
- blocked chart and map requests produce usable fallback states;
- municipality comparison search, status filtering, sorting, and no-match state;
- assistance category changes retain populated legacy fields and use pieces for fingerlings;
- cooperative selection cancel/apply behavior, focus handling, and safe text rendering;
- the file upload action opens and focuses the upload section.

Temporary previews use synthetic records and are outside the repository. They are local QA evidence, not staff observations or production screenshots. A 720px reflow check approximates a narrow layout at desktop zoom; it is not a substitute for checking actual 200% browser zoom.

## Limits still requiring work

1. The farmer page still embeds its complete municipality farmer finder and boundary metadata. Opening the map still requests an unpaginated all-plots payload. Deferring those requests reduces work during directory visits but does not bound the map workload. A follow-up should provide scoped paginated farmer lookup and bounded/viewport parcel queries, with isolation tests.
2. Existing controller report queries and aggregates still run during page generation. Lazy charts defer the browser library and rendering, not database work. Measure query time and data volume before choosing server caching or separate report endpoints.
3. Some module-specific styles, document preview tools, and CDN dependencies remain. The shared foundation is adopted by the changed workflows; this is not a claim that every legacy screen meets every design target.
4. Live Google Maps, weather, satellite export, file processing, public QR scanning, and persistence were not end-to-end tested against production data or credentials. Provider failure states and rendered interfaces were checked with synthetic fixtures.
5. The repository's legacy baseline schema is incomplete. The general PHPUnit configuration must not be used against an operational database. Integration checks need the approved sanitized schema in a disposable environment, as described in `AGENTS.md`.
6. No measured before/after staff task study has been completed. Earlier dashboard screenshots provide limited visual reference; a complete task baseline has not been captured.

## Staff and staging acceptance

Use synthetic records with municipal staff/head, provincial agriculture, provincial veterinary, and super-admin accounts. Record each task's completion, time, wrong turns, help requests, and whether the user recognized success. Do not assign target improvements without first measuring the baseline.

| Task | Required result | Review status |
| --- | --- | --- |
| Find and update a farmer, correct an error, open their ID/history | Correct record and scope; preserved values; clear success | Pending |
| Record assistance and an Animal Health service | Correct recipient/owner, quantity/unit, date and municipality | Pending |
| Cancel/apply/save cooperative membership and assign machinery | No canceled selection persists; permitted related records only | Pending |
| Find maintenance attention and open a relevant record | Staff identify the next action without unrelated reporting | Pending |
| Map a parcel, import a polygon and export a sheet | Clear edit mode; geometry safeguards and export attribution preserved | Pending |
| Upload/preview/download a file; update an account; inspect an audit event | Authorized actions only, accurate scope and intelligible feedback | Pending |
| Repeat representative tasks with keyboard, 200% zoom and narrow layouts | Essential controls, errors and success remain usable | Pending |
| Submit a stale edit and a foreign-municipality selection | Server rejects safely without overwriting or leaking data | Pending |

## Release and rollback

The original interface changes require no database migration, new package, or environment variable. The subsequent province-supervision feature below requires its own migration and explicit account setup. Existing map and file-provider configuration is still required for those features.

Before release, review the UI diff separately from the owner's unrelated boundary/seeder work, record the intended revision and previous revision, run the existing production checks in isolated staging, and record staff acceptance. A production release requires the owner's explicit instruction.

Deploy through the existing release procedure and rebuild compiled Blade views with `php artisan view:cache`. If an interface regression appears, restore the recorded compatible application release and rebuild its views. The original interface changes require no data rollback; province-supervision rollback follows `PROVINCE_SUPERVISION.md`. Keep production configuration and private storage intact.

## Province supervision and account UX — 2026-09-08

User Management now distinguishes global System Owner access from a Super Admin's assigned province. Directory filters, role choices, province/municipality selectors, scope labels, and dashboard/audit views follow those permissions. Account forms show a province only for provincial roles and a municipality only for municipal roles. Required fields switch with the role; self-edit forms preserve role, assignment, and status. Inactive prepared Super Admins show the password requirement before activation. Shared spacing, colors, labels, error feedback, and optional password disclosures are retained.

Verification passed 86 isolated tests with 1,175 assertions for access isolation, account mutations, audit/report scope, migration up/down, and existing boundary workflows. Changed PHP files passed Pint and syntax checks; Blade views compiled. Synthetic Owner and Tarlac account create/list/edit pages passed 15 browser checks across 390, 768, and 1440 CSS pixels, with no horizontal page overflow. Role changes updated visibility and native required controls, and foreign province/privileged account choices were absent. Desktop and mobile screenshots were visually reviewed. CDN requests were blocked during these checks, so enhanced third-party selectors were not exercised.

The local additive migration and explicit account setup were applied after a database backup. The existing administrator is now System Owner; Benguet and Tarlac Super Admins are prepared inactive. Existing operational records, geofences, and passwords were verified unchanged. See `PROVINCE_SUPERVISION.md` for account activation, deployment, local evidence, and coordinated rollback. No production deployment or live sign-in using the inactive accounts was performed.

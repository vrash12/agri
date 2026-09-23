# Agriculture Information System — Developer Guide

The September 23, 2026 Bicol planning-reference release deployed 114 geofences through `BicolBoundarySeeder` at runtime commit `aabace9`: Albay 18, Camarines Norte 12, Camarines Sur 36 including Iriga City, Catanduanes 11, Masbate 21, separate Naga City 1, and Sorsogon 15. `region-access:configure --owner=<id> --region=region5` explicitly links the seven scopes and never creates accounts. All references use the pinned geoBoundaries ADM3 revision 9469f09 with independent OCHA COD-AB v03 identity and polygon-area checks. Naga City remains outside Camarines Sur provincial choices. See `docs/BICOL_BOUNDARY_SOURCES.md`; credentials remain outside the repository.

This file applies to the entire repository. It is both a functional map of the system and a set of implementation rules for developers and coding agents. Update it whenever a role, route, model, workflow, integration, or deployment requirement changes.

`SYSTEM_FEATURES.md` is the companion user-facing feature catalog. Keep it synchronized with this guide whenever a feature, permission, integration, or operational limitation changes.

The September 23 MIMAROPA release deployed runtime commit `33a150c` through GitHub and Hostinger git pull, activating all 73 references and regional membership with every existing row/account preserved. The explicit `MimaropaBoundarySeeder` supplies 73 planning references: Marinduque 6, Occidental Mindoro 11, Oriental Mindoro 15 including Calapan, Palawan 23, separate Puerto Princesa City 1, and Romblon 17. It is atomic, idempotent and excluded from automatic seeding. `region-access:configure --owner=<id> --region=mimaropa` explicitly groups these six scopes without issuing accounts or changing the default configuration set. Existing ownership and operational records are preserved. `GeoGeometry` accepts isolated MultiPolygon point contacts while still rejecting shared lines and overlapping interiors; Gloria requires this valid topology. Kalayaan uses full pinned geometry and Cagayancillo uses a documented topology-preserving reduction retaining all 36 parts. See [docs/MIMAROPA_BOUNDARY_SOURCES.md](docs/MIMAROPA_BOUNDARY_SOURCES.md) for provenance, validation, limitations and deployment status.

The September 23, 2026 Hostinger release installed runtime commit `88d3bdc` through GitHub main and `git pull --ff-only`. It simplifies Farmers to Region → Municipality and replaces homepage collage controls with side arrows. The existing CALABARZON Regional Head was retained, the Cavite/Batangas/Laguna Super Admins were activated, and one Provincial Agriculture Staff account was created per selected province. All seven live sign-ins and their municipality choices passed verification. See `docs/GITHUB_DEPLOYMENT.md` and `docs/CALABARZON_BOUNDARY_SOURCES.md`; credentials remain outside the repository.

Production release status: the September 20, 2026 Hostinger release installed commit `54cc9e4`, including the welcome collages, dashboard enhancements, assistance coverage, Ramos barangay references, Region II and Negros Island references. The separately verified Mountain Province import added ten municipality references later that day. See `docs/FULL_DEPLOYMENT_2026_09_20.md` for checks, data preservation and remaining limitations; earlier local-only verification notes describe their original implementation stage.

The authorized September 21 scoped Hostinger release installed compact dashboard reports, the remaining 53 CAR geofences (77 total), and the office sign-in confidentiality/testing notice. All existing rows and account assignments were preserved, with verified private backups. See `docs/CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md`. Pending primary farmer-ID changes were not included in this release.

Read [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) before creating or revising user interfaces. It defines the shared management-system theme, typography, forms, tables, actions, responsive behavior, and accessibility targets. Adopt it within the requested scope; its target styles do not imply that every existing screen has already been migrated.

Use [GREEN_YELLOW_THEME.md](GREEN_YELLOW_THEME.md) for the current color treatment: green primary actions, restrained yellow accents, white work surfaces, and separate status colors. Consume the shared tokens; preserve stored parcel colors and readable focus on both light and dark surfaces.

The current interface implementation and outstanding verification are recorded in [DESIGN_IMPLEMENTATION.md](DESIGN_IMPLEMENTATION.md). Reuse `partials.design-tokens`, `partials.operations-ui-styles`, and the layout's `partials.form-feedback`. Optional native disclosures must retain their controls inside the form and reveal validation errors. Operational report pages register `renderOperationalCharts` on their report disclosure before including `partials.operational-report-loader`; figures must remain available if the library fails.

## Application branding

Farmer registry cards use `FarmerCardLocations` to join distinct non-delisted, same-municipality imported `PARCEL ADDRESS 1–3` values with ` / `. Residence fields and mapped-plot names are never substituted for parcel addresses. Missing addresses are explicitly labeled. The front shows the registry municipality separately; the back and PNG/digital rendering wrap parcel addresses. The full address list is also printed on the sheet; oversized card text explicitly refers to that list, and oversized digital/PNG backs fail with guidance instead of dropping addresses. No new parcel-address geocoding or database mutation is performed. See `docs/FARMER_CARD_PARCEL_ADDRESSES.md` for checks and deployment status.

AgriGOV is the application identity. Use `<x-brand />` for the integrated wordmark and `<x-brand compact />` for the square emblem. Standalone pages include `partials.branding-head`; the shared layout handles authenticated modules. Keep agency seals and registry identifiers distinct from application branding. Assets and deployment requirements are recorded in `docs/AGRIGOV_BRANDING.md`.

## Senior developer mandate

Act as the senior full-stack developer and software architect responsible for helping the project owner build, improve, secure, test, document, and deploy this system. Treat the application as a real government operations platform with multiple simultaneous users and municipality-owned data, not as a prototype or generated demo.

### How to work with the project owner

- Understand the requested operational outcome before changing code.
- Inspect the relevant routes, controllers, models, policies, migrations, views, tests, and existing conventions before implementation.
- Explain important choices, risks, tradeoffs, and deployment requirements in clear language.
- Make reasonable, reversible assumptions when details are minor; ask the owner when a missing decision would materially change permissions, data ownership, privacy, cost, or workflow.
- Challenge contradictory or unsafe requirements with concrete evidence and offer a practical alternative.
- Preserve existing working behavior unless the owner explicitly requests a breaking change.
- Do not silently expand the task into unrelated rewrites.
- Never push, deploy, email, change production data, run destructive database operations, or expose credentials unless the owner explicitly requests that action.
- Never place passwords, API keys, database credentials, private tokens, or production secrets in source control, logs, screenshots, tests, documentation, or responses.

### Clean-code standards

- Follow SOLID, DRY, KISS, separation of concerns, least privilege, and secure-by-default design.
- Prefer clear domain names such as `municipality`, `farmer`, `parcel`, `assistance release`, and `animal-health service` over vague abbreviations.
- Keep controllers focused on HTTP concerns: validate the request, authorize the action, call domain/application services, and return the response.
- Move reusable business rules, geometry processing, import logic, ownership resolution, concurrency handling, and integrations into dedicated support or service classes.
- Use Form Request classes for new forms with complex or reusable validation. Small one-purpose endpoints may keep concise controller validation when that matches the surrounding module.
- Keep Eloquent models focused on relationships, casts, scopes, constants, and model-level behavior. Do not turn models into catch-all service containers.
- Reuse policies and `MunicipalityAccess`; do not repeat role and municipality comparisons throughout controllers and Blade files.
- Prefer small, cohesive methods with one clear responsibility. Extract a method when it removes duplication or gives a business rule a meaningful name.
- Avoid premature abstractions, unnecessary repositories, speculative patterns, and large framework additions that do not solve a current problem.
- Use strict comparisons, explicit defaults, meaningful return types, and PHPDoc for non-obvious array shapes or generic collections.
- Handle expected errors with useful user-facing messages while reporting unexpected failures for developers.
- Do not swallow exceptions unless the operation is deliberately best-effort, such as audit logging; document that behavior in code.
- Keep comments focused on why a decision exists, especially for security, geometry, concurrency, compatibility, or provider limitations. Do not narrate obvious syntax.
- Remove dead code only after confirming it has no route, view, job, test, CLI, or external dependency.

### Architecture boundaries

Use the following responsibility flow for new work:

```text
Blade / JavaScript interface
        ↓
Routes and middleware
        ↓
Controller or Form Request validation
        ↓
Laravel policy authorization
        ↓
Application/domain support service
        ↓
Eloquent models and database transaction
        ↓
Audit, cache, queue, file, map, weather, or export integration
```

- Presentation code must not decide whether a user owns a record; policies and municipality-scoped server queries make that decision.
- Controllers must authorize route-bound records before reading protected relationships, files, or geometry.
- Database writes involving multiple related records must be transaction-backed and safe to retry when appropriate.
- Operations that can be edited by multiple users must use the existing optimistic-version and shared-lock mechanisms.
- External API calls must have timeouts, bounded retries where safe, caching when appropriate, and a useful failure state.
- Large imports, exports, map payloads, and dashboards must be bounded, paginated, chunked, aggregated, cached, or lazy-loaded rather than fully loaded into memory without a limit.
- New modules owned by a municipality must include `municipality_id`, a foreign key and indexes, model relationships, municipality-aware policies, scoped queries, validation of related records, audit coverage, and isolation tests.
- Database constraints should protect important invariants in addition to application validation whenever the supported database can enforce them safely.
- Schema changes must use reversible migrations and must account for the legacy baseline-schema warning in this guide.

### Laravel and PHP practices

- Follow PSR-12 formatting and the existing Laravel conventions.
- Format changed PHP files with Laravel Pint before completion.
- Prefer dependency injection over service-location calls in domain code.
- Use route-model binding with parameter names that exactly match controller arguments.
- Prevent mass-assignment mistakes by maintaining each model's `$fillable` or `$guarded` fields deliberately.
- Add casts for booleans, dates, JSON, decimals, and identifiers where they improve correctness.
- Avoid raw SQL when Eloquent or the query builder is clear and efficient; parameterize every unavoidable raw expression.
- Use eager loading and aggregate queries to prevent N+1 query problems.
- Select only the columns required by large listings and map endpoints.
- Never call `env()` outside configuration files.
- Keep application timestamps in UTC and convert them for display through the existing local-time support.
- Do not change public URLs, route names, legacy table names, or compatibility fields without checking all imports, exports, QR codes, bookmarks, and deployment dependencies.

### Security and privacy review

For every new endpoint or action, verify:

1. authentication and role access;
2. municipality scope and related-record ownership;
3. policy authorization for view, create, update, delete, import, export, preview, and download actions;
4. request validation, file type and size limits, and safe filenames;
5. CSRF protection or API authentication;
6. rate limiting for public or provider-backed endpoints;
7. protection against mass assignment, SQL injection, spreadsheet formula injection, XSS, path traversal, and insecure direct-object references;
8. exclusion of passwords, secrets, private tokens, birth dates, contact data, and protected paths from public responses and audit metadata;
9. safe failure behavior that does not reveal whether another municipality's protected record exists;
10. an audit event for sensitive, administrative, destructive, or export actions when appropriate;
11. response headers, when the endpoint returns anything a browser renders. `App\Http\Middleware\SecurityHeaders` covers the application; a route that returns stored or uploaded bytes sets its own stricter policy and never echoes a caller-supplied content type.

### User-experience standard

- Build interfaces that look and behave like practical agriculture-office tools rather than generic AI-generated dashboards.
- Use the existing visual system, navigation patterns, colors, spacing, cards, filters, tables, empty states, and responsive behavior.
- Place filters near the records or map they affect and clearly state the active municipality scope.
- Use familiar agricultural language and concise instructions for staff who may not be highly technical.
- Provide loading, success, warning, empty, disabled, and failure states for every asynchronous workflow.
- Prevent duplicate submissions and preserve user-entered values after validation errors.
- Ensure keyboard access, readable contrast, descriptive labels, mobile layouts, and useful focus behavior.
- Confirm destructive actions and explain why an action is unavailable instead of only hiding or disabling it.
- Keep dashboards decision-oriented: show what needs attention, why it matters, and the next safe action.

### Performance and simultaneous-user standard

- Apply municipality scope before search, statistics, charts, pagination, exports, or map serialization.
- Prefer database aggregation over loading records and counting them in PHP.
- Prevent N+1 queries with purposeful eager loading, aggregate subqueries, or grouped queries.
- Add indexes for frequently filtered foreign keys, statuses, dates, identifiers, and uniqueness rules.
- Paginate normal listings and chunk large imports or exports.
- Cache expensive, read-heavy results with explicit invalidation or bounded expiration.
- Use atomic cache locks around provider refreshes and other stampede-prone work.
- Use the existing synchronization middleware and `ConcurrentWrite` for shared record changes.
- Keep transactions short; do not perform slow network calls while holding database row locks.
- Use queues for long-running work when the hosting environment supports reliable workers. Provide a safe synchronous or scheduled alternative when shared hosting does not.
- Before horizontal scaling, replace machine-local coordination with Redis or another shared atomic cache and verify shared file storage.

### Required implementation workflow

1. Read this guide and the relevant section of `SYSTEM_FEATURES.md`.
2. Check the current Git branch and working tree; preserve unrelated owner changes.
3. Trace the complete existing workflow from route to interface and database before editing.
4. Write a small implementation plan for changes that cross multiple modules or alter data ownership.
5. Implement the smallest coherent architecture that fully handles the request.
6. Add or update tests for the happy path, permissions, municipality isolation, validation, and important failure paths.
7. Run Laravel Pint on changed PHP files.
8. Run focused automated tests, Blade compilation, PHP syntax checks, route verification, and `git diff --check` as applicable.
9. Review the final diff for accidental files, secrets, debug output, duplicate logic, unbounded queries, and incorrect permissions.
10. Update `AGENTS.md`, `SYSTEM_FEATURES.md`, environment examples, and deployment instructions when behavior or requirements change.
11. Report what changed, what was verified, any migration or configuration required, and what was not tested or deployed.

### Definition of done

A feature is complete only when:

- the requested workflow works from the interface through persistence and reporting;
- role permissions and municipality isolation are enforced on the server;
- validation and failure messages are understandable;
- concurrent writes cannot silently overwrite newer data;
- queries and payloads are reasonable for production-sized data;
- security, privacy, audit, and external-provider behavior have been reviewed;
- focused tests pass and changed views compile;
- documentation and environment examples match the implementation;
- no secrets, temporary files, debug code, or unrelated changes were introduced;
- deployment steps and migrations are clearly identified, but production is not changed without explicit authorization.

## 1. What this system is

This is a Laravel-based Agriculture Information System for the Provincial Agriculture Office and municipal agriculture offices in Tarlac. It centralizes:

- farmer registry and farmer identification cards;
- municipality-owned farmer registry source rows that retain every field from approved Excel imports and remain linked to the resolved farmer without replacing canonical identity fields;
- GIS farm-parcel mapping;
- agriculture and fisheries assistance releases, including seed, fertilizer, fingerlings, feed, and fishing gear;
- animal-health services covering vaccination, deworming, vitamins, and treatment;
- farmers' cooperatives and membership;
- agricultural machinery inventory and maintenance monitoring;
- municipality-owned backup files;
- user and role management;
- province-wide dashboards and audit trails.

The application is multi-municipality. Operational records belong to one `municipality_id`, and municipal users must never see or mutate another municipality's records. The System Owner oversees all configured provinces. Super Administrators, provincial agriculture staff, and veterinary accounts require an explicit province_id and are limited to that province; veterinary accounts remain Animal Health-only. Municipal accounts inherit their province through their assigned municipality. Super Administrators and the System Owner have read-only operational oversight and manage accounts/security within their scope.

## 2. Technology and important dependencies

- PHP `^8.1` (raised from `^8.0.2` on 2026-09-19: the patched PhpSpreadsheet requires
  8.1. `composer.json` pins `config.platform.php` to `8.1.0` so the lock resolves for
  the oldest supported server rather than whatever the developer machine runs.
  `nette/schema` caps the set at 8.3, so the supported range is **PHP 8.1 – 8.3**;
  confirm the server before deploying with `php -v`.)
- Laravel `^9.19`
- MySQL/MariaDB
- Blade views with server-rendered forms
- Laravel session authentication and Sanctum's default `/api/user` endpoint
- PhpSpreadsheet for Excel import/export
- Endroid QR Code for farmer ID QR codes
- Google Maps JavaScript API and a Google Map ID for the authenticated plotting workspace
- Google Maps Static API through an authorized same-origin server proxy for satellite PNG exports
- Nominatim/OpenStreetMap for server-side geocoding through `/api/geocode`
- Google Maps JavaScript API for the public QR-linked parcel map
- Open-Meteo for cached municipality-level weather forecasts and rule-based agricultural guidance
- PAGASA website links for official weather, tropical cyclone, flood, and agri-weather bulletins
- Chart.js for dashboards and module charts
- DataTables, Tom Select, Handsontable, SheetJS, CodeMirror, JSZip, and docx-preview from CDNs in various views

The application currently uses Vite only for the standard `resources/css/app.css` and `resources/js/app.js` entry points. A significant amount of module CSS and JavaScript is embedded in Blade views.

## 3. Roles and effective permissions

The supported office roles are constants in `App\Models\User`. Farmer portal identities use a separate model, table, and session guard described below; never add them to the office-role lists:

| Role | Operational visibility | Operational writes | User management | Backup Folder | Audit Trail |
| --- | --- | --- | --- | --- | --- |
| `system_owner` | All configured provinces | No routine operational writes; may manage geofences | Create/manage Regional Heads, province Super Admins and lower roles; own privileges and all owner accounts protected | No access | Global access and CSV export |
| `gis_evaluator` | Active administrative geofences only, across configured provinces | None | No access | No access | No access |
| `regional_head` | Assigned region's active provinces and separate city scopes | Read-only, including geofences | Provincial Super Admins and lower roles within the region; own profile only | No access | Assigned provinces' snapshots and CSV export; global/unknown events excluded |
| `super_admin` | Assigned province only | No routine operational writes; may manage geofences in own province | Provincial and municipal staff in own province; own profile only; no peer/owner management | No access | Own province snapshots and CSV export |
| `provincial_staff` | Assigned province only | Yes; must choose an authorized municipality for new records | No | Own province, subject to policy | No |
| `provincial_vet` | Assigned province, Animal Health only | Yes, Animal Health only; must choose the municipality for new records | No | No access | No |
| `municipal_head` | Assigned municipality only | Yes | May manage only `municipal_staff` in the same municipality | Assigned municipality only | No |
| `municipal_staff` | Assigned municipality only | Yes | No | Assigned municipality only | No |

All accounts must be active. Regional Heads require an active region with no province/municipality assignment; provincial roles require an existing active province; municipal roles require an existing active municipality and supervising province. Only the System Owner assigns Regional Heads. `EnsureAccountScope` checks authenticated application requests, and the Sanctum user endpoint uses the same check. Login independently validates scope. UI visibility is not security: controllers must still call policies for every protected action.

External GIS Evaluators require no operational office assignment. `RestrictGisEvaluatorAccess` confines them to the read-only municipality-geofence page and its boundary and barangay JSON endpoints. The geofence controller returns active boundaries only and deliberately skips every farmer, parcel, snapshot, export, draft, account, audit and write query for this role. Evaluator accounts are provisioned directly by the System Owner outside the general account-management form and should be disabled when an evaluation ends.

### Authentication workflow

GIS Evaluators additionally require a future `evaluation_expires_at`, null geographic assignments, and a first-use password change when `evaluation_password_pending` is true. The evaluator password routes require the current password and a confirmed replacement of at least 15 characters (maximum 72 bytes), lock the account during changes, and never flash credentials. Apply `2026_09_22_000100_add_evaluator_access_limits.php` explicitly before provisioning. Expiry is enforced at sign-in and on each scoped request. Evaluator boundary visits and password changes are audited without credentials.

The office sign-in form includes a visible confidentiality and testing notice below its submit button. It limits the stated purpose to authorized testing/validation and asks participants not to disclose confidential materials or use them to reproduce AgriGOV without prior written permission. The new required checkbox says “I have read and understood the confidentiality and testing notice.” It starts unchecked, remains inside the credential form, uses native required validation and Laravel's `accepted` validation before credentials are attempted, and preserves its checked state after an invalid-password response without flashing the password. Missing acknowledgment produces a clear field error and does not count as a failed credential attempt; the existing request throttle still applies. No signature or separate acceptance record is stored, and account permissions and farmer-portal sign-in are unchanged. The checkbox update was deployed through GitHub on September 21 in commit 610013d; see docs/GITHUB_DEPLOYMENT.md. Deploy `AuthController`, the login Blade view and `public/css/login-layout.css` together, mirror CSS to both Hostinger public directories, and refresh compiled views. See `docs/SIGN_IN_CONFIDENTIALITY_NOTICE.md`.

The standalone `/login` page keeps the credential form on the left and an eight-photo Philippine agriculture slideshow on the right, stacking the form first on smaller screens. `public/css/login-layout.css` and `public/js/login-slideshow.js` provide the layout and progressive enhancement. Photos loop every seven seconds without visible controls; playback suspends for hidden tabs and reduced-motion preference. The first photo remains visible without JavaScript. Keep the footer Image sources link to `public/photo-credits.html` for required attribution; provenance is recorded in `docs/LOGIN_SLIDESHOW.md`. Authentication, CSRF, error recovery, and password-manager fields must remain independent of the slideshow.

1. A guest submits email, password, the required confidentiality-notice acknowledgment, and optional “remember me” to `AuthController@login`.
2. Laravel attempts session authentication and regenerates the session ID after success.
3. The controller rejects unknown roles, inactive users, municipal users without a municipality, and users assigned to a missing/inactive municipality.
4. Successful and failed/blocked sign-ins are written to the audit trail when the `audit_logs` table is available.
5. Every unsuccessful sign-in, whether the password was wrong or the account was blocked, counts toward a lockout of five attempts per email address and client address. A locked address is refused for five minutes before the password is checked, and the lockout is audited once as `login_throttled` so a flood cannot fill the audit trail. A completed sign-in clears the counter. The route additionally caps one client address at 30 sign-in requests per minute, which is generous enough for an office sharing a single public address.
6. Unsuccessful sign-in entries are capped at 20 per client address per 15 minutes. Past that ceiling one `login_failures_suppressed` entry records that the rest of the window was suppressed, so an address working through many email addresses cannot bury genuine entries. Successful sign-ins, logouts, and session timeouts are never suppressed.
7. `last_login_at` is updated. Agriculture roles are redirected to the municipality-aware dashboard, while `provincial_vet` goes directly to Animal Health.
8. Logout is audited, the session is invalidated, and the CSRF token is regenerated.
   `SecurityHeaders` marks authenticated and sign-in/session responses private and non-storable. The authenticated layout loads `public/js/session-history.js` to hide restored browser-history snapshots and reload them through server authentication. Deploy that asset to both Hostinger public directories before clearing compiled views.
9. Authenticated sessions have a 15-minute idle limit. Browser activity is shared across tabs and sends a throttled heartbeat only while the user is active.
10. The interface warns during the final minute, then automatically signs the account out. The server independently rejects stale requests, invalidates the session, and records a `session_timeout` audit event.

Office accounts have no public registration, forgotten-password, email-verification, or self-service password-reset workflow. Farmer portal accounts have staff-assisted activation and recovery; they cannot self-register or recover using a birthday.

### Farmer portal

`FarmerPortalAccount` / `farmer_portal_accounts` uses the separate `farmer` session guard; the default `web` guard and office `User` roles remain unchanged. Each account links to one existing farmer and snapshots its municipality. A transfer fails closed until newly authorized staff verify identity and reissue access. Successful sign-in to either audience clears the other audience's session identity.

Staff authorized by the Farmer update policy manage access from **Farmer profile/history → Farmer portal access**. `GET /farmers/{farmer}/portal-account`, POST `/activation`, and POST `/disable` stay within office authentication, scope, veterinary restriction, and synchronized-write middleware. `FarmerPortalAccounts` locks the parent/account with `ConcurrentWrite`, rejects stale versions, derives ownership from the locked farmer, and audits issuance/recovery/disable without credentials. Oversight and veterinary accounts cannot issue access. Staff must perform an actual office identity check, then confirm it before issuing a code.

The canonical login ID is `AGRI-F-` plus the padded farmer ID. Sign-in optionally accepts the exact recorded RSBSA number when it matches one farmer across the registry. Never select the first duplicate or merge identities. This is AgriGOV authentication, not central RSBSA integration. Activation uses the canonical ID and a random 32-character code valid for 24 hours. Store only its SHA-256 hash; render plaintext once in the private staff response, never in a URL, session flash, log, or audit metadata. Codes are consumed under a row lock. Recovery clears the previous password and code and increments `session_version`; disabling also revokes access.

Public forms at `/farmer-portal/login` and `/farmer-portal/activate` retain CSRF and route throttles. Passwords require 15 characters, confirmation, at most 72 bytes, bcrypt hashing, and the existing bounded breached-password verifier. Five authentication failures lock the identifier/IP and account for five minutes; failed-event audit volume is capped. `EnsureFarmerPortalSession` refreshes state, checks active account/ownership/municipality/province, password/activation, session version and idle timeout, authorizes the self-account policy, and never switches the office guard to farmer. Private responses are non-storable and load the history-restoration guard. Portal audit URLs omit query strings. No remember-me, birthday authentication, SMS, or public recovery lookup exists.

`FarmerPortalRecords` supplies read-only My Profile, My Farm and My Assistance. Ownership comes from the authenticated account, not input. Releases must match both farmer and municipality foreign keys, never an identity snapshot. Parcels paginate at 10 and releases at 15. Seasonal crops match the selected year, owned parcel and municipality. Private map HTML has no geometry; `/farmer-portal/parcels/{plot}/geometry` returns one owned parcel capped at 10,000 points and 1 MB stored geometry. Google Maps loads only on request and has loading/retry states. No other farmers, office directory, operational editing, or applications are available.

Deploy the additive `2026_09_20_000100_create_farmer_portal_accounts_table.php` migration against a verified baseline, new portal classes/config/policy/views/routes, and `public/css/farmer-portal.css`, `public/js/farmer-portal.js`, `public/js/farmer-portal-map.js` plus existing `session-history.js`. Mirror assets to both Hostinger public directories before refreshing caches. See `docs/FARMER_PORTAL.md`. Installation does not issue real farmer accounts; production deployment requires owner authorization.

## 4. Municipality isolation — non-negotiable rules

The Farmers directory now uses `FarmerWorkspace` for a staged Region → Municipality chooser with province group labels. The System Owner begins at region; Regional Heads retain their assigned region; provincial accounts retain their province; municipal accounts open directly. Each stage narrows `MunicipalityAccess` choices and validates parent/child consistency. Until a municipality is chosen, `/farmers` returns the chooser without operational aggregates, farmer rows, or geometry. Existing municipality bookmarks still resolve their parents. Unassigned regions remain an explicit group without geographic reassignment. See `docs/FARMER_WORKSPACE_AND_COVERAGE.md` for deployment and tests.

Dashboard `DashboardCoverage` aggregates annual assistance reach against the current registry using valid same-municipality farmer links, reports unlinked and undated releases separately, and counts active municipalities with exactly one, no, or multiple active geofences. Empty denominators and an uninstalled boundary module remain explicit unavailable states. Scope is applied before aggregation; query counts do not grow per municipality. Figures and definitions appear under the existing reporting-year control. These statistics do not establish program eligibility or unmet need.

Operational ownership remains the numeric foreign key `municipality_id`, not the human-readable `farm_municipality` field. Province supervision uses `municipalities.province_id -> provinces.id` and `users.province_id` for provincial roles. The legacy municipality `province` string remains a display/compatibility field and must never decide access.

- Only `system_owner` has global visibility. Null, inactive, missing, or unsupported scope fails closed.
- `regional_head` reads are scoped through `provinces.region_id` and `users.region_id`, including explicitly configured independent-city scopes. `MunicipalityAccess::scopeProvinces()` and `scopeMunicipalities()` enforce this before lists, maps, aggregates or exports. Regional Heads manage only provincial/lower accounts in their region, cannot edit operational records or geofences, and cannot manage owner/peer identities or their own privileges. See `docs/REGIONAL_SUPERVISION.md` for the additive migration and explicit membership configuration; no municipality ownership is changed.
- `MunicipalityAccess::scopeMunicipalities()` scopes municipality queries; `scope()`, `choices()`, and `resolveForWrite()` enforce province or municipality ownership for operational work.
- `User::canAccessAllMunicipalities()` is a compatibility UI helper for choosing multiple municipalities within authorized scope; it is never permission to return an unfiltered query.
- System Owner account setup and province migration are explicit operations documented in `PROVINCE_SUPERVISION.md`; never guess assignments or promote an arbitrary account automatically.

- Use `App\Support\MunicipalityAccess` for operational queries, filters, allowed municipality choices, and write ownership.
- Municipal list queries must apply municipality scope before search, statistics, charts, pagination, lookups, or exports.
- Municipal writes derive `municipality_id` from the authenticated account. Do not trust a submitted municipality ID.
- Provincial staff must explicitly choose an active municipality for creates/imports when no owning record determines it.
- Related farmers, cooperatives, machinery holders, distributions, and plots must belong to the same municipality.
- Route-model-bound records must be authorized before view, edit, update, delete, download, preview, stream, save, assign, or export.
- Farm plots inherit municipality ownership through `farm_plots.farmer_id -> farmers.municipality_id`.
- Super admins may view operational records across the province but `User::canManageOperationalData()` deliberately prevents operational creates, imports, updates, and deletes.
- Provincial Veterinary Office accounts have province-wide municipality scope only for `AntiRabiesVaccination`. `RestrictProvincialVeterinaryAccess` blocks every other authenticated application module, while the shared policy concern denies the role for all other municipality-owned models.
- Super admins must not regain Backup Folder access. `BackupFilePolicy` explicitly blocks it even though super admins have province-wide scope elsewhere.
- Never rely on hidden buttons or navigation checks to enforce any of these rules.

Reusable authorization is implemented by:

- `App\Policies\Concerns\AuthorizesMunicipalityRecords`
- model-specific policies in `app/Policies`
- model-to-policy registration in `App\Providers\AuthServiceProvider`
- `App\Support\MunicipalityAccess`

When adding a new municipality-owned module, reuse these components instead of copying role comparisons into controllers.

## 5. Functional module catalog

### Public welcome page

Route: `GET /` (`welcome`). Guests receive a public farmer-services guide in `resources/views/welcome.blade.php`; signed-in users retain the dashboard or Animal Health redirect. `/login` remains the staff sign-in entry. The page provides native service disclosures, an office-visit checklist, and links to official DA, RSBSA Finder, PhilRice, ATI, BFAR, and PAGASA resources. It does not query operational records or publish counts, accept farmer registrations/applications, or create bookings. Keep availability and eligibility inquiries with the responsible office.

The page consumes `partials.design-tokens` and its scoped `public/css/welcome.css` / `public/js/welcome.js`. `partials.welcome-slideshow` and `public/js/welcome-slideshow.js` add 20 Philippine agriculture photographs in five distinct four-photo collages. The gallery uses manual Previous/Next arrow buttons that wrap between collages; the controls have labelled 44px targets, keyboard arrow support, and a live status announcement. Only the first collage loads before interaction; each other collage hydrates when visited. Navigation, the first collage, and service guidance remain usable without JavaScript. The About AgriGOV section explains six office capabilities without querying operational records. The unmodified existing DA seal identifies the linked DA resources separately from application branding. Preserve the footer photo credits and link to `public/photo-credits.html`; service thumbnails remain lazy-loaded. See `docs/WELCOME_PAGE.md`, `docs/WELCOME_PHOTO_SOURCES.md`, and `docs/WELCOME_COLLAGE_PHOTOS.md` for design, asset provenance, and verification. No migration or new configuration is required. Deploy the slideshow script before rebuilding views; mirror public assets to both Hostinger public directories.

The homepage redesign uses a neobrutalist photo collage with page-scoped
square borders and offset shadows, retaining the shared green/yellow colors,
Roboto, service disclosures, DA identity, farmer/office entries and photo credits.
Only photo frames are rotated; controls stay level. The hero rotates five different arrangements of four photographs, with varied
landscape and portrait frames. Services precede
the system overview. This welcome-page style is an explicit owner-requested
exception to the ordinary management-panel geometry; do not apply it globally.
The redesign was deployed September 20, 2026; see `docs/FULL_DEPLOYMENT_2026_09_20.md`.

Welcome-page microinteractions are progressive enhancements: short button presses,
gallery arrow feedback, link and disclosure feedback, active-section navigation, and
one-time heading settling on `[data-welcome-reveal]`. Fragment links move focus to
their destination without replacing native scrolling/history. Content stays visible
without the observer or animation APIs. Respect reduced motion for decorative
transitions; do not add continuous decorative movement or automatic gallery playback.

Inside AgriGOV presents six office capabilities in a numbered, expandable toolkit
beside its introduction and office sign-in action. Keep these as native
`details`/`summary` elements with visible benefit text, optional explanatory
content and decorative plus/minus indicators. The light toolkit uses green focus
outlines inside the dark section; retain keyboard access and reduced-motion rules.

### 5.1 Dashboard

Route: `GET /dashboard` (`dashboard`)

The dashboard builds role-scoped operational KPIs and recent activity:

- farmers, agriculture/fisheries distributions, kilograms and fingerlings released, animal-health services and animals served, cooperatives, and machinery;
- total plots, mapped area, mapped/unmapped farmers, and mapping coverage;
- farmers missing FFRS or farm location;
- machinery availability and maintenance attention;
- current-month distributions and animal-health services;
- monthly kilogram release and top seed-variety charts;
- recent recipients, animal-health services, and parcel activity;
- backup totals/latest upload only for roles allowed to use Backup Folder.

The default dashboard shows four key figures, up to three role-aware actions, an attention panel, and five recent assistance releases. Additional program totals, current-month details, charts, recent services, and parcel activity are in a closed Reports disclosure. Chart.js loads when Reports opens; monthly figures remain readable without it. Super Admin municipality comparison has its own disclosure with search, status filtering, sorting, and municipality-scoped directory links.

Dashboard Reports includes equipment-type stacked bars for operating condition and availability, monthly animal-health service counts by service type, and fingerling quantities by active municipality. The validated `report_year` filter selects the year for monthly services, fingerling quantities, and production in the municipality comparison; choices come from scoped recorded years plus the current/selected year. Other totals remain all-time and machinery reflects the current inventory. Production comparison uses `HarvestRecord` quantities per commodity and recognized unit, never assistance amounts. The production trend and comparison display one commodity/unit indicator at a time. Missing production is `null` (shown as Not recorded); a recorded zero remains zero. Fingerlings count only `fish_fingerlings` releases measured in pieces; incomplete quantities and undated records are disclosed separately. All figures remain available without Chart.js, and the header links to the graphs. See `docs/DASHBOARD_ENHANCEMENTS_2026_09_19.md` for verification and deployment scope.

For super admins it also produces a province comparison without per-municipality N+1 queries. Each active municipality includes farmer, mapping, distribution, vaccination, cooperative, machinery, and staffing metrics. Municipalities are classified as operational, missing a head, without farmer records, or behind on mapping. Only the System Owner receives the count of operational records with no municipality; a province admin cannot infer unassigned/global data.

Municipality charts in Reports show eight municipalities per page, with local name search and Previous/Next controls when more than eight are present. `public/js/dashboard-chart-pages.js` slices labels and all series by original row index, retaining duplicate names, zero/null values, scope and units. The comparison keeps search/page when its indicator changes. All metric figures, including comparison, are in closed native disclosures with a named keyboard-scrollable table capped at 360px; figures remain complete without scripts. The compact-layout update was deployed to Hostinger September 21 at 00:38 UTC. Keep the JavaScript asset mirrored to both Hostinger public directories with the dashboard views/partials before refreshing views; no migration is required. See `docs/DASHBOARD_COMPACT_REPORTS_2026_09_21.md` and `docs/CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md`.

Weather and agricultural advisories are intentionally embedded in the Parcel Map instead of being shown as a separate page or global-sidebar module. The map's Weather button lazy-loads a municipality-scoped drawer with current conditions, rainfall/wind indicators, advisories, a three-day outlook, refresh controls, and PAGASA links. Selecting a farmer switches the drawer to that farmer's municipality; municipal accounts remain locked to their assignment, while provincial staff and super admins may choose active municipalities in their assigned province; the System Owner can choose across provinces.

### 5.2 Farmer registry

Primary model/table: `Farmer` / `farmers`

Routes: `farmers.*` plus the public `farmers.public-land` route.

Functions:

- list, search, filter, paginate, create, edit, and delete farmer profiles;
- filter by municipality for provincial users, gender, mapped/unmapped state, missing FFRS, and missing location;
- use five directory columns, explicit record/history and map actions, optional profile disclosures, and registry insights loaded on demand;
- use the prominent Municipality Workspace selector as the shared scope for registry totals, the complete map farmer finder, parcel boundaries, and weather; registry-only search and quality filters do not remove other municipality farmers from the map;
- aggregate input-release history and parcel statistics into the directory;
- display gender and top-location charts from the filtered record set;
- store FFRS/RSBSA numbers, identity and contact details, declared area, ecosystem, farm location, and ARB/4Ps/IP/PWD/SC/OFW flags;
- upload, replace, and remove JPG/PNG/WebP profile photos up to 3 MB;
- keep photos on the private `local` disk and stream them only after authorization;
- show each farmer's distribution history, weighted totals, date range, top item/variety, machinery count, and charts;
- generate the display-only registry identifier `PAIS-FRM-######` from the database ID;
- generate a printable/downloadable two-sided local farmer registry card;
- automatically present the same card in a responsive digital-ID dialog with front/back switching, current-side download, and an enlarged QR scanning view;
- place a QR code on the ID that points to the farmer's public interactive land page;
- import the `PARCEL LISTING` and `OUTSIDE LGU` sheets from an `.xlsx` or `.xls` workbook;
- aggregate repeated parcel rows into one farmer and update/create farmers within the selected municipality.

Farmer deletion is blocked when distributions or farm plots exist. Cooperative memberships are detached, and the private photo is removed when deletion succeeds. Machinery foreign keys are configured to become null at the database level when the machinery migration is active.

### 5.3 Farm plotting and maps

Primary model/table: `FarmPlot` / `farm_plots`

Authenticated functions:

The directory's Parcel Map disclosure preserves `#farmersMapModule` bookmarks and row actions. It defers Google Maps startup and the all-plots request until opened; KMZ tools load when a KMZ file is selected. The farmer finder searches the scoped server endpoint. The all-plots endpoint remains unpaginated but caps responses through `map.max_plots_per_request` and reports truncation; the 3D display optimization does not reduce that network payload.

The 3D parcel renderer uses one interactive polygon per parcel, reuses cached overlays, and yields between drawing batches. `public/js/parcel-display-geometry.js` supplies display-only one-metre overview paths with a two-percent area-change guard and topology checks. Original rings remain in the plot cache for edits, collision checks, fitting, measurements, and exports; selected farmers and nearby parcels at close range use full detail. Never persist simplified display paths. Camera-driven refreshes are debounced and cancellable so stale batches cannot restore hidden or deleted plots. See `docs/PARCEL_MAP_PERFORMANCE.md` for verification and limits.

The **Crops by season** disclosure adds a year/dry-or-wet-season crop layer to the same workspace. Staff record one classification per parcel/year/season through `GET/POST /farm-plots/{plot}/seasonal-crops`; System Owner and Super Admin accounts can inspect but cannot save. `ParcelCropSeason` / `parcel_crop_seasons` stores explicit crop records, optional source notes, and municipality ownership derived from the parcel's farmer. `StoreParcelCropSeasonRequest`, `ParcelCropSeasonPolicy`, and `ParcelCropSeasons` enforce authorization, parent ownership, a parent lock through `ConcurrentWrite`, optimistic versions, and audit logging. The unique plot/year/season constraint protects simultaneous first entries. Missing crop records appear as Not recorded, never as fallow or an inferred crop from assistance/harvest data.

`GET /farm-plots/crop-layer` returns classifications only for authorized requested parcels, capped at 200 IDs per request. `public/js/parcel-crop-layer.js` batches requests, ignores stale responses, supports crop filtering and a labeled count legend, and exposes loading/error/retry states. `farmers-maps.js` reuses existing overlays; layer colors never change saved parcel colors, geometry, or exports. Counts describe loaded parcels, not planted hectares or all municipal land. Deploy the additive migration by its explicit path after a backup and install both JavaScript assets in both Hostinger public directories. See `docs/SEASONAL_PARCEL_CROPS.md` for usage, verification, and deployment order.

- retrieve all visible parcels or one accessible farmer's parcels as JSON;
- draw and save polygon boundaries for the selected farmer;
- rename, recolor, reshape, and delete saved polygons;
- calculate centroid and approximate spherical area in hectares on the server;
- show saved parcels, mapping totals, fit/reset controls, and farmer details in the Google Maps workspace without placing centroid pins over parcel boundaries;
- identify a parcel's farmer, FFRS, location, name, and area in a compact hover card; clicking a polygon isolates that farmer's parcels until the user chooses the in-map All Parcels reset;
- for provincial and super-admin users, pass the selected municipality to the all-plots endpoint so the map never mixes parcel boundaries from other municipalities;
- import KML/KMZ in the browser for a selected farmer and save each parsed polygon through the authorized plot endpoint;
- bulk-import server-side KML/XML placemarks for one selected municipality;
- match server-side imports only against farmers in that municipality using parcel codes and unambiguous name/location strategies; shared-surname matches fail closed for review instead of selecting the first database row;
- preserve/import KML style colors when possible;
- export/print a parcel information sheet and downloadable PNG from the browser.

Satellite plot images are fetched by the authorized `farm-plots.static-map`
endpoint and returned from the application origin. Do not revert exports to a
direct `maps.googleapis.com` canvas image: the cross-origin response taints the
canvas and forces the coordinate-grid fallback.

The server-side bulk-import form currently validates KML/XML only. Browser-side selected-farmer import supports KML and KMZ through JSZip.

Plot actions authorize either the owning `Farmer` or the `FarmPlot`. Municipal users cannot retrieve all provincial plots, open a foreign farmer, add a plot to a foreign farmer, or mutate a plot owned by another municipality.

### 5.4 Public QR land verification

Route: `GET /land/{40-character-token}` (`farmers.public-land`), throttled to 60 requests per minute.

Each farmer receives a random `public_map_token` on creation; the migration backfills existing farmers. The token is hidden from normal model serialization and is embedded only in the generated QR URL.

The public page uses Google Maps JavaScript API with hybrid satellite imagery and provides a read-only interactive map with pan, zoom, map-type switching, parcel selection, parcel area, declared area, registry ID, farmer name, and location. It deliberately excludes contact details, birth date, account data, distribution records, and other internal data. Responses disable indexing, caching, and MIME sniffing. The referrer policy is `strict-origin-when-cross-origin` so Google receives only the application origin needed to validate the website-restricted browser key; the random public token and path are not sent cross-origin.

Changing or exposing this page requires a privacy review. Do not replace the token with a sequential farmer ID.

### 5.5 Agriculture and fisheries assistance

Primary model/table: `RiceSeedDistribution` / `rice_seed_distributions`

The historical table name remains `rice_seed_distributions`, but the module now represents both agricultural and fisheries assistance. The assistance sector is derived from `input_category`; do not create a second uncoordinated distribution table merely to separate fisheries records.

Functions:

- create, edit, delete, search, filter, paginate, import, and CSV-export releases;
- link each release to a farmer and copy a farmer identity/location snapshot into the distribution record;
- support rice seed, corn seed, vegetable seed, fertilizer/abono, soil amendment, and other farm-input categories;
- support fish fingerlings, fish feed, fishing gear, aquaculture inputs, and other fisheries-assistance categories;
- support quantities in kg, sacks, packs, grams, liters, milliliters, bottles, pieces, sets, rolls, boxes, or bundles;
- suggest tilapia, hito/catfish, bangus, and carp fingerlings plus common feeds and fishing equipment while allowing a custom item/species/variety name;
- require fingerling releases to use the `piece` unit so provincial and municipal fingerling counts remain valid;
- retain NRP fields such as claimed area/seed, lot series, crop establishment, sowing label, harvested area, production bags, planted variety, and seed class;
- filter by text, identity fields, municipality, assistance sector, category, gender, eligibility flags, numeric ranges, and date ranges;
- display filtered KPIs and charts for monthly releases, item/category mix, fisheries records, fingerlings issued, locations, gender, age, eligibility, establishment method, yield variety, seed class, and area by municipality;
- import an `NRP DISTRIBUTION` Excel sheet, match farmers by FFRS/RSBSA inside the selected municipality, and update/create release rows;
- stream filtered CSV exports in chunks.

Only rows whose `quantity_unit` is empty or `kg` are included in kilogram totals. Do not add fingerlings, pieces, sacks, bottles, or liters directly to kilogram aggregates. Fisheries KPIs count `fish_fingerlings` only when the unit is `piece`.

`StoreRiceSeedDistributionRequest::SEED_CLASSES` offers Certified, Registered and Not Specified. Imported releases also hold establishment methods and seed classes the form has never offered, such as `Direct seeded`; the request keeps the value already stored on the record being edited acceptable, and the controller adds it to the select, so a legacy record stays editable instead of failing validation on a field nobody touched. Do not remove a stored value from the accepted list without checking the real data first.

#### 5.5.1 Rice Seed Distribution Sheet

Season values are stored as the short codes in `RiceDistributionBatch::SEASONS` (`dry`, `wet`) and displayed through their labels; `SEASON_ABBREVIATIONS` turns them into the printed band, so a heading reads "2025 DS" from stored data and is never hardcoded. A planting season is required; a harvest season is deliberately separate and stays empty until a harvest is reported, and is never inferred from the planting season.

Three columns must not be confused with older ones that look similar. `registered_rice_area_ha` is the area registered for rice and is **not** `farm_area_ha`, which is the farmer's total farm area. `seed_bag_kg` is the weight of a seed bag and is **not** `avg_weight_per_bag_kg`, which the Production monitoring section records as the harvest bag weight. Total released kilograms are derived from `seed_bags * seed_bag_kg` into the existing `kgs_received`; there is no second total, and a batch never re-counts a release.

`consent_status` defaults to `unrecorded` so an unknown answer stays unknown rather than becoming a No. `batch_id` is nullable and every existing release remains fully usable without a sheet.

Primary model/table: `RiceDistributionBatch` / `rice_distribution_batches`

Routes: `rice-distribution-batches.*`, including `rice-distribution-batches.sheet` and `rice-distribution-batches.export`.

A sheet groups existing assistance releases for one programme reference and planting season so a municipal office can print, sign and file them. It stores no released quantity of its own; every printed total is aggregated from the grouped release rows, so the sheet and the module's kilogram figures cannot disagree.

Functions:

- create, edit, delete, search, and paginate municipality-owned sheets carrying a reference, planting season/year, optional harvest season/year, an optional default seed-bag weight, and notes;
- attach a release to a sheet through the nullable `rice_seed_distributions.batch_id`; releases recorded before sheets existed keep a null batch and stay fully editable;
- record the sheet fields on each release: `registered_rice_area_ha` (declared rice area, separate from the total `farm_area_ha`), `seed_bags`, `seed_bag_kg` (seed bag weight, separate from the harvest `avg_weight_per_bag_kg`), explicit `harvest_season`/`harvest_year`, `consent_status`, `kp_kits_received`, and `representative_name`;
- derive the released total from `seed_bags * seed_bag_kg` in `App\Support\SeedReleaseQuantity` and store it in the existing `kgs_received` column — never add a second total column;
- render the sheet on screen and as a wide grouped `.xlsx` workbook with merged season headings, a header block repeated on every printed page, a printed totals row, and a blank signature column.

`consent_status` is exactly `unrecorded`, `yes` or `no` and defaults to `unrecorded`. A consent that was never asked must stay unrecorded; do not read it as a refusal or as agreement, and do not backfill it.

Season headings are always built from the stored season and year through `RiceDistributionBatch::seasonHeading()`. Never hardcode a season such as "2025 DS", and never infer the harvest season from the planting season or from today's date.

`App\Support\RiceSeedDistributionSheet` owns the sheet's meaning — titles, column groups, row values, the filtered release query and the aggregated totals — and is shared by the screen and the export, so the two cannot disagree. `App\Support\RiceSeedDistributionSheetWorkbook` only draws the worksheet, and writes every title, heading, label and value through `App\Support\CsvExport::value()` as an explicit string so a stored value cannot execute as a spreadsheet formula. That stores figures as text, which suits a sheet that is printed and signed. Workbook exports are capped at `RiceSeedDistributionSheetWorkbook::MAX_ROWS` (5000) because PhpSpreadsheet holds the whole workbook in memory; a larger scope is directed to the streaming CSV export.

Deleting a sheet is refused while it still groups releases, re-checked inside the row lock. Sheet exports are audited with `exported`, and create/update/delete flow through `AuditModelObserver` under the `Assistance distributions` module.

### Assistance coverage map

`GET /assistance-coverage` (`assistance-coverage.index`) is a read-only planning
report linked from Agriculture & Fisheries. It authorizes `viewAny` on
`RiceSeedDistribution` before validating filters through `AssistanceCoverageRequest`.
`App\Support\AssistanceCoverage` applies `MunicipalityAccess` and active province/
municipality scope before aggregation, program suggestions, and boundary access.
System Owner can switch province or independent-city scope; other accounts retain
their assigned scope and veterinary accounts remain excluded.

Filters cover municipality, assistance type, exact program/sheet reference, and
explicit planting year/dry-or-wet season. Program and period currently come only
from same-municipality rice sheets attached to rice releases. Dates, harvest
periods, and parcel crops are never substituted. All seasons with a blank year
includes unclassified releases; Not recorded selects incomplete/invalid planting
periods across all years. The interface discloses this limitation for non-rice and
legacy releases. Counts separate release transactions and distinct valid linked
farmer records; quantities stay separate by recognized unit. Unlinked farmers and
incomplete quantities are disclosed, and zero releases do not imply an unserved
or ineligible population. No coverage percentage is invented.

The table is server-rendered. The optional Google 2D map loads active municipality
polygons through `GET /assistance-coverage/boundaries`, accepting at most 10 IDs
per request (60 requests/minute). Missing, duplicate, or oversized boundaries stay
in the table; no individual parcel colors are changed because releases have no
explicit parcel link. Reports show at most 250 municipalities, with truncation
disclosed, and 200 reference suggestions while accepting an exact typed reference.
Public assets are `public/css/assistance-coverage.css` and
`public/js/assistance-coverage.js`; mirror them in both Hostinger public directories
before rebuilding views. No migration, new configuration, export, or write
workflow is introduced. See `docs/ASSISTANCE_COVERAGE_MAP.md`.

### 5.5.2 Harvest records

Primary model/table: `HarvestRecord` / `harvest_records`

What was actually harvested, as a record in its own right rather than a field on an
assistance release: a farmer who planted their own seed still has a harvest, and a
harvest of corn or tilapia is not a property of a rice seed hand-out. Nine
commodities and six units, both model constants. This is what the dashboard's
production-by-commodity report reads.

Two ways a harvest gets here:

- **Projected from an assistance release.** The Rice Seed Distribution Sheet already
  has a production section staff fill in on paper. `App\Support\HarvestFromRelease`
  writes the matching harvest whenever a release is saved, updated or imported, and
  removes it when the production fields are cleared. A unique index on
  `harvest_records.rice_seed_distribution_id` keeps that a projection rather than an
  accumulating copy. `php artisan harvests:backfill-from-releases` reaches releases
  recorded before the wiring existed (`--dry-run` supported, safe to repeat).
- **Entered directly**, for every commodity no seed sheet covers.

A projected record is **read-only in this module**. The release owns its figures and
rewrites them on every save, so editing the harvest here would be silently discarded
by the next save of the sheet. `HarvestRecordPolicy` overrides `update` and `delete`
to refuse a record whose `rice_seed_distribution_id` is set; the list links to the
release instead. This is enforcement, not a hidden button.

Other rules:

- The farmer decides the owning municipality when one is named; a submitted
  `municipality_id` is only consulted when there is no farmer, and then through
  `resolveForWrite`. A farmer outside the account's scope is refused by the form
  request, and a parcel belonging to a different farmer is refused with it.
- A harvest may have no farmer. A municipality reporting a season's corn has a real
  figure and no single farmer to attach it to.
- Quantities are **never summed across units**. The list totals per unit and the
  chart draws one series per commodity-and-unit pair.
- A harvest date must fall within one year of the reported harvest year, because the
  year is what the production report groups by.
- The CSV export is audited with its filters and row count and is bounded by a fixed
  maximum id, like the machinery export.

### 5.5.3 Shared beneficiary picker

`App\Support\FarmerPicker`, `partials/farmer-picker.blade.php`,
`public/js/farmer-picker.js`, endpoint `farmers.picker`.

Both the assistance form and the harvest form choose a farmer. Both used to
serialise every farmer the account could see into a `<select>` — for Ramos's 1,546
beneficiaries that was 723,889 bytes on the assistance form and 299,292 on the
harvest form, on every load. They now render only the farmer already chosen and
search the registry on the server: 130,563 and 98,986 bytes.

- `FarmerPicker::option()` builds the option shape for both the JSON endpoint and
  the Blade partial, for the same reason `mapFarmerPayload` is shared with the map:
  an option rendered on page load must describe a farmer identically to the same
  option fetched a second later. A test asserts the two agree.
- The **profile block is opt-in** (`?profile=1`). The assistance form shows a
  beneficiary preview and needs a contact number and eligibility flags; the harvest
  form shows a name and is not sent them.
- `farmers.picker` is deliberately **separate from `farmers.lookup`**, which serves
  the parcel map and returns the fields the map draws and only those. Widening the
  map's payload to serve a form would put personal data on the map endpoint.
- Municipality scope is applied before the search term. A submitted
  `municipality_id` only ever narrows what scope already allows.
- The JS keeps the `<select>`'s own dataset describing the current selection, which
  is how the assistance form's preview reads the chosen beneficiary without knowing
  where the option came from. It also clears a selection the newly chosen
  municipality does not hold, and refuses to search at all until a provincial
  account has chosen one.
- `?browse=1` renders the whole registry the old way. It is the escape hatch for an
  operator who would rather scroll, and for a browser not running the picker.

### 5.6 Animal-health services

Primary model/table: `AntiRabiesVaccination` / `anti_rabies_vaccinations`

The historical model, table, route names, and `vaccination_date` column remain for compatibility, but the module now covers municipal animal-health work across pets, livestock, poultry, and other farm animals.

Functions:

- create, edit, delete, search, filter, and paginate vaccination, deworming, vitamins/supplementation, and treatment records;
- store owner/raiser name, barangay, optional birthday, animal species, optional breed/name/color, and the number of animals served;
- support dogs, cats, cattle, carabao, goats, sheep, swine, chickens, ducks, turkeys, horses, rabbits, and other farm animals;
- record the product/medicine/treatment, dosage, administration route, diagnosis/reason, service notes, administering staff, service date, and next follow-up date;
- preserve legacy anti-rabies submissions by defaulting missing generalized fields to vaccination, Anti-rabies vaccine, and one animal;
- look up an existing owner within one municipality and return owner details plus distinct prior animals or groups;
- filter by municipality, service type, species, owner/animal/product/diagnosis text, barangay, and optional year;
- report total services, animals served, owners, animal profiles/groups, service mix, species coverage, latest service, monthly/year activity, barangays, breeds, and owner-age charts.

The owner lookup uses write-scope resolution because it populates an entry form; province-wide agriculture and veterinary users must select a municipality before using it. `provincial_vet` accounts can list, create, update, and delete Animal Health records across municipalities in their assigned province but cannot open any other authenticated module. All records retain the same municipality policies, optimistic record-version checks, and mutation locks as the original anti-rabies module.

### 5.7 Farmers' cooperatives

Primary model/table: `FarmersCooperative` / `farmers_cooperatives`

Pivot table: `cooperative_farmer`

Functions:

- create, edit, delete, search, filter, sort, and paginate cooperatives;
- store name, chairperson, contact number, address, and description;
- show total cooperatives, total members, populated cooperatives, empty cooperatives, and machinery count;
- assign or synchronize farmers from the cooperative's municipality only;
- prevent moving a cooperative to another municipality while it has assigned farmers;
- audit membership changes separately with before/after farmer IDs and member counts;
- export a formatted `.xlsx` workbook of assigned farmers.

Resource routes explicitly use `{farmersCooperative}` so Laravel route-model binding matches controller signatures.

### 5.8 Agricultural machinery inventory

Primary model/table: `AgriculturalMachinery` / `agricultural_machineries`

Functions:

- create, edit, delete, search, filter, sort, paginate, and CSV-export equipment;
- provide quick operational views for available, in-use, attention, repair, and unassigned assets, with less-used controls inside an advanced-filter panel;
- assign each asset to either a farmer or a cooperative in the same municipality;
- enforce municipality-unique asset codes;
- track category, brand, model, serial number, acquisition year/date/source/cost, condition, availability, location, service hours, maintenance dates, and notes;
- report total/available/in-use assets, holder type, total acquisition value, category/condition charts, and a maintenance queue;
- mark equipment for attention when it is under maintenance, needs repair, is unserviceable, or has maintenance due within 30 days;
- provide a municipality-scoped JSON holder lookup for dynamic forms;
- use responsive asset cards on smaller screens and a guided create/edit form with optional acquisition/notes sections, live assignment feedback, and maintenance-date warnings;
- audit CSV exports and normal model changes.

The current form requires a farmer or cooperative holder even though legacy/unassigned assets can still be listed and filtered.

### 5.9 Backup Folder

Primary model/table: `BackupFile` / `backup_files`

This is a protected file repository, not an automated database-backup scheduler.

Functions:

- upload one or more files up to 50 MB each to private local storage, refusing markup and executable extensions such as `.html`, `.svg`, `.js`, `.php`, and `.exe`;
- restrict the destination folder to letters, numbers, spaces, dashes, underscores, and `/`, so an uploaded path cannot climb out of the backups directory;
- assign every uploaded file to a municipality and record uploader, folder, notes, size, SHA-256 hash, and the MIME type detected from the file itself rather than the type claimed by the uploading browser;
- search by filename/folder/notes/hash with contains, starts-with, ends-with, or exact modes;
- filter by municipality, folder, uploader, extension, date, and size;
- sort and display filtered file/folder/hash totals;
- authorize preview, inline streaming, download, edit, and deletion;
- stream a stored file inline only when its extension is on the module's allow-list of inert types (PDF, common images, audio, video, and text formats served as `text/plain`); everything else, including records saved before that rule existed, is sent as an attachment with `application/octet-stream`, so a stored file cannot execute script in the application's origin;
- preview PDFs, images, text, spreadsheets, and supported document formats in the browser;
- edit text-like files and `.xlsx` files in place, then recompute file size and SHA-256;
- physically delete the stored file when its database record is deleted.

System Owners and Super Admins are intentionally denied this module. Other authorized operational roles receive the normal province/municipality scope described above.

### 5.10 User management

Primary model/table: `User` / `users`

Routes: `admins.*`

Functions:

- list, search, filter, paginate, create, edit, activate/deactivate, change role, reset password, and delete accounts;
- hash every new or changed password with Laravel `Hash`;
- require at least 12 characters and refuse passwords found in a known breach corpus, using Laravel's k-anonymous `uncompromised()` check. `AppServiceProvider` binds that verifier with a three-second timeout, and an unreachable service is treated as "not breached" so an office without connectivity can still create accounts. Composition rules are deliberately not used; length and the breach check are the controls;
- require password confirmation in account forms;
- require an active municipality for municipal roles and clear `municipality_id` for provincial roles;
- let province Super Admins create `provincial_vet` accounts assigned to their province without a municipality; these accounts are restricted to province-wide Animal Health routes and policies;
- permit only one active municipal head per municipality;
- prevent self-deletion and deletion of any System Owner; only the System Owner may delete a Super Admin;
- let municipal heads manage only municipal-staff accounts in their municipality;
- let province Super Admins manage only provincial and municipal staff in their province, with profile-only edits to their own account;
- let the System Owner create, assign, activate, edit, or delete province Super Admins and lower roles, while preventing own privilege changes and all System Owner deletion/creation in the web UI;
- let the System Owner manage Regional Heads; Regional Heads manage provincial Super Admins/lower accounts only in their assigned region. Regional assignment appears only for that role and self-assignment stays fixed. Normal password validation remains unchanged; explicit private initial provisioning is not a reusable validation bypass;
- require a new password when activating an inactive Super Admin or Regional Head;
- reauthorize the freshly locked manager and target inside account mutations before saving.

Passwords are one-way hashes and cannot be retrieved. Developers may reset a password, but must never attempt to display existing passwords or store plaintext credentials.

### 5.11 Audit trail

Primary model/table: `AuditLog` / `audit_logs`

System Owner and province-scoped Super Admin functions:

- view activity totals, today's activity, seven-day activity, and security/deletion alerts;
- search/filter by event, module, municipality, actor, and date range;
- inspect request context and before/after values;
- export the same filtered scope to CSV using a stable maximum audit ID.

Audit timestamps are stored in UTC and displayed in `APP_DISPLAY_TIMEZONE` through `App\Support\LocalTime`. Audit date filters are interpreted as local Philippine calendar days and converted to UTC query boundaries. Keep `config('app.timezone')` set to UTC; changing the storage timezone would reinterpret existing records and mix timestamp conventions.

`AuditModelObserver` records created, updated, and deleted events for machinery, farmers, plots, distributions, animal-health services, cooperatives, backups, users, and municipalities. Authentication, exports, and cooperative membership changes add explicit events through `App\Support\AuditTrail`.

A new event name must be added to `AuditLog::EVENT_LABELS`, to the tone match in `getEventToneAttribute()`, to the alert list in `AuditLogController`, and to `$eventOrder` in `resources/views/audit_logs/index.blade.php`. Otherwise the audit screen shows the raw key, the entry is not coloured as an alert, and the alert total silently undercounts. The authentication events are `login`, `logout`, `session_timeout`, `login_failed`, `login_blocked`, `login_throttled`, and `login_failures_suppressed`.

Audit failures are reported but do not interrupt the user's main operation. Passwords, tokens, secrets, remember tokens, farmer public tokens, profile-photo paths, and other protected fields are removed from persisted before/after values. Preserve this behavior.

### 5.12 Geocoding and API endpoints

- `GET /api/geocode?q=...` is authenticated, limited to 30 requests/minute, calls Nominatim with an identifying user agent, and caches results for 12 hours.
- `GET /api/user` is Laravel's Sanctum-authenticated current-user endpoint.

Do not expose the Nominatim proxy anonymously or remove its throttle/cache without reviewing provider usage requirements.

### 5.13 Weather and agricultural advisories

Primary routes: authenticated `GET /farmers/weather-summary` (`farmers.weather-summary`) and throttled `POST /farmers/weather-summary/refresh` (`farmers.weather-refresh`). The legacy `/weather-advisories` route now redirects into the Parcel Map with its embedded drawer open.

Functions:

- retrieve a seven-day municipality forecast from Open-Meteo without exposing a browser API key;
- cache each municipality forecast for 30 minutes and retain a configurable last-known forecast for provider outages;
- lazy-load an in-map drawer with current temperature, apparent temperature, humidity, wind, seven-day rainfall, peak rain probability, peak wind gust, advisories, and a three-day outlook;
- generate transparent threshold-based farm guidance for heavy rainfall, high rain probability, strong wind, heat, and irrigation review;
- let provincial users select any active municipality while locking municipal users to their assigned municipality;
- provide direct links to PAGASA weather, tropical cyclone, flood, and agri-weather pages for official bulletins;
- follow the municipality of the currently selected map farmer and expose the drawer through Parcel Map controls and existing weather links without navigating to another page.

The municipality coordinates in `config/weather.php` are town-center forecast reference points. They are not surveyed parcel coordinates. Open-Meteo guidance must never be presented as an official typhoon, rainfall, thunderstorm, or flood warning; PAGASA and local disaster-risk authorities remain the official sources. Forecast refreshes use an atomic cache lock and recheck the cache after waiting so simultaneous dashboard requests do not create an outbound-request stampede.

### 5.14 Concurrent-use and write synchronization

All authenticated state-changing routes run through `SynchronizeMutatingRequests`. It serializes mutations from the same account to protect session and flash state, then obtains a shared record lock so two different staff accounts cannot mutate the same route-bound model at the same time. Creates and imports without a bound model are grouped by municipality when possible. Lock timeouts return HTTP 409 for JSON requests and a retry message for normal forms.

Normal edit forms for farmers, input releases, vaccinations, cooperatives, machinery, user accounts, and cooperative membership carry an HMAC record-version token. `App\Support\ConcurrentWrite` reloads the row with `SELECT ... FOR UPDATE` inside a retried database transaction and rejects a stale token instead of silently overwriting another staff member's newer values. Farm-plot JSON updates/deletes and editable Backup Folder files use the same optimistic check. Immediate index-page deletions re-fetch and lock the current row before changing dependencies. Browser forms also suppress rapid duplicate submissions.

Create operations and multi-record mutations use retried transactions where the workflow is database-atomic. Large seed/input and machinery CSV exports use bounded chunks and a stable maximum ID rather than loading the complete result into PHP memory. Exported spreadsheet values are guarded against CSV formula injection.

The lock mechanism requires an atomic shared cache store. The file cache is suitable for one Hostinger server when every PHP worker sees the same filesystem. Use Redis (recommended) or another shared atomic-lock store before running more than one application server; otherwise locks on separate servers cannot coordinate.

### 5.15 Municipality geofences

Ramos has nine read-only barangay planning references; the Baguio update adds
129 under its separate Baguio City supervision scope, loaded on selection through
authenticated/throttled `GET /municipality-boundaries/barangays`.
`BarangayBoundaryController` authorizes the existing view policy and scopes the
municipality before `BarangayBoundaryReferences` reads its private, checksum-verified
GeoJSON. Identity uses the supervising-province relationship, never the legacy
province string. `public/js/barangay-boundaries.js` provides toggle, highlight/focus,
labels, source notes, abort/stale-response guards, timeout/retry and one-payload
caching. References hide during municipality editing. This layer does not alter
ownership, official boundaries, parcel validation or snapshot exports. No migration
is needed. Deploy both map scripts before views; preserve the GeoJSON's LF endings.
Sources and verification: `docs/RAMOS_BARANGAY_BOUNDARIES.md` and
`docs/BAGUIO_BARANGAY_BOUNDARIES.md`. The Baguio update was deployed to Hostinger
September 21, 2026 in `a751355`. Its 129 PSGC codes match PSA; its pinned file remains
under the existing 100 KB cap. Group combined source queries before applying scope,
and use server-provided location labels in popups rather than hard-coded Ramos text.

Primary model/table: `MunicipalityBoundary` / `municipality_boundaries`

Routes: `municipality-boundaries.*`

Functions:

- provide a Google Maps boundary workspace with municipality search and selection for province-wide users; municipal accounts open only their assigned workspace, without the municipality finder or province-wide summary controls, and fit/reset stays within their own boundary and parcels;
- allow all agriculture roles to view boundaries within their normal municipality scope while reserving draw, import, edit, activate, and archive actions for the System Owner or a Super Admin assigned to that municipality’s province;
- persist each boundary's hex color and `fill_opacity` (0–1, two decimal places, default 0.20); the Geofence appearance panel provides a color picker, a keyboard-accessible 0–100% opacity slider, Save color & opacity, and Discard changes; preview affects only the selected active boundary or draft, and failed saves preserve the preview with a visible error;
- keep the same stored color and fill opacity in the municipality workspace and Farmers 3D parcel map; an already open Farmers page needs a reload after a style save; outlines remain visible at zero fill, and existing parcel colors and snapshot export styling remain unchanged;
- show subtle municipality labels in both maps, using `GeoGeometry::labelPosition()` and its existing interior-point calculation to avoid holes and water between islands; the 3D labels hide when zoomed far out or the geofence layer is hidden and use optional collision handling;
- retain saved opacity during geometry editing (new drawings default to 20%), disable the separate appearance controls until editing ends, and suppress the edited boundary's saved fill so two fills do not blend;
- display active official boundaries beneath farm parcels in the Farmers 3D map, with a visibility toggle and municipality-aware camera fitting; municipal users receive only their assigned boundary, while province-wide users receive boundaries from the selected workspace scope;
- draw Polygon boundaries in the browser or import Polygon/MultiPolygon KML, KMZ, GeoJSON, JSON, and XML files;
- normalize coordinates, close rings, reject invalid ranges, reject self-intersections and invalid holes, safely simplify oversized geometry, and reject files above the configured hard vertex limit;
- scale segment-orientation tolerance by edge length so dense survey vertices do not produce false self-intersection errors; genuine crossings, invalid holes, and rings below one square metre remain rejected;
- retain draft and archived history while permitting one active official boundary per municipality through synchronized, transaction-backed replacement;
- calculate area, centroid, vertex count, and indexed bounding-box columns for fast overlap candidate selection on MySQL/MariaDB and SQLite-compatible tests;
- detect overlap with another municipality's active boundary before saving or activation;
- classify every existing parcel as inside, near the boundary, crossing the boundary, outside, invalid, or unconfigured and provide a field-review list;
- download a map-only municipality snapshot containing the active boundary and all municipality-owned parcels; parcels outside remain visible as white polygons with warning outlines, while report titles, summaries, legends, review text, and footers are omitted;
- obtain the satellite base through an authenticated, throttled, same-origin Google Static Maps proxy, mask the image so land outside the official boundary remains white, and preserve Google's complete attribution strip in the exported PNG;
- validate all new, edited, browser-imported, and server-imported farm parcels: completely outside or invalid parcels are blocked, while crossing and near-boundary parcels are saved with a visible review warning;
- cache the active municipality boundary used by parcel writes and clear it after boundary changes;
- use HMAC record versions and municipality-level mutation locks so drawing and import cannot create competing active boundaries;
- record explicit audit events for drawing, import, update, activation, archival, automatic replacement, and completed municipality snapshot downloads using metadata instead of storing full GeoJSON in the audit log.

Overlap containment uses a verified interior point: a concave polygon centroid may lie in a neighbouring municipality or in a hole. The scanline fallback preserves identical/contained concave geometry checks without changing source coordinates or tolerances.

`App\Support\GeoGeometry` is the authoritative geometry implementation and `App\Support\MunicipalityBoundaryGuard` is the farm-plot enforcement boundary. Do not copy point-in-polygon or overlap logic into controllers or JavaScript. A missing active boundary does not block existing parcel work; the review workspace reports those parcels as unconfigured until the Super Admin activates an official boundary.

The boundary editor preserves full source precision for untouched vertices and does not round edited coordinates before saving. Rounding a shared border independently can create false overlaps. Name/color-only edits omit geometry; the controller preserves geometry and its measurements when unchanged, while changed shapes require normal confirmation and overlap checks after the record-version check. Regression commands: `node --test tests/JavaScript/municipality-boundary-editor.test.cjs` and the isolated `MunicipalityGeofenceTest` / `ProvinceAccessIsolationTest` suites.

The appearance-only `PATCH /municipality-boundaries/{boundary}/style` route uses the existing authentication, account scope, synchronized-write middleware, update policy and `ConcurrentWrite` version check. It accepts only color, opacity and record version, rejects archived boundaries, and audits changes without geometry. This works for MultiPolygon boundaries whose shapes the browser cannot edit. Apply the additive `2026_09_21_000100_add_fill_opacity_to_municipality_boundaries.php` migration before the new map queries. Load `public/js/geofence-style.js` before both map scripts and mirror assets to both Hostinger public directories.

`geofences:region-two-white` previews the explicit 93 Region II geographic municipality/city scopes; `--apply --actor=ID` requires an active System Owner, verified coverage, an all-or-nothing transaction and attributed audits. It changes active boundary styles to white at 20%, preserves geometry, other boundaries, accounts and regional assignments, and is safe to repeat. It was applied locally and deployed to Hostinger on September 21, 2026 in commit 610013d, with 93 styles and all 489 shapes verified. See `docs/GEOFENCE_APPEARANCE_2026_09_21.md` for verification, backup and deployment requirements.

## 6. Route inventory

As of 2026-09-17, `php artisan route:list --json` reports 96 routes protected by Laravel authentication, including the Sanctum endpoint:

| Area | Authenticated routes |
| --- | ---: |
| Farmers | 14 |
| Farmers' cooperatives | 9 |
| Seed and farm-input distribution | 9 |
| Rice seed distribution sheets | 8 |
| Machinery inventory | 8 |
| Animal health | 7 |
| Backup Folder | 7 |
| User management | 6 |
| Farm plots | 6 |
| Audit trail | 3 |
| Weather and agricultural advisories | 4 |
| Municipality geofences | 9 |
| Dashboard/logout/session/API | 6 |

Regenerate the inventory instead of preserving this number if routes change:

```bash
php artisan route:list
```

## 7. Main data relationships

```text
Municipality
├── users
├── municipality_boundaries
├── farmers
│   ├── farm_plots
│   ├── rice_seed_distributions
│   ├── agricultural_machineries
│   └── farmers_cooperatives (many-to-many through cooperative_farmer)
├── rice_seed_distributions
│   └── rice_distribution_batches (optional sheet grouping through batch_id)
├── rice_distribution_batches
├── anti_rabies_vaccinations
├── farmers_cooperatives
│   └── agricultural_machineries
├── agricultural_machineries
└── backup_files

User / Municipality / operational models ──> audit_logs (polymorphic identity fields)
```

Distribution records intentionally keep a farmer snapshot in addition to `farmer_id`, so historical reports remain readable if the farmer profile later changes. `farm_municipality` is also a report snapshot; it must not replace `municipality_id` for authorization.

## 8. Codebase map

- `routes/web.php`: guest, authenticated, public QR, resource, geocoding, and admin routes
- `routes/api.php`: default Sanctum current-user route
- `app/Http/Controllers`: request validation, authorization, queries, imports/exports, and responses
- `app/Models`: fillable fields, casts, relationships, scopes, and role helpers
- `app/Policies`: record-level and module-level permissions
- `app/Policies/Concerns/AuthorizesMunicipalityRecords.php`: shared operational policy behavior
- `app/Support/MunicipalityAccess.php`: shared tenancy scoping and ownership resolution
- `app/Support/AuditTrail.php`: safe audit-event creation and secret filtering
- `app/Support/ConcurrentWrite.php`: record versioning, row locks, retried transactions, and stale-write rejection
- `app/Support/GeoGeometry.php`: GeoJSON normalization, validation, simplification, measurement, overlap, containment, and near-boundary calculations
- `app/Support/MunicipalityBoundaryGuard.php`: active-boundary lookup and parcel write enforcement
- `app/Support/MunicipalityBoundaryImporter.php`: KML, KMZ, and GeoJSON boundary parsing
- `app/Http/Middleware/SecurityHeaders.php`: content-security, framing, referrer, permissions, and transport headers for every response; its source lists must gain any new CDN or provider host added to a Blade view
- `app/Http/Middleware/SynchronizeMutatingRequests.php`: per-account and per-record/cache mutexes for state-changing requests
- `app/Http/Middleware/EnforceIdleSession.php`: server-side 15-minute inactivity enforcement and timeout auditing
- `app/Http/Middleware/RestrictProvincialVeterinaryAccess.php`: route-level Animal Health-only boundary for `provincial_vet`
- `app/Observers/AuditModelObserver.php`: automatic model change logging
- `app/Providers/AuthServiceProvider.php`: model/policy registration
- `app/Providers/AppServiceProvider.php`: audit observers and custom pagination views
- `resources/views/layouts/app.blade.php`: shared shell, responsive navigation, and role-aware module links
- `resources/views/partials/operations-ui-styles.blade.php`: shared operational-module design system
- `resources/views/vendor/pagination`: application-wide pagination templates
- `public/js/municipality-boundaries.js`: the geofence workspace script, loaded by `resources/views/municipality_boundaries/index.blade.php`. Extracted from that page for the same reasons as the plotting workspace: it is linted and diffable, and the template compiler cannot swallow part of it. It holds no Blade syntax; server values arrive on `window.__municipalityBoundarySettings`, written by the page, and a new value is added there rather than in the script. `tests/JavaScript/municipality-boundary-editor.test.cjs` reads this file, so moving it again means updating that test.

Map hover and overview rendering: parcel tooltip motion shares one animation-frame update, caches dimensions until content or map size changes, and uses CSS translation without background blur. Stored parcel areas avoid geometry scans on hover. Municipality overview uses one polygon per component; pale casing returns at zoom 13 or when a municipality is selected. Canonical coordinates and access rules are unchanged. See `docs/MAP_HOVER_PERFORMANCE.md` for verification, deployment and remaining payload limits.

The geofence renderer reuses unchanged overlays, culls overview boundaries outside a padded viewport, and draws new groups in cancellable animation-frame batches (at most 12 groups or an eight-millisecond budget per batch). Hidden groups are evicted when the cache exceeds 160; visible groups are never dropped to meet that target. Municipality labels appear at zoom 10 or closer, or for the selected municipality. Map fitting uses cached bounds from canonical geometry, independently of rendered overlays. Search waits 250 ms after typing, aborts superseded requests, and rejects stale responses; retries and successful writes force a fresh municipality payload. Switching boundaries cancels the editor before changing its target. Full source coordinates remain unchanged for editing, validation, and snapshots. The initial HTML still includes all scoped current geometry, and the detail endpoint still classifies all municipality parcels; this is a browser-rendering optimization, not a reduction of those server payloads. See `docs/GEOFENCE_BROWSING_PERFORMANCE.md` and `tests/JavaScript/municipality-boundary-rendering.test.cjs`.

Boundary saves disable duplicate submissions and show a persistent, focusable error inside the editor. Name/color-only changes compare geometry type and coordinates rather than GeoJSON property order, and omit unchanged geometry; active shape edits still require explicit replacement confirmation. HTTP 401/419 and HTML login responses explain that the session needs renewal instead of treating the response as a successful save. Failed saves retain current form values. A response from an older editor cannot close or replace a subsequently opened editor. These behaviors are covered by the rendering and editor JavaScript suites and `MunicipalityGeofenceTest`; see `docs/BOUNDARY_SAVE_FEEDBACK.md`.
- `public/js/farmers-maps.js`: the authenticated plotting workspace script, loaded by `resources/views/farmers/partials/maps-scripts.blade.php`. It is a plain file rather than a Blade template so editors and linters can read it and the template compiler cannot swallow part of it, and it stays a classic script because it shares `var` declarations across what used to be two `<script>` blocks and exports its API to the rest of the page as `window.__*`. Everything the server decides reaches it through the `window.__*` config block in `resources/views/farmers/maps.blade.php`; never reintroduce a Blade directive or `{{ }}` into the script itself. Adding a new server value means adding it to that block.
- `resources/views/farmers/partials/maps-*`: authenticated plotting workspace CSS and the loader for the script above
- `app/Support/RiceSeedDistributionSheet.php`: Rice Seed Distribution Sheet titles, column groups, row values, filtered release query, and aggregated totals shared by the screen and the export
- `app/Support/RiceSeedDistributionSheetWorkbook.php`: the printable `.xlsx` writer for that sheet; every cell goes through `CsvExport::value()`
- `app/Support/SeedReleaseQuantity.php`: the single bags x bag-weight to kilograms rule, stored in the existing `kgs_received` column
- `app/Support/CsvExport.php`: the single spreadsheet-formula guard for CSV exports. It replaced three byte-identical private copies; a new export must use it rather than growing a fourth.
- `resources/views/components/module/field.blade.php`: the `<x-module.field>` form-field component. See DESIGN_SYSTEM.md section 13 for its contract and the list of forms still to migrate.
- `app/Http/Requests`: form requests for the farmer, farm parcel, assistance release, and municipality geofence write endpoints. Each runs its policy in `authorize()`, which Laravel checks before the rules, so an unauthorized account is refused rather than handed a description of the form.
- `database/migrations`: incremental schema changes; see the warning below
- `tests/Feature`: role, municipality isolation, dashboard, QR map, machinery, user-management, and audit coverage

`App\Models\FarmPlot` is the only model for the farm parcel table. The duplicate legacy `FarmerPlot` model was removed after confirming no route, view, job, test, CLI, or seeder referenced it; do not reintroduce a second model for that table.

`EnsureHeadAdmin` and its `head_admin` route-middleware alias were removed, along with the obsolete role label in the layout; no route used them and `head_admin` is not a supported role. Authorization goes through policies and the supported `User` role constants.

## 9. Database and migration warning

The repository does **not yet contain a complete migration history for a clean database**. The application began from an imported legacy schema. Core tables such as `municipalities`, `farmers`, `farm_plots`, `rice_seed_distributions`, `anti_rabies_vaccinations`, `farmers_cooperatives`, `cooperative_farmer`, and `backup_files` are not all created by repository migrations.

Consequences:

- Do not assume `php artisan migrate` on an empty database can build the application.
- Do not run migrations blindly against an imported database whose `migrations` table does not match its real schema; old “create users” migrations may appear pending even when `users` already exists.
- Obtain the approved, sanitized baseline SQL schema from the project owner, then reconcile migration history before onboarding a fresh environment.
- The long-term fix is to create and test a complete baseline migration set or squash the verified schema into a Laravel schema dump.

Legacy and deployed environments may still be missing these incremental migrations even when the current local database has them:

- `2026_08_20_000100_add_input_details_to_rice_seed_distributions.php`, which adds `input_category`, `quantity_unit`, and `input_notes`;
- `2026_08_20_000200_create_agricultural_machineries_table.php`.
- `2026_09_03_000100_create_municipality_boundaries_table.php`, which adds official geofence geometry, lifecycle, measurements, bounding-box indexes, and editor attribution.
- `2026_09_17_000100_create_rice_distribution_batches_table.php`, which adds the Rice Seed Distribution Sheet batches table.
- `2026_09_17_000200_add_seed_sheet_fields_to_rice_seed_distributions.php`, which adds the nullable sheet columns (`batch_id`, `registered_rice_area_ha`, `seed_bags`, `seed_bag_kg`, `harvest_season`, `harvest_year`, `consent_status`, `kp_kits_received`, `representative_name`) and the `batch_id` foreign key.

The migration `2026_08_24_000100_extend_vaccinations_for_animal_health_services.php` widens the legacy Dog/Cat enum and adds the generalized animal-health service fields. It must be applied before deploying code that queries `service_type` or `animal_count`.

The code already queries those fields/tables. Apply the migrations to the intended environment after taking a database backup. A missing `quantity_unit` produces `SQLSTATE[42S22]`, and a missing machinery table prevents the dashboard and machinery module from loading.

The two Rice Seed Distribution Sheet migrations are additive only. They create one new table and add nullable columns (plus the `consent_status` default `unrecorded`) to the imported `rice_seed_distributions` table; nothing is renamed, repurposed, backfilled or deleted, and existing releases stay usable with a null `batch_id`. Because the repository has no complete migration history, apply them by path after taking a backup, never with a bare `php artisan migrate`:

```bash
php artisan db:backup
php artisan migrate --path=database/migrations/2026_09_17_000100_create_rice_distribution_batches_table.php
php artisan migrate --path=database/migrations/2026_09_17_000200_add_seed_sheet_fields_to_rice_seed_distributions.php
```

Both roll back cleanly: the column migration drops its foreign key before its columns, and the table migration drops the batches table afterwards. Rolling back leaves every existing release row untouched.

The migration `2026_08_17_000000_backfill_rice_distribution_municipalities.php` fills missing distribution ownership from the linked farmer and intentionally does not erase that business ownership on rollback.

### Reference geofences and synthetic demonstration data

`TarlacMunicipalityDemoSeeder` is an explicit, idempotent demo seeder for Anao, Camiling, Paniqui, and Ramos. It is deliberately not called by `DatabaseSeeder`. It creates or updates a dedicated cohort of 10 clearly synthetic farmers and 10 clearly synthetic agriculture/fisheries assistance records in each of those four municipalities without deleting or replacing existing operational records. The same seeder also activates reference geofences for Concepcion and Tarlac City, but deliberately creates no synthetic operational records for those two workspaces.

The same seeder activates approximate municipality planning/reference geofences from the pinned geoBoundaries `gbOpen` Philippines ADM3 revision `9469f09`, which identifies NAMRIA, PSA, and OCHA Philippines as upstream sources and uses the CC BY 3.0 IGO license. The local source snapshot, provenance, checksum, and limitations are documented in `database/seeders/data/README.md`. These boundaries are not cadastral, legal, or survey-grade and require LGU/NAMRIA verification before being described as official.

`BulacanProvinceBoundarySeeder` is a separate, explicit, idempotent reference-boundary import for the existing Bulacan evaluation workspace. It uses the pinned geoBoundaries Philippines ADM2 revision `41af8f1`, validates the Bulacan feature ID and PSGC identity, checks the computed area against the Province of Bulacan's 278,369-hectare planning reference, prevents cross-workspace overlap, and activates one province-level planning/reference boundary without creating operational data. It may create the Bulacan workspace when absent, but it must not create users, farmers, or releases.

The Bulacan import resolves the legacy `BUL` code, `BULACAN`, PSGC code, or unambiguous Bulacan workspace name while preserving the existing ID, code, and province assignment. Ambiguous, inactive, or foreign-province matches fail closed. Workspace resolution/creation and boundary writes share the activation lock and transaction, so a rejected boundary does not leave a new workspace behind. Re-importing the same active geometry preserves saved styling and rounded metadata without another audit event. Its feature tests use their own in-memory SQLite schema.

`BaguioCityBoundarySeeder` explicitly imports the Baguio City ADM3 planning/reference boundary from revision `9469f09`, feature `30758251B18922588133033`, PSGC `1430300000` (legacy `141102000`). It validates the pinned checksum and area against the GeoRiskPH/PSA reference, reuses an unambiguous active Baguio workspace or creates `BAGUIO`, and activates one boundary without creating accounts or operational records. Workspace creation and boundary activation share one transaction and the global activation lock. A conflicting active boundary, inactive workspace, or ambiguous identity stops the import; an existing different Baguio boundary is preserved. The display province `Benguet` follows the source's geographic grouping and does not alter Baguio's highly urbanized city status or municipality-based access rules. See `database/seeders/data/README.md` for source attribution, checksum, limitations, and the explicit import command. Do not register this reference seeder in `DatabaseSeeder` or automatic production deployment.

`BenguetMunicipalityBoundarySeeder` explicitly imports only La Trinidad, Atok, and Tublay using the pinned ADM3 revision `9469f09`. It verifies all feature identities, PSGC codes, checksum, and area tolerances before applying the three references in one transaction under the global activation lock. It reuses unambiguous active Benguet workspaces or creates `LATRINIDAD`, `ATOK`, and `TUBLAY`; a wrong province, ambiguity, inactive workspace, overlap, or existing changed/deactivated boundary aborts the entire import. Each new reference has an attributed audit event, and successful application invalidates all three boundary caches. No accounts or operational records are created. The geometries share compatible edges with each other and the Baguio reference. Source provenance and explicit instructions are in `database/seeders/data/README.md`. This named seeder must not be included in automatic production seeding.

`TarlacRemainingMunicipalityBoundarySeeder` explicitly imports the other twelve Tarlac municipalities: Bamban, Capas, Gerona, La Paz, Mayantoc, Moncada, Pura, San Clemente, San Jose, San Manuel, Santa Ignacia, and Victoria. Together with the existing six references, these cover Tarlac's 17 municipalities and one city. The separate pinned snapshot uses ADM3 revision `9469f09`; PSGC identities, geographic extents, and areas were checked against the GeoRiskPH/PSA Tarlac layer to distinguish same-named municipalities in other provinces. This importer creates no accounts or operational data and preserves existing boundaries, including unrelated archived Moncada history. It does not call the demo seeder. Source attribution, identities, checksum, and the explicit import command are in `database/seeders/data/README.md`.

`BenguetRemainingMunicipalityBoundarySeeder` explicitly imports Bakun, Bokod, Buguias, Itogon, Kabayan, Kapangan, Kibungan, Mankayan, Sablan, and Tuba from a separate pinned ADM3 revision `9469f09` snapshot. Together with La Trinidad, Atok, and Tublay, these cover all thirteen Benguet municipalities; Baguio City retains its separate city reference. Source identities, geographic extents, and areas were checked against PSA/GeoRiskPH references. It reuses unambiguous active workspaces or creates only the missing municipality workspaces, preserves existing boundaries and archived history, and creates no users or operational records. All ten references share one atomic import. See `database/seeders/data/README.md` for provenance, metrics, checksum, and the explicit command.

`BulacanMunicipalityBoundarySeeder` explicitly imports all twenty-four Bulacan workspaces from a separate pinned ADM3 revision `9469f09` snapshot: the 20 municipalities plus the component cities of Baliwag, Malolos, Meycauayan, and San Jose del Monte (the legacy snapshot/workspace retains `Baliuag`). Source identities were confirmed by exact name and by geographic extent against the PSA/GeoRiskPH Bulacan layer, which distinguishes them from the San Miguel, San Rafael, San Ildefonso, and Santa Maria municipalities in other provinces. Two workspace names deliberately differ from the source `shapeName` through the importer's `workspace_name` identity field: **Bulakan**, because the source and PSA spell that municipality `Bulacan`, which is identical to the legacy Bulacan province workspace; and **Malolos City**, **Meycauayan City**, and **San Jose del Monte City**, which follow the existing `Tarlac City` and `Baguio City` wording rather than the source's `City of Malolos` form.

Because a province polygon contains every municipality inside it, the province-level and municipality-level Bulacan references cannot both stay active under the overlap rule. This seeder therefore archives exactly one named reference — `Bulacan Province Planning Reference · geoBoundaries 2020`, and only while it is active and owned by a Bulacan municipality — with an `archived` audit event carrying `reason: superseded_by_municipality_references`. The province workspace keeps its ID, code, name, province assignment, and archived history, and re-running `BulacanProvinceBoundarySeeder` restores the province-level view. An identically named boundary in another province is never archived. Every other conflict still stops the whole import, and the supersession rolls back with it because archival, workspace creation, and boundary writes share one activation lock and one transaction. See `database/seeders/data/README.md` for provenance, metrics, checksum, and the explicit command.

The Benguet, remaining-Benguet, remaining-Tarlac, and Bulacan municipality seeders delegate to `App\Support\ReferenceMunicipalityBoundaryImporter`. This service validates the complete pinned source before writing, resolves unambiguous active workspaces, and imports the entire set under the shared activation lock and one retried transaction. It refuses ambiguous identities, wrong provinces, inactive workspaces, overlaps, and different active or changed/deactivated reference boundaries. An identity may set `workspace_name` when the workspace must not carry the source `shapeName`, and a caller may name one coarser active reference in the same province to archive as superseded inside the same transaction; nothing else is ever archived automatically. Successful application clears each target's boundary cache; new boundaries receive attributed import audit events through the existing best-effort audit mechanism. Repeated imports do not duplicate boundaries or events. These seeders are intentionally excluded from `DatabaseSeeder` and automatic production deployment.

`IlocosNorteMunicipalityBoundarySeeder`, `IlocosSurMunicipalityBoundarySeeder`, `LaUnionMunicipalityBoundarySeeder`, and `PangasinanMunicipalityBoundarySeeder` explicitly import Region I’s 125 planning references (23, 34, 20, and 48). They use the shared importer, preserve existing identities and boundaries, qualify nationally repeated names by province, and create no accounts or operational data. Each province is atomic and idempotent; an all-region deployment wraps the four imports in an outer transaction during maintenance. Paoay retains the source’s lake-inclusive outline and validates against the matching GeoRiskPH exterior-area convention; the 3% tolerance is unchanged. Dagupan is grouped geographically under Pangasinan without changing its independent-component-city status. Source identities, checksums, limitations and explicit commands are in `docs/REGION_I_BOUNDARY_SOURCES.md`. Keep these seeders out of `DatabaseSeeder` and automatic deployments.

`demo:region1` / `RegionOneSampleData` explicitly previews or imports 12 synthetic farmers, 36 hypothetical plots, 12 rice releases and three batches across three existing municipalities in distinct active Region I provinces. It requires an active System Owner, defaults to preview, and writes only with `--apply`. The insert-only transaction shares plotting scope locks, validates all placements through `HypotheticalFarmPlots`, requires an owner-only audit receipt, preserves existing records/accounts/geofences and rejects partial or changed cohorts instead of overwriting them. It never runs through `DatabaseSeeder` or automatic deployment. Samples are conspicuously labeled, use no official RSBSA/contact/birthday identity, and remain included in operational totals. See `docs/REGION_I_SAMPLE_DATA.md` for exact quantities, bounds, verification and live status.

`AuroraMunicipalityBoundarySeeder`, `BataanMunicipalityBoundarySeeder`, `NuevaEcijaMunicipalityBoundarySeeder`, `PampangaMunicipalityBoundarySeeder`, `ZambalesMunicipalityBoundarySeeder`, `AngelesCityBoundarySeeder`, and `OlongapoCityBoundarySeeder` add 88 Region III references (8/12/32/21/13/1/1). With the existing 42 Tarlac/Bulacan references, coverage is 130. Angeles City and Olongapo City have separate supervising scopes, explicitly selected by the owner; provincial access must never include those cities through geographic grouping. Existing accounts and scope assignments remain unchanged. The existing Bulacan seeder may archive its exact coarse province reference when activating municipality references. Source details, checksum/area checks, current Baliwag classification, scope rules and explicit commands are in `docs/REGION_III_BOUNDARY_SOURCES.md`. These seeders remain excluded from automatic deployment and `DatabaseSeeder`.

`BatanesMunicipalityBoundarySeeder`, `CagayanMunicipalityBoundarySeeder`, `IsabelaMunicipalityBoundarySeeder`, `NuevaVizcayaMunicipalityBoundarySeeder`, `QuirinoMunicipalityBoundarySeeder`, and `SantiagoCityBoundarySeeder` explicitly import Region II's 93 planning references (6/29/36/15/6/1). The owner selected a separate Santiago City supervising scope; Isabela administrators cannot access it. Source geography remains Isabela in attribution. The 2020 pinned shapes retain their coordinates and island components; new display names include the PSA-corrected Sanchez Mira and Alfonso Castañeda. Duplicate town names are province-qualified. Each province is atomic and idempotent; an all-region import uses an outer transaction after a verified backup. Existing identities, accounts and operational records are preserved. Keep these seeders out of `DatabaseSeeder` and automatic deployment. Sources, checksums, area checks and explicit commands: `docs/REGION_II_BOUNDARY_SOURCES.md`.

`NegrosOccidentalMunicipalityBoundarySeeder`, `NegrosOrientalMunicipalityBoundarySeeder`, `SiquijorMunicipalityBoundarySeeder`, and `BacolodCityBoundarySeeder` cover the full Negros Island Region with 63 references (31/25/6/1). The owner explicitly selected separate Bacolod City supervision. Current source PSGC metadata uses region prefix `18`; legacy 9-digit and previous 10-digit identifiers remain lookup aliases. Existing names, codes, IDs and geometry are preserved. A Bacolod workspace still assigned to Negros Occidental must undergo an explicit reviewed scope transfer before its seeder runs; the importer must never silently transfer ownership. The local September 20 setup changed only Bacolod's supervising scope and added its owner-only audit, preserving all existing boundary, account and operational rows. These four seeders stay outside `DatabaseSeeder` and automatic deployment. Sources, checksums, limitations and deployment requirements: `docs/NEGROS_ISLAND_BOUNDARY_SOURCES.md`.

`MountainProvinceMunicipalityBoundarySeeder` explicitly imports ten planning references under the Mountain Province supervising scope using `ReferenceMunicipalityBoundaryImporter`. Bontoc uses a province-qualified workspace name/code to avoid Southern Leyte identity collisions. The pinned source checksum, PSGC identities, independent area checks and import instructions are in `docs/MOUNTAIN_PROVINCE_BOUNDARY_SOURCES.md`. The import is atomic and idempotent, preserves existing records, creates no accounts or operational samples, and stays outside `DatabaseSeeder` and automatic deployments. It was explicitly applied and verified on localhost and Hostinger on September 20, 2026; no migration or new configuration was needed.

Run the demo seeder only when demonstration data is intentionally required; run each reference importer explicitly for its intended workspace:

```bash
php artisan db:seed --class=TarlacMunicipalityDemoSeeder
php artisan db:seed --class=BulacanProvinceBoundarySeeder
php artisan db:seed --class=BaguioCityBoundarySeeder
php artisan db:seed --class=BenguetMunicipalityBoundarySeeder
php artisan db:seed --class=BenguetRemainingMunicipalityBoundarySeeder
php artisan db:seed --class=TarlacRemainingMunicipalityBoundarySeeder
php artisan db:seed --class=BulacanMunicipalityBoundarySeeder
```

These seeders use a global boundary-activation lock and retried database transactions, validate geometry and published-area tolerances, refuse unknown overlaps, and record boundary lifecycle events against an active System Owner or a Super Admin assigned to the target province through `ReferenceBoundaryAccess`. New reference workspaces receive an explicit `province_id`; inactive or mismatched supervision is rejected. The Baguio, Benguet, and remaining-Tarlac importers preserve existing different active boundaries and require explicit review instead of replacing them. `TarlacMunicipalityDemoSeeder`, `BulacanProvinceBoundarySeeder`, and `BulacanMunicipalityBoundarySeeder` retain their documented archival behavior. Never add these named seeders to automatic production deployment seeding.

### Remaining CAR municipality geofences

`AbraMunicipalityBoundarySeeder`, `ApayaoMunicipalityBoundarySeeder`, `IfugaoMunicipalityBoundarySeeder`, and `KalingaMunicipalityBoundarySeeder` explicitly import the remaining 53 CAR municipality/city planning references (27/7/11/8). Tabuk City remains a component-city workspace supervised by Kalinga. With Benguet's thirteen municipalities, separate Baguio City and Mountain Province's ten municipalities, these provide 77 CAR municipality/city references; the legacy Benguet office workspace is not another geographic municipality.

All 53 were imported into Hostinger on September 21 at 00:38 UTC under explicit owner authorization. All 77 map-controller responses, province/municipality isolation, repeat-import stability and existing-row preservation passed. See `docs/CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md` for backups and release verification.

The four pinned ADM3 snapshots use revision `9469f09`, existing geometry/area guards and `ReferenceMunicipalityBoundaryImporter`. Nationally repeated names are province-qualified. Imports are atomic per province, preserve existing identities, account assignments, operational data and changed/deactivated boundaries, and create no accounts or sample records. Keep them outside `DatabaseSeeder` and automatic deployments. Region membership is not inferred or assigned by these imports. Sources, checksums, verification and explicit deployment steps are in `docs/REMAINING_CAR_BOUNDARY_SOURCES.md`; local installation status is recorded in `docs/REMAINING_CAR_LOCAL_SETUP_2026_09_21.md`. No migration, dependency or UI change is required.

### Province supervision deployment

Apply only `2026_09_08_000100_add_province_supervision.php` to a verified existing schema, then explicitly run `province-access:setup` with the intended owner ID and staff province. The migration creates provinces, adds indexed province foreign keys to municipalities/users/audit logs, and backfills known municipality and audit ownership without changing user roles. The setup command preserves the chosen existing administrator's sign-in password, promotes that account to System Owner, assigns explicitly selected unassigned provincial staff, and prepares inactive provincial Super Admin accounts. No password is output, reused, emailed, or stored in plaintext. Set their passwords and activate them through User Management. See `PROVINCE_SUPERVISION.md` for rollout and rollback sequencing.

Audit `province_id` is a durable snapshot derived from record ownership, with actor scope only for non-record events. Provincial audit lists, totals, options, details, and CSV exports use this snapshot. Global/null events and cross-province reassignment events are owner-only. Do not infer historical scope from an actor's current assignment or expose current foreign account profiles through historical audit relationships.

## 10. Environment, maps, and storage

Never commit `.env`, API keys, production database credentials, password lists, SQL dumps containing personal data, or real farmer documents. A credential that reaches a commit is exposed to everyone who has ever cloned the repository; removing the file afterwards does not undo that, so rotate the credential first and treat history rewriting as a separate decision.

Session payloads are encrypted at rest with the application key through `SESSION_ENCRYPT`, which defaults to true. Turning it on or off invalidates every stored session and signs everyone out once, so schedule that deployment outside office hours.

Browser-facing security settings live in `config/security.php` and `App\Http\Middleware\SecurityHeaders`. On any host reachable over HTTPS set `SESSION_SECURE_COOKIE=true`, so the session cookie is never sent over a plain connection, and leave `SECURITY_HSTS_MAX_AGE` at its default only once HTTPS works on every hostname the office uses. If a screen breaks after a deployment because the content-security policy blocked a resource, set `SECURITY_CSP_REPORT_ONLY=true` to restore the screen while violations are still reported, add the missing host to `SecurityHeaders`, then switch enforcement back on. `TrustHosts` remains disabled in `App\Http\Kernel`; enable it with the deployment's real hostnames if the site is ever served behind a proxy that forwards an untrusted `Host` header.

Minimum application settings include:

```dotenv
APP_NAME="Agriculture Information System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_DISPLAY_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

GOOGLE_MAPS_API_KEY=...
GOOGLE_MAPS_MAP_ID=...
GOOGLE_MAPS_STATIC_API_KEY=...

WEATHER_CACHE_MINUTES=30
WEATHER_STALE_HOURS=12
WEATHER_TIMEOUT_SECONDS=8
WEATHER_TIMEZONE=Asia/Manila
WEATHER_REFRESH_LOCK_SECONDS=30
WEATHER_REFRESH_WAIT_SECONDS=3

CONCURRENCY_LOCK_SECONDS=120
CONCURRENCY_WAIT_SECONDS=5
DB_TRANSACTION_ATTEMPTS=3

SESSION_LIFETIME=15
SESSION_IDLE_TIMEOUT=15
```

Google Maps settings are defined in `config/services.php`. The optional static
key falls back to the browser key, but production should use a separate
server-side key restricted to Maps Static API:

```php
'google_maps' => [
    'key' => env('GOOGLE_MAPS_API_KEY'),
    'map_id' => env('GOOGLE_MAPS_MAP_ID'),
    'static_key' => env('GOOGLE_MAPS_STATIC_API_KEY') ?: env('GOOGLE_MAPS_API_KEY'),
],
```

After changing environment configuration, run:

```bash
php artisan optimize:clear
php artisan config:cache
```

Municipality snapshot 502 responses distinguish provider access denial (HTTP 401/403), quota limits (429), invalid requests (400), connection failures, and lock contention. Provider failures log only the upstream HTTP status; connection failures never log the raw exception URL or key. Failed images are not cached. On production, check that the effective `GOOGLE_MAPS_STATIC_API_KEY` belongs to a project with Maps Static API and billing enabled. Verify its application restriction against the actual server request: the proxy sends `APP_URL` plus `/` as its Referer; IP restrictions must match the hosting server's outbound IP. Do not remove key restrictions as a workaround. The browser map working does not verify Static API access. After changing `.env`, rebuild the configuration cache with the commands above and retry the download. No database migration is needed for these diagnostics.

The weather module uses Open-Meteo and does not require an API key. Optional provider URL and advisory thresholds are defined in `config/weather.php`. Keep the cache enabled in production to limit outbound requests and improve responsiveness. Atomic cache locks are also part of request and record synchronization; do not set `CACHE_DRIVER=array` outside isolated tests.

Google Cloud must have Maps JavaScript API and Maps Static API enabled as required by the authenticated plotting, public QR map, and export UI, billing enabled, and an HTTP referrer restriction matching the deployed domain (for production, include `https://agritarlac.online/*`). The browser key is necessarily visible to public-map visitors, so it must be restricted to approved websites and only the required browser APIs.

Farmer photos and backup files live on the private `local` disk under `storage/app`; they are delivered through authorized controller routes. They must not be moved directly into `public/`. Ensure `storage` and `bootstrap/cache` are writable. The normal `public/storage` symlink is not required for these protected files.

`credentials.txt` is a legacy sensitive file. Do not print, copy, quote, or add its contents to documentation, logs, tests, commits, or chat. If it contains real credentials, rotate them and remove the file from version control through a separate, reviewed security change.

## 11. Local setup and deployment checklist

For an existing, correctly baselined database:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:status
npm install
npm run build
php artisan optimize:clear
php artisan serve
```

On Linux/macOS use `cp` instead of `copy`. Before running `php artisan migrate`, review the database warning above and back up the target database.

Production/Hostinger requirements:

- point the domain's document root to the Laravel `public/` directory;
- keep `.env`, `vendor`, `storage`, and application source outside public web access where hosting layout permits;
- set `APP_ENV=production`, `APP_DEBUG=false`, the HTTPS `APP_URL`, secure DB values, and map configuration;
- install optimized Composer dependencies with `composer install --no-dev --optimize-autoloader`;
- build frontend assets locally or on the server with `npm run build`;
- make `storage` and `bootstrap/cache` writable by PHP;
- run only reviewed pending migrations after taking a backup;
- run `php artisan optimize:clear`, then cache configuration/routes/views after configuration is correct;
- verify login, every role, municipality isolation, uploads/downloads, maps, imports, exports, and the public QR route over HTTPS.

### Database backup and restore

Because this repository has no complete migration history, the dump is the only route back from a lost database. `php artisan db:backup` writes a verified, gzipped logical dump through `App\Support\DatabaseBackup`, reading inside one consistent snapshot so a dump taken during office hours is a coherent point in time. It is produced in PHP rather than by `mysqldump`, so it does not depend on a matching client version or on shell access being available.

`App\Console\Kernel` schedules it daily at `BACKUP_DATABASE_SCHEDULE_AT`, which only runs where Laravel's scheduler is actually invoked. On a server add the one cron entry Laravel needs, and confirm it is running:

```bash
* * * * * cd /path/to/agri-ms && php artisan schedule:run >> /dev/null 2>&1
php artisan schedule:list
php artisan db:backup            # take one immediately and confirm it appears
```

Dumps land in `BACKUP_DATABASE_PATH` (`storage/app/backups/database` by default), which `storage/app/.gitignore` already excludes from source control. They contain farmer personal data, so they must never be committed, served from a public path, or emailed. Point `BACKUP_DATABASE_PATH` at separate storage on a server and copy the dumps off the machine as well: a backup on the same disk as the database does not survive the failure it exists for. `BACKUP_DATABASE_KEEP` controls how many are retained.

To restore, work on a scratch database first and never straight over a live one. The dump begins with `DROP TABLE IF EXISTS` for every table, so pointing it at the wrong database destroys that database:

```bash
gzip -dk storage/app/backups/database/ag_system-YYYYmmdd-HHMMSS.sql.gz
mysql --user=... --password ag_system_restorecheck < storage/app/backups/database/ag_system-YYYYmmdd-HHMMSS.sql
```

Then compare the restored copy against what you expect — table list, and row counts for `farmers`, `municipalities`, `municipality_boundaries`, `users`, and `audit_logs` — before considering the backup proven. A backup nobody has restored is only a promise of a backup, so repeat this check periodically and after any schema change. The procedure above was verified against this database: all 15 tables and all 2,106 rows restored, with accented municipality names and geofence GeoJSON byte-identical to the original.

## 12. Testing

Primary commands:

```bash
php artisan route:list
php artisan test
```

Important feature suites include:

- `MunicipalitySeparationTest`
- `SuperAdminOperationalReadOnlyTest`
- `MunicipalHeadUserManagementTest`
- `OperationsDashboardTest`
- `PublicFarmerLandMapTest`
- `SuperAdminAuditTrailTest`
- `ProvincialVeterinaryAccessTest`
- `RiceSeedDistributionSheetTest`
- `ConcurrentWriteTest` (uses its own in-memory SQLite connection)

The current `phpunit.xml` does not configure a separate test database, and feature tests use `DatabaseTransactions`. Never run the suite while `.env` points to production. Configure a dedicated disposable test database first. Add a regression test whenever changing permissions, municipality scoping, route-model binding, public-map privacy, imports, exports, file access, or audit redaction.

The UI suites `SharedDesignPresentationTest`, `DashboardPresentationTest`, `FarmerWorkspacePresentationTest`, `OperationsPresentationTest`, and `SupportingWorkflowPresentationTest` use unsaved fixtures and an in-memory SQLite connection. They check rendering, visible role actions, form values, and version tokens; they do not replace database-backed authorization, persistence, or provider integration tests.

## 13. Rules for implementing changes

1. Inspect the working tree before editing and preserve unrelated/uncommitted work.
2. Add schema changes through migrations; never depend on manual production-only column edits.
3. Add new model fields to `$fillable`/casts only after validating request input and ownership.
4. Register a policy for every new protected model and authorize every controller action.
5. Apply municipality scope to the base query before deriving tables, lookups, KPIs, charts, exports, or pagination.
6. Validate cross-model municipality ownership on every create/update/assignment/import.
7. Use the same filtered query for on-screen reports and exports so totals cannot disagree.
8. Keep System Owner and Super Admin operational access read-only and keep Backup Folder unavailable to both roles unless the product owner explicitly changes the rule.
9. Keep public QR responses read-only and privacy-limited; do not expose contact, birth, eligibility, vaccination, distribution, or account data.
10. Keep secrets and passwords out of audit values and logs.
11. Use eager loading, grouped aggregates, pagination caps, and chunked exports for multi-user performance.
12. Use `ConcurrentWrite` for editable shared records, keep version tokens on every edit surface, and place related database changes in a short transaction; never hold a database transaction open during HTTP calls or long imports.
13. Preserve CSRF protection, throttling, validation, escaped Blade output, and safe CSV handling.
14. Use named routes and explicit resource parameter names when controller argument names are camel-cased.
15. Update navigation, dashboard metrics, audit-module labels, migrations, tests, and this file when adding a module.

## 14. Definition of done for a municipality-owned feature

A feature is not complete until all of the following are true:

- its table has `municipality_id`, indexes, and appropriate foreign keys;
- its model exposes the ownership relation and required fillable/cast fields;
- municipal users automatically write to their own municipality;
- provincial staff can explicitly choose an active municipality;
- lists, searches, statistics, charts, lookups, exports, and dashboards share the same scope;
- view/edit/update/delete/preview/download/stream/assignment actions authorize record ownership;
- super-admin read/write rules match the permission matrix;
- relevant changes appear in the audit trail without secrets;
- simultaneous edits cannot silently overwrite a newer record and retryable multi-row writes are transaction-safe;
- responsive empty, loading, error, and validation states exist in the UI;
- feature tests cover own municipality, foreign municipality, provincial access, and super-admin behavior;
- migrations work against a backed-up copy of the real schema;
- this `AGENTS.md` is updated if system behavior changed.

### Assistance beneficiary selector

The shared agriculture/fisheries create/edit form initializes its own beneficiary Tom Select, searches both FFRS and RSBSA identifiers, and limits displayed matches to 100 while searching the full loaded choice list. Form panels allow dropdown overflow. Provincial users choose a municipality before selecting beneficiaries; municipal accounts retain their server-scoped choices. Native select fallback and server authorization remain required.

### Local Baguio/Benguet account alignment

The local workspace was explicitly aligned with Hostinger on 2026-09-18: Baguio City has separate province supervision and is excluded from Benguet Super Admin access. The legacy Benguet office workspace (`BEN`) supports its existing municipal-head role and must not be treated as province-wide operational staff access. See `PROVINCE_SUPERVISION.md` for the local synchronization and verification. Do not automatically merge Baguio back into Benguet.

### GitHub deployment preference — September 21, 2026

The owner requires GitHub-based releases: review and commit the approved scope, push to `https://github.com/vrash12/agri`, then use `git pull --ff-only origin main` on Hostinger. Preserve unexplained server edits and unrelated local work; never force-push or use a hard reset as a shortcut. Take private backups, apply only explicitly reviewed migrations, mirror changed assets to both public directories, refresh caches and verify before returning online. Each production release still requires owner authorization. See `docs/GITHUB_DEPLOYMENT.md`. The September 21 geofence/checkbox release is authorized; farmer-ID changes remain outside its scope.

### Google base-map label controls

Farmers and Municipality geofences include a Map labels On/Off button, deployed September 21, 2026 in GitHub commit `a751355`. Use native 3D HYBRID/SATELLITE modes and 2D map types; retain AgriGOV overlay labels and all drawing state. The 2D native map-type selector must keep the button synchronized. No database write or inline style override is involved. See docs/MAP_LABEL_VISIBILITY.md for API references and deployment verification.

CALABARZON reference coverage was deployed to Hostinger through GitHub commit `496b706` on September 22: 142 municipality/city geofences across Batangas, Cavite, Laguna, Quezon, Rizal and the separate Lucena City scope. Run the explicit transaction-backed `CalabarzonBoundarySeeder` only after a verified backup; it preserves existing workspaces, styles and operational data and stops on conflicts. The explicit `region-access:configure --owner=<id> --region=region4a` command links the five provinces and separate Lucena City scope without changing other regions or issuing accounts. Three inactive provincial Super Admin accounts were later prepared for Cavite (`agriculture.cavite@yahoo.com`), Batangas (`agri@batangas.gov.ph`) and Laguna (`faesopaglaguna@gmail.com`); passwords must be set before activation and no invitation was sent. Source attribution, water-inclusive Cavinti and Noveleta area conventions, validation and verified deployment are documented in [docs/CALABARZON_BOUNDARY_SOURCES.md](docs/CALABARZON_BOUNDARY_SOURCES.md).

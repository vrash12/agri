# Agriculture Information System — Developer Guide

This file applies to the entire repository. It is both a functional map of the system and a set of implementation rules for developers and coding agents. Update it whenever a role, route, model, workflow, integration, or deployment requirement changes.

## 1. What this system is

This is a Laravel-based Agriculture Information System for the Provincial Agriculture Office and municipal agriculture offices in Tarlac. It centralizes:

- farmer registry and farmer identification cards;
- GIS farm-parcel mapping;
- seed, fertilizer, and other farm-input releases;
- anti-rabies vaccination records;
- farmers' cooperatives and membership;
- agricultural machinery inventory and maintenance monitoring;
- municipality-owned backup files;
- user and role management;
- province-wide dashboards and audit trails.

The application is multi-municipality. Operational records belong to one `municipality_id`, and municipal users must never see or mutate another municipality's records. Provincial users can work across municipalities, while the super administrator has province-wide read-only operational oversight and manages accounts/security.

## 2. Technology and important dependencies

- PHP `^8.0.2`
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

The only supported roles are constants in `App\Models\User`:

| Role | Operational visibility | Operational writes | User management | Backup Folder | Audit Trail |
| --- | --- | --- | --- | --- | --- |
| `super_admin` | All municipalities | No; read-only oversight | All non-protected account operations; own/super-admin protections apply | No access | Full access and CSV export |
| `provincial_staff` | All municipalities | Yes; must choose the municipality for new records | No | All municipalities, subject to policy | No |
| `municipal_head` | Assigned municipality only | Yes | May manage only `municipal_staff` in the same municipality | Assigned municipality only | No |
| `municipal_staff` | Assigned municipality only | Yes | No | Assigned municipality only | No |

All accounts must be active. Municipal roles also require an existing, active municipality. UI visibility is not security: controllers must still call policies for every protected action.

### Authentication workflow

1. A guest submits email, password, and optional “remember me” to `AuthController@login`.
2. Laravel attempts session authentication and regenerates the session ID after success.
3. The controller rejects unknown roles, inactive users, municipal users without a municipality, and users assigned to a missing/inactive municipality.
4. Successful and failed/blocked sign-ins are written to the audit trail when the `audit_logs` table is available.
5. `last_login_at` is updated, and every role is redirected to the municipality-aware dashboard.
6. Logout is audited, the session is invalidated, and the CSRF token is regenerated.

There is currently no user-facing registration, forgotten-password, email-verification, or password-reset workflow.

## 4. Municipality isolation — non-negotiable rules

The primary tenancy boundary is the numeric foreign key `municipality_id`, not the human-readable `farm_municipality` field.

- Use `App\Support\MunicipalityAccess` for operational queries, filters, allowed municipality choices, and write ownership.
- Municipal list queries must apply municipality scope before search, statistics, charts, pagination, lookups, or exports.
- Municipal writes derive `municipality_id` from the authenticated account. Do not trust a submitted municipality ID.
- Provincial staff must explicitly choose an active municipality for creates/imports when no owning record determines it.
- Related farmers, cooperatives, machinery holders, distributions, and plots must belong to the same municipality.
- Route-model-bound records must be authorized before view, edit, update, delete, download, preview, stream, save, assign, or export.
- Farm plots inherit municipality ownership through `farm_plots.farmer_id -> farmers.municipality_id`.
- Super admins may view operational records across the province but `User::canManageOperationalData()` deliberately prevents operational creates, imports, updates, and deletes.
- Super admins must not regain Backup Folder access. `BackupFilePolicy` explicitly blocks it even though super admins have province-wide scope elsewhere.
- Never rely on hidden buttons or navigation checks to enforce any of these rules.

Reusable authorization is implemented by:

- `App\Policies\Concerns\AuthorizesMunicipalityRecords`
- model-specific policies in `app/Policies`
- model-to-policy registration in `App\Providers\AuthServiceProvider`
- `App\Support\MunicipalityAccess`

When adding a new municipality-owned module, reuse these components instead of copying role comparisons into controllers.

## 5. Functional module catalog

### 5.1 Dashboard

Route: `GET /dashboard` (`dashboard`)

The dashboard builds role-scoped operational KPIs and recent activity:

- farmers, distributions, kilograms released, vaccinations, cooperatives, and machinery;
- total plots, mapped area, mapped/unmapped farmers, and mapping coverage;
- farmers missing FFRS or farm location;
- machinery availability and maintenance attention;
- current-month distributions and vaccinations;
- monthly kilogram release and top seed-variety charts;
- recent recipients, vaccinations, and parcel activity;
- backup totals/latest upload for non-super-admin users.

For super admins it also produces a province comparison without per-municipality N+1 queries. Each active municipality includes farmer, mapping, distribution, vaccination, cooperative, machinery, and staffing metrics. Municipalities are classified as operational, missing a head, without farmer records, or behind on mapping. It also counts operational records with no municipality.

Weather and agricultural advisories are intentionally embedded in the Parcel Map instead of being shown as a separate page or global-sidebar module. The map's Weather button lazy-loads a municipality-scoped drawer with current conditions, rainfall/wind indicators, advisories, a three-day outlook, refresh controls, and PAGASA links. Selecting a farmer switches the drawer to that farmer's municipality; municipal accounts remain locked to their assignment, while provincial staff and super admins may choose any active municipality.

### 5.2 Farmer registry

Primary model/table: `Farmer` / `farmers`

Routes: `farmers.*` plus the public `farmers.public-land` route.

Functions:

- list, search, filter, paginate, create, edit, and delete farmer profiles;
- filter by municipality for provincial users, gender, mapped/unmapped state, missing FFRS, and missing location;
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

- retrieve all visible parcels or one accessible farmer's parcels as JSON;
- draw and save polygon boundaries for the selected farmer;
- rename, recolor, reshape, and delete saved polygons;
- calculate centroid and approximate spherical area in hectares on the server;
- show saved parcels, mapping totals, fit/reset controls, and farmer details in the Google Maps workspace without placing centroid pins over parcel boundaries;
- identify a parcel's farmer, FFRS, location, name, and area in a compact hover card; clicking a polygon isolates that farmer's parcels until the user chooses the in-map All Parcels reset;
- for provincial and super-admin users, pass the selected municipality to the all-plots endpoint so the map never mixes parcel boundaries from other municipalities;
- import KML/KMZ in the browser for a selected farmer and save each parsed polygon through the authorized plot endpoint;
- bulk-import server-side KML/XML placemarks for one selected municipality;
- match server-side imports only against farmers in that municipality using parcel codes and progressively looser name/location strategies;
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

### 5.5 Seeds and farm inputs

Primary model/table: `RiceSeedDistribution` / `rice_seed_distributions`

The historical table name remains `rice_seed_distributions`, but the module now represents broader agricultural inputs.

Functions:

- create, edit, delete, search, filter, paginate, import, and CSV-export releases;
- link each release to a farmer and copy a farmer identity/location snapshot into the distribution record;
- support rice seed, corn seed, vegetable seed, fertilizer/abono, soil amendment, and other farm input categories;
- support quantities in kg, sacks, packs, grams, liters, milliliters, bottles, or pieces;
- allow a custom item/variety name and notes;
- retain NRP fields such as claimed area/seed, lot series, crop establishment, sowing label, harvested area, production bags, planted variety, and seed class;
- filter by text, identity fields, municipality, gender, category, eligibility flags, numeric ranges, and date ranges;
- display filtered KPIs and charts for monthly releases, item/category mix, locations, gender, age, eligibility, establishment method, yield variety, seed class, and area by municipality;
- import an `NRP DISTRIBUTION` Excel sheet, match farmers by FFRS/RSBSA inside the selected municipality, and update/create release rows;
- stream filtered CSV exports in chunks.

Only rows whose `quantity_unit` is empty or `kg` are included in kilogram totals. Do not add sacks, bottles, or liters directly to kilogram aggregates.

### 5.6 Anti-rabies vaccinations

Primary model/table: `AntiRabiesVaccination` / `anti_rabies_vaccinations`

Functions:

- create, edit, delete, search, filter, and paginate vaccination records;
- store owner name, barangay, birthday, pet type, breed, name, color, and vaccination date/year;
- validate `pet_type` as `Dog` or `Cat` and persist it through the model's fillable fields;
- look up an existing owner within one municipality and return owner details plus distinct prior pets;
- filter by municipality, owner/pet/breed text, barangay, pet type, and optional year;
- report total vaccinations, unique owners/pets, latest vaccination, current-month activity, and year/month/pet/barangay/breed/owner-age charts.

The owner lookup uses write-scope resolution because it populates an entry form; provincial staff must select a municipality before using it.

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
- assign each asset to either a farmer or a cooperative in the same municipality;
- enforce municipality-unique asset codes;
- track category, brand, model, serial number, acquisition year/date/source/cost, condition, availability, location, service hours, maintenance dates, and notes;
- report total/available/in-use assets, holder type, total acquisition value, category/condition charts, and a maintenance queue;
- mark equipment for attention when it is under maintenance, needs repair, is unserviceable, or has maintenance due within 30 days;
- provide a municipality-scoped JSON holder lookup for dynamic forms;
- audit CSV exports and normal model changes.

The current form requires a farmer or cooperative holder even though legacy/unassigned assets can still be listed and filtered.

### 5.9 Backup Folder

Primary model/table: `BackupFile` / `backup_files`

This is a protected file repository, not an automated database-backup scheduler.

Functions:

- upload one or more files up to 50 MB each to private local storage;
- assign every uploaded file to a municipality and record uploader, folder, notes, MIME type, size, and SHA-256 hash;
- search by filename/folder/notes/hash with contains, starts-with, ends-with, or exact modes;
- filter by municipality, folder, uploader, extension, date, and size;
- sort and display filtered file/folder/hash totals;
- authorize preview, inline streaming, download, edit, and deletion;
- preview PDFs, images, text, spreadsheets, and supported document formats in the browser;
- edit text-like files and `.xlsx` files in place, then recompute file size and SHA-256;
- physically delete the stored file when its database record is deleted.

Super admins are intentionally denied this module. Other authorized operational roles receive the normal province/municipality scope described above.

### 5.10 User management

Primary model/table: `User` / `users`

Routes: `admins.*`

Functions:

- list, search, filter, paginate, create, edit, activate/deactivate, change role, reset password, and delete accounts;
- hash every new or changed password with Laravel `Hash`;
- require at least eight characters and confirmation in account forms;
- require an active municipality for municipal roles and clear `municipality_id` for provincial roles;
- permit only one active municipal head per municipality;
- prevent self-deletion and deletion of a super-admin account through the controller;
- let municipal heads manage only municipal-staff accounts in their municipality;
- let super admins manage the broader account set.

Passwords are one-way hashes and cannot be retrieved. Developers may reset a password, but must never attempt to display existing passwords or store plaintext credentials.

### 5.11 Audit trail

Primary model/table: `AuditLog` / `audit_logs`

Super-admin-only functions:

- view activity totals, today's activity, seven-day activity, and security/deletion alerts;
- search/filter by event, module, municipality, actor, and date range;
- inspect request context and before/after values;
- export the same filtered scope to CSV using a stable maximum audit ID.

`AuditModelObserver` records created, updated, and deleted events for machinery, farmers, plots, distributions, vaccinations, cooperatives, backups, users, and municipalities. Authentication, exports, and cooperative membership changes add explicit events through `App\Support\AuditTrail`.

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
- follow the municipality of the currently selected map farmer and expose the drawer through the Parcel Map and dashboard quick action without navigating to another page.

The municipality coordinates in `config/weather.php` are town-center forecast reference points. They are not surveyed parcel coordinates. Open-Meteo guidance must never be presented as an official typhoon, rainfall, thunderstorm, or flood warning; PAGASA and local disaster-risk authorities remain the official sources. Forecast refreshes use an atomic cache lock and recheck the cache after waiting so simultaneous dashboard requests do not create an outbound-request stampede.

### 5.14 Concurrent-use and write synchronization

All authenticated state-changing routes run through `SynchronizeMutatingRequests`. It serializes mutations from the same account to protect session and flash state, then obtains a shared record lock so two different staff accounts cannot mutate the same route-bound model at the same time. Creates and imports without a bound model are grouped by municipality when possible. Lock timeouts return HTTP 409 for JSON requests and a retry message for normal forms.

Normal edit forms for farmers, input releases, vaccinations, cooperatives, machinery, user accounts, and cooperative membership carry an HMAC record-version token. `App\Support\ConcurrentWrite` reloads the row with `SELECT ... FOR UPDATE` inside a retried database transaction and rejects a stale token instead of silently overwriting another staff member's newer values. Farm-plot JSON updates/deletes and editable Backup Folder files use the same optimistic check. Immediate index-page deletions re-fetch and lock the current row before changing dependencies. Browser forms also suppress rapid duplicate submissions.

Create operations and multi-record mutations use retried transactions where the workflow is database-atomic. Large seed/input and machinery CSV exports use bounded chunks and a stable maximum ID rather than loading the complete result into PHP memory. Exported spreadsheet values are guarded against CSV formula injection.

The lock mechanism requires an atomic shared cache store. The file cache is suitable for one Hostinger server when every PHP worker sees the same filesystem. Use Redis (recommended) or another shared atomic-lock store before running more than one application server; otherwise locks on separate servers cannot coordinate.

## 6. Route inventory

As of 2026-08-24, `php artisan route:list --json` reports 77 routes protected by Laravel authentication, including the Sanctum endpoint:

| Area | Authenticated routes |
| --- | ---: |
| Farmers | 14 |
| Farmers' cooperatives | 9 |
| Seed and farm-input distribution | 9 |
| Machinery inventory | 8 |
| Vaccinations | 7 |
| Backup Folder | 7 |
| User management | 6 |
| Farm plots | 6 |
| Audit trail | 3 |
| Weather and agricultural advisories | 4 |
| Dashboard/logout/API | 4 |

Regenerate the inventory instead of preserving this number if routes change:

```bash
php artisan route:list
```

## 7. Main data relationships

```text
Municipality
├── users
├── farmers
│   ├── farm_plots
│   ├── rice_seed_distributions
│   ├── agricultural_machineries
│   └── farmers_cooperatives (many-to-many through cooperative_farmer)
├── rice_seed_distributions
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
- `app/Http/Middleware/SynchronizeMutatingRequests.php`: per-account and per-record/cache mutexes for state-changing requests
- `app/Observers/AuditModelObserver.php`: automatic model change logging
- `app/Providers/AuthServiceProvider.php`: model/policy registration
- `app/Providers/AppServiceProvider.php`: audit observers and custom pagination views
- `resources/views/layouts/app.blade.php`: shared shell, responsive navigation, and role-aware module links
- `resources/views/partials/operations-ui-styles.blade.php`: shared operational-module design system
- `resources/views/vendor/pagination`: application-wide pagination templates
- `resources/views/farmers/partials/maps-*`: authenticated plotting workspace CSS/JavaScript
- `database/migrations`: incremental schema changes; see the warning below
- `tests/Feature`: role, municipality isolation, dashboard, QR map, machinery, user-management, and audit coverage

`app/Models/FarmerPlot.php` is a duplicate legacy model for the same table. Active code uses `App\Models\FarmPlot`. Do not introduce new references to `FarmerPlot`; remove it only after confirming no external code depends on it.

`App\Http\Middleware\EnsureHeadAdmin` checks the obsolete `head_admin` role and is not used by current routes. Current authorization must go through policies and the four supported `User` role constants.

## 9. Database and migration warning

The repository does **not yet contain a complete migration history for a clean database**. The application began from an imported legacy schema. Core tables such as `municipalities`, `farmers`, `farm_plots`, `rice_seed_distributions`, `anti_rabies_vaccinations`, `farmers_cooperatives`, `cooperative_farmer`, and `backup_files` are not all created by repository migrations.

Consequences:

- Do not assume `php artisan migrate` on an empty database can build the application.
- Do not run migrations blindly against an imported database whose `migrations` table does not match its real schema; old “create users” migrations may appear pending even when `users` already exists.
- Obtain the approved, sanitized baseline SQL schema from the project owner, then reconcile migration history before onboarding a fresh environment.
- The long-term fix is to create and test a complete baseline migration set or squash the verified schema into a Laravel schema dump.

At the time this file was written, the local database had not yet applied:

- `2026_08_20_000100_add_input_details_to_rice_seed_distributions.php`, which adds `input_category`, `quantity_unit`, and `input_notes`;
- `2026_08_20_000200_create_agricultural_machineries_table.php`.

The code already queries those fields/tables. Apply the migrations to the intended environment after taking a database backup. A missing `quantity_unit` produces `SQLSTATE[42S22]`, and a missing machinery table prevents the dashboard and machinery module from loading.

The migration `2026_08_17_000000_backfill_rice_distribution_municipalities.php` fills missing distribution ownership from the linked farmer and intentionally does not erase that business ownership on rollback.

## 10. Environment, maps, and storage

Never commit `.env`, API keys, production database credentials, password lists, SQL dumps containing personal data, or real farmer documents.

Minimum application settings include:

```dotenv
APP_NAME="Agriculture Information System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

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
- `ConcurrentWriteTest` (uses its own in-memory SQLite connection)

The current `phpunit.xml` does not configure a separate test database, and feature tests use `DatabaseTransactions`. Never run the suite while `.env` points to production. Configure a dedicated disposable test database first. Add a regression test whenever changing permissions, municipality scoping, route-model binding, public-map privacy, imports, exports, file access, or audit redaction.

## 13. Rules for implementing changes

1. Inspect the working tree before editing and preserve unrelated/uncommitted work.
2. Add schema changes through migrations; never depend on manual production-only column edits.
3. Add new model fields to `$fillable`/casts only after validating request input and ownership.
4. Register a policy for every new protected model and authorize every controller action.
5. Apply municipality scope to the base query before deriving tables, lookups, KPIs, charts, exports, or pagination.
6. Validate cross-model municipality ownership on every create/update/assignment/import.
7. Use the same filtered query for on-screen reports and exports so totals cannot disagree.
8. Keep super-admin operational access read-only and keep Backup Folder unavailable to super admins unless the product owner explicitly changes the rule.
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

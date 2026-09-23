# AgriGOV — Claude Code project instructions

## Purpose

Act as the senior full-stack developer and software architect for AgriGOV.
Improve code quality, security, consistency, performance and maintainability
while preserving existing workflows, records and account assignments.

This file contains instructions and a prioritized work plan. It does not mean
the planned improvements have already been implemented, tested or deployed.
Work within the owner's current request; do not execute the entire backlog
automatically or turn a documentation request into application changes.

## Read first

Read [AGENTS.md](AGENTS.md) and the relevant sections of
[SYSTEM_FEATURES.md](SYSTEM_FEATURES.md) before changing the application. AGENTS.md
is the authoritative architecture, role, ownership and deployment guide. Keep
the guides synchronized when behavior changes. If guidance and implementation
disagree, inspect the code and explain the discrepancy before changing behavior.

For interface work, also read [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md),
[GREEN_YELLOW_THEME.md](GREEN_YELLOW_THEME.md) and
[DESIGN_IMPLEMENTATION.md](DESIGN_IMPLEMENTATION.md).

## Project context

- AgriGOV is a government agriculture operations system with real office users
  and municipality-owned records. Preserve existing records and account scopes.
- Laravel 9, PHP 8.1–8.3, MySQL/MariaDB, Blade and JavaScript. The repository pins
  Composer's PHP platform to 8.1. Use the installed dependencies and existing
  conventions; do not introduce a new framework for a small change.
- Vite builds the standard CSS/JS entry points. Many modules also use Blade
  assets and files under `public/`; a Vite build alone does not deploy those.
- Office authentication uses the `web` guard. Farmer portal accounts use the
  separate `farmer` guard. Never mix their identities or permissions.

The main modules are farmer records/cards and imported source rows, farm
parcels, geofences, agriculture/fisheries assistance, harvests, Animal Health,
cooperatives, machinery, backups, weather, dashboards, accounts and auditing.
Preserve compatibility with Google Maps, the authorized satellite proxy,
Nominatim, Open-Meteo, PhpSpreadsheet and the existing interface libraries.

## How to work

1. Check the current branch and `git status --short`. Preserve owner changes;
   never reset, clean, stash or reformat unrelated work to simplify a task.
2. Trace the affected route, middleware, controller, policy, support service,
   model, view and tests. Explain a small plan for changes across modules.
3. Implement the smallest complete change. Reuse existing domain services,
   design tokens and components. Keep controllers focused on HTTP concerns.
4. Test the behavior and important failure paths. Format only changed PHP files,
   inspect the final diff, and update the relevant documentation.
5. Report what changed, the checks actually run, remaining limitations and
   whether deployment occurred. Never describe local work as deployed.

Proceed with authorized reversible work. Ask only when an unresolved choice
changes permissions, ownership, privacy, cost or workflow. Pushing, production
writes, deployment and sending email require the owner's explicit authorization
for that work; reuse authorization already given within its scope.

## Architecture and role boundaries

Follow the existing responsibility flow:

```text
Blade / JavaScript -> routes and middleware -> controller / Form Request
    -> policy -> application/domain support service
    -> Eloquent and transaction -> audit/cache/storage/provider integration
```

| Role | Operational and administrative limits |
| --- | --- |
| System Owner | Global oversight, account/security and geofence administration; no routine operational writes or Backup Folder access |
| Regional Head | Assigned active region and separate city scopes; read-only operations and authorized lower-account management |
| Super Administrator | Assigned active province; read-only operations and scoped account/security/geofence administration |
| Provincial Agriculture Staff | Assigned active province; operational writes with an authorized municipality selected |
| Provincial Veterinary account | Assigned active province; Animal Health only |
| Municipal Head | Assigned active municipality; operations and management of its municipal staff |
| Municipal Staff | Assigned active municipality; operational work only |
| GIS Evaluator | Active administrative geofences only; expiring read-only access with first-use password requirements |

Use `app/Policies`, `MunicipalityAccess`, `ConcurrentWrite`, `AuditTrail` and
the existing domain services rather than duplicating their rules in controllers
or templates. New municipality-owned modules require foreign keys/indexes,
relationships, policies, scoped queries, related-record validation, audit events
and permission/isolation tests. Ownership names and report snapshots never
substitute for numeric ownership IDs.

## Security and ownership rules

- Scope queries through `App\Support\MunicipalityAccess` before filtering,
  aggregating, exporting or serializing data. Numeric ownership IDs govern
  access; names and report snapshots do not.
- Authorize route-bound records with policies before reading relationships,
  files or geometry. Validate related records within the same allowed scope.
  Hiding a button never substitutes for server authorization.
- Regional/provincial oversight roles are read-only for routine operational
  records. Preserve separate city scopes. Veterinary accounts remain Animal
  Health-only; evaluators remain limited, expiring, read-only map accounts.
- Use `ConcurrentWrite`, version tokens and the existing synchronization
  middleware for shared edits. Related writes need short transactions and
  ownership checks against the freshly locked record.
- Keep CSRF, request throttles, account-scope checks, session limits, password
  hashing, security headers and private-response cache controls intact.
- Do not put credentials, activation codes, tokens, private paths or personal
  data in source, logs, screenshots, tool output or replies. Use synthetic test
  data. Never retrieve existing plaintext passwords or commit `.env` files.
- Use `AuditTrail` for audit records and pass only approved structured context.
  Review its filtering rather than assuming it removes every sensitive value.
  Never copy request bodies, credentials or provider responses into descriptions
  or free text. Audit privacy improvements are listed below as pending work.
- Uploads need content/type/size validation and safe storage names. Downloads,
  previews and exports need independent authorization and safe response headers.
  Preserve formula-injection protection in spreadsheet/CSV exports.
- External requests need bounded timeouts and safe failure states. Keep secrets
  out of provider diagnostics. Use configuration rather than `env()` in app code.
- Escape untrusted HTML/JavaScript, bind SQL parameters and reject unsafe remote
  URLs or redirects. Use generated storage names and prevent path traversal.
- Keep farmer access issuance/recovery staff-assisted. Do not add birthday
  authentication, public account lookup or self-registration implicitly.
- Keep private responses non-storable and preserve the history-restoration
  guard. Do not weaken CSP or other protections merely to silence an error.

## Code quality and consistency

- Apply SOLID, DRY and KISS through clear responsibilities and practical reuse.
  Avoid speculative abstractions, new frameworks for small changes and blanket
  rewrites. Delete code only after checking routes, views, jobs and CLI consumers.
- Follow PSR-12 and Laravel Pint. Prefer explicit types, small cohesive methods,
  meaningful names and strict comparisons. Comment on reasons, not syntax.
- Put reusable rules in `app/Support` or the existing service layer; keep models
  focused on relationships, casts and model behavior. Use Form Requests for
  complex shared validation and existing policies for authorization.
- Prevent N+1 queries, select required fields, paginate listings, and bound or
  chunk imports, exports and geometry. Cache expensive reads with invalidation
  or a bounded lifetime. Avoid network requests inside locked transactions.
- Preserve public URLs, import compatibility, QR behavior and existing stored
  parcel colors. Farmer cards use imported parcel addresses joined by ` / `;
  residence addresses must not replace them.
- Keep errors understandable, forms recoverable, actions keyboard accessible
  and mobile layouts usable. Use the shared green/yellow theme and `<x-brand />`.
- Maintain fillable/guarded fields deliberately; never mass-assign a complete
  request. Use casts and constraints to protect important invariants.
- Farmers opens the registry and parcel map directly. Keep optional region,
  province and municipality filters scoped to the account and consistent across
  listings, totals, farmer lookup and parcel requests.
- Reuse design tokens, operations styles and form feedback. Provide loading,
  success, validation, empty, disabled and failure states; prevent duplicates
  and preserve safe input. Confirm destructive actions and explain restrictions.
- Handle expected failures clearly; report unexpected ones without exposing
  internals. Do not silently swallow exceptions outside documented best-effort
  behavior or weaken actions that require a durable audit record.

## Performance and growing geofence coverage

- Measure latency, query counts, payload size, vertices and browser rendering
  before choosing a new technology. Keep access controls intact while tuning.
- Scope before aggregating. Prevent N+1 queries, load required columns only and
  paginate or chunk work rather than loading every operational record.
- Bound map requests and load detailed geometry only when needed. Display
  simplification must preserve the stored geometry used for validation and area.
- Avoid rebuilding all polygons or fetching data on every hover. Reuse hover
  UI, update only affected features and limit expensive interaction handlers.
  Cancel or ignore stale requests.
- Cache expensive reads with invalidation or bounded expiry. Use atomic locks
  for provider refreshes and other stampede-prone operations.
- Consider spatial indexes, background workers or vector rendering only after
  identifying the bottleneck and confirming hosting support. Shared hosting
  needs a safe fallback when persistent queue workers are unavailable.
- Keep transactions short and network calls outside database locks. Multiple
  servers need shared atomic coordination and verified shared file storage.
- Record before/after measurements; shorter code alone is not a speed result.

## Validation commands

Run from the repository root with a supported PHP executable and Composer.
Confirm the actual PHP version; the developer machine may differ from Hostinger.

**Before running tests:** `phpunit.xml` does not currently enforce a separate
test database. Some suites create SQLite schemas, while older transaction-based
suites depend on the imported legacy schema. Confirm disposable isolation first.
A transaction is not permission to use an office database. Never run tests in a
production checkout or reuse production keys/data for fixtures.

```sh
git status --short
php -v
composer validate --no-check-publish
composer audit --locked
php vendor/phpunit/phpunit/phpunit --filter=RelevantTest
php vendor/laravel/pint/builds/pint path/to/changed.php
php vendor/laravel/pint/builds/pint --test path/to/changed.php
php -l path/to/changed.php
git diff --check
```

`RelevantTest` and `path/to/changed.php` are placeholders for affected tests and
files. Do not assume custom Composer test/lint commands exist; inspect the
current scripts. Test behavior, permissions, municipality isolation, validation,
concurrent edits and important failure paths rather than mirroring the code.

Run relevant `tests/JavaScript/*.test.cjs` files with `node --test`. Compile Blade
using `php artisan view:cache` and inspect `php artisan route:list` when those
parts change, only in a safe local environment. Build Vite when its entry points
change and use browser checks for interaction/layout changes.

Before dependency work, inspect whether `vendor` is linked to another checkout;
install and verify separately if it is. Do not alter another project's libraries.
Use explicit changed-file paths for Pint if its automatic discovery inspects a
linked checkout, and verify that files were actually checked.

Report exact results and omissions. Focused tests do not establish a full-suite
pass, and static inspection does not establish browser/provider behavior.

## Prioritized improvement work — instructions, not completed changes

Revalidate these findings before implementation. Handle each as a focused,
reviewable change within the owner's requested scope, with regression tests and
updated documentation. Do not start the entire list automatically.

### 1. Safe, repeatable automated checks

- Add a bootstrap that selects disposable SQLite and in-memory cache/session/
  mail, with a random process-only application key, before application providers
  boot. Reject cached operational configuration and inherited database URLs.
- Test the guard with deliberately unsafe synthetic environment settings.
- Make legacy tests self-contained using a reviewed synthetic baseline. MySQL
  backup/restore checks need a separately isolated MySQL environment.
- Add documented, reliable test and changed-file formatting commands once the
  underlying checks work. Never bypass isolation to make a test pass.

### 2. Consistent audit privacy

- `AuditTrail::cleanValues` and its nested helper currently apply different
  exclusions. Use one policy at every depth, including array-convertible objects;
  prevent unsupported serialization from bypassing that policy.
- Review activation codes/hashes, API keys, authorization/cookie data, birth
  dates, contact numbers and protected paths alongside password/token fields.
- Preserve protected-only events by recording changed field names without
  their values. Keep existing actor and geographic ownership rules.
- Office audit URLs currently retain query strings. Protect them consistently
  instead of limiting query-string omission to farmer portal routes.
- Verify persisted events, nested data, sensitive-only edits and isolation.
  Historical audit cleanup requires separate owner authorization.

### 3. Dependency security and framework compatibility

A read-only Composer audit on September 24, 2026 reported **41 advisory records
across 11 packages** in the existing lockfile. These are feed entries, not 41
independently confirmed exploits; some entries describe the same issue. Rerun
the audit because advisories and available patches change.

- Review compatible Guzzle/PSR-7, CommonMark and Symfony updates and their
  dependencies. Test authentication, HTTP handling, uploads, exports, maps and
  external-provider failures against the resulting lockfile.
- Laravel 9 remains flagged. Plan a supported framework upgrade with PHP,
  Sanctum, validation, middleware and dependency compatibility checks. Updating
  supporting libraries alone does not resolve framework advisories.
- Review the maintainer's [email validation advisory](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq),
  [temporary signed URL advisory](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp)
  and [wildcard file validation advisory](https://github.com/laravel/framework/security/advisories/GHSA-78fx-h6xr-vch4).
  Assess the affected application paths and use the published patched versions.
- Do not suppress advisories, ignore platform requirements or update dependencies
  directly in production. This documentation task applies no dependency changes.

### 4. Module quality, consistency and performance

- Consolidate duplicated validation, ownership checks, formatting and business
  rules using the existing services and components.
- Add missing permission/isolation coverage before structural refactoring.
- Standardize forms, errors, labels and loading states within the touched module
  rather than restyling unrelated screens.
- Profile growing geofence data and fix the demonstrated query, payload or
  rendering bottleneck. Record measurable improvement and remaining limits.

## Database and release boundaries

The repository has an incomplete legacy migration baseline. Never use a blanket
`migrate`, `migrate:fresh`, reset, restore or `DatabaseSeeder` to repair a working
database. Build explicit disposable schemas for tests; production migrations
must be individually reviewed against the actual baseline.

Reference geofence seeders are explicit, atomic/idempotent imports. Keep source
checksums, provenance, geometry validation and independent city scopes intact.
They must not create accounts or operational demo records implicitly, or run as
part of ordinary deployments. Planning references are not survey-grade borders.

Consult the relevant `docs/*BOUNDARY_SOURCES.md` file. Preserve valid MultiPolygon
point contacts while rejecting shared lines and interior overlaps. Naga City
and Puerto Princesa retain their separate supervising scopes. Region grouping
does not implicitly issue accounts or alter unrelated default scope settings.

For an authorized release, follow [docs/GITHUB_DEPLOYMENT.md](docs/GITHUB_DEPLOYMENT.md):
verify a private backup, push the reviewed commit to GitHub, then pull it on
Hostinger with `git pull --ff-only`. Apply only approved migrations/seeders,
mirror changed public assets to both public directories, refresh the appropriate
caches and verify sign-ins, scope isolation and data preservation. Never bypass
GitHub by copying source directly into production.

Install reviewed locked dependencies with the documented production options
when needed; do not run `composer update` on the server. Confirm the deployed
commit, keep backups outside the web root and avoid destructive asset syncing.

## Definition of done

For an implementation request, the workflow must work through interface and
persistence; permissions, municipality isolation, validation, privacy and stale
write protection must hold; payloads/queries must remain reasonable; meaningful
checks must pass; and documentation must match. Preserve unrelated changes and
state what was not tested or deployed. For a documentation-only request,
completion means accurate, usable guidance—not application changes.

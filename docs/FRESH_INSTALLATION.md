# Reproducible fresh installation

Status: implemented locally October 24, 2026; not deployed. This guide supersedes the former requirement to obtain a legacy SQL baseline for a **new empty installation**. It does not authorize changes to any existing database.

## Runtime and prerequisites

- Use PHP compatible with the committed Composer lock: PHP 8.3 or newer, Laravel 12.69.3, Sanctum 4 and Laravel AI SDK 1.2.0. Run `php -v` and `composer check-platform-reqs`; CLI and web PHP must agree. PHP 8.1/8.2 and Laravel 9 instructions in older release receipts are historical.
- Install Composer dependencies from `composer.lock`, and frontend dependencies from `package-lock.json` with `npm ci`. PHP needs the extensions required by Composer, including PDO MySQL, XML/DOM, mbstring, fileinfo, GD and ZIP for the enabled workflows. SQLite is required for the isolated test suite.
- Create an empty, dedicated MySQL/MariaDB database and an application-scoped database user. The runtime user must have the permissions needed for approved migrations; never borrow another application's database. SQLite is used for automated isolation tests, not certified here as a replacement for production MySQL/MariaDB.
- Make `storage` and `bootstrap/cache` writable by PHP. The web document root is `public/`; `.env`, source, private uploads and backups must remain outside public access.

## Install an empty application

1. Check out the reviewed release and run `composer install` (production uses `--no-dev --optimize-autoloader`). Do not use `composer update` to reproduce a release.
2. Copy `.env.example` to `.env` and edit privately. Set a unique database, `APP_URL`, application environment and session settings. For local HTTP only, use `SESSION_SECURE_COOKIE=false`; HTTPS environments require `true`. Keep `SESSION_DOMAIN` blank. Start with AI/Sentinel disabled and provider keys blank.
3. Run `php artisan key:generate` **once on this new installation**, then `php artisan migrate`. No SQL dump, operational data or secret file is needed. No accounts, provinces, municipalities, farmer data, geofences or published LGU websites are seeded.
4. Run `npm ci` and `npm run build`. Commit neither dependencies nor generated private files. Existing Blade modules also serve versioned assets directly from `public/`.
5. In an interactive terminal run `php artisan system:bootstrap-owner`. Optional `--name` and `--email` supply nonsecret identity fields. The command asks twice for a hidden 15–72 character password, limited to 72 bytes. Never put the password in shell arguments, a file in the repository or deployment logs.
6. Sign in through `/login`, acknowledge the testing notice and verify the System Owner dashboard. The command creates one active, geographically unassigned owner and a required audit receipt. It refuses a second office account, an unknown/unmarked installation and noninteractive use. It is not an account recovery or privilege escalation command.
7. Configure approved geography through the existing explicit geography/import workflows, then office accounts and LGU profiles. Review named boundary seeders and their source documentation before explicitly using one. `DatabaseSeeder` intentionally does not provision the system. Never guess office ownership, issue sample farmer accounts or publish a site during installation.

## Optional integrations and operations

Maps need the configured Google Maps API key/Map ID and provider restrictions; Static Maps exports need the server key. AI needs an approved private key, disclosure, allowance settings and acceptance before `ASSISTANT_ENABLED=true`. Sentinel is separately opt-in. Without these, installation and permitted record workflows remain available; provider behavior is not verified merely by saving settings.

LGU sites work centrally under `/lgus/{slug}`. Subdomains additionally need provider DNS, a shared restricted entry point and HTTPS for each host, plus `LGU_WEBSITE_DOMAIN`. App code alone cannot issue Hostinger virtual hosts or certificates. See `LGU_PUBLIC_WEBSITES.md` and `LGU_SUBDOMAIN_HOSTING_2026_10_21.md`.

On a single host use the same atomic file cache for all PHP workers. Horizontal scaling requires shared coordination/storage. Configure Laravel's scheduler explicitly, verify `php artisan schedule:list`, take a test `db:backup` and verify off-machine retention/restore. Migrations reproduce schema; they cannot restore lost records, account hashes, encryption keys or private files.

For an authorized production installation set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS/secure sessions, and correct private provider settings before rebuilding `config:cache`, `route:cache` and `view:cache`. Do not use a general cache clear on an operating system; it resets provider allowance counters. See `GITHUB_DEPLOYMENT.md` for Hostinger's separate served-public asset copies.

## Baseline ownership and existing installations

`2014_10_12_000100_create_legacy_application_tables.php` fills the eight missing original tables and original user fields before the later additive migrations. Its marker table, `application_installation_baselines`, contains `legacy_application_v1` only on a database whose core tables this migration created. New users default inactive with an ordinary municipal role; no identity is implicitly privileged.

If all eight legacy core tables exist and no marker exists, baseline `up()` and `down()` are no-ops. Existing defaults, indexes, constraints and rows are not standardized or repaired. Fresh schema has explicit ownership constraints and a unique cooperative membership pair; adoption does not force those onto old data. Partial/unrelated databases, preexisting accounts in an otherwise blank schema, and interrupted marked baselines fail with review guidance. MySQL metadata checks explicitly inspect the selected database, not every schema visible to the DB user.

MySQL DDL is not transactional. If a **new disposable** install fails partway, retain the error and inspect the cause; do not remove the marker to bypass the guard. After confirming it contains no needed data, an operator can recreate that specifically identified disposable database and retry. Never apply this recovery to production or an imported installation.

On an existing deployment, first verify backup/restore, actual schema and migration receipts. Do not run the fresh-install recipe or `migrate:fresh`/`migrate:reset` there. The baseline cannot prevent a preceding pending `create_users` migration from colliding with an imported users table. Reconcile that history explicitly rather than inventing receipts automatically. A future release may register only the reviewed baseline no-op by path after checking all eight core tables; no production migration ran during this work.

## Verification evidence

- Nine isolated installation tests cover complete schema, repeat migration, rollback/reinstall, legacy adoption preservation, partial/unrelated refusal, existing accounts, owner bootstrap/authentication/audit, password validation and repeated-bootstrap refusal.
- A newly created, task-specific local MariaDB 10.4.32 database completed all 33 migrations: 30 tables and zero office/farmer/geography rows. Repeat migration, full reset and reinstall passed. A synthetic staff login/dashboard and registry sections then worked on that schema. No production schema or rows were used.
- Rollback fixes remove dependent foreign keys before indexes on MariaDB and explicitly remove SQLite indexes before dropping their columns. Named-FK removal uses SQLite's supported column form where required.
- Synthetic MariaDB backup/restore matched all 30 tables and 82 rows in a different newly created database. Literal farmer-search escaping and the native age calculation were checked on MariaDB. SQLite transactional fixtures provide MySQL `MONTH`, `YEAR` and `FIELD` functions; `ReportDateSql` keeps native MySQL age SQL and supplies a tested SQLite expression. MySQL-only backup tests are skipped under SQLite rather than reported as passing there. See [acceptance results](ACCEPTANCE_2026_10_24.md) for the initial full run, corrections and focused reruns.
- Local PHP is 8.4.10. PHP 8.3 production dependency compatibility comes from the lock/platform requirements and earlier deployment receipts; this work did not repeat a new physical PHP 8.3 server installation or external-provider acceptance.

Run the complete PHP/JavaScript suite and the acceptance checklist for a release candidate. Transactional legacy tests now migrate an in-memory database before beginning transactions, and refuse persistent database connections. Real devices, screen-reader audio, staff approval, provider billing/credentials and disaster recovery remain separate evidence, never implied by a successful schema build.

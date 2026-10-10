# Laravel 12 and AI SDK upgrade

The [RAG follow-up](ASSISTANT_RAG.md) uses this same installed
SDK and dependency lockfile. Reviewed local workflow retrieval, strict response
schema/source checks and a versioned help cache add no dependency or schema.
Provider context still excludes raw chat and records. The dependency upgrade,
unified Assistant and RAG were deployed together.

Status: deployed on Hostinger on October 11, 2026 in runtime `7d36fff`, after a
GitHub push, fast-forward pull and locked Composer install. CLI and web PHP are
8.3.35; Laravel is 12.69.3 and Laravel AI SDK is 1.2.0. Pending digital-ID changes
remain local. Existing schema, operational records and accounts were preserved;
nine approved AI environment settings were provisioned privately. See the
[deployment receipt](GITHUB_DEPLOYMENT.md).

The Office Assistant follow-up now uses this foundation for one conversation
inside normal authenticated office pages, canonical AI help and local policy-
scoped records/follow-ups. No raw chat or operational data goes to the provider.
The current checkout has 144 routes. The configuration-only statements and
143-route checks below describe the initial dependency upgrade stage, before
Assistant. See [OFFICE_ASSISTANT.md](OFFICE_ASSISTANT.md) for the 194-test combined
regression, actual normal-login/MySQL checks and remaining limitations.

The deployed unified feature adds bounded
reference encryption, Assistant-only concurrency and pre-work session commit
without late session or CSRF-cookie overwrites. No conversation table, vector database,
streaming or worker is required. The previously outstanding 19 legacy-schema
classes now pass against a disposable synthetic MariaDB database. Hostinger
runtime/schema preflight, backups and release smoke checks also passed for this
release, as recorded below. See
[AI_ASSISTANT_IMPLEMENTATION_PLAN.md](AI_ASSISTANT_IMPLEMENTATION_PLAN.md).

## Implementation plan

1. Preserve pending farmer-card changes and record the existing test baseline.
2. Resolve Laravel 12, Sanctum 4, Laravel AI SDK 1 and compatible development
   dependencies against PHP 8.3. Isolate this checkout's vendor directory from
   the other checkout's existing Laravel 9 dependencies.
3. Keep the existing application/bootstrap structure, middleware, routes,
   policies, guards, storage paths and database schema. Apply compatibility
   changes identified in the Laravel 10, 11 and 12 upgrade guides.
4. Publish the AI configuration only, without conversation-table migrations or
   an AI endpoint. The owner's October 11 follow-up authorized private OpenAI
   configuration and a read-only authentication check. Verify the SDK with fakes;
   do not send farmer records to a provider.
5. Update PHPUnit configuration and tests as needed; compare operational tests
   with the pre-upgrade baseline. Check dependencies, security advisories,
   routes, views, JavaScript and PHP syntax. Document remaining limitations and
   the production installation/rollback procedure.

## Primary references

- [Laravel 10 upgrade](https://laravel.com/framework/docs/10.x/upgrade)
- [Laravel 11 upgrade](https://laravel.com/framework/docs/11.x/upgrade)
- [Laravel 12 upgrade](https://laravel.com/framework/docs/12.x/upgrade)
- [Laravel AI SDK](https://laravel.com/framework/docs/12.x/ai-sdk)
- [AI SDK requirements](https://github.com/laravel/ai/blob/1.x/composer.json)

## Installed versions and compatibility

| Component | Local locked version |
| --- | --- |
| Laravel | 12.69.3 |
| Laravel AI SDK | 1.2.0 |
| Sanctum | 4.3.3 |
| PhpSpreadsheet | 2.4.8 |
| Endroid QR Code | 4.8.5 |
| PHPUnit | 11.5.57 |
| Composer PHP platform | 8.3.0 |

The existing bootstrap and kernels remain in use. Routes, policies, tenancy,
separate office/farmer guards, UTC storage and private filesystem roots are
unchanged. Sanctum's configuration now uses its version-4 middleware names.
Automatic password rehashing on login is disabled to preserve explicit account
updates and avoid introducing unaudited writes during this upgrade.

Redis, Memcached and DynamoDB store prefixes explicitly retain Laravel 9's
trailing separator. The global cache prefix and database/file behavior are
unchanged. Do not append another separator to the environment value. Existing
file-cache payloads and NDVI counters can be read and incremented without a
general cache clear.

The old province/region migrations now remove constrained foreign IDs during
SQLite rollback, which Laravel 12 supports. Their MySQL/MariaDB rollback behavior
and forward migrations are unchanged. These source edits do not require rerunning
already-applied migrations. No migration was executed against an office database.

This checkout now has its own ignored vendor directory. The prior vendor junction
was preserved privately; the other checkout's Laravel 9 dependencies were not
updated. Composer dependencies are reproducible from the reviewed lockfile.

## AI configuration and privacy

`config/ai.php` is published, with `AI_PROVIDER` and `AI_CACHE_STORE` environment
settings. Embedding caching remains off and defaults to the existing file cache
if enabled later, rather than requiring an uninstalled database-cache table.
The authorized OpenAI key is kept only in private environment configuration,
including the ignored local `.env` and Hostinger environment. Local access is
restricted to the owner, administrators and SYSTEM. It is absent from source,
examples, test fixtures and this receipt. Never display or commit it.

During the foundation stage, the read-only OpenAI models endpoint returned HTTP 200. No inference request,
farmer identity, assistance record or parcel coordinate was sent. OpenAI response
storage defaults off through `OPENAI_STORE=false`; this setting is not a claim
of universal zero data retention. See [OpenAI authentication guidance](https://developers.openai.com/api/reference/overview#authentication).

Installing the SDK alone did not add an AI report button, chat or automatic advice.
No conversation tables, provider tools, document uploads or background AI jobs
are enabled. Any subsequent feature needs server-side authorization and scope,
approved context, limits, timeouts, usage accounting and a useful failure state.
Do not forward raw operational records to a model by default.

## Verification

### Historical local upgrade checks

- On official Windows PHP 8.3.35, matching the reported Hostinger PHP version:
  **514 isolated PHP tests / 8,047 assertions passed**. These include SDK fakes,
  structured output, legacy cache payloads, authentication, scope, portal/card
  rendering, provider budgets, crop/map controls, imports and rollback checks.
- Another **13 Baguio boundary-seeder tests / 83 assertions passed**. They are
  outside the isolated subset above.
- **177 JavaScript tests passed**, including actual NDVI pixel-script parity,
  drawing, crop badges, maps and ID/address behavior.
- Composer validation, optimized autoload generation, PHP 8.3 platform checks
  and locked-dependency audit passed; no reported security advisories.
- All 26 changed/new PHP files passed syntax checks. Changed PHP files passed
  Pint. Blade compilation and 143 routes passed verification, with no duplicate
  route names. Whitespace checks and the added-secret-pattern check passed.
- The original pending card patch still passes reverse-application checks for
  its controller, artwork, stylesheet, view and tests, proving preservation.

The pre-upgrade operational run on PHP 8.4.10 contained 656 tests: 512 passed,
142 errored due to the absent legacy schema, and two failed. One failure was the
backup command's unsupported empty SQLite database; the other was an outdated
municipality-label assertion. The label assertion now checks the actual scope
shown while retaining its foreign-record denial. The NDVI JavaScript export
helper was also updated for the existing request-budget constructor argument.
Both upgrade-induced SQLite rollback failures were corrected and verified.

The original 514-test subset excluded all boundary-seeder classes and 19 older
legacy-schema classes (146 tests). The full boundary-seeder run was stopped
during lengthy reference processing. This receipt does **not** claim the full
boundary-seeder suite passed.

The October 11 release checks subsequently cleared all **19 legacy-schema
classes / 146 tests / 974 assertions** on PHP **8.3.35** and MariaDB **10.4.32**
through Laravel's `mysql` driver, with no failures, errors or risky tests. A new
disposable local database contained schema cloned from the isolated synthetic
integration database; **no source rows were copied**. One synthetic province
exercised backup INSERT escaping. The application backup/create/verify/restore
check also passed for **20 tables / 3 synthetic rows**, with exact table-definition
and row-count matches. No runtime fix, office-data operation or migration was
needed. These checks cleared the local legacy-schema gate before the separate
Hostinger runtime/schema preflight and live release checks.

The verified legacy classes were:
`DataQualityRulesTest`, `DatabaseBackupTest`, `DuplicateClaimCheckTest`,
`ExportSafetyTest`, `FarmerPickerTest`, `HarvestFromReleaseTest`,
`HarvestRecordModuleTest`, `ImportUploadLimitsTest`, `MapPayloadBoundsTest`,
`MunicipalHeadUserManagementTest`, `MunicipalitySeparationTest`,
`OperationsDashboardTest`, `ProvincialVeterinaryAccessTest`,
`PublicFarmerLandMapTest`, `RiceSeedDistributionSheetTest`,
`SessionIdleTimeoutTest`, `SuperAdminAuditTrailTest`,
`SuperAdminOperationalReadOnlyTest`, `WeatherAdvisoryAccessTest`.

The repository's default PHPUnit 11 configuration forces testing and in-memory
SQLite and blanks the real OpenAI key. The private legacy-check configuration
instead forced only the new disposable MariaDB database, unique cache/compiled-
view paths, array sessions/cache and an empty OpenAI key.
`CreatesApplication` loads only an optional `.env.testing`, never the office
`.env`, and supplies a random fixture encryption key. Clear stale compiled Blade
views before verification after a framework change; old compiled artifacts caused
empty test renders until refreshed. Do not use a cached production configuration
for tests or remove the database isolation to make legacy tests run.

### October 11 live release acceptance

Runtime `7d36fff` was installed through GitHub main, Hostinger fast-forward pull
and `composer install` from the reviewed lockfile. CLI/web PHP 8.3.35, Laravel
12.69.3 and AI SDK 1.2.0 were verified. The private pre-release database backup
was verified for **21 tables / 9,366 rows**. Schema and pre-existing row hashes,
application key, session/cache/Redis namespaces and existing NDVI counters were
preserved. Only nine approved AI environment settings were provisioned; the
whole environment is not claimed unchanged. No migration, seeder, account change
or general cache clear ran. Pending card changes were excluded.

Read-only live CLI checks passed for seven office-role policies, one excluded
account, ten general-help topics, three veterinary-help topics, six report
paths, two page renders and one card render. Five public HTTPS checks returned
200; Assistant CSS/JavaScript matched the source in both public directories with
0644 permissions. A canonical crop-help generation succeeded with two sources
and **834 input / 173 output tokens**; its accepted cache hit used zero new
provider tokens. These are server/public-HTTP smoke checks, not a load test or
an interactive sign-in test for every account. Details and rollback evidence are
in [GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md).

## Deployment and rollback requirements

The completed release followed these controls. Retain them for future releases.

1. Verify the Hostinger CLI and web runtime are PHP 8.3+ with required extensions,
   and confirm live schema/runtime compatibility. The disposable synthetic
   MariaDB integration and backup/restore prerequisite above is complete.
   Do not point automated tests at production. Repeat live preflight and release
   smoke checks for each subsequent production release.
2. On explicit release authorization, take verified private database,
   environment, served-asset and existing-vendor backups. Preserve session cookie,
   cache/Redis prefixes, budget values and file paths. Record the old runtime.
3. Commit and push the reviewed scope to GitHub, then use Hostinger
   `git pull --ff-only origin main`. Install the exact lockfile with
   `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
   Never run Composer update on Hostinger. Installing the framework dependencies
   is required; a Git pull alone is insufficient for this release.
4. No new migration or seeder is required. Do not publish AI migrations or run a
   blanket migration/rollback. Provision an approved provider key privately only
   when AI is intended on that environment. Keep response storage off by default.
5. Refresh configuration, route and compiled-view caches without clearing the
   general cache. Mirror any separately approved public card assets to both
   Hostinger public directories with readable 0644 permissions.
6. Verify old-row fingerprints, cache counters, sign-in for every role/guard,
   isolation, imports/exports, cards, maps, private downloads and public QR pages
   before leaving maintenance. Test paid provider actions only with approved,
   non-sensitive context.

If verification fails, keep maintenance enabled and restore the recorded prior
runtime, its vendor directory and approved environment/assets together. No
database rollback is needed for this dependency upgrade. Preserve the private
operational database and cache; do not use a hard reset over unexplained edits.

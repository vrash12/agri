# GitHub-to-Hostinger deployment

## Verified Hostinger release — October 12, 2026 — Ramos white parcel base and whole-map crop icons

Runtime `5bd80d5c545664f9e50720ef0b42e7465d8431d` was pushed to GitHub main and
installed with Hostinger `git pull --ff-only origin main` from `fa9ee17`, on PHP
8.3.35. The Ramos Farmers workspace now presents imported parcel polygons with
a neutral white display base while preserving stored plot colors, geometry and
exports. Saved crop classifications still load across the whole plotted map in
scoped batches, and crop icons/selected crop sections remain above the white
base. Crop enclosures reject positive overlap while allowing shared edges; no
record rewrite or hectare inference was introduced.

A private verified backup was created before the pull: **21 tables / 9,391
rows**, **3.2 MB compressed**, SHA-256
`509e8974ef13e25575b3de246114e7b827341b2b95805d6d80269acec2288117` in
`/home/u330835917/.private/crop-white-202610120145`. Existing untracked server
files were retained. No migration, dependency, account, environment or
operational data change ran, and the general cache was not cleared. The three
changed public JavaScript assets were mirrored to `public_html` with 0644
permissions; source, public copies and live HTTPS responses have matching
hashes. Configuration, route and Blade caches were rebuilt.

Local verification passed **235 JavaScript tests** and **22 crop-season PHP
tests / 185 assertions** with the existing `.env.testing` file warning. Pint,
JavaScript/PHP syntax, Blade compilation and whitespace checks passed. Login and
farmer-login returned HTTP 200 after deployment; the protected crop endpoint
redirected an unauthenticated request to sign-in. Live Google Maps visual
acceptance remains outstanding.

## Verified Hostinger release — October 12, 2026 — crop lines and automatic overview icons

Runtime `89ff3d1af921d588f91840c20ebec2c5235e328c` was pushed to GitHub main
and installed with Hostinger `git pull --ff-only origin main` from clean tracked
baseline `9e0fe80`, on PHP 8.3.35. Staff can save visual crop lines with two or
more points without hectare calculations. Existing polygon crop records remain
compatible. The Farmers map automatically loads scoped crop metadata in 200-ID
batches and shows cached crop badges without a farmer/parcel click. Recorded
crops follow the selected year/season; missing records produce no icon.

A private verified backup contains **21 tables / 9,384 rows**, **3,375,241
compressed bytes**, SHA-256
`34facfc32c2103f80178866032aa26bf351e176be8de6e89e87505c04775d03a`.
Prior tracked runtime, bootstrap caches, served assets and private environment
were archived outside the document root. All table-row/schema fingerprints,
environment bytes, application key and effective session/cache/Redis namespaces
passed preservation checks before and after the smoke checks. No migration,
dependency change, account operation, operational write or general cache clear
ran. Existing untracked server files were retained.

All five changed scripts were mirrored with 0644 permissions and match both
public directories and live versioned HTTPS responses. Configuration, route and
Blade caches rebuilt successfully. Homepage, office/farmer sign-in return 200;
guest classification and crop-detail endpoints return 401. Read-only server
checks covered synthetic line preparation without hectares, an authorized
office crop-modal render and scoped MySQL classification without coordinates.
Maintenance ended and tracked server files are clean.

Local acceptance passed 228 JavaScript tests, 25 crop/geometry tests / 177
assertions and 35 RAG tests / 246 assertions with existing file-cache warnings.
Pint, syntax, Blade and whitespace checks passed. Live Google Maps icon visuals,
real drawing/saving and every role's interactive workflow were not repeated.

The private verifier initially reused the backup's native read snapshot, then
selected an office without plotted parcels and lacked HTTP view context. It was
corrected to use a fresh connection, an authorized plotted scope and the usual
request/view context; preservation and smoke checks then passed. These were
verification-helper issues and required no runtime patch or data change.

## Verified Hostinger release — October 12, 2026 — context-aware Assistant and farmer workflow updates

Runtime `d89aa107e7f5c27d4603a0c598c5cde514a8d636` was pushed to GitHub main
and installed with Hostinger `git pull --ff-only origin main` from `2531ae1`.
The release includes the bounded Assistant identity/page/account awareness,
guided follow-ups, local data-quality review, pending-state UX, crop-editor
correction, Ramos farmer-card seal/signature artwork and their tests/docs.

A private verified backup was created before the pull: 21 tables / 9,382 rows,
3.2 MB compressed, SHA-256
`0a9dd0be95af3248e44b3f79be1d2854fdbc0cf524955d77e317f2130b5aba72`.
Existing untracked server recovery files were preserved. No migration,
dependency version change, environment change, account operation or operational
data write ran. Optimized Composer autoloading and configuration, route and
Blade caches were refreshed; the general cache was not cleared.

Changed public assets were mirrored to `public_html` with readable permissions;
source and served hashes match. Login and all changed CSS, JavaScript, SVG and
Ramos seal URLs returned HTTP 200. Composer reported the pre-existing untracked
`app/Models/FarmerPlot.php` PSR-4 filename mismatch; that server file was left
untouched and is outside this release.

Focused verification passed the Assistant conversation/identity/resolver suite
(194 tests / 2,727 assertions), records/context/report suite (99 tests / 481
assertions), and 45 JavaScript tests. Pint and whitespace checks passed. The
file-cache notices in the PHP suites are existing test warnings, not failures.

## Pending Assistant workflow follow-up — October 11, 2026

Local and uncommitted. Include `AssistantNextSteps`, conversation/context/resolver,
reviewed help/guide, tests and both public Assistant assets with the reliability
scope below. After release authorization, push to GitHub and fast-forward pull on
Hostinger; mirror assets to both public directories with 0644 permissions. Verify
editable follow-ups, scope/year choices, expired references and veterinary limits.
No migration, dependency, environment/account operation or general cache clear.
Preserve unrelated farmer-card work. Production remains at runtime `82a24c4`.

## Pending Assistant reliability follow-up — October 11, 2026

This change is local and uncommitted; production remains at the receipt below.
After owner release authorization, push the reviewed scope to GitHub, then pull
with `git pull --ff-only origin main` on Hostinger. Include `AssistantReportRequest`,
the resolver/context/orchestration changes, reviewed Assistant-help passages and
index, Assistant partial, CSS and JavaScript. Mirror assets to both public
directories with 0644 permissions, refresh autoloading and compiled views, and
verify help-language continuity, filtered-report clarification, 429 waits and
permissions. No migration, dependency, environment or account operation is needed.
Keep namespaces and budgets intact; never clear the general cache. Preserve
unrelated pending farmer-card work. See [Office Assistant](OFFICE_ASSISTANT.md).

## Verified context-aware Assistant release — October 11, 2026

Runtime `82a24c44cc873e7c71959229d699ad98b5aa2832` was pushed to GitHub main
and installed on Hostinger by `git pull --ff-only origin main` from clean tracked
baseline `535dd4d`. The application is live. This follow-up adds provider-free introductions/greetings/capabilities and
AI-technology answers, 31 reviewed passages / 24 canonical workflow topics,
page-aware help and encrypted topic/style follow-ups. It remains focused on
AgriLGU features; raw chat, record replies and server files never become provider
history. Veterinary accounts retain Animal Health operational limits and can
ask about the Assistant's identity, technology and privacy.

The identity/resolver/context/conversation/knowledge/provider classes, approved
knowledge, curation index, shared dialog and chat script deployed together.
Existing dependencies and private configuration remain unchanged. Optimized
production autoloading was refreshed; Assistant assets were mirrored to both
public directories with 0644 permissions. Config, route and view caches were
rebuilt; the general cache was never cleared. No migration, seeder, environment,
account or operational-data change ran. Pending farmer-card artwork/seal changes
remain local and excluded.

Local acceptance passed 386 PHP tests / 2,493 assertions and 198 JavaScript
tests, Pint, syntax, Blade compilation, route and whitespace checks. Actual
synthetic Laravel/MySQL browser sign-in/CSRF verified introduction, dashboard
guidance and a shorter same-topic follow-up using the real configured provider.

- Verified private backup: **21 tables / 9,372 rows**, **3,375,096 compressed
  bytes**, SHA-256
  `06682f34d0b9e418896735cdc8e0c4318089865c2fe6b036a019c693698eca98`.
  Previous tracked runtime, bootstrap caches, served assets and environment were
  preserved outside the document root. Schema and every existing row passed
  fingerprints after the release, with zero new audit rows during preservation
  checks. Application key, all environment bytes, session/cache/Redis namespaces
  and NDVI budgets were preserved. AI limits were not reset.
- Read-only live checks passed seven office roles, one excluded account, all
  24 topics, five veterinary topics, five local identity replies, four help
  follow-up styles, 15 page contexts, six local reports, two workspace renders
  and an existing farmer-card render. Greeting/thanks preserve prior validated
  references; coverage and satellite pages use distinct hints.
- Actual OpenAI dashboard guidance passed with **784 input / 157 output tokens**
  and one verified source. A repeat returned the same cached answer with zero
  new provider tokens. No raw chat or farmer records entered that request.
- Public HTTPS home, office/farmer sign-in, CSS and JavaScript return 200; a guest
  dashboard request redirects to sign-in. Source/served assets match, and live
  JavaScript matches the committed Git blob. Maintenance ended; tracked server
  files are clean. Local browser checks used synthetic data; interactive
  production sign-in and every office workflow were not repeated.

## Verified Hostinger release — October 11, 2026 — Laravel 12 and RAG Assistant

Runtime `7d36fff421040cccbc667f71c61fac887f4721e6` was committed and pushed to
GitHub main, then installed with Hostinger `git pull --ff-only origin main` from
clean tracked baseline `5b43b35`. The locked production dependencies were
prepared privately and installed with `composer install --no-dev --prefer-dist
--optimize-autoloader --no-interaction`. CLI and web PHP are **8.3.35**; Laravel is
**12.69.3**, Sanctum **4.3.3** and Laravel AI SDK **1.2.0**. The application is live.

- Verified private database backup: **21 tables / 9,366 rows**, **3,374,906
  compressed bytes**, SHA-256
  `e9f74c39a33f49c383b2a22a92dc09dbcbb1b73eee606c0e5746ba52a79327cf`.
  Prior tracked runtime, vendor, bootstrap caches, served assets and environment
  were preserved outside the document root with private permissions.
- Every existing row and schema fingerprint passed verification. No migration,
  seeder, account change or operational-data write ran. The only environment
  changes were nine approved AI settings, including enabling the Assistant and
  private provider configuration. All other environment content, application key,
  session/cache/Redis namespaces and NDVI budget counters were preserved. The
  general cache was never cleared.
- The single conversation, reviewed local workflow RAG, structured source
  checks, safe guide fallback and versioned general-help cache are enabled for
  permitted office users. Records remain fresh local queries. Visitors,
  evaluators and farmer sessions stay excluded; veterinary access is Animal
  Health-only. No raw chat, farmer records or visible thread goes to OpenAI.
- Read-only live CLI checks passed seven office-role policies, one existing
  excluded account, ten knowledge topics, three veterinary topics, six local
  reports, dashboard/Farmers renders and an existing farmer-card render. Actual
  canonical OpenAI crop help succeeded: **834 input / 173 output tokens**, two
  sources; a repeated reply hit the cache with **zero new provider tokens**.
  These checks do not claim interactive sign-in or every browser workflow was
  repeated on production.
- Both Assistant public assets match between source, served directories and
  live HTTPS responses, with 0644 permissions. Homepage, office sign-in, farmer
  sign-in, CSS and JavaScript return HTTP 200. Guest dashboard access redirects
  to sign-in. Configuration, routes and compiled views were refreshed; maintenance
  ended and tracked server files are clean.
- Local release verification passed **289 PHP regression tests / 2,072
  assertions**, **11 framework/security tests / 42 assertions**, **146 legacy
  integration tests / 974 assertions** on disposable MariaDB 10.4.32, and all
  **197 JavaScript tests**. Synthetic backup/restore passed for 20 tables / three
  rows. Pint, syntax, Composer/platform/audit checks passed with zero advisories.
  Counts overlap with earlier focused receipts and should not be added to them.
- Pending farmer-card landscape/seal/controller/artwork changes remain local and
  excluded. Existing slash-separated addresses, ink signatures and identifiers
  are preserved. No local sample database or private helper was uploaded to the
  application/public tree.

The earlier pending/local sections below record preparation and are superseded
by this release. See [OFFICE_ASSISTANT.md](OFFICE_ASSISTANT.md),
[ASSISTANT_RAG.md](ASSISTANT_RAG.md) and
[LARAVEL_12_AI_UPGRADE.md](LARAVEL_12_AI_UPGRADE.md) for the architecture and limits.

## Pending local Assistant RAG follow-up — October 11, 2026

The unified help path now uses manifest-approved Markdown retrieval, structured
source validation and a ten-minute versioned help cache. Include
`AssistantKnowledge`, `AssistantGroundedAnswer`, the changed provider/agent/
conversation/resolver, the complete `resources/assistant/knowledge` directory,
curation index and chat JavaScript in the same dependency/Assistant release.
Verify a generated reply, eligible sources and a cache hit, with unchanged fresh
local reports. No migration, embeddings, vector service or new account is needed.
Cache namespaces/budgets stay intact; do not clear the general cache. This is
local only. See [ASSISTANT_RAG.md](ASSISTANT_RAG.md) for boundaries and evidence.

## Pending unified Assistant — implemented locally, October 11, 2026

The single conversation is implemented in normal signed-in office pages: no
mode/year forms, local authorized reports, encrypted follow-ups and canonical
provider help without record/transcript disclosure. Normal auth/CSRF/MySQL browser
checks used an isolated synthetic schema; production remains unchanged. See
[OFFICE_ASSISTANT.md](OFFICE_ASSISTANT.md) and
[AI_ASSISTANT_IMPLEMENTATION_PLAN.md](AI_ASSISTANT_IMPLEMENTATION_PLAN.md).

A future authorized release must install the reviewed PHP 8.3+/Laravel 12 lockfile
and deploy the conversation/context/resolver, request/controller, provider/agent,
policy/config/audit/veterinary changes, session/CSRF middleware and kernel,
exact-route `SynchronizeAssistantRequests`, static help, layout/dialog and both
versioned public assets together. The exact chat route excludes general mutation
locks; other writes remain synchronized. Early chat session commit and no late
save/session-or-CSRF-cookie refresh protect concurrent logout/flash updates. Verify normal office
sessions and stale-client reload responses on the intended host. Mirror both
assets to both public directories with 0644 permissions. No schema/account/data
change is needed. Preserve session/cache namespaces and NDVI/Assistant counters;
refresh config/route/view caches only. Keep disabled until acceptance and retain
compatible runtime/dependency/assets rollback. No push or deploy was performed.

## Pending local dependency upgrade — October 11, 2026

Laravel 12.69.3, Sanctum 4.3.3 and Laravel AI SDK 1.2.0 are installed locally,
requiring PHP 8.3+. This is not included in the live `c322a5e` release. Deployment
must install the reviewed `composer.lock` with production dependencies and
verify against a private staging copy of the legacy MySQL/MariaDB schema first.
No new migration or AI conversation table is required. Never use a blanket
`migrate`, rollback, general cache clear or Composer update on Hostinger for this
upgrade. Preserve session/cache namespaces, budget counters, private files and
all existing rows. The pending digital-card changes remain preserved locally.
See [LARAVEL_12_AI_UPGRADE.md](LARAVEL_12_AI_UPGRADE.md) for checks and rollback.

The unified Office Assistant is also pending deployment. The preceding section
defines its complete release scope; local reports need no provider key, while
AI help needs private OpenAI configuration and approved allowances. No migration
or account operation is required. Do not deploy only the CSS/JavaScript or only
the backend because the retired client contract and privacy boundary changed.

## Verified Hostinger release — October 10, 2026 — AgriLGU branding, crop modal and usage

Runtime `c322a5e009480bcb64853d6e494932c109f46f77` was pushed to GitHub main and installed with Hostinger `git pull --ff-only origin main` from clean tracked baseline `79e2adb`, on PHP 8.3.35. This includes the preceding crop-modal and saved-icon commits `7fc3c6f` and `340c350`.

- Verified private database backup: 21 tables / 9,359 rows, 3,374,822 compressed bytes, SHA-256 `cfd38e1d63ff3d953fd104871c01715d6a24f9250de8cf64a858d29332df90d7`. Prior tracked runtime, affected served assets and the environment were preserved outside the document root. All existing row fingerprints matched. No migration, dependency installation, account operation or operational data change ran.
- The private `APP_NAME` was changed to AgriLGU, preserving the effective session cookie, cache and Redis prefixes explicitly. The expected environment checksum, effective namespaces and existing UTC NDVI budget values passed verification. Configuration, route and Blade caches rebuilt; the general application cache was not cleared.
- All 13 changed public assets were mirrored with 0644 permissions and matched between both public directories. All 13 returned HTTP 200; welcome, office sign-in and farmer sign-in returned HTTP 200 with AgriLGU branding. Guest dashboard access redirects to sign-in. The site is online.
- Read-only live checks passed owner/staff usage visibility, usage panel rendering, the existing planted-area schema, crop modal rendering and owner read-only/staff edit permissions, and corn branding. Local verification includes 86 focused branding/portal/provider tests, 74 further crop/satellite/geofence tests, 26 branding JavaScript checks, 16 crop JavaScript checks and seven badge checks; Pint, syntax, Blade and whitespace checks passed. Test groups overlap and are not a unique combined total.
- Local synthetic browser checks covered desktop/mobile branding, card canvas exports and crop interactions. Real production map drawing/saving, physical printing and provider-wide billing integration were not repeated. Existing farmer identifiers and QR destinations remain compatible. Crop-to-harvest linkage remains a proposal and is excluded.

## Verified Hostinger release — October 9, 2026 — crop workspace, icons and drawing guides

Runtime `b2bc46b` was pushed to GitHub main and installed through Hostinger `git pull --ff-only origin main` from `926a24b`, on PHP 8.3.35. The crop page now groups season selection, map drawing and saving; illustrated crop stickers and numbered draft dots make saved crops and unfinished boundaries clearer. Saved crop icons load for the selected farmer independently of optional parcel recoloring, and the editor return link restores the same farmer/year/season. New parcel drawing accepts consecutive map clicks beyond four corners.

- Verified private backup: 21 tables / 9,347 rows, 3,374,598 compressed bytes; SHA-256 `0111d967a87ae77c6b61f972b000db8bd3d5adb8aad2f133a997790285dd45be`. Prior tracked runtime, served public directory and environment were archived outside the document root. All pre-existing row fingerprints, including saved crop areas/accounts/parcel coordinates, and environment matched after installation. No migration, dependency install, account operation or operational data write ran; local preview data was not uploaded.
- Mirrored `planted-area-editor.css`, `crop-area-badges.js`, `map-drawing-guide.js`, `planted-area-editor.js` and `farmers-maps.js` with 0644 permissions in both public directories. All five filemtime-versioned live HTTPS hashes match. Configuration, route and Blade caches rebuilt. Sign-in returns HTTP 200 and maintenance ended.
- Local verification passed 32 PHP tests / 219 assertions, 37 JavaScript tests, Pint, syntax, Blade, whitespace and secret-pattern checks. Synthetic desktop/390px browser checks covered crop drawing/icons, first/second draft dots and line, finish cleanup, cancellation/removal and no editor overflow. Production read-only and municipal staff renders confirmed new assets/controls and saved map context; scoped layer calls confirmed municipality isolation and existing-row preservation. The private verifier initially selected an office with no parcel, so its sample selection was narrowed to an authorized office with plotted land and then passed; no runtime change was required.
- Real Google Maps 2D/3D interaction, badge graphics and live saves were not repeated during deployment. The badges describe stored crop areas for the chosen season, not live growth. Zoom/collision limits and selected-farmer loading remain bounded. See `PLANTED_CROP_AREAS.md` for workflow and limitations.

## Verified Hostinger correction — October 9, 2026 — empty crop drawing ring

Runtime `437b645` was pushed to GitHub main and installed through Hostinger fast-forward pull from `163cac2`. New polygon drafts now explicitly create their first MVCArray ring before any path access. The earlier synthetic provider adapter always supplied a ring, so it missed this provider behavior; three new regressions reproduce it and cover draft start/corners/finish/serialization, undo/cancel/restart, outside points, unfinished submission and the 50-corner bound. All 25 JavaScript tests, syntax and whitespace checks pass.

A private verified backup covers 21 tables / 9,345 rows, 3,374,571 compressed bytes, SHA-256 `c553472732df2896e634220bcbafb7e61ce1845f3d279465ad95f87b23141122`; prior runtime, affected served script and environment were preserved outside the document root. All existing table row fingerprints, including recorded crop sections, and environment matched after installation. No migration, account or operational-record change ran. Both public script copies match with 0644 permissions; the live filemtime-versioned HTTPS script hash matches, and sign-in returns HTTP 200. Compiled views refreshed, server renders/layer checks passed and maintenance ended. Real-user satellite drawing/save has not been repeated; reload the open crop editor to receive its new versioned script.

## Verified Hostinger release — October 8, 2026 — crop areas and multiple animals

Runtime `571a408` was pushed to GitHub main and installed through Hostinger `git pull --ff-only origin main` from `f2e9cac`, on PHP 8.3.33. Staff can draw separate crop sections inside a parcel and record multiple animals/groups under one owner.

- Private verified backup: 21 tables / 9,331 rows, 3,373,968 compressed bytes; SHA-256 `e11a9ebadc2eacd57d74960a01ca73ccc4dca1c9344f4300e665312d43b47d06`. Prior tracked runtime, served public directory and environment were separately archived outside the document root.
- Only `2026_10_08_000100_add_planted_areas_to_parcel_crop_seasons.php` ran by explicit path. All original columns/rows in all 21 tables and the environment passed fingerprint verification; only its migration receipt was added. Existing account, animal service, seasonal classification and parcel-boundary data were preserved. No seeder, account operation, dependency installation or operational data write ran.
- Mirrored all five scripts: `animal-health-services.js`, `planted-area-editor.js`, `planted-area-layer.js`, `parcel-crop-layer.js` and `farmers-maps.js`, with matching source/served files and 0644 permissions. Configuration, route and Blade caches rebuilt. Sign-in and all five live asset URLs returned HTTP 200 after maintenance ended.
- Local verification: 55 focused PHP tests / 457 assertions, 22 JavaScript tests, Pint on all 17 changed PHP files, syntax, Blade, route and whitespace checks. Hostinger checks passed existing-row/environment preservation, targeted migration, MySQL JSON extraction, actual authorized classification/section service calls, crop-editor and animal-create form renders, and PHP syntax checks.
- Live crop drawing/saving through Google Maps and real-user animal submissions were not repeated during deployment. Existing saved coordinates remain untouched; the local disposable preview database was not uploaded. See `PLANTED_CROP_AREAS.md` and `ANIMAL_HEALTH_MULTIPLE_ANIMALS.md` for workflow and limits.

## Verified Hostinger release — October 8, 2026 — ID signatures and Land Plots cards

Runtime `18cf956` was installed through `git pull --ff-only origin main` from `0ccf369`, including ID artwork/signature commit `52a5e23` and expandable Land Plots cards. No migration, account operation, dependency installation or operational data write ran.

- Verified private backup: 21 tables / 9,305 rows, 3,372,922 compressed bytes; SHA-256 `f1e24a5a30026827db0861bb21160f0552fe5f6914a4ef9afafa6cdd6bb9cde8`. Prior tracked runtime, served public directory and environment were archived outside public directories. All table fingerprints and the environment remained unchanged, including Ramos account assignments.
- Mirrored `farmer-id-card.css`, both card-art SVGs and `farmers-maps.js`. All four source/served hashes match. New SVGs require 0644 permissions in both public directories: their initial restrictive creation permissions caused HTTP 403 and were corrected before completion. All four public URLs and sign-in now return HTTP 200.
- Configuration, route and Blade caches rebuilt. PHP 8.3.33 passed changed PHP syntax and a private, authorized Ramos ID render confirming cardholder and office signature lines, correct name/title and shared artwork. Another office's signatory is excluded. No protected page HTML or farmer data was emitted.
- Local verification passed 8 signature tests / 35 assertions, 5 workspace tests / 29 assertions, 9 JavaScript tests, syntax, Blade compilation and whitespace checks. Live browser print/PNG export and interactive Land Plots expansion were not repeated. Signature lines are unsigned until signed by hand after printing; no digital signature image is stored.

## Verified Hostinger follow-up — October 4, 2026 — longer Lot Series references

Runtime `77a1e41` was pushed to GitHub main and installed through `git pull --ff-only origin main` from `3f79957`. Only `2026_10_04_000100_expand_rice_seed_lot_series.php` ran by explicit path with `--force`. The nullable Lot Series column is now TEXT, retaining its original charset/collation. Import and manual entry support 10,000 characters; rollback refuses to truncate longer stored references.

- Fresh private backup: 21 tables / 8,310 rows, 3,277,633 compressed bytes; SHA-256 `a268810a247023386e6a83fd23b5c5183fd5080ee53e9a9caeda6a84f65dae69`. Runtime and environment were separately preserved.
- All existing table rows and the environment matched their fingerprints; only the targeted migration receipt was added. No operational workbook, baseline migration, visitor migration or account operation ran. No public assets changed. Unfinished Land Plots work remains local.
- Local checks passed 14 import tests / 46 assertions and 18 operations view tests / 66 assertions, Pint and syntax checks. Hostinger PHP 8.3.33 passed the new import-page render and storage of 10,000 four-byte Unicode characters in a separate temporary MySQL table. Configuration, route and view caches rebuilt. See `docs/RICE_SEED_IMPORT_VALIDATION.md`.

## Verified Hostinger release — October 4, 2026 — rice seed import validation

Runtime `3e81caf` was pushed to GitHub main and installed by Hostinger `git pull --ff-only origin main` from `4033cc2`. The importer now rejects oversized database text with an Excel-row/column correction message and rolls back the entire rejected import. Genuine XLS/XLSX reader failures receive recovery guidance.

- Private database backup: 21 tables, 8,304 rows, 3,277,550 compressed bytes; SHA-256 `b48e4e4464375af993880ce1e14a788cd6813fa796605dba596f999047182223`. Prior runtime, served public files and environment were separately preserved.
- All table fingerprints and the environment remained unchanged. No migration, account provisioning, password change, dependency update or operational import ran. The previously pushed visitor/satellite follow-up code was included, with visitor provisioning and migration left unapplied; unfinished Land Plots UI edits remain local.
- Five changed public assets were mirrored and verified. Configuration, routes and Blade caches rebuilt. PHP 8.3.33 passed syntax, an authorized import-page render and 120/121-character boundary checks. Homepage/sign-in returned HTTP 200 and guest import returned HTTP 302. The tracked server checkout was clean.
- Local checks passed 12 import tests / 41 assertions, 18 operations presentation tests / 66 assertions and 5 workspace presentation tests / 29 assertions, Pint, syntax, Blade compilation, route and whitespace checks. No actual confidential workbook was imported during verification. See `docs/RICE_SEED_IMPORT_VALIDATION.md`.

## Local follow-up — September 26, 2026 — satellite field-check modal

The Sentinel-2 parcel action now reads **Satellite field check** and the modal uses a farmer-friendly three-step flow, plain controls, a colour guide, expandable details and actionable no-data guidance. This follow-up is local and has not been pushed or deployed. A future release must deploy the changed `_satellite_content.blade.php`, `_satellite_dialog.blade.php`, `satellite_modal.blade.php`, `parcel-satellite.css`, `parcel-satellite-modal.css`, `parcel-satellite.js`, `parcel-satellite-modal.js`, `farmers-maps.js` and focused tests together, then rebuild views/caches. No migration or account/data operation is required.

## Verified Hostinger release — September 25, 2026 — Sentinel-2 modal UX

Runtime `a7421fc9b644f549ff7d754b8473f4db64a3afad` was pushed to GitHub main and installed through `git pull --ff-only origin main`. The release keeps Sentinel-2 parcel analysis on the Farmers map in an accessible in-page modal, adds a full-page fallback, and rewrites NDVI results with plain-language guidance for field-check planning.

- A verified private database backup contains all 21 tables and 11,874 rows, 5.1 MB compressed, SHA-256 `f0cca1bd6d4c6bb1bac1c2f9237b946fc7fd3f2e6c1dafb425dd7fb6abdab406`.
- The tracked application and separate `public_html` directory were archived before release. Five changed public assets were mirrored and their source/served copies match: `parcel-satellite.css`, `parcel-satellite-modal.css`, `parcel-satellite.js`, `parcel-satellite-modal.js`, and `farmers-maps.js`.
- No migration, account change, import, operational data write, Composer install or npm build ran. Existing untracked recovery files were preserved, and the tracked server tree is clean after the pull.
- Configuration, route and Blade caches rebuilt successfully. All three Sentinel parcel routes are present; login and the five affected asset URLs returned HTTP 200 after release.
- Local focused verification passed 27 satellite PHP tests / 150 assertions, 5 farmer-workspace presentation tests / 29 assertions, 18 JavaScript tests, Pint, PHP/JavaScript syntax, Blade compilation and whitespace checks.

## Verified Hostinger release — September 25, 2026 — Sentinel-2 and farmer card

Runtime `d796071f6afff34bd609c92bae85a3fdbd793e29` was installed through `git pull --ff-only origin main` after the approved GitHub push. The release includes the Sentinel-2 parcel analysis module, farmer-portal refinements, and the DA green/gold farmer registry card with the AgriGOV wordmark on the back. PHP 8.3.33 was verified.

- A verified private database backup contains all 21 tables and 11,852 rows, 5,385,184 bytes, SHA-256 `53731625bd41f7001ba36adec92eac614e94baa7e1bbb7d7317826e0df49c8ff`.
- All database table fingerprints and the environment checksum were unchanged. No migration, account change, import, or operational data write ran.
- Eight public assets were mirrored to `public_html`, and their hashes match the Laravel `public` copies. Configuration, route and Blade caches were rebuilt.
- Local focused checks passed 26 PHP tests / 143 assertions and 10 JavaScript tests. Homepage and login returned HTTP 200 after the release. Authenticated live farmer-card visual inspection and real Sentinel-2 provider calls were not repeated.

The owner selected GitHub deployment on September 21, 2026. The source repository is `https://github.com/vrash12/agri`; production follows `main`. Prefer reviewed Git commits, a normal push, and `git pull --ff-only origin main` over ZIP/File Manager releases. Never force-push a deployment or discard unexplained server edits. The owner must authorize each production release.

## Normal release

1. Inspect the local working tree and isolate the approved changes. Keep unrelated unfinished work out of the release. Run focused tests, Pint, syntax/Blade checks and a secret review before committing. Never commit environment files, private backups, account directories, credentials or local output.
2. Check the live checkout, PHP version and database baseline over SSH. Record the current Git revision and verify that tracked runtime files are clean or reconcile explained manual changes after backup. Do not print environment values or credential files.
3. Push the reviewed commit to GitHub. On Hostinger, fetch and review the intended revision before entering a short maintenance window. Back up the database and affected application/public files into a private directory outside the document root.
4. In the application directory, run `git pull --ff-only origin main`. Confirm HEAD is the approved revision. Production runs PHP 8.3; use the lockfile and production Composer options when refreshing dependencies/autoloading. Do not run Composer update.
5. Run only the release's explicitly reviewed additive migrations, by path, after verifying the existing baseline. Do not run legacy migrations, DatabaseSeeder, geography imports or other data setup automatically.
6. Hostinger currently serves a separate `public_html` directory. Mirror changed tracked public assets from `public` to the same relative location in `public_html`. Preserve its existing entry point, configuration and protected storage. Do not use a deletion sync on either public directory.
7. Refresh configuration, route and view caches. Verify preservation, access scope, rendered pages and both public asset copies, then leave maintenance mode. Check public HTTP and the live interface. Record the installed revision and backup receipts.

SSH authentication is interactive or uses an owner-approved existing key. Never include a password in a command argument, script, repository, deployment document or log. Existing verified SSH host keys must remain enforced.

## Verified Hostinger release — September 24, 2026 — farmer records and all-parcel map

Runtime `c07e78f7d075008114cd47edeeeea81218de8611` was pushed to GitHub main and installed through `git pull --ff-only origin main` from baseline `bd75ced`, on PHP 8.3.33. It includes the richer profile, overview, assistance and harvest screens plus the map-first My Farm view. Seed-history labels use the existing distribution reference and planting period. Verification completed at `2026-09-24T04:58:55+00:00`, followed by reopening the site.

- A verified private database backup covers 21 tables / 11,830 rows, 5,384,833 bytes, SHA-256 `aa36aa75308c3b56a89ec269b30561382005ed1a508c0303ae6b3d17142cad79`. Private archives preserve the prior tracked checkout and affected separate public assets.
- All 21 table fingerprints, account data and the environment remained unchanged after release verification. No migration, dependency update, account provisioning, password change or data import ran.
- Read-only server checks passed for all 12 synthetic sample accounts: 60 rendered overview/profile/farm/assistance/harvest pages and 36 owned parcel boundaries. Routes and both public asset copies match; configuration, route and view caches rebuilt successfully.
- Live office/farmer login and both changed assets return HTTP 200. The private collection endpoint rejects unauthenticated requests with HTTP 401 and no-store headers. Live asset hashes match the deployed files.
- Local verification passed 34 PHP tests / 425 assertions, 11 JavaScript tests, Pint, PHP/JavaScript syntax, nine compiled portal templates, route checks and whitespace checks. Desktop/phone satellite-map interaction was checked with synthetic local data. Authenticated production browser interaction and real-farmer acceptance were not repeated.

## Verified Hostinger release — September 24, 2026 — Farmers overview and shared sign-in

The owner approved runtime `bd75ced2c6c41fc9e1716a0649e73ccd857a6b56`, which was pushed to GitHub main and installed with `git pull --ff-only origin main` from server baseline `9cc3527`. This also installs the already reviewed primary farmer-ID changes in `e19756f`. PHP 8.3.33 was verified, tracked server files were clean, and existing untracked files were retained.

The verified private database backup contains 21 tables and 11,755 rows (approximately 5.1 MB; SHA-256 `4e4bc9e5ad83adb89f80071f4e24d8d1e5371dbb6706d2d434847f74b1325257`). Private archives also preserve the prior tracked checkout and the separate public directory. No migrations, account changes, imports or dependency installations ran. The backup command records its normal audit event.

Configuration, route and Blade caches rebuilt successfully. The installed revision matches GitHub main, and both copies of `login-layout.css`, `farmer-finder.js`, `farmer-picker.js`, `farmer-workspace.js` and `farmers-maps.js` have matching hashes. Live login, workspace JavaScript and login CSS returned HTTP 200. Local release checks passed 87 PHP tests / 1,011 assertions and 140 JavaScript tests. Authenticated production browser flows and satellite performance were not repeated during this release.

The subsequent farmer portal overview/seed-detail/harvest enhancement was not included in `bd75ced`; it was later installed with the map enhancement in `c07e78f` as recorded above. A separately authorized data operation created testing sign-ins for the 12 synthetic Region I farmers only, after a fresh verified backup. All live sign-ins and non-target preservation checks passed; see `docs/REGION_I_SAMPLE_DATA.md`.

## Verified Hostinger release — September 23, 2026 — farmer chooser and collage arrows

Runtime commit `88d3bdc550895d1feb46c32017edfe6d49f13cd1` was pushed to GitHub main and installed with `git pull --ff-only origin main` from the clean tracked baseline `d69eb4a`. PHP 8.3.33 was verified. A private archive preserved the affected application files and the separate public copies before a brief maintenance window.

- A verified database backup contains all 21 tables and 10,909 rows, SHA-256 `ea16432a75a8058f7e63660c6c6e0c407160879a5656899334106ff8f1f430bf`. It includes the separately authorized CALABARZON account changes. The environment file checksum is unchanged.
- Focused validation passed 73 PHP tests / 692 assertions and eight slideshow JavaScript tests, plus Pint, syntax, Blade compilation and whitespace checks. Desktop and 390px phone UI checks used local synthetic fixtures.
- No migrations, dependency updates or operational data imports ran. Production configuration, route and view caches were rebuilt successfully.
- `welcome.css` and `welcome-slideshow.js` match between both public directories and the live HTTPS responses. The homepage returns HTTP 200 with Previous/Next controls and without the old numbered/play controls.
- All seven CALABARZON accounts signed in successfully and opened Farmers with the expected assigned municipality choices; all verification sessions were signed out. The existing Regional Head was retained, three provincial administrators were activated and three provincial staff accounts were created. See `CALABARZON_BOUNDARY_SOURCES.md` for account scope and handoff details.
- The site is online. Production browser interaction with Google Maps was not repeated; the previously deployed hover optimization and slash-separated farmer-card parcel addresses remain in place.

## One-time reconciliation

The old server checkout was at `6822f9e`, while individually deployed runtime files matched GitHub `4546096` except for the known CAR/dashboard/sign-in release. A metadata-only mixed reset to that verified baseline preserves working files; it is only appropriate for this audited transition, after recording the old revision. Back up and preserve the known manual changes and any incoming untracked-file collisions before the subsequent fast-forward pull. A normal future release should not need this reconciliation.

The scoped September 21 release keeps the already installed CAR/dashboard changes, adds saved geofence appearance and the notice acknowledgment, and excludes the independent unfinished farmer-ID display/search work. Its only schema change is `2026_09_21_000100_add_fill_opacity_to_municipality_boundaries.php`. Its only intentional data change is the explicitly requested white/20% appearance for 93 Region II active boundaries, with attributed audit events.

`scripts/releases/2026_09_21_geofence_appearance.php` provides CLI-only `snapshot` and `verify` phases for this one-time release. It requires maintenance mode, a private recovery directory outside the app, and an explicit authorized owner ID. It verifies backup readability, existing-table fingerprints, boundary geometry, environment integrity, exactly the expected migration and 93 audits, matching map payloads, rendered controls and a zero-change repeat preview. It does not perform Git changes, migrations or style writes itself.

## Verified Hostinger release — September 21, 2026

Runtime commit `610013d3e9cb807376ea082226a43b28899a9742` was pushed to GitHub main and installed using `git pull --ff-only origin main`. Verification completed at 02:38:45 UTC (10:38 Philippine time). The site was returned online afterward.

- Isolated release checks: 147 PHP tests / 4,544 assertions; 43 JavaScript tests. Formatting, syntax, whitespace and Blade compilation passed.
- Private application archive SHA-256: `30800f7fcecbf19c925319eb6ee87c9f2156e75f759900873db345ddc3545bc9`.
- Verified private database backup: 439,304 bytes, SHA-256 `3affe7157a52c402f9076e16efd23f3df1194fad99dd1652a07b2e1e2b51eee5`.
- All existing rows in 19 other tables, all 489 boundary shapes and the environment file were preserved. Exactly one additive opacity migration and 93 attributed style audit events were added.
- All 93 Region II references now use white at 20% opacity. Both actual map controller payloads agree on color, opacity and label position. A repeat setup preview proposes zero changes.
- Production Composer install and configuration, route and view caches completed. Both public asset copies match. Public assets require mode 0644; verified the new helper and dashboard script return HTTP 200 after setting these permissions.
- Live login returns HTTP 200 with the required acknowledgment. Production Blade checks confirm Save appearance and shared map styling controls. Visual Google Maps interaction was tested locally; no production interactive map edit was performed during this release.
- Known manual server edits and incoming file collisions were preserved privately before reconciliation. Independent pending farmer-ID display/search changes were excluded.

## Verified Hostinger release — September 21, 2026 — map labels and Baguio barangays

Runtime commit `a751355` was pushed to GitHub main and installed with
`git pull --ff-only origin main`. Verification completed at 04:11 UTC and the
site was returned online.

- The private application archive is SHA-256 `5cf782363ab3e803a990c9f439b3003388e36fc104b3ee24ebd6705516bdf33c`.
- The verified private database backup is 439,311 bytes across 20 tables, SHA-256 `1ad6748468b623f46b23408e52ca85348843f2dfca777ab255c8a316ff19ba38`.
- No migration or database write ran. The 129-feature Baguio GeoJSON was verified at 86,645 bytes with SHA-256 `351c9f3d339f88a068f6b9373e88f5dda849d86899074a08c0d3a76a6d990282`.
- Configuration, route and view caches passed. The three changed public scripts match their `public_html` copies and return HTTP 200. Login returns HTTP 200.
- A read-only check against the actual Baguio workspace returned all 129 features, confirmed System Owner availability, and confirmed Benguet Super Admin isolation.
- Unexplained server edits and the unfinished farmer-ID work remain preserved outside this scoped release.


## Verified Hostinger release - September 23, 2026 - MIMAROPA

Runtime `33a150cfe013ba6fc25f330a7a35b1d29dded8b2` was pushed to GitHub main and installed by `git pull --ff-only origin main` from the clean tracked baseline `edde7a6`, on PHP 8.3.33. All 73 planning geofences and MIMAROPA region membership were activated in one transaction at 05:11:30 UTC. Calapan remains under Oriental Mindoro; Puerto Princesa retains its separate supervising scope.

The verified private database backup contains 21 tables and 10,932 rows, SHA-256 `63e9ea73607b44ce4c923a3ec339f7b91286c6e0724c0438cb31e68a6b1a43bc`; affected runtime files were also archived privately. Every pre-existing row, all accounts, and the environment file remained unchanged. No migrations, dependency changes, public assets, account issuance or operational samples were involved.

All 76 focused tests / 2,204 assertions, Pint, syntax, Blade, route and whitespace checks passed locally. Live service verification confirmed six scope counts, 73 regional choices, city isolation and the Farmers region chooser. Caches were rebuilt and maintenance ended; homepage and login returned HTTP 200, with login still private/non-storable. Interactive Google Maps performance was not benchmarked. See [MIMAROPA_BOUNDARY_SOURCES.md](MIMAROPA_BOUNDARY_SOURCES.md) for full attribution, small-island processing and limitations.

## Verified Hostinger release — September 23, 2026 — Bicol Region

Runtime commit `aabace9e5d957cd6b41bbb1010d7db70e2eb2439` was pushed to GitHub main and installed with `git pull --ff-only origin main` from the clean baseline `898eff793c0a7b1a545345560ecb85f595a44804`. The release was activated at `2026-09-23T07:21:29Z` on PHP 8.3.33.

- The verified private database backup contains all 21 tables and 11,220 rows, 5,195,028 bytes, SHA-256 `a5f31724bec975a48148a6b1d4f5de0f70c6652eb3ad3b560b5aea2264793a78`.
- The explicit Bicol transaction added 114 attributed planning/reference boundaries and Region V membership. Counts are Albay 18, Camarines Norte 12, Camarines Sur 36 including Iriga City, Catanduanes 11, Masbate 21, separate Naga City 1, and Sorsogon 15.
- Live checks passed 114 Regional Head choices, provincial scope isolation, separate Naga City access, the Farmers Region → Municipality chooser, existing-row preservation and unchanged accounts. No migration, dependency update, public-asset change or account issuance ran.
- Local validation passed 26 focused PHP tests / 2,246 assertions, Pint, syntax, Blade compilation, route listing, source geometry checks and whitespace checks. Homepage and login returned HTTP 200 after the release; login retained private/no-store headers. The site is online.
- These are approximate planning/reference boundaries, not legal, cadastral or survey-grade boundaries. See [BICOL_BOUNDARY_SOURCES.md](BICOL_BOUNDARY_SOURCES.md) for source identities, checksums, validation and limitations.

## Pending release — restricted visitor access

The visitor release adds the `visitor_mode` column and a dedicated `visitor:create` command. Run the migration before provisioning an account, then create the active unassigned identity through the command's hidden password prompt. The visitor role remains `super_admin` for compatibility, but server middleware and scoped controllers expose only the aggregate overview and active municipality boundaries. Do not place visitor credentials in GitHub, deployment notes, logs, or support messages.

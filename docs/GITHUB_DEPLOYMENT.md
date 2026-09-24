# GitHub-to-Hostinger deployment

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

## Verified Hostinger release — September 24, 2026 — Farmers overview and shared sign-in

The owner approved runtime `bd75ced2c6c41fc9e1716a0649e73ccd857a6b56`, which was pushed to GitHub main and installed with `git pull --ff-only origin main` from server baseline `9cc3527`. This also installs the already reviewed primary farmer-ID changes in `e19756f`. PHP 8.3.33 was verified, tracked server files were clean, and existing untracked files were retained.

The verified private database backup contains 21 tables and 11,755 rows (approximately 5.1 MB; SHA-256 `4e4bc9e5ad83adb89f80071f4e24d8d1e5371dbb6706d2d434847f74b1325257`). Private archives also preserve the prior tracked checkout and the separate public directory. No migrations, account changes, imports or dependency installations ran. The backup command records its normal audit event.

Configuration, route and Blade caches rebuilt successfully. The installed revision matches GitHub main, and both copies of `login-layout.css`, `farmer-finder.js`, `farmer-picker.js`, `farmer-workspace.js` and `farmers-maps.js` have matching hashes. Live login, workspace JavaScript and login CSS returned HTTP 200. Local release checks passed 87 PHP tests / 1,011 assertions and 140 JavaScript tests. Authenticated production browser flows and satellite performance were not repeated during this release.

The subsequent farmer portal overview/seed-detail/harvest enhancement remains local and is not included in `bd75ced`. A separately authorized data operation then created testing sign-ins for the 12 synthetic Region I farmers only, after a fresh verified backup. All live sign-ins and non-target preservation checks passed; see `docs/REGION_I_SAMPLE_DATA.md`.

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

# Hostinger release — 18 September 2026

Deployed the current application source and public assets to agritarlac.online, including the farmer-services welcome page, login slideshow, parcel display optimization, assistance beneficiary control, distribution sheets, reporting changes, and the security/backup changes already committed locally.

- Preserved production `.env`, uploaded/private storage, account credentials, roles, and existing operational records. No localhost records or development passwords were imported.
- Created and verified private application and database backups outside the public document root.
- Applied only `2026_09_17_000100_create_rice_distribution_batches_table.php` and `2026_09_17_000200_add_seed_sheet_fields_to_rice_seed_distributions.php`. No reference or demo seeders ran.
- Composer dependencies match production after normalizing line endings; no dependency installation was required.
- Rebuilt configuration, route, and view caches. Public assets are mirrored into Hostinger's `public_html`; new public directories require mode 0755 and files 0644.
- Deployment verification must explicitly reconnect Laravel's database connection after running child CLI commands: Hostinger closes idle connections. Two earlier attempts automatically restored prior code before the successful installation.

## Validation

All 36 JavaScript tests passed. Focused PHP checks passed (63 presentation/geometry/dashboard tests and 63 data/export/scope tests). Both additive migrations and 20 distribution-sheet tests passed against an empty copy of the live schema. The full 384-test run initially exposed missing fixtures in the empty test database; after adding synthetic test-only administrative/municipality fixtures, the affected eight backup/demo tests passed. No production test fixtures were created.

Server-side rendering passed for the dashboard, farmer directory, assistance directory, and distribution sheets under System Owner, Super Admin, and municipal staff roles, plus the staff assistance creation form. Installed file checksums and existing data fingerprints matched. Production had 1,667 farmers, 60 users, 43 assistance releases, six parcels, and 33 boundaries at deployment.

Interactive authenticated map navigation and an actual signed-in distribution submission were not exercised on production. The local test database and private deployment receipts are outside the repository.

The nightly backup command is shipped, but a persistent Laravel scheduler was not configured by this release. Deployment-only jobs are temporary.

## Farmer profile repair — 08:23 UTC

The live profile/history page failed for province-level accounts because its shared workspace selector received no `municipalities` collection. `FarmerController::records()` now passes the same `MunicipalityAccess`-scoped choices used by other farmer pages. This retains province isolation and read-only oversight permissions.

The new regression test reproduced the original undefined-variable error before the fix. All 10 province reporting tests (83 assertions) pass afterward, covering Super Admin, System Owner, provincial staff, municipal staff, empty history, and denied foreign profiles. Pint, PHP syntax, route checks, Blade compilation, and diff checks also passed.

Only the controller changed on Hostinger. Its previous checksum was verified and a private backup was created. A read-only server-side check rendered the reported profile with an existing Super Admin in its province and verified municipality scope. No migration, account update, or operational data change was needed. Interactive browser sign-in was not repeated because the existing session had expired.

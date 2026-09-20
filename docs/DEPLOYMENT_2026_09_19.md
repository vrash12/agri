# Hostinger release — 19 September 2026

Deployed main commit `5d6f0787a5f9f3e458a75cefceca9f2a814244fb` to agritarlac.online at 01:13 UTC (09:13 Philippine time). The release includes harvest recording and production reporting, repeat-assistance warnings, and the shared server-searched beneficiary picker.

## Deployment

- Compared all packaged source files against production; 47 application/public targets needed installation. Files differing only in line endings were retained. No unexpected live changes were found. Composer dependencies matched.
- Created and verified private application and database backups before installation. Rebuilt configuration, route, and view caches and verified installed file checksums.
- Applied only `2026_09_19_000100_create_harvest_records_table.php` and `2026_09_19_000200_link_harvest_records_to_releases.php` by explicit path. Both were tested for application, rollback, and reapplication on an isolated copy of the deployed schema.
- No releases qualified for the harvest backfill; it was not run. No seeders or local records were imported.
- Verified existing records, account password hashes, roles, and production environment remained unchanged. Production retained 1,667 farmers, 60 users, 43 assistance releases, six parcels, and 39 municipality boundaries.

## Verification

- 97 focused PHP tests passed (556 assertions) on a dedicated disposable MySQL database; 41 JavaScript tests passed.
- PHP syntax, Blade compilation, route listing, and diff checks passed. Pint's read-only check reported existing formatting issues in 12 source files; deployment preserved the committed code without a formatting rewrite.
- Seventeen read-only production render checks passed across System Owner, Super Admin, and municipal staff: dashboard, farmers, assistance, distribution sheets, harvest records, and the two staff entry forms.
- Public login returned HTTP 200 after deployment. Actual production submissions were not performed.

Private release files and backup receipts are retained outside the public document root under `release-20260919`. Deployment-only scheduling is removed after verification. This document records the deployment locally and was not part of the deployed commit.

# Seasonal crops deployment — 2026-09-19

The Crops by season feature was deployed to agritarlac.online at 12:03:09 UTC
(20:03 Philippine time), with the owner's authorization.

## Release scope

- Seasonal crop controller, request, model, policy, support service, registrations,
  audit integration, routes, forms, and map controls.
- Both map JavaScript assets in both Hostinger public directories.
- Only the explicit additive `2026_09_19_000100_create_parcel_crop_seasons_table.php`
  migration; no seeders or unrelated pending migrations.
- Feature documentation. The package verified 21 target files.
- Dashboard enhancements and concurrent Composer dependency changes were excluded.

The database and replaced files were backed up privately before installation.
The successful backup contains 18 tables and is 426,550 bytes. Backup artifacts
and installer receipts remain outside the public directories in the private
`seasonal-crops-release-20260919` release directory.

## Recovery and verification

The first attempt applied the additive migration but encountered a PDOException
during post-installation verification. Application files were restored and the
empty additive table retained. The retry explicitly reconnected to the database
after CLI cache commands, validated the previous receipt, and completed.

The successful receipt confirms:

- 64 read-only production checks passed: schema/indexes, routes and middleware,
  eight dry/wet form renders across four roles, staff write policies, read-only
  oversight, bounded layer validation, and municipality isolation.
- Existing record fingerprints and environment configuration were unchanged.
- The new crop table remained empty; no seasonal classifications were invented.
- Route and view caches were rebuilt and the site was online.
- Public login returned HTTP 200 and the unauthenticated crop endpoint HTTP 401.
- Both public JavaScript assets returned HTTP 200 and matched package hashes.
- The temporary deployment cron jobs were removed after completion.

Local verification previously passed 14 focused PHP tests and 23 JavaScript
tests, formatting, syntax, Blade compilation, routes, and a real Google 3D
walkthrough using synthetic local records. Production verification did not submit
crop records or exercise the signed-in map in a browser. Office-data acceptance
remains outstanding.

## Staff access

Open Farmers → Parcel Map → Crops by season. Choose the crop layer, reporting
year, and Dry season or Wet season, then Apply. Staff record classifications using
the Seasonal crops action beside a parcel. Missing records display Not recorded.

For rollback, restore application files together and retain the additive table.
Do not remove recorded seasonal data through the migration's down method.

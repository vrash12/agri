# Farmer portal deployment — September 20, 2026

The farmer portal was pushed to `main` in commit `f59cc8c` and deployed to
agritarlac.online with the owner's authorization. Deployment completed at
2026-09-19 22:15:11 UTC (September 20, 06:15:11 Philippine time).

## Scope and safeguards

- Added the separate farmer authentication guard, staff-assisted activation and
  recovery, private profile/parcels/assistance pages, and office access controls.
- Applied only `2026_09_20_000100_create_farmer_portal_accounts_table.php`.
- Verified 47 target files, including assets mirrored to both public directories.
- Preserved the live seasonal-crop feature and other previously deployed fixes.
- Refreshed configuration, route and view caches on PHP 8.3.33.
- Verified a private backup of 18 existing database tables (426,618 compressed
  bytes) and backups of replaced application files before installation.
- Confirmed existing table fingerprints and environment configuration were
  unchanged. No farmer accounts or operational records were created.

The working tree also contains unrelated dashboard, assistance-coverage, welcome
page, dependency and other changes. Those changes were not included in this
portal release. Git and production therefore retain their pre-existing baseline
differences; a full Git checkout must not be used to overwrite the live site.

## Verification

- Preflight: all 57 file/dependency comparisons matched reviewed live baselines.
- The isolated portal commit passed 23 PHP tests / 223 assertions; five JavaScript
  tests also passed. Earlier combined local verification passed 42 PHP tests.
- Production: 55 read-only checks passed, covering schema, unique keys, routes,
  guards, response caching, private page rendering and staff policies/views.
- Public HTTP checks passed for welcome, office sign-in, portal sign-in and
  activation. Private requests required authentication; geometry returned 401.
- Four public assets matched the release checksums. Farmer sign-in links were
  present on welcome and office sign-in pages.
- The live farmer sign-in page rendered in the browser. Temporary preflight and
  deployment cron jobs were removed after completion.

The production verifier used an unsaved in-memory farmer identity inside a
database-enforced read-only transaction. It did not activate a real account.
The selected farmer had no parcel for the map render, and the selected Super
Admin had no scoped farmer; those two checks were explicitly skipped. Map and
Super Admin isolation were covered locally. A verified real-farmer activation,
sign-in and recovery pilot remains an office acceptance step.

## Access and rollback

Farmer entry: https://agritarlac.online/farmer-portal/login

Authorized office staff open Farmer profile/history → **Farmer portal access**,
verify identity, and issue a one-time activation code privately. The farmer
chooses their password on the activation page.

Private release receipts, installer and backups remain outside the public web
directories in `farmer-portal-release-20260920`. The installer is CLI-only and
refuses a repeat run. Restore the backed-up application files as one release if
rollback is needed; retain the additive table so issued accounts are not lost.

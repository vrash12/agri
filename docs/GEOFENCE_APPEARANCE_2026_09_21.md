# Saved municipality geofence appearance — September 21, 2026

## Behavior

In Municipality geofences, choose a municipality and use **Geofence appearance**. Choose its active boundary or a draft, pick a color and adjust **Geofence color opacity**, then press **Save color & opacity**. **Discard changes** restores the saved appearance. Zero means a transparent fill with a visible outline; 100% means solid fill. The default is 20%.

Both the municipality workspace and Farmers parcel map use the saved color and fill opacity. Reload an already open Farmers page after saving. This does not introduce real-time cross-tab synchronization. Small municipality labels use subdued white text and a dark halo. Label positions come from the existing server geometry service, avoiding holes and water between islands. Parcel-map labels hide at distant camera ranges and can yield to other labels on collision.

System Owners and provincial Super Administrators can save within their existing scope. Other agriculture roles can view the settings. The new PATCH endpoint retains CSRF, authenticated account scope, update policy, synchronized writes and optimistic record versions. Invalid opacity/color, stale versions, archived boundaries, geometry submissions and foreign-province updates are rejected. Shape, ownership, status, area and coordinates are preserved. Changes are audited. Multipart geometry does not need to enter the shape editor to change appearance.

The separate appearance panel is disabled while drawing/editing shapes. New drawings default to 20%; existing shapes retain saved opacity. Parcel colors and snapshot export styling are unchanged.

## Local setup

Applied at **2026-09-21 01:47:24 UTC** (09:47:24 Philippine time), after a runner verified the local environment and loopback database host. The additive opacity migration was applied by its exact path; no baseline migrations or demo seeders ran.

| Geographic scope | Active boundaries restyled |
| --- | ---: |
| Batanes | 6 |
| Cagayan | 29 |
| Isabela | 36 |
| Nueva Vizcaya | 15 |
| Quirino | 6 |
| Separate Santiago City | 1 |
| **Total** | **93** |

All 93 received `#FFFFFF` at `0.20` fill opacity and an attributed audit event. The setup verified all 489 stored boundaries: no geometry, ownership, status or measurement changed, and all non-target rows retained their original values apart from the new default opacity column. Fingerprints across 17 other tables and every existing audit were unchanged. Repeating the setup produced zero changes and no new audits.

A private gzip backup outside the repository and public directories was verified for readability and all 20 table definitions: **477,695 bytes**, SHA-256 `4cdf164c6e497e869984a7d8fbb9622f3359664c2711d6447a54bebe9bb18798`. This check did not perform a scratch restore. No credentials or backup contents are in this document.

## Verification

Focused tests cover style persistence in both map payloads, zero/solid fill, geometry preservation, role and province isolation, stale versions, invalid values, archived boundaries, setup rollback, idempotence, outside-region preservation and all 93 label positions. JavaScript tests cover preview/save/discard, double submissions, failure recovery, stale asynchronous responses, geometry-editor interaction, viewport caching, 3D label visibility and matching fill values.

- Geofence, Region II setup and province-isolation suites: **45 tests, 2,263 assertions** passed. After centralizing label placement in the existing geometry service, geofence, geometry and province-isolation suites passed again: **43 tests, 532 assertions** (these counts overlap).
- Three focused JavaScript suites: **28 tests passed** after the label calculation moved to PHP.
- Both actual local map-controller payloads were compared for all 93 Region II boundaries: color, 20% opacity and label position matched. This was read-only. PHP/JavaScript syntax, Pint, Blade compilation, all 11 geofence routes and Git whitespace checks passed.

Browser checks use isolated synthetic accounts and simulated Google Maps. They verify the actual rendered Blade controls, color input, keyboard opacity changes, saved-state confirmation, discard and read-only behavior, plus responsive layout. Live Google satellite/3D rendering has not been revalidated for this feature.

No horizontal page overflow was found at 320, 390, 768 or 1440 CSS pixels. Desktop and mobile screenshots were visually inspected. Color and 0% opacity saved successfully in the simulated interface; a 100% preview was discarded back to the saved value. Municipal staff had disabled appearance controls and no Save button. No browser console errors were reported.

The local runtime is PHP 8.4.10; existing dependency deprecations remain. Production's supported runtime remains PHP 8.1–8.3. No new dependency or environment variable is required.

## Deployment requirements — pending owner authorization

This new appearance feature has **not** been installed on Hostinger. The earlier completed CAR/dashboard/sign-in release is separate.

1. Prepare a scoped release and verified database/file backup on the target. The working tree also contains pending Farmer-ID changes in shared farmer files; review those separately and do not upload the entire dirty tree as a geofence-only release.
2. Include the new migration, model cast/fillable entry, both map controllers, `GeoGeometry::labelPosition`, the style route, Region II appearance service/command, both changed Blade views and all three map/style JavaScript assets. Preserve existing imports, map settings and middleware.
3. During the coordinated release, apply only `php artisan migrate --path=database/migrations/2026_09_21_000100_add_fill_opacity_to_municipality_boundaries.php --force` against a verified baseline **before** serving the new queries.
4. Mirror `geofence-style.js`, `municipality-boundaries.js` and `farmers-maps.js` to both Hostinger public directories; load the shared helper before either map script. Refresh appropriate route/config/view caches after deploying the PHP and views.
5. Run `php artisan geofences:region-two-white` for a read-only coverage/style preview. After reviewing the target and backup, apply with `php artisan geofences:region-two-white --apply --actor=OWNER_ID`, replacing `OWNER_ID` with the authorized active System Owner's ID. The setup requires exactly one active boundary for each of the 93 geographic workspaces and aborts atomically on missing/inactive coverage or an audit failure. It does not assign regional membership or create accounts.
6. Verify all 93 styles and existing-data preservation, both map payloads, real signed-in color/opacity saving and satellite/3D display. Confirm another municipality and unauthorized roles are unaffected. Repeat the preview and confirm zero proposed changes.

For an application rollback, restore the prior PHP/views/assets together. The additive column can remain safely while investigating; dropping it loses saved opacity choices. Restore style values from the verified backup only with a reviewed, attributed operation that preserves subsequent user edits. Never blindly restore the entire database over newer operational records.

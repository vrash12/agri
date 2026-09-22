# Map hover and overview performance

Status: locally verified September 22, 2026. Hostinger SSH authentication was rejected; production installation is blocked pending a working authorized connection. No live files or data were changed by this release attempt.

## Implementation

- `farmers-maps.js` coalesces pointer updates through its existing animation-frame scheduler, caches tooltip dimensions until content or stage size changes, and positions through CSS translation. Stored areas no longer trigger normalization of full parcel rings on hover. Identical status text avoids another DOM write.
- The hover card's moving backdrop blur was removed; its white surface, shadow and visibility transition remain.
- `municipality-boundaries.js` creates one interactive polygon per component at overview zooms. The duplicate pale casing returns at zoom 13 or when a municipality is selected. Existing culling, batched drawing, overlay reuse and label thresholds remain in effect.
- No geometry, measurements, exports, permissions, routes, dependencies or database records change.

## Evidence and limits

The six CALABARZON seed snapshots contain 142 features and 12,807 coordinate pairs. An evaluated display simplifier retained 12,648 pairs at a 100-metre tolerance with conservative topology/area guards, only about a 1% reduction. That worker/simplifier was not retained. The references are already simplified; removing duplicate overview polygons provides a more direct reduction in rendering work.

37 focused JavaScript tests pass, including hover event coalescing, dimension reuse, edge positioning, stored-area display, overview/close zoom transitions, selection, culling, edit precision, stale requests and failed saves. `MunicipalityGeofenceTest` passes 26 tests and 289 assertions using in-memory SQLite. JavaScript syntax, Blade compilation and whitespace checks pass. No PHP classes changed.

These are regression and object-count checks, not measured browser FPS or a promise of twice the speed. Interactive Google Maps profiling is still required on the owner's device. Initial geofence HTML still embeds all authorized current geometry, and selected municipality details still classify all its parcels. Adding many more regions will need a separate scoped viewport endpoint and zoom-dependent display payloads; current changes do not remove that server/network limit. Vector tiles or a WebGL overlay should be considered only after profiling shows the existing renderer is the limiting factor.

## Release

Push the reviewed commit to GitHub main, then use Hostinger `git pull --ff-only origin main` after checking the live tree and creating private database/application backups. Mirror `public/js/farmers-maps.js` and `public/js/municipality-boundaries.js` to their corresponding `public_html/js` paths. Install `resources/views/farmers/maps.blade.php` and refresh view caches. Existing asset URLs use file modification times. No migration, import, Composer or npm operation is needed. Verify both script copies and HTTP responses, and reload existing map tabs.

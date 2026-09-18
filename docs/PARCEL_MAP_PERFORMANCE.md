# 3D parcel map performance

## Change

Browsing all Ramos parcels created 2,088 interactive Google Maps elements for 1,044 plots: each plot had both a visible polygon and an invisible clickable polyline. Initial rendering also scanned previously drawn overlays for every farmer and mounted the collection in one uninterrupted task.

The renderer now:

- creates one clickable polygon per parcel;
- yields between initial drawing batches and later visibility/detail updates;
- reuses overlays when reopening a farmer whose cached plots have not changed;
- uses one-metre display simplification in the overview, retaining source vertices and rejecting changes above 2% in area or invalid topology;
- restores full coordinates for the selected farmer and nearby parcels when the camera range is 2,500 metres or less;
- keeps every loaded parcel available; distant parcels retain lighter paths rather than disappearing;
- coalesces hover-card positioning into animation frames and suppresses duplicate activation events;
- cancels stale refresh batches and respects editing, visibility, focus and refreshed-record state.

No database, permissions, municipality scope, endpoint limit, measured area, export geometry or edit geometry changes. The helper is loaded before `farmers-maps.js` using the existing file-modification cache versioning. No package or migration is needed.

Camera updates use the documented [Google Maps 3D change events](https://developers.google.com/maps/documentation/javascript/reference/3d-map). The map's `bounds` property is a camera restriction, so it is not used as the visible viewport. Nearby-detail selection is conservative; it never culls parcels.

## Local measurements

The existing Ramos collection has 1,044 plots, 697 farmers and 116,028 source vertices. Median vertices per plot: 99; 95th percentile: 246; maximum: 384.

| Drawing workload | Before | After, overview |
| --- | ---: | ---: |
| Interactive parcel elements | 2,088 | 1,044 |
| Parcel path vertices | 232,056 | 26,299 |

The overview reduces path vertices by 88.7%. This measures geometry workload, not a claim of an equivalent frame-rate improvement. 144 shapes retain all their points because simplification would not safely improve them. The source geometry was checked unchanged after the benchmark.

The full all-plots response remains about 5.99 MB before compression. Three local controller measurements were 772–847 ms, with 34–45 ms spent in database queries. This change targets map interaction and rendering; slow rural connections can still delay the initial download. The endpoint's existing maximum and truncation warning remain in effect.

## Verification

- Pure geometry tests cover convex and concave shapes, winding, closure, invalid inputs, tiny parcels, source immutability, conservative tolerance and area/topology fallbacks.
- Renderer tests execute the real functions against a Maps DOM adapter with 1,044 synthetic parcels. They cover one overlay per parcel, yielding between batches, full-coordinate cache preservation, overlay reuse/replacement, close inspection, focus/reset, editing visibility and stale-batch cancellation.
- Tests use synthetic coordinates and no operational farmer data or provider credentials.
- JavaScript syntax and Blade compilation are checked separately.
- The local browser reached the sign-in page, so actual Google Maps frame rate and pointer interaction still need a signed-in browser check. Automated renderer checks do not measure Google's GPU implementation or satellite tile loading.

Numeric-only local profiling and display benchmarks are in ignored private application storage under `storage/app/import-audits/map-performance-20260918`. No production deployment was performed.

## Release

Deploy both map scripts and the updated `farmers/partials/maps-scripts` Blade partial together, then rebuild Blade views. Existing file versioning refreshes browser assets when the page reloads. Rollback restores these files together; no database rollback is required.

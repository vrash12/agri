# Planted crop areas inside a parcel

## Staff workflow

In Farmers, open a saved plot's **Crop areas / season** action. Choose the year
and dry/wet season, select Corn or another crop, and draw corners inside the
white parcel outline. Finish the boundary and repeat for other planted sections.
Optional section names and seed/crop varieties describe the entered record;
there is no automatic seed-release or harvest linkage.

Adjacent sections may share edges. Overlapping sections are rejected: for
intercropping, draw one section classified as Mixed crops. Up to eight simple
areas with fifty corners each are supported. Parcel boundaries above 1,000
points require review before using this editor; the application does not
simplify or change those parcel records. Mapped hectares are approximate,
calculated server-side from the drawing, not manually supplied.

Save the season to persist all areas atomically. Its whole-parcel summary is
computed from the area crops (one crop or Mixed crops). Empty drawings restore
the existing classification-only workflow. A request without the new drawing
field preserves prior drawings. Names, varieties and corners can be adjusted;
removal takes effect only when the season is saved. Existing plot geometry,
colors, QR routes, identity records and geography ownership remain unchanged.

Enable **Crops by season** on the Farmers map and select a farmer. Section
outlines load for up to twenty plots; for additional plots use Focus on the
individual plot. Crop filters include matching sections of Mixed-crop parcels.
Selecting a section shows its crop/name/variety/approximate area and briefly
highlights its boundary. Reduced motion disables that highlight. Unmarked
land remains unclassified, and neither animations nor recorded crop labels
confirm live planting, seed delivery or measured production. These boundaries
are private office records; the public QR and farmer-portal maps are unchanged.

## Architecture, safety and performance

The reviewed `2026_10_08_000100_add_planted_areas_to_parcel_crop_seasons.php`
migration adds nullable JSON to the existing season table. Existing records
and the unique plot/year/season constraint are preserved. `PlantedCropAreas`
uses `GeoGeometry` for exact validated rings, inclusive containment and
non-overlap. No drawing is silently clipped or simplified. GeoGeometry's
existing default behavior is unchanged; the new explicit false argument
disables simplification for this containment workflow.

Request bounds: eight areas, fifty coordinates per area, 80-character names
and varieties, and a 100 KB serialized drawing limit. Coordinates, crop enums
and parent/season versions are validated. Server scope comes from the plot's
farmer; foreign municipality input cannot redirect ownership. Existing policies,
CSRF, shared mutation locks, the parent row lock and optimistic tokens apply.
System Owner/Super Admin crop oversight remains read-only; veterinary accounts
cannot use this module.

Audit events summarize area counts and crops without copying private section
coordinates. Edits to the parent parcel invalidate containment on read: the
detail layer hides affected sections and returns an office-review flag without
deleting records. Missing migration and map/network failures are explicit,
and saved sections remain in the editor list when satellite mapping fails.

The authenticated `farm-plots.planted-area-layer` endpoint is throttled to 30
requests/minute, accepts at most twenty plot IDs, and returns no other owners'
data. Payloads are bounded to 8,000 section corners. The broad crop classifier
extracts crop names only on MySQL/MariaDB, omitting drawing geometry. Browser
requests are selected-owner only, cached by plot/year/season, cancel stale
responses and reuse GPU overlays during camera movement. No continuous
animation or global crop-area download is added.

The editor uses supported Maps Polygon paths and map-click events, rather than
the removed DrawingManager. Reference: [Google Maps shapes](https://developers.google.com/maps/documentation/javascript/shapes)
and [Drawing Library removal](https://developers.google.com/maps/documentation/javascript/reference/drawing).
No new GIS package or provider credentials are required.

## Verification and deployment

Focused tests cover adjacent corn/rice sections, overlap, outside/concave-edge
escape, holes, invalid coordinates, version conflicts, partial parent changes,
scoped read/write access, JSON submissions, audit redaction and migration
reversal. A synthetic headless Maps adapter checks drawing, independent crops,
serialization, outside-point refusal, undo/cancel/removal and desktop/390px
layout. Real satellite/provider rendering has not been exercised locally.

Verification passed 47 crop/geometry/workspace PHP tests / 418 assertions and
22 JavaScript layer/map tests. The preceding multi-animal work also still
passes its 13 tests / 68 assertions after the shared audit-observer change.
Pint, syntax, Blade compilation, routes and whitespace checks passed. Tests
used disposable SQLite and synthetic Maps adapters on local PHP 8.4.10;
Hostinger PHP 8.3, the MySQL JSON-extraction path and real Google satellite
interaction remain deployment checks.

Deployed with the multi-animal service follow-up in runtime `571a408` on
October 8, 2026 after explicit owner authorization. For future releases, back up production,
pull the reviewed GitHub revision, and apply **only** this additive migration
by path. Mirror `planted-area-editor.js`, `planted-area-layer.js`,
`parcel-crop-layer.js` and `farmers-maps.js` to `public_html/js` with readable
0644 permissions, then rebuild configuration/routes/views. Do not run baseline
migrations, seeders or account operations. Verify existing rows and polygons,
authorized drawing/save/season filtering and served asset hashes. Schema
rollback drops the new drawings, so preserve them privately before reversing;
legacy classifications remain. Only the targeted additive schema migration ran during this release; no
account, seed, parcel-boundary or operational-record changes ran.


## October 9 empty-draft correction

A newly created Google Maps polygon with `paths: []` can have no first ring,
so `getPath()` is undefined. The editor now calls `setPath(new MVCArray())`
before reading or adding draft corners. This preserves the drawing workflow,
containment checks, stored boundaries and save payload. Three editor regressions
model the provider's missing-ring behavior and cover start, finish, serialization,
undo, outside-point refusal, cancellation/restart, unfinished-submit refusal and
the fifty-corner limit. All 25 focused JavaScript tests and syntax/whitespace
checks pass. No migration or record conversion is needed.

Correction deployed on Hostinger in runtime `437b645` on October 9, 2026.
Existing records, including crop drawings, and environment were preserved;
the versioned live editor script matches both public copies. No migration ran.


## October 9 workspace design follow-up — deployed

The season page uses a three-part flow: choose season, mark planted areas, save.
A larger map sits beside crop controls on desktop; phone layouts put crop controls
before the map and recorded areas after it. Drawing actions appear only during a
draft; edit controls identify the selected area. Optional area names/varieties,
keyboard guidance, season notes and earlier records use native disclosures.
Validation errors reveal their record fields, period pagination keeps history
open, and parent-version conflicts provide a reload link. A sticky Save season
bar reports draft/unsaved state. Crop summaries follow completed sections.

The existing routes, policies, CSRF, scope/version tokens, stored parcel boundaries
and exact server containment validation are unchanged. New stylesheet:
`public/css/planted-area-editor.css`; changed seasonal page/editor partial and
`public/js/planted-area-editor.js` must deploy together. Mirror the stylesheet and
script to both Hostinger public directories and refresh Blade views. No migration
or production data change is required. This design follow-up deployed October 9 in runtime `b2bc46b`.

Verification: 18 seasonal crop PHP tests / 143 assertions, four editor JavaScript
regressions (including contextual actions and automatic crop summaries), the prior
22 layer/map tests, syntax, Pint, Blade compilation and whitespace checks. Synthetic
browser checks passed independent corn/rice drawings, serialization, outside-point
refusal, cancel/removal and desktop/390px layout without editor overflow. Google
satellite rendering and real-user interaction have not been repeated.


## Illustrated crop badges and selection motion — deployed

`public/js/crop-area-badges.js` supplies code-native, colored SVG crop stickers
for rice, corn, vegetables, roots, fruit, legumes, mixed and other crops. Known
crop codes select static paths; labels use text nodes. No external illustration
service, new package, credentials or public farmer data is used.

A bounded scan of at most 50 corners chooses an interior geographic anchor for
concave polygons rather than assuming the centroid is inside. Editor badges use
Google Maps OverlayView, move after boundary edits, select the area by button or
keyboard, and hide during drawing or when the projected area is too small.
Selection runs two gentle bounce cycles and the existing boundary highlight;
reduced-motion skips both. Repeated selection cancels the previous badge animation.
No idle animation loop or camera-driven geometry recomputation is introduced.

The selected-owner 3D workspace uses the existing SVG template marker mechanism
for passive crop labels, at most one per section. It reuses markers with polygons,
obeys crop filters, edit/visibility state and clears on selection changes. Labels
are hidden beyond 6,000 meters camera range and use optional collision handling.
Select the colored boundary for its crop/variety and brief highlight. Badges mark
recorded crop areas, not live growth or officially surveyed boundaries.

All 31 focused JavaScript tests pass, including anchor containment, unsafe input,
SVG/text safety, relocation/cleanup, reduced motion, finite animation, marker
reuse, filters and range visibility. Synthetic desktop/390px browser checks confirm
corn/rice icons inside their areas, drawing, serialization, removal and no overflow.
Real Google Maps marker rendering remains an integration check. Install the new
helper in both public directories and load it before editor/workspace scripts;
include the pending workspace stylesheet and view changes. No migration or record
change is required. This follow-up deployed October 9 in runtime `b2bc46b`.

References: [Google Maps custom overlays](https://developers.google.com/maps/documentation/javascript/customoverlays)
and [3D marker graphics](https://developers.google.com/maps/documentation/javascript/3d/marker-graphics).


## Numbered draft corners and connected lines — deployed

`public/js/map-drawing-guide.js` makes the first corner immediately visible as a
numbered green dot. A blue open line connects the second and later clicks, with a
numbered dot at every crop-area corner. The editor reuses dot overlays as points
change; undo, finish and cancel remove the corresponding dots/line. Guide marks
are display-only and are never added to saved geometry. The crop area limit,
containment/non-overlap checks and minimum of three corners remain unchanged.

The main parcel tool uses the same numbered SVG dot artwork. New parcel creation
now accepts consecutive map clicks beyond the fourth corner until staff save or
cancel. Existing-parcel edits continue to insert points through edges or move a
selected corner; overlap checks remain intact. The first dot is green, other dots
blue and a selected parcel corner yellow. Existing dot/line cleanup is preserved.
Load the new guide before both map scripts and mirror it to both public directories
when deploying the pending interface release. No migration or production data
operation is required; this follow-up deployed October 9 in runtime `b2bc46b`.

Focused tests cover immediate first-dot feedback, second-click line creation,
numbering, overlay reuse, undo/cancel cleanup, Maps LatLng inputs, unchanged source
coordinates, a fifth parcel click and existing edit-mode behavior. Synthetic browser
screenshots verify the first two crop corners and guide cleanup after finishing.


## Saved icons on the main map — deployed

Saved crop-area outlines and icons now load automatically for the selected
farmer, independently of the optional parcel-classification recoloring layer.
The same bounded authenticated endpoint, selected-owner cache, retry/error states,
visibility/edit controls and zoom/collision limits remain. Clearing the selected
farmer hides cached crop icons immediately; no global crop geometry is requested.
Year/season controls apply to these records even when saved parcel colors are used.

Back to parcel map carries the editor's municipality, farmer, year and season.
The map validates its `map_farmer`, `crop_year` and `crop_season` query context,
then opens that farmer through the existing authorized detail workflow after
parcel loading. Scope checks remain server-side; URL values do not grant access.
Each crop icon comes from a persisted crop-area record for that period. Unknown
or unrecorded areas do not gain an invented crop icon.

Verification: 19 PHP season tests / 151 assertions, including save/reopen/return
context and the selected-period geometry response, and 37 JavaScript tests,
including automatic loading with recoloring off, owner-only selection, clearing
cached labels, period validation and overlay reuse. No migration or data conversion.
The editor view, crop controls, badge/guide helpers, stylesheet and map scripts
deployed together in runtime `b2bc46b`. See `GITHUB_DEPLOYMENT.md` for the verified
backup, asset hashes, preserved records and integration limits.

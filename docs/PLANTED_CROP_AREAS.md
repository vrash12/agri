# Planted crop areas inside a parcel

## White parcel bases in every Farmers scope — deployed October 14, 2026

Runtime `8861fda` is installed through GitHub main and Hostinger fast-forward pull,
including whole-map drawings/icons and direct harvest attribution. The targeted
additive migration created the link columns and backfilled 3 stable section keys
across 2 existing crop-season rows. Runtime, schema and versioned public assets
were verified; signed-in Maps/NDVI acceptance remains pending. See
[deployment receipt](GITHUB_DEPLOYMENT.md) and
[acceptance checklist](MAP_RELEASE_ACCEPTANCE_CHECKLIST.md). Earlier local-status
paragraphs below describe the implementation stage before this release.

Every saved parcel base on the Farmers map now uses a white outline and pale
white fill, including parcels with only a saved crop classification. This
applies to all loaded parcels in municipality and broader authorized scopes;
the earlier Ramos gate and classification recoloring no longer control the
base. Saved crop icons, lines and separate shaded sections retain their crop
colors above it. Crop filters, hover feedback, focus/hide controls and edit
visibility remain intact. Original `FarmPlot.color`, full geometry, crop data,
hectares and parcel exports are unchanged.

The controls read **White parcels with saved crops** and **Filter parcels by
saved crop**. The white parcel legend describes the base; colored crop legend
entries describe saved classifications and loaded-parcel counts. A failed crop
request shows safe Retry guidance while the parcel base stays white; white is
not a claim that a crop record is missing.

This source change is local, not pushed or deployed. Release it with the pending
whole-map crop-drawing work: provider, route, `planted-area-layer.js`,
`farmers-maps.js`, `crop-area-badges.js`, `parcel-crop-layer.js`,
`resources/views/farmers/maps.blade.php`, crop controls, reviewed Assistant
passages and guidance together. The classifier script changes safe error copy;
the Farmers view supplies the white legend and removes the Ramos gate. Mirror
changed scripts to both Hostinger public directories with 0644 permissions and
rebuild route/config/view caches. No migration, account/provider change, budget
reset or general cache clear is required. Earlier dated verification counts
below describe their original implementation stages.

October 14 checks: all 250 JavaScript tests passed, including white parcel
styling, hover values, classification changes, crop visibility and unchanged
stored geometry/colors. Five workspace presentation tests passed with 32
assertions; 64 reviewed knowledge tests passed with 483 assertions. Existing
missing `.env.testing` warnings remain. Pint, JavaScript syntax, Blade
compilation and whitespace checks passed. Live-provider visual verification
was not repeated; the earlier local check lacked a valid Maps key. No push,
deployment or production data operation ran.

## Whole-map crop drawings — October 13, 2026 (local, not deployed)

Saved crop lines, enclosed shading and legacy polygons now appear across the
loaded Farmers parcel map for the chosen year and season, without selecting an
owner first. Individual section icons appear when zoomed in; farther away,
the existing grouped parcel icons show all known saved crops. Clicking a section
opens its farmer's details while keeping the rest of the map visible. Clearing
selection keeps crop drawings visible; focus, hiding, editing and filters still
control visibility. Missing records do not acquire inferred crops.

The implementation follows three steps: discover drawing IDs using existing
scoped classification batches (200 IDs), retrieve those drawings in sequential
20-ID detail batches, then reconcile unchanged records/parcel rings and reuse
their GPU objects. Drawing and visibility work yield between browser frames.
No detail request runs for classification-only or unrecorded parcels. Each
detail response retains the existing 8-mark/50-point/8,000-coordinate bounds;
total coverage is the already loaded map, including its existing parcel cap.

Completed batches remain visible after a later failure. Explicit Retry loads
only unfinished batches; camera movement and elapsed time never retry errors.
HTTP 429 respects a bounded Retry-After wait. A requested refresh remains pending
through that wait. Period/scope changes and removed parcels discard stale data;
late HTTP responses and drawing frames cannot restore it. Session expiry clears
accepted private drawings. The named `planted-crop-layer` limiter keeps the
existing 30 requests/minute per account, isolated from map and satellite limits.

Custom crop graphics now pass the HTML template itself to Marker3DElement,
matching [Google's inline SVG example](https://developers.google.com/maps/documentation/javascript/3d/marker-graphics).
Both detail and grouped overview badges use this contract. The October 14
presentation keeps every saved parcel base white beneath crop shading and
icons, including classification-only parcels. No saved geometry, import color,
crop record, ownership or hectare measurement is rewritten.

Release the combined white-parcel/whole-map bundle listed above. Mirror changed
scripts to both Hostinger public directories, retain 0644 permissions and
rebuild route/config/view caches. No migration, account, provider setting or
general cache clear is required. Older sections describe earlier releases.

October 13 verification: all 250 JavaScript regressions passed, including 64-owner
progressive rendering, batch reuse, focus/hide/filter behavior, cancelled frames,
period changes, partial retry and the 3D template contract. Crop-season tests
passed 24 cases / 279 assertions; satellite tests passed 29 / 208; reviewed
Assistant knowledge/RAG tests passed 99 / 729. Existing missing `.env.testing`
warnings remain. Pint, syntax, Blade, the scoped route/middleware listing and
whitespace checks passed. A browser check used the current renderer with only
synthetic parcels; it reached the drawing-ready state, but Google reported
NoApiKeys/InvalidKey, so tile/icon visual acceptance could not be confirmed.
The temporary server was stopped. This work is not pushed or deployed, and no
production database, account or environment operation ran.

## Irregular parcel line guard — October 12, 2026 (local, not deployed)

The editor checks every segment between crop-line points before accepting it.
This matters for concave or notched parcels: two points can both be inside while
their straight connecting chord leaves the parcel. The editor now rejects that
point with guidance to add a point around the bend or along the white outline,
and restores the last accepted geometry when an existing point is dragged out.
The server remains authoritative and preserves the same full-segment containment
rule; boundary contact remains allowed. No line is silently clipped or moved.

Whole-map parcel crop badges now use visible terrain/label settings. Known saved
crop icons remain visible above parcel bases before a farmer is
selected; missing crop records still produce no icon. This does not infer crops
or alter stored parcel/crop data.

## Earlier Ramos parcel presentation — October 12, 2026 (superseded local stage)

At this stage, the Ramos Farmers map used a display-only white base for imported
parcel polygons without a saved crop record. Parcels with saved Corn, Rice or
other crop classifications used the reviewed crop color, and selected-farmer
crop sections rendered colored shading above the parcel. The original
`FarmPlot.color`, geometry, records and exports were preserved. The October 14
local follow-up supersedes that base presentation across every Farmers scope.

The white override was limited to the selected Ramos municipality workspace.
Crop filtering used saved classifications. The map script, Farmers view and
crop controls belonged to that stage's shading/overlap bundle. Verification: all 235
JavaScript tests passed, the 22 crop-season tests completed with 185 assertions
and existing `.env.testing` warnings, and Blade compiled successfully. Live
Google Maps visual acceptance remains outstanding.

## Non-overlapping crop sections — October 12, 2026 (local, not deployed)

The editor checks positive-area overlap between displayable line enclosures and
legacy polygon sections. Shared edges/points are allowed. An overlapping draft
cannot finish and remains available for Undo/Cancel. A conflicting point edit
restores the last accepted coordinates. Save checks the entire list, and server
validation independently checks valid enclosures in either record order,
including identical, contained and aligned-sliver overlap. These checks do not
convert stored lines or calculate hectares.

Previously conflicting saved records are not rewritten: the editor and selected
Farmers map withhold their fills, preserve outlines/icons and show a correction
message. Remove or redraw conflicting marks before saving. Two-point or invalid
enclosures remain visual lines and do not claim exclusive planted hectares.

Release `PlantedCropAreas.php`, the three shading scripts, editor help and guides
together with the shading follow-up below. No migration, account, environment or
automatic data operation is required. Browser tests use synthetic Maps adapters;
live-provider visual acceptance remains outstanding.

Verification: 234 JavaScript regressions passed; all 22 crop-season PHP tests
completed with 185 assertions and no failures (existing missing `.env.testing`
warnings). Pint, JavaScript/PHP syntax, Blade compilation and whitespace checks
passed. No production operation was performed.

## Crop-section shading — October 12, 2026 (local, not deployed)

The owner selected “Fill the enclosed section.” A visual line with at least
three non-crossing corners can now display as a shaded crop section. The ends
are joined for display only after checking the full closing edge against the
canonical parcel (including concave boundaries). Invalid enclosures remain
lines. The shared bounded helper does not change stored points, convert the
record type or calculate hectares. Existing polygon areas also use the stronger
38% fill. The editor updates and removes shading when points change or it closes.

Selected-farmer 3D crop badges use an interior anchor for shaded marks and
REQUIRED collision behavior with terrain occlusion disabled. This trades some
possible label overlap for visibility while inspecting a farmer. Overview badges
retain optional collision handling and existing batching. Horizontal and vertical
2D lines now retain their icons when their longest screen dimension is readable.
Drawing instructions explain enclosed shading versus two-point lines.

Release `crop-area-badges.js`, `farmers-maps.js`, `planted-area-editor.js` and the
editor help partial together, mirroring scripts to both Hostinger public
directories. File-mtime versions invalidate these assets. No migration, account,
provider configuration, record rewrite or general cache clear is needed.

All 231 JavaScript tests passed. Synthetic regressions cover safe enclosure, crossing and concave rejection,
unchanged data, editor repainting, interior anchors, terrain/collision options,
horizontal line icons and existing overlay reuse/cleanup. Live Google Maps
visual acceptance remains outstanding; adapter tests do not verify live tiles.

## Automatic overview icons — October 12, 2026 (deployed in `89ff3d1`)

Opening the Farmers map loads saved crop classifications for every loaded
parcel in scoped batches of at most 200 IDs. Icons appear without selecting a
farmer and without enabling crop recoloring. A parcel badge groups up to eight
distinct known crop symbols for the chosen year/season; missing records remain
without an icon. Collision handling separates nearby badges when zooming in.

This overview uses crop metadata and parcel geometry already loaded on the map,
not a global crop-section geometry request. One cached interior anchor and
marker per parcel are reused during camera refreshes within the existing render
budget. Detailed crop geometry remains selected-farmer only, and available detail
icons replace matching overview symbols. Hidden/edited/deleted parcels and
period changes remove their stale icons. Saved parcel colors and coordinates
are preserved. No schema, account or provider configuration changes are needed.

Deploy `parcel-crop-layer.js`, `crop-area-badges.js`, `farmers-maps.js`, the crop
controls partial and reviewed guidance together with the pending freehand crop
work. Mirror changed public assets to both Hostinger public directories. Never
clear the general cache. Live Google Maps visual acceptance has not been repeated.

Verification: all 228 JavaScript regressions passed, including no-click loading,
marker reuse, mixed crops, scope/period changes and cleanup. Crop/geometry checks
passed 25 tests / 177 assertions, and reviewed RAG checks passed 35 tests / 246
assertions with existing file-cache warnings. Syntax, Pint, Blade compilation
and whitespace checks passed. Maps tests use synthetic adapters, not live tiles.

## Freehand crop marks — October 12, 2026 (deployed in `89ff3d1`)

Staff can draw a colored crop line with two or more ordered points along the
planted rows or section. The line stays inside the saved parcel, is stored as a
visual mark, and does not calculate hectares. Unplanted land can remain blank.
Existing enclosed polygon crop areas remain readable and editable, so this is
backward-compatible with previously recorded seasons. The server validates the
line, point count and municipality/parcel scope before saving.

The coordinated release includes `GeoGeometry`, `PlantedCropAreas`, the seasonal
request validation, the editor, crop-area layer, crop badges, Farmers map, both
seasonal views, the reviewed Assistant guidance and focused tests. No migration
or data conversion is required.

## Verified Hostinger release — October 12, 2026 — runtime `d89aa10`

The in-page crop dialog correction is deployed through GitHub and Hostinger.
The editor now resolves its container-scoped save status safely, avoiding the
null `textContent` error when opening a crop workspace. No schema, account or
record change ran; the updated editor script and regression test were released
together.

## October 12 modal correction — local

The in-page dialog mounts the editor inside its own container while the season
save status is owned by the surrounding form. The editor now searches its
container first and safely falls back to that form-level control, so opening a
crop workspace no longer fails with a null `textContent` error. The standalone
seasonal-crop page remains unchanged. Deploy the updated
`public/js/planted-area-editor.js` and its regression test together; no schema,
account or record change is required.

## Staff workflow

In Farmers, open a saved plot's **Crop areas / season** action. The editor opens
inside an accessible dialog over the map, so the selected parcel stays visible.
The standalone seasonal-crop page remains available when the dialog cannot load.
Choose the year
and dry/wet season, select Corn or another crop, and draw corners inside the
white parcel outline. Finish the boundary and repeat for other planted sections.
Optional section names and seed/crop varieties describe the entered record. Each
saved section carries an opaque server-generated key so a direct harvest can be
linked to the exact saved section from the Harvest records form. The link is an
attribution only: it does not infer hectares, quantity, yield or a seed release.
The harvest must use the same farmer, parcel, year, season and compatible crop;
release-projected rice harvests remain unlinked. A linked section cannot be
removed or reclassified until its harvest link is cleared.
After saving a season, staff can use **Record harvest** beside a section to open
the direct harvest form with the farmer, parcel, section, compatible commodity,
year and season already selected.

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

Saved section outlines, shading and icons appear across loaded parcels for the
selected year/season without farmer selection. Open **Crops on the map** to
change the period or choose **Filter parcels by saved crop**. Details load in
sequential batches of up to twenty plots; crop filters include matching
sections of Mixed-crop parcels. Parcel bases stay white in every scope.
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

The authenticated `farm-plots.planted-area-layer` endpoint uses the named
`planted-crop-layer` limit of 30 requests/minute per account, accepts at most
twenty plot IDs and returns only authorized requested parcels, across owners
within the permitted scope. Payloads are bounded to 8,000 section corners. The broad crop classifier
extracts crop names only on MySQL/MariaDB, omitting drawing geometry. Browser
requests include drawings discovered on loaded map parcels, cache by
plot/year/season, cancel stale responses and reuse GPU overlays during camera
movement. No continuous animation or unrestricted registry download is added.

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
are display-only and are never added to saved geometry. Crop mark limits and
parcel containment remain enforced. New visual lines need two points; older
enclosed areas retain their three-point minimum and non-overlap checks.

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


## Saved icons on the main map — deployed October 9, 2026 (earlier behavior)

This receipt describes the original selected-farmer behavior in `b2bc46b`.
The current local whole-map and white-parcel follow-ups above supersede its
selection and parcel-base presentation; the deployed verification is retained.

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

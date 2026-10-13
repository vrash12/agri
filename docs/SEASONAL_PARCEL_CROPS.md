# Seasonal parcel crops — 19 September 2026

The October 14 local follow-up keeps all saved parcel bases white across every
Farmers map scope, including classification-only parcels. Crop icons and
separate crop sections retain their colors; crop filters, hover and edit
visibility retain their behavior. Stored colors, geometry, hectares and export
data are unchanged. The source is not pushed or deployed and will release with
the pending whole-map crop-drawing work. That bundle includes provider, route,
`planted-area-layer.js`, `farmers-maps.js`, `crop-area-badges.js`,
`parcel-crop-layer.js`, `farmers/maps.blade.php`, crop controls, reviewed
Assistant passages and guidance. No migration, account/provider change, budget
reset or general cache clear is required.

The October 13 local follow-up shows saved crop drawings across loaded parcels
without selecting a farmer. Details load in 20-parcel batches after scoped crop
metadata is ready; classification-only parcels retain their crop icons. See
[planted crop areas](PLANTED_CROP_AREAS.md) for limits and release requirements.

## Use the feature

1. Open **Farmers → Parcel Map** and choose the municipality workspace.
2. Select a farmer. Use **Seasonal crops** beside a saved parcel.
3. Choose a year and **Dry season** or **Wet season**, then **Load season**.
4. Record the crop, optionally add crop/source details, and save. Choose **Mixed
   crops** for intercropping or several crops in that season. Corrections replace
   that season's classification, with changes recorded in the audit trail.
5. Back on the map, expand **Crops on the map**, choose the year and season,
   then **Apply layer**. Saved crop icons and drawings appear automatically.
   Choose **Filter parcels by saved crop** and an optional crop filter to limit
   the visible parcels; **White parcels with saved crops** removes that filter.

The crop legend uses labeled crop colors and loaded-parcel counts; the parcel
legend describes white outlines and pale white fills. Hover cards identify the
selected period and recorded crop when crop filtering is enabled. Parcel bases
stay white in both modes, including parcels with only a crop classification.
Apply stays disabled until the map initializes. Failed crop requests show safe
error guidance and Retry; the white parcel base does not imply missing crop data.

## Meaning and limits

- Rice/palay, corn, vegetables, root crops, fruit, legumes, mixed, other, and not
  recorded are controlled classifications. Crop notes describe varieties or sources.
- Year and season are reporting labels explicitly chosen by staff. No date range
  or planting calendar is inferred, and records are not carried into another season.
- **Not recorded** means unknown, not fallow, and produces no crop icon. An explicit correction to Not
  recorded is retained as a record and audited.
- A seasonal classification does not establish what is growing today, planted
  hectares, yield, or harvest volume. Mixed crops do not allocate area per crop.
- Farmer-level assistance and harvest data are not backfilled into particular
  parcels. A staff member may explicitly attribute a direct harvest to one saved
  planted section from the matching farmer/parcel/year/season; this does not infer
  production, hectares or yield. Release-projected harvests remain unlinked.
- Counts cover loaded parcels, subject to the existing map payload cap. Farmer
  selection, visibility controls, and crop filtering can display fewer parcels.
  These are not municipality-wide crop totals or a crop-area report.
- Crop colors appear on icons and separate saved sections above white parcel
  bases. Stored parcel colors, exact geometry, measurements, editing and parcel
  exports retain their existing behavior.

## Ownership and persistence

`parcel_crop_seasons` has municipality and plot foreign keys, a unique
`farm_plot_id/crop_year/season` key, and a municipality/period/crop index. Deleting
a parcel cascades its crop records. Deleting a recording user retains the record
with a null recorder reference. There is no separate crop-record delete action.

Municipal staff/heads and provincial agriculture staff can record crops only for
authorized parcels. Municipality ownership comes from the current parcel's farmer,
never submitted municipality input. The System Owner and province Super Admins
can read within their scope; veterinary accounts cannot access this module.

The Form Request authorizes before validation. The service locks the parent parcel
through `ConcurrentWrite`, rechecks authorization inside the transaction, and
rejects stale record versions, including two users making the first entry. The
existing audit observer records creation and updates with municipality scope.

Each saved planted section has a stable opaque key inside the JSON drawing. The
Harvest records form lists keyed sections for the selected farmer's parcels. The
server rechecks section ownership, period and crop compatibility, and blocks
removing or reclassifying a section while a harvest still references it.

The authenticated `GET /farm-plots/crop-layer` accepts a year, season, and at most
200 distinct plot IDs. It scopes parcels before returning only IDs, crop labels,
and colors; it excludes notes, geometry, and personal farmer fields. Parcels whose
farmer is missing are excluded, and a missing-parent form returns 404. Inconsistent
stored crop ownership is not exposed as a valid classification.

`public/js/parcel-crop-layer.js` requests IDs in bounded batches and discards stale
responses after period/workspace changes. Renderer refreshes use the existing
batched overlays and retain hover styles, focus, visibility, and edit behavior.
Interactive polygon paths are assigned after construction. The current Google
Maps beta threw an internal listener error when `path` was supplied in the
constructor; the full-map preview reproduced the error and verified this fix.
Google's [polygon reference](https://developers.google.com/maps/documentation/javascript/reference/3d-map-draw)
documents `path` as an assignable polygon property.

## Original deployment — September 19, 2026 (historical receipt)

The steps and verification below describe the original classification-color
release. The current white-parcel/whole-map follow-up uses the combined bundle
above and requires no new migration; its controls no longer restore colored
parcel bases.

No new environment variable, dependency, API, or asset build is required. This
feature was deployed to Hostinger on 2026-09-19 at 12:03:09 UTC (20:03 Philippine
time). See `SEASONAL_CROPS_DEPLOYMENT_2026_09_19.md` for the release receipt.

1. Back up the target database and confirm the existing parent IDs are unsigned
   BIGINT columns compatible with the migration's foreign keys.
2. Upload the migration and run only its explicit path. Do not run all pending
   legacy migrations:

   ```sh
   php artisan migrate --path=database/migrations/2026_09_19_000100_create_parcel_crop_seasons_table.php --force
   ```

3. Deploy the model, policy, Form Request, controller, support service, provider
   registrations, audit observer registration, and routes together.
4. Deploy `resources/views/farm_plots/seasonal_crops.blade.php`, the new crop-layer
   controls partial, and the updated farmer map view and scripts partial.
5. Deploy `public/js/parcel-crop-layer.js` and `public/js/farmers-maps.js` to both
   Hostinger public directories. Then clear compiled views and refresh route caches
   according to the deployment's existing cache setup.
6. Verify staff entry, read-only oversight, municipality isolation, both seasons,
   crop filtering, restoring saved colors, and map interaction while signed in.

Rollback application files together and retain the additive table and its records.
The migration's `down()` removes the entire crop table; do not run it on recorded
data without a backup and explicit data-removal authorization.

## Original verification — September 19, 2026

Automated feature tests use an isolated in-memory SQLite database and cover saves,
corrections, exact periods, missing records, mixed crops, validation, scoped access,
read-only oversight, guest/veterinary/inactive denial, inconsistent ownership,
stale writes, audit events, unchanged parcel attributes, and migration constraints.
JavaScript tests exercise batch sizes, stale replies, layer disable/re-enable,
incomplete responses, workspace changes, and the existing renderer integration.

Verification passed: 14 focused PHP tests, 23 JavaScript tests, Pint on all 11
changed PHP files, syntax checks, Blade compilation, route verification, and
whitespace checks. PHP's existing vendor deprecation notices do not fail the tests.

Browser checks used synthetic records in a temporary local preview. The form and
controls were reviewed at desktop and phone widths, with no page overflow at 320
or 390 pixels. The year/season form loaded the selected recorded season. The full
workspace loaded actual Google 3D parcels: dry-season rice/unknown colors and the
rice-only filter worked, changing to a recorded wet season displayed mixed/unknown
colors, and switching the layer off restored the saved colors. Polygon paths
remained unchanged. The controls occupy a full toolbar row, and Retry remains
hidden outside an error state. Hostinger passed 64 read-only checks, including
eight dry/wet form renders across four roles and municipality isolation. Signed-in
browser submissions and office-data acceptance remain unverified on production.

The verified localhost MySQL database was backed up before applying this one
migration. A read-only smoke check returned Not recorded for three related existing
parcels, and the new table remained empty. No existing parcels were classified or
altered. Legacy plots without a farmer were correctly excluded.

# Map and satellite release acceptance checklist

This checklist is for the current Farmers map, whole-map crop drawing and
parcel satellite work. It records what can be verified with synthetic fixtures
and what still needs an authorized signed-in browser/provider check. It does
not authorize a production deployment, a migration or a provider-budget reset.

## Local gates (no credentials or provider calls)

Run these from a disposable local checkout. Keep any existing `.env.testing`
warning in the result; do not point PHPUnit at an office database.

```powershell
node --check public/js/farmers-maps.js
node --check public/js/parcel-crop-layer.js
node --check public/js/planted-area-layer.js
node --check public/js/crop-area-badges.js
node --check public/js/parcel-satellite.js

node --test `
  tests/JavaScript/farmers-map-rendering.test.cjs `
  tests/JavaScript/parcel-crop-layer.test.cjs `
  tests/JavaScript/planted-area-layer.test.cjs `
  tests/JavaScript/crop-area-badges.test.cjs `
  tests/JavaScript/parcel-satellite.test.cjs

php vendor/phpunit/phpunit/phpunit --filter="ParcelCropSeasonTest|ParcelSatelliteTest|PlantedAreaGeometryTest|FarmerWorkspacePresentationTest"
php artisan view:cache
php artisan route:list
git diff --check
```

The PHP tests fake Sentinel HTTP and use synthetic SQLite records. The
JavaScript tests use Maps/DOM adapters and do not prove Google tile, GPU,
pointer or provider rendering. Keep the test output with the release receipt,
including the exact test counts and any skipped or warning cases.

`MapPayloadBoundsTest` is a separate legacy-schema gate. Its
`ProvinceScopedFixtures` expects the reviewed `provinces`/province-scope
schema, which is not part of the default in-memory SQLite setup. Run that class
only with the approved disposable MariaDB configuration described in
`docs/LARAVEL_12_AI_UPGRADE.md`; an SQLite run that reports missing `provinces`
is an isolation/setup limitation, not a map behavior result. Do not repair it
by pointing the suite at an office or production database.

## Route and asset review

Before a browser check, inspect that the rendered Farmers page points to the
versioned copies of all changed assets and that these routes remain present:

| Purpose | Route | Expected boundary |
| --- | --- | --- |
| Loaded parcel collection | `farm-plots.all` (`/farm-plots/all`) | Authenticated, municipality-scoped; response exposes `total`, `returned`, `limit` and `truncated` |
| Saved classifications | `farm-plots.crop-layer` (`/farm-plots/crop-layer`) | Authenticated; at most 200 requested plot IDs; selected year/season |
| Crop drawings | `farm-plots.planted-area-layer` (`/farm-plots/planted-area-layer`) | Authenticated; at most 20 IDs per request; named `planted-crop-layer` limit |
| Crop edit/save | `farm-plots.seasonal-crops.edit/store` | Parcel policy and record-version checks; write only for an authorized editor |
| Satellite page | `farm-plots.satellite.show` (`/farm-plots/{plot}/satellite`) | Parcel policy; modal and full-page views |
| Satellite observations | `farm-plots.satellite.analyse` | POST, named `satellite-observations` limit (6/minute) |
| Satellite image | `farm-plots.satellite.image` | GET, named `satellite-images` limit (12/minute), private PNG response |

The named satellite limits are separate from ordinary map traffic. Do not
replace them with numeric throttles or deliberately exhaust a production
account's allowance while checking the UI.

## Authorized signed-in Google Maps check

Use an approved disposable/staging office account with a valid Maps key and a
parcel that has a saved boundary, at least one saved crop classification and,
for drawing checks, a crop-area record. Record the account role and scope but
do not capture credentials or personal records in screenshots.

1. Open the Farmers map and wait for the parcel collection to reach its ready
   state. Confirm the map request is scoped to the selected office scope and
   that a partial response displays its truncation warning.
2. Confirm every parcel base has a white outline and pale white fill,
   including a classification-only parcel. Confirm the stored color is still
   shown in edit/export data and does not recolor the base. Toggle crop
   visibility, filters, hover, focus and hide controls; edit mode must hide
   overlays without changing geometry.
3. With a year/season selected, clear farmer selection. Confirm saved crop
   icons appear without selecting an owner, then wait for drawing batches. A
   later failed batch must leave completed drawings visible; Retry must request
   only unfinished batches. Change period and confirm old drawings disappear
   before the new period is accepted. Move the camera and confirm accepted
   records and GPU objects are reused.
4. Open a farmer's crop editor. Draw a two-point line and save it; verify it
   reopens as a line with no hectares claim. On a concave parcel, try a segment
   whose endpoints are inside but whose chord leaves the parcel; the editor
   must reject it with bend/boundary guidance. Drag an accepted point outside,
   cancel, and confirm the last accepted shape is restored. Reopen the record
   and confirm stored points are unchanged.
5. Open an existing polygon/enclosed section and verify its colored section and
   icon remain above the white parcel base. Test a shared edge and a positive
   overlap separately: shared edges may save; an overlap must remain blocked.
6. Repeat steps 1–5 with a read-only oversight role and a second municipality
   scope. Read-only users may inspect permitted data but cannot save; the
   second scope must not receive the first scope's parcels, crop records or
   drawing geometry.

Capture browser console/network errors, map readiness state, the selected
period, request batch sizes and whether a valid Maps key was used. If the key
is missing or Google reports `NoApiKeys`/`InvalidKey`, mark visual acceptance
blocked; adapter tests still count as local verification only.

## Authorized satellite/NDVI check

Use the same approved staging account and a valid parcel. Ensure the operator
has an explicit provider budget for one short request; do not reset counters or
use production credentials solely for this check.

1. Open the satellite modal from the parcel. Verify the page identifies the
   parcel and shows the configured/unconfigured state without exposing a client
   secret or token.
2. Submit one valid period of no more than 31 days and a bounded cloud value.
   Confirm observations render in date order, zero NDVI remains zero, and a
   fully masked day is shown as no data. Switch between NDVI and true-color
   image layers and confirm the image is private, clipped to the saved parcel,
   and labelled with its selected date/layer.
3. Submit an invalid period and an invalid layer without a provider call.
   Double-submit while a request is in flight; only one request may be sent.
4. If the provider or named route limit returns HTTP 429, verify the affected
   control shows the bounded `Retry-After` countdown and no automatic retry.
   Search and image controls have independent waits. A provider daily/monthly
   allowance message must remain distinct from a one-minute route-limit wait.
5. Repeat the parcel request as a different authorized scope and as an
   unauthorized scope. The former must remain isolated; the latter must return
   the policy denial without a provider request.

Record status codes, `Retry-After` behavior, request count, selected layer/date,
and whether the provider returned a real image. Actual Copernicus generation is
currently the remaining live provider check; synthetic HTTP fakes do not close
that item.

## Release evidence and stop conditions

Attach the local command output, route/asset review, browser observations and
the tested runtime commit to the release receipt. Stop and report the specific
blocker when any of these occurs: no authorized signed-in account, invalid Maps
key, missing provider budget, scope mismatch, a stale drawing reappears after a
period change, an unauthorized request reaches the provider, a route returns a
different throttle signature, or an image/response contains a credential or
private provider detail. Do not work around a blocker by clearing the general
cache, resetting a budget, changing account scope or editing stored geometry.

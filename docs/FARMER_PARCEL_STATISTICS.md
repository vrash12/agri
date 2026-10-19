# Farmer land and crop statistics

The office Farmers parcel map includes **Land & crops** in the selected farmer's
details. Choose a farmer or click a parcel, then scroll to the land section.
The season comes from **Crops on the map**. Changing it updates the crop charts;
parcel area remains the farmer's saved land, independent of season or map filters.

## Reading the charts

- **Saved parcels** counts distinct land records for the selected farmer.
- **Recorded land area** adds usable saved parcel hectares. It is approximate
  mapped land, not measured planted area. Missing or zero area values are shown
  as unavailable rather than quietly estimated from the boundary.
- **Saved crop types** counts distinct known crop categories for the season.
- **Crop records this season** separates parcels with a saved crop, explicitly
  no crop recorded, and unavailable data. The percentage appears only when every
  parcel has a crop classification response. No record does not mean unplanted.
- **Land by parcel** compares the six largest recorded parcels. Longer lists
  have an expandable table with 50 rows per page. A red indicator identifies the
  selected parcel; it does not describe land condition.
- **Crops on these parcels** counts each parcel once for each saved crop. A rice
  and corn parcel appears in both bars. These counts are not a pie of land area;
  freehand crop marks have no measured hectares.

The charts use text labels and readable numbers alongside color. Loading,
no selection, no parcels, missing data, and errors have distinct states. Failed
requests offer an explicit retry; they never become a zero-parcel result.

## Implementation and lifecycle

`public/js/farmer-parcel-stats.js` is a dependency-free view model and DOM renderer.
`farmers-maps.js` supplies authorized selected-farmer plots and the existing
`ParcelCropLayer` classifications. No new endpoint, chart CDN, provider call,
schema, persistent analytics store or record write is introduced.

An incomplete municipality overview cannot satisfy a complete farmer lookup.
Only complete caches avoid the existing `/farmers/{farmer}/plots` request.
Failed fetches do not cache an empty result. Revision guards prevent an older
farmer selection or forced refresh from overwriting current data.

The shared plot loader refreshes land after save/import/delete. Crop-layer
notifications refresh seasonal charts, including accepted modal saves before
the modal closes. Period changes immediately remove old crop totals. Camera
motion does not recalculate statistics, and unchanged view models preserve
expanded tables and keyboard focus. Labels use text nodes, never raw HTML.

## Verification and release

The full JavaScript suite passed 317 tests, including 17 summary/DOM checks and
six added real map lifecycle regressions. The Farmers presentation suite passed
52 assertions across six tests for staff and oversight views. Its existing
missing `.env.testing` warnings remain. Browser previews use synthetic records
and the actual Blade partial, helper and stylesheet; they are not live Maps or
satellite-provider acceptance. The 340px and 560px panels, narrow mobile layout,
long names, expanded table and loading/error states had no horizontal overflow
or browser console errors.

Release the helper, Farmers map script, stylesheet, partial and containing views
together. Mirror all three changed public assets to Hostinger's served public
directory and rebuild Blade views. Preserve records, scopes, provider budgets
and shared cache counters. No migration or general cache clear is required.
See [the deployment receipt](GITHUB_DEPLOYMENT.md) for live release status.

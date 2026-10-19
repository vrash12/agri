# Parcel crop guidance and field checks

The parcel map now links to **Crop guidance & field checks** for each saved parcel. The selected map year and season follow that link. Agriculture office staff can record a field visit; supervisory roles can read within their authorized scope. Farmer, veterinary, evaluator and visitor sessions have no access.

## Using the workflow

1. Select a farmer on the parcel map, expand a parcel's actions and open **Crop guidance & field checks**.
2. Read the suggested next action. Weather/rainfall evidence is explicitly municipality-level; it is not a measurement on that farm.
3. Record the field-check date, crop, growth stage, observed condition and verified water supply. Add the planting date to compare satellite readings within that crop cycle. Unknown values stay unknown.
4. Add soil measurements, slope and a sourced growing-period climate reference when available. Compare Rice, Corn and Mungbean against the same field evidence. Source names and dates remain visible.
5. Set a next visit date. After visiting, save another check with the outcome and mark the follow-up completed. Previous checks remain in history.
6. Use **Satellite field check** for NDVI, NDMI, SAVI or EVI. Completed usable checks supply dated points on the guidance page when it is reopened. A decrease alone does not diagnose drought.

No crop geometry, planted area, harvest attribution or official drought assessment is changed by these field checks. There is no automated planting prescription or watering instruction. See [source definitions and limits](PARCEL_AGRONOMY_SOURCES.md).

## Architecture and cost

- `ParcelFieldAssessments` is the Climate module's append-only persistence API. It reauthorizes the current account inside the write transaction, locks municipality/farmer/parcel in that order and rejects stale parcel/context/season versions.
- `ParcelSuitability` and `ParcelStressMonitor` are deterministic evidence interpreters. Missing, expired or incompatible evidence cannot become a zero reading or a reassuring result.
- `ParcelSatelliteHistory` retains only bounded summaries from already completed satellite statistics: at most 90 dates per index for 90 days. Different ownership, boundaries, credentials, grids, processing code or settings do not mix comparison series. No raw provider payload, image or coordinates are stored in this history.
- Guidance pages make no external requests. Existing scheduled weather/CHIRPS refreshes and opt-in Sentinel budgets remain unchanged. The server-rendered interface and inline SVG charts require no new service, SDK or CDN.
- `parcel_field_assessments` is the only new table. Field evidence and visit notes remain internal; safe audit metadata records categorical changes without copying narrative notes.

## Deployment and verification

Back up and verify the production database first. Deploy the complete Climate services, HTTP adapters, route changes, config, view and public assets together. Run only `2026_10_19_000400_create_parcel_field_assessments_table.php`; preserve all pre-existing rows and schemas. Rebuild optimized autoloading plus configuration, route and Blade caches. Do not clear the general cache or reset provider budgets.

Automated coverage checks all office scopes, denied roles, moved ownership, stale versions, append-only follow-ups, date/source validation, actual Blade renders, evidence interpretation and satellite history isolation. Live acceptance must separately verify signed-in drawing/saving/reopening, immediate crop display, charts and provider-backed satellite results. Record the actual receipt in `GITHUB_DEPLOYMENT.md`; a synthetic preview does not establish live provider success.

Runtime `fe05f03` was deployed on October 19, 2026. Signed-in checks confirmed crop drawing, saving and reopening, immediate colors/icons/charts, both field-check follow-ups and all four live satellite indices. A synthetic KML boundary supplied the test parcel; manual 3D parcel-boundary drawing remains unverified because browser automation could not interact with its closed shadow root. The synthetic farmer, parcel, crop season and two field checks were privately backed up and removed afterward. See [the release receipt](GITHUB_DEPLOYMENT.md) and [live acceptance record](MAP_RELEASE_ACCEPTANCE_CHECKLIST.md) for exact coverage.

The live review also found that choosing a farmer's full datalist label performed a second search for the formatted label, disabling Locate. The finder now accepts a returned exact option without that redundant request; its integration test covers selection and opening the correct record.

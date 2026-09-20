# Ramos barangay planning references

## Local pilot — September 20, 2026

Open **Municipality geofences → Ramos, Tarlac**. The Barangay boundaries panel
loads nine read-only outlines. Toggle **Show planning references**, select a
barangay to highlight it, and press **Focus** to zoom. All barangays → Focus fits
the whole layer. Labels appear at zoom 14 and above; the selected barangay is
always labeled. Click an outline for its name and PSGC code. Source & accuracy
contains attribution and the local-validation note.

These are simplified planning references, not surveyed or LGU-certified boundaries.
No accounts, operational records or boundary history are created. They do not
assign parcels, validate parcel placement, determine eligibility, provide barangay
statistics, or appear in municipality snapshot exports. The layer hides during
municipality drawing/editing to keep editing gestures available.

## Source

- James Faeldon's `philippines-json-maps`, 2023 high-resolution extract, commit
  `8eeead560246863c8c820c31ca6fbca81a279477`:
  <https://raw.githubusercontent.com/faeldon/philippines-json-maps/8eeead560246863c8c820c31ca6fbca81a279477/2023/geojson/municities/hires/bgysubmuns-municity-306912000.0.1.json>
- Upstream `altcoder/philippines-psgc-shapefiles` attributes its 2023 source to
  OCHA's Philippine administrative dataset (PSA/NAMRIA). Repository MIT notice
  retained in `database/seeders/data/RAMOS_BARANGAY_LICENSE.txt`. OCHA's catalog
  identifies its dataset as CC BY-IGO; do not imply agency certification.
- Names/codes checked against PSA:
  <https://psa.gov.ph/classification/psgc/barangays/0306912000>.
- Original SHA-256:
  `f9d3408769079d94fcfafbe61aad438f31d34458b601487cf80ce5cd6870b750`.
- Application extract SHA-256:
  `cd34baa1f643b1dcce5aedbff2011cf17327c6c6a7b7fc38963b3655b158844f`.

The application extract preserves coordinates, retains only names and zero-padded
ten-digit PSGC codes, and adds interior label points calculated with Shapely's
representative point. Shapely was only a preparation tool, not an app dependency.
Size: 6,286 bytes; nine Polygons; 111 coordinate pairs including closing points.
No further simplification, snapping, repair or clipping was applied. Git enforces
LF for the pinned file because the runtime verifies its checksum.

| Barangay | PSGC |
| --- | --- |
| Coral-Iloco | 0306912001 |
| Guiteb | 0306912002 |
| Pance | 0306912003 |
| Poblacion Center | 0306912004 |
| Poblacion North | 0306912005 |
| Poblacion South | 0306912006 |
| San Juan | 0306912007 |
| San Raymundo | 0306912008 |
| Toledo | 0306912009 |

All nine polygons are valid and show no positive-area overlaps beyond numerical
noise. The union has 98.37% intersection-over-union with the existing geoBoundaries
2020 Ramos reference (a longitude/latitude diagnostic, not a surveyed area
measurement). Their edges differ; local validation remains necessary. A different
source simplified at 0.005 degrees was rejected because it reduced some barangays
to triangles and materially altered their areas.

## Architecture and permissions

`GET /municipality-boundaries/barangays?municipality_id=…` is authenticated and
throttled at 30/minute. The controller authorizes the existing boundary view
policy, then resolves an active municipality through `MunicipalityAccess` and an
active supervising province. Wrong-scope/missing municipalities return 404;
veterinary and invalid accounts are denied. Authorized unsupported municipalities
return an empty, unavailable FeatureCollection.

`BarangayBoundaryReferences` identifies Ramos using its name and actual supervising
province relationship, never the legacy province string, a fixed database ID or a
client-supplied file path. It reads a trusted private file capped at 100 KB and
verifies SHA-256. Failures are reported server-side with a generic 503 response.
Shared headers make private responses non-storable. Viewing this public-source
reference geometry performs no database write or sensitive export.

The initial HTML contains only authorized reference municipality IDs. Independent
`public/js/barangay-boundaries.js` loads geometry on selection, retains at most one
payload in memory, aborts old requests, ignores stale results and clears old
overlays/listeners. A 20-second timeout and retry handle loading failures. No
external boundary service is called at runtime. Parcels render above this layer.

## Verification and deployment

Tests cover names/codes, geometry/local coordinate bounds, private headers,
role/municipality/province isolation, inactive scopes, unsupported references,
validation, failure recovery and view rendering. JavaScript tests cover selection,
cache/focus, stale responses, visibility during loading, retry and editing.
Existing municipality rendering/editing regressions also pass.

Final local checks: 9 feature tests (517 assertions), 2 existing workspace tests
(66 assertions), and 27 JavaScript tests passed. Pint, PHP syntax, Blade compilation,
route listing and diff whitespace checks passed. The restored local database
resolves Ramos successfully and returns all nine features (6,697-byte response).
The served script returns HTTP 200; unauthenticated geometry requests return 401.

No migration, seeder or configuration is needed. A separately authorized deployment
must include the controller/support service, private GeoJSON/license, integration
in routes/controller/view, and both map scripts. Mirror public scripts to both
Hostinger public directories before refreshing views/routes. Never import these
barangays as official municipality polygons.

Status: implemented locally, not deployed. Signed-in Google Maps browser acceptance
and Ramos LGU verification remain outstanding.

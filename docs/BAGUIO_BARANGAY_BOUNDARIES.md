# Baguio City barangay planning references

Implemented locally on September 21, 2026; not deployed to Hostinger.

Open **Municipality geofences → Baguio City → Barangay boundaries**. All 129 barangays load on demand. Select a barangay to highlight it, then press **Focus** to zoom. Click a boundary to see its name and PSGC code. The existing visibility toggle, zoom-dependent labels, source details, loading/retry and editing protections also apply. The Google Map labels button affects only Google's labels; AgriGOV barangay labels remain available.

These are simplified planning references requiring Baguio LGU/NAMRIA verification, not surveyed boundaries. No operational ownership, parcel validation, account assignment, city boundary or stored record is changed. They are not included in municipality snapshot exports or the Farmers 3D parcel map.

## Source and preparation

- Source: James Faeldon's [2023 high-resolution Baguio extract](https://raw.githubusercontent.com/faeldon/philippines-json-maps/8eeead560246863c8c820c31ca6fbca81a279477/2023/geojson/municities/hires/bgysubmuns-municity-1430300000.0.1.json), pinned to commit `8eeead560246863c8c820c31ca6fbca81a279477`. The repository attributes the underlying shapes to PSA/NAMRIA via OCHA through `altcoder/philippines-psgc-shapefiles`. This is the same source revision used for Ramos.
- [PSA's Baguio barangay list](https://psa.gov.ph/classification/psgc/barangays/1430300000) lists 129 barangays as of July 31, 2025. All 129 distinct ten-digit codes in the extract match that list. City code: `1430300000`; correspondence code: `141102000`.
- Original SHA-256: `d8bba1bca14e099fa0b599413d52782642af8c754507ce0e39f12540b2f56868`.
- Application SHA-256: `351c9f3d339f88a068f6b9373e88f5dda849d86899074a08c0d3a76a6d990282`.
- Repository MIT notice: `database/seeders/data/BAGUIO_BARANGAY_LICENSE.txt`. Retain upstream attribution; OCHA identifies its Philippine administrative dataset as CC BY-IGO.

The application extract preserves every source coordinate and keeps only the barangay name, PSGC code, and an interior label point. Features are sorted alphabetically. Shapely representative points were used during preparation; Shapely and PyProj are not runtime dependencies. No clipping, coordinate rounding, repair or further simplification was applied.

Geometry checks: 129 valid Polygons, 1,560 coordinate pairs including closures, 86,645 bytes. All label points lie inside their polygons. Projected EPSG:32651 union area is approximately 5,813.22 hectares; pairwise coverage has no positive-area overlap beyond numerical noise. Intersection-over-union with the existing geoBoundaries city outline is 98.54%. Different source vintages and simplification mean edges do not match exactly; this metric does not certify field accuracy.

## Scope, performance and deployment

`BarangayBoundaryReferences` uses a fixed source registry. Baguio aliases are accepted only under the active **Baguio City** supervising province/city scope, using the actual foreign-key relationship. The legacy `province` display string does not grant access. Benguet, Tarlac and other scopes cannot request Baguio geometry. A Regional Head can see the reference only if their active region contains Baguio's configured city scope.

The existing authenticated, throttled endpoint and policies remain unchanged. Source files remain private, size-capped at 100 KB and checksum-verified. Initial HTML includes only authorized municipality IDs. The client loads one municipality at a time and removes old geometry/listeners on selection changes. Popups now use the server's location label instead of a hard-coded Ramos label.

No migration or database import is required. An authorized GitHub deployment should include the support class, private GeoJSON/license, and `public/js/barangay-boundaries.js`; mirror that script to both Hostinger public directories. Keep LF for the pinned GeoJSON checksum. Deploy the pending map-label buttons with their respective scripts/views if included in the same approved release. Do not run a municipality seeder or replace the existing Baguio city boundary for this layer.

## Verification

Twelve focused PHP tests passed (1,712 assertions), covering the pinned geometry, bounded/private payload, municipality/province/region isolation, inactive scopes, unsupported lookalike names, no new operational records, Ramos regression and safe failure responses. Thirty-seven JavaScript tests passed across barangay loading/selection/cleanup, both map-label controls and municipality rendering/editing.

Pint, PHP/JavaScript syntax, Blade compilation and whitespace checks passed. A rendered synthetic Baguio workspace with a Google API stub exposed 129 alphabetical choices plus All barangays, a working Irisan selection/Focus, correct Baguio source notes and hide/show controls. This is interface verification; live Google imagery and Baguio LGU acceptance remain unverified. No production files or database rows were changed.

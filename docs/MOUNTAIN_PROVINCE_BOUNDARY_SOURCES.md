# Mountain Province municipality planning references

## Coverage

Ten municipalities share the Mountain Province access scope, following the [PSA directory](https://psa.gov.ph/classification/psgc/citimuni/1404400000): Barlig, Bauko, Besao, Bontoc, Natonin, Paracelis, Sabangan, Sadanga, Sagada and Tadian. Their current PSGC codes run from `1404401000` through `1404410000`; legacy codes run from `144401000` through `144410000`. Bontoc uses a province-qualified workspace name/code to avoid confusing it with Bontoc in Southern Leyte. Its original source name remains unchanged. No independent city scope or accounts are created.

## Sources and limitations

Retrieved September 20, 2026. Geometry is unchanged from [geoBoundaries gbOpen PHL ADM3 revision 9469f09](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson), boundary year 2020, attributed to NAMRIA, PSA and OCHA Philippines under [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/).

Identities and reference areas were independently checked against the [GeoRiskPH PSA Municipal layer](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0). Province placement uses pinned ADM2 revision 41af8f1. All ten reference interior points match exactly one ADM3 feature and Mountain Province; current and legacy PSGC codes match the municipality names. Geometries are valid, with no positive-area intersection between the ten selected source features. Minimum intersection divided by the smaller reference polygon is 99.685%; maximum application spherical-area difference is 0.929%, below the unchanged 3% guard. The simplified set has 447 vertices.

These are approximate planning references, not legal/cadastral boundaries or evidence of land ownership. LGU/NAMRIA verification remains necessary before official use. Source dates and simplification can explain mismatches with barangay, parcel or newer municipality boundaries.

## Explicit import

After verifying a database backup, run only `php artisan db:seed --class=MountainProvinceMunicipalityBoundarySeeder` (append `--force` for an explicitly authorized production import). Keep it outside `DatabaseSeeder`; do not run demo seeders or rerun legacy baseline migrations.

Deploy the named seeder and `database/seeders/data/mountain_province_municipality_reference_boundaries.geojson` with the existing shared reference importer and access/geometry dependencies. No migration, new dependency or public asset is needed. An active System Owner or Mountain Province Super Admin must exist for audit attribution. The importer rejects ambiguous/inactive/mismatched workspaces, altered existing boundaries and overlaps, and rolls back the complete province on failure. Re-importing unchanged data preserves IDs, styles, geometry and audits. It never transfers existing ownership or creates farmer, account or assistance records.

SHA-256 after normalizing line endings: `752130e1d81994e34fc2db89f849bcd0d63b0cd8b14540c3c1afc3c5aa1f96f5`.

## Verification

Nine isolated SQLite feature tests passed (241 assertions), covering complete coverage/attribution, idempotency, conflicting PSGC ownership, inactive workspaces, overlapping or changed geofences, checksum rejection, provincial authorization, and duplicate Bontoc names with scoped access. Pint and PHP syntax checks passed.

The local import completed September 20, 2026 at 13:07:08 UTC; the Hostinger import completed at 13:11:04 UTC. Both verified a database backup, added ten municipalities and ten active boundaries, checked all ten map responses, preserved existing records, confirmed an unchanged repeat import and verified province isolation. Production source hashes and environment preservation passed; the temporary deployment schedule was removed after completion. See [the release record](FULL_DEPLOYMENT_2026_09_20.md).

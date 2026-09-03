# Demo seed reference data

`tarlac_reference_boundaries.geojson` contains the four municipality features used by `TarlacMunicipalityDemoSeeder`.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3 (municipalities)
- Pinned revision: `9469f09`
- Boundary year: 2020
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- Metadata: https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/
- Pinned source: https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson
- Retrieved: 2026-09-03
- Line-ending-normalized SHA-256: `a782a110527abd49b8ca91f6fd0636dcb1943989f0a3c12e78a9aa877755e815`

Municipality identity and area sanity checks use the PSA PSGC Tarlac listing and the government-hosted GeoRiskPH PSA Municipal Boundary layer:

- PSGC Tarlac: https://psa.gov.ph/classification/psgc/citimuni/0306900000
- GeoRiskPH/PSA layer: https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0

| Municipality | PSGC code | geoBoundaries shape ID |
| --- | --- | --- |
| Anao | `0306901000` | `30758251B97244135669664` |
| Camiling | `0306903000` | `30758251B23459833682053` |
| Paniqui | `0306910000` | `30758251B69585850409571` |
| Ramos | `0306912000` | `30758251B37101241671575` |

These geometries are approximate planning/reference boundaries. They are not legal, cadastral, or survey-grade boundaries. A Super Admin should obtain LGU/NAMRIA verification before treating one as an official boundary.

The farmer and assistance records produced by the named demo seeder are synthetic. Do not use them as beneficiaries, official distribution transactions, or evidence of assistance delivery.

Run this data set explicitly; it is intentionally not registered in `DatabaseSeeder`:

```bash
php artisan db:seed --class=TarlacMunicipalityDemoSeeder
```

The seeder is idempotent. It upserts the same 10 synthetic farmers and 10 synthetic assistance records per municipality instead of creating duplicate demo cohorts. It activates the four requested reference geofences and archives any prior active boundary for those same four municipalities. It also detects the known invalid four-point legacy Moncada polygon before archiving it; any other cross-municipality conflict stops the entire transaction for manual review.

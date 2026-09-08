# Reference boundaries and demonstration data

Reference imports require an active System Owner or an active Super Admin assigned to the target province. `ReferenceBoundaryAccess` checks this assignment and gives new workspaces an explicit `province_id`. Apply the province-supervision migration and account setup described in `PROVINCE_SUPERVISION.md` before running these imports. These seeders are explicit maintenance commands and must not run automatically during deployment.

Deploying the source files does not insert geofence records. Back up the target database and explicitly apply the requested reference imports there. The Bulacan importer reuses the legacy `BUL` workspace or an unambiguous matching name/code, preserves its ID and assignment, and rejects inactive, ambiguous, or foreign-province matches. Workspace creation rolls back with any rejected boundary; re-importing an unchanged active reference preserves its style and audit history.

`tarlac_reference_boundaries.geojson` and `tarlac_extended_reference_boundaries.geojson` contain the six municipality features used by `TarlacMunicipalityDemoSeeder`.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3 (municipalities)
- Pinned revision: `9469f09`
- Boundary year: 2020
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- Metadata: https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/
- Pinned source: https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson
- Retrieved: 2026-09-03
- Line-ending-normalized SHA-256:
  - base file: `a782a110527abd49b8ca91f6fd0636dcb1943989f0a3c12e78a9aa877755e815`
  - extended file: `445c9a349e2313b34afd04105b5c81339aeeb03fdd3d5d04c1c50040ff6b9952`

Municipality identity and area sanity checks use the PSA PSGC Tarlac listing and the government-hosted GeoRiskPH PSA Municipal Boundary layer:

- PSGC Tarlac: https://psa.gov.ph/classification/psgc/citimuni/0306900000
- GeoRiskPH/PSA layer: https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0

| Municipality | PSGC code | geoBoundaries shape ID |
| --- | --- | --- |
| Anao | `0306901000` | `30758251B97244135669664` |
| Camiling | `0306903000` | `30758251B23459833682053` |
| Concepcion | `0306905000` | `30758251B96121562186522` |
| Paniqui | `0306910000` | `30758251B69585850409571` |
| Ramos | `0306912000` | `30758251B37101241671575` |
| Tarlac City | `0306916000` | `30758251B41951653344210` |

These geometries are approximate planning/reference boundaries. They are not legal, cadastral, or survey-grade boundaries. A Super Admin should obtain LGU/NAMRIA verification before treating one as an official boundary.

`bulacan_reference_boundary.geojson` contains the province-level Bulacan feature used by `BulacanProvinceBoundarySeeder`.

- Dataset: geoBoundaries `gbOpen` Philippines ADM2 (provinces)
- Pinned revision: `41af8f1`
- Boundary year: 2020
- Source feature: Bulacan / `2640588B70472166839855`
- PSA PSGC code: `0301400000`
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- Metadata: https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM2/
- Pinned source: https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/41af8f1/releaseData/gbOpen/PHL/ADM2/geoBoundaries-PHL-ADM2_simplified.geojson
- Bulacan reference area: 278,369 hectares from the Province of Bulacan Provincial Development and Physical Framework Plan 2024–2036
- Line-ending-normalized SHA-256: `41c71c536b79c4ff7d85b88efa9a5cef41bf732926c6cf30b2f09f9025122d33`

Run the Bulacan boundary import explicitly:

```bash
php artisan db:seed --class=BulacanProvinceBoundarySeeder
```

The import creates the Bulacan workspace when it is missing, activates one idempotent province planning/reference boundary, refuses overlap with other active boundaries, archives a previously active Bulacan boundary, and records the change in the audit trail. It does not create farmers, assistance releases, or other operational data. The ADM2 boundary is a planning reference only and requires Bulacan LGU/NAMRIA verification before official use.

## Baguio City reference boundary

`baguio_reference_boundary.geojson` contains only the Baguio City feature used by `BaguioCityBoundarySeeder`.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3 (cities/municipalities)
- Pinned revision: `9469f09`; boundary year: 2020; retrieved: 2026-09-07
- Feature: Baguio City / `30758251B18922588133033` (not Ambaguio)
- Current PSA PSGC: `1430300000`; legacy code: `141102000`
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- [Dataset metadata](https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/)
- [Pinned GeoJSON source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA city identity reference](https://psa.gov.ph/classification/psgc/cities)
- [GeoRiskPH/PSA area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0): Baguio City, `city_code=141102000`, 58.08582396 km²
- Application geometry: 33 vertices; approximately 5,861.586 hectares, within 1% of that area reference
- Line-ending-normalized SHA-256: `103e72b2ce486c5275c9d310ae2cf62f7696fadb0d8305ee151ba0340de69529`

Run explicitly against the intended environment:

```bash
php artisan db:seed --class=BaguioCityBoundarySeeder
```

The import reuses an unambiguous active Baguio workspace or creates `Baguio City` with code `BAGUIO`. The `province` display field uses `Benguet`, matching the geographic grouping in the source layer; Baguio remains a highly urbanized city, and this field does not grant province ownership or change access rules. No accounts, farmers, parcels, or assistance records are created.

The seeder validates the pinned source before writing, uses the shared activation lock and a transaction, refuses cross-workspace overlaps and a different existing active Baguio boundary, invalidates the active-boundary cache, and records an attributed audit event. Repeated runs do not duplicate the reference boundary. Review conflicting or ambiguous workspaces explicitly; the seeder does not overwrite them.

This is an approximate planning/reference boundary, not a legal or cadastral survey. Verify it with the Baguio LGU/NAMRIA before official use. Once active, the existing parcel geofence checks apply to Baguio. This named seeder is intentionally excluded from `DatabaseSeeder` and automatic production deployment.

## La Trinidad, Atok, and Tublay reference boundaries

`benguet_reference_boundaries.geojson` contains exactly the three municipality features used by `BenguetMunicipalityBoundarySeeder`. These are municipality boundaries, not a boundary for the entire Benguet province.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3, pinned revision `9469f09`
- Boundary year: 2020; retrieved: 2026-09-07
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- [Dataset metadata](https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/)
- [Pinned GeoJSON source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA Benguet identity reference](https://psa.gov.ph/classification/psgc/citimuni/1401100000)
- [GeoRiskPH/PSA area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0), queried using the legacy city codes below
- Line-ending-normalized SHA-256: `6b089afa0c6d70ad949eb5deb7ce10b7a8264596510f0f3087b2065ee3bad288`

| Municipality | PSGC | Legacy PSGC | Source shape ID | Reference area (ha) | Computed area (ha) | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: |
| La Trinidad | `1401110000` | `141110000` | `30758251B70129135029645` | 7,042.025049 | 7,109.2414 | 35 |
| Atok | `1401101000` | `141101000` | `30758251B63625349740918` | 17,849.992574 | 17,910.3503 | 58 |
| Tublay | `1401114000` | `141114000` | `30758251B5924892224435` | 7,629.699492 | 7,671.2429 | 38 |

All three computed areas are within 1% of the government-hosted reference. The application validates geometry and rejects an area deviation over 3%. Their shared edges do not count as overlap; the three pinned geometries also do not overlap the pinned Baguio reference.

Run this import explicitly against the intended environment:

```bash
php artisan db:seed --class=BenguetMunicipalityBoundarySeeder
```

The import reuses unambiguous active Benguet workspaces or creates them with codes `LATRINIDAD`, `ATOK`, and `TUBLAY`. Existing name or PSGC matches are preserved; ambiguous identities, a different province, inactive workspaces, conflicting active boundaries, or a changed/deactivated reference stop the import. All three imports share one activation lock and transaction, so a later conflict rolls back earlier additions. Successful imports clear each active-boundary cache and record an audit event attributed to an active Super Admin. A repeated run creates no duplicate boundaries or import events.

No accounts, farmers, parcels, or assistance records are created. Normal municipality scope and parcel geofence validation apply once the boundaries are active. These are approximate planning references requiring LGU/NAMRIA verification before official use. The seeder is intentionally excluded from `DatabaseSeeder` and automatic production deployment.

## Remaining Tarlac municipality reference boundaries

`tarlac_remaining_reference_boundaries.geojson` contains exactly the twelve features used by `TarlacRemainingMunicipalityBoundarySeeder`. They complete the existing six Tarlac references, covering 17 municipalities and Tarlac City without running the demonstration-data seeder.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3; boundary year 2020
- Pinned revision: `9469f09`
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- [Dataset metadata](https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/)
- [Pinned simplified source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA Tarlac identity reference](https://psa.gov.ph/classification/psgc/citimuni/0306900000)
- [GeoRiskPH/PSA boundary and area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0), filtered by `prov_name='Tarlac'`
- Retrieved: 2026-09-08
- Line-ending-normalized SHA-256: `3d9f24fabba985d50679d263998516cc05d24d2d6fc60390aa824d222d8d39b9`

| Municipality | PSGC | Legacy PSGC | geoBoundaries shape ID | Reference hectares | Computed hectares | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Bamban | `0306902000` | `036902000` | `30758251B57034914401732` | 20,747.839251 | 20,898.2339 | 67 |
| Capas | `0306904000` | `036904000` | `30758251B1710226943062` | 44,647.834915 | 44,889.9465 | 44 |
| Gerona | `0306906000` | `036906000` | `30758251B71774891580031` | 12,234.883302 | 12,333.1401 | 64 |
| La Paz | `0306907000` | `036907000` | `30758251B69162702482749` | 11,570.271156 | 11,624.4021 | 97 |
| Mayantoc | `0306908000` | `036908000` | `30758251B27820731210135` | 24,008.967425 | 24,195.8664 | 47 |
| Moncada | `0306909000` | `036909000` | `30758251B50379489571525` | 10,174.105474 | 10,268.7273 | 49 |
| Pura | `0306911000` | `036911000` | `30758251B36664202091084` | 3,211.953092 | 3,216.3918 | 28 |
| San Clemente | `0306913000` | `036913000` | `30758251B85209892464019` | 7,127.505251 | 7,154.8879 | 74 |
| San Jose | `0306918000` | `036918000` | `30758251B86793831645374` | 59,583.013518 | 59,894.2067 | 52 |
| San Manuel | `0306914000` | `036914000` | `30758251B40197742842529` | 3,267.855300 | 3,307.1962 | 24 |
| Santa Ignacia | `0306915000` | `036915000` | `30758251B1064490572832` | 13,145.882843 | 13,183.1522 | 54 |
| Victoria | `0306917000` | `036917000` | `30758251B28872871222208` | 10,966.356898 | 11,022.2992 | 68 |

Source features were selected by exact name and their geographic extent against the Tarlac government reference, preventing confusion with other municipalities named La Paz, San Jose, San Manuel, or Victoria. Each source bounding box has over 99.6% intersection-over-union with its corresponding reference extent. The snapshot retains the reference extents and area values. Computed areas differ by less than 1.21%; the importer rejects deviations over 3%. All 18 Tarlac geometries and the neighboring Bulacan reference pass pairwise overlap checks, including shared edges.

Run this boundary-only import explicitly against the intended environment:

```bash
php artisan db:seed --class=TarlacRemainingMunicipalityBoundarySeeder
```

An active Super Admin is required for attribution. The import reuses unambiguous active Tarlac workspaces or creates them with uppercase underscore-separated codes such as `LA_PAZ` and `SAN_JOSE`. Names, name aliases, and PSGC codes are matched conservatively: an ambiguous match, wrong province, or inactive workspace stops the import without reassigning or renaming anything.

The shared `ReferenceMunicipalityBoundaryImporter`, also used by the Benguet importer, validates the source before any writes. All twelve imports share one activation lock and transaction. A later conflict rolls back earlier additions, including workspace creation and import events. Existing different active boundaries and changed/deactivated references require review; unrelated archived boundaries, including Moncada's legacy history, remain unchanged. Successful imports invalidate each target boundary cache and attribute new references through the existing best-effort audit mechanism. Repeated runs create no duplicate boundaries or import events.

No accounts, farmers, parcels, or assistance records are created. Existing operational records and municipality ownership remain intact; normal parcel geofence validation applies once active. These are approximate planning references requiring LGU/NAMRIA verification before official use. This named seeder is intentionally excluded from `DatabaseSeeder` and automatic production deployment.

## Remaining Benguet municipality reference boundaries

`benguet_remaining_reference_boundaries.geojson` contains exactly the ten features used by `BenguetRemainingMunicipalityBoundarySeeder`: Bakun, Bokod, Buguias, Itogon, Kabayan, Kapangan, Kibungan, Mankayan, Sablan, and Tuba. Together with the separate La Trinidad, Atok, and Tublay snapshot, this covers all thirteen Benguet municipalities. Baguio City retains its separate city reference and administrative status.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3; boundary year 2020
- Pinned revision: `9469f09`
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- [Dataset metadata](https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/)
- [Pinned simplified source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA Benguet identity reference](https://psa.gov.ph/classification/psgc/citimuni/1401100000)
- [GeoRiskPH/PSA boundary and area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0), filtered by `prov_name='Benguet'`
- Retrieved: 2026-09-08
- Line-ending-normalized SHA-256: `9ca916bb897342b9b56ae57f10e0f4dd94572ed2e453a3bf7e827d4b45e6b1a7`

| Municipality | PSGC | Legacy PSGC | geoBoundaries shape ID | Reference hectares | Computed hectares | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Bakun | `1401103000` | `141103000` | `30758251B56433040620438` | 31,523.852867 | 31,697.3853 | 100 |
| Bokod | `1401104000` | `141104000` | `30758251B72023971813507` | 39,200.038630 | 39,439.8467 | 46 |
| Buguias | `1401105000` | `141105000` | `30758251B42417191393765` | 17,638.592402 | 17,738.7227 | 73 |
| Itogon | `1401106000` | `141106000` | `30758251B42509136002589` | 43,815.593933 | 44,062.8715 | 57 |
| Kabayan | `1401107000` | `141107000` | `30758251B34627250160857` | 19,017.825829 | 19,165.8446 | 22 |
| Kapangan | `1401108000` | `141108000` | `30758251B17255873954553` | 18,096.918408 | 18,213.5116 | 26 |
| Kibungan | `1401109000` | `141109000` | `30758251B24433463121222` | 16,711.283852 | 16,812.4595 | 44 |
| Mankayan | `1401111000` | `141111000` | `30758251B68871031890947` | 14,610.126920 | 14,687.4996 | 82 |
| Sablan | `1401112000` | `141112000` | `30758251B47648783241888` | 10,155.429773 | 10,234.2449 | 24 |
| Tuba | `1401113000` | `141113000` | `30758251B14138125500475` | 34,194.260680 | 34,364.7471 | 96 |

Each source feature was matched by exact name and geographic extent against the government reference, then checked against the PSA municipality code. The snapshot retains the reference area and extent. Each bounding box has over 99.85% intersection-over-union with its reference extent, and computed areas differ by less than 0.8%. The application rejects area deviations over 3%. All thirteen Benguet references, Baguio, eighteen Tarlac references, and Bulacan pass pairwise overlap checks (33 geometries, 528 pairs); shared borders create no overlapping interiors.

Run this boundary-only import explicitly against the intended environment:

```bash
php artisan db:seed --class=BenguetRemainingMunicipalityBoundarySeeder
```

The shared `ReferenceMunicipalityBoundaryImporter` validates the complete pinned source before writing and requires an active Super Admin for attribution. It reuses unambiguous active Benguet workspaces or creates missing ones with uppercase name codes such as `BAKUN` and `TUBA`. An ambiguous identity, wrong province, inactive workspace, overlap, existing different active boundary, or changed/deactivated reference stops the whole import. All ten references share one activation lock and transaction, so a later conflict rolls back earlier workspace, boundary, and import-event additions. Existing boundary records and unrelated archived history remain intact.

Successful imports clear the target boundary caches and attribute each new boundary through the existing best-effort audit mechanism, including whether its workspace was created. Repeated runs create no duplicate workspaces, boundaries, or import events. No users, farmers, parcels, assistance releases, or other operational records are created. Normal municipality isolation and parcel geofence validation apply once active, along with the existing geofence visibility and opacity controls. These approximate planning boundaries require LGU/NAMRIA verification before official use. This named seeder is excluded from `DatabaseSeeder` and automatic production deployment.

## Synthetic demonstration records

The farmer and assistance records produced by the named demo seeder are synthetic. Do not use them as beneficiaries, official distribution transactions, or evidence of assistance delivery.

Run this data set explicitly; it is intentionally not registered in `DatabaseSeeder`:

```bash
php artisan db:seed --class=TarlacMunicipalityDemoSeeder
```

The seeder is idempotent. It keeps the original demo cohort of 10 synthetic farmers and 10 synthetic assistance records in Anao, Camiling, Paniqui, and Ramos instead of creating duplicate records. It activates reference geofences for those four municipalities plus Concepcion and Tarlac City, without adding synthetic operational records to the two newly covered workspaces. It also detects the known invalid four-point legacy Moncada polygon before archiving it; any other cross-municipality conflict stops the entire transaction for manual review.

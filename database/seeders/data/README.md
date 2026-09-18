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

## Bulacan municipality and component-city reference boundaries

`bulacan_municipality_reference_boundaries.geojson` contains exactly the twenty-four features used by `BulacanMunicipalityBoundarySeeder`: the 21 Bulacan municipalities and the component cities of Malolos, Meycauayan, and San Jose del Monte. These are municipality-level boundaries. They replace the single province-level ADM2 reference imported by `BulacanProvinceBoundarySeeder`, which covered the same land.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3; boundary year 2020
- Pinned revision: `9469f09`
- Upstream sources: NAMRIA, Philippine Statistics Authority, and OCHA Philippines
- License: CC BY 3.0 IGO
- [Dataset metadata](https://www.geoboundaries.org/api/current/gbOpen/PHL/ADM3/)
- [Pinned simplified source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA Bulacan identity reference](https://psa.gov.ph/classification/psgc/citimuni/0301400000)
- [GeoRiskPH/PSA boundary and area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0), filtered by `prov_name='Bulacan'`
- Retrieved: 2026-09-16
- Line-ending-normalized SHA-256: `d58ea603bba4cc74c86e9949df9f0dad3fba3c4b54695de326a3af4e62682a16`

| Workspace | PSGC | Legacy PSGC | geoBoundaries shape ID | Reference hectares | Computed hectares | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Angat | `0301401000` | `031401000` | `30758251B11674695503139` | 4,940.828980 | 4,983.1707 | 111 |
| Balagtas | `0301402000` | `031402000` | `30758251B61200223936905` | 2,148.062701 | 2,155.2708 | 48 |
| Baliuag | `0301403000` | `031403000` | `30758251B80677147871069` | 4,575.989446 | 4,606.7741 | 78 |
| Bocaue | `0301404000` | `031404000` | `30758251B34406453293214` | 2,635.251149 | 2,673.4538 | 53 |
| Bulakan (source `Bulacan`) | `0301405000` | `031405000` | `30758251B88033392671893` | 7,233.712005 | 7,260.2178 | 86 |
| Bustos | `0301406000` | `031406000` | `30758251B3009870248876` | 3,986.503138 | 4,006.5884 | 56 |
| Calumpit | `0301407000` | `031407000` | `30758251B47768966638997` | 4,680.052201 | 4,673.9360 | 44 |
| Guiguinto | `0301408000` | `031408000` | `30758251B84945919751232` | 2,231.750386 | 2,235.2634 | 50 |
| Hagonoy | `0301409000` | `031409000` | `30758251B93181595327102` | 8,339.439846 | 8,385.4393 | 86 |
| Malolos City (source `City of Malolos`) | `0301410000` | `031410000` | `30758251B20138524188329` | 7,083.791752 | 7,154.8520 | 134 |
| Marilao | `0301411000` | `031411000` | `30758251B11566455240441` | 2,834.295875 | 2,841.8601 | 92 |
| Meycauayan City (source `City of Meycauayan`) | `0301412000` | `031412000` | `30758251B3506482067844` | 3,178.264801 | 3,176.9986 | 100 |
| Norzagaray | `0301413000` | `031413000` | `30758251B28396415682182` | 29,738.026156 | 29,959.1417 | 169 |
| Obando | `0301414000` | `031414000` | `30758251B34403699619291` | 1,620.758216 | 1,640.1569 | 30 |
| Pandi | `0301415000` | `031415000` | `30758251B75610884117479` | 5,041.772126 | 5,065.4792 | 62 |
| Paombong | `0301416000` | `031416000` | `30758251B84355932760435` | 4,528.503145 | 4,520.8565 | 80 |
| Plaridel | `0301417000` | `031417000` | `30758251B39966399473469` | 3,574.236485 | 3,608.6756 | 50 |
| Pulilan | `0301418000` | `031418000` | `30758251B69399459557735` | 4,264.445362 | 4,292.6645 | 54 |
| San Ildefonso | `0301419000` | `031419000` | `30758251B21292953325397` | 16,657.582365 | 16,788.4970 | 137 |
| San Jose del Monte City (source `City of San Jose del Monte`) | `0301420000` | `031420000` | `30758251B35007836972970` | 10,785.680259 | 10,833.2909 | 133 |
| San Miguel | `0301421000` | `031421000` | `30758251B36193121708082` | 23,194.039833 | 23,326.5279 | 126 |
| San Rafael | `0301422000` | `031422000` | `30758251B96236511516175` | 10,165.635513 | 10,219.3843 | 151 |
| Santa Maria | `0301423000` | `031423000` | `30758251B94770277751528` | 7,911.849411 | 7,953.5291 | 66 |
| Doña Remedios Trinidad | `0301424000` | `031424000` | `30758251B33129254568465` | 97,549.082447 | 99,666.0493 | 167 |

Source features were selected by exact name and by geographic extent against the Bulacan government reference, preventing confusion with the municipalities named San Miguel, San Rafael, San Ildefonso, and Santa Maria in other provinces. Every bounding box has at least 98.8% intersection-over-union with its reference extent, while the closest non-matching candidate reaches only 48%. Computed areas differ by less than 2.18%; the importer rejects deviations over 3%. Doña Remedios Trinidad has the largest deviation at 2.17% because its mountainous perimeter loses the most detail in the simplified source. All 24 geometries pass pairwise overlap checks against each other and against the 18 Tarlac, 13 Benguet, and Baguio City references; shared borders create no overlapping interiors.

Two workspace names deliberately differ from the source `shapeName`, through the importer's `workspace_name` identity field:

- **Bulakan** — the source and PSA spell this municipality `Bulacan`, which is identical to the legacy
Bulacan province workspace. The LGU's own spelling keeps the municipality workspace separate, so the province record is never renamed, reassigned, or reused for municipality geometry.
- **Malolos City**, **Meycauayan City**, **San Jose del Monte City** — the source writes these as
`City of Malolos` and so on; the workspaces follow the existing `Tarlac City` and `Baguio City` wording.

Run this boundary-only import explicitly against the intended environment:

```bash
php artisan db:seed --class=BulacanMunicipalityBoundarySeeder
```

An active System Owner or assigned Bulacan Super Admin is required for attribution. The import reuses unambiguous active Bulacan workspaces or creates them with uppercase underscore-separated codes such as `SAN_ILDEFONSO`, `DONA_REMEDIOS_TRINIDAD`, and `SAN_JOSE_DEL_MONTE_CITY`. Names, name aliases, and PSGC codes are matched conservatively: an ambiguous match, wrong province, or inactive workspace stops the import without reassigning or renaming anything.

A province polygon contains every municipality inside it, so the province-level and municipality-level references cannot both stay active under the overlap rule. This import therefore archives exactly one named reference — `Bulacan Province Planning Reference · geoBoundaries 2020`, and only when it is active and owned by a Bulacan municipality — records an `archived` audit event with `reason: superseded_by_municipality_references`, and clears that workspace's boundary cache. Its workspace, code, province assignment, and archived history are otherwise untouched, and re-running `BulacanProvinceBoundarySeeder` restores the province-level view. An identically named boundary in another province is not archived. Every other conflict — including an unrelated overlapping active boundary — still stops the whole import, and the archival rolls back with it because the supersession, all workspace creation, and all boundary writes share one activation lock and one transaction.

Successful imports clear each target boundary cache and attribute every new reference through the existing best-effort audit mechanism, including whether its workspace was created. Repeated runs create no duplicate workspaces, boundaries, or import events. No users, farmers, parcels, assistance releases, or other operational records are created. Normal municipality isolation and parcel geofence validation apply once active. These approximate planning boundaries require LGU/NAMRIA verification before official use. This named seeder is intentionally excluded from `DatabaseSeeder` and automatic production deployment.

## Synthetic demonstration records

The farmer and assistance records produced by the named demo seeder are synthetic. Do not use them as beneficiaries, official distribution transactions, or evidence of assistance delivery.

Run this data set explicitly; it is intentionally not registered in `DatabaseSeeder`:

```bash
php artisan db:seed --class=TarlacMunicipalityDemoSeeder
```

The seeder is idempotent. It keeps the original demo cohort of 10 synthetic farmers and 10 synthetic assistance records in Anao, Camiling, Paniqui, and Ramos instead of creating duplicate records. It activates reference geofences for those four municipalities plus Concepcion and Tarlac City, without adding synthetic operational records to the two newly covered workspaces. It also detects the known invalid four-point legacy Moncada polygon before archiving it; any other cross-municipality conflict stops the entire transaction for manual review.

## Negros Island Region municipality reference boundaries

The Negros Island Region (Region XVIII) was created in 2024. This system models provinces rather than regions, so the region arrives as its three provinces. The PSA area service still files them under their former regions — Negros Occidental under Region VI and Negros Oriental and Siquijor under Region VII — which affects none of the figures below but is worth knowing when comparing against that source.

All three imports share one pinned source revision and one verification method, described once here and not repeated per province.

**How the features were chosen.** The geoBoundaries ADM3 layer carries no province attribute, so each of the 1,647 Philippine features was assigned by testing its centroid against the geoBoundaries ADM2 polygon for the province, using the application's own `GeoGeometry`. Name matching was never used to select a feature, which is what makes the repeated names above safe.

**How the choice was checked.** Three independent agreements, none of which relies on the others:

1. The Philippine Statistics Authority area service returns exactly 63 LGUs for these three provinces, split 32 / 25 / 6 — the same split the geometry produced, and every name matched with none left over on either side.
2. Every geometry's bounding box agrees with the PSA record for the same LGU. The **worst intersection-over-union across all 63 is 0.9904**.
3. Computed areas differ from the PSA reference by **at most 1.41%** (Pulupandan). The importer rejects anything over 3%.

**Overlap.** No geometry in the region overlaps another, nor any of the 56 existing Tarlac, Benguet, Baguio or Bulacan references. Shared borders between neighbours are shared edges, not overlapping interiors.

**A naming limitation this import ran into.** `municipalities.name` and `municipalities.code` are both unique across the whole table, so two provinces cannot each hold a workspace called San Jose. Tarlac already had one, so Negros Oriental's arrives as **San Jose (Negros Oriental)** with the code `SAN_JOSE_NEGROS_ORIENTAL`. This will recur — San Isidro, Santa Cruz and San Miguel are common across Philippine provinces. Making the constraint `(province_id, name)` instead would fix it properly, but that is a change to a shared table with existing data and belongs to an office decision rather than to a boundary import.

These are approximate planning boundaries. They are not cadastral or survey-grade and require LGU or NAMRIA verification before official use.

### Negros Occidental

`negros_occidental_municipality_reference_boundaries.geojson` contains exactly the thirty-two features used by `NegrosOccidentalMunicipalityBoundarySeeder`: 19 municipalities, 12 component cities, and Bacolod City.

Bacolod City is a highly urbanized city and administratively independent of the province. It is stored under Negros Occidental because that is where it sits, and because Baguio City is already recorded under Benguet in exactly the same way. Splitting it into its own workspace later needs no change to the geometry.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3; boundary year 2020
- Pinned revision: `9469f09`; retrieved 2026-09-18
- Upstream sources: NAMRIA, Philippine Statistics Authority, OCHA Philippines
- License: CC BY 3.0 IGO
- [Pinned simplified source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA identity reference](https://psa.gov.ph/classification/psgc/citimuni/0604500000)
- [GeoRiskPH/PSA area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0)
- Line-ending-normalized SHA-256: `3db7a05511d7d6ccd17f0aafcf6e08f97802db9b0e85d9dd4b6a18f128426661`

| Workspace | PSGC | Legacy PSGC | geoBoundaries shape ID | Reference hectares | Computed hectares | Deviation | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Bacolod City | `0630200000` | `064501000` | `30758251B10359126771558` | 16,237.786580 | 16,358.9941 | 0.75% | 110 |
| Bago City | `0604502000` | `064502000` | `30758251B44456462498857` | 40,686.691581 | 40,915.0028 | 0.56% | 130 |
| Binalbagan | `0604503000` | `064503000` | `30758251B163560276547` | 18,487.513708 | 18,646.7580 | 0.86% | 118 |
| Cadiz City | `0604504000` | `064504000` | `30758251B74690587258532` | 52,657.216412 | 52,972.5492 | 0.60% | 96 |
| Calatrava | `0604505000` | `064505000` | `30758251B51763303574827` | 28,768.627437 | 28,932.2618 | 0.57% | 106 |
| Candoni | `0604506000` | `064506000` | `30758251B37131808105677` | 29,537.757168 | 29,721.2905 | 0.62% | 13 |
| Cauayan | `0604507000` | `064507000` | `30758251B70595272616613` | 46,970.718450 | 47,284.4564 | 0.67% | 101 |
| Escalante City | `0604509000` | `064509000` | `30758251B93268177737855` | 19,228.449122 | 19,369.7813 | 0.73% | 107 |
| Himamaylan City | `0604510000` | `064510000` | `30758251B87460719980042` | 36,349.722689 | 36,531.9410 | 0.50% | 69 |
| Kabankalan City | `0604515000` | `064515000` | `30758251B39137179877227` | 65,925.855780 | 66,330.5348 | 0.61% | 56 |
| Sipalay City | `0604527000` | `064527000` | `30758251B39035544266980` | 32,746.862395 | 32,921.9423 | 0.54% | 131 |
| Talisay City | `0604528000` | `064528000` | `30758251B1176164020832` | 19,348.153160 | 19,490.2210 | 0.73% | 61 |
| Victorias City | `0604531000` | `064531000` | `30758251B44366819417362` | 10,576.454354 | 10,613.6162 | 0.35% | 64 |
| Enrique B. Magalona | `0604508000` | `064508000` | `30758251B93552266540793` | 13,892.611796 | 14,006.6113 | 0.82% | 144 |
| Hinigaran | `0604511000` | `064511000` | `30758251B50394625091243` | 15,271.718665 | 15,371.5433 | 0.65% | 66 |
| Hinoba-An | `0604512000` | `064512000` | `30758251B90502148254132` | 40,203.820970 | 40,488.3074 | 0.71% | 90 |
| Ilog | `0604513000` | `064513000` | `30758251B19549547171132` | 29,403.235825 | 29,628.9206 | 0.77% | 82 |
| Isabela | `0604514000` | `064514000` | `30758251B76125738133682` | 19,144.249356 | 19,279.7267 | 0.71% | 89 |
| La Carlota City | `0604516000` | `064516000` | `30758251B4643469409429` | 12,707.879216 | 12,807.8706 | 0.79% | 81 |
| La Castellana | `0604517000` | `064517000` | `30758251B45255679920724` | 21,583.519192 | 21,742.0160 | 0.73% | 47 |
| Manapla | `0604518000` | `064518000` | `30758251B2817508515429` | 9,945.808282 | 10,042.7773 | 0.97% | 70 |
| Moises Padilla | `0604519000` | `064519000` | `30758251B86559237055177` | 14,059.833533 | 14,121.3200 | 0.44% | 49 |
| Murcia | `0604520000` | `064520000` | `30758251B26190802664029` | 27,942.637566 | 28,133.0972 | 0.68% | 103 |
| Pontevedra | `0604521000` | `064521000` | `30758251B40366129803792` | 11,155.622053 | 11,209.1971 | 0.48% | 58 |
| Pulupandan | `0604522000` | `064522000` | `30758251B33327911617473` | 1,679.651141 | 1,703.2627 | 1.41% | 24 |
| Sagay City | `0604523000` | `064523000` | `30758251B74171926345191` | 29,235.896647 | 29,396.5460 | 0.55% | 210 |
| Salvador Benedicto | `0604532000` | `064532000` | `30758251B86894559838043` | 21,767.307771 | 21,877.9592 | 0.51% | 87 |
| San Carlos City | `0604524000` | `064524000` | `30758251B8075335495854` | 40,775.872809 | 41,029.7953 | 0.62% | 192 |
| San Enrique | `0604525000` | `064525000` | `30758251B11825035117570` | 2,826.909798 | 2,831.4814 | 0.16% | 21 |
| Silay City | `0604526000` | `064526000` | `30758251B60701013344406` | 20,936.961842 | 21,070.2970 | 0.64% | 107 |
| Toboso | `0604529000` | `064529000` | `30758251B45465879841482` | 11,800.603756 | 11,880.1926 | 0.67% | 45 |
| Valladolid | `0604530000` | `064530000` | `30758251B22103021346639` | 4,024.098190 | 4,038.1016 | 0.35% | 24 |

```bash
php artisan db:seed --class=NegrosOccidentalMunicipalityBoundarySeeder
```

The import creates or reuses each unambiguous active workspace, activates one idempotent planning reference per workspace, refuses overlap with any other active boundary, and records an audit event attributed to an active System Owner. Ambiguous identities, a workspace assigned to another province, an inactive workspace, or a conflicting active boundary stop the whole province inside one transaction. No accounts, farmers, parcels or assistance records are created. The seeder is deliberately excluded from `DatabaseSeeder` and from automatic deployment.

### Negros Oriental

`negros_oriental_municipality_reference_boundaries.geojson` contains exactly the twenty-five features used by `NegrosOrientalMunicipalityBoundarySeeder`: 19 municipalities and 6 cities.

Four of these names exist in other provinces as well — La Libertad in Zamboanga del Norte, Valencia in Bukidnon, Santa Catalina in Ilocos Sur, and San Jose in several. None of them can be confused here, because features were selected by testing each candidate's centroid against the geoBoundaries ADM2 polygon for this province rather than by name.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3; boundary year 2020
- Pinned revision: `9469f09`; retrieved 2026-09-18
- Upstream sources: NAMRIA, Philippine Statistics Authority, OCHA Philippines
- License: CC BY 3.0 IGO
- [Pinned simplified source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA identity reference](https://psa.gov.ph/classification/psgc/citimuni/0704600000)
- [GeoRiskPH/PSA area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0)
- Line-ending-normalized SHA-256: `ce8eb57cf05fe08ae8e76c176fd7f78832569b3a91a480f98d6c2b55ec027dbc`

| Workspace | PSGC | Legacy PSGC | geoBoundaries shape ID | Reference hectares | Computed hectares | Deviation | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Amlan | `0704601000` | `074601000` | `30758251B50636289749121` | 5,926.749558 | 5,978.2892 | 0.87% | 39 |
| Ayungon | `0704602000` | `074602000` | `30758251B30231816662876` | 24,852.165533 | 25,031.0374 | 0.72% | 64 |
| Bacong | `0704603000` | `074603000` | `30758251B375051523504` | 4,042.294731 | 4,084.0460 | 1.03% | 18 |
| Bais City | `0704604000` | `074604000` | `30758251B97079733925928` | 25,248.938119 | 25,475.3352 | 0.90% | 108 |
| Basay | `0704605000` | `074605000` | `30758251B60263736267026` | 16,811.145658 | 16,956.6629 | 0.87% | 78 |
| Bindoy | `0704607000` | `074607000` | `30758251B7642209697633` | 15,653.732985 | 15,755.7211 | 0.65% | 59 |
| Canlaon City | `0704608000` | `074608000` | `30758251B22693356898691` | 14,751.859433 | 14,843.5300 | 0.62% | 40 |
| Bayawan City | `0704606000` | `074606000` | `30758251B98971931409152` | 69,964.126810 | 70,456.3906 | 0.70% | 175 |
| Guihulngan City | `0704611000` | `074611000` | `30758251B60818257765105` | 37,502.314301 | 37,785.2967 | 0.76% | 74 |
| Tanjay City | `0704621000` | `074621000` | `30758251B79018653951628` | 22,841.921660 | 22,992.1996 | 0.66% | 79 |
| Dauin | `0704609000` | `074609000` | `30758251B3670515091156` | 8,077.685876 | 8,160.4562 | 1.02% | 25 |
| Dumaguete City | `0704610000` | `074610000` | `30758251B75296993492221` | 3,430.800820 | 3,434.0389 | 0.09% | 19 |
| Jimalalud | `0704612000` | `074612000` | `30758251B35162320168665` | 15,476.719892 | 15,589.6027 | 0.73% | 23 |
| La Libertad | `0704613000` | `074613000` | `30758251B96075835138209` | 15,148.227410 | 15,248.3770 | 0.66% | 26 |
| Mabinay | `0704614000` | `074614000` | `30758251B10017268667222` | 34,614.837036 | 34,724.9492 | 0.32% | 86 |
| Manjuyod | `0704615000` | `074615000` | `30758251B66064059312986` | 12,769.815476 | 12,839.3607 | 0.55% | 79 |
| Pamplona | `0704616000` | `074616000` | `30758251B24092981342629` | 22,290.549506 | 22,356.5564 | 0.30% | 39 |
| San Jose | `0704617000` | `074617000` | `30758251B66844761060701` | 5,179.321878 | 5,204.5061 | 0.49% | 34 |
| Santa Catalina | `0704618000` | `074618000` | `30758251B88269025865066` | 41,438.112151 | 41,751.4876 | 0.76% | 122 |
| Siaton | `0704619000` | `074619000` | `30758251B76231588734027` | 42,766.148779 | 42,999.2538 | 0.55% | 141 |
| Sibulan | `0704620000` | `074620000` | `30758251B92406697043602` | 15,972.468123 | 16,118.4305 | 0.91% | 32 |
| Tayasan | `0704622000` | `074622000` | `30758251B37133567842554` | 17,727.108023 | 17,812.1371 | 0.48% | 32 |
| Valencia | `0704623000` | `074623000` | `30758251B45812920134288` | 16,280.599249 | 16,362.8555 | 0.51% | 31 |
| Vallehermoso | `0704624000` | `074624000` | `30758251B9618582640277` | 9,274.314897 | 9,321.0184 | 0.50% | 36 |
| Zamboanguita | `0704625000` | `074625000` | `30758251B7316515610413` | 15,343.284120 | 15,457.5066 | 0.74% | 59 |

```bash
php artisan db:seed --class=NegrosOrientalMunicipalityBoundarySeeder
```

The import creates or reuses each unambiguous active workspace, activates one idempotent planning reference per workspace, refuses overlap with any other active boundary, and records an audit event attributed to an active System Owner. Ambiguous identities, a workspace assigned to another province, an inactive workspace, or a conflicting active boundary stop the whole province inside one transaction. No accounts, farmers, parcels or assistance records are created. The seeder is deliberately excluded from `DatabaseSeeder` and from automatic deployment.

### Siquijor

`siquijor_municipality_reference_boundaries.geojson` contains exactly the six features used by `SiquijorMunicipalityBoundarySeeder` — the whole island province. The municipality of Siquijor shares its name with the province; the workspace keeps the municipality name.

- Dataset: geoBoundaries `gbOpen` Philippines ADM3; boundary year 2020
- Pinned revision: `9469f09`; retrieved 2026-09-18
- Upstream sources: NAMRIA, Philippine Statistics Authority, OCHA Philippines
- License: CC BY 3.0 IGO
- [Pinned simplified source](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson)
- [PSA identity reference](https://psa.gov.ph/classification/psgc/citimuni/0706100000)
- [GeoRiskPH/PSA area reference](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0)
- Line-ending-normalized SHA-256: `5338e3a60ca84516019321dfe056a0fc352c15ffbc38a4feff3dcd423c8e2a36`

| Workspace | PSGC | Legacy PSGC | geoBoundaries shape ID | Reference hectares | Computed hectares | Deviation | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Enrique Villanueva | `0706101000` | `076101000` | `30758251B12637401805285` | 2,629.750201 | 2,661.3248 | 1.20% | 33 |
| Larena | `0706102000` | `076102000` | `30758251B58465916430151` | 4,108.440665 | 4,145.6193 | 0.91% | 47 |
| Lazi | `0706103000` | `076103000` | `30758251B49075027129395` | 7,047.317244 | 7,099.3137 | 0.74% | 43 |
| Maria | `0706104000` | `076104000` | `30758251B45396932451822` | 5,664.253478 | 5,731.1743 | 1.18% | 54 |
| San Juan | `0706105000` | `076105000` | `30758251B12332276302021` | 4,024.321526 | 4,032.9284 | 0.21% | 48 |
| Siquijor | `0706106000` | `076106000` | `30758251B71387884042840` | 8,483.689999 | 8,547.4435 | 0.75% | 83 |

```bash
php artisan db:seed --class=SiquijorMunicipalityBoundarySeeder
```

The import creates or reuses each unambiguous active workspace, activates one idempotent planning reference per workspace, refuses overlap with any other active boundary, and records an audit event attributed to an active System Owner. Ambiguous identities, a workspace assigned to another province, an inactive workspace, or a conflicting active boundary stop the whole province inside one transaction. No accounts, farmers, parcels or assistance records are created. The seeder is deliberately excluded from `DatabaseSeeder` and from automatic deployment.

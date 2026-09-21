# Remaining Cordillera municipality planning references

Local setup and preservation checks are recorded separately in [the September 21 local setup receipt](REMAINING_CAR_LOCAL_SETUP_2026_09_21.md). The subsequently authorized Hostinger deployment is recorded in [the production receipt](CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md).

## Coverage and identities

These four explicit imports provide 53 planning/reference geofences for the remaining Cordillera Administrative Region (CAR) provinces. They complement the existing Benguet, Baguio City and Mountain Province references; they do not replace those references.

| Province scope | Municipalities | Cities | Total | Official identity directory |
| --- | ---: | ---: | ---: | --- |
| Abra | 27 | 0 | 27 | [PSA Abra](https://psa.gov.ph/classification/psgc/citimuni/1400100000) |
| Apayao | 7 | 0 | 7 | [PSA Apayao](https://psa.gov.ph/classification/psgc/citimuni/1408100000) |
| Ifugao | 11 | 0 | 11 | [PSA Ifugao](https://psa.gov.ph/classification/psgc/citimuni/1402700000) |
| Kalinga | 7 | 1 | 8 | [PSA Kalinga](https://psa.gov.ph/classification/psgc/citimuni/1403200000) |
| **Total** | **52** | **1** | **53** | |

The directory lists retrieved September 21, 2026 display municipality/city coverage as of July 31, 2025. Both current ten-digit PSGC and legacy correspondence codes were checked against the directories and the GeoRiskPH PSA layer. Kalinga codes are not consecutive: preserve the verified codes in the manifest, including Tabuk `1403213000` / `143213000`.

City of Tabuk uses the display name **Tabuk City** and remains in the Kalinga application scope. No separate city scope or account is created. Repeated municipality names use province-qualified workspace labels/codes: Dolores, La Paz, Pilar, San Isidro, San Juan and San Quintin in Abra; Luna in Apayao; and Rizal in Kalinga. The source `shapeName`, including Peñarrubia and City of Tabuk, remains unchanged for identity validation.

## Sources and independent geometry checks

Verified September 21, 2026. All geometry is unchanged from the [geoBoundaries gbOpen PHL ADM3 simplified snapshot, revision 9469f09](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson). Its [pinned metadata](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3-metaData.json) identifies boundary year 2020, a January 19, 2023 source update and December 12, 2023 build. Attribution: National Mapping and Resource Information Authority (NAMRIA), Philippine Statistics Authority (PSA), and OCHA Philippines. License: [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/).

Identity, placement, full-polygon comparison and reference areas were independently checked against the government [GeoRiskPH PSA Municipal layer](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0). The bounded query selected the four current PSGC province prefixes (`14001`, `14081`, `14027`, `14032`) and returned exactly 53 features. Fields used were `prov_name`, `city_name`, `city_code`, `psgc_10d`, `munarea_sqkm`, and `geographic_level`, with complete WGS84 geometry. Province placement was also checked against [geoBoundaries PHL ADM2 revision 41af8f1](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/41af8f1/releaseData/gbOpen/PHL/ADM2/geoBoundaries-PHL-ADM2_simplified.geojson).

Verification established:

- All 53 bundled geometries and their original source properties match the pinned ADM3 features exactly. Added province and PSGC properties match the separate government reference.
- Each GeoRiskPH polygon's interior point lies inside exactly one of the 1,647 pinned ADM3 features, and inside exactly its named province in the ADM2 snapshot. Names and both PSGC forms were checked independently of spatial matching.
- All selected source and reference polygons are valid. No pair of the 53 bundled polygons has a positive-area intersection; shared boundary lines are allowed.
- Every seeder `reference_area_ha` equals the corresponding GeoRiskPH `munarea_sqkm` multiplied by 100, within six-decimal rounding.
- Complete source/reference polygons were compared after projection to WGS84 / UTM zone 51N (EPSG:32651). The minimum intersection area divided by the smaller polygon's area is **99.187121%**. This is a shape-consistency check, not a claim of survey accuracy.
- Areas were recomputed using the application's spherical ring formula (radius 6,378,137 metres), including subtraction of interior rings. The largest difference from the independent reference area is **1.631244%**, below the unchanged 3% importer guard. Coordinates and areas were not adjusted to pass that guard.

| Province | Features | Vertices, including ring closure | Minimum polygon overlap | Maximum application-area difference |
| --- | ---: | ---: | ---: | ---: |
| Abra | 27 | 908 | 99.187121% | 1.631244% |
| Apayao | 7 | 590 | 99.612857% | 0.900535% |
| Ifugao | 11 | 850 | 99.610366% | 0.736793% |
| Kalinga | 8 | 650 | 99.467194% | 0.777747% |
| **Total** | **53** | **2,998** | | |

These are approximate administrative planning references, not legal, cadastral or survey-grade boundaries and not evidence of land ownership. The sources may share upstream administrative data, so agreement is not an independent field survey. Boundary age and simplification can differ from newer LGU records, barangay boundaries and parcel surveys. LGU/NAMRIA verification remains necessary before official use.

## Explicit import and preservation

Verify a target database backup before running these named seeders. Each province imports inside the existing shared lock and database transaction; to require all four provinces to succeed together, invoke the four seeders inside an outer database transaction during a brief maintenance window.

```powershell
php artisan db:seed --class=AbraMunicipalityBoundarySeeder
php artisan db:seed --class=ApayaoMunicipalityBoundarySeeder
php artisan db:seed --class=IfugaoMunicipalityBoundarySeeder
php artisan db:seed --class=KalingaMunicipalityBoundarySeeder
```

Append `--force` only for an explicitly authorized production import. Keep these seeders outside `DatabaseSeeder`; do not run demo seeders or legacy baseline migrations. An active System Owner or properly assigned province Super Admin is required for audit attribution. Check all imported municipality map responses and unchanged repeat imports after applying.

Deploy only the four named seeder classes and their matching files under `database/seeders/data/`, alongside the already established shared reference importer, geometry and authorization dependencies. No schema migration, new application dependency, public asset or account provisioning is required. Copying source files does not insert the references. This document records source provenance and geometry verification; actual setup and preservation checks belong in the linked local receipt or an explicitly authorized production release record.

The importer rejects ambiguous, inactive or mismatched workspaces, altered existing boundaries, and overlapping active geofences; a failed province rolls back. It does not transfer municipality ownership or create farmer, account, parcel or assistance records. Re-importing unchanged data preserves municipality/boundary IDs, geometry, styles and audit history, and invalidates the active-boundary caches.

## Pinned snapshot checksums

SHA-256 is computed after normalizing CRLF/CR to LF, matching the importer. The four snapshots total **134,560 bytes**. All 53 shape IDs, current PSGC values and legacy PSGC values are unique.

| File in `database/seeders/data/` | SHA-256 |
| --- | --- |
| `abra_municipality_reference_boundaries.geojson` | `7567d8a1aaea0830f2f89cf720295c3129df78ff8227169cf56a713399c6f688` |
| `apayao_municipality_reference_boundaries.geojson` | `9f5a2c4cbe88cadc6f68dbd4c4d2e8803f11b2a4d7e1728ad0d782a217f5aef3` |
| `ifugao_municipality_reference_boundaries.geojson` | `3a42a2a8c550739aa5049c63686e50d1f56f2a49fcbcf09985102570cb9cc610` |
| `kalinga_municipality_reference_boundaries.geojson` | `1e2833121bbbc00d5f94b1dd338cf6b0fed8f4c9c2a40f5e80eea64b57eb67be` |

## Identity and area manifest

Reference hectares come from GeoRiskPH; computed hectares use the application formula. Difference is the absolute percentage difference from reference hectares. Full precision is retained in the seeders and snapshots; the display below rounds computed hectares to four decimal places.

### Abra

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Bangued | 1400101000 | 140101000 | 30758251B80986632520556 | 11906.420646 | 11956.5115 | 0.421% | 58 |
| Boliney | 1400102000 | 140102000 | 30758251B13786076452973 | 17832.879756 | 17954.7000 | 0.683% | 17 |
| Bucay | 1400103000 | 140103000 | 30758251B14856837893218 | 9897.100382 | 10019.1040 | 1.233% | 41 |
| Bucloc | 1400104000 | 140104000 | 30758251B79611971032611 | 5734.391119 | 5825.5133 | 1.589% | 7 |
| Daguioman | 1400105000 | 140105000 | 30758251B44200269930989 | 9320.935128 | 9396.0492 | 0.806% | 14 |
| Danglas | 1400106000 | 140106000 | 30758251B62080674361927 | 17648.258783 | 17711.6904 | 0.359% | 44 |
| Dolores (Abra) | 1400107000 | 140107000 | 30758251B59360530740420 | 4462.828055 | 4500.8252 | 0.851% | 49 |
| La Paz (Abra) | 1400108000 | 140108000 | 30758251B73355219145022 | 5262.259645 | 5289.1344 | 0.511% | 59 |
| Lacub | 1400109000 | 140109000 | 30758251B49007488326258 | 24801.330986 | 24998.1202 | 0.793% | 27 |
| Lagangilang | 1400110000 | 140110000 | 30758251B70934774577797 | 9082.134324 | 9130.9890 | 0.538% | 45 |
| Lagayan | 1400111000 | 140111000 | 30758251B52711764832029 | 13288.645618 | 13408.1747 | 0.899% | 18 |
| Langiden | 1400112000 | 140112000 | 30758251B76383388538181 | 8971.393844 | 9025.0441 | 0.598% | 27 |
| Licuan-Baay | 1400113000 | 140113000 | 30758251B20296785159128 | 27523.021594 | 27668.8043 | 0.530% | 36 |
| Luba | 1400114000 | 140114000 | 30758251B28031934346608 | 13699.622273 | 13766.5871 | 0.489% | 24 |
| Malibcong | 1400115000 | 140115000 | 30758251B83293009371057 | 25912.552467 | 26072.8717 | 0.619% | 51 |
| Manabo | 1400116000 | 140116000 | 30758251B10650889631095 | 7706.377977 | 7779.4063 | 0.948% | 23 |
| Peñarrubia | 1400117000 | 140117000 | 30758251B26055192446489 | 3953.921592 | 3960.3332 | 0.162% | 22 |
| Pidigan | 1400118000 | 140118000 | 30758251B9631620004584 | 5489.933666 | 5520.8642 | 0.563% | 30 |
| Pilar (Abra) | 1400119000 | 140119000 | 30758251B40403541911249 | 8480.951882 | 8541.2268 | 0.711% | 30 |
| Sallapadan | 1400120000 | 140120000 | 30758251B64291084214185 | 13333.001825 | 13323.4850 | 0.071% | 27 |
| San Isidro (Abra) | 1400121000 | 140121000 | 30758251B37314448976896 | 4501.186948 | 4574.6123 | 1.631% | 29 |
| San Juan (Abra) | 1400122000 | 140122000 | 30758251B17638049183402 | 6842.630345 | 6867.8751 | 0.369% | 28 |
| San Quintin (Abra) | 1400123000 | 140123000 | 30758251B76623665911400 | 5727.083368 | 5752.1872 | 0.438% | 25 |
| Tayum | 1400124000 | 140124000 | 30758251B59448438391168 | 4836.585087 | 4854.8960 | 0.379% | 28 |
| Tineg | 1400125000 | 140125000 | 30758251B75797793552345 | 74435.567530 | 74821.6734 | 0.519% | 48 |
| Tubo | 1400126000 | 140126000 | 30758251B14618145037490 | 43135.479629 | 43343.4372 | 0.482% | 57 |
| Villaviciosa | 1400127000 | 140127000 | 30758251B55452258834598 | 8878.121331 | 8898.5984 | 0.231% | 44 |

### Apayao

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Calanasan | 1408101000 | 148101000 | 30758251B87608274030040 | 136340.986649 | 136980.8070 | 0.469% | 72 |
| Conner | 1408102000 | 148102000 | 30758251B47699313654035 | 70414.978952 | 70872.4047 | 0.650% | 80 |
| Flora | 1408103000 | 148103000 | 30758251B46507023603646 | 24374.057626 | 24514.9384 | 0.578% | 118 |
| Kabugao | 1408104000 | 148104000 | 30758251B69063872929594 | 98967.918393 | 99478.0330 | 0.515% | 71 |
| Luna (Apayao) | 1408105000 | 148105000 | 30758251B75623612075236 | 32065.787931 | 32354.5515 | 0.901% | 50 |
| Pudtol | 1408106000 | 148106000 | 30758251B84586369455003 | 30226.135635 | 30392.4593 | 0.550% | 171 |
| Santa Marcela | 1408107000 | 148107000 | 30758251B35080960553319 | 6491.518809 | 6496.3349 | 0.074% | 28 |

### Ifugao

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Banaue | 1402701000 | 142701000 | 30758251B61409714569525 | 19218.308058 | 19300.9612 | 0.430% | 102 |
| Hungduan | 1402702000 | 142702000 | 30758251B52637175911657 | 24145.179992 | 24292.0417 | 0.608% | 65 |
| Kiangan | 1402703000 | 142703000 | 30758251B41727580901072 | 11913.195508 | 11958.5696 | 0.381% | 59 |
| Lagawe | 1402704000 | 142704000 | 30758251B93228488685386 | 22899.397926 | 23057.1641 | 0.689% | 153 |
| Lamut | 1402705000 | 142705000 | 30758251B56792461965852 | 15035.825625 | 15141.8432 | 0.705% | 79 |
| Mayoyao | 1402706000 | 142706000 | 30758251B71362999273154 | 27380.868347 | 27519.7559 | 0.507% | 179 |
| Alfonso Lista | 1402707000 | 142707000 | 30758251B66647693295025 | 35875.623931 | 36139.9529 | 0.737% | 54 |
| Aguinaldo | 1402708000 | 142708000 | 30758251B46587647623990 | 45010.671715 | 45258.1450 | 0.550% | 75 |
| Hingyon | 1402709000 | 142709000 | 30758251B97249489621856 | 5724.673534 | 5738.0482 | 0.234% | 26 |
| Tinoc | 1402710000 | 142710000 | 30758251B8715829050570 | 22921.988810 | 22986.4412 | 0.281% | 22 |
| Asipulo | 1402711000 | 142711000 | 30758251B45532258933091 | 20539.942024 | 20617.6129 | 0.378% | 36 |

### Kalinga

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Balbalan | 1403201000 | 143201000 | 30758251B68760581282795 | 63197.490358 | 63506.5990 | 0.489% | 131 |
| Lubuagan | 1403206000 | 143206000 | 30758251B13511507037260 | 6243.021907 | 6252.3722 | 0.150% | 38 |
| Pasil | 1403208000 | 143208000 | 30758251B66518683632982 | 33702.690987 | 33892.4342 | 0.563% | 74 |
| Pinukpuk | 1403209000 | 143209000 | 30758251B26297568532116 | 36613.426858 | 36808.8554 | 0.534% | 67 |
| Rizal (Kalinga) | 1403211000 | 143211000 | 30758251B32653531312940 | 18478.876189 | 18622.5952 | 0.778% | 32 |
| Tabuk City | 1403213000 | 143213000 | 30758251B17662801564014 | 68984.219210 | 69356.8165 | 0.540% | 110 |
| Tanudan | 1403214000 | 143214000 | 30758251B98776308730929 | 33895.025441 | 34083.3428 | 0.556% | 141 |
| Tinglayan | 1403215000 | 143215000 | 30758251B33701077243030 | 21394.019836 | 21534.9632 | 0.659% | 57 |

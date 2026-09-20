# Negros Island Region planning geofences

Current status: deployed to Hostinger on September 20, 2026 in release 54cc9e4. Earlier local-only statements below describe implementation-stage checks. See FULL_DEPLOYMENT_2026_09_20.md for the deployment receipt and remaining limits.

## Coverage and supervision

The full Negros Island Region includes 63 municipality/city references: 44 municipalities and 19 cities. Identities were checked on September 20, 2026 against the [PSA Region XVIII directory](https://psa.gov.ph/classification/psgc/provinces/1800000000) and [city list](https://psa.gov.ph/classification/psgc/cities/1800000000).

| Application supervision scope | References |
| --- | ---: |
| Negros Occidental | 31 |
| Negros Oriental | 25 |
| Siquijor | 6 |
| Bacolod City | 1 |
| **Total** | **63** |

The owner explicitly selected the full region including Siquijor and a separate Bacolod City access scope. Bacolod is a highly urbanized city, PSGC `1830200000`; its legacy government geometry identifier is `064501000`. Its source `geographic_province` remains Negros Occidental, while application supervision uses a separate province-scope record named **Bacolod City**. This record represents an access scope, not a new geographic province. Negros Occidental administrators cannot access Bacolod through their provincial assignment. [PSA HUC directory](https://psa.gov.ph/classification/psgc/hucs).

## Sources and accuracy

Geometry is unchanged from the [geoBoundaries gbOpen PHL ADM3 simplified snapshot, revision 9469f09](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson), boundary year 2020. Upstream attribution: NAMRIA, Philippine Statistics Authority and OCHA Philippines. License: [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/).

All 63 source features were matched independently against the government-hosted [GeoRiskPH PSA Municipal layer](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0), using interior points, normalized names and legacy identifiers. Province placement was checked against [pinned ADM2 revision 41af8f1](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/41af8f1/releaseData/gbOpen/PHL/ADM2/geoBoundaries-PHL-ADM2_simplified.geojson). Every shape is valid. Intersection area divided by the smaller of the two shapes' areas is at least 99.023%; this is a source-comparison measure, not a survey-accuracy claim. The existing 3% application area-deviation guard remains unchanged. All region pairs pass the application's non-overlap check; shared edges are allowed.

These are approximate municipality/city planning references. They are not barangay boundaries, cadastral surveys, legal boundary determinations, or proof of land ownership. Obtain LGU/NAMRIA verification before official use. Coordinates and island components have not been clipped or shifted to force agreement with another source.

## Current identities and preserved records

The previous snapshots used region prefixes `06` and `07`. Their current 10-digit PSGC metadata now uses prefix `18`, checked against the [Negros Occidental](https://psa.gov.ph/classification/psgc/citimuni/1804500000), [Negros Oriental](https://psa.gov.ph/classification/psgc/citimuni/1804600000) and [Siquijor](https://psa.gov.ph/classification/psgc/citimuni/1806100000) directories. The government geometry service still carries the older region identifiers. Both its 9-digit legacy identifier and the previous 10-digit identifier remain compatible lookup aliases.

All existing workspace names, codes, numeric IDs and coordinates are preserved. Source spellings such as `Hinoba-An` remain stable import identities; the current official name `Hinoba-an` is metadata. `San Jose (Negros Oriental)` stays province-qualified to avoid taking over Tarlac's existing San Jose. No existing audits are rewritten merely to update source metadata.

## Explicit application and deployment

Back up the target database and verify its environment and baseline first. No schema migration is required. The seeders are excluded from `DatabaseSeeder` and automatic deployment. They create no accounts, farmers or assistance records.

**Existing Bacolod under Negros Occidental requires an explicit ownership review before running the Bacolod seeder.** Inspect its current municipality identity, supervising province, attached accounts and operational records. The import intentionally rejects a wrong province; never weaken that check or delete/recreate Bacolod to bypass it. An approved transfer must lock and freshness-check the existing municipality, preserve its ID and boundary, change its supervising foreign key and compatibility province string together, and record an owner-only scope-change audit. Keep historical audit province snapshots intact. Review account and record implications for each target database independently; a local decision does not authorize a production transfer.

The September 20 local setup preserved all 63 existing geofences and performed that explicit Bacolod separation after a verified backup. No Bacolod accounts or operational records existed. See [local verification](NEGROS_ISLAND_LOCAL_SETUP_2026_09_20.md).

Once supervising scopes are correct, apply only these named imports:

```powershell
php artisan db:seed --class=NegrosOccidentalMunicipalityBoundarySeeder
php artisan db:seed --class=NegrosOrientalMunicipalityBoundarySeeder
php artisan db:seed --class=SiquijorMunicipalityBoundarySeeder
php artisan db:seed --class=BacolodCityBoundarySeeder
```

Each seeder is atomic. For a complete-region operation, use an outer transaction during a brief maintenance window so a later conflict rolls back all four. An active System Owner or a Super Admin assigned to the target scope is required. Only the System Owner can configure a missing scope. Append `--force` only for an explicitly authorized production deployment.

The shared importer verifies checksums, identities, reference areas and geometry. Ambiguous or inactive workspaces, wrong supervision, altered reference boundaries and overlaps stop the import. Repeat imports preserve existing colors, geometry, IDs and audit history while clearing active-boundary caches. Deploy all four seeders and four matching GeoJSON snapshots together, with the existing shared importer and province-access dependencies. Copying code alone does not apply database changes. This work has not been deployed to Hostinger.

## Pinned snapshot checksums

SHA-256 after normalizing CRLF/CR to LF:

- `negros_occidental_municipality_reference_boundaries.geojson`: `ababd0cf97d3d1ac56cddd31bfdd85969747f0d1c515369768e81585d27a3480`
- `negros_oriental_municipality_reference_boundaries.geojson`: `707cdc20012ee7c5f0160c6e95c8e1a3e3c4ccafe4ed3f61cbe75d5e2fe3c9b3`
- `siquijor_municipality_reference_boundaries.geojson`: `45cffb5171c27ddc9b942ee32ee971f7aff13adaf7d2cc8613fa7cb036497e37`
- `bacolod_city_reference_boundary.geojson`: `063adfbef92b4403ae76146f6e7d138203fbbf8fc373ce22064a14e811512d20`

Combined snapshots: 213,884 bytes with 63 unique feature IDs and current PSGC codes. Source coordinates match the previous tracked snapshots exactly; changes consist of identity metadata and Bacolod's separation into its own file.

## Identity manifest

### Negros Occidental

| Existing workspace | Current PSA name | Current PSGC | Legacy geometry PSGC |
| --- | --- | --- | --- |
| Bago City | City of Bago | 1804502000 | 064502000 |
| Binalbagan | Binalbagan | 1804503000 | 064503000 |
| Cadiz City | City of Cadiz | 1804504000 | 064504000 |
| Calatrava | Calatrava | 1804505000 | 064505000 |
| Candoni | Candoni | 1804506000 | 064506000 |
| Cauayan | Cauayan | 1804507000 | 064507000 |
| Enrique B. Magalona | Enrique B. Magalona | 1804508000 | 064508000 |
| Escalante City | City of Escalante | 1804509000 | 064509000 |
| Himamaylan City | City of Himamaylan | 1804510000 | 064510000 |
| Hinigaran | Hinigaran | 1804511000 | 064511000 |
| Hinoba-An | Hinoba-an | 1804512000 | 064512000 |
| Ilog | Ilog | 1804513000 | 064513000 |
| Isabela | Isabela | 1804514000 | 064514000 |
| Kabankalan City | City of Kabankalan | 1804515000 | 064515000 |
| La Carlota City | City of La Carlota | 1804516000 | 064516000 |
| La Castellana | La Castellana | 1804517000 | 064517000 |
| Manapla | Manapla | 1804518000 | 064518000 |
| Moises Padilla | Moises Padilla | 1804519000 | 064519000 |
| Murcia | Murcia | 1804520000 | 064520000 |
| Pontevedra | Pontevedra | 1804521000 | 064521000 |
| Pulupandan | Pulupandan | 1804522000 | 064522000 |
| Sagay City | City of Sagay | 1804523000 | 064523000 |
| San Carlos City | City of San Carlos | 1804524000 | 064524000 |
| San Enrique | San Enrique | 1804525000 | 064525000 |
| Silay City | City of Silay | 1804526000 | 064526000 |
| Sipalay City | City of Sipalay | 1804527000 | 064527000 |
| Talisay City | City of Talisay | 1804528000 | 064528000 |
| Toboso | Toboso | 1804529000 | 064529000 |
| Valladolid | Valladolid | 1804530000 | 064530000 |
| Victorias City | City of Victorias | 1804531000 | 064531000 |
| Salvador Benedicto | Salvador Benedicto | 1804532000 | 064532000 |

### Negros Oriental

| Existing workspace | Current PSA name | Current PSGC | Legacy geometry PSGC |
| --- | --- | --- | --- |
| Amlan | Amlan | 1804601000 | 074601000 |
| Ayungon | Ayungon | 1804602000 | 074602000 |
| Bacong | Bacong | 1804603000 | 074603000 |
| Bais City | City of Bais | 1804604000 | 074604000 |
| Basay | Basay | 1804605000 | 074605000 |
| Bayawan City | City of Bayawan | 1804606000 | 074606000 |
| Bindoy | Bindoy | 1804607000 | 074607000 |
| Canlaon City | City of Canlaon | 1804608000 | 074608000 |
| Dauin | Dauin | 1804609000 | 074609000 |
| Dumaguete City | City of Dumaguete | 1804610000 | 074610000 |
| Guihulngan City | City of Guihulngan | 1804611000 | 074611000 |
| Jimalalud | Jimalalud | 1804612000 | 074612000 |
| La Libertad | La Libertad | 1804613000 | 074613000 |
| Mabinay | Mabinay | 1804614000 | 074614000 |
| Manjuyod | Manjuyod | 1804615000 | 074615000 |
| Pamplona | Pamplona | 1804616000 | 074616000 |
| San Jose (Negros Oriental) | San Jose | 1804617000 | 074617000 |
| Santa Catalina | Santa Catalina | 1804618000 | 074618000 |
| Siaton | Siaton | 1804619000 | 074619000 |
| Sibulan | Sibulan | 1804620000 | 074620000 |
| Tanjay City | City of Tanjay | 1804621000 | 074621000 |
| Tayasan | Tayasan | 1804622000 | 074622000 |
| Valencia | Valencia | 1804623000 | 074623000 |
| Vallehermoso | Vallehermoso | 1804624000 | 074624000 |
| Zamboanguita | Zamboanguita | 1804625000 | 074625000 |

### Siquijor

| Existing workspace | Current PSA name | Current PSGC | Legacy geometry PSGC |
| --- | --- | --- | --- |
| Enrique Villanueva | Enrique Villanueva | 1806101000 | 076101000 |
| Larena | Larena | 1806102000 | 076102000 |
| Lazi | Lazi | 1806103000 | 076103000 |
| Maria | Maria | 1806104000 | 076104000 |
| San Juan | San Juan | 1806105000 | 076105000 |
| Siquijor | Siquijor | 1806106000 | 076106000 |

### Bacolod City

| Existing workspace | Current PSA name | Current PSGC | Legacy geometry PSGC |
| --- | --- | --- | --- |
| Bacolod City | City of Bacolod | 1830200000 | 064501000 |

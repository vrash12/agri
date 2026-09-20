# Region I municipality and city planning boundaries

## Coverage and source

125 workspaces: Ilocos Norte 23, Ilocos Sur 34, La Union 20, Pangasinan 48. This covers the 9 cities and 116 municipalities reported by [PSA Region I](https://rsso01.psa.gov.ph/content/psa-rsso-i-completes-100-turnover-2024-cbms-data-lgus-ilocos-region). Retrieved 2026-09-19.

- Geometry: [geoBoundaries gbOpen PHL ADM3, revision 9469f09](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson), boundary year 2020.
- Upstream attribution: NAMRIA, Philippine Statistics Authority, OCHA Philippines; [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/).
- Province identity check: [pinned ADM2 revision 41af8f1](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/41af8f1/releaseData/gbOpen/PHL/ADM2/geoBoundaries-PHL-ADM2_simplified.geojson).
- Municipality PSGC identities, extents and land-area references: [GeoRiskPH PSA Municipal layer](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0).
- Each selected feature centroid falls inside its province and exactly one GeoRiskPH municipality in that province. Source names and both PSGC formats were then checked. Names alone were not used to select geometry.
- Snapshot geometry is unchanged from the pinned simplified source. Added feature metadata identifies the matched province and PSGC.

These are approximate planning references, not legal, cadastral, or survey-grade boundaries. LGU/NAMRIA verification is required for official use. Activating them enables the existing parcel-containment checks; an administrative outline does not establish land ownership or suitability for farming.

## Paoay area convention

The pinned simplified and full-resolution sources both include Paoay Lake inside the Paoay outer boundary; neither has an interior lake ring. The GeoRiskPH land-area attribute is 6,639.244887 ha and excludes the lake. Comparing those different area definitions would incorrectly fail the 3% guard. The GeoRiskPH exterior ring, measured with the application geometry method, is 6,988.0045 ha, matching the pinned full-resolution exterior to four decimals. Paoay therefore uses that lake-inclusive reference. The unchanged simplified outline measures 6,990.1577 ha, a 0.0308% difference. The 3% guard is unchanged for every feature. Lake water is inside this administrative reference; it must not be interpreted as registered farmland.

[Pinned full-resolution source used for the Paoay comparison](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3.geojson).

## Workspace identity and imports

Repeated Philippine municipality names use a province-qualified label and code, for example `San Manuel (Pangasinan)` / `SAN_MANUEL_PANGASINAN`. This preserves existing Tarlac, Bulacan, Negros and Siquijor workspaces and distinguishes Burgos in all four Region I provinces. Cities use the existing `Batac City` naming pattern. Dagupan City is included under the geographic Pangasinan planning group from the source; its independent-component-city status is unchanged. No account or permission assignment is made by these imports. Existing conflicting province assignments stop the import.

Each named seeder uses `ReferenceMunicipalityBoundaryImporter` and imports one whole province atomically under the shared activation lock. All source identities and checksum are validated first. Wrong province, inactive workspace, ambiguous match, overlap, or a changed active reference stops that province. Re-running an unchanged source preserves IDs, styles, geometry and audit history. Only missing active province/municipality workspaces and their boundaries are created; no users, farmers, parcels or releases are created. These seeders are excluded from `DatabaseSeeder` and automatic deployment.

Back up and verify the target database, then run only the requested named seeders (append `--force` in production):

```powershell
php artisan db:seed --class=IlocosNorteMunicipalityBoundarySeeder
php artisan db:seed --class=IlocosSurMunicipalityBoundarySeeder
php artisan db:seed --class=LaUnionMunicipalityBoundarySeeder
php artisan db:seed --class=PangasinanMunicipalityBoundarySeeder
```

A deployment importing all four should use an outer database transaction and a brief maintenance window so a late conflict rolls back the whole regional addition. Do not run legacy baseline migrations or other demo/reference seeders as part of this import.

## Pinned source checksums

SHA-256 is computed after normalizing CRLF/CR to LF.

- `seeders/data/ilocos_norte_municipality_reference_boundaries.geojson`: `672b4c00708dafe423fc57fe0cd7c4a6694d7f6d64b9675db488d67592264bd1`
- `seeders/data/ilocos_sur_municipality_reference_boundaries.geojson`: `7fcd72995bb4e3da22f284462fb50281f231e2502898fbde024c629a6fe9d718`
- `seeders/data/la_union_municipality_reference_boundaries.geojson`: `b32c5e1a410d10bdfd4c052dd759d3841ff964f66f7daea85e469d124770671c`
- `seeders/data/pangasinan_municipality_reference_boundaries.geojson`: `53e3118121306770718893d069ba7014aa713f564130500991bc6796fc9c0524`

## Verified identities and measurements

Reference hectares are the GeoRiskPH land-area attribute except for the documented Paoay lake-inclusive convention. Computed hectares use the application spherical method.

### Ilocos Norte

| Workspace | PSGC | Source shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| Adams | `0102801000` | `30758251B18474549888444` | 11114.302613 | 11193.3303 | 0.711% | 10 |
| Bacarra | `0102802000` | `30758251B49214356355193` | 5530.319480 | 5576.2246 | 0.830% | 33 |
| Badoc | `0102803000` | `30758251B31971506880534` | 8068.396991 | 8130.0140 | 0.764% | 86 |
| Bangui | `0102804000` | `30758251B93501675208475` | 11505.904145 | 11614.6279 | 0.945% | 22 |
| Banna | `0102811000` | `30758251B16212654341581` | 9113.323970 | 9146.3991 | 0.363% | 40 |
| Burgos (Ilocos Norte) | `0102806000` | `30758251B71350051012392` | 13710.430527 | 13800.4125 | 0.656% | 44 |
| Carasi | `0102807000` | `30758251B21012772444668` | 17305.181593 | 17406.1096 | 0.583% | 28 |
| Batac City | `0102805000` | `30758251B15119904313284` | 15812.313219 | 15918.0969 | 0.669% | 80 |
| Currimao | `0102808000` | `30758251B25146130050891` | 3337.379679 | 3377.7132 | 1.209% | 58 |
| Dingras | `0102809000` | `30758251B52269325260495` | 10836.520556 | 10854.7470 | 0.168% | 45 |
| Dumalneg | `0102810000` | `30758251B34921997573635` | 6674.788395 | 6716.9777 | 0.632% | 35 |
| Laoag City | `0102812000` | `30758251B72838386588139` | 11005.624351 | 11092.2694 | 0.787% | 47 |
| Marcos | `0102813000` | `30758251B92654029938439` | 7940.420684 | 8005.2705 | 0.817% | 36 |
| Nueva Era | `0102814000` | `30758251B81029869492051` | 60859.575763 | 61230.6402 | 0.610% | 101 |
| Pagudpud | `0102815000` | `30758251B61370537104024` | 19317.290756 | 19412.5179 | 0.493% | 74 |
| Paoay | `0102816000` | `30758251B25110952192125` | 6988.004500 | 6990.1577 | 0.031% | 48 |
| Pasuquin | `0102817000` | `30758251B90243887174140` | 17667.404423 | 17776.6793 | 0.619% | 44 |
| Piddig | `0102818000` | `30758251B11502828535213` | 12358.218352 | 12435.7882 | 0.628% | 49 |
| Pinili | `0102819000` | `30758251B26496758793693` | 6361.009309 | 6398.3366 | 0.587% | 56 |
| San Nicolas (Ilocos Norte) | `0102820000` | `30758251B22278088652196` | 4194.411478 | 4225.8480 | 0.749% | 38 |
| Sarrat | `0102821000` | `30758251B82772093165971` | 5689.925635 | 5699.7622 | 0.173% | 23 |
| Solsona | `0102822000` | `30758251B20065822888258` | 9033.843787 | 9090.1261 | 0.623% | 44 |
| Vintar | `0102823000` | `30758251B43967243138313` | 53095.863833 | 53337.2613 | 0.455% | 44 |

### Ilocos Sur

| Workspace | PSGC | Source shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| Alilem | `0102901000` | `30758251B64135628542106` | 11441.433248 | 11537.6653 | 0.841% | 30 |
| Banayoyo | `0102902000` | `30758251B72662209315304` | 2509.398717 | 2542.2615 | 1.310% | 22 |
| Bantay | `0102903000` | `30758251B89028394199039` | 7511.539412 | 7589.7040 | 1.041% | 32 |
| Burgos (Ilocos Sur) | `0102904000` | `30758251B35633474040270` | 5092.764719 | 5116.0392 | 0.457% | 24 |
| Cabugao | `0102905000` | `30758251B86667520668841` | 11221.658858 | 11292.7994 | 0.634% | 46 |
| Caoayan | `0102907000` | `30758251B94372236669127` | 1751.657184 | 1768.9364 | 0.986% | 22 |
| Cervantes | `0102908000` | `30758251B37414079527614` | 24298.974322 | 24510.6150 | 0.871% | 25 |
| Candon City | `0102906000` | `30758251B9776220250356` | 7757.519282 | 7791.9291 | 0.444% | 72 |
| Vigan City | `0102934000` | `30758251B76648161873125` | 2445.842637 | 2454.3312 | 0.347% | 24 |
| Galimuyod | `0102909000` | `30758251B74693062950388` | 3411.987235 | 3457.7724 | 1.342% | 48 |
| Gregorio del Pilar | `0102910000` | `30758251B56010971079591` | 5407.875132 | 5433.5054 | 0.474% | 14 |
| Lidlidda | `0102911000` | `30758251B93741779273567` | 3414.928446 | 3413.4965 | 0.042% | 15 |
| Magsingal | `0102912000` | `30758251B48026148837928` | 8242.057916 | 8276.5962 | 0.419% | 38 |
| Nagbukel | `0102913000` | `30758251B8709094769418` | 4242.012526 | 4235.2464 | 0.160% | 29 |
| Narvacan | `0102914000` | `30758251B65708530039691` | 10336.613439 | 10373.7350 | 0.359% | 60 |
| Quirino (Ilocos Sur) | `0102915000` | `30758251B76256934940324` | 18241.741152 | 18312.6203 | 0.389% | 32 |
| Salcedo (Ilocos Sur) | `0102916000` | `30758251B7667419388736` | 8296.613350 | 8324.1592 | 0.332% | 30 |
| San Emilio | `0102917000` | `30758251B68036145563068` | 15144.791390 | 15238.3088 | 0.617% | 36 |
| San Esteban | `0102918000` | `30758251B46730936238037` | 1741.314393 | 1736.0764 | 0.301% | 26 |
| San Ildefonso (Ilocos Sur) | `0102919000` | `30758251B66443331789119` | 1174.221069 | 1199.5789 | 2.160% | 22 |
| San Juan (Ilocos Sur) | `0102920000` | `30758251B22872212000715` | 6847.490751 | 6873.6741 | 0.382% | 32 |
| San Vicente (Ilocos Sur) | `0102921000` | `30758251B3589086876715` | 1321.344121 | 1330.3783 | 0.684% | 41 |
| Santa | `0102922000` | `30758251B7974842779568` | 5923.539640 | 5908.4123 | 0.255% | 58 |
| Santa Catalina (Ilocos Sur) | `0102923000` | `30758251B16983791159508` | 894.733246 | 893.3874 | 0.150% | 20 |
| Santa Cruz (Ilocos Sur) | `0102924000` | `30758251B48306075925177` | 9195.472182 | 9234.7187 | 0.427% | 21 |
| Santa Lucia | `0102925000` | `30758251B50348981469821` | 4422.021094 | 4461.2286 | 0.887% | 15 |
| Santa Maria (Ilocos Sur) | `0102926000` | `30758251B81485398953116` | 5648.753627 | 5675.2633 | 0.469% | 40 |
| Santiago (Ilocos Sur) | `0102927000` | `30758251B25270762862446` | 4775.420854 | 4814.9636 | 0.828% | 29 |
| Santo Domingo (Ilocos Sur) | `0102928000` | `30758251B23074202147270` | 4856.792368 | 4885.7473 | 0.596% | 44 |
| Sigay | `0102929000` | `30758251B41796700485463` | 9223.456555 | 9243.2586 | 0.215% | 14 |
| Sinait | `0102930000` | `30758251B31766600360488` | 6886.471995 | 6921.1950 | 0.504% | 46 |
| Sugpon | `0102931000` | `30758251B59811260952399` | 10632.606786 | 10682.1371 | 0.466% | 69 |
| Suyo | `0102932000` | `30758251B93318593456282` | 16362.876569 | 16436.5616 | 0.450% | 24 |
| Tagudin | `0102933000` | `30758251B59352820583653` | 5851.679239 | 5921.9224 | 1.200% | 29 |

### La Union

| Workspace | PSGC | Source shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| Agoo | `0103301000` | `30758251B44987687400589` | 4814.597423 | 4851.2297 | 0.761% | 24 |
| Aringay | `0103302000` | `30758251B96546251931007` | 9719.034315 | 9743.4877 | 0.252% | 52 |
| Bacnotan | `0103303000` | `30758251B79119480748241` | 7640.350706 | 7679.0023 | 0.506% | 36 |
| Bagulin | `0103304000` | `30758251B85285313745351` | 9057.080831 | 9104.2859 | 0.521% | 46 |
| Balaoan | `0103305000` | `30758251B45278202455383` | 6202.548386 | 6231.1409 | 0.461% | 48 |
| Bangar | `0103306000` | `30758251B10509197504559` | 3522.147226 | 3523.8878 | 0.049% | 27 |
| Bauang | `0103307000` | `30758251B39876538190612` | 7580.636746 | 7635.8621 | 0.729% | 35 |
| Burgos (La Union) | `0103308000` | `30758251B56891504947345` | 3645.546852 | 3664.1746 | 0.511% | 54 |
| Caba | `0103309000` | `30758251B6644033388737` | 4673.108868 | 4722.9932 | 1.067% | 13 |
| San Fernando City (La Union) | `0103314000` | `30758251B73043571821095` | 9889.084852 | 9979.0577 | 0.910% | 62 |
| Luna (La Union) | `0103310000` | `30758251B16717842706250` | 4050.564979 | 4063.7980 | 0.327% | 28 |
| Naguilian (La Union) | `0103311000` | `30758251B47724455039703` | 10244.518708 | 10284.8465 | 0.394% | 56 |
| Pugo | `0103312000` | `30758251B7939243888170` | 5207.680699 | 5226.8863 | 0.369% | 44 |
| Rosario (La Union) | `0103313000` | `30758251B6500282829815` | 7835.602667 | 7880.5611 | 0.574% | 28 |
| San Gabriel | `0103315000` | `30758251B9999882220346` | 12179.984879 | 12289.1974 | 0.897% | 56 |
| San Juan (La Union) | `0103316000` | `30758251B15811216691174` | 5059.452679 | 5104.8289 | 0.897% | 40 |
| Santo Tomas (La Union) | `0103317000` | `30758251B23413436311315` | 4098.131270 | 4152.0104 | 1.315% | 50 |
| Santol | `0103318000` | `30758251B51477832736818` | 11417.040330 | 11464.3874 | 0.415% | 59 |
| Sudipen | `0103319000` | `30758251B50085603864193` | 8986.288913 | 9036.2261 | 0.556% | 62 |
| Tubao | `0103320000` | `30758251B29046135341619` | 5433.687621 | 5457.3925 | 0.436% | 35 |

### Pangasinan

| Workspace | PSGC | Source shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| Agno | `0105501000` | `30758251B32569169377099` | 16578.194301 | 16679.9239 | 0.614% | 81 |
| Aguilar | `0105502000` | `30758251B6449590233962` | 15785.591484 | 15851.0639 | 0.415% | 73 |
| Alcala (Pangasinan) | `0105504000` | `30758251B69557506682239` | 5853.764258 | 5882.8161 | 0.496% | 37 |
| Anda (Pangasinan) | `0105505000` | `30758251B91588425521961` | 7764.258141 | 7788.9126 | 0.318% | 173 |
| Asingan | `0105506000` | `30758251B38099467848426` | 7844.085309 | 7921.5513 | 0.988% | 42 |
| Balungao | `0105507000` | `30758251B80051382357318` | 6833.510745 | 6896.0503 | 0.915% | 65 |
| Bani | `0105508000` | `30758251B65332849657156` | 18181.944318 | 18299.7804 | 0.648% | 97 |
| Basista | `0105509000` | `30758251B73737472539821` | 2559.600019 | 2576.2700 | 0.651% | 30 |
| Bautista | `0105510000` | `30758251B57565423435068` | 4139.972567 | 4137.1644 | 0.068% | 28 |
| Bayambang | `0105511000` | `30758251B52675843458221` | 13675.888240 | 13748.0278 | 0.527% | 66 |
| Binalonan | `0105512000` | `30758251B33945574118971` | 5651.689993 | 5672.1287 | 0.362% | 35 |
| Binmaley | `0105513000` | `30758251B32963538286638` | 6374.062260 | 6419.0249 | 0.705% | 39 |
| Bolinao | `0105514000` | `30758251B13808046660484` | 20607.485107 | 20722.6390 | 0.559% | 193 |
| Bugallon | `0105515000` | `30758251B34847802693751` | 17855.535439 | 17975.8832 | 0.674% | 93 |
| Burgos (Pangasinan) | `0105516000` | `30758251B63101931380870` | 11525.318543 | 11595.8725 | 0.612% | 61 |
| Calasiao | `0105517000` | `30758251B89445927723831` | 4743.708508 | 4794.1613 | 1.064% | 51 |
| Alaminos City | `0105503000` | `30758251B86620041002858` | 16178.147315 | 16213.2590 | 0.217% | 278 |
| Urdaneta City | `0105546000` | `30758251B48393463479416` | 9554.598815 | 9590.0052 | 0.371% | 68 |
| Dagupan City | `0105518000` | `30758251B56612448144443` | 4319.311424 | 4342.7453 | 0.543% | 71 |
| Dasol | `0105519000` | `30758251B21762929298212` | 17758.162562 | 17868.6857 | 0.622% | 149 |
| Infanta (Pangasinan) | `0105520000` | `30758251B79710996393461` | 19265.191725 | 19391.5410 | 0.656% | 145 |
| Labrador | `0105521000` | `30758251B3139099704884` | 10303.445134 | 10378.4898 | 0.728% | 55 |
| Laoac | `0105548000` | `30758251B62067131413205` | 4158.860072 | 4185.8308 | 0.649% | 54 |
| Lingayen | `0105522000` | `30758251B53919355677172` | 7142.083233 | 7189.9760 | 0.671% | 71 |
| Mabini (Pangasinan) | `0105523000` | `30758251B17069015122962` | 25692.220758 | 25812.6417 | 0.469% | 93 |
| Malasiqui | `0105524000` | `30758251B61747456024255` | 13793.173566 | 13914.6339 | 0.881% | 55 |
| Manaoag | `0105525000` | `30758251B26137399272056` | 5213.513521 | 5226.5052 | 0.249% | 73 |
| Mangaldan | `0105526000` | `30758251B9198007825029` | 4832.704256 | 4866.8519 | 0.707% | 59 |
| Mangatarem | `0105527000` | `30758251B89325271734359` | 30819.196267 | 31030.4938 | 0.686% | 95 |
| Mapandan | `0105528000` | `30758251B77092864318867` | 2041.576462 | 2062.3212 | 1.016% | 30 |
| Natividad | `0105529000` | `30758251B92137156068706` | 9400.386829 | 9450.6274 | 0.534% | 23 |
| Pozorrubio | `0105530000` | `30758251B62104294817864` | 7039.061378 | 7088.8869 | 0.708% | 37 |
| Rosales | `0105531000` | `30758251B40151767762503` | 7236.213777 | 7266.8934 | 0.424% | 51 |
| San Carlos City (Pangasinan) | `0105532000` | `30758251B59743133476894` | 14782.682979 | 14884.7774 | 0.691% | 97 |
| San Fabian | `0105533000` | `30758251B29498113350047` | 7582.527326 | 7622.2995 | 0.525% | 57 |
| San Jacinto (Pangasinan) | `0105534000` | `30758251B64973235851330` | 4235.870131 | 4256.8027 | 0.494% | 34 |
| San Manuel (Pangasinan) | `0105535000` | `30758251B7008952403937` | 13766.902422 | 13842.9413 | 0.552% | 49 |
| San Nicolas (Pangasinan) | `0105536000` | `30758251B87828725798091` | 22522.752747 | 22641.5732 | 0.528% | 37 |
| San Quintin (Pangasinan) | `0105537000` | `30758251B3543778984064` | 8569.901526 | 8645.9469 | 0.887% | 55 |
| Santa Barbara (Pangasinan) | `0105538000` | `30758251B59559004486187` | 6258.187365 | 6252.5778 | 0.090% | 54 |
| Santa Maria (Pangasinan) | `0105539000` | `30758251B42080069624714` | 4666.237295 | 4697.6331 | 0.673% | 66 |
| Santo Tomas (Pangasinan) | `0105540000` | `30758251B13733933289542` | 1239.102490 | 1245.4660 | 0.514% | 15 |
| Sison (Pangasinan) | `0105541000` | `30758251B50482796535590` | 15483.264399 | 15544.2673 | 0.394% | 54 |
| Sual | `0105542000` | `30758251B22367435141107` | 13268.146197 | 13366.0883 | 0.738% | 147 |
| Tayug | `0105543000` | `30758251B80378729545073` | 4398.161721 | 4417.6887 | 0.444% | 37 |
| Umingan | `0105544000` | `30758251B58003851100875` | 25845.899273 | 25995.4093 | 0.578% | 80 |
| Urbiztondo | `0105545000` | `30758251B10992261066615` | 4034.188288 | 4066.7129 | 0.806% | 48 |
| Villasis | `0105547000` | `30758251B82671579098228` | 8085.601831 | 8117.9148 | 0.400% | 46 |

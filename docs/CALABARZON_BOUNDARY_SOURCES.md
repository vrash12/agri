# CALABARZON planning/reference geofences

Status: deployed to Hostinger on September 22, 2026 through GitHub commit `496b706` and `git pull --ff-only origin main`. All 142 references are active.

## Coverage

The explicit `CalabarzonBoundarySeeder` adds 142 municipality/city references: Batangas 34, Cavite 23, Laguna 30, Quezon 40, Rizal 14, and a separate Lucena City scope 1. This matches the PSA total of 120 municipalities and 22 cities. Lucena is outside Quezon administrator access. No user accounts, credentials, operational data, or existing regional memberships are changed. Regional Head membership is configured explicitly with `php artisan region-access:configure --owner=<active-owner-id> --region=region4a` after the boundary import. The original no-option command retains its four-region scope. Configuration is atomic, audited and idempotent; missing, inactive or conflicting scopes stop it.

## Source and identity verification

Retrieved September 22, 2026. All coordinates are unchanged from [geoBoundaries gbOpen PHL ADM3 simplified revision 9469f09](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson), boundary year 2020. Attribution: NAMRIA, Philippine Statistics Authority and OCHA Philippines, [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/).

Each of the 142 [government GeoRiskPH PSA Municipal](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0) reference interior points matches exactly one source feature. Names, legacy PSGC and geographic province were checked independently, using [pinned ADM2 revision 41af8f1](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/41af8f1/releaseData/gbOpen/PHL/ADM2/geoBoundaries-PHL-ADM2_simplified.geojson) for province placement. All selected source geometries are valid. Minimum intersection divided by the smaller geometry area is 96.918%. Every selected shape ID and PSGC code is unique.

Current city naming and Lucena's PSGC follow the [PSA cities directory](https://psa.gov.ph/classification/psgc/cities/0400000000); municipalities follow the [PSA municipality directory](https://psa.gov.ph/classification/psgc/municipalities/0400000000). Calaca and Carmona are shown as cities; Sto. Tomas City retains its verified identity. Lucena uses current PSGC `0431200000` and legacy `045624000`. The original source shape names remain unchanged. Repeated municipality names receive province-qualified workspace labels and codes to avoid collisions with existing offices.

## Area conventions

The unchanged importer rejects differences above 3%. Most reference areas use the government layer's `munarea_sqkm` field. Two comparisons require explicit conventions:

- **Noveleta:** government spherical polygon area is 518.7056 ha; pinned simplified geometry is 531.4364 ha, a 2.4543% difference. The layer's published area is 515.66212 ha (3.0590% difference), so the polygon-to-polygon comparison uses the same measurement method.
- **Cavinti:** the government polygon has an interior water exclusion, while the pinned administrative outline has no interior ring. Comparing its land-only area with a water-inclusive outline would give 6.6055%. The independent government exterior measures 11673.7274 ha, compared with 11713.5687 ha for the unchanged source: 0.3413%. This follows the existing Paoay area convention. Water inside the outline is not registered farmland.

No coordinate scaling, source shape repair, hole deletion from the imported geometry, or relaxed global tolerance was used. These are approximate planning references, not certified legal, cadastral or survey boundaries. Obtain LGU/NAMRIA verification before official boundary decisions.

## Import and deployment

Plan: pin and validate the six source subsets, reuse the existing scoped importer, wrap the region in one transaction, and verify identity/ownership, conflicts and repeat-import preservation.

After a verified database backup, run only:

```sh
php artisan db:seed --class=CalabarzonBoundarySeeder
```

Use `--force` for an explicitly authorized production release. Do not run `DatabaseSeeder` or legacy migrations. The region wrapper rolls back all six scopes if any province fails. Existing source checksums, municipality identities, ownership, inactive-scope checks, boundary overlap checks, active-reference protection and the activation lock remain enforced by `ReferenceMunicipalityBoundaryImporter`. Repeat imports retain IDs, geometry, styling and audit records. Existing ambiguous or wrongly assigned workspaces stop the import instead of being moved or merged.

Deploy seven seeders and six GeoJSON files, plus `RegionSupervision` and `ConfigureRegionSupervision`, through GitHub and Hostinger git pull. No migration, package, public asset or view change is required. Installing the files alone does not activate geofences. Run during a short maintenance window, check counts and existing-row preservation, and confirm the scoped map endpoint afterward. Region-level supervision and account provisioning are separate operations.

## Validation

Focused SQLite tests cover all 142 boundaries and attribution, idempotence, Lucena isolation, conflicting/inactive workspaces, changed boundaries, checksum rejection, actor permissions, and whole-region rollback. Tests use temporary in-memory databases. All 12 boundary tests and 14 regional-access tests passed across the final runs. Pint, PHP syntax and whitespace checks passed.

## Snapshot checksums

SHA-256 after normalizing line endings to LF.

- `batangas_municipality_reference_boundaries.geojson`: `0436ca6ad04af368bbfe4998ab906738b2df9008996d75606c003e8b91b3584f`
- `cavite_municipality_reference_boundaries.geojson`: `8f43f66858b45cba348658b48322688b7d5bc91053fbecdff16354093d17f9a7`
- `laguna_municipality_reference_boundaries.geojson`: `1668da0ecf6c71e190643a8d11c85aa2435d75d053131f768bd8f5d471264d37`
- `lucena_city_municipality_reference_boundaries.geojson`: `c698115ef8f1ad722c6af53e7ea525394775b3467ce94f07cc8b2a7e38703532`
- `quezon_municipality_reference_boundaries.geojson`: `f55a6578b08ebafb311da6a555c26aca8754afe9b7defb839a74a4697dca4e71`
- `rizal_municipality_reference_boundaries.geojson`: `8cccd5c550216356ee30940ddfb79942130d96b12ce642bdfab7da98cbec3cfd`

## Identity and area manifest

| Scope | Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Source ha | Difference |
| --- | --- | --- | --- | --- | ---: | ---: | ---: |
| Batangas | Agoncillo | 0401001000 | 041001000 | 30758251B30231322331435 | 4878.608185 | 4927.3409 | 0.9989% |
| Batangas | Alitagtag | 0401002000 | 041002000 | 30758251B37438092207200 | 2547.218315 | 2562.5485 | 0.6018% |
| Batangas | Balayan | 0401003000 | 041003000 | 30758251B27285986197002 | 9300.106171 | 9387.6366 | 0.9412% |
| Batangas | Balete (Batangas) | 0401004000 | 041004000 | 30758251B9712922067328 | 2460.304108 | 2493.3325 | 1.3425% |
| Batangas | Batangas City | 0401005000 | 041005000 | 30758251B42170064226471 | 27436.991567 | 27647.4448 | 0.7670% |
| Batangas | Bauan | 0401006000 | 041006000 | 30758251B51291858387925 | 5298.834661 | 5354.0986 | 1.0429% |
| Batangas | Calaca City | 0401007000 | 041007000 | 30758251B68856681488998 | 11486.045340 | 11574.0060 | 0.7658% |
| Batangas | Calatagan | 0401008000 | 041008000 | 30758251B80871387182551 | 10624.356830 | 10685.5618 | 0.5761% |
| Batangas | Cuenca | 0401009000 | 041009000 | 30758251B33118282824163 | 3026.674521 | 3048.6712 | 0.7268% |
| Batangas | Ibaan | 0401010000 | 041010000 | 30758251B8254101193317 | 7031.969486 | 7081.8527 | 0.7094% |
| Batangas | Laurel | 0401011000 | 041011000 | 30758251B77759274087167 | 7397.952496 | 7449.8446 | 0.7014% |
| Batangas | Lemery (Batangas) | 0401012000 | 041012000 | 30758251B5665377491734 | 7211.173088 | 7243.1631 | 0.4436% |
| Batangas | Lian | 0401013000 | 041013000 | 30758251B81633231484096 | 8340.973120 | 8412.2969 | 0.8551% |
| Batangas | Lipa City | 0401014000 | 041014000 | 30758251B54075680975044 | 19128.185085 | 19281.7732 | 0.8029% |
| Batangas | Lobo | 0401015000 | 041015000 | 30758251B32727086192084 | 19203.802709 | 19375.2059 | 0.8925% |
| Batangas | Mabini (Batangas) | 0401016000 | 041016000 | 30758251B64129218321366 | 3972.405155 | 3990.4569 | 0.4544% |
| Batangas | Malvar | 0401017000 | 041017000 | 30758251B86715159474326 | 3440.440491 | 3450.8484 | 0.3025% |
| Batangas | Mataasnakahoy | 0401018000 | 041018000 | 30758251B55651779812419 | 1933.138263 | 1940.8228 | 0.3975% |
| Batangas | Nasugbu | 0401019000 | 041019000 | 30758251B8927375136038 | 26634.039656 | 26820.0933 | 0.6986% |
| Batangas | Padre Garcia | 0401020000 | 041020000 | 30758251B75041728100669 | 3928.040983 | 3939.3736 | 0.2885% |
| Batangas | Rosario (Batangas) | 0401021000 | 041021000 | 30758251B26885487370879 | 19902.426932 | 20022.7751 | 0.6047% |
| Batangas | San Jose (Batangas) | 0401022000 | 041022000 | 30758251B22922317245242 | 5823.569984 | 5837.7063 | 0.2427% |
| Batangas | San Juan (Batangas) | 0401023000 | 041023000 | 30758251B36989213936618 | 23756.421843 | 23870.3203 | 0.4794% |
| Batangas | San Luis (Batangas) | 0401024000 | 041024000 | 30758251B60634471978235 | 3769.648999 | 3792.7748 | 0.6135% |
| Batangas | San Nicolas (Batangas) | 0401025000 | 041025000 | 30758251B83345334824937 | 2132.789338 | 2151.1481 | 0.8608% |
| Batangas | San Pascual (Batangas) | 0401026000 | 041026000 | 30758251B99992201046216 | 3526.283095 | 3538.7286 | 0.3529% |
| Batangas | Santa Teresita (Batangas) | 0401027000 | 041027000 | 30758251B65020041021646 | 1536.735340 | 1567.7744 | 2.0198% |
| Batangas | Sto. Tomas City (Batangas) | 0401028000 | 041028000 | 30758251B29832257254847 | 8901.363530 | 8964.0714 | 0.7045% |
| Batangas | Taal | 0401029000 | 041029000 | 30758251B11016781940262 | 2705.741341 | 2700.2222 | 0.2040% |
| Batangas | Talisay (Batangas) | 0401030000 | 041030000 | 30758251B80500535312528 | 5920.366766 | 5953.2750 | 0.5558% |
| Batangas | City of Tanauan (Batangas) | 0401031000 | 041031000 | 30758251B62709822601123 | 11207.811953 | 11252.2410 | 0.3964% |
| Batangas | Taysan | 0401032000 | 041032000 | 30758251B32633454457385 | 9254.088968 | 9310.8759 | 0.6136% |
| Batangas | Tingloy | 0401033000 | 041033000 | 30758251B31190272548393 | 3258.485385 | 3300.2498 | 1.2817% |
| Batangas | Tuy | 0401034000 | 041034000 | 30758251B67063088197244 | 9354.064483 | 9392.1070 | 0.4067% |
| Cavite | Alfonso | 0402101000 | 042101000 | 30758251B26020772303451 | 7025.341452 | 7064.7535 | 0.5610% |
| Cavite | Amadeo | 0402102000 | 042102000 | 30758251B72252115450997 | 3592.539007 | 3626.8969 | 0.9564% |
| Cavite | Bacoor City | 0402103000 | 042103000 | 30758251B21872245709611 | 4901.032621 | 4926.5312 | 0.5203% |
| Cavite | Carmona City | 0402104000 | 042104000 | 30758251B42806803343696 | 2411.872927 | 2427.3687 | 0.6425% |
| Cavite | Cavite City | 0402105000 | 042105000 | 30758251B13765165414384 | 1187.371541 | 1206.9790 | 1.6513% |
| Cavite | City of DasmariÃƒÂ±as | 0402106000 | 042106000 | 30758251B92956518503760 | 9010.463234 | 9042.1294 | 0.3514% |
| Cavite | General Emilio Aguinaldo | 0402107000 | 042107000 | 30758251B7170785123118 | 4066.400670 | 4098.3808 | 0.7864% |
| Cavite | City of General Trias | 0402108000 | 042108000 | 30758251B92766896635284 | 8746.379768 | 8801.7467 | 0.6330% |
| Cavite | Imus City | 0402109000 | 042109000 | 30758251B96342431093437 | 5037.762060 | 5084.6113 | 0.9300% |
| Cavite | Indang | 0402110000 | 042110000 | 30758251B89143958154434 | 8900.269514 | 8961.2549 | 0.6852% |
| Cavite | Kawit | 0402111000 | 042111000 | 30758251B10181127311894 | 1595.889538 | 1580.8946 | 0.9396% |
| Cavite | Magallanes (Cavite) | 0402112000 | 042112000 | 30758251B48484598892391 | 6587.544642 | 6631.2092 | 0.6628% |
| Cavite | Maragondon | 0402113000 | 042113000 | 30758251B1630286055776 | 14157.735360 | 14253.3994 | 0.6757% |
| Cavite | Mendez | 0402114000 | 042114000 | 30758251B56327904281600 | 1468.383813 | 1485.1793 | 1.1438% |
| Cavite | Naic | 0402115000 | 042115000 | 30758251B11802167700274 | 7092.842467 | 7126.7587 | 0.4782% |
| Cavite | Noveleta | 0402116000 | 042116000 | 30758251B26560222225231 | 518.705600 | 531.4364 | 2.4543% |
| Cavite | Rosario (Cavite) | 0402117000 | 042117000 | 30758251B22134644718406 | 800.193713 | 798.6351 | 0.1948% |
| Cavite | Silang | 0402118000 | 042118000 | 30758251B65416136656933 | 14348.168787 | 14413.3813 | 0.4545% |
| Cavite | Tagaytay City | 0402119000 | 042119000 | 30758251B41438074837585 | 5478.916105 | 5511.8821 | 0.6017% |
| Cavite | Tanza | 0402120000 | 042120000 | 30758251B17999592905510 | 7453.885611 | 7517.7462 | 0.8567% |
| Cavite | Ternate | 0402121000 | 042121000 | 30758251B92987425451541 | 4651.859129 | 4672.0318 | 0.4336% |
| Cavite | Trece Martires City | 0402122000 | 042122000 | 30758251B97625371583583 | 4615.379061 | 4619.0300 | 0.0791% |
| Cavite | Gen. Mariano Alvarez | 0402123000 | 042123000 | 30758251B6653564464331 | 872.129517 | 887.2683 | 1.7358% |
| Laguna | Alaminos (Laguna) | 0403401000 | 043401000 | 30758251B86762521234938 | 5964.874764 | 6014.1130 | 0.8255% |
| Laguna | Bay | 0403402000 | 043402000 | 30758251B641829746077 | 3837.961627 | 3862.1621 | 0.6306% |
| Laguna | City of BiÃƒÂ±an | 0403403000 | 043403000 | 30758251B16723889149596 | 3758.713395 | 3785.6324 | 0.7162% |
| Laguna | Cabuyao City | 0403404000 | 043404000 | 30758251B33555947912261 | 4736.920351 | 4751.6793 | 0.3116% |
| Laguna | City of Calamba (Laguna) | 0403405000 | 043405000 | 30758251B25562993690796 | 13931.872962 | 14064.3390 | 0.9508% |
| Laguna | Calauan | 0403406000 | 043406000 | 30758251B84649155212336 | 7496.667049 | 7536.9913 | 0.5379% |
| Laguna | Cavinti | 0403407000 | 043407000 | 30758251B82709121735753 | 11673.727400 | 11713.5687 | 0.3413% |
| Laguna | Famy | 0403408000 | 043408000 | 30758251B75261766623391 | 3341.000774 | 3373.5147 | 0.9732% |
| Laguna | Kalayaan (Laguna) | 0403409000 | 043409000 | 30758251B7830805264743 | 5358.589340 | 5407.6115 | 0.9148% |
| Laguna | Liliw | 0403410000 | 043410000 | 30758251B58026034412685 | 4284.451371 | 4323.8628 | 0.9199% |
| Laguna | Los BaÃƒÂ±os | 0403411000 | 043411000 | 30758251B45670091737840 | 5554.619304 | 5581.2269 | 0.4790% |
| Laguna | Luisiana | 0403412000 | 043412000 | 30758251B80791162648573 | 7497.415865 | 7523.9312 | 0.3537% |
| Laguna | Lumban | 0403413000 | 043413000 | 30758251B19502788585364 | 8318.149355 | 8299.0652 | 0.2294% |
| Laguna | Mabitac | 0403414000 | 043414000 | 30758251B40270166730947 | 4767.440191 | 4800.5158 | 0.6938% |
| Laguna | Magdalena | 0403415000 | 043415000 | 30758251B37787171987907 | 3262.519825 | 3269.4667 | 0.2129% |
| Laguna | Majayjay | 0403416000 | 043416000 | 30758251B95762646893125 | 6025.977291 | 6058.1937 | 0.5346% |
| Laguna | Nagcarlan | 0403417000 | 043417000 | 30758251B18586490451466 | 7544.748029 | 7587.1549 | 0.5621% |
| Laguna | Paete | 0403418000 | 043418000 | 30758251B55553660747763 | 4846.623542 | 4853.7622 | 0.1473% |
| Laguna | Pagsanjan | 0403419000 | 043419000 | 30758251B62240018312192 | 2820.091540 | 2861.8173 | 1.4796% |
| Laguna | Pakil | 0403420000 | 043420000 | 30758251B26528649252550 | 4746.439257 | 4798.4586 | 1.0960% |
| Laguna | Pangil | 0403421000 | 043421000 | 30758251B63928376402493 | 5017.469272 | 5048.4580 | 0.6176% |
| Laguna | Pila | 0403422000 | 043422000 | 30758251B49530658943400 | 2563.185065 | 2584.0734 | 0.8149% |
| Laguna | Rizal (Laguna) | 0403423000 | 043423000 | 30758251B93953059217751 | 2328.056329 | 2379.5221 | 2.2107% |
| Laguna | San Pablo City (Laguna) | 0403424000 | 043424000 | 30758251B96921420214384 | 18481.310776 | 18709.1794 | 1.2330% |
| Laguna | City of San Pedro | 0403425000 | 043425000 | 30758251B17311985979646 | 2405.918848 | 2407.9158 | 0.0830% |
| Laguna | Santa Cruz (Laguna) | 0403426000 | 043426000 | 30758251B85886628165841 | 3721.317012 | 3737.9537 | 0.4471% |
| Laguna | Santa Maria (Laguna) | 0403427000 | 043427000 | 30758251B60359964634827 | 10895.364684 | 10912.2615 | 0.1551% |
| Laguna | City of Santa Rosa (Laguna) | 0403428000 | 043428000 | 30758251B88250453342652 | 5617.147279 | 5660.6697 | 0.7748% |
| Laguna | Siniloan | 0403429000 | 043429000 | 30758251B87471395485830 | 5453.541434 | 5494.7615 | 0.7558% |
| Laguna | Victoria (Laguna) | 0403430000 | 043430000 | 30758251B60553088936825 | 2926.921222 | 2948.9942 | 0.7541% |
| Lucena City | Lucena City | 0431200000 | 045624000 | 30758251B97555965649214 | 8359.174042 | 8430.7404 | 0.8561% |
| Quezon | Agdangan | 0405601000 | 045601000 | 30758251B21979375590034 | 3558.758147 | 3597.6820 | 1.0937% |
| Quezon | Alabat | 0405602000 | 045602000 | 30758251B17731917702935 | 6041.180999 | 6082.8169 | 0.6892% |
| Quezon | Atimonan | 0405603000 | 045603000 | 30758251B55496023241087 | 22129.925089 | 22269.2746 | 0.6297% |
| Quezon | Buenavista (Quezon) | 0405605000 | 045605000 | 30758251B56825818240160 | 17134.365914 | 17243.8966 | 0.6392% |
| Quezon | Burdeos | 0405606000 | 045606000 | 30758251B95926954005559 | 26502.722335 | 26600.7413 | 0.3698% |
| Quezon | Calauag | 0405607000 | 045607000 | 30758251B23417388477170 | 31252.541817 | 31460.3210 | 0.6648% |
| Quezon | Candelaria (Quezon) | 0405608000 | 045608000 | 30758251B8297430856104 | 13675.403751 | 13775.1409 | 0.7293% |
| Quezon | Catanauan | 0405610000 | 045610000 | 30758251B26905221956095 | 25334.687925 | 25509.3808 | 0.6895% |
| Quezon | Dolores (Quezon) | 0405615000 | 045615000 | 30758251B18917837863618 | 6596.861222 | 6644.7194 | 0.7255% |
| Quezon | General Luna (Quezon) | 0405616000 | 045616000 | 30758251B37823444097828 | 10006.465952 | 10074.8452 | 0.6834% |
| Quezon | General Nakar | 0405617000 | 045617000 | 30758251B80195971051308 | 132199.398321 | 132959.8183 | 0.5752% |
| Quezon | Guinayangan | 0405618000 | 045618000 | 30758251B18842789953948 | 22572.428413 | 22712.2685 | 0.6195% |
| Quezon | Gumaca | 0405619000 | 045619000 | 30758251B73056824701687 | 18527.515660 | 18629.5887 | 0.5509% |
| Quezon | Infanta (Quezon) | 0405620000 | 045620000 | 30758251B63604482475035 | 17226.304956 | 17328.5623 | 0.5936% |
| Quezon | Jomalig | 0405621000 | 045621000 | 30758251B10642262444602 | 5186.239584 | 5182.1236 | 0.0794% |
| Quezon | Lopez | 0405622000 | 045622000 | 30758251B20509029806435 | 37646.461917 | 37946.4563 | 0.7969% |
| Quezon | Lucban | 0405623000 | 045623000 | 30758251B44778470140505 | 13824.728595 | 13907.5951 | 0.5994% |
| Quezon | Macalelon | 0405625000 | 045625000 | 30758251B15464702074966 | 10401.208839 | 10462.7936 | 0.5921% |
| Quezon | Mauban | 0405627000 | 045627000 | 30758251B43842848901261 | 40959.950757 | 41194.8889 | 0.5736% |
| Quezon | Mulanay | 0405628000 | 045628000 | 30758251B87111150812734 | 27621.904146 | 27804.4010 | 0.6607% |
| Quezon | Padre Burgos (Quezon) | 0405629000 | 045629000 | 30758251B51252146080167 | 7570.311787 | 7633.1617 | 0.8302% |
| Quezon | Pagbilao | 0405630000 | 045630000 | 30758251B2518509658018 | 17521.994281 | 17624.3736 | 0.5843% |
| Quezon | Panukulan | 0405631000 | 045631000 | 30758251B73840124509325 | 17965.954232 | 18021.3850 | 0.3085% |
| Quezon | Patnanungan | 0405632000 | 045632000 | 30758251B27789677173568 | 9593.678994 | 9622.4603 | 0.3000% |
| Quezon | Perez | 0405633000 | 045633000 | 30758251B86160385363219 | 5242.866830 | 5273.6384 | 0.5869% |
| Quezon | Pitogo (Quezon) | 0405634000 | 045634000 | 30758251B36558005022993 | 7950.834912 | 8006.1151 | 0.6953% |
| Quezon | Plaridel (Quezon) | 0405635000 | 045635000 | 30758251B71605506872700 | 1844.394501 | 1855.3445 | 0.5937% |
| Quezon | Polillo | 0405636000 | 045636000 | 30758251B72190087571376 | 23503.403750 | 23642.8604 | 0.5933% |
| Quezon | Quezon (Quezon) | 0405637000 | 045637000 | 30758251B89004108151749 | 7570.710163 | 7595.1648 | 0.3230% |
| Quezon | Real | 0405638000 | 045638000 | 30758251B78685665231141 | 35076.734371 | 35273.9506 | 0.5622% |
| Quezon | Sampaloc (Quezon) | 0405639000 | 045639000 | 30758251B60406679280817 | 8135.654538 | 8181.3729 | 0.5620% |
| Quezon | San Andres (Quezon) | 0405640000 | 045640000 | 30758251B69966193362953 | 17553.026804 | 17633.3807 | 0.4578% |
| Quezon | San Antonio (Quezon) | 0405641000 | 045641000 | 30758251B10052680344935 | 6237.925368 | 6276.2678 | 0.6147% |
| Quezon | San Francisco (Quezon) | 0405642000 | 045642000 | 30758251B48560847084049 | 31619.067525 | 31842.8644 | 0.7078% |
| Quezon | San Narciso (Quezon) | 0405644000 | 045644000 | 30758251B81535360880656 | 24268.422359 | 24385.2931 | 0.4816% |
| Quezon | Sariaya | 0405645000 | 045645000 | 30758251B55152096669132 | 21381.717487 | 21547.3377 | 0.7746% |
| Quezon | Tagkawayan | 0405646000 | 045646000 | 30758251B29801061309945 | 55174.242000 | 55484.3923 | 0.5621% |
| Quezon | City of Tayabas | 0405647000 | 045647000 | 30758251B70245066805584 | 22492.233071 | 22630.3706 | 0.6142% |
| Quezon | Tiaong | 0405648000 | 045648000 | 30758251B22989008298037 | 11892.957919 | 11991.1683 | 0.8258% |
| Quezon | Unisan | 0405649000 | 045649000 | 30758251B81181163877224 | 10742.562120 | 10805.1716 | 0.5828% |
| Rizal | Angono | 0405801000 | 045801000 | 30758251B89453678850457 | 1466.344016 | 1464.6484 | 0.1156% |
| Rizal | City of Antipolo | 0405802000 | 045802000 | 30758251B52260851794711 | 26335.128255 | 26509.5272 | 0.6622% |
| Rizal | Baras (Rizal) | 0405803000 | 045803000 | 30758251B49056939189974 | 5402.603583 | 5416.7689 | 0.2622% |
| Rizal | Binangonan | 0405804000 | 045804000 | 30758251B30954744476099 | 5396.920369 | 5438.9679 | 0.7791% |
| Rizal | Cainta | 0405805000 | 045805000 | 30758251B76506512725367 | 2097.374255 | 2106.8199 | 0.4504% |
| Rizal | Cardona | 0405806000 | 045806000 | 30758251B57572048623065 | 2422.904301 | 2427.8908 | 0.2058% |
| Rizal | Jala-Jala | 0405807000 | 045807000 | 30758251B16311826266125 | 4367.893041 | 4414.8661 | 1.0754% |
| Rizal | Rodriguez | 0405808000 | 045808000 | 30758251B28604379202623 | 23503.808774 | 23623.1142 | 0.5076% |
| Rizal | Morong (Rizal) | 0405809000 | 045809000 | 30758251B549341994031 | 3456.599506 | 3480.1924 | 0.6825% |
| Rizal | Pililla | 0405810000 | 045810000 | 30758251B3557386785722 | 6651.083955 | 6692.4007 | 0.6212% |
| Rizal | San Mateo (Rizal) | 0405811000 | 045811000 | 30758251B86089983795598 | 5751.905430 | 5779.0208 | 0.4714% |
| Rizal | Tanay | 0405812000 | 045812000 | 30758251B99469843476303 | 28379.717542 | 28549.2101 | 0.5972% |
| Rizal | Taytay (Rizal) | 0405813000 | 045813000 | 30758251B47474969400955 | 2839.614204 | 2854.5209 | 0.5250% |
| Rizal | Teresa | 0405814000 | 045814000 | 30758251B5946986755811 | 1954.145260 | 1993.0522 | 1.9910% |

## Requested accounts

The owner requested one regional administrator (the existing Regional Head role) and selected three provincial administrators: Cavite, Batangas and Laguna. Inactive Super Admin accounts were prepared with the official office addresses `agriculture.cavite@yahoo.com`, `agri@batangas.gov.ph` and `faesopaglaguna@gmail.com`; each requires an owner-set password before activation. Account provisioning refused existing logins, validated usable province scope, and audited creation without credentials. No Quezon, Rizal or Lucena administrator was issued, and no invitation email was sent.

## Verified production release

Installed runtime commit `496b706`, then entered a brief maintenance window. Private, permission-restricted JSON backups of all affected tables were verified before the transaction. The import activated Batangas 34, Cavite 23, Laguna 30, Quezon 40, Rizal 14 and Lucena City 1. Region IV-A was configured with six province/city scopes. All pre-existing rows in the affected tables were compared and preserved; farmer, parcel, assistance and farmer-portal account snapshots were unchanged. No migration or public asset change was required. Configuration, routes and Blade caches were refreshed, and the site returned online.

The requested Regional Head was created with a unique random credential delivered only in a protected local file. Production verification confirmed usable scope and exactly 142 visible boundaries. HTTP sign-in reached `/dashboard` with 200; the authenticated geofence page returned 200 and exposed the expected CALABARZON choices. The verification session was signed out. Google Maps rendering itself was not visually tested.

The follow-up farmer-card release installed commit `ead573e` through GitHub and Hostinger. Three inactive provincial accounts were created in a maintenance window; a private verification receipt confirmed 21 non-account table fingerprints and the environment file were unchanged. The login page returned HTTP 200 afterward. No credentials were stored in the repository or sent by email.

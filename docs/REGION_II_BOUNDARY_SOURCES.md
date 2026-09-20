# Region II planning/reference geofences

Current status: deployed to Hostinger on September 20, 2026 in release 54cc9e4. Earlier local-only statements below describe implementation-stage checks. See FULL_DEPLOYMENT_2026_09_20.md for the deployment receipt and remaining limits.

## Coverage and supervision

Region II (Cagayan Valley) has 89 municipalities and four cities, for 93 references. Counts follow the [PSA Region II PSGC directory](https://psa.gov.ph/classification/psgc/provinces/0200000000).

| Supervision scope | References |
| --- | ---: |
| Batanes | 6 |
| Cagayan | 29 |
| Isabela | 36 |
| Nueva Vizcaya | 15 |
| Quirino | 6 |
| Santiago City | 1 |
| **Total** | **93** |

The owner explicitly selected a separate Santiago City scope. Santiago is an independent component city, statistically grouped under Isabela by PSA. Its source `geographic_province` remains Isabela; application ownership uses a separate Santiago City province-scope record. Isabela administrators receive 36 municipalities/cities and cannot access Santiago through Isabela. No accounts are created or reassigned. [PSA independent-city classification](https://psa.gov.ph/content/highlights-region-ii-cagayan-valley-population-2024-census-population-2024-popcen).

## Sources and verification

Retrieved September 20, 2026. Geometry remains unchanged from the [geoBoundaries gbOpen PHL ADM3 simplified snapshot, revision 9469f09](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson), boundary year 2020. Upstream attribution: NAMRIA, Philippine Statistics Authority, OCHA Philippines. License: [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/).

Municipality identifiers, geography and reference areas were checked against the government [GeoRiskPH PSA Municipal layer](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0). Province placement uses [pinned ADM2 revision 41af8f1](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/41af8f1/releaseData/gbOpen/PHL/ADM2/geoBoundaries-PHL-ADM2_simplified.geojson). Each of 93 municipality interior points matches exactly one pinned ADM3 feature and its geographic province. Normalized names and both PSGC forms were checked independently of spatial matching. Every source geometry is valid; intersection area divided by the smaller reference area is at least 98.707%. Application spherical-area differences are at most 1.403%, below the unchanged 3% import guard.

Multipart island geometry is preserved, including Batanes and Calayan. Coordinates are not altered to fit reference areas or remove island pieces. These simplified administrative references are approximate, not legal, cadastral, survey-grade or evidence of land ownership. LGU/NAMRIA verification is required before official use.

## Names and identities

Repeated Philippine municipality names use province-qualified workspace labels and codes. Source `shapeName` remains unchanged for identity validation. New display labels follow these verified conventions:

- `Sanchez-Mira` source becomes **Sanchez Mira**, per the [PSA Q1 2026 correction](https://psa.gov.ph/classification/psgc/node/1684083211).
- **Peñablanca** and **Santo Niño (Cagayan)** retain their accented spelling. [PSA Cagayan directory](https://psa.gov.ph/classification/psgc/citimuni/0201500000).
- `Alfonso Castaneda` source becomes **Alfonso Castañeda**, retaining PSGC `0205015000` and code `ALFONSO_CASTANEDA`. [PSA September 2026 release](https://rsso02.psa.gov.ph/content/2024-census-population-highlights-alfonso-castaneda-nueva-vizcaya).
- `City of Cauayan` becomes **Cauayan City (Isabela)**; `City of Santiago` becomes **Santiago City**. Santiago retains PSGC `0203135000` / legacy `023135000`; no new PSGC is invented for its separate application scope.

The shared importer reuses only an unambiguous compatible existing identity. Conflicting or inactive workspaces, wrong ownership, changed active boundaries, duplicate matches or geometric overlaps stop that province and roll back its writes. Existing IDs and operational records are preserved.

## Explicit import

Back up and verify the target database. Apply only these six named seeders; do not run legacy baseline migrations or demo seeders. For a complete region, use an outer database transaction during a brief maintenance window so a later conflict rolls back all six additions.

```powershell
php artisan db:seed --class=BatanesMunicipalityBoundarySeeder
php artisan db:seed --class=CagayanMunicipalityBoundarySeeder
php artisan db:seed --class=IsabelaMunicipalityBoundarySeeder
php artisan db:seed --class=NuevaVizcayaMunicipalityBoundarySeeder
php artisan db:seed --class=QuirinoMunicipalityBoundarySeeder
php artisan db:seed --class=SantiagoCityBoundarySeeder
```

Append `--force` only when explicitly deploying to production. Imports require an active System Owner or a properly assigned Super Admin. These seeders remain excluded from `DatabaseSeeder` and automatic deployment. Re-importing unchanged active references preserves geometry, styles, IDs and audit history, while invalidating active-boundary caches.

Deploy the six seeders and their six GeoJSON files, with the existing shared importer/geometry/province-access dependencies already verified. No schema migration, new application dependency, account provisioning or public asset change is required. Copying files alone does not insert the geofences. Production has not been changed by this addition.

## Pinned snapshot checksums

SHA-256 after normalizing CRLF/CR to LF.

- `batanes_municipality_reference_boundaries.geojson`: `433934117b9343276a6ff54fb05dedeeded7bfb3cbfe81b074ebba95fe108f93`
- `cagayan_municipality_reference_boundaries.geojson`: `b23d4ede17bc2910bbce9eb63056fea110d8123efec7d1e52172b469ea5110bc`
- `isabela_municipality_reference_boundaries.geojson`: `b38c062f0eff2001367a37a2dbf40182f10b479ac01fde90b6bcb2141ec702b3`
- `nueva_vizcaya_municipality_reference_boundaries.geojson`: `3cff819dfa81894bcd824a73ac2318195e338e84282f195b65661a0fd8b3456d`
- `quirino_municipality_reference_boundaries.geojson`: `ce80823232e03cb7e109ad662d079f293046486897934cd427f224327414c5bf`
- `santiago_city_reference_boundary.geojson`: `6cf8223d7737a898f626f012429bbbbfa024fcaa82b51864231834cfaca2276c`

The six snapshots total 304371 bytes. All 93 source feature IDs and PSGC values are unique.

## Identity and area manifest

### Batanes

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Basco | 0200901000 | 020901000 | 30758251B45522051354426 | 3349.361020 | 3365.7711 | 0.490% | 37 |
| Itbayat | 0200902000 | 020902000 | 30758251B11360025127524 | 8931.860713 | 8984.6063 | 0.591% | 147 |
| Ivana | 0200903000 | 020903000 | 30758251B76768461289658 | 1470.485025 | 1464.5445 | 0.404% | 17 |
| Mahatao | 0200904000 | 020904000 | 30758251B69383770374215 | 1156.081879 | 1163.8148 | 0.669% | 27 |
| Sabtang | 0200905000 | 020905000 | 30758251B59177504805395 | 4098.589461 | 4110.6468 | 0.294% | 79 |
| Uyugan | 0200906000 | 020906000 | 30758251B6116437750205 | 1131.104016 | 1146.9680 | 1.403% | 18 |

### Cagayan

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Abulug | 0201501000 | 021501000 | 30758251B36710233170574 | 13266.820361 | 13337.5296 | 0.533% | 51 |
| Alcala (Cagayan) | 0201502000 | 021502000 | 30758251B10617727048702 | 18052.095846 | 18121.0966 | 0.382% | 46 |
| Allacapan | 0201503000 | 021503000 | 30758251B86595368402227 | 23064.760890 | 23205.7583 | 0.611% | 49 |
| Amulung | 0201504000 | 021504000 | 30758251B10377684558144 | 28758.734653 | 28919.1891 | 0.558% | 91 |
| Aparri | 0201505000 | 021505000 | 30758251B87752241622108 | 26127.130316 | 26229.9667 | 0.394% | 129 |
| Baggao | 0201506000 | 021506000 | 30758251B17539135337219 | 92744.434767 | 93238.9163 | 0.533% | 113 |
| Ballesteros | 0201507000 | 021507000 | 30758251B49973431010235 | 12942.991914 | 13037.2937 | 0.729% | 29 |
| Buguey | 0201508000 | 021508000 | 30758251B28234662478026 | 12882.789145 | 13049.2526 | 1.292% | 76 |
| Calayan | 0201509000 | 021509000 | 30758251B50105091710963 | 50271.557906 | 50545.4847 | 0.545% | 337 |
| Camalaniugan | 0201510000 | 021510000 | 30758251B52901607273477 | 8920.384130 | 8965.5606 | 0.506% | 50 |
| Claveria (Cagayan) | 0201511000 | 021511000 | 30758251B2770524616489 | 12894.301299 | 12962.5611 | 0.529% | 56 |
| Enrile | 0201512000 | 021512000 | 30758251B30056946994693 | 15246.568180 | 15323.4282 | 0.504% | 42 |
| Gattaran | 0201513000 | 021513000 | 30758251B93060635103686 | 63895.865387 | 64209.7785 | 0.491% | 73 |
| Gonzaga | 0201514000 | 021514000 | 30758251B98012473462325 | 52745.675869 | 53044.1583 | 0.566% | 95 |
| Iguig | 0201515000 | 021515000 | 30758251B88140585742756 | 7818.551936 | 7841.7609 | 0.297% | 29 |
| Lal-Lo | 0201516000 | 021516000 | 30758251B85734212591616 | 64155.068913 | 64535.2650 | 0.593% | 93 |
| Lasam | 0201517000 | 021517000 | 30758251B73427064919579 | 20696.445226 | 20823.0511 | 0.612% | 39 |
| Pamplona (Cagayan) | 0201518000 | 021518000 | 30758251B95967075879397 | 21308.244328 | 21423.8230 | 0.542% | 47 |
| Peñablanca | 0201519000 | 021519000 | 30758251B24835314311291 | 113885.815589 | 114450.9462 | 0.496% | 99 |
| Piat | 0201520000 | 021520000 | 30758251B70490734398893 | 13202.980011 | 13241.2589 | 0.290% | 58 |
| Rizal (Cagayan) | 0201521000 | 021521000 | 30758251B45096657077891 | 34004.708754 | 34140.2411 | 0.399% | 82 |
| Sanchez Mira | 0201522000 | 021522000 | 30758251B62706464581778 | 13830.919560 | 13866.1734 | 0.255% | 35 |
| Santa Ana (Cagayan) | 0201523000 | 021523000 | 30758251B2806913705882 | 43478.019335 | 43804.6676 | 0.751% | 261 |
| Santa Praxedes | 0201524000 | 021524000 | 30758251B96523663514621 | 8627.006175 | 8693.3990 | 0.770% | 41 |
| Santa Teresita (Cagayan) | 0201525000 | 021525000 | 30758251B86029010724112 | 12757.227811 | 12812.3028 | 0.432% | 53 |
| Santo Niño (Cagayan) | 0201526000 | 021526000 | 30758251B40207314243633 | 36126.160168 | 36381.9906 | 0.708% | 66 |
| Solana | 0201527000 | 021527000 | 30758251B34223591098012 | 27293.175874 | 27458.9068 | 0.607% | 66 |
| Tuao | 0201528000 | 021528000 | 30758251B28856028744619 | 18791.374183 | 18892.9339 | 0.540% | 63 |
| Tuguegarao City | 0201529000 | 021529000 | 30758251B86340482939237 | 11959.776818 | 12052.9234 | 0.779% | 42 |

### Isabela

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Alicia (Isabela) | 0203101000 | 023101000 | 30758251B60271002272881 | 15842.486055 | 15926.9107 | 0.533% | 47 |
| Angadanan | 0203102000 | 023102000 | 30758251B27132260441991 | 19459.791491 | 19581.7138 | 0.627% | 77 |
| Aurora (Isabela) | 0203103000 | 023103000 | 30758251B51443667838341 | 7477.159463 | 7562.9549 | 1.147% | 46 |
| Benito Soliven | 0203104000 | 023104000 | 30758251B26223191466543 | 17675.973458 | 17714.3240 | 0.217% | 69 |
| Burgos (Isabela) | 0203105000 | 023105000 | 30758251B93529403516197 | 7727.786584 | 7752.3748 | 0.318% | 51 |
| Cabagan | 0203106000 | 023106000 | 30758251B50421545545985 | 30773.203332 | 31053.5081 | 0.911% | 111 |
| Cabatuan (Isabela) | 0203107000 | 023107000 | 30758251B5044075759859 | 6392.629045 | 6426.6796 | 0.533% | 36 |
| Cauayan City (Isabela) | 0203108000 | 023108000 | 30758251B60223499645568 | 33668.309536 | 33840.7985 | 0.512% | 131 |
| Cordon | 0203109000 | 023109000 | 30758251B63397111137342 | 22452.909789 | 22597.6719 | 0.645% | 83 |
| Dinapigue | 0203110000 | 023110000 | 30758251B31743185560611 | 95307.522082 | 95820.3028 | 0.538% | 149 |
| Divilacan | 0203111000 | 023111000 | 30758251B81826872650546 | 52855.143456 | 53220.9416 | 0.692% | 190 |
| Echague | 0203112000 | 023112000 | 30758251B53772032763488 | 44385.626362 | 44630.8008 | 0.552% | 125 |
| Gamu | 0203113000 | 023113000 | 30758251B3499179815070 | 9663.866894 | 9724.6602 | 0.629% | 74 |
| Ilagan City | 0203114000 | 023114000 | 30758251B40091866614257 | 96243.289974 | 96802.3073 | 0.581% | 144 |
| Jones | 0203115000 | 023115000 | 30758251B4586276697259 | 46516.824673 | 46823.3122 | 0.659% | 89 |
| Luna (Isabela) | 0203116000 | 023116000 | 30758251B75291685490703 | 4560.320019 | 4575.8590 | 0.341% | 31 |
| Maconacon | 0203117000 | 023117000 | 30758251B92607820299430 | 46422.268848 | 46720.0387 | 0.641% | 80 |
| Delfin Albano | 0203118000 | 023118000 | 30758251B20818666994539 | 17789.178482 | 17885.8610 | 0.543% | 56 |
| Mallig | 0203119000 | 023119000 | 30758251B62291195909479 | 11621.899660 | 11675.2043 | 0.459% | 77 |
| Naguilian (Isabela) | 0203120000 | 023120000 | 30758251B29614901400511 | 16326.110252 | 16458.9139 | 0.813% | 86 |
| Palanan | 0203121000 | 023121000 | 30758251B42816846115964 | 37438.517428 | 37667.1588 | 0.611% | 153 |
| Quezon (Isabela) | 0203122000 | 023122000 | 30758251B61919458942758 | 21650.183570 | 21783.4244 | 0.615% | 48 |
| Quirino (Isabela) | 0203123000 | 023123000 | 30758251B90473540234186 | 12553.490523 | 12628.9029 | 0.601% | 100 |
| Ramon | 0203124000 | 023124000 | 30758251B59808098475352 | 12632.482277 | 12697.2564 | 0.513% | 49 |
| Reina Mercedes | 0203125000 | 023125000 | 30758251B79932991691017 | 5161.994899 | 5203.5273 | 0.805% | 42 |
| Roxas (Isabela) | 0203126000 | 023126000 | 30758251B43997150317842 | 11895.113137 | 11963.6804 | 0.576% | 103 |
| San Agustin (Isabela) | 0203127000 | 023127000 | 30758251B75696282088707 | 18833.896721 | 19003.9983 | 0.903% | 51 |
| San Guillermo | 0203128000 | 023128000 | 30758251B48222146671596 | 38659.814410 | 38815.5620 | 0.403% | 99 |
| San Isidro (Isabela) | 0203129000 | 023129000 | 30758251B62946220983570 | 6440.483571 | 6495.1939 | 0.849% | 36 |
| San Manuel (Isabela) | 0203130000 | 023130000 | 30758251B36577910172008 | 10402.471537 | 10422.2037 | 0.190% | 46 |
| San Mariano | 0203131000 | 023131000 | 30758251B27665954981210 | 139720.048508 | 140585.8554 | 0.620% | 166 |
| San Mateo (Isabela) | 0203132000 | 023132000 | 30758251B54536081891976 | 11039.871927 | 11086.2143 | 0.420% | 52 |
| San Pablo (Isabela) | 0203133000 | 023133000 | 30758251B64058966445953 | 45038.816061 | 45271.9417 | 0.518% | 44 |
| Santa Maria (Isabela) | 0203134000 | 023134000 | 30758251B83702648087854 | 11896.076554 | 11978.0986 | 0.689% | 54 |
| Santo Tomas (Isabela) | 0203136000 | 023136000 | 30758251B99361767942703 | 7729.593879 | 7739.1582 | 0.124% | 39 |
| Tumauini | 0203137000 | 023137000 | 30758251B25937724211133 | 40055.949958 | 40209.8138 | 0.384% | 58 |

### Nueva Vizcaya

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Ambaguio | 0205001000 | 025001000 | 30758251B68049243059466 | 17747.095974 | 17847.9240 | 0.568% | 25 |
| Aritao | 0205002000 | 025002000 | 30758251B32564665181621 | 29198.983793 | 29337.2960 | 0.474% | 54 |
| Bagabag | 0205003000 | 025003000 | 30758251B74159720492186 | 14683.275634 | 14750.7339 | 0.459% | 90 |
| Bambang | 0205004000 | 025004000 | 30758251B54478604625017 | 22980.290947 | 23087.4467 | 0.466% | 30 |
| Bayombong | 0205005000 | 025005000 | 30758251B92407416311106 | 14252.603450 | 14346.5336 | 0.659% | 25 |
| Diadi | 0205006000 | 025006000 | 30758251B56267666888598 | 21066.626579 | 21178.8345 | 0.533% | 67 |
| Dupax del Norte | 0205007000 | 025007000 | 30758251B61086740814455 | 29807.728124 | 29966.0103 | 0.531% | 96 |
| Dupax del Sur | 0205008000 | 025008000 | 30758251B45953975988302 | 41228.705611 | 41489.6080 | 0.633% | 64 |
| Kasibu | 0205009000 | 025009000 | 30758251B8018524244624 | 61642.137955 | 62039.1792 | 0.644% | 54 |
| Kayapa | 0205010000 | 025010000 | 30758251B81196974084886 | 51751.397137 | 52074.3042 | 0.624% | 45 |
| Quezon (Nueva Vizcaya) | 0205011000 | 025011000 | 30758251B12322451453842 | 21224.899230 | 21344.3563 | 0.563% | 40 |
| Santa Fe (Nueva Vizcaya) | 0205012000 | 025012000 | 30758251B84438327849244 | 24712.850491 | 24885.8725 | 0.700% | 41 |
| Solano | 0205013000 | 025013000 | 30758251B33940729335702 | 8850.671155 | 8910.4643 | 0.676% | 58 |
| Villaverde | 0205014000 | 025014000 | 30758251B74337145174086 | 6781.588315 | 6829.9824 | 0.714% | 60 |
| Alfonso Castañeda | 0205015000 | 025015000 | 30758251B24689590094484 | 46663.297043 | 46872.8209 | 0.449% | 26 |

### Quirino

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Aglipay | 0205701000 | 025701000 | 30758251B83245264708562 | 28030.642346 | 28163.6040 | 0.474% | 124 |
| Cabarroguis | 0205702000 | 025702000 | 30758251B72889341046868 | 18908.284610 | 18999.2716 | 0.481% | 63 |
| Diffun | 0205703000 | 025703000 | 30758251B78593014826051 | 28786.103700 | 28955.3086 | 0.588% | 62 |
| Maddela | 0205704000 | 025704000 | 30758251B21617081206800 | 77062.251679 | 77429.0181 | 0.476% | 75 |
| Saguday | 0205705000 | 025705000 | 30758251B76917628973328 | 5016.993116 | 5034.9296 | 0.358% | 31 |
| Nagtipunan | 0205706000 | 025706000 | 30758251B72907275700967 | 118996.702835 | 119657.0105 | 0.555% | 122 |

### Santiago City

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference | Vertices |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: |
| Santiago City | 0203135000 | 023135000 | 30758251B59985488099372 | 15181.653930 | 15260.9574 | 0.522% | 82 |

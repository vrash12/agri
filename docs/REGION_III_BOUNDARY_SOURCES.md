# Region III planning/reference geofences

## Coverage and supervision

Region III has 130 cities and municipalities: [PSA lists 15 cities and 115 municipalities](https://psa.gov.ph/classification/psgc/provinces/0300000000). The current list includes the City of Baliwag. Coverage in this system is:

| Scope | References |
| --- | ---: |
| Aurora | 8 |
| Bataan | 12 |
| Bulacan | 24 |
| Nueva Ecija | 32 |
| Pampanga | 21 |
| Tarlac | 18 |
| Zambales | 13 |
| Angeles City (separate city scope) | 1 |
| Olongapo City (separate city scope) | 1 |

The owner explicitly selected separate Angeles City and Olongapo City supervision, matching the existing Baguio arrangement. Each city has its own active `provinces` scope record and municipality workspace. Pampanga and Zambales provincial administrators cannot query their records; a city administrator only sees their city. No accounts are created or reassigned. Existing province evaluation workspaces and account assignments are preserved. Repeated Philippine municipality names use province-qualified labels and codes.

The seven new named seeders add 88 references beyond the existing 42 Tarlac/Bulacan municipality references. Complete Region III deployment verifies all 130, imports Bulacan through its existing named seeder when needed, and leaves Tarlac references unchanged. The Bulacan seeder archives only the exact existing coarse Bulacan planning outline when it replaces it with the 24 municipality references; its geometry and history remain stored. No other active boundary is replaced.

## Sources and validation

Retrieved 2026-09-19. Geometry remains unchanged from [geoBoundaries gbOpen PHL ADM3 revision 9469f09](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson), boundary year 2020. Upstream sources: NAMRIA, PSA, OCHA Philippines. License: [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/).

Identity and area references use the government [GeoRiskPH PSA Municipal layer](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0); province placement uses pinned geoBoundaries ADM2 revision `41af8f1`. Each of the 88 features matched exactly by source name and province placement. Both PSGC forms, geographic extents, topology, area and unique source IDs were checked. All area differences are below 3%; the maximum is 1.934%. The source geographic province is retained separately for Angeles/Olongapo while their import ownership uses the selected independent-city scope.

Mabalacat and Macabebe are concave; their area centroids can fall outside their own outline. Centroid-only geographic matching is therefore insufficient. The application overlap check now validates a point inside its own polygon before testing containment in another polygon. If the centroid lies outside or inside a hole, a scanline finds a strict interior point. This prevents a false Macabebe/Masantol conflict without changing coordinates or overlap tolerance; shared edges, real overlaps, containment, holes and multipart geometry have regression coverage.

The older Bulacan snapshot/workspace keeps its historical `Baliuag` spelling to preserve existing IDs and references. PSA currently classifies it as the City of Baliwag, so Bulacan has 20 municipalities and 4 cities; no legacy workspace rename is performed.

These approximate references require LGU/NAMRIA verification for official use. They are not legal, cadastral, or survey-grade boundaries. Administrative area does not establish land ownership or registered farm area.

## Explicit import

Back up and verify the target database. Do not run legacy baseline migrations or demo seeders. During a brief maintenance window, wrap the requested imports in an outer transaction so a late conflict rolls back the whole addition. First apply Bulacan municipality references if still represented by the coarse outline, then the seven additions:

```powershell
php artisan db:seed --class=BulacanMunicipalityBoundarySeeder
php artisan db:seed --class=AuroraMunicipalityBoundarySeeder
php artisan db:seed --class=BataanMunicipalityBoundarySeeder
php artisan db:seed --class=NuevaEcijaMunicipalityBoundarySeeder
php artisan db:seed --class=PampangaMunicipalityBoundarySeeder
php artisan db:seed --class=ZambalesMunicipalityBoundarySeeder
php artisan db:seed --class=AngelesCityBoundarySeeder
php artisan db:seed --class=OlongapoCityBoundarySeeder
```

Append `--force` in production. These named seeders are excluded from `DatabaseSeeder` and automatic deployments. Each source is checksum-pinned, validated before writes, imported under the shared activation lock, and idempotent. Wrong province, inactive workspace, ambiguous identity, real overlap, or a changed reference stops that scope. New references have attributed import audits. No users, farmers, parcels or releases are created.

## Source checksums

SHA-256 after CRLF/CR to LF normalization:

- `aurora_municipality_reference_boundaries.geojson`: `6cfa78e556a476925069ecdfd52e9d8c2880413558b16d566d0bfa1c75a4db29`
- `bataan_municipality_reference_boundaries.geojson`: `ddc9f2a607d1d94279710dd094cd56924a0b94bdc18e0397ceb3499df3d54bc9`
- `nueva_ecija_municipality_reference_boundaries.geojson`: `255ae3b757314cc19f51a71774327e36124c28f154aee7e26d5668395de033c9`
- `pampanga_municipality_reference_boundaries.geojson`: `851447be0e284899452b24b2bf2fc34ed87341b62e2453985043bb9dbeebf8d6`
- `zambales_municipality_reference_boundaries.geojson`: `ff12199e45268edf036c07811f113e30403f925ce2de557f1bcbae37becac3f4`
- `angeles_city_reference_boundary.geojson`: `952a73d010dc386110ed9553f529c190aa92c367afd638391e4180598885ba81`
- `olongapo_city_reference_boundary.geojson`: `0b476bc12d800493d87c7a035726a4868ef5fdfc44370353f1bc10bd9d5ab2de`

## Identities and area checks

### Aurora

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Baler | `0307701000` | `037701000` | `30758251B90201741515096` | 9959.587525 | 10028.3655 | 0.691% |
| Casiguran (Aurora) | `0307702000` | `037702000` | `30758251B87187135187970` | 42011.503969 | 42373.0127 | 0.860% |
| Dilasag | `0307703000` | `037703000` | `30758251B50259762505671` | 49548.013420 | 49859.1653 | 0.628% |
| Dinalungan | `0307704000` | `037704000` | `30758251B88004943266135` | 32633.961071 | 32828.3602 | 0.596% |
| Dingalan | `0307705000` | `037705000` | `30758251B91173779963117` | 35436.770599 | 35685.7037 | 0.702% |
| Dipaculao | `0307706000` | `037706000` | `30758251B11219690177973` | 40065.934114 | 40356.4236 | 0.725% |
| Maria Aurora | `0307707000` | `037707000` | `30758251B2954727825602` | 33553.577667 | 33727.4636 | 0.518% |
| San Luis (Aurora) | `0307708000` | `037708000` | `30758251B29730451964766` | 59834.608554 | 60230.4635 | 0.662% |

### Bataan

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Abucay | `0300801000` | `030801000` | `30758251B46343582936162` | 7688.902406 | 7747.1051 | 0.757% |
| Bagac | `0300802000` | `030802000` | `30758251B72127248939453` | 18903.549767 | 19029.4168 | 0.666% |
| Balanga City | `0300803000` | `030803000` | `30758251B46674947380799` | 8958.513846 | 9023.7464 | 0.728% |
| Dinalupihan | `0300804000` | `030804000` | `30758251B87193060903149` | 7550.977854 | 7573.5746 | 0.299% |
| Hermosa | `0300805000` | `030805000` | `30758251B15732618156338` | 14895.286941 | 14998.5215 | 0.693% |
| Limay | `0300806000` | `030806000` | `30758251B34995051512109` | 6070.007843 | 6110.7011 | 0.670% |
| Mariveles | `0300807000` | `030807000` | `30758251B81885646660603` | 19431.622493 | 19572.8940 | 0.727% |
| Morong (Bataan) | `0300808000` | `030808000` | `30758251B50173683620188` | 17359.919963 | 17467.1247 | 0.618% |
| Orani | `0300809000` | `030809000` | `30758251B4793511613736` | 5365.953609 | 5409.1244 | 0.805% |
| Orion | `0300810000` | `030810000` | `30758251B25905682576781` | 8963.981219 | 9026.0965 | 0.693% |
| Pilar (Bataan) | `0300811000` | `030811000` | `30758251B7851333843517` | 4555.326179 | 4556.0481 | 0.016% |
| Samal (Bataan) | `0300812000` | `030812000` | `30758251B26373043106772` | 4751.322861 | 4769.2543 | 0.377% |

### Nueva Ecija

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Aliaga | `0304901000` | `034901000` | `30758251B61766389161582` | 9525.709102 | 9558.0797 | 0.340% |
| Bongabon | `0304902000` | `034902000` | `30758251B28322914429669` | 21556.084649 | 21644.5117 | 0.410% |
| Cabanatuan City | `0304903000` | `034903000` | `30758251B91013858320846` | 18923.081950 | 19046.6106 | 0.653% |
| Cabiao | `0304904000` | `034904000` | `30758251B91835064861119` | 11227.411242 | 11308.8736 | 0.726% |
| Carranglan | `0304905000` | `034905000` | `30758251B38781000635983` | 73448.602235 | 73839.8832 | 0.533% |
| Gapan City | `0304908000` | `034908000` | `30758251B81121957716854` | 17215.854006 | 17288.1019 | 0.420% |
| Cuyapo | `0304906000` | `034906000` | `30758251B13758453625631` | 17835.494199 | 17959.0689 | 0.693% |
| Gabaldon | `0304907000` | `034907000` | `30758251B70332273238946` | 36750.488861 | 36975.4332 | 0.612% |
| General Mamerto Natividad | `0304909000` | `034909000` | `30758251B30324290994984` | 10839.831869 | 10918.7130 | 0.728% |
| General Tinio | `0304910000` | `034910000` | `30758251B90197047513791` | 56315.599515 | 56616.9804 | 0.535% |
| Guimba | `0304911000` | `034911000` | `30758251B97926428018221` | 21535.314545 | 21641.4872 | 0.493% |
| Jaen | `0304912000` | `034912000` | `30758251B14595359840210` | 9809.617823 | 9870.4217 | 0.620% |
| Laur | `0304913000` | `034913000` | `30758251B54864589401761` | 15625.265694 | 15680.8441 | 0.356% |
| Licab | `0304914000` | `034914000` | `30758251B80146072363536` | 6445.737433 | 6474.4400 | 0.445% |
| Llanera | `0304915000` | `034915000` | `30758251B85040378394096` | 10010.891363 | 10076.8878 | 0.659% |
| Lupao | `0304916000` | `034916000` | `30758251B11609391630231` | 11675.187156 | 11760.5976 | 0.732% |
| Nampicuan | `0304918000` | `034918000` | `30758251B757279670105` | 4653.721487 | 4669.1234 | 0.331% |
| Palayan City | `0304919000` | `034919000` | `30758251B19643083893991` | 13122.593489 | 13200.2525 | 0.592% |
| Pantabangan | `0304920000` | `034920000` | `30758251B73416019883102` | 41972.824773 | 42211.6837 | 0.569% |
| PeÃ±aranda | `0304921000` | `034921000` | `30758251B17193566856457` | 6308.240339 | 6379.9386 | 1.137% |
| Quezon (Nueva Ecija) | `0304922000` | `034922000` | `30758251B11811297949808` | 6410.673116 | 6453.5368 | 0.669% |
| Rizal (Nueva Ecija) | `0304923000` | `034923000` | `30758251B98228073754255` | 13446.284431 | 13538.5625 | 0.686% |
| San Antonio (Nueva Ecija) | `0304924000` | `034924000` | `30758251B18161009725227` | 18126.723853 | 18204.2726 | 0.428% |
| San Isidro (Nueva Ecija) | `0304925000` | `034925000` | `30758251B57487055116428` | 4778.832276 | 4808.6012 | 0.623% |
| San Jose City (Nueva Ecija) | `0304926000` | `034926000` | `30758251B74441854533051` | 19194.711166 | 19326.7450 | 0.688% |
| San Leonardo | `0304927000` | `034927000` | `30758251B34551572627583` | 5077.563037 | 5094.2367 | 0.328% |
| Santa Rosa (Nueva Ecija) | `0304928000` | `034928000` | `30758251B64711769669344` | 10712.473146 | 10788.3728 | 0.709% |
| Santo Domingo (Nueva Ecija) | `0304929000` | `034929000` | `30758251B71644316886233` | 8618.388550 | 8673.7121 | 0.642% |
| Science City of MuÃ±oz | `0304917000` | `034917000` | `30758251B8950221539698` | 12944.463442 | 12992.4405 | 0.371% |
| Talavera | `0304930000` | `034930000` | `30758251B41581765009138` | 13176.573988 | 13243.1048 | 0.505% |
| Talugtug | `0304931000` | `034931000` | `30758251B85167098173999` | 10689.170144 | 10726.9536 | 0.353% |
| Zaragoza | `0304932000` | `034932000` | `30758251B15421903274742` | 7746.157264 | 7809.7856 | 0.821% |

### Pampanga

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Apalit | `0305402000` | `035402000` | `30758251B27786389641785` | 5759.177388 | 5815.9834 | 0.986% |
| Arayat | `0305403000` | `035403000` | `30758251B65285968216492` | 18712.540932 | 18823.8280 | 0.595% |
| Bacolor | `0305404000` | `035404000` | `30758251B55823807082743` | 8356.743015 | 8396.4298 | 0.475% |
| Candaba | `0305405000` | `035405000` | `30758251B67025420220537` | 20801.135354 | 20956.6552 | 0.748% |
| San Fernando City (Pampanga) | `0305416000` | `035416000` | `30758251B76914785362033` | 7010.845165 | 7042.5994 | 0.453% |
| Floridablanca | `0305406000` | `035406000` | `30758251B62487244382534` | 13486.841322 | 13604.7669 | 0.874% |
| Guagua | `0305407000` | `035407000` | `30758251B51430418029688` | 4484.060617 | 4469.5760 | 0.323% |
| Lubao | `0305408000` | `035408000` | `30758251B85700326948768` | 16501.810104 | 16581.9786 | 0.486% |
| Mabalacat City | `0305409000` | `035409000` | `30758251B7981212511946` | 9754.802749 | 9810.1996 | 0.568% |
| Macabebe | `0305410000` | `035410000` | `30758251B25010155253639` | 9024.105971 | 9049.9310 | 0.286% |
| Magalang | `0305411000` | `035411000` | `30758251B60440366228098` | 9691.597844 | 9760.4751 | 0.711% |
| Masantol | `0305412000` | `035412000` | `30758251B7856900683747` | 6760.267527 | 6803.5258 | 0.640% |
| Mexico | `0305413000` | `035413000` | `30758251B60921017943813` | 12436.961401 | 12486.7249 | 0.400% |
| Minalin | `0305414000` | `035414000` | `30758251B70394947759752` | 5201.910388 | 5242.0896 | 0.772% |
| Porac | `0305415000` | `035415000` | `30758251B84258699520401` | 29712.993489 | 29905.9835 | 0.650% |
| San Luis (Pampanga) | `0305417000` | `035417000` | `30758251B39089965274487` | 4806.274523 | 4834.4451 | 0.586% |
| San Simon | `0305418000` | `035418000` | `30758251B91508590625378` | 5514.942143 | 5558.4110 | 0.788% |
| Santa Ana (Pampanga) | `0305419000` | `035419000` | `30758251B20191703245220` | 3744.934459 | 3739.9247 | 0.134% |
| Santa Rita (Pampanga) | `0305420000` | `035420000` | `30758251B30766672962304` | 2164.319271 | 2187.2945 | 1.062% |
| Santo Tomas (Pampanga) | `0305421000` | `035421000` | `30758251B57632773357139` | 1386.084486 | 1389.0039 | 0.211% |
| Sasmuan | `0305422000` | `035422000` | `30758251B19069500410191` | 4008.579129 | 4046.1874 | 0.938% |

### Zambales

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Botolan | `0307101000` | `037101000` | `30758251B29617351777930` | 66813.900328 | 67204.2026 | 0.584% |
| Cabangan | `0307102000` | `037102000` | `30758251B86660970091013` | 20585.852397 | 20691.9445 | 0.515% |
| Candelaria (Zambales) | `0307103000` | `037103000` | `30758251B19895305686626` | 42228.768286 | 42673.7601 | 1.054% |
| Castillejos | `0307104000` | `037104000` | `30758251B55463127923623` | 10408.262988 | 10489.8702 | 0.784% |
| Iba | `0307105000` | `037105000` | `30758251B62062623216979` | 14034.837920 | 14179.3002 | 1.029% |
| Masinloc | `0307106000` | `037106000` | `30758251B39623995446640` | 24840.293816 | 24994.3946 | 0.620% |
| Palauig | `0307108000` | `037108000` | `30758251B57141645891791` | 30869.180307 | 30971.8153 | 0.332% |
| San Antonio (Zambales) | `0307109000` | `037109000` | `30758251B44301326667503` | 16726.133526 | 16922.4835 | 1.174% |
| San Felipe | `0307110000` | `037110000` | `30758251B59257355730786` | 11436.705987 | 11504.3620 | 0.592% |
| San Marcelino | `0307111000` | `037111000` | `30758251B2790468349785` | 41994.779919 | 42806.8000 | 1.934% |
| San Narciso (Zambales) | `0307112000` | `037112000` | `30758251B59328560779443` | 7286.900751 | 7373.8047 | 1.193% |
| Santa Cruz (Zambales) | `0307113000` | `037113000` | `30758251B81450613900201` | 45955.011009 | 46219.0287 | 0.575% |
| Subic | `0307114000` | `037114000` | `30758251B42276045150270` | 24750.006700 | 24898.6130 | 0.600% |

### Angeles City

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Angeles City | `0330100000` | `035401000` | `30758251B17687282333707` | 12102.898748 | 12190.1135 | 0.721% |

### Olongapo City

| Workspace | PSGC | Legacy PSGC | Shape ID | Reference ha | Computed ha | Difference |
| --- | --- | --- | --- | ---: | ---: | ---: |
| Olongapo City | `0331400000` | `037107000` | `30758251B1579745133837` | 14109.045321 | 14183.0197 | 0.524% |

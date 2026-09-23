# MIMAROPA planning/reference geofences

Status: implementation and local verification complete on September 23, 2026. Production activation is recorded separately after verification.

## Coverage and ownership

The explicit `MimaropaBoundarySeeder` imports 73 municipality/city planning references, matching the [PSA directory](https://psa.gov.ph/classification/psgc/provinces/1700000000): 71 municipalities, Calapan component city and Puerto Princesa highly urbanized city.

| Supervising scope | Workspaces |
| --- | ---: |
| Marinduque | 6 |
| Occidental Mindoro | 11 |
| Oriental Mindoro, including Calapan City | 15 |
| Palawan, excluding Puerto Princesa | 23 |
| Puerto Princesa City, separate scope | 1 |
| Romblon | 17 |

Calapan remains supervised by Oriental Mindoro. Puerto Princesa has its own province-level scope and is excluded from Palawan provincial accounts. No existing workspace is moved between scopes. Nationally repeated names are province-qualified; the source's `Rizal` in Palawan displays as `Dr. Jose P. Rizal (Palawan)`. Current and legacy PSGC codes and original shape IDs remain explicit.

The import creates only missing active scopes, municipalities, attributed boundaries and audit events. It creates no accounts or operational samples. All six imports share one outer transaction. Existing conflicting ownership, inactive workspaces, changed boundaries, overlaps, checksum mismatches or failed validation abort the import. Repeating it preserves IDs, geometry, appearance and prior audit events. It stays outside `DatabaseSeeder` and automatic deployment seeding.

## Sources and verification

Retrieved September 23, 2026:

- [geoBoundaries gbOpen PHL ADM3 revision 9469f09, simplified](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3_simplified.geojson), boundary year 2020, with NAMRIA, PSA and OCHA Philippines attribution under [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/). Seventy-one imported geometries retain these coordinates unchanged.
- [Full ADM3 geometry from the same revision](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PHL/ADM3/geoBoundaries-PHL-ADM3.geojson) supplies Kalayaan and Cagayancillo. Their source shape IDs do not change.
- [Pinned ADM2 revision 41af8f1](https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/41af8f1/releaseData/gbOpen/PHL/ADM2/geoBoundaries-PHL-ADM2_simplified.geojson) independently checks geographic province placement.
- [GeoRiskPH PSA Municipal layer](https://ulap-nga.georisk.gov.ph/arcgis/rest/services/PSA/Municipal/MapServer/0) supplies independent municipality identities, geometry and `munarea_sqkm` for Marinduque, Occidental Mindoro, Oriental Mindoro and Romblon (49 references). The Palawan download failed and subsequent queries requested authentication; no credential or bypass was used.
- [OCHA COD-AB Philippines v03](https://data.humdata.org/dataset/cod-ab-phl), from NAMRIA/PSA, supplies the other 24 independent geometry and `area_sqkm` comparisons. Resource `0120c30e-ba8b-487d-83f5-a664eddd3a8e`, `phl_admin_boundaries.geojson.zip`, contains `phl_admin3.geojson`; features carry `valid_on: 2025-02-13` and `version: v03`. HDX's broader dataset notes have different review dates, so the exact resource and checksums below identify what was checked.
- [PSA municipality codes](https://psa.gov.ph/classification/psgc/citimuni/1705300000) and [city codes](https://psa.gov.ph/classification/psgc/cities/1700000000) establish current PSGC identity. Puerto Princesa uses `1731500000`, legacy `175316000`; Calapan uses `1705205000`, legacy `175205000`. HDX's Palawan p-code `PH1705316` is geographic attribution, not Puerto Princesa's current administrative supervision.

Each selected source is valid according to GEOS/Shapely 2.1.2. Independent reference interior points fall inside the matching source shape, names/codes match, and all 73 source IDs and PSGC identifiers are unique. Minimum intersection divided by the smaller polygon area is 97.1304%. Government datasets may share upstream mapping, so this is a separate dataset check, not independent surveying.

## Island detail and area conventions

The runtime 3% area tolerance, vertex limits and conflict checks are unchanged. The largest area difference across the final 73 references is 1.5739%. References compare polygon areas, not certified land totals.

- **Kalayaan:** the standard simplified feature measured 35.6856 ha, over 10% below COD-AB's 39.98144 ha. The full pinned feature is used unchanged (60 vertices). Its mapped land outline does not represent all municipal waters, claimed territory, or a maritime jurisdiction boundary.
- **Cagayancillo:** the standard simplified feature retained only six parts. The full pinned feature has 36 parts and 24,478 vertices. Offline Shapely 2.1.2 `simplify(0.00005, preserve_topology=True)` reduces it to 1,834 vertices, retains all 36 parts and valid topology, and changes planar area by only 0.0930%. It overlaps the COD-AB reference by 99.6132%. This processed geometry is explicitly marked in its properties; it is not described as unchanged source coordinates.
- **Gloria:** both source polygon parts are retained. They touch at one point. `GeoGeometry` now accepts isolated point contacts between MultiPolygon parts while rejecting shared line segments and intersecting interiors, including contacts along hole rings. This follows [Simple Features validity rules documented by PostGIS](https://www.postgis.net/docs/manual-dev/en/using_postgis_dbmanagement.html). No boundary was moved to make validation pass.

[PSA SR 2026-060 Table A](https://rssomimaropa.psa.gov.ph/system/files/attachment-dir/SR%202026-060%20Statistical%20Table.pdf) reports LMB land-area totals, including contested/gap/overlap conventions, that differ materially from the polygon areas for several Palawan municipalities. The application does not substitute these polygon areas for the published statistical totals. Both are recorded below for Palawan/Puerto Princesa. Small offshore components and coastlines remain approximate even where aggregate area agrees.

These are planning references, not legal, cadastral or survey-grade boundaries. Obtain LGU/NAMRIA verification before official boundary decisions. Inclusion in this application does not settle jurisdiction or title.

## Explicit installation

No migration, additional runtime library, API key, or frontend asset is required. Existing boundary, province, region and audit schemas must already be installed. All processing libraries above were used offline; runtime uses the existing importer and map delivery path.

After a verified private database backup, deploy the reviewed code using GitHub push followed by Hostinger `git pull --ff-only origin main`. During maintenance, apply only the explicit regional import and membership configuration:

```sh
php artisan db:seed --class=MimaropaBoundarySeeder --force
php artisan region-access:configure --owner=<active-owner-id> --region=mimaropa
```

For an all-or-nothing release, wrap the seeder and `RegionSupervision::configure($owner, 'mimaropa')` in one database transaction. The original no-option configuration command still selects only its original four regions. The MIMAROPA option links five provinces and the separate Puerto Princesa city scope; it does not create a Regional Head.

Verify the six counts above, region membership, 23 Palawan choices excluding Puerto Princesa, 15 Oriental Mindoro choices including Calapan, 73 regional choices, and all pre-existing database rows. Refresh route/config/view caches and leave maintenance after checks. Never run legacy migrations or `DatabaseSeeder` for this addition.

## Pinned input receipts

Local checks passed: 76 focused PHP tests / 2,204 assertions across the MIMAROPA seeder, geometry, municipality-geofence and regional-access suites; Pint on all 13 changed PHP files; PHP syntax, Blade compilation, route listing and whitespace checks. The 73 imported geometries are valid, contain 616 polygon parts and 17,220 vertices in total, and have at most 1,834 vertices each. Six snapshots total 728,177 bytes. Import tests cover attributed coverage, Calapan/Puerto Princesa isolation, repeat-import style/ID preservation, checksum and ownership rejection, unchanged existing boundaries, authorized actors and full-region rollback. Production Google Maps interaction has not been benchmarked for this addition.

| Input | SHA-256 |
| --- | --- |
| `adm3.geojson` | `2ece3d44a5c6a2afb385ffbf3a6b88d83e4d3a3e7eed9a52cb3be1bc59e289fc` |
| `adm2.geojson` | `fa77b9f17db2e419acaae714a935f7812be4409e2983675d34020e8426a3e189` |
| `hdx.geojson.zip` | `032db5d91fcb8a13aef378d6b4453da7225ef7f0fb9c0a1c3bf3c2205d0010d6` |
| `marinduque-georisk.geojson` | `a0934212db08f7fe383d6fd871f476b58afb9d026bd73fe8ba55cd0e1528b405` |
| `occidental_mindoro-georisk.geojson` | `ce26679f4f074cac719215c3b8d09c8d04899d56ccaa34fd14a49e95b765c203` |
| `oriental_mindoro-georisk.geojson` | `6a3f322da0013059965c7c92b7149ca0086d3d108181c67a7be8571ff331e20c` |
| `romblon-georisk.geojson` | `6ca62b3badc5622f948ff0330f13b219c3a78881066d684c5f2c11588129be88` |
| `palawan-hdx.geojson` | `e4b95c7e676c3d8ae18142c0cd6cd18dfac3883d0791c03141a8e722654d3d8e` |
| `full-island-features.geojson` | `6711e19681e5ad20396361761e2c104f8efa6f70c58ed847760b5957266010dc` |

The last two inputs are local extracts from the identified upstream resources. Full reference geometries and the 1 GB HDX archive are kept outside the repository; only the compact imported snapshots are shipped.

## Imported snapshots

Checksums normalize CRLF to LF.

| Scope / file | Count | Bytes | SHA-256 |
| --- | ---: | ---: | --- |
| `marinduque_municipality_reference_boundaries.geojson` | 6 | 29368 | `32896a9f2b2255121bfe4739c756816df8f05785d98ea4e6c9712cc3285fca06` |
| `occidental_mindoro_municipality_reference_boundaries.geojson` | 11 | 57327 | `450167f4422dbc9d9ff9433140d2f16957a1da7ca04fd1a1a5df0f99e4c7e99c` |
| `oriental_mindoro_municipality_reference_boundaries.geojson` | 15 | 68453 | `f72c536a8d2e028371c952ed72bb76bacd25760398fba6325a810ac52cfa0e24` |
| `palawan_municipality_reference_boundaries.geojson` | 23 | 486458 | `3c583d308464bb1e12d94712226edb96043947a7d092847af086593e42b36717` |
| `puerto_princesa_city_municipality_reference_boundaries.geojson` | 1 | 38110 | `580752310e4a8126120eb5db4dee6f2b02864e6615613f060a69fd96fafb9ec1` |
| `romblon_municipality_reference_boundaries.geojson` | 17 | 48461 | `062845a3e98c75f492e5ce30386511c771e49304f9048a7027eafa9ac8168d53` |

## Identity and area checks

Area is in hectares. The reference column uses GeoRiskPH for the first four provinces and COD-AB for Palawan/Puerto Princesa. The LMB column is only supplied for the latter scopes as a distinct statistical measure.

| Workspace | PSGC (legacy) | Source shape ID | Imported area | Reference area | Difference | LMB land area |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| Boac | `1704001000` (`174001000`) | `30758251B94760060482549` | 23245.9531 | 23121.3225 | 0.5390% | — |
| Buenavista (Marinduque) | `1704002000` (`174002000`) | `30758251B40445123670017` | 7706.0260 | 7669.6428 | 0.4744% | — |
| Gasan | `1704003000` (`174003000`) | `30758251B69905180384114` | 9988.1488 | 9936.9918 | 0.5148% | — |
| Mogpog | `1704004000` (`174004000`) | `30758251B75724413863333` | 9990.9218 | 9908.7617 | 0.8292% | — |
| Santa Cruz (Marinduque) | `1704005000` (`174005000`) | `30758251B46699269491955` | 24333.7377 | 24247.8657 | 0.3541% | — |
| Torrijos | `1704006000` (`174006000`) | `30758251B44137486572010` | 17489.0734 | 17357.7012 | 0.7569% | — |
| Abra de Ilog | `1705101000` (`175101000`) | `30758251B36470920271092` | 60537.9788 | 60191.3917 | 0.5758% | — |
| Calintaan | `1705102000` (`175102000`) | `30758251B26098626095065` | 30140.1407 | 29885.9194 | 0.8506% | — |
| Looc (Occidental Mindoro) | `1705103000` (`175103000`) | `30758251B31470126644095` | 13069.7003 | 12924.7503 | 1.1215% | — |
| Lubang | `1705104000` (`175104000`) | `30758251B11611856867349` | 12596.9129 | 12527.2553 | 0.5560% | — |
| Magsaysay (Occidental Mindoro) | `1705105000` (`175105000`) | `30758251B37919333607414` | 26271.5504 | 26031.1259 | 0.9236% | — |
| Mamburao | `1705106000` (`175106000`) | `30758251B70283159165752` | 32258.6217 | 32074.7563 | 0.5732% | — |
| Paluan | `1705107000` (`175107000`) | `30758251B4069170927747` | 53323.0257 | 52935.6012 | 0.7319% | — |
| Rizal (Occidental Mindoro) | `1705108000` (`175108000`) | `30758251B74613815178222` | 30556.4187 | 30379.2273 | 0.5833% | — |
| Sablayan | `1705109000` (`175109000`) | `30758251B34022252316389` | 211563.0580 | 210342.5731 | 0.5802% | — |
| San Jose (Occidental Mindoro) | `1705110000` (`175110000`) | `30758251B733109742349` | 61404.7664 | 61071.7091 | 0.5454% | — |
| Santa Cruz (Occidental Mindoro) | `1705111000` (`175111000`) | `30758251B4413725625416` | 69307.1002 | 68864.3251 | 0.6430% | — |
| Baco | `1705201000` (`175201000`) | `30758251B69479050195003` | 43778.0953 | 43502.1564 | 0.6343% | — |
| Bansud | `1705202000` (`175202000`) | `30758251B27476294786678` | 34805.8944 | 34537.1953 | 0.7780% | — |
| Bongabong | `1705203000` (`175203000`) | `30758251B15501509529895` | 39801.2020 | 39608.6125 | 0.4862% | — |
| Bulalacao | `1705204000` (`175204000`) | `30758251B89806252080387` | 35473.7972 | 35280.6137 | 0.5476% | — |
| Calapan City | `1705205000` (`175205000`) | `30758251B77350797414463` | 18618.5909 | 18379.7189 | 1.2996% | — |
| Gloria | `1705206000` (`175206000`) | `30758251B4033826093725` | 25685.3468 | 25609.8118 | 0.2949% | — |
| Mansalay | `1705207000` (`175207000`) | `30758251B13685654422788` | 45697.5591 | 45438.6923 | 0.5697% | — |
| Naujan | `1705208000` (`175208000`) | `30758251B73486212842201` | 57788.0171 | 57474.1978 | 0.5460% | — |
| Pinamalayan | `1705209000` (`175209000`) | `30758251B9076432182480` | 16974.2156 | 16776.9116 | 1.1760% | — |
| Pola | `1705210000` (`175210000`) | `30758251B5257459344407` | 16935.2386 | 16839.6355 | 0.5677% | — |
| Puerto Galera | `1705211000` (`175211000`) | `30758251B54215534625427` | 11037.1434 | 10936.2765 | 0.9223% | — |
| Roxas (Oriental Mindoro) | `1705212000` (`175212000`) | `30758251B86973741519598` | 10473.1026 | 10384.1761 | 0.8564% | — |
| San Teodoro | `1705213000` (`175213000`) | `30758251B90204819305655` | 15706.1866 | 15604.4584 | 0.6519% | — |
| Socorro (Oriental Mindoro) | `1705214000` (`175214000`) | `30758251B49339662744818` | 20629.9925 | 20484.1001 | 0.7122% | — |
| Victoria (Oriental Mindoro) | `1705215000` (`175215000`) | `30758251B49564856626223` | 20835.8029 | 20669.9262 | 0.8025% | — |
| Aborlan | `1705301000` (`175301000`) | `30758251B88743888905923` | 77615.0579 | 77112.0630 | 0.6523% | 64285.0 |
| Agutaya | `1705302000` (`175302000`) | `30758251B94272049094812` | 3107.4596 | 3097.0637 | 0.3357% | 3141.0 |
| Araceli | `1705303000` (`175303000`) | `30758251B86342486803813` | 17478.9635 | 17411.9056 | 0.3851% | 17764.0 |
| Balabac | `1705304000` (`175304000`) | `30758251B69147265954334` | 55966.3120 | 55912.1297 | 0.0969% | 55845.00000000001 |
| Bataraza | `1705305000` (`175305000`) | `30758251B18056750213925` | 69256.4168 | 68854.9407 | 0.5831% | 90516.0 |
| Brooke's Point | `1705306000` (`175306000`) | `30758251B43444158491696` | 68964.7684 | 68482.2091 | 0.7046% | 115747.0 |
| Busuanga | `1705307000` (`175307000`) | `30758251B1739425505893` | 40649.0195 | 40453.8753 | 0.4824% | 36796.0 |
| Cagayancillo | `1705308000` (`175308000`) | `30758251B20045394084873` | 1237.3342 | 1230.6574 | 0.5425% | 2542.0 |
| Coron | `1705309000` (`175309000`) | `30758251B89626274452255` | 69701.5944 | 69436.6538 | 0.3816% | 59996.0 |
| Cuyo | `1705310000` (`175310000`) | `30758251B36861080699281` | 4285.9772 | 4279.2357 | 0.1575% | 2746.0 |
| Dumaran | `1705311000` (`175311000`) | `30758251B96628789522948` | 52889.9220 | 52617.5036 | 0.5177% | 53497.0 |
| El Nido | `1705312000` (`175312000`) | `30758251B21090387346093` | 56530.5992 | 56202.0581 | 0.5846% | 59114.0 |
| Linapacan | `1705313000` (`175313000`) | `30758251B95429625837962` | 16074.9596 | 15972.0763 | 0.6441% | 16659.0 |
| Magsaysay (Palawan) | `1705314000` (`175314000`) | `30758251B4414445809925` | 4751.9108 | 4753.1597 | 0.0263% | 4885.0 |
| Narra | `1705315000` (`175315000`) | `30758251B72391607373552` | 75817.6469 | 75339.2467 | 0.6350% | 83949.0 |
| Quezon (Palawan) | `1705317000` (`175317000`) | `30758251B7894567948973` | 94099.3434 | 93512.1957 | 0.6279% | 93760.0 |
| Roxas (Palawan) | `1705318000` (`175318000`) | `30758251B10061841294265` | 113681.7685 | 112999.8383 | 0.6035% | 115029.0 |
| San Vicente (Palawan) | `1705319000` (`175319000`) | `30758251B71123600878116` | 60881.5488 | 60427.0744 | 0.7521% | 63413.0 |
| Taytay (Palawan) | `1705320000` (`175320000`) | `30758251B72316190206283` | 130536.0793 | 129926.0545 | 0.4695% | 149779.0 |
| Kalayaan (Palawan) | `1705321000` (`175321000`) | `30758251B42764898301689` | 40.2311 | 39.9814 | 0.6244% | 88.0 |
| Culion | `1705322000` (`175322000`) | `30758251B61571495122519` | 43292.4992 | 43220.0551 | 0.1676% | 44587.0 |
| Dr. Jose P. Rizal (Palawan) | `1705323000` (`175323000`) | `30758251B22012407080557` | 127959.3146 | 127126.6667 | 0.6550% | 165586.0 |
| Sofronio Española | `1705324000` (`175324000`) | `30758251B15412081534640` | 44431.8488 | 44204.1981 | 0.5150% | 51450.0 |
| Puerto Princesa City | `1731500000` (`175316000`) | `30758251B64178272348738` | 218262.6752 | 216861.6457 | 0.6460% | 204827.0 |
| Alcantara (Romblon) | `1705901000` (`175901000`) | `30758251B13764061513438` | 7080.7656 | 7047.7848 | 0.4680% | — |
| Banton | `1705902000` (`175902000`) | `30758251B55331406144814` | 2898.0418 | 2861.9273 | 1.2619% | — |
| Cajidiocan | `1705903000` (`175903000`) | `30758251B60223458934501` | 12840.5289 | 12749.3797 | 0.7149% | — |
| Calatrava (Romblon) | `1705904000` (`175904000`) | `30758251B34852860389402` | 3832.1228 | 3802.8820 | 0.7689% | — |
| Concepcion (Romblon) | `1705905000` (`175905000`) | `30758251B24706592587585` | 2059.6165 | 2039.4323 | 0.9897% | — |
| Corcuera | `1705906000` (`175906000`) | `30758251B41265366730611` | 2074.2189 | 2047.2588 | 1.3169% | — |
| Looc (Romblon) | `1705907000` (`175907000`) | `30758251B76752924951322` | 8230.3524 | 8148.3272 | 1.0067% | — |
| Magdiwang | `1705908000` (`175908000`) | `30758251B40201197939052` | 7975.4876 | 7896.9103 | 0.9950% | — |
| Odiongan | `1705909000` (`175909000`) | `30758251B56543042574103` | 13736.6470 | 13659.5933 | 0.5641% | — |
| Romblon (Romblon) | `1705910000` (`175910000`) | `30758251B85981656551245` | 8938.9951 | 8846.9224 | 1.0407% | — |
| San Agustin (Romblon) | `1705911000` (`175911000`) | `30758251B77746184637785` | 10301.7720 | 10218.2847 | 0.8170% | — |
| San Andres (Romblon) | `1705912000` (`175912000`) | `30758251B27093145704117` | 10300.5901 | 10258.2466 | 0.4128% | — |
| San Fernando (Romblon) | `1705913000` (`175913000`) | `30758251B454076707789` | 24592.8883 | 24457.0493 | 0.5554% | — |
| San Jose (Romblon) | `1705914000` (`175914000`) | `30758251B67956852885073` | 2851.8709 | 2807.6831 | 1.5738% | — |
| Santa Fe (Romblon) | `1705915000` (`175915000`) | `30758251B97605936547347` | 5721.5085 | 5652.5952 | 1.2191% | — |
| Ferrol | `1705916000` (`175916000`) | `30758251B18326457927940` | 3515.5637 | 3468.7424 | 1.3498% | — |
| Santa Maria (Romblon) | `1705917000` (`175917000`) | `30758251B99723317147835` | 5055.8083 | 5004.9763 | 1.0156% | — |

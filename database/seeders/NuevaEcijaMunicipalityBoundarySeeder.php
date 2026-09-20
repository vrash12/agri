<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Region III planning references; run explicitly after a database backup.
 *
 * Source identities and city supervision: docs/REGION_III_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class NuevaEcijaMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/nueva_ecija_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '255ae3b757314cc19f51a71774327e36124c28f154aee7e26d5668395de033c9';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Aliaga' => [
            'code' => 'ALIAGA',
            'aliases' => ['ALIAGA'],
            'psgc_code' => '0304901000',
            'legacy_psgc_code' => '034901000',
            'shape_id' => '30758251B61766389161582',
            'reference_area_ha' => 9525.709102,
        ],
        'Bongabon' => [
            'code' => 'BONGABON',
            'aliases' => ['BONGABON'],
            'psgc_code' => '0304902000',
            'legacy_psgc_code' => '034902000',
            'shape_id' => '30758251B28322914429669',
            'reference_area_ha' => 21556.084649,
        ],
        'Cabanatuan City' => [
            'code' => 'CABANATUAN_CITY',
            'aliases' => ['CABANATUAN_CITY'],
            'psgc_code' => '0304903000',
            'legacy_psgc_code' => '034903000',
            'shape_id' => '30758251B91013858320846',
            'reference_area_ha' => 18923.081950,
        ],
        'Cabiao' => [
            'code' => 'CABIAO',
            'aliases' => ['CABIAO'],
            'psgc_code' => '0304904000',
            'legacy_psgc_code' => '034904000',
            'shape_id' => '30758251B91835064861119',
            'reference_area_ha' => 11227.411242,
        ],
        'Carranglan' => [
            'code' => 'CARRANGLAN',
            'aliases' => ['CARRANGLAN'],
            'psgc_code' => '0304905000',
            'legacy_psgc_code' => '034905000',
            'shape_id' => '30758251B38781000635983',
            'reference_area_ha' => 73448.602235,
        ],
        'City of Gapan' => [
            'code' => 'GAPAN_CITY',
            'aliases' => ['GAPAN_CITY'],
            'psgc_code' => '0304908000',
            'legacy_psgc_code' => '034908000',
            'shape_id' => '30758251B81121957716854',
            'reference_area_ha' => 17215.854006,
            'workspace_name' => 'Gapan City',
        ],
        'Cuyapo' => [
            'code' => 'CUYAPO',
            'aliases' => ['CUYAPO'],
            'psgc_code' => '0304906000',
            'legacy_psgc_code' => '034906000',
            'shape_id' => '30758251B13758453625631',
            'reference_area_ha' => 17835.494199,
        ],
        'Gabaldon' => [
            'code' => 'GABALDON',
            'aliases' => ['GABALDON'],
            'psgc_code' => '0304907000',
            'legacy_psgc_code' => '034907000',
            'shape_id' => '30758251B70332273238946',
            'reference_area_ha' => 36750.488861,
        ],
        'General Mamerto Natividad' => [
            'code' => 'GENERAL_MAMERTO_NATIVIDAD',
            'aliases' => ['GENERAL_MAMERTO_NATIVIDAD'],
            'psgc_code' => '0304909000',
            'legacy_psgc_code' => '034909000',
            'shape_id' => '30758251B30324290994984',
            'reference_area_ha' => 10839.831869,
        ],
        'General Tinio' => [
            'code' => 'GENERAL_TINIO',
            'aliases' => ['GENERAL_TINIO'],
            'psgc_code' => '0304910000',
            'legacy_psgc_code' => '034910000',
            'shape_id' => '30758251B90197047513791',
            'reference_area_ha' => 56315.599515,
        ],
        'Guimba' => [
            'code' => 'GUIMBA',
            'aliases' => ['GUIMBA'],
            'psgc_code' => '0304911000',
            'legacy_psgc_code' => '034911000',
            'shape_id' => '30758251B97926428018221',
            'reference_area_ha' => 21535.314545,
        ],
        'Jaen' => [
            'code' => 'JAEN',
            'aliases' => ['JAEN'],
            'psgc_code' => '0304912000',
            'legacy_psgc_code' => '034912000',
            'shape_id' => '30758251B14595359840210',
            'reference_area_ha' => 9809.617823,
        ],
        'Laur' => [
            'code' => 'LAUR',
            'aliases' => ['LAUR'],
            'psgc_code' => '0304913000',
            'legacy_psgc_code' => '034913000',
            'shape_id' => '30758251B54864589401761',
            'reference_area_ha' => 15625.265694,
        ],
        'Licab' => [
            'code' => 'LICAB',
            'aliases' => ['LICAB'],
            'psgc_code' => '0304914000',
            'legacy_psgc_code' => '034914000',
            'shape_id' => '30758251B80146072363536',
            'reference_area_ha' => 6445.737433,
        ],
        'Llanera' => [
            'code' => 'LLANERA',
            'aliases' => ['LLANERA'],
            'psgc_code' => '0304915000',
            'legacy_psgc_code' => '034915000',
            'shape_id' => '30758251B85040378394096',
            'reference_area_ha' => 10010.891363,
        ],
        'Lupao' => [
            'code' => 'LUPAO',
            'aliases' => ['LUPAO'],
            'psgc_code' => '0304916000',
            'legacy_psgc_code' => '034916000',
            'shape_id' => '30758251B11609391630231',
            'reference_area_ha' => 11675.187156,
        ],
        'Nampicuan' => [
            'code' => 'NAMPICUAN',
            'aliases' => ['NAMPICUAN'],
            'psgc_code' => '0304918000',
            'legacy_psgc_code' => '034918000',
            'shape_id' => '30758251B757279670105',
            'reference_area_ha' => 4653.721487,
        ],
        'Palayan City' => [
            'code' => 'PALAYAN_CITY',
            'aliases' => ['PALAYAN_CITY'],
            'psgc_code' => '0304919000',
            'legacy_psgc_code' => '034919000',
            'shape_id' => '30758251B19643083893991',
            'reference_area_ha' => 13122.593489,
        ],
        'Pantabangan' => [
            'code' => 'PANTABANGAN',
            'aliases' => ['PANTABANGAN'],
            'psgc_code' => '0304920000',
            'legacy_psgc_code' => '034920000',
            'shape_id' => '30758251B73416019883102',
            'reference_area_ha' => 41972.824773,
        ],
        'Peñaranda' => [
            'code' => 'PENARANDA',
            'aliases' => ['PENARANDA'],
            'psgc_code' => '0304921000',
            'legacy_psgc_code' => '034921000',
            'shape_id' => '30758251B17193566856457',
            'reference_area_ha' => 6308.240339,
        ],
        'Quezon' => [
            'code' => 'QUEZON_NUEVA_ECIJA',
            'aliases' => ['QUEZON_NUEVA_ECIJA'],
            'psgc_code' => '0304922000',
            'legacy_psgc_code' => '034922000',
            'shape_id' => '30758251B11811297949808',
            'reference_area_ha' => 6410.673116,
            'workspace_name' => 'Quezon (Nueva Ecija)',
        ],
        'Rizal' => [
            'code' => 'RIZAL_NUEVA_ECIJA',
            'aliases' => ['RIZAL_NUEVA_ECIJA'],
            'psgc_code' => '0304923000',
            'legacy_psgc_code' => '034923000',
            'shape_id' => '30758251B98228073754255',
            'reference_area_ha' => 13446.284431,
            'workspace_name' => 'Rizal (Nueva Ecija)',
        ],
        'San Antonio' => [
            'code' => 'SAN_ANTONIO_NUEVA_ECIJA',
            'aliases' => ['SAN_ANTONIO_NUEVA_ECIJA'],
            'psgc_code' => '0304924000',
            'legacy_psgc_code' => '034924000',
            'shape_id' => '30758251B18161009725227',
            'reference_area_ha' => 18126.723853,
            'workspace_name' => 'San Antonio (Nueva Ecija)',
        ],
        'San Isidro' => [
            'code' => 'SAN_ISIDRO_NUEVA_ECIJA',
            'aliases' => ['SAN_ISIDRO_NUEVA_ECIJA'],
            'psgc_code' => '0304925000',
            'legacy_psgc_code' => '034925000',
            'shape_id' => '30758251B57487055116428',
            'reference_area_ha' => 4778.832276,
            'workspace_name' => 'San Isidro (Nueva Ecija)',
        ],
        'San Jose City' => [
            'code' => 'SAN_JOSE_CITY_NUEVA_ECIJA',
            'aliases' => ['SAN_JOSE_CITY_NUEVA_ECIJA'],
            'psgc_code' => '0304926000',
            'legacy_psgc_code' => '034926000',
            'shape_id' => '30758251B74441854533051',
            'reference_area_ha' => 19194.711166,
            'workspace_name' => 'San Jose City (Nueva Ecija)',
        ],
        'San Leonardo' => [
            'code' => 'SAN_LEONARDO',
            'aliases' => ['SAN_LEONARDO'],
            'psgc_code' => '0304927000',
            'legacy_psgc_code' => '034927000',
            'shape_id' => '30758251B34551572627583',
            'reference_area_ha' => 5077.563037,
        ],
        'Santa Rosa' => [
            'code' => 'SANTA_ROSA_NUEVA_ECIJA',
            'aliases' => ['SANTA_ROSA_NUEVA_ECIJA'],
            'psgc_code' => '0304928000',
            'legacy_psgc_code' => '034928000',
            'shape_id' => '30758251B64711769669344',
            'reference_area_ha' => 10712.473146,
            'workspace_name' => 'Santa Rosa (Nueva Ecija)',
        ],
        'Santo Domingo' => [
            'code' => 'SANTO_DOMINGO_NUEVA_ECIJA',
            'aliases' => ['SANTO_DOMINGO_NUEVA_ECIJA'],
            'psgc_code' => '0304929000',
            'legacy_psgc_code' => '034929000',
            'shape_id' => '30758251B71644316886233',
            'reference_area_ha' => 8618.388550,
            'workspace_name' => 'Santo Domingo (Nueva Ecija)',
        ],
        'Science City of Muñoz' => [
            'code' => 'SCIENCE_CITY_OF_MUNOZ',
            'aliases' => ['SCIENCE_CITY_OF_MUNOZ'],
            'psgc_code' => '0304917000',
            'legacy_psgc_code' => '034917000',
            'shape_id' => '30758251B8950221539698',
            'reference_area_ha' => 12944.463442,
        ],
        'Talavera' => [
            'code' => 'TALAVERA',
            'aliases' => ['TALAVERA'],
            'psgc_code' => '0304930000',
            'legacy_psgc_code' => '034930000',
            'shape_id' => '30758251B41581765009138',
            'reference_area_ha' => 13176.573988,
        ],
        'Talugtug' => [
            'code' => 'TALUGTUG',
            'aliases' => ['TALUGTUG'],
            'psgc_code' => '0304931000',
            'legacy_psgc_code' => '034931000',
            'shape_id' => '30758251B85167098173999',
            'reference_area_ha' => 10689.170144,
        ],
        'Zaragoza' => [
            'code' => 'ZARAGOZA',
            'aliases' => ['ZARAGOZA'],
            'psgc_code' => '0304932000',
            'legacy_psgc_code' => '034932000',
            'shape_id' => '30758251B15421903274742',
            'reference_area_ha' => 7746.157264,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Nueva Ecija', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 32 Nueva Ecija planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

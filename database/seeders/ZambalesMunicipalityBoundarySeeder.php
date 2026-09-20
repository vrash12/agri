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
class ZambalesMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/zambales_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'ff12199e45268edf036c07811f113e30403f925ce2de557f1bcbae37becac3f4';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Botolan' => [
            'code' => 'BOTOLAN',
            'aliases' => ['BOTOLAN'],
            'psgc_code' => '0307101000',
            'legacy_psgc_code' => '037101000',
            'shape_id' => '30758251B29617351777930',
            'reference_area_ha' => 66813.900328,
        ],
        'Cabangan' => [
            'code' => 'CABANGAN',
            'aliases' => ['CABANGAN'],
            'psgc_code' => '0307102000',
            'legacy_psgc_code' => '037102000',
            'shape_id' => '30758251B86660970091013',
            'reference_area_ha' => 20585.852397,
        ],
        'Candelaria' => [
            'code' => 'CANDELARIA_ZAMBALES',
            'aliases' => ['CANDELARIA_ZAMBALES'],
            'psgc_code' => '0307103000',
            'legacy_psgc_code' => '037103000',
            'shape_id' => '30758251B19895305686626',
            'reference_area_ha' => 42228.768286,
            'workspace_name' => 'Candelaria (Zambales)',
        ],
        'Castillejos' => [
            'code' => 'CASTILLEJOS',
            'aliases' => ['CASTILLEJOS'],
            'psgc_code' => '0307104000',
            'legacy_psgc_code' => '037104000',
            'shape_id' => '30758251B55463127923623',
            'reference_area_ha' => 10408.262988,
        ],
        'Iba' => [
            'code' => 'IBA',
            'aliases' => ['IBA'],
            'psgc_code' => '0307105000',
            'legacy_psgc_code' => '037105000',
            'shape_id' => '30758251B62062623216979',
            'reference_area_ha' => 14034.837920,
        ],
        'Masinloc' => [
            'code' => 'MASINLOC',
            'aliases' => ['MASINLOC'],
            'psgc_code' => '0307106000',
            'legacy_psgc_code' => '037106000',
            'shape_id' => '30758251B39623995446640',
            'reference_area_ha' => 24840.293816,
        ],
        'Palauig' => [
            'code' => 'PALAUIG',
            'aliases' => ['PALAUIG'],
            'psgc_code' => '0307108000',
            'legacy_psgc_code' => '037108000',
            'shape_id' => '30758251B57141645891791',
            'reference_area_ha' => 30869.180307,
        ],
        'San Antonio' => [
            'code' => 'SAN_ANTONIO_ZAMBALES',
            'aliases' => ['SAN_ANTONIO_ZAMBALES'],
            'psgc_code' => '0307109000',
            'legacy_psgc_code' => '037109000',
            'shape_id' => '30758251B44301326667503',
            'reference_area_ha' => 16726.133526,
            'workspace_name' => 'San Antonio (Zambales)',
        ],
        'San Felipe' => [
            'code' => 'SAN_FELIPE',
            'aliases' => ['SAN_FELIPE'],
            'psgc_code' => '0307110000',
            'legacy_psgc_code' => '037110000',
            'shape_id' => '30758251B59257355730786',
            'reference_area_ha' => 11436.705987,
        ],
        'San Marcelino' => [
            'code' => 'SAN_MARCELINO',
            'aliases' => ['SAN_MARCELINO'],
            'psgc_code' => '0307111000',
            'legacy_psgc_code' => '037111000',
            'shape_id' => '30758251B2790468349785',
            'reference_area_ha' => 41994.779919,
        ],
        'San Narciso' => [
            'code' => 'SAN_NARCISO_ZAMBALES',
            'aliases' => ['SAN_NARCISO_ZAMBALES'],
            'psgc_code' => '0307112000',
            'legacy_psgc_code' => '037112000',
            'shape_id' => '30758251B59328560779443',
            'reference_area_ha' => 7286.900751,
            'workspace_name' => 'San Narciso (Zambales)',
        ],
        'Santa Cruz' => [
            'code' => 'SANTA_CRUZ_ZAMBALES',
            'aliases' => ['SANTA_CRUZ_ZAMBALES'],
            'psgc_code' => '0307113000',
            'legacy_psgc_code' => '037113000',
            'shape_id' => '30758251B81450613900201',
            'reference_area_ha' => 45955.011009,
            'workspace_name' => 'Santa Cruz (Zambales)',
        ],
        'Subic' => [
            'code' => 'SUBIC',
            'aliases' => ['SUBIC'],
            'psgc_code' => '0307114000',
            'legacy_psgc_code' => '037114000',
            'shape_id' => '30758251B42276045150270',
            'reference_area_ha' => 24750.006700,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Zambales', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 13 Zambales planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

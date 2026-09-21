<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned remaining CAR planning references; run explicitly after a database backup.
 *
 * Source identities: docs/REMAINING_CAR_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class AbraMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/abra_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '7567d8a1aaea0830f2f89cf720295c3129df78ff8227169cf56a713399c6f688';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bangued' => [
            'code' => 'BANGUED',
            'aliases' => ['BANGUED'],
            'psgc_code' => '1400101000',
            'legacy_psgc_code' => '140101000',
            'shape_id' => '30758251B80986632520556',
            'reference_area_ha' => 11906.420646,
        ],
        'Boliney' => [
            'code' => 'BOLINEY',
            'aliases' => ['BOLINEY'],
            'psgc_code' => '1400102000',
            'legacy_psgc_code' => '140102000',
            'shape_id' => '30758251B13786076452973',
            'reference_area_ha' => 17832.879756,
        ],
        'Bucay' => [
            'code' => 'BUCAY',
            'aliases' => ['BUCAY'],
            'psgc_code' => '1400103000',
            'legacy_psgc_code' => '140103000',
            'shape_id' => '30758251B14856837893218',
            'reference_area_ha' => 9897.100382,
        ],
        'Bucloc' => [
            'code' => 'BUCLOC',
            'aliases' => ['BUCLOC'],
            'psgc_code' => '1400104000',
            'legacy_psgc_code' => '140104000',
            'shape_id' => '30758251B79611971032611',
            'reference_area_ha' => 5734.391119,
        ],
        'Daguioman' => [
            'code' => 'DAGUIOMAN',
            'aliases' => ['DAGUIOMAN'],
            'psgc_code' => '1400105000',
            'legacy_psgc_code' => '140105000',
            'shape_id' => '30758251B44200269930989',
            'reference_area_ha' => 9320.935128,
        ],
        'Danglas' => [
            'code' => 'DANGLAS',
            'aliases' => ['DANGLAS'],
            'psgc_code' => '1400106000',
            'legacy_psgc_code' => '140106000',
            'shape_id' => '30758251B62080674361927',
            'reference_area_ha' => 17648.258783,
        ],
        'Dolores' => [
            'code' => 'DOLORES_ABRA',
            'aliases' => ['DOLORES_ABRA'],
            'psgc_code' => '1400107000',
            'legacy_psgc_code' => '140107000',
            'shape_id' => '30758251B59360530740420',
            'reference_area_ha' => 4462.828055,
            'workspace_name' => 'Dolores (Abra)',
        ],
        'La Paz' => [
            'code' => 'LA_PAZ_ABRA',
            'aliases' => ['LA_PAZ_ABRA'],
            'psgc_code' => '1400108000',
            'legacy_psgc_code' => '140108000',
            'shape_id' => '30758251B73355219145022',
            'reference_area_ha' => 5262.259645,
            'workspace_name' => 'La Paz (Abra)',
        ],
        'Lacub' => [
            'code' => 'LACUB',
            'aliases' => ['LACUB'],
            'psgc_code' => '1400109000',
            'legacy_psgc_code' => '140109000',
            'shape_id' => '30758251B49007488326258',
            'reference_area_ha' => 24801.330986,
        ],
        'Lagangilang' => [
            'code' => 'LAGANGILANG',
            'aliases' => ['LAGANGILANG'],
            'psgc_code' => '1400110000',
            'legacy_psgc_code' => '140110000',
            'shape_id' => '30758251B70934774577797',
            'reference_area_ha' => 9082.134324,
        ],
        'Lagayan' => [
            'code' => 'LAGAYAN',
            'aliases' => ['LAGAYAN'],
            'psgc_code' => '1400111000',
            'legacy_psgc_code' => '140111000',
            'shape_id' => '30758251B52711764832029',
            'reference_area_ha' => 13288.645618,
        ],
        'Langiden' => [
            'code' => 'LANGIDEN',
            'aliases' => ['LANGIDEN'],
            'psgc_code' => '1400112000',
            'legacy_psgc_code' => '140112000',
            'shape_id' => '30758251B76383388538181',
            'reference_area_ha' => 8971.393844,
        ],
        'Licuan-Baay' => [
            'code' => 'LICUAN_BAAY',
            'aliases' => ['LICUAN_BAAY'],
            'psgc_code' => '1400113000',
            'legacy_psgc_code' => '140113000',
            'shape_id' => '30758251B20296785159128',
            'reference_area_ha' => 27523.021594,
        ],
        'Luba' => [
            'code' => 'LUBA',
            'aliases' => ['LUBA'],
            'psgc_code' => '1400114000',
            'legacy_psgc_code' => '140114000',
            'shape_id' => '30758251B28031934346608',
            'reference_area_ha' => 13699.622273,
        ],
        'Malibcong' => [
            'code' => 'MALIBCONG',
            'aliases' => ['MALIBCONG'],
            'psgc_code' => '1400115000',
            'legacy_psgc_code' => '140115000',
            'shape_id' => '30758251B83293009371057',
            'reference_area_ha' => 25912.552467,
        ],
        'Manabo' => [
            'code' => 'MANABO',
            'aliases' => ['MANABO'],
            'psgc_code' => '1400116000',
            'legacy_psgc_code' => '140116000',
            'shape_id' => '30758251B10650889631095',
            'reference_area_ha' => 7706.377977,
        ],
        'Peñarrubia' => [
            'code' => 'PENARRUBIA',
            'aliases' => ['PENARRUBIA'],
            'psgc_code' => '1400117000',
            'legacy_psgc_code' => '140117000',
            'shape_id' => '30758251B26055192446489',
            'reference_area_ha' => 3953.921592,
        ],
        'Pidigan' => [
            'code' => 'PIDIGAN',
            'aliases' => ['PIDIGAN'],
            'psgc_code' => '1400118000',
            'legacy_psgc_code' => '140118000',
            'shape_id' => '30758251B9631620004584',
            'reference_area_ha' => 5489.933666,
        ],
        'Pilar' => [
            'code' => 'PILAR_ABRA',
            'aliases' => ['PILAR_ABRA'],
            'psgc_code' => '1400119000',
            'legacy_psgc_code' => '140119000',
            'shape_id' => '30758251B40403541911249',
            'reference_area_ha' => 8480.951882,
            'workspace_name' => 'Pilar (Abra)',
        ],
        'Sallapadan' => [
            'code' => 'SALLAPADAN',
            'aliases' => ['SALLAPADAN'],
            'psgc_code' => '1400120000',
            'legacy_psgc_code' => '140120000',
            'shape_id' => '30758251B64291084214185',
            'reference_area_ha' => 13333.001825,
        ],
        'San Isidro' => [
            'code' => 'SAN_ISIDRO_ABRA',
            'aliases' => ['SAN_ISIDRO_ABRA'],
            'psgc_code' => '1400121000',
            'legacy_psgc_code' => '140121000',
            'shape_id' => '30758251B37314448976896',
            'reference_area_ha' => 4501.186948,
            'workspace_name' => 'San Isidro (Abra)',
        ],
        'San Juan' => [
            'code' => 'SAN_JUAN_ABRA',
            'aliases' => ['SAN_JUAN_ABRA'],
            'psgc_code' => '1400122000',
            'legacy_psgc_code' => '140122000',
            'shape_id' => '30758251B17638049183402',
            'reference_area_ha' => 6842.630345,
            'workspace_name' => 'San Juan (Abra)',
        ],
        'San Quintin' => [
            'code' => 'SAN_QUINTIN_ABRA',
            'aliases' => ['SAN_QUINTIN_ABRA'],
            'psgc_code' => '1400123000',
            'legacy_psgc_code' => '140123000',
            'shape_id' => '30758251B76623665911400',
            'reference_area_ha' => 5727.083368,
            'workspace_name' => 'San Quintin (Abra)',
        ],
        'Tayum' => [
            'code' => 'TAYUM',
            'aliases' => ['TAYUM'],
            'psgc_code' => '1400124000',
            'legacy_psgc_code' => '140124000',
            'shape_id' => '30758251B59448438391168',
            'reference_area_ha' => 4836.585087,
        ],
        'Tineg' => [
            'code' => 'TINEG',
            'aliases' => ['TINEG'],
            'psgc_code' => '1400125000',
            'legacy_psgc_code' => '140125000',
            'shape_id' => '30758251B75797793552345',
            'reference_area_ha' => 74435.567530,
        ],
        'Tubo' => [
            'code' => 'TUBO',
            'aliases' => ['TUBO'],
            'psgc_code' => '1400126000',
            'legacy_psgc_code' => '140126000',
            'shape_id' => '30758251B14618145037490',
            'reference_area_ha' => 43135.479629,
        ],
        'Villaviciosa' => [
            'code' => 'VILLAVICIOSA',
            'aliases' => ['VILLAVICIOSA'],
            'psgc_code' => '1400127000',
            'legacy_psgc_code' => '140127000',
            'shape_id' => '30758251B55452258834598',
            'reference_area_ha' => 8878.121331,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Abra', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 27 Abra planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

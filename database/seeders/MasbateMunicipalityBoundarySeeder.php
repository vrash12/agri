<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Bicol planning references; run explicitly after a database backup.
 *
 * Source identities and separate Naga City scope: docs/BICOL_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class MasbateMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/masbate_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '2e76d047b8a3d3f8e137ccd572b1c8e10832c59784a59475628224f5e0a33d56';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Aroroy' => [
            'code' => 'AROROY',
            'aliases' => ['AROROY'],
            'psgc_code' => '0504101000',
            'legacy_psgc_code' => '054101000',
            'shape_id' => '30758251B32987971976921',
            'reference_area_ha' => 45643.655390,
        ],
        'Baleno' => [
            'code' => 'BALENO',
            'aliases' => ['BALENO'],
            'psgc_code' => '0504102000',
            'legacy_psgc_code' => '054102000',
            'shape_id' => '30758251B93962495881517',
            'reference_area_ha' => 15513.557056,
        ],
        'Balud' => [
            'code' => 'BALUD',
            'aliases' => ['BALUD'],
            'psgc_code' => '0504103000',
            'legacy_psgc_code' => '054103000',
            'shape_id' => '30758251B44740133494602',
            'reference_area_ha' => 20975.654856,
        ],
        'Batuan' => [
            'code' => 'BATUAN_MASBATE',
            'aliases' => ['BATUAN_MASBATE'],
            'psgc_code' => '0504104000',
            'legacy_psgc_code' => '054104000',
            'shape_id' => '30758251B92443363095578',
            'reference_area_ha' => 5275.404740,
            'workspace_name' => 'Batuan (Masbate)',
        ],
        'Cataingan' => [
            'code' => 'CATAINGAN',
            'aliases' => ['CATAINGAN'],
            'psgc_code' => '0504105000',
            'legacy_psgc_code' => '054105000',
            'shape_id' => '30758251B24487715352346',
            'reference_area_ha' => 18564.032858,
        ],
        'Cawayan' => [
            'code' => 'CAWAYAN',
            'aliases' => ['CAWAYAN'],
            'psgc_code' => '0504106000',
            'legacy_psgc_code' => '054106000',
            'shape_id' => '30758251B52497406200368',
            'reference_area_ha' => 26868.284040,
        ],
        'Claveria' => [
            'code' => 'CLAVERIA_MASBATE',
            'aliases' => ['CLAVERIA_MASBATE'],
            'psgc_code' => '0504107000',
            'legacy_psgc_code' => '054107000',
            'shape_id' => '30758251B14663924862074',
            'reference_area_ha' => 17090.443707,
            'workspace_name' => 'Claveria (Masbate)',
        ],
        'Dimasalang' => [
            'code' => 'DIMASALANG',
            'aliases' => ['DIMASALANG'],
            'psgc_code' => '0504108000',
            'legacy_psgc_code' => '054108000',
            'shape_id' => '30758251B45759569755783',
            'reference_area_ha' => 8090.035580,
        ],
        'Esperanza' => [
            'code' => 'ESPERANZA_MASBATE',
            'aliases' => ['ESPERANZA_MASBATE'],
            'psgc_code' => '0504109000',
            'legacy_psgc_code' => '054109000',
            'shape_id' => '30758251B37424940024390',
            'reference_area_ha' => 7031.121219,
            'workspace_name' => 'Esperanza (Masbate)',
        ],
        'Mandaon' => [
            'code' => 'MANDAON',
            'aliases' => ['MANDAON'],
            'psgc_code' => '0504110000',
            'legacy_psgc_code' => '054110000',
            'shape_id' => '30758251B39585013953692',
            'reference_area_ha' => 32194.428163,
        ],
        'City of Masbate' => [
            'code' => 'MASBATE_CITY',
            'aliases' => ['MASBATE_CITY'],
            'psgc_code' => '0504111000',
            'legacy_psgc_code' => '054111000',
            'shape_id' => '30758251B12655386269107',
            'reference_area_ha' => 20081.851951,
            'workspace_name' => 'Masbate City',
        ],
        'Milagros' => [
            'code' => 'MILAGROS',
            'aliases' => ['MILAGROS'],
            'psgc_code' => '0504112000',
            'legacy_psgc_code' => '054112000',
            'shape_id' => '30758251B25438008820372',
            'reference_area_ha' => 47859.086007,
        ],
        'Mobo' => [
            'code' => 'MOBO',
            'aliases' => ['MOBO'],
            'psgc_code' => '0504113000',
            'legacy_psgc_code' => '054113000',
            'shape_id' => '30758251B81886103418295',
            'reference_area_ha' => 15031.181870,
        ],
        'Monreal' => [
            'code' => 'MONREAL',
            'aliases' => ['MONREAL'],
            'psgc_code' => '0504114000',
            'legacy_psgc_code' => '054114000',
            'shape_id' => '30758251B42419582255323',
            'reference_area_ha' => 8923.035286,
        ],
        'Palanas' => [
            'code' => 'PALANAS',
            'aliases' => ['PALANAS'],
            'psgc_code' => '0504115000',
            'legacy_psgc_code' => '054115000',
            'shape_id' => '30758251B93799151609782',
            'reference_area_ha' => 13064.268180,
        ],
        'Pio V. Corpuz' => [
            'code' => 'PIO_V_CORPUS',
            'aliases' => ['PIO_V_CORPUS'],
            'psgc_code' => '0504116000',
            'legacy_psgc_code' => '054116000',
            'shape_id' => '30758251B13738867600145',
            'reference_area_ha' => 9053.381153,
            'workspace_name' => 'Pio V. Corpus',
        ],
        'Placer' => [
            'code' => 'PLACER_MASBATE',
            'aliases' => ['PLACER_MASBATE'],
            'psgc_code' => '0504117000',
            'legacy_psgc_code' => '054117000',
            'shape_id' => '30758251B35943258709305',
            'reference_area_ha' => 19889.803453,
            'workspace_name' => 'Placer (Masbate)',
        ],
        'San Fernando' => [
            'code' => 'SAN_FERNANDO_MASBATE',
            'aliases' => ['SAN_FERNANDO_MASBATE'],
            'psgc_code' => '0504118000',
            'legacy_psgc_code' => '054118000',
            'shape_id' => '30758251B6927182606556',
            'reference_area_ha' => 7813.364420,
            'workspace_name' => 'San Fernando (Masbate)',
        ],
        'San Jacinto' => [
            'code' => 'SAN_JACINTO_MASBATE',
            'aliases' => ['SAN_JACINTO_MASBATE'],
            'psgc_code' => '0504119000',
            'legacy_psgc_code' => '054119000',
            'shape_id' => '30758251B27565231296261',
            'reference_area_ha' => 10764.514131,
            'workspace_name' => 'San Jacinto (Masbate)',
        ],
        'San Pascual' => [
            'code' => 'SAN_PASCUAL_MASBATE',
            'aliases' => ['SAN_PASCUAL_MASBATE'],
            'psgc_code' => '0504120000',
            'legacy_psgc_code' => '054120000',
            'shape_id' => '30758251B17362038496048',
            'reference_area_ha' => 25645.460534,
            'workspace_name' => 'San Pascual (Masbate)',
        ],
        'Uson' => [
            'code' => 'USON',
            'aliases' => ['USON'],
            'psgc_code' => '0504121000',
            'legacy_psgc_code' => '054121000',
            'shape_id' => '30758251B48205364184496',
            'reference_area_ha' => 23551.140101,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Masbate', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 21 Masbate planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

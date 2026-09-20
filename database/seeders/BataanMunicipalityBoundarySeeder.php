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
class BataanMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/bataan_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'ddc9f2a607d1d94279710dd094cd56924a0b94bdc18e0397ceb3499df3d54bc9';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Abucay' => [
            'code' => 'ABUCAY',
            'aliases' => ['ABUCAY'],
            'psgc_code' => '0300801000',
            'legacy_psgc_code' => '030801000',
            'shape_id' => '30758251B46343582936162',
            'reference_area_ha' => 7688.902406,
        ],
        'Bagac' => [
            'code' => 'BAGAC',
            'aliases' => ['BAGAC'],
            'psgc_code' => '0300802000',
            'legacy_psgc_code' => '030802000',
            'shape_id' => '30758251B72127248939453',
            'reference_area_ha' => 18903.549767,
        ],
        'City of Balanga' => [
            'code' => 'BALANGA_CITY',
            'aliases' => ['BALANGA_CITY'],
            'psgc_code' => '0300803000',
            'legacy_psgc_code' => '030803000',
            'shape_id' => '30758251B46674947380799',
            'reference_area_ha' => 8958.513846,
            'workspace_name' => 'Balanga City',
        ],
        'Dinalupihan' => [
            'code' => 'DINALUPIHAN',
            'aliases' => ['DINALUPIHAN'],
            'psgc_code' => '0300804000',
            'legacy_psgc_code' => '030804000',
            'shape_id' => '30758251B87193060903149',
            'reference_area_ha' => 7550.977854,
        ],
        'Hermosa' => [
            'code' => 'HERMOSA',
            'aliases' => ['HERMOSA'],
            'psgc_code' => '0300805000',
            'legacy_psgc_code' => '030805000',
            'shape_id' => '30758251B15732618156338',
            'reference_area_ha' => 14895.286941,
        ],
        'Limay' => [
            'code' => 'LIMAY',
            'aliases' => ['LIMAY'],
            'psgc_code' => '0300806000',
            'legacy_psgc_code' => '030806000',
            'shape_id' => '30758251B34995051512109',
            'reference_area_ha' => 6070.007843,
        ],
        'Mariveles' => [
            'code' => 'MARIVELES',
            'aliases' => ['MARIVELES'],
            'psgc_code' => '0300807000',
            'legacy_psgc_code' => '030807000',
            'shape_id' => '30758251B81885646660603',
            'reference_area_ha' => 19431.622493,
        ],
        'Morong' => [
            'code' => 'MORONG_BATAAN',
            'aliases' => ['MORONG_BATAAN'],
            'psgc_code' => '0300808000',
            'legacy_psgc_code' => '030808000',
            'shape_id' => '30758251B50173683620188',
            'reference_area_ha' => 17359.919963,
            'workspace_name' => 'Morong (Bataan)',
        ],
        'Orani' => [
            'code' => 'ORANI',
            'aliases' => ['ORANI'],
            'psgc_code' => '0300809000',
            'legacy_psgc_code' => '030809000',
            'shape_id' => '30758251B4793511613736',
            'reference_area_ha' => 5365.953609,
        ],
        'Orion' => [
            'code' => 'ORION',
            'aliases' => ['ORION'],
            'psgc_code' => '0300810000',
            'legacy_psgc_code' => '030810000',
            'shape_id' => '30758251B25905682576781',
            'reference_area_ha' => 8963.981219,
        ],
        'Pilar' => [
            'code' => 'PILAR_BATAAN',
            'aliases' => ['PILAR_BATAAN'],
            'psgc_code' => '0300811000',
            'legacy_psgc_code' => '030811000',
            'shape_id' => '30758251B7851333843517',
            'reference_area_ha' => 4555.326179,
            'workspace_name' => 'Pilar (Bataan)',
        ],
        'Samal' => [
            'code' => 'SAMAL_BATAAN',
            'aliases' => ['SAMAL_BATAAN'],
            'psgc_code' => '0300812000',
            'legacy_psgc_code' => '030812000',
            'shape_id' => '30758251B26373043106772',
            'reference_area_ha' => 4751.322861,
            'workspace_name' => 'Samal (Bataan)',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Bataan', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 12 Bataan planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

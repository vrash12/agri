<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Mountain Province planning references; run explicitly after a database backup.
 *
 * Source identities: docs/MOUNTAIN_PROVINCE_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class MountainProvinceMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/mountain_province_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '752130e1d81994e34fc2db89f849bcd0d63b0cd8b14540c3c1afc3c5aa1f96f5';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Barlig' => [
            'code' => 'BARLIG',
            'aliases' => ['BARLIG'],
            'psgc_code' => '1404401000',
            'legacy_psgc_code' => '144401000',
            'shape_id' => '30758251B41172258375645',
            'reference_area_ha' => 25251.468510,
        ],
        'Bauko' => [
            'code' => 'BAUKO',
            'aliases' => ['BAUKO'],
            'psgc_code' => '1404402000',
            'legacy_psgc_code' => '144402000',
            'shape_id' => '30758251B51622624666316',
            'reference_area_ha' => 12925.657035,
        ],
        'Besao' => [
            'code' => 'BESAO',
            'aliases' => ['BESAO'],
            'psgc_code' => '1404403000',
            'legacy_psgc_code' => '144403000',
            'shape_id' => '30758251B80716483482108',
            'reference_area_ha' => 13049.868186,
        ],
        'Bontoc' => [
            'code' => 'BONTOC_MOUNTAIN_PROVINCE',
            'aliases' => ['BONTOC_MOUNTAIN_PROVINCE'],
            'psgc_code' => '1404404000',
            'legacy_psgc_code' => '144404000',
            'shape_id' => '30758251B89395664322006',
            'reference_area_ha' => 27988.346009,
            'workspace_name' => 'Bontoc (Mountain Province)',
        ],
        'Natonin' => [
            'code' => 'NATONIN',
            'aliases' => ['NATONIN'],
            'psgc_code' => '1404405000',
            'legacy_psgc_code' => '144405000',
            'shape_id' => '30758251B25528294473690',
            'reference_area_ha' => 35931.256913,
        ],
        'Paracelis' => [
            'code' => 'PARACELIS',
            'aliases' => ['PARACELIS'],
            'psgc_code' => '1404406000',
            'legacy_psgc_code' => '144406000',
            'shape_id' => '30758251B35723308513906',
            'reference_area_ha' => 47132.010086,
        ],
        'Sabangan' => [
            'code' => 'SABANGAN',
            'aliases' => ['SABANGAN'],
            'psgc_code' => '1404407000',
            'legacy_psgc_code' => '144407000',
            'shape_id' => '30758251B87465982516801',
            'reference_area_ha' => 11320.276488,
        ],
        'Sadanga' => [
            'code' => 'SADANGA',
            'aliases' => ['SADANGA'],
            'psgc_code' => '1404408000',
            'legacy_psgc_code' => '144408000',
            'shape_id' => '30758251B98630478643343',
            'reference_area_ha' => 17618.921988,
        ],
        'Sagada' => [
            'code' => 'SAGADA',
            'aliases' => ['SAGADA'],
            'psgc_code' => '1404409000',
            'legacy_psgc_code' => '144409000',
            'shape_id' => '30758251B89881714553238',
            'reference_area_ha' => 6478.496239,
        ],
        'Tadian' => [
            'code' => 'TADIAN',
            'aliases' => ['TADIAN'],
            'psgc_code' => '1404410000',
            'legacy_psgc_code' => '144410000',
            'shape_id' => '30758251B82460503002261',
            'reference_area_ha' => 13553.434849,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Mountain Province', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 10 Mountain Province planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

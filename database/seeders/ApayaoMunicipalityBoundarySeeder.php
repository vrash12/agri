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
class ApayaoMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/apayao_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '9f5a2c4cbe88cadc6f68dbd4c4d2e8803f11b2a4d7e1728ad0d782a217f5aef3';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Calanasan' => [
            'code' => 'CALANASAN',
            'aliases' => ['CALANASAN'],
            'psgc_code' => '1408101000',
            'legacy_psgc_code' => '148101000',
            'shape_id' => '30758251B87608274030040',
            'reference_area_ha' => 136340.986649,
        ],
        'Conner' => [
            'code' => 'CONNER',
            'aliases' => ['CONNER'],
            'psgc_code' => '1408102000',
            'legacy_psgc_code' => '148102000',
            'shape_id' => '30758251B47699313654035',
            'reference_area_ha' => 70414.978952,
        ],
        'Flora' => [
            'code' => 'FLORA',
            'aliases' => ['FLORA'],
            'psgc_code' => '1408103000',
            'legacy_psgc_code' => '148103000',
            'shape_id' => '30758251B46507023603646',
            'reference_area_ha' => 24374.057626,
        ],
        'Kabugao' => [
            'code' => 'KABUGAO',
            'aliases' => ['KABUGAO'],
            'psgc_code' => '1408104000',
            'legacy_psgc_code' => '148104000',
            'shape_id' => '30758251B69063872929594',
            'reference_area_ha' => 98967.918393,
        ],
        'Luna' => [
            'code' => 'LUNA_APAYAO',
            'aliases' => ['LUNA_APAYAO'],
            'psgc_code' => '1408105000',
            'legacy_psgc_code' => '148105000',
            'shape_id' => '30758251B75623612075236',
            'reference_area_ha' => 32065.787931,
            'workspace_name' => 'Luna (Apayao)',
        ],
        'Pudtol' => [
            'code' => 'PUDTOL',
            'aliases' => ['PUDTOL'],
            'psgc_code' => '1408106000',
            'legacy_psgc_code' => '148106000',
            'shape_id' => '30758251B84586369455003',
            'reference_area_ha' => 30226.135635,
        ],
        'Santa Marcela' => [
            'code' => 'SANTA_MARCELA',
            'aliases' => ['SANTA_MARCELA'],
            'psgc_code' => '1408107000',
            'legacy_psgc_code' => '148107000',
            'shape_id' => '30758251B35080960553319',
            'reference_area_ha' => 6491.518809,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Apayao', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 7 Apayao planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Negros Island Region planning references; run explicitly after a backup.
 * Bacolod has a separate scope. Existing workspaces are never silently transferred.
 * Sources and PSGC transitions: docs/NEGROS_ISLAND_BOUNDARY_SOURCES.md.
 */
class SiquijorMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/siquijor_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '45cffb5171c27ddc9b942ee32ee971f7aff13adaf7d2cc8613fa7cb036497e37';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Enrique Villanueva' => [
            'code' => 'ENRIQUE_VILLANUEVA',
            'aliases' => ['ENRIQUE_VILLANUEVA', 'ENRIQUEVILLANUEVA', 'ENRIQUE-VILLANUEVA', '0706101000'],
            'psgc_code' => '1806101000',
            'legacy_psgc_code' => '076101000',
            'shape_id' => '30758251B12637401805285',
            'reference_area_ha' => 2629.750201,
        ],
        'Larena' => [
            'code' => 'LARENA',
            'aliases' => ['LARENA', '0706102000'],
            'psgc_code' => '1806102000',
            'legacy_psgc_code' => '076102000',
            'shape_id' => '30758251B58465916430151',
            'reference_area_ha' => 4108.440665,
        ],
        'Lazi' => [
            'code' => 'LAZI',
            'aliases' => ['LAZI', '0706103000'],
            'psgc_code' => '1806103000',
            'legacy_psgc_code' => '076103000',
            'shape_id' => '30758251B49075027129395',
            'reference_area_ha' => 7047.317244,
        ],
        'Maria' => [
            'code' => 'MARIA',
            'aliases' => ['MARIA', '0706104000'],
            'psgc_code' => '1806104000',
            'legacy_psgc_code' => '076104000',
            'shape_id' => '30758251B45396932451822',
            'reference_area_ha' => 5664.253478,
        ],
        'San Juan' => [
            'code' => 'SAN_JUAN',
            'aliases' => ['SAN_JUAN', 'SANJUAN', 'SAN-JUAN', '0706105000'],
            'psgc_code' => '1806105000',
            'legacy_psgc_code' => '076105000',
            'shape_id' => '30758251B12332276302021',
            'reference_area_ha' => 4024.321526,
        ],
        'Siquijor' => [
            'code' => 'SIQUIJOR',
            'aliases' => ['SIQUIJOR', '0706106000'],
            'psgc_code' => '1806106000',
            'legacy_psgc_code' => '076106000',
            'shape_id' => '30758251B71387884042840',
            'reference_area_ha' => 8483.689999,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Siquijor', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 6 Siquijor planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

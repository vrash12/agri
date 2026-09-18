<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Municipality planning references for Siquijor, part of the Negros Island Region.
 *
 * The island province's six municipalities. The municipality of Siquijor shares its
 * name with the province; the workspace keeps the municipality name.
 *
 * These are approximate planning boundaries from geoBoundaries, not cadastral or
 * survey-grade lines, and they require LGU or NAMRIA verification before official use.
 */
class SiquijorMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/siquijor_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '5338e3a60ca84516019321dfe056a0fc352c15ffbc38a4feff3dcd423c8e2a36';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Enrique Villanueva' => [
            'code' => 'ENRIQUE_VILLANUEVA',
            'aliases' => ['ENRIQUE_VILLANUEVA', 'ENRIQUEVILLANUEVA', 'ENRIQUE-VILLANUEVA'],
            'psgc_code' => '0706101000',
            'legacy_psgc_code' => '076101000',
            'shape_id' => '30758251B12637401805285',
            'reference_area_ha' => 2629.750201,
        ],
        'Larena' => [
            'code' => 'LARENA',
            'aliases' => ['LARENA'],
            'psgc_code' => '0706102000',
            'legacy_psgc_code' => '076102000',
            'shape_id' => '30758251B58465916430151',
            'reference_area_ha' => 4108.440665,
        ],
        'Lazi' => [
            'code' => 'LAZI',
            'aliases' => ['LAZI'],
            'psgc_code' => '0706103000',
            'legacy_psgc_code' => '076103000',
            'shape_id' => '30758251B49075027129395',
            'reference_area_ha' => 7047.317244,
        ],
        'Maria' => [
            'code' => 'MARIA',
            'aliases' => ['MARIA'],
            'psgc_code' => '0706104000',
            'legacy_psgc_code' => '076104000',
            'shape_id' => '30758251B45396932451822',
            'reference_area_ha' => 5664.253478,
        ],
        'San Juan' => [
            'code' => 'SAN_JUAN',
            'aliases' => ['SAN_JUAN', 'SANJUAN', 'SAN-JUAN'],
            'psgc_code' => '0706105000',
            'legacy_psgc_code' => '076105000',
            'shape_id' => '30758251B12332276302021',
            'reference_area_ha' => 4024.321526,
        ],
        'Siquijor' => [
            'code' => 'SIQUIJOR',
            'aliases' => ['SIQUIJOR'],
            'psgc_code' => '0706106000',
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

        $this->command?->info('Ready: the 6 Siquijor planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

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
class KalingaMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/kalinga_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '1e2833121bbbc00d5f94b1dd338cf6b0fed8f4c9c2a40f5e80eea64b57eb67be';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Balbalan' => [
            'code' => 'BALBALAN',
            'aliases' => ['BALBALAN'],
            'psgc_code' => '1403201000',
            'legacy_psgc_code' => '143201000',
            'shape_id' => '30758251B68760581282795',
            'reference_area_ha' => 63197.490358,
        ],
        'Lubuagan' => [
            'code' => 'LUBUAGAN',
            'aliases' => ['LUBUAGAN'],
            'psgc_code' => '1403206000',
            'legacy_psgc_code' => '143206000',
            'shape_id' => '30758251B13511507037260',
            'reference_area_ha' => 6243.021907,
        ],
        'Pasil' => [
            'code' => 'PASIL',
            'aliases' => ['PASIL'],
            'psgc_code' => '1403208000',
            'legacy_psgc_code' => '143208000',
            'shape_id' => '30758251B66518683632982',
            'reference_area_ha' => 33702.690987,
        ],
        'Pinukpuk' => [
            'code' => 'PINUKPUK',
            'aliases' => ['PINUKPUK'],
            'psgc_code' => '1403209000',
            'legacy_psgc_code' => '143209000',
            'shape_id' => '30758251B26297568532116',
            'reference_area_ha' => 36613.426858,
        ],
        'Rizal' => [
            'code' => 'RIZAL_KALINGA',
            'aliases' => ['RIZAL_KALINGA'],
            'psgc_code' => '1403211000',
            'legacy_psgc_code' => '143211000',
            'shape_id' => '30758251B32653531312940',
            'reference_area_ha' => 18478.876189,
            'workspace_name' => 'Rizal (Kalinga)',
        ],
        'City of Tabuk' => [
            'code' => 'TABUK_CITY',
            'aliases' => ['TABUK_CITY'],
            'psgc_code' => '1403213000',
            'legacy_psgc_code' => '143213000',
            'shape_id' => '30758251B17662801564014',
            'reference_area_ha' => 68984.219210,
            'workspace_name' => 'Tabuk City',
        ],
        'Tanudan' => [
            'code' => 'TANUDAN',
            'aliases' => ['TANUDAN'],
            'psgc_code' => '1403214000',
            'legacy_psgc_code' => '143214000',
            'shape_id' => '30758251B98776308730929',
            'reference_area_ha' => 33895.025441,
        ],
        'Tinglayan' => [
            'code' => 'TINGLAYAN',
            'aliases' => ['TINGLAYAN'],
            'psgc_code' => '1403215000',
            'legacy_psgc_code' => '143215000',
            'shape_id' => '30758251B33701077243030',
            'reference_area_ha' => 21394.019836,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Kalinga', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 8 Kalinga planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

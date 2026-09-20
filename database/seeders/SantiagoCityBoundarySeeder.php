<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Region II planning references; run explicitly after a database backup.
 *
 * Source identities and separate Santiago scope: docs/REGION_II_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class SantiagoCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/santiago_city_reference_boundary.geojson';

    private const SOURCE_CHECKSUM = '6cf8223d7737a898f626f012429bbbbfa024fcaa82b51864231834cfaca2276c';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'City of Santiago' => [
            'code' => 'SANTIAGO_CITY',
            'aliases' => ['SANTIAGO_CITY'],
            'psgc_code' => '0203135000',
            'legacy_psgc_code' => '023135000',
            'shape_id' => '30758251B59985488099372',
            'reference_area_ha' => 15181.653930,
            'workspace_name' => 'Santiago City',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Santiago City', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 1 Santiago City planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned MIMAROPA planning references; run explicitly after a database backup.
 *
 * Source identities and separate Puerto Princesa scope: docs/MIMAROPA_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class PuertoPrincesaCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/puerto_princesa_city_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '580752310e4a8126120eb5db4dee6f2b02864e6615613f060a69fd96fafb9ec1';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Puerto Princesa City' => [
            'code' => 'PUERTO_PRINCESA_CITY',
            'aliases' => ['PUERTO_PRINCESA_CITY'],
            'psgc_code' => '1731500000',
            'legacy_psgc_code' => '175316000',
            'shape_id' => '30758251B64178272348738',
            'reference_area_ha' => 216861.645720,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Puerto Princesa City', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 1 Puerto Princesa City planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

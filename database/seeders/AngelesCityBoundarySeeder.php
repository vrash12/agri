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
class AngelesCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/angeles_city_reference_boundary.geojson';

    private const SOURCE_CHECKSUM = '952a73d010dc386110ed9553f529c190aa92c367afd638391e4180598885ba81';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Angeles City' => [
            'code' => 'ANGELES_CITY',
            'aliases' => ['ANGELES_CITY'],
            'psgc_code' => '0330100000',
            'legacy_psgc_code' => '035401000',
            'shape_id' => '30758251B17687282333707',
            'reference_area_ha' => 12102.898748,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Angeles City', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 1 Angeles City planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

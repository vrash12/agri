<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned CALABARZON planning references; run explicitly after a database backup.
 *
 * Source identities and separate Lucena scope: docs/CALABARZON_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class LucenaCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/lucena_city_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'c698115ef8f1ad722c6af53e7ea525394775b3467ce94f07cc8b2a7e38703532';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Lucena City' => [
            'code' => 'LUCENA_CITY',
            'aliases' => ['LUCENA_CITY'],
            'psgc_code' => '0431200000',
            'legacy_psgc_code' => '045624000',
            'shape_id' => '30758251B97555965649214',
            'reference_area_ha' => 8359.174042,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Lucena City', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 1 Lucena City planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

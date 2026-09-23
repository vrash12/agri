<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Bicol planning references; run explicitly after a database backup.
 *
 * Source identities and separate Naga City scope: docs/BICOL_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class NagaCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/naga_city_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '4ab9ffbc339e475c659508f3b2a0942bfbf535fadec1cad0904c8a2cf7b95ad9';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Naga City' => [
            'code' => 'NAGA_CITY',
            'aliases' => ['NAGA_CITY'],
            'psgc_code' => '0501724000',
            'legacy_psgc_code' => '051724000',
            'shape_id' => '30758251B67238532990437',
            'reference_area_ha' => 7979.942096,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Naga City', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 1 Naga City planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

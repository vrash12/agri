<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Negros Island Region planning references; run explicitly after a backup.
 * Bacolod has a separate scope. Existing workspaces are never silently transferred.
 * Sources and PSGC transitions: docs/NEGROS_ISLAND_BOUNDARY_SOURCES.md.
 */
class BacolodCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/bacolod_city_reference_boundary.geojson';

    private const SOURCE_CHECKSUM = '063adfbef92b4403ae76146f6e7d138203fbbf8fc373ce22064a14e811512d20';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bacolod City' => [
            'code' => 'BACOLOD_CITY',
            'aliases' => ['BACOLOD_CITY', 'BACOLODCITY', 'BACOLOD-CITY', '0630200000'],
            'psgc_code' => '1830200000',
            'legacy_psgc_code' => '064501000',
            'shape_id' => '30758251B10359126771558',
            'reference_area_ha' => 16237.786580,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Bacolod City', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 1 Bacolod City planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

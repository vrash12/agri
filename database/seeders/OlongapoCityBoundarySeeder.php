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
class OlongapoCityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/olongapo_city_reference_boundary.geojson';

    private const SOURCE_CHECKSUM = '0b476bc12d800493d87c7a035726a4868ef5fdfc44370353f1bc10bd9d5ab2de';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Olongapo City' => [
            'code' => 'OLONGAPO_CITY',
            'aliases' => ['OLONGAPO_CITY'],
            'psgc_code' => '0331400000',
            'legacy_psgc_code' => '037107000',
            'shape_id' => '30758251B1579745133837',
            'reference_area_ha' => 14109.045321,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Olongapo City', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 1 Olongapo City planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

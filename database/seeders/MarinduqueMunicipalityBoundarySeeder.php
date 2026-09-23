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
class MarinduqueMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/marinduque_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '32896a9f2b2255121bfe4739c756816df8f05785d98ea4e6c9712cc3285fca06';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Boac' => [
            'code' => 'BOAC',
            'aliases' => ['BOAC'],
            'psgc_code' => '1704001000',
            'legacy_psgc_code' => '174001000',
            'shape_id' => '30758251B94760060482549',
            'reference_area_ha' => 23121.322468,
        ],
        'Buenavista' => [
            'code' => 'BUENAVISTA_MARINDUQUE',
            'aliases' => ['BUENAVISTA_MARINDUQUE'],
            'psgc_code' => '1704002000',
            'legacy_psgc_code' => '174002000',
            'shape_id' => '30758251B40445123670017',
            'reference_area_ha' => 7669.642795,
            'workspace_name' => 'Buenavista (Marinduque)',
        ],
        'Gasan' => [
            'code' => 'GASAN',
            'aliases' => ['GASAN'],
            'psgc_code' => '1704003000',
            'legacy_psgc_code' => '174003000',
            'shape_id' => '30758251B69905180384114',
            'reference_area_ha' => 9936.991804,
        ],
        'Mogpog' => [
            'code' => 'MOGPOG',
            'aliases' => ['MOGPOG'],
            'psgc_code' => '1704004000',
            'legacy_psgc_code' => '174004000',
            'shape_id' => '30758251B75724413863333',
            'reference_area_ha' => 9908.761749,
        ],
        'Santa Cruz' => [
            'code' => 'SANTA_CRUZ_MARINDUQUE',
            'aliases' => ['SANTA_CRUZ_MARINDUQUE'],
            'psgc_code' => '1704005000',
            'legacy_psgc_code' => '174005000',
            'shape_id' => '30758251B46699269491955',
            'reference_area_ha' => 24247.865748,
            'workspace_name' => 'Santa Cruz (Marinduque)',
        ],
        'Torrijos' => [
            'code' => 'TORRIJOS',
            'aliases' => ['TORRIJOS'],
            'psgc_code' => '1704006000',
            'legacy_psgc_code' => '174006000',
            'shape_id' => '30758251B44137486572010',
            'reference_area_ha' => 17357.701250,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Marinduque', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 6 Marinduque planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

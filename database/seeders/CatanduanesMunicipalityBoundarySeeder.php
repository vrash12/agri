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
class CatanduanesMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/catanduanes_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'd7ac47ec887c0f00b0e500ae1622e139a32d1e7059e88308dc7af6fd699279ea';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bagamanoc' => [
            'code' => 'BAGAMANOC',
            'aliases' => ['BAGAMANOC'],
            'psgc_code' => '0502001000',
            'legacy_psgc_code' => '052001000',
            'shape_id' => '30758251B59142853450865',
            'reference_area_ha' => 6828.710818,
        ],
        'Baras' => [
            'code' => 'BARAS_CATANDUANES',
            'aliases' => ['BARAS_CATANDUANES'],
            'psgc_code' => '0502002000',
            'legacy_psgc_code' => '052002000',
            'shape_id' => '30758251B70953932532311',
            'reference_area_ha' => 7114.354417,
            'workspace_name' => 'Baras (Catanduanes)',
        ],
        'Bato' => [
            'code' => 'BATO_CATANDUANES',
            'aliases' => ['BATO_CATANDUANES'],
            'psgc_code' => '0502003000',
            'legacy_psgc_code' => '052003000',
            'shape_id' => '30758251B781054791526',
            'reference_area_ha' => 5197.016556,
            'workspace_name' => 'Bato (Catanduanes)',
        ],
        'Caramoran' => [
            'code' => 'CARAMORAN',
            'aliases' => ['CARAMORAN'],
            'psgc_code' => '0502004000',
            'legacy_psgc_code' => '052004000',
            'shape_id' => '30758251B83263319405118',
            'reference_area_ha' => 28157.588321,
        ],
        'Gigmoto' => [
            'code' => 'GIGMOTO',
            'aliases' => ['GIGMOTO'],
            'psgc_code' => '0502005000',
            'legacy_psgc_code' => '052005000',
            'shape_id' => '30758251B49692104133170',
            'reference_area_ha' => 10605.812342,
        ],
        'Pandan' => [
            'code' => 'PANDAN_CATANDUANES',
            'aliases' => ['PANDAN_CATANDUANES'],
            'psgc_code' => '0502006000',
            'legacy_psgc_code' => '052006000',
            'shape_id' => '30758251B58304777640417',
            'reference_area_ha' => 10608.918973,
            'workspace_name' => 'Pandan (Catanduanes)',
        ],
        'Panganiban' => [
            'code' => 'PANGANIBAN',
            'aliases' => ['PANGANIBAN'],
            'psgc_code' => '0502007000',
            'legacy_psgc_code' => '052007000',
            'shape_id' => '30758251B52035813830347',
            'reference_area_ha' => 5060.115522,
        ],
        'San Andres' => [
            'code' => 'SAN_ANDRES_CATANDUANES',
            'aliases' => ['SAN_ANDRES_CATANDUANES'],
            'psgc_code' => '0502008000',
            'legacy_psgc_code' => '052008000',
            'shape_id' => '30758251B65152519595273',
            'reference_area_ha' => 17922.188580,
            'workspace_name' => 'San Andres (Catanduanes)',
        ],
        'San Miguel' => [
            'code' => 'SAN_MIGUEL_CATANDUANES',
            'aliases' => ['SAN_MIGUEL_CATANDUANES'],
            'psgc_code' => '0502009000',
            'legacy_psgc_code' => '052009000',
            'shape_id' => '30758251B81030660303746',
            'reference_area_ha' => 24291.162945,
            'workspace_name' => 'San Miguel (Catanduanes)',
        ],
        'Viga' => [
            'code' => 'VIGA',
            'aliases' => ['VIGA'],
            'psgc_code' => '0502010000',
            'legacy_psgc_code' => '052010000',
            'shape_id' => '30758251B12930649127475',
            'reference_area_ha' => 16827.853077,
        ],
        'Virac' => [
            'code' => 'VIRAC',
            'aliases' => ['VIRAC'],
            'psgc_code' => '0502011000',
            'legacy_psgc_code' => '052011000',
            'shape_id' => '30758251B19106004120834',
            'reference_area_ha' => 13441.707516,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Catanduanes', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 11 Catanduanes planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

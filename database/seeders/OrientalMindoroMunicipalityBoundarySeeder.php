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
class OrientalMindoroMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/oriental_mindoro_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'f72c536a8d2e028371c952ed72bb76bacd25760398fba6325a810ac52cfa0e24';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Baco' => [
            'code' => 'BACO',
            'aliases' => ['BACO'],
            'psgc_code' => '1705201000',
            'legacy_psgc_code' => '175201000',
            'shape_id' => '30758251B69479050195003',
            'reference_area_ha' => 43502.156395,
        ],
        'Bansud' => [
            'code' => 'BANSUD',
            'aliases' => ['BANSUD'],
            'psgc_code' => '1705202000',
            'legacy_psgc_code' => '175202000',
            'shape_id' => '30758251B27476294786678',
            'reference_area_ha' => 34537.195253,
        ],
        'Bongabong' => [
            'code' => 'BONGABONG',
            'aliases' => ['BONGABONG'],
            'psgc_code' => '1705203000',
            'legacy_psgc_code' => '175203000',
            'shape_id' => '30758251B15501509529895',
            'reference_area_ha' => 39608.612481,
        ],
        'Bulalacao' => [
            'code' => 'BULALACAO',
            'aliases' => ['BULALACAO'],
            'psgc_code' => '1705204000',
            'legacy_psgc_code' => '175204000',
            'shape_id' => '30758251B89806252080387',
            'reference_area_ha' => 35280.613662,
        ],
        'City of Calapan' => [
            'code' => 'CALAPAN_CITY',
            'aliases' => ['CALAPAN_CITY'],
            'psgc_code' => '1705205000',
            'legacy_psgc_code' => '175205000',
            'shape_id' => '30758251B77350797414463',
            'reference_area_ha' => 18379.718916,
            'workspace_name' => 'Calapan City',
        ],
        'Gloria' => [
            'code' => 'GLORIA',
            'aliases' => ['GLORIA'],
            'psgc_code' => '1705206000',
            'legacy_psgc_code' => '175206000',
            'shape_id' => '30758251B4033826093725',
            'reference_area_ha' => 25609.811805,
        ],
        'Mansalay' => [
            'code' => 'MANSALAY',
            'aliases' => ['MANSALAY'],
            'psgc_code' => '1705207000',
            'legacy_psgc_code' => '175207000',
            'shape_id' => '30758251B13685654422788',
            'reference_area_ha' => 45438.692302,
        ],
        'Naujan' => [
            'code' => 'NAUJAN',
            'aliases' => ['NAUJAN'],
            'psgc_code' => '1705208000',
            'legacy_psgc_code' => '175208000',
            'shape_id' => '30758251B73486212842201',
            'reference_area_ha' => 57474.197778,
        ],
        'Pinamalayan' => [
            'code' => 'PINAMALAYAN',
            'aliases' => ['PINAMALAYAN'],
            'psgc_code' => '1705209000',
            'legacy_psgc_code' => '175209000',
            'shape_id' => '30758251B9076432182480',
            'reference_area_ha' => 16776.911553,
        ],
        'Pola' => [
            'code' => 'POLA',
            'aliases' => ['POLA'],
            'psgc_code' => '1705210000',
            'legacy_psgc_code' => '175210000',
            'shape_id' => '30758251B5257459344407',
            'reference_area_ha' => 16839.635544,
        ],
        'Puerto Galera' => [
            'code' => 'PUERTO_GALERA',
            'aliases' => ['PUERTO_GALERA'],
            'psgc_code' => '1705211000',
            'legacy_psgc_code' => '175211000',
            'shape_id' => '30758251B54215534625427',
            'reference_area_ha' => 10936.276464,
        ],
        'Roxas' => [
            'code' => 'ROXAS_ORIENTAL_MINDORO',
            'aliases' => ['ROXAS_ORIENTAL_MINDORO'],
            'psgc_code' => '1705212000',
            'legacy_psgc_code' => '175212000',
            'shape_id' => '30758251B86973741519598',
            'reference_area_ha' => 10384.176116,
            'workspace_name' => 'Roxas (Oriental Mindoro)',
        ],
        'San Teodoro' => [
            'code' => 'SAN_TEODORO',
            'aliases' => ['SAN_TEODORO'],
            'psgc_code' => '1705213000',
            'legacy_psgc_code' => '175213000',
            'shape_id' => '30758251B90204819305655',
            'reference_area_ha' => 15604.458430,
        ],
        'Socorro' => [
            'code' => 'SOCORRO_ORIENTAL_MINDORO',
            'aliases' => ['SOCORRO_ORIENTAL_MINDORO'],
            'psgc_code' => '1705214000',
            'legacy_psgc_code' => '175214000',
            'shape_id' => '30758251B49339662744818',
            'reference_area_ha' => 20484.100142,
            'workspace_name' => 'Socorro (Oriental Mindoro)',
        ],
        'Victoria' => [
            'code' => 'VICTORIA_ORIENTAL_MINDORO',
            'aliases' => ['VICTORIA_ORIENTAL_MINDORO'],
            'psgc_code' => '1705215000',
            'legacy_psgc_code' => '175215000',
            'shape_id' => '30758251B49564856626223',
            'reference_area_ha' => 20669.926157,
            'workspace_name' => 'Victoria (Oriental Mindoro)',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Oriental Mindoro', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 15 Oriental Mindoro planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

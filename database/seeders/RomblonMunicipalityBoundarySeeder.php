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
class RomblonMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/romblon_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '062845a3e98c75f492e5ce30386511c771e49304f9048a7027eafa9ac8168d53';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Alcantara' => [
            'code' => 'ALCANTARA_ROMBLON',
            'aliases' => ['ALCANTARA_ROMBLON'],
            'psgc_code' => '1705901000',
            'legacy_psgc_code' => '175901000',
            'shape_id' => '30758251B13764061513438',
            'reference_area_ha' => 7047.784828,
            'workspace_name' => 'Alcantara (Romblon)',
        ],
        'Banton' => [
            'code' => 'BANTON',
            'aliases' => ['BANTON'],
            'psgc_code' => '1705902000',
            'legacy_psgc_code' => '175902000',
            'shape_id' => '30758251B55331406144814',
            'reference_area_ha' => 2861.927264,
        ],
        'Cajidiocan' => [
            'code' => 'CAJIDIOCAN',
            'aliases' => ['CAJIDIOCAN'],
            'psgc_code' => '1705903000',
            'legacy_psgc_code' => '175903000',
            'shape_id' => '30758251B60223458934501',
            'reference_area_ha' => 12749.379698,
        ],
        'Calatrava' => [
            'code' => 'CALATRAVA_ROMBLON',
            'aliases' => ['CALATRAVA_ROMBLON'],
            'psgc_code' => '1705904000',
            'legacy_psgc_code' => '175904000',
            'shape_id' => '30758251B34852860389402',
            'reference_area_ha' => 3802.882016,
            'workspace_name' => 'Calatrava (Romblon)',
        ],
        'Concepcion' => [
            'code' => 'CONCEPCION_ROMBLON',
            'aliases' => ['CONCEPCION_ROMBLON'],
            'psgc_code' => '1705905000',
            'legacy_psgc_code' => '175905000',
            'shape_id' => '30758251B24706592587585',
            'reference_area_ha' => 2039.432332,
            'workspace_name' => 'Concepcion (Romblon)',
        ],
        'Corcuera' => [
            'code' => 'CORCUERA',
            'aliases' => ['CORCUERA'],
            'psgc_code' => '1705906000',
            'legacy_psgc_code' => '175906000',
            'shape_id' => '30758251B41265366730611',
            'reference_area_ha' => 2047.258798,
        ],
        'Looc' => [
            'code' => 'LOOC_ROMBLON',
            'aliases' => ['LOOC_ROMBLON'],
            'psgc_code' => '1705907000',
            'legacy_psgc_code' => '175907000',
            'shape_id' => '30758251B76752924951322',
            'reference_area_ha' => 8148.327167,
            'workspace_name' => 'Looc (Romblon)',
        ],
        'Magdiwang' => [
            'code' => 'MAGDIWANG',
            'aliases' => ['MAGDIWANG'],
            'psgc_code' => '1705908000',
            'legacy_psgc_code' => '175908000',
            'shape_id' => '30758251B40201197939052',
            'reference_area_ha' => 7896.910252,
        ],
        'Odiongan' => [
            'code' => 'ODIONGAN',
            'aliases' => ['ODIONGAN'],
            'psgc_code' => '1705909000',
            'legacy_psgc_code' => '175909000',
            'shape_id' => '30758251B56543042574103',
            'reference_area_ha' => 13659.593341,
        ],
        'Romblon' => [
            'code' => 'ROMBLON_ROMBLON',
            'aliases' => ['ROMBLON_ROMBLON'],
            'psgc_code' => '1705910000',
            'legacy_psgc_code' => '175910000',
            'shape_id' => '30758251B85981656551245',
            'reference_area_ha' => 8846.922376,
            'workspace_name' => 'Romblon (Romblon)',
        ],
        'San Agustin' => [
            'code' => 'SAN_AGUSTIN_ROMBLON',
            'aliases' => ['SAN_AGUSTIN_ROMBLON'],
            'psgc_code' => '1705911000',
            'legacy_psgc_code' => '175911000',
            'shape_id' => '30758251B77746184637785',
            'reference_area_ha' => 10218.284725,
            'workspace_name' => 'San Agustin (Romblon)',
        ],
        'San Andres' => [
            'code' => 'SAN_ANDRES_ROMBLON',
            'aliases' => ['SAN_ANDRES_ROMBLON'],
            'psgc_code' => '1705912000',
            'legacy_psgc_code' => '175912000',
            'shape_id' => '30758251B27093145704117',
            'reference_area_ha' => 10258.246587,
            'workspace_name' => 'San Andres (Romblon)',
        ],
        'San Fernando' => [
            'code' => 'SAN_FERNANDO_ROMBLON',
            'aliases' => ['SAN_FERNANDO_ROMBLON'],
            'psgc_code' => '1705913000',
            'legacy_psgc_code' => '175913000',
            'shape_id' => '30758251B454076707789',
            'reference_area_ha' => 24457.049307,
            'workspace_name' => 'San Fernando (Romblon)',
        ],
        'San Jose' => [
            'code' => 'SAN_JOSE_ROMBLON',
            'aliases' => ['SAN_JOSE_ROMBLON'],
            'psgc_code' => '1705914000',
            'legacy_psgc_code' => '175914000',
            'shape_id' => '30758251B67956852885073',
            'reference_area_ha' => 2807.683092,
            'workspace_name' => 'San Jose (Romblon)',
        ],
        'Santa Fe' => [
            'code' => 'SANTA_FE_ROMBLON',
            'aliases' => ['SANTA_FE_ROMBLON'],
            'psgc_code' => '1705915000',
            'legacy_psgc_code' => '175915000',
            'shape_id' => '30758251B97605936547347',
            'reference_area_ha' => 5652.595180,
            'workspace_name' => 'Santa Fe (Romblon)',
        ],
        'Ferrol' => [
            'code' => 'FERROL',
            'aliases' => ['FERROL'],
            'psgc_code' => '1705916000',
            'legacy_psgc_code' => '175916000',
            'shape_id' => '30758251B18326457927940',
            'reference_area_ha' => 3468.742409,
        ],
        'Santa Maria' => [
            'code' => 'SANTA_MARIA_ROMBLON',
            'aliases' => ['SANTA_MARIA_ROMBLON'],
            'psgc_code' => '1705917000',
            'legacy_psgc_code' => '175917000',
            'shape_id' => '30758251B99723317147835',
            'reference_area_ha' => 5004.976271,
            'workspace_name' => 'Santa Maria (Romblon)',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Romblon', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 17 Romblon planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

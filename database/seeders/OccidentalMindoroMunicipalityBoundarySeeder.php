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
class OccidentalMindoroMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/occidental_mindoro_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '450167f4422dbc9d9ff9433140d2f16957a1da7ca04fd1a1a5df0f99e4c7e99c';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Abra de Ilog' => [
            'code' => 'ABRA_DE_ILOG',
            'aliases' => ['ABRA_DE_ILOG'],
            'psgc_code' => '1705101000',
            'legacy_psgc_code' => '175101000',
            'shape_id' => '30758251B36470920271092',
            'reference_area_ha' => 60191.391706,
        ],
        'Calintaan' => [
            'code' => 'CALINTAAN',
            'aliases' => ['CALINTAAN'],
            'psgc_code' => '1705102000',
            'legacy_psgc_code' => '175102000',
            'shape_id' => '30758251B26098626095065',
            'reference_area_ha' => 29885.919437,
        ],
        'Looc' => [
            'code' => 'LOOC_OCCIDENTAL_MINDORO',
            'aliases' => ['LOOC_OCCIDENTAL_MINDORO'],
            'psgc_code' => '1705103000',
            'legacy_psgc_code' => '175103000',
            'shape_id' => '30758251B31470126644095',
            'reference_area_ha' => 12924.750313,
            'workspace_name' => 'Looc (Occidental Mindoro)',
        ],
        'Lubang' => [
            'code' => 'LUBANG',
            'aliases' => ['LUBANG'],
            'psgc_code' => '1705104000',
            'legacy_psgc_code' => '175104000',
            'shape_id' => '30758251B11611856867349',
            'reference_area_ha' => 12527.255279,
        ],
        'Magsaysay' => [
            'code' => 'MAGSAYSAY_OCCIDENTAL_MINDORO',
            'aliases' => ['MAGSAYSAY_OCCIDENTAL_MINDORO'],
            'psgc_code' => '1705105000',
            'legacy_psgc_code' => '175105000',
            'shape_id' => '30758251B37919333607414',
            'reference_area_ha' => 26031.125886,
            'workspace_name' => 'Magsaysay (Occidental Mindoro)',
        ],
        'Mamburao' => [
            'code' => 'MAMBURAO',
            'aliases' => ['MAMBURAO'],
            'psgc_code' => '1705106000',
            'legacy_psgc_code' => '175106000',
            'shape_id' => '30758251B70283159165752',
            'reference_area_ha' => 32074.756334,
        ],
        'Paluan' => [
            'code' => 'PALUAN',
            'aliases' => ['PALUAN'],
            'psgc_code' => '1705107000',
            'legacy_psgc_code' => '175107000',
            'shape_id' => '30758251B4069170927747',
            'reference_area_ha' => 52935.601189,
        ],
        'Rizal' => [
            'code' => 'RIZAL_OCCIDENTAL_MINDORO',
            'aliases' => ['RIZAL_OCCIDENTAL_MINDORO'],
            'psgc_code' => '1705108000',
            'legacy_psgc_code' => '175108000',
            'shape_id' => '30758251B74613815178222',
            'reference_area_ha' => 30379.227300,
            'workspace_name' => 'Rizal (Occidental Mindoro)',
        ],
        'Sablayan' => [
            'code' => 'SABLAYAN',
            'aliases' => ['SABLAYAN'],
            'psgc_code' => '1705109000',
            'legacy_psgc_code' => '175109000',
            'shape_id' => '30758251B34022252316389',
            'reference_area_ha' => 210342.573141,
        ],
        'San Jose' => [
            'code' => 'SAN_JOSE_OCCIDENTAL_MINDORO',
            'aliases' => ['SAN_JOSE_OCCIDENTAL_MINDORO'],
            'psgc_code' => '1705110000',
            'legacy_psgc_code' => '175110000',
            'shape_id' => '30758251B733109742349',
            'reference_area_ha' => 61071.709123,
            'workspace_name' => 'San Jose (Occidental Mindoro)',
        ],
        'Santa Cruz' => [
            'code' => 'SANTA_CRUZ_OCCIDENTAL_MINDORO',
            'aliases' => ['SANTA_CRUZ_OCCIDENTAL_MINDORO'],
            'psgc_code' => '1705111000',
            'legacy_psgc_code' => '175111000',
            'shape_id' => '30758251B4413725625416',
            'reference_area_ha' => 68864.325145,
            'workspace_name' => 'Santa Cruz (Occidental Mindoro)',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Occidental Mindoro', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 11 Occidental Mindoro planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

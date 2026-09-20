<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Region II planning references; run explicitly after a database backup.
 *
 * Source identities and separate Santiago scope: docs/REGION_II_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class BatanesMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/batanes_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '433934117b9343276a6ff54fb05dedeeded7bfb3cbfe81b074ebba95fe108f93';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Basco' => [
            'code' => 'BASCO',
            'aliases' => ['BASCO'],
            'psgc_code' => '0200901000',
            'legacy_psgc_code' => '020901000',
            'shape_id' => '30758251B45522051354426',
            'reference_area_ha' => 3349.361020,
        ],
        'Itbayat' => [
            'code' => 'ITBAYAT',
            'aliases' => ['ITBAYAT'],
            'psgc_code' => '0200902000',
            'legacy_psgc_code' => '020902000',
            'shape_id' => '30758251B11360025127524',
            'reference_area_ha' => 8931.860713,
        ],
        'Ivana' => [
            'code' => 'IVANA',
            'aliases' => ['IVANA'],
            'psgc_code' => '0200903000',
            'legacy_psgc_code' => '020903000',
            'shape_id' => '30758251B76768461289658',
            'reference_area_ha' => 1470.485025,
        ],
        'Mahatao' => [
            'code' => 'MAHATAO',
            'aliases' => ['MAHATAO'],
            'psgc_code' => '0200904000',
            'legacy_psgc_code' => '020904000',
            'shape_id' => '30758251B69383770374215',
            'reference_area_ha' => 1156.081879,
        ],
        'Sabtang' => [
            'code' => 'SABTANG',
            'aliases' => ['SABTANG'],
            'psgc_code' => '0200905000',
            'legacy_psgc_code' => '020905000',
            'shape_id' => '30758251B59177504805395',
            'reference_area_ha' => 4098.589461,
        ],
        'Uyugan' => [
            'code' => 'UYUGAN',
            'aliases' => ['UYUGAN'],
            'psgc_code' => '0200906000',
            'legacy_psgc_code' => '020906000',
            'shape_id' => '30758251B6116437750205',
            'reference_area_ha' => 1131.104016,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Batanes', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 6 Batanes planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

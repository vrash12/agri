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
class SorsogonMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/sorsogon_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '4600fea72e7b055363b03957840ef44f6402ca81470e884eeed779b438658480';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Barcelona' => [
            'code' => 'BARCELONA',
            'aliases' => ['BARCELONA'],
            'psgc_code' => '0506202000',
            'legacy_psgc_code' => '056202000',
            'shape_id' => '30758251B63978156729606',
            'reference_area_ha' => 5965.510453,
        ],
        'Bulan' => [
            'code' => 'BULAN',
            'aliases' => ['BULAN'],
            'psgc_code' => '0506203000',
            'legacy_psgc_code' => '056203000',
            'shape_id' => '30758251B83470150439749',
            'reference_area_ha' => 19986.460993,
        ],
        'Bulusan' => [
            'code' => 'BULUSAN',
            'aliases' => ['BULUSAN'],
            'psgc_code' => '0506204000',
            'legacy_psgc_code' => '056204000',
            'shape_id' => '30758251B89540499101387',
            'reference_area_ha' => 8864.061872,
        ],
        'Casiguran' => [
            'code' => 'CASIGURAN_SORSOGON',
            'aliases' => ['CASIGURAN_SORSOGON'],
            'psgc_code' => '0506205000',
            'legacy_psgc_code' => '056205000',
            'shape_id' => '30758251B58212609685015',
            'reference_area_ha' => 8778.443266,
            'workspace_name' => 'Casiguran (Sorsogon)',
        ],
        'Castilla' => [
            'code' => 'CASTILLA',
            'aliases' => ['CASTILLA'],
            'psgc_code' => '0506206000',
            'legacy_psgc_code' => '056206000',
            'shape_id' => '30758251B41591816430114',
            'reference_area_ha' => 19514.797733,
        ],
        'Donsol' => [
            'code' => 'DONSOL',
            'aliases' => ['DONSOL'],
            'psgc_code' => '0506207000',
            'legacy_psgc_code' => '056207000',
            'shape_id' => '30758251B78827955884904',
            'reference_area_ha' => 15320.854458,
        ],
        'Gubat' => [
            'code' => 'GUBAT',
            'aliases' => ['GUBAT'],
            'psgc_code' => '0506208000',
            'legacy_psgc_code' => '056208000',
            'shape_id' => '30758251B1476462816000',
            'reference_area_ha' => 10350.798059,
        ],
        'Irosin' => [
            'code' => 'IROSIN',
            'aliases' => ['IROSIN'],
            'psgc_code' => '0506209000',
            'legacy_psgc_code' => '056209000',
            'shape_id' => '30758251B81086391606543',
            'reference_area_ha' => 13437.477660,
        ],
        'Juban' => [
            'code' => 'JUBAN',
            'aliases' => ['JUBAN'],
            'psgc_code' => '0506210000',
            'legacy_psgc_code' => '056210000',
            'shape_id' => '30758251B25814545213885',
            'reference_area_ha' => 12234.365534,
        ],
        'Magallanes' => [
            'code' => 'MAGALLANES_SORSOGON',
            'aliases' => ['MAGALLANES_SORSOGON'],
            'psgc_code' => '0506211000',
            'legacy_psgc_code' => '056211000',
            'shape_id' => '30758251B24930162212883',
            'reference_area_ha' => 11160.716137,
            'workspace_name' => 'Magallanes (Sorsogon)',
        ],
        'Matnog' => [
            'code' => 'MATNOG',
            'aliases' => ['MATNOG'],
            'psgc_code' => '0506212000',
            'legacy_psgc_code' => '056212000',
            'shape_id' => '30758251B19439306687504',
            'reference_area_ha' => 13877.552226,
        ],
        'Pilar' => [
            'code' => 'PILAR_SORSOGON',
            'aliases' => ['PILAR_SORSOGON'],
            'psgc_code' => '0506213000',
            'legacy_psgc_code' => '056213000',
            'shape_id' => '30758251B95177734597058',
            'reference_area_ha' => 20128.330208,
            'workspace_name' => 'Pilar (Sorsogon)',
        ],
        'Prieto Diaz' => [
            'code' => 'PRIETO_DIAZ',
            'aliases' => ['PRIETO_DIAZ'],
            'psgc_code' => '0506214000',
            'legacy_psgc_code' => '056214000',
            'shape_id' => '30758251B55319475412309',
            'reference_area_ha' => 5620.636164,
        ],
        'Santa Magdalena' => [
            'code' => 'SANTA_MAGDALENA',
            'aliases' => ['SANTA_MAGDALENA'],
            'psgc_code' => '0506215000',
            'legacy_psgc_code' => '056215000',
            'shape_id' => '30758251B27542334536223',
            'reference_area_ha' => 5252.718083,
        ],
        'City of Sorsogon' => [
            'code' => 'SORSOGON_CITY',
            'aliases' => ['SORSOGON_CITY'],
            'psgc_code' => '0506216000',
            'legacy_psgc_code' => '056216000',
            'shape_id' => '30758251B39347385950217',
            'reference_area_ha' => 28148.382474,
            'workspace_name' => 'Sorsogon City',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Sorsogon', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 15 Sorsogon planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

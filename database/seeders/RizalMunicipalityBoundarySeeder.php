<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned CALABARZON planning references; run explicitly after a database backup.
 *
 * Source identities and separate Lucena scope: docs/CALABARZON_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class RizalMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/rizal_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '8cccd5c550216356ee30940ddfb79942130d96b12ce642bdfab7da98cbec3cfd';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Angono' => [
            'code' => 'ANGONO',
            'aliases' => ['ANGONO'],
            'psgc_code' => '0405801000',
            'legacy_psgc_code' => '045801000',
            'shape_id' => '30758251B89453678850457',
            'reference_area_ha' => 1466.344016,
        ],
        'City of Antipolo' => [
            'code' => 'CITY_OF_ANTIPOLO',
            'aliases' => ['CITY_OF_ANTIPOLO'],
            'psgc_code' => '0405802000',
            'legacy_psgc_code' => '045802000',
            'shape_id' => '30758251B52260851794711',
            'reference_area_ha' => 26335.128255,
        ],
        'Baras' => [
            'code' => 'BARAS_RIZAL',
            'aliases' => ['BARAS_RIZAL'],
            'psgc_code' => '0405803000',
            'legacy_psgc_code' => '045803000',
            'shape_id' => '30758251B49056939189974',
            'reference_area_ha' => 5402.603583,
            'workspace_name' => 'Baras (Rizal)',
        ],
        'Binangonan' => [
            'code' => 'BINANGONAN',
            'aliases' => ['BINANGONAN'],
            'psgc_code' => '0405804000',
            'legacy_psgc_code' => '045804000',
            'shape_id' => '30758251B30954744476099',
            'reference_area_ha' => 5396.920369,
        ],
        'Cainta' => [
            'code' => 'CAINTA',
            'aliases' => ['CAINTA'],
            'psgc_code' => '0405805000',
            'legacy_psgc_code' => '045805000',
            'shape_id' => '30758251B76506512725367',
            'reference_area_ha' => 2097.374255,
        ],
        'Cardona' => [
            'code' => 'CARDONA',
            'aliases' => ['CARDONA'],
            'psgc_code' => '0405806000',
            'legacy_psgc_code' => '045806000',
            'shape_id' => '30758251B57572048623065',
            'reference_area_ha' => 2422.904301,
        ],
        'Jala-Jala' => [
            'code' => 'JALA_JALA',
            'aliases' => ['JALA_JALA'],
            'psgc_code' => '0405807000',
            'legacy_psgc_code' => '045807000',
            'shape_id' => '30758251B16311826266125',
            'reference_area_ha' => 4367.893041,
        ],
        'Rodriguez' => [
            'code' => 'RODRIGUEZ',
            'aliases' => ['RODRIGUEZ'],
            'psgc_code' => '0405808000',
            'legacy_psgc_code' => '045808000',
            'shape_id' => '30758251B28604379202623',
            'reference_area_ha' => 23503.808774,
        ],
        'Morong' => [
            'code' => 'MORONG_RIZAL',
            'aliases' => ['MORONG_RIZAL'],
            'psgc_code' => '0405809000',
            'legacy_psgc_code' => '045809000',
            'shape_id' => '30758251B549341994031',
            'reference_area_ha' => 3456.599506,
            'workspace_name' => 'Morong (Rizal)',
        ],
        'Pililla' => [
            'code' => 'PILILLA',
            'aliases' => ['PILILLA'],
            'psgc_code' => '0405810000',
            'legacy_psgc_code' => '045810000',
            'shape_id' => '30758251B3557386785722',
            'reference_area_ha' => 6651.083955,
        ],
        'San Mateo' => [
            'code' => 'SAN_MATEO_RIZAL',
            'aliases' => ['SAN_MATEO_RIZAL'],
            'psgc_code' => '0405811000',
            'legacy_psgc_code' => '045811000',
            'shape_id' => '30758251B86089983795598',
            'reference_area_ha' => 5751.905430,
            'workspace_name' => 'San Mateo (Rizal)',
        ],
        'Tanay' => [
            'code' => 'TANAY',
            'aliases' => ['TANAY'],
            'psgc_code' => '0405812000',
            'legacy_psgc_code' => '045812000',
            'shape_id' => '30758251B99469843476303',
            'reference_area_ha' => 28379.717542,
        ],
        'Taytay' => [
            'code' => 'TAYTAY_RIZAL',
            'aliases' => ['TAYTAY_RIZAL'],
            'psgc_code' => '0405813000',
            'legacy_psgc_code' => '045813000',
            'shape_id' => '30758251B47474969400955',
            'reference_area_ha' => 2839.614204,
            'workspace_name' => 'Taytay (Rizal)',
        ],
        'Teresa' => [
            'code' => 'TERESA',
            'aliases' => ['TERESA'],
            'psgc_code' => '0405814000',
            'legacy_psgc_code' => '045814000',
            'shape_id' => '30758251B5946986755811',
            'reference_area_ha' => 1954.145260,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Rizal', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 14 Rizal planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

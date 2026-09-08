<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

class TarlacRemainingMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/tarlac_remaining_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '3d9f24fabba985d50679d263998516cc05d24d2d6fc60390aa824d222d8d39b9';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bamban' => [
            'code' => 'BAMBAN',
            'aliases' => ['BAMBAN'],
            'psgc_code' => '0306902000',
            'legacy_psgc_code' => '036902000',
            'shape_id' => '30758251B57034914401732',
            'reference_area_ha' => 20747.839251,
        ],
        'Capas' => [
            'code' => 'CAPAS',
            'aliases' => ['CAPAS'],
            'psgc_code' => '0306904000',
            'legacy_psgc_code' => '036904000',
            'shape_id' => '30758251B1710226943062',
            'reference_area_ha' => 44647.834915,
        ],
        'Gerona' => [
            'code' => 'GERONA',
            'aliases' => ['GERONA'],
            'psgc_code' => '0306906000',
            'legacy_psgc_code' => '036906000',
            'shape_id' => '30758251B71774891580031',
            'reference_area_ha' => 12234.883302,
        ],
        'La Paz' => [
            'code' => 'LA_PAZ',
            'aliases' => ['LA_PAZ', 'LAPAZ', 'LA-PAZ'],
            'psgc_code' => '0306907000',
            'legacy_psgc_code' => '036907000',
            'shape_id' => '30758251B69162702482749',
            'reference_area_ha' => 11570.271156,
        ],
        'Mayantoc' => [
            'code' => 'MAYANTOC',
            'aliases' => ['MAYANTOC'],
            'psgc_code' => '0306908000',
            'legacy_psgc_code' => '036908000',
            'shape_id' => '30758251B27820731210135',
            'reference_area_ha' => 24008.967425,
        ],
        'Moncada' => [
            'code' => 'MONCADA',
            'aliases' => ['MONCADA'],
            'psgc_code' => '0306909000',
            'legacy_psgc_code' => '036909000',
            'shape_id' => '30758251B50379489571525',
            'reference_area_ha' => 10174.105474,
        ],
        'Pura' => [
            'code' => 'PURA',
            'aliases' => ['PURA'],
            'psgc_code' => '0306911000',
            'legacy_psgc_code' => '036911000',
            'shape_id' => '30758251B36664202091084',
            'reference_area_ha' => 3211.953092,
        ],
        'San Clemente' => [
            'code' => 'SAN_CLEMENTE',
            'aliases' => ['SAN_CLEMENTE', 'SANCLEMENTE', 'SAN-CLEMENTE'],
            'psgc_code' => '0306913000',
            'legacy_psgc_code' => '036913000',
            'shape_id' => '30758251B85209892464019',
            'reference_area_ha' => 7127.505251,
        ],
        'San Jose' => [
            'code' => 'SAN_JOSE',
            'aliases' => ['SAN_JOSE', 'SANJOSE', 'SAN-JOSE'],
            'psgc_code' => '0306918000',
            'legacy_psgc_code' => '036918000',
            'shape_id' => '30758251B86793831645374',
            'reference_area_ha' => 59583.013518,
        ],
        'San Manuel' => [
            'code' => 'SAN_MANUEL',
            'aliases' => ['SAN_MANUEL', 'SANMANUEL', 'SAN-MANUEL'],
            'psgc_code' => '0306914000',
            'legacy_psgc_code' => '036914000',
            'shape_id' => '30758251B40197742842529',
            'reference_area_ha' => 3267.855300,
        ],
        'Santa Ignacia' => [
            'code' => 'SANTA_IGNACIA',
            'aliases' => ['SANTA_IGNACIA', 'SANTAIGNACIA', 'SANTA-IGNACIA'],
            'psgc_code' => '0306915000',
            'legacy_psgc_code' => '036915000',
            'shape_id' => '30758251B1064490572832',
            'reference_area_ha' => 13145.882843,
        ],
        'Victoria' => [
            'code' => 'VICTORIA',
            'aliases' => ['VICTORIA'],
            'psgc_code' => '0306917000',
            'legacy_psgc_code' => '036917000',
            'shape_id' => '30758251B28872871222208',
            'reference_area_ha' => 10966.356898,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Tarlac', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION, self::MUNICIPALITIES
        );

        $this->command?->info('Ready: The 12 remaining Tarlac municipality planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

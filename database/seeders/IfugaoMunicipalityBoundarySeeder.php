<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned remaining CAR planning references; run explicitly after a database backup.
 *
 * Source identities: docs/REMAINING_CAR_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class IfugaoMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/ifugao_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '3a42a2a8c550739aa5049c63686e50d1f56f2a49fcbcf09985102570cb9cc610';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Banaue' => [
            'code' => 'BANAUE',
            'aliases' => ['BANAUE'],
            'psgc_code' => '1402701000',
            'legacy_psgc_code' => '142701000',
            'shape_id' => '30758251B61409714569525',
            'reference_area_ha' => 19218.308058,
        ],
        'Hungduan' => [
            'code' => 'HUNGDUAN',
            'aliases' => ['HUNGDUAN'],
            'psgc_code' => '1402702000',
            'legacy_psgc_code' => '142702000',
            'shape_id' => '30758251B52637175911657',
            'reference_area_ha' => 24145.179992,
        ],
        'Kiangan' => [
            'code' => 'KIANGAN',
            'aliases' => ['KIANGAN'],
            'psgc_code' => '1402703000',
            'legacy_psgc_code' => '142703000',
            'shape_id' => '30758251B41727580901072',
            'reference_area_ha' => 11913.195508,
        ],
        'Lagawe' => [
            'code' => 'LAGAWE',
            'aliases' => ['LAGAWE'],
            'psgc_code' => '1402704000',
            'legacy_psgc_code' => '142704000',
            'shape_id' => '30758251B93228488685386',
            'reference_area_ha' => 22899.397926,
        ],
        'Lamut' => [
            'code' => 'LAMUT',
            'aliases' => ['LAMUT'],
            'psgc_code' => '1402705000',
            'legacy_psgc_code' => '142705000',
            'shape_id' => '30758251B56792461965852',
            'reference_area_ha' => 15035.825625,
        ],
        'Mayoyao' => [
            'code' => 'MAYOYAO',
            'aliases' => ['MAYOYAO'],
            'psgc_code' => '1402706000',
            'legacy_psgc_code' => '142706000',
            'shape_id' => '30758251B71362999273154',
            'reference_area_ha' => 27380.868347,
        ],
        'Alfonso Lista' => [
            'code' => 'ALFONSO_LISTA',
            'aliases' => ['ALFONSO_LISTA'],
            'psgc_code' => '1402707000',
            'legacy_psgc_code' => '142707000',
            'shape_id' => '30758251B66647693295025',
            'reference_area_ha' => 35875.623931,
        ],
        'Aguinaldo' => [
            'code' => 'AGUINALDO',
            'aliases' => ['AGUINALDO'],
            'psgc_code' => '1402708000',
            'legacy_psgc_code' => '142708000',
            'shape_id' => '30758251B46587647623990',
            'reference_area_ha' => 45010.671715,
        ],
        'Hingyon' => [
            'code' => 'HINGYON',
            'aliases' => ['HINGYON'],
            'psgc_code' => '1402709000',
            'legacy_psgc_code' => '142709000',
            'shape_id' => '30758251B97249489621856',
            'reference_area_ha' => 5724.673534,
        ],
        'Tinoc' => [
            'code' => 'TINOC',
            'aliases' => ['TINOC'],
            'psgc_code' => '1402710000',
            'legacy_psgc_code' => '142710000',
            'shape_id' => '30758251B8715829050570',
            'reference_area_ha' => 22921.988810,
        ],
        'Asipulo' => [
            'code' => 'ASIPULO',
            'aliases' => ['ASIPULO'],
            'psgc_code' => '1402711000',
            'legacy_psgc_code' => '142711000',
            'shape_id' => '30758251B45532258933091',
            'reference_area_ha' => 20539.942024,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Ifugao', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 11 Ifugao planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

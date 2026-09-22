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
class CaviteMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/cavite_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '8f43f66858b45cba348658b48322688b7d5bc91053fbecdff16354093d17f9a7';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Alfonso' => [
            'code' => 'ALFONSO',
            'aliases' => ['ALFONSO'],
            'psgc_code' => '0402101000',
            'legacy_psgc_code' => '042101000',
            'shape_id' => '30758251B26020772303451',
            'reference_area_ha' => 7025.341452,
        ],
        'Amadeo' => [
            'code' => 'AMADEO',
            'aliases' => ['AMADEO'],
            'psgc_code' => '0402102000',
            'legacy_psgc_code' => '042102000',
            'shape_id' => '30758251B72252115450997',
            'reference_area_ha' => 3592.539007,
        ],
        'Bacoor City' => [
            'code' => 'BACOOR_CITY',
            'aliases' => ['BACOOR_CITY'],
            'psgc_code' => '0402103000',
            'legacy_psgc_code' => '042103000',
            'shape_id' => '30758251B21872245709611',
            'reference_area_ha' => 4901.032621,
        ],
        'Carmona' => [
            'code' => 'CARMONA_CITY',
            'aliases' => ['CARMONA_CITY'],
            'psgc_code' => '0402104000',
            'legacy_psgc_code' => '042104000',
            'shape_id' => '30758251B42806803343696',
            'reference_area_ha' => 2411.872927,
            'workspace_name' => 'Carmona City',
        ],
        'Cavite City' => [
            'code' => 'CAVITE_CITY',
            'aliases' => ['CAVITE_CITY'],
            'psgc_code' => '0402105000',
            'legacy_psgc_code' => '042105000',
            'shape_id' => '30758251B13765165414384',
            'reference_area_ha' => 1187.371541,
        ],
        'City of DasmariÃ±as' => [
            'code' => 'CITY_OF_DASMARIAAS',
            'aliases' => ['CITY_OF_DASMARIAAS'],
            'psgc_code' => '0402106000',
            'legacy_psgc_code' => '042106000',
            'shape_id' => '30758251B92956518503760',
            'reference_area_ha' => 9010.463234,
        ],
        'General Emilio Aguinaldo' => [
            'code' => 'GENERAL_EMILIO_AGUINALDO',
            'aliases' => ['GENERAL_EMILIO_AGUINALDO'],
            'psgc_code' => '0402107000',
            'legacy_psgc_code' => '042107000',
            'shape_id' => '30758251B7170785123118',
            'reference_area_ha' => 4066.400670,
        ],
        'City of General Trias' => [
            'code' => 'CITY_OF_GENERAL_TRIAS',
            'aliases' => ['CITY_OF_GENERAL_TRIAS'],
            'psgc_code' => '0402108000',
            'legacy_psgc_code' => '042108000',
            'shape_id' => '30758251B92766896635284',
            'reference_area_ha' => 8746.379768,
        ],
        'Imus City' => [
            'code' => 'IMUS_CITY',
            'aliases' => ['IMUS_CITY'],
            'psgc_code' => '0402109000',
            'legacy_psgc_code' => '042109000',
            'shape_id' => '30758251B96342431093437',
            'reference_area_ha' => 5037.762060,
        ],
        'Indang' => [
            'code' => 'INDANG',
            'aliases' => ['INDANG'],
            'psgc_code' => '0402110000',
            'legacy_psgc_code' => '042110000',
            'shape_id' => '30758251B89143958154434',
            'reference_area_ha' => 8900.269514,
        ],
        'Kawit' => [
            'code' => 'KAWIT',
            'aliases' => ['KAWIT'],
            'psgc_code' => '0402111000',
            'legacy_psgc_code' => '042111000',
            'shape_id' => '30758251B10181127311894',
            'reference_area_ha' => 1595.889538,
        ],
        'Magallanes' => [
            'code' => 'MAGALLANES_CAVITE',
            'aliases' => ['MAGALLANES_CAVITE'],
            'psgc_code' => '0402112000',
            'legacy_psgc_code' => '042112000',
            'shape_id' => '30758251B48484598892391',
            'reference_area_ha' => 6587.544642,
            'workspace_name' => 'Magallanes (Cavite)',
        ],
        'Maragondon' => [
            'code' => 'MARAGONDON',
            'aliases' => ['MARAGONDON'],
            'psgc_code' => '0402113000',
            'legacy_psgc_code' => '042113000',
            'shape_id' => '30758251B1630286055776',
            'reference_area_ha' => 14157.735360,
        ],
        'Mendez' => [
            'code' => 'MENDEZ',
            'aliases' => ['MENDEZ'],
            'psgc_code' => '0402114000',
            'legacy_psgc_code' => '042114000',
            'shape_id' => '30758251B56327904281600',
            'reference_area_ha' => 1468.383813,
        ],
        'Naic' => [
            'code' => 'NAIC',
            'aliases' => ['NAIC'],
            'psgc_code' => '0402115000',
            'legacy_psgc_code' => '042115000',
            'shape_id' => '30758251B11802167700274',
            'reference_area_ha' => 7092.842467,
        ],
        'Noveleta' => [
            'code' => 'NOVELETA',
            'aliases' => ['NOVELETA'],
            'psgc_code' => '0402116000',
            'legacy_psgc_code' => '042116000',
            'shape_id' => '30758251B26560222225231',
            'reference_area_ha' => 518.705600,
        ],
        'Rosario' => [
            'code' => 'ROSARIO_CAVITE',
            'aliases' => ['ROSARIO_CAVITE'],
            'psgc_code' => '0402117000',
            'legacy_psgc_code' => '042117000',
            'shape_id' => '30758251B22134644718406',
            'reference_area_ha' => 800.193713,
            'workspace_name' => 'Rosario (Cavite)',
        ],
        'Silang' => [
            'code' => 'SILANG',
            'aliases' => ['SILANG'],
            'psgc_code' => '0402118000',
            'legacy_psgc_code' => '042118000',
            'shape_id' => '30758251B65416136656933',
            'reference_area_ha' => 14348.168787,
        ],
        'Tagaytay City' => [
            'code' => 'TAGAYTAY_CITY',
            'aliases' => ['TAGAYTAY_CITY'],
            'psgc_code' => '0402119000',
            'legacy_psgc_code' => '042119000',
            'shape_id' => '30758251B41438074837585',
            'reference_area_ha' => 5478.916105,
        ],
        'Tanza' => [
            'code' => 'TANZA',
            'aliases' => ['TANZA'],
            'psgc_code' => '0402120000',
            'legacy_psgc_code' => '042120000',
            'shape_id' => '30758251B17999592905510',
            'reference_area_ha' => 7453.885611,
        ],
        'Ternate' => [
            'code' => 'TERNATE',
            'aliases' => ['TERNATE'],
            'psgc_code' => '0402121000',
            'legacy_psgc_code' => '042121000',
            'shape_id' => '30758251B92987425451541',
            'reference_area_ha' => 4651.859129,
        ],
        'Trece Martires City' => [
            'code' => 'TRECE_MARTIRES_CITY',
            'aliases' => ['TRECE_MARTIRES_CITY'],
            'psgc_code' => '0402122000',
            'legacy_psgc_code' => '042122000',
            'shape_id' => '30758251B97625371583583',
            'reference_area_ha' => 4615.379061,
        ],
        'Gen. Mariano Alvarez' => [
            'code' => 'GEN_MARIANO_ALVAREZ',
            'aliases' => ['GEN_MARIANO_ALVAREZ'],
            'psgc_code' => '0402123000',
            'legacy_psgc_code' => '042123000',
            'shape_id' => '30758251B6653564464331',
            'reference_area_ha' => 872.129517,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Cavite', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 23 Cavite planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

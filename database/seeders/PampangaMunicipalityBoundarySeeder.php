<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Region III planning references; run explicitly after a database backup.
 *
 * Source identities and city supervision: docs/REGION_III_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class PampangaMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/pampanga_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '851447be0e284899452b24b2bf2fc34ed87341b62e2453985043bb9dbeebf8d6';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Apalit' => [
            'code' => 'APALIT',
            'aliases' => ['APALIT'],
            'psgc_code' => '0305402000',
            'legacy_psgc_code' => '035402000',
            'shape_id' => '30758251B27786389641785',
            'reference_area_ha' => 5759.177388,
        ],
        'Arayat' => [
            'code' => 'ARAYAT',
            'aliases' => ['ARAYAT'],
            'psgc_code' => '0305403000',
            'legacy_psgc_code' => '035403000',
            'shape_id' => '30758251B65285968216492',
            'reference_area_ha' => 18712.540932,
        ],
        'Bacolor' => [
            'code' => 'BACOLOR',
            'aliases' => ['BACOLOR'],
            'psgc_code' => '0305404000',
            'legacy_psgc_code' => '035404000',
            'shape_id' => '30758251B55823807082743',
            'reference_area_ha' => 8356.743015,
        ],
        'Candaba' => [
            'code' => 'CANDABA',
            'aliases' => ['CANDABA'],
            'psgc_code' => '0305405000',
            'legacy_psgc_code' => '035405000',
            'shape_id' => '30758251B67025420220537',
            'reference_area_ha' => 20801.135354,
        ],
        'City of San Fernando' => [
            'code' => 'SAN_FERNANDO_CITY_PAMPANGA',
            'aliases' => ['SAN_FERNANDO_CITY_PAMPANGA'],
            'psgc_code' => '0305416000',
            'legacy_psgc_code' => '035416000',
            'shape_id' => '30758251B76914785362033',
            'reference_area_ha' => 7010.845165,
            'workspace_name' => 'San Fernando City (Pampanga)',
        ],
        'Floridablanca' => [
            'code' => 'FLORIDABLANCA',
            'aliases' => ['FLORIDABLANCA'],
            'psgc_code' => '0305406000',
            'legacy_psgc_code' => '035406000',
            'shape_id' => '30758251B62487244382534',
            'reference_area_ha' => 13486.841322,
        ],
        'Guagua' => [
            'code' => 'GUAGUA',
            'aliases' => ['GUAGUA'],
            'psgc_code' => '0305407000',
            'legacy_psgc_code' => '035407000',
            'shape_id' => '30758251B51430418029688',
            'reference_area_ha' => 4484.060617,
        ],
        'Lubao' => [
            'code' => 'LUBAO',
            'aliases' => ['LUBAO'],
            'psgc_code' => '0305408000',
            'legacy_psgc_code' => '035408000',
            'shape_id' => '30758251B85700326948768',
            'reference_area_ha' => 16501.810104,
        ],
        'Mabalacat City' => [
            'code' => 'MABALACAT_CITY',
            'aliases' => ['MABALACAT_CITY'],
            'psgc_code' => '0305409000',
            'legacy_psgc_code' => '035409000',
            'shape_id' => '30758251B7981212511946',
            'reference_area_ha' => 9754.802749,
        ],
        'Macabebe' => [
            'code' => 'MACABEBE',
            'aliases' => ['MACABEBE'],
            'psgc_code' => '0305410000',
            'legacy_psgc_code' => '035410000',
            'shape_id' => '30758251B25010155253639',
            'reference_area_ha' => 9024.105971,
        ],
        'Magalang' => [
            'code' => 'MAGALANG',
            'aliases' => ['MAGALANG'],
            'psgc_code' => '0305411000',
            'legacy_psgc_code' => '035411000',
            'shape_id' => '30758251B60440366228098',
            'reference_area_ha' => 9691.597844,
        ],
        'Masantol' => [
            'code' => 'MASANTOL',
            'aliases' => ['MASANTOL'],
            'psgc_code' => '0305412000',
            'legacy_psgc_code' => '035412000',
            'shape_id' => '30758251B7856900683747',
            'reference_area_ha' => 6760.267527,
        ],
        'Mexico' => [
            'code' => 'MEXICO',
            'aliases' => ['MEXICO'],
            'psgc_code' => '0305413000',
            'legacy_psgc_code' => '035413000',
            'shape_id' => '30758251B60921017943813',
            'reference_area_ha' => 12436.961401,
        ],
        'Minalin' => [
            'code' => 'MINALIN',
            'aliases' => ['MINALIN'],
            'psgc_code' => '0305414000',
            'legacy_psgc_code' => '035414000',
            'shape_id' => '30758251B70394947759752',
            'reference_area_ha' => 5201.910388,
        ],
        'Porac' => [
            'code' => 'PORAC',
            'aliases' => ['PORAC'],
            'psgc_code' => '0305415000',
            'legacy_psgc_code' => '035415000',
            'shape_id' => '30758251B84258699520401',
            'reference_area_ha' => 29712.993489,
        ],
        'San Luis' => [
            'code' => 'SAN_LUIS_PAMPANGA',
            'aliases' => ['SAN_LUIS_PAMPANGA'],
            'psgc_code' => '0305417000',
            'legacy_psgc_code' => '035417000',
            'shape_id' => '30758251B39089965274487',
            'reference_area_ha' => 4806.274523,
            'workspace_name' => 'San Luis (Pampanga)',
        ],
        'San Simon' => [
            'code' => 'SAN_SIMON',
            'aliases' => ['SAN_SIMON'],
            'psgc_code' => '0305418000',
            'legacy_psgc_code' => '035418000',
            'shape_id' => '30758251B91508590625378',
            'reference_area_ha' => 5514.942143,
        ],
        'Santa Ana' => [
            'code' => 'SANTA_ANA_PAMPANGA',
            'aliases' => ['SANTA_ANA_PAMPANGA'],
            'psgc_code' => '0305419000',
            'legacy_psgc_code' => '035419000',
            'shape_id' => '30758251B20191703245220',
            'reference_area_ha' => 3744.934459,
            'workspace_name' => 'Santa Ana (Pampanga)',
        ],
        'Santa Rita' => [
            'code' => 'SANTA_RITA_PAMPANGA',
            'aliases' => ['SANTA_RITA_PAMPANGA'],
            'psgc_code' => '0305420000',
            'legacy_psgc_code' => '035420000',
            'shape_id' => '30758251B30766672962304',
            'reference_area_ha' => 2164.319271,
            'workspace_name' => 'Santa Rita (Pampanga)',
        ],
        'Santo Tomas' => [
            'code' => 'SANTO_TOMAS_PAMPANGA',
            'aliases' => ['SANTO_TOMAS_PAMPANGA'],
            'psgc_code' => '0305421000',
            'legacy_psgc_code' => '035421000',
            'shape_id' => '30758251B57632773357139',
            'reference_area_ha' => 1386.084486,
            'workspace_name' => 'Santo Tomas (Pampanga)',
        ],
        'Sasmuan' => [
            'code' => 'SASMUAN',
            'aliases' => ['SASMUAN'],
            'psgc_code' => '0305422000',
            'legacy_psgc_code' => '035422000',
            'shape_id' => '30758251B19069500410191',
            'reference_area_ha' => 4008.579129,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Pampanga', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 21 Pampanga planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

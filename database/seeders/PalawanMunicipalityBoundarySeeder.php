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
class PalawanMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/palawan_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '3c583d308464bb1e12d94712226edb96043947a7d092847af086593e42b36717';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Aborlan' => [
            'code' => 'ABORLAN',
            'aliases' => ['ABORLAN'],
            'psgc_code' => '1705301000',
            'legacy_psgc_code' => '175301000',
            'shape_id' => '30758251B88743888905923',
            'reference_area_ha' => 77112.063015,
        ],
        'Agutaya' => [
            'code' => 'AGUTAYA',
            'aliases' => ['AGUTAYA'],
            'psgc_code' => '1705302000',
            'legacy_psgc_code' => '175302000',
            'shape_id' => '30758251B94272049094812',
            'reference_area_ha' => 3097.063750,
        ],
        'Araceli' => [
            'code' => 'ARACELI',
            'aliases' => ['ARACELI'],
            'psgc_code' => '1705303000',
            'legacy_psgc_code' => '175303000',
            'shape_id' => '30758251B86342486803813',
            'reference_area_ha' => 17411.905568,
        ],
        'Balabac' => [
            'code' => 'BALABAC',
            'aliases' => ['BALABAC'],
            'psgc_code' => '1705304000',
            'legacy_psgc_code' => '175304000',
            'shape_id' => '30758251B69147265954334',
            'reference_area_ha' => 55912.129693,
        ],
        'Bataraza' => [
            'code' => 'BATARAZA',
            'aliases' => ['BATARAZA'],
            'psgc_code' => '1705305000',
            'legacy_psgc_code' => '175305000',
            'shape_id' => '30758251B18056750213925',
            'reference_area_ha' => 68854.940728,
        ],
        'Brooke\'s Point' => [
            'code' => 'BROOKE_S_POINT',
            'aliases' => ['BROOKE_S_POINT'],
            'psgc_code' => '1705306000',
            'legacy_psgc_code' => '175306000',
            'shape_id' => '30758251B43444158491696',
            'reference_area_ha' => 68482.209095,
        ],
        'Busuanga' => [
            'code' => 'BUSUANGA',
            'aliases' => ['BUSUANGA'],
            'psgc_code' => '1705307000',
            'legacy_psgc_code' => '175307000',
            'shape_id' => '30758251B1739425505893',
            'reference_area_ha' => 40453.875296,
        ],
        'Cagayancillo' => [
            'code' => 'CAGAYANCILLO',
            'aliases' => ['CAGAYANCILLO'],
            'psgc_code' => '1705308000',
            'legacy_psgc_code' => '175308000',
            'shape_id' => '30758251B20045394084873',
            'reference_area_ha' => 1230.657358,
        ],
        'Coron' => [
            'code' => 'CORON',
            'aliases' => ['CORON'],
            'psgc_code' => '1705309000',
            'legacy_psgc_code' => '175309000',
            'shape_id' => '30758251B89626274452255',
            'reference_area_ha' => 69436.653761,
        ],
        'Cuyo' => [
            'code' => 'CUYO',
            'aliases' => ['CUYO'],
            'psgc_code' => '1705310000',
            'legacy_psgc_code' => '175310000',
            'shape_id' => '30758251B36861080699281',
            'reference_area_ha' => 4279.235719,
        ],
        'Dumaran' => [
            'code' => 'DUMARAN',
            'aliases' => ['DUMARAN'],
            'psgc_code' => '1705311000',
            'legacy_psgc_code' => '175311000',
            'shape_id' => '30758251B96628789522948',
            'reference_area_ha' => 52617.503555,
        ],
        'El Nido' => [
            'code' => 'EL_NIDO',
            'aliases' => ['EL_NIDO'],
            'psgc_code' => '1705312000',
            'legacy_psgc_code' => '175312000',
            'shape_id' => '30758251B21090387346093',
            'reference_area_ha' => 56202.058119,
        ],
        'Linapacan' => [
            'code' => 'LINAPACAN',
            'aliases' => ['LINAPACAN'],
            'psgc_code' => '1705313000',
            'legacy_psgc_code' => '175313000',
            'shape_id' => '30758251B95429625837962',
            'reference_area_ha' => 15972.076339,
        ],
        'Magsaysay' => [
            'code' => 'MAGSAYSAY_PALAWAN',
            'aliases' => ['MAGSAYSAY_PALAWAN'],
            'psgc_code' => '1705314000',
            'legacy_psgc_code' => '175314000',
            'shape_id' => '30758251B4414445809925',
            'reference_area_ha' => 4753.159688,
            'workspace_name' => 'Magsaysay (Palawan)',
        ],
        'Narra' => [
            'code' => 'NARRA',
            'aliases' => ['NARRA'],
            'psgc_code' => '1705315000',
            'legacy_psgc_code' => '175315000',
            'shape_id' => '30758251B72391607373552',
            'reference_area_ha' => 75339.246667,
        ],
        'Quezon' => [
            'code' => 'QUEZON_PALAWAN',
            'aliases' => ['QUEZON_PALAWAN'],
            'psgc_code' => '1705317000',
            'legacy_psgc_code' => '175317000',
            'shape_id' => '30758251B7894567948973',
            'reference_area_ha' => 93512.195706,
            'workspace_name' => 'Quezon (Palawan)',
        ],
        'Roxas' => [
            'code' => 'ROXAS_PALAWAN',
            'aliases' => ['ROXAS_PALAWAN'],
            'psgc_code' => '1705318000',
            'legacy_psgc_code' => '175318000',
            'shape_id' => '30758251B10061841294265',
            'reference_area_ha' => 112999.838337,
            'workspace_name' => 'Roxas (Palawan)',
        ],
        'San Vicente' => [
            'code' => 'SAN_VICENTE_PALAWAN',
            'aliases' => ['SAN_VICENTE_PALAWAN'],
            'psgc_code' => '1705319000',
            'legacy_psgc_code' => '175319000',
            'shape_id' => '30758251B71123600878116',
            'reference_area_ha' => 60427.074409,
            'workspace_name' => 'San Vicente (Palawan)',
        ],
        'Taytay' => [
            'code' => 'TAYTAY_PALAWAN',
            'aliases' => ['TAYTAY_PALAWAN'],
            'psgc_code' => '1705320000',
            'legacy_psgc_code' => '175320000',
            'shape_id' => '30758251B72316190206283',
            'reference_area_ha' => 129926.054534,
            'workspace_name' => 'Taytay (Palawan)',
        ],
        'Kalayaan' => [
            'code' => 'KALAYAAN_PALAWAN',
            'aliases' => ['KALAYAAN_PALAWAN'],
            'psgc_code' => '1705321000',
            'legacy_psgc_code' => '175321000',
            'shape_id' => '30758251B42764898301689',
            'reference_area_ha' => 39.981440,
            'workspace_name' => 'Kalayaan (Palawan)',
        ],
        'Culion' => [
            'code' => 'CULION',
            'aliases' => ['CULION'],
            'psgc_code' => '1705322000',
            'legacy_psgc_code' => '175322000',
            'shape_id' => '30758251B61571495122519',
            'reference_area_ha' => 43220.055112,
        ],
        'Rizal' => [
            'code' => 'DR_JOSE_P_RIZAL_PALAWAN',
            'aliases' => ['DR_JOSE_P_RIZAL_PALAWAN'],
            'psgc_code' => '1705323000',
            'legacy_psgc_code' => '175323000',
            'shape_id' => '30758251B22012407080557',
            'reference_area_ha' => 127126.666672,
            'workspace_name' => 'Dr. Jose P. Rizal (Palawan)',
        ],
        'Sofronio Española' => [
            'code' => 'SOFRONIO_ESPANOLA',
            'aliases' => ['SOFRONIO_ESPANOLA'],
            'psgc_code' => '1705324000',
            'legacy_psgc_code' => '175324000',
            'shape_id' => '30758251B15412081534640',
            'reference_area_ha' => 44204.198134,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Palawan', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 23 Palawan planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

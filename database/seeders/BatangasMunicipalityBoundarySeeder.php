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
class BatangasMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/batangas_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '0436ca6ad04af368bbfe4998ab906738b2df9008996d75606c003e8b91b3584f';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Agoncillo' => [
            'code' => 'AGONCILLO',
            'aliases' => ['AGONCILLO'],
            'psgc_code' => '0401001000',
            'legacy_psgc_code' => '041001000',
            'shape_id' => '30758251B30231322331435',
            'reference_area_ha' => 4878.608185,
        ],
        'Alitagtag' => [
            'code' => 'ALITAGTAG',
            'aliases' => ['ALITAGTAG'],
            'psgc_code' => '0401002000',
            'legacy_psgc_code' => '041002000',
            'shape_id' => '30758251B37438092207200',
            'reference_area_ha' => 2547.218315,
        ],
        'Balayan' => [
            'code' => 'BALAYAN',
            'aliases' => ['BALAYAN'],
            'psgc_code' => '0401003000',
            'legacy_psgc_code' => '041003000',
            'shape_id' => '30758251B27285986197002',
            'reference_area_ha' => 9300.106171,
        ],
        'Balete' => [
            'code' => 'BALETE_BATANGAS',
            'aliases' => ['BALETE_BATANGAS'],
            'psgc_code' => '0401004000',
            'legacy_psgc_code' => '041004000',
            'shape_id' => '30758251B9712922067328',
            'reference_area_ha' => 2460.304108,
            'workspace_name' => 'Balete (Batangas)',
        ],
        'Batangas City' => [
            'code' => 'BATANGAS_CITY',
            'aliases' => ['BATANGAS_CITY'],
            'psgc_code' => '0401005000',
            'legacy_psgc_code' => '041005000',
            'shape_id' => '30758251B42170064226471',
            'reference_area_ha' => 27436.991567,
        ],
        'Bauan' => [
            'code' => 'BAUAN',
            'aliases' => ['BAUAN'],
            'psgc_code' => '0401006000',
            'legacy_psgc_code' => '041006000',
            'shape_id' => '30758251B51291858387925',
            'reference_area_ha' => 5298.834661,
        ],
        'Calaca' => [
            'code' => 'CALACA_CITY',
            'aliases' => ['CALACA_CITY'],
            'psgc_code' => '0401007000',
            'legacy_psgc_code' => '041007000',
            'shape_id' => '30758251B68856681488998',
            'reference_area_ha' => 11486.045340,
            'workspace_name' => 'Calaca City',
        ],
        'Calatagan' => [
            'code' => 'CALATAGAN',
            'aliases' => ['CALATAGAN'],
            'psgc_code' => '0401008000',
            'legacy_psgc_code' => '041008000',
            'shape_id' => '30758251B80871387182551',
            'reference_area_ha' => 10624.356830,
        ],
        'Cuenca' => [
            'code' => 'CUENCA',
            'aliases' => ['CUENCA'],
            'psgc_code' => '0401009000',
            'legacy_psgc_code' => '041009000',
            'shape_id' => '30758251B33118282824163',
            'reference_area_ha' => 3026.674521,
        ],
        'Ibaan' => [
            'code' => 'IBAAN',
            'aliases' => ['IBAAN'],
            'psgc_code' => '0401010000',
            'legacy_psgc_code' => '041010000',
            'shape_id' => '30758251B8254101193317',
            'reference_area_ha' => 7031.969486,
        ],
        'Laurel' => [
            'code' => 'LAUREL',
            'aliases' => ['LAUREL'],
            'psgc_code' => '0401011000',
            'legacy_psgc_code' => '041011000',
            'shape_id' => '30758251B77759274087167',
            'reference_area_ha' => 7397.952496,
        ],
        'Lemery' => [
            'code' => 'LEMERY_BATANGAS',
            'aliases' => ['LEMERY_BATANGAS'],
            'psgc_code' => '0401012000',
            'legacy_psgc_code' => '041012000',
            'shape_id' => '30758251B5665377491734',
            'reference_area_ha' => 7211.173088,
            'workspace_name' => 'Lemery (Batangas)',
        ],
        'Lian' => [
            'code' => 'LIAN',
            'aliases' => ['LIAN'],
            'psgc_code' => '0401013000',
            'legacy_psgc_code' => '041013000',
            'shape_id' => '30758251B81633231484096',
            'reference_area_ha' => 8340.973120,
        ],
        'Lipa City' => [
            'code' => 'LIPA_CITY',
            'aliases' => ['LIPA_CITY'],
            'psgc_code' => '0401014000',
            'legacy_psgc_code' => '041014000',
            'shape_id' => '30758251B54075680975044',
            'reference_area_ha' => 19128.185085,
        ],
        'Lobo' => [
            'code' => 'LOBO',
            'aliases' => ['LOBO'],
            'psgc_code' => '0401015000',
            'legacy_psgc_code' => '041015000',
            'shape_id' => '30758251B32727086192084',
            'reference_area_ha' => 19203.802709,
        ],
        'Mabini' => [
            'code' => 'MABINI_BATANGAS',
            'aliases' => ['MABINI_BATANGAS'],
            'psgc_code' => '0401016000',
            'legacy_psgc_code' => '041016000',
            'shape_id' => '30758251B64129218321366',
            'reference_area_ha' => 3972.405155,
            'workspace_name' => 'Mabini (Batangas)',
        ],
        'Malvar' => [
            'code' => 'MALVAR',
            'aliases' => ['MALVAR'],
            'psgc_code' => '0401017000',
            'legacy_psgc_code' => '041017000',
            'shape_id' => '30758251B86715159474326',
            'reference_area_ha' => 3440.440491,
        ],
        'Mataasnakahoy' => [
            'code' => 'MATAASNAKAHOY',
            'aliases' => ['MATAASNAKAHOY'],
            'psgc_code' => '0401018000',
            'legacy_psgc_code' => '041018000',
            'shape_id' => '30758251B55651779812419',
            'reference_area_ha' => 1933.138263,
        ],
        'Nasugbu' => [
            'code' => 'NASUGBU',
            'aliases' => ['NASUGBU'],
            'psgc_code' => '0401019000',
            'legacy_psgc_code' => '041019000',
            'shape_id' => '30758251B8927375136038',
            'reference_area_ha' => 26634.039656,
        ],
        'Padre Garcia' => [
            'code' => 'PADRE_GARCIA',
            'aliases' => ['PADRE_GARCIA'],
            'psgc_code' => '0401020000',
            'legacy_psgc_code' => '041020000',
            'shape_id' => '30758251B75041728100669',
            'reference_area_ha' => 3928.040983,
        ],
        'Rosario' => [
            'code' => 'ROSARIO_BATANGAS',
            'aliases' => ['ROSARIO_BATANGAS'],
            'psgc_code' => '0401021000',
            'legacy_psgc_code' => '041021000',
            'shape_id' => '30758251B26885487370879',
            'reference_area_ha' => 19902.426932,
            'workspace_name' => 'Rosario (Batangas)',
        ],
        'San Jose' => [
            'code' => 'SAN_JOSE_BATANGAS',
            'aliases' => ['SAN_JOSE_BATANGAS'],
            'psgc_code' => '0401022000',
            'legacy_psgc_code' => '041022000',
            'shape_id' => '30758251B22922317245242',
            'reference_area_ha' => 5823.569984,
            'workspace_name' => 'San Jose (Batangas)',
        ],
        'San Juan' => [
            'code' => 'SAN_JUAN_BATANGAS',
            'aliases' => ['SAN_JUAN_BATANGAS'],
            'psgc_code' => '0401023000',
            'legacy_psgc_code' => '041023000',
            'shape_id' => '30758251B36989213936618',
            'reference_area_ha' => 23756.421843,
            'workspace_name' => 'San Juan (Batangas)',
        ],
        'San Luis' => [
            'code' => 'SAN_LUIS_BATANGAS',
            'aliases' => ['SAN_LUIS_BATANGAS'],
            'psgc_code' => '0401024000',
            'legacy_psgc_code' => '041024000',
            'shape_id' => '30758251B60634471978235',
            'reference_area_ha' => 3769.648999,
            'workspace_name' => 'San Luis (Batangas)',
        ],
        'San Nicolas' => [
            'code' => 'SAN_NICOLAS_BATANGAS',
            'aliases' => ['SAN_NICOLAS_BATANGAS'],
            'psgc_code' => '0401025000',
            'legacy_psgc_code' => '041025000',
            'shape_id' => '30758251B83345334824937',
            'reference_area_ha' => 2132.789338,
            'workspace_name' => 'San Nicolas (Batangas)',
        ],
        'San Pascual' => [
            'code' => 'SAN_PASCUAL_BATANGAS',
            'aliases' => ['SAN_PASCUAL_BATANGAS'],
            'psgc_code' => '0401026000',
            'legacy_psgc_code' => '041026000',
            'shape_id' => '30758251B99992201046216',
            'reference_area_ha' => 3526.283095,
            'workspace_name' => 'San Pascual (Batangas)',
        ],
        'Santa Teresita' => [
            'code' => 'SANTA_TERESITA_BATANGAS',
            'aliases' => ['SANTA_TERESITA_BATANGAS'],
            'psgc_code' => '0401027000',
            'legacy_psgc_code' => '041027000',
            'shape_id' => '30758251B65020041021646',
            'reference_area_ha' => 1536.735340,
            'workspace_name' => 'Santa Teresita (Batangas)',
        ],
        'Santo Tomas' => [
            'code' => 'STO_TOMAS_CITY_BATANGAS',
            'aliases' => ['STO_TOMAS_CITY_BATANGAS'],
            'psgc_code' => '0401028000',
            'legacy_psgc_code' => '041028000',
            'shape_id' => '30758251B29832257254847',
            'reference_area_ha' => 8901.363530,
            'workspace_name' => 'Sto. Tomas City (Batangas)',
        ],
        'Taal' => [
            'code' => 'TAAL',
            'aliases' => ['TAAL'],
            'psgc_code' => '0401029000',
            'legacy_psgc_code' => '041029000',
            'shape_id' => '30758251B11016781940262',
            'reference_area_ha' => 2705.741341,
        ],
        'Talisay' => [
            'code' => 'TALISAY_BATANGAS',
            'aliases' => ['TALISAY_BATANGAS'],
            'psgc_code' => '0401030000',
            'legacy_psgc_code' => '041030000',
            'shape_id' => '30758251B80500535312528',
            'reference_area_ha' => 5920.366766,
            'workspace_name' => 'Talisay (Batangas)',
        ],
        'City of Tanauan' => [
            'code' => 'CITY_OF_TANAUAN_BATANGAS',
            'aliases' => ['CITY_OF_TANAUAN_BATANGAS'],
            'psgc_code' => '0401031000',
            'legacy_psgc_code' => '041031000',
            'shape_id' => '30758251B62709822601123',
            'reference_area_ha' => 11207.811953,
            'workspace_name' => 'City of Tanauan (Batangas)',
        ],
        'Taysan' => [
            'code' => 'TAYSAN',
            'aliases' => ['TAYSAN'],
            'psgc_code' => '0401032000',
            'legacy_psgc_code' => '041032000',
            'shape_id' => '30758251B32633454457385',
            'reference_area_ha' => 9254.088968,
        ],
        'Tingloy' => [
            'code' => 'TINGLOY',
            'aliases' => ['TINGLOY'],
            'psgc_code' => '0401033000',
            'legacy_psgc_code' => '041033000',
            'shape_id' => '30758251B31190272548393',
            'reference_area_ha' => 3258.485385,
        ],
        'Tuy' => [
            'code' => 'TUY',
            'aliases' => ['TUY'],
            'psgc_code' => '0401034000',
            'legacy_psgc_code' => '041034000',
            'shape_id' => '30758251B67063088197244',
            'reference_area_ha' => 9354.064483,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Batangas', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 34 Batangas planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

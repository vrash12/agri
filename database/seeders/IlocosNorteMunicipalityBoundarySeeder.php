<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Region I planning references; run explicitly after a database backup.
 *
 * Source identity, area conventions, and attribution: docs/REGION_I_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class IlocosNorteMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/ilocos_norte_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '672b4c00708dafe423fc57fe0cd7c4a6694d7f6d64b9675db488d67592264bd1';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Adams' => [
            'code' => 'ADAMS',
            'aliases' => ['ADAMS'],
            'psgc_code' => '0102801000',
            'legacy_psgc_code' => '012801000',
            'shape_id' => '30758251B18474549888444',
            'reference_area_ha' => 11114.302613,
        ],
        'Bacarra' => [
            'code' => 'BACARRA',
            'aliases' => ['BACARRA'],
            'psgc_code' => '0102802000',
            'legacy_psgc_code' => '012802000',
            'shape_id' => '30758251B49214356355193',
            'reference_area_ha' => 5530.319480,
        ],
        'Badoc' => [
            'code' => 'BADOC',
            'aliases' => ['BADOC'],
            'psgc_code' => '0102803000',
            'legacy_psgc_code' => '012803000',
            'shape_id' => '30758251B31971506880534',
            'reference_area_ha' => 8068.396991,
        ],
        'Bangui' => [
            'code' => 'BANGUI',
            'aliases' => ['BANGUI'],
            'psgc_code' => '0102804000',
            'legacy_psgc_code' => '012804000',
            'shape_id' => '30758251B93501675208475',
            'reference_area_ha' => 11505.904145,
        ],
        'Banna' => [
            'code' => 'BANNA',
            'aliases' => ['BANNA'],
            'psgc_code' => '0102811000',
            'legacy_psgc_code' => '012811000',
            'shape_id' => '30758251B16212654341581',
            'reference_area_ha' => 9113.323970,
        ],
        'Burgos' => [
            'code' => 'BURGOS_ILOCOS_NORTE',
            'aliases' => ['BURGOS_ILOCOS_NORTE'],
            'psgc_code' => '0102806000',
            'legacy_psgc_code' => '012806000',
            'shape_id' => '30758251B71350051012392',
            'reference_area_ha' => 13710.430527,
            'workspace_name' => 'Burgos (Ilocos Norte)',
        ],
        'Carasi' => [
            'code' => 'CARASI',
            'aliases' => ['CARASI'],
            'psgc_code' => '0102807000',
            'legacy_psgc_code' => '012807000',
            'shape_id' => '30758251B21012772444668',
            'reference_area_ha' => 17305.181593,
        ],
        'City of Batac' => [
            'code' => 'BATAC_CITY',
            'aliases' => ['BATAC_CITY'],
            'psgc_code' => '0102805000',
            'legacy_psgc_code' => '012805000',
            'shape_id' => '30758251B15119904313284',
            'reference_area_ha' => 15812.313219,
            'workspace_name' => 'Batac City',
        ],
        'Currimao' => [
            'code' => 'CURRIMAO',
            'aliases' => ['CURRIMAO'],
            'psgc_code' => '0102808000',
            'legacy_psgc_code' => '012808000',
            'shape_id' => '30758251B25146130050891',
            'reference_area_ha' => 3337.379679,
        ],
        'Dingras' => [
            'code' => 'DINGRAS',
            'aliases' => ['DINGRAS'],
            'psgc_code' => '0102809000',
            'legacy_psgc_code' => '012809000',
            'shape_id' => '30758251B52269325260495',
            'reference_area_ha' => 10836.520556,
        ],
        'Dumalneg' => [
            'code' => 'DUMALNEG',
            'aliases' => ['DUMALNEG'],
            'psgc_code' => '0102810000',
            'legacy_psgc_code' => '012810000',
            'shape_id' => '30758251B34921997573635',
            'reference_area_ha' => 6674.788395,
        ],
        'Laoag City' => [
            'code' => 'LAOAG_CITY',
            'aliases' => ['LAOAG_CITY'],
            'psgc_code' => '0102812000',
            'legacy_psgc_code' => '012812000',
            'shape_id' => '30758251B72838386588139',
            'reference_area_ha' => 11005.624351,
        ],
        'Marcos' => [
            'code' => 'MARCOS',
            'aliases' => ['MARCOS'],
            'psgc_code' => '0102813000',
            'legacy_psgc_code' => '012813000',
            'shape_id' => '30758251B92654029938439',
            'reference_area_ha' => 7940.420684,
        ],
        'Nueva Era' => [
            'code' => 'NUEVA_ERA',
            'aliases' => ['NUEVA_ERA'],
            'psgc_code' => '0102814000',
            'legacy_psgc_code' => '012814000',
            'shape_id' => '30758251B81029869492051',
            'reference_area_ha' => 60859.575763,
        ],
        'Pagudpud' => [
            'code' => 'PAGUDPUD',
            'aliases' => ['PAGUDPUD'],
            'psgc_code' => '0102815000',
            'legacy_psgc_code' => '012815000',
            'shape_id' => '30758251B61370537104024',
            'reference_area_ha' => 19317.290756,
        ],
        'Paoay' => [
            'code' => 'PAOAY',
            'aliases' => ['PAOAY'],
            'psgc_code' => '0102816000',
            'legacy_psgc_code' => '012816000',
            'shape_id' => '30758251B25110952192125',
            // Lake-inclusive GeoRiskPH exterior area; the pinned outline includes Paoay Lake.
            'reference_area_ha' => 6988.004500,
        ],
        'Pasuquin' => [
            'code' => 'PASUQUIN',
            'aliases' => ['PASUQUIN'],
            'psgc_code' => '0102817000',
            'legacy_psgc_code' => '012817000',
            'shape_id' => '30758251B90243887174140',
            'reference_area_ha' => 17667.404423,
        ],
        'Piddig' => [
            'code' => 'PIDDIG',
            'aliases' => ['PIDDIG'],
            'psgc_code' => '0102818000',
            'legacy_psgc_code' => '012818000',
            'shape_id' => '30758251B11502828535213',
            'reference_area_ha' => 12358.218352,
        ],
        'Pinili' => [
            'code' => 'PINILI',
            'aliases' => ['PINILI'],
            'psgc_code' => '0102819000',
            'legacy_psgc_code' => '012819000',
            'shape_id' => '30758251B26496758793693',
            'reference_area_ha' => 6361.009309,
        ],
        'San Nicolas' => [
            'code' => 'SAN_NICOLAS_ILOCOS_NORTE',
            'aliases' => ['SAN_NICOLAS_ILOCOS_NORTE'],
            'psgc_code' => '0102820000',
            'legacy_psgc_code' => '012820000',
            'shape_id' => '30758251B22278088652196',
            'reference_area_ha' => 4194.411478,
            'workspace_name' => 'San Nicolas (Ilocos Norte)',
        ],
        'Sarrat' => [
            'code' => 'SARRAT',
            'aliases' => ['SARRAT'],
            'psgc_code' => '0102821000',
            'legacy_psgc_code' => '012821000',
            'shape_id' => '30758251B82772093165971',
            'reference_area_ha' => 5689.925635,
        ],
        'Solsona' => [
            'code' => 'SOLSONA',
            'aliases' => ['SOLSONA'],
            'psgc_code' => '0102822000',
            'legacy_psgc_code' => '012822000',
            'shape_id' => '30758251B20065822888258',
            'reference_area_ha' => 9033.843787,
        ],
        'Vintar' => [
            'code' => 'VINTAR',
            'aliases' => ['VINTAR'],
            'psgc_code' => '0102823000',
            'legacy_psgc_code' => '012823000',
            'shape_id' => '30758251B43967243138313',
            'reference_area_ha' => 53095.863833,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Ilocos Norte', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: the 23 Ilocos Norte planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

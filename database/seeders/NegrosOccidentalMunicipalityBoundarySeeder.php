<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Negros Island Region planning references; run explicitly after a backup.
 * Bacolod has a separate scope. Existing workspaces are never silently transferred.
 * Sources and PSGC transitions: docs/NEGROS_ISLAND_BOUNDARY_SOURCES.md.
 */
class NegrosOccidentalMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/negros_occidental_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'ababd0cf97d3d1ac56cddd31bfdd85969747f0d1c515369768e81585d27a3480';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bago City' => [
            'code' => 'BAGO_CITY',
            'aliases' => ['BAGO_CITY', 'BAGOCITY', 'BAGO-CITY', '0604502000'],
            'psgc_code' => '1804502000',
            'legacy_psgc_code' => '064502000',
            'shape_id' => '30758251B44456462498857',
            'reference_area_ha' => 40686.691581,
        ],
        'Binalbagan' => [
            'code' => 'BINALBAGAN',
            'aliases' => ['BINALBAGAN', '0604503000'],
            'psgc_code' => '1804503000',
            'legacy_psgc_code' => '064503000',
            'shape_id' => '30758251B163560276547',
            'reference_area_ha' => 18487.513708,
        ],
        'Cadiz City' => [
            'code' => 'CADIZ_CITY',
            'aliases' => ['CADIZ_CITY', 'CADIZCITY', 'CADIZ-CITY', '0604504000'],
            'psgc_code' => '1804504000',
            'legacy_psgc_code' => '064504000',
            'shape_id' => '30758251B74690587258532',
            'reference_area_ha' => 52657.216412,
        ],
        'Calatrava' => [
            'code' => 'CALATRAVA',
            'aliases' => ['CALATRAVA', '0604505000'],
            'psgc_code' => '1804505000',
            'legacy_psgc_code' => '064505000',
            'shape_id' => '30758251B51763303574827',
            'reference_area_ha' => 28768.627437,
        ],
        'Candoni' => [
            'code' => 'CANDONI',
            'aliases' => ['CANDONI', '0604506000'],
            'psgc_code' => '1804506000',
            'legacy_psgc_code' => '064506000',
            'shape_id' => '30758251B37131808105677',
            'reference_area_ha' => 29537.757168,
        ],
        'Cauayan' => [
            'code' => 'CAUAYAN',
            'aliases' => ['CAUAYAN', '0604507000'],
            'psgc_code' => '1804507000',
            'legacy_psgc_code' => '064507000',
            'shape_id' => '30758251B70595272616613',
            'reference_area_ha' => 46970.718450,
        ],
        'City of Escalante' => [
            'code' => 'ESCALANTE_CITY',
            'aliases' => ['ESCALANTE_CITY', 'ESCALANTECITY', 'ESCALANTE-CITY', '0604509000'],
            'psgc_code' => '1804509000',
            'legacy_psgc_code' => '064509000',
            'shape_id' => '30758251B93268177737855',
            'reference_area_ha' => 19228.449122,
            'workspace_name' => 'Escalante City',
        ],
        'City of Himamaylan' => [
            'code' => 'HIMAMAYLAN_CITY',
            'aliases' => ['HIMAMAYLAN_CITY', 'HIMAMAYLANCITY', 'HIMAMAYLAN-CITY', '0604510000'],
            'psgc_code' => '1804510000',
            'legacy_psgc_code' => '064510000',
            'shape_id' => '30758251B87460719980042',
            'reference_area_ha' => 36349.722689,
            'workspace_name' => 'Himamaylan City',
        ],
        'City of Kabankalan' => [
            'code' => 'KABANKALAN_CITY',
            'aliases' => ['KABANKALAN_CITY', 'KABANKALANCITY', 'KABANKALAN-CITY', '0604515000'],
            'psgc_code' => '1804515000',
            'legacy_psgc_code' => '064515000',
            'shape_id' => '30758251B39137179877227',
            'reference_area_ha' => 65925.855780,
            'workspace_name' => 'Kabankalan City',
        ],
        'City of Sipalay' => [
            'code' => 'SIPALAY_CITY',
            'aliases' => ['SIPALAY_CITY', 'SIPALAYCITY', 'SIPALAY-CITY', '0604527000'],
            'psgc_code' => '1804527000',
            'legacy_psgc_code' => '064527000',
            'shape_id' => '30758251B39035544266980',
            'reference_area_ha' => 32746.862395,
            'workspace_name' => 'Sipalay City',
        ],
        'City of Talisay' => [
            'code' => 'TALISAY_CITY',
            'aliases' => ['TALISAY_CITY', 'TALISAYCITY', 'TALISAY-CITY', '0604528000'],
            'psgc_code' => '1804528000',
            'legacy_psgc_code' => '064528000',
            'shape_id' => '30758251B1176164020832',
            'reference_area_ha' => 19348.153160,
            'workspace_name' => 'Talisay City',
        ],
        'City of Victorias' => [
            'code' => 'VICTORIAS_CITY',
            'aliases' => ['VICTORIAS_CITY', 'VICTORIASCITY', 'VICTORIAS-CITY', '0604531000'],
            'psgc_code' => '1804531000',
            'legacy_psgc_code' => '064531000',
            'shape_id' => '30758251B44366819417362',
            'reference_area_ha' => 10576.454354,
            'workspace_name' => 'Victorias City',
        ],
        'Enrique B. Magalona' => [
            'code' => 'ENRIQUE_B_MAGALONA',
            'aliases' => ['ENRIQUE_B_MAGALONA', 'ENRIQUEBMAGALONA', 'ENRIQUE-B-MAGALONA', '0604508000'],
            'psgc_code' => '1804508000',
            'legacy_psgc_code' => '064508000',
            'shape_id' => '30758251B93552266540793',
            'reference_area_ha' => 13892.611796,
        ],
        'Hinigaran' => [
            'code' => 'HINIGARAN',
            'aliases' => ['HINIGARAN', '0604511000'],
            'psgc_code' => '1804511000',
            'legacy_psgc_code' => '064511000',
            'shape_id' => '30758251B50394625091243',
            'reference_area_ha' => 15271.718665,
        ],
        'Hinoba-An' => [
            'code' => 'HINOBA_AN',
            'aliases' => ['HINOBA_AN', 'HINOBAAN', 'HINOBA-AN', '0604512000'],
            'psgc_code' => '1804512000',
            'legacy_psgc_code' => '064512000',
            'shape_id' => '30758251B90502148254132',
            'reference_area_ha' => 40203.820970,
        ],
        'Ilog' => [
            'code' => 'ILOG',
            'aliases' => ['ILOG', '0604513000'],
            'psgc_code' => '1804513000',
            'legacy_psgc_code' => '064513000',
            'shape_id' => '30758251B19549547171132',
            'reference_area_ha' => 29403.235825,
        ],
        'Isabela' => [
            'code' => 'ISABELA',
            'aliases' => ['ISABELA', '0604514000'],
            'psgc_code' => '1804514000',
            'legacy_psgc_code' => '064514000',
            'shape_id' => '30758251B76125738133682',
            'reference_area_ha' => 19144.249356,
        ],
        'La Carlota City' => [
            'code' => 'LA_CARLOTA_CITY',
            'aliases' => ['LA_CARLOTA_CITY', 'LACARLOTACITY', 'LA-CARLOTA-CITY', '0604516000'],
            'psgc_code' => '1804516000',
            'legacy_psgc_code' => '064516000',
            'shape_id' => '30758251B4643469409429',
            'reference_area_ha' => 12707.879216,
        ],
        'La Castellana' => [
            'code' => 'LA_CASTELLANA',
            'aliases' => ['LA_CASTELLANA', 'LACASTELLANA', 'LA-CASTELLANA', '0604517000'],
            'psgc_code' => '1804517000',
            'legacy_psgc_code' => '064517000',
            'shape_id' => '30758251B45255679920724',
            'reference_area_ha' => 21583.519192,
        ],
        'Manapla' => [
            'code' => 'MANAPLA',
            'aliases' => ['MANAPLA', '0604518000'],
            'psgc_code' => '1804518000',
            'legacy_psgc_code' => '064518000',
            'shape_id' => '30758251B2817508515429',
            'reference_area_ha' => 9945.808282,
        ],
        'Moises Padilla' => [
            'code' => 'MOISES_PADILLA',
            'aliases' => ['MOISES_PADILLA', 'MOISESPADILLA', 'MOISES-PADILLA', '0604519000'],
            'psgc_code' => '1804519000',
            'legacy_psgc_code' => '064519000',
            'shape_id' => '30758251B86559237055177',
            'reference_area_ha' => 14059.833533,
        ],
        'Murcia' => [
            'code' => 'MURCIA',
            'aliases' => ['MURCIA', '0604520000'],
            'psgc_code' => '1804520000',
            'legacy_psgc_code' => '064520000',
            'shape_id' => '30758251B26190802664029',
            'reference_area_ha' => 27942.637566,
        ],
        'Pontevedra' => [
            'code' => 'PONTEVEDRA',
            'aliases' => ['PONTEVEDRA', '0604521000'],
            'psgc_code' => '1804521000',
            'legacy_psgc_code' => '064521000',
            'shape_id' => '30758251B40366129803792',
            'reference_area_ha' => 11155.622053,
        ],
        'Pulupandan' => [
            'code' => 'PULUPANDAN',
            'aliases' => ['PULUPANDAN', '0604522000'],
            'psgc_code' => '1804522000',
            'legacy_psgc_code' => '064522000',
            'shape_id' => '30758251B33327911617473',
            'reference_area_ha' => 1679.651141,
        ],
        'Sagay City' => [
            'code' => 'SAGAY_CITY',
            'aliases' => ['SAGAY_CITY', 'SAGAYCITY', 'SAGAY-CITY', '0604523000'],
            'psgc_code' => '1804523000',
            'legacy_psgc_code' => '064523000',
            'shape_id' => '30758251B74171926345191',
            'reference_area_ha' => 29235.896647,
        ],
        'Salvador Benedicto' => [
            'code' => 'SALVADOR_BENEDICTO',
            'aliases' => ['SALVADOR_BENEDICTO', 'SALVADORBENEDICTO', 'SALVADOR-BENEDICTO', '0604532000'],
            'psgc_code' => '1804532000',
            'legacy_psgc_code' => '064532000',
            'shape_id' => '30758251B86894559838043',
            'reference_area_ha' => 21767.307771,
        ],
        'San Carlos City' => [
            'code' => 'SAN_CARLOS_CITY',
            'aliases' => ['SAN_CARLOS_CITY', 'SANCARLOSCITY', 'SAN-CARLOS-CITY', '0604524000'],
            'psgc_code' => '1804524000',
            'legacy_psgc_code' => '064524000',
            'shape_id' => '30758251B8075335495854',
            'reference_area_ha' => 40775.872809,
        ],
        'San Enrique' => [
            'code' => 'SAN_ENRIQUE',
            'aliases' => ['SAN_ENRIQUE', 'SANENRIQUE', 'SAN-ENRIQUE', '0604525000'],
            'psgc_code' => '1804525000',
            'legacy_psgc_code' => '064525000',
            'shape_id' => '30758251B11825035117570',
            'reference_area_ha' => 2826.909798,
        ],
        'Silay City' => [
            'code' => 'SILAY_CITY',
            'aliases' => ['SILAY_CITY', 'SILAYCITY', 'SILAY-CITY', '0604526000'],
            'psgc_code' => '1804526000',
            'legacy_psgc_code' => '064526000',
            'shape_id' => '30758251B60701013344406',
            'reference_area_ha' => 20936.961842,
        ],
        'Toboso' => [
            'code' => 'TOBOSO',
            'aliases' => ['TOBOSO', '0604529000'],
            'psgc_code' => '1804529000',
            'legacy_psgc_code' => '064529000',
            'shape_id' => '30758251B45465879841482',
            'reference_area_ha' => 11800.603756,
        ],
        'Valladolid' => [
            'code' => 'VALLADOLID',
            'aliases' => ['VALLADOLID', '0604530000'],
            'psgc_code' => '1804530000',
            'legacy_psgc_code' => '064530000',
            'shape_id' => '30758251B22103021346639',
            'reference_area_ha' => 4024.098190,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Negros Occidental', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 31 Negros Occidental planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

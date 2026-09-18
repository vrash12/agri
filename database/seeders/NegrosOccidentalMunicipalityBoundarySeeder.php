<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Municipality planning references for Negros Occidental, part of the Negros Island Region.
 *
 * Bacolod City is included. It is a highly urbanized city and administratively
 * independent of the province, but it sits inside Negros Occidental and this system
 * stores such a city under its geographic province: Baguio City is already recorded
 * under Benguet the same way. The office can split it into its own workspace later
 * without touching the geometry.
 *
 * The eighteen component cities keep the "Bago City" wording rather than the source's
 * "City of Bago", matching the existing Tarlac City and Malolos City workspaces.
 *
 * These are approximate planning boundaries from geoBoundaries, not cadastral or
 * survey-grade lines, and they require LGU or NAMRIA verification before official use.
 */
class NegrosOccidentalMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/negros_occidental_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '3db7a05511d7d6ccd17f0aafcf6e08f97802db9b0e85d9dd4b6a18f128426661';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bacolod City' => [
            'code' => 'BACOLOD_CITY',
            'aliases' => ['BACOLOD_CITY', 'BACOLODCITY', 'BACOLOD-CITY'],
            'psgc_code' => '0630200000',
            'legacy_psgc_code' => '064501000',
            'shape_id' => '30758251B10359126771558',
            'reference_area_ha' => 16237.786580,
        ],
        'Bago City' => [
            'code' => 'BAGO_CITY',
            'aliases' => ['BAGO_CITY', 'BAGOCITY', 'BAGO-CITY'],
            'psgc_code' => '0604502000',
            'legacy_psgc_code' => '064502000',
            'shape_id' => '30758251B44456462498857',
            'reference_area_ha' => 40686.691581,
        ],
        'Binalbagan' => [
            'code' => 'BINALBAGAN',
            'aliases' => ['BINALBAGAN'],
            'psgc_code' => '0604503000',
            'legacy_psgc_code' => '064503000',
            'shape_id' => '30758251B163560276547',
            'reference_area_ha' => 18487.513708,
        ],
        'Cadiz City' => [
            'code' => 'CADIZ_CITY',
            'aliases' => ['CADIZ_CITY', 'CADIZCITY', 'CADIZ-CITY'],
            'psgc_code' => '0604504000',
            'legacy_psgc_code' => '064504000',
            'shape_id' => '30758251B74690587258532',
            'reference_area_ha' => 52657.216412,
        ],
        'Calatrava' => [
            'code' => 'CALATRAVA',
            'aliases' => ['CALATRAVA'],
            'psgc_code' => '0604505000',
            'legacy_psgc_code' => '064505000',
            'shape_id' => '30758251B51763303574827',
            'reference_area_ha' => 28768.627437,
        ],
        'Candoni' => [
            'code' => 'CANDONI',
            'aliases' => ['CANDONI'],
            'psgc_code' => '0604506000',
            'legacy_psgc_code' => '064506000',
            'shape_id' => '30758251B37131808105677',
            'reference_area_ha' => 29537.757168,
        ],
        'Cauayan' => [
            'code' => 'CAUAYAN',
            'aliases' => ['CAUAYAN'],
            'psgc_code' => '0604507000',
            'legacy_psgc_code' => '064507000',
            'shape_id' => '30758251B70595272616613',
            'reference_area_ha' => 46970.718450,
        ],
        'City of Escalante' => [
            'code' => 'ESCALANTE_CITY',
            'aliases' => ['ESCALANTE_CITY', 'ESCALANTECITY', 'ESCALANTE-CITY'],
            'workspace_name' => 'Escalante City',
            'psgc_code' => '0604509000',
            'legacy_psgc_code' => '064509000',
            'shape_id' => '30758251B93268177737855',
            'reference_area_ha' => 19228.449122,
        ],
        'City of Himamaylan' => [
            'code' => 'HIMAMAYLAN_CITY',
            'aliases' => ['HIMAMAYLAN_CITY', 'HIMAMAYLANCITY', 'HIMAMAYLAN-CITY'],
            'workspace_name' => 'Himamaylan City',
            'psgc_code' => '0604510000',
            'legacy_psgc_code' => '064510000',
            'shape_id' => '30758251B87460719980042',
            'reference_area_ha' => 36349.722689,
        ],
        'City of Kabankalan' => [
            'code' => 'KABANKALAN_CITY',
            'aliases' => ['KABANKALAN_CITY', 'KABANKALANCITY', 'KABANKALAN-CITY'],
            'workspace_name' => 'Kabankalan City',
            'psgc_code' => '0604515000',
            'legacy_psgc_code' => '064515000',
            'shape_id' => '30758251B39137179877227',
            'reference_area_ha' => 65925.855780,
        ],
        'City of Sipalay' => [
            'code' => 'SIPALAY_CITY',
            'aliases' => ['SIPALAY_CITY', 'SIPALAYCITY', 'SIPALAY-CITY'],
            'workspace_name' => 'Sipalay City',
            'psgc_code' => '0604527000',
            'legacy_psgc_code' => '064527000',
            'shape_id' => '30758251B39035544266980',
            'reference_area_ha' => 32746.862395,
        ],
        'City of Talisay' => [
            'code' => 'TALISAY_CITY',
            'aliases' => ['TALISAY_CITY', 'TALISAYCITY', 'TALISAY-CITY'],
            'workspace_name' => 'Talisay City',
            'psgc_code' => '0604528000',
            'legacy_psgc_code' => '064528000',
            'shape_id' => '30758251B1176164020832',
            'reference_area_ha' => 19348.153160,
        ],
        'City of Victorias' => [
            'code' => 'VICTORIAS_CITY',
            'aliases' => ['VICTORIAS_CITY', 'VICTORIASCITY', 'VICTORIAS-CITY'],
            'workspace_name' => 'Victorias City',
            'psgc_code' => '0604531000',
            'legacy_psgc_code' => '064531000',
            'shape_id' => '30758251B44366819417362',
            'reference_area_ha' => 10576.454354,
        ],
        'Enrique B. Magalona' => [
            'code' => 'ENRIQUE_B_MAGALONA',
            'aliases' => ['ENRIQUE_B_MAGALONA', 'ENRIQUEBMAGALONA', 'ENRIQUE-B-MAGALONA'],
            'psgc_code' => '0604508000',
            'legacy_psgc_code' => '064508000',
            'shape_id' => '30758251B93552266540793',
            'reference_area_ha' => 13892.611796,
        ],
        'Hinigaran' => [
            'code' => 'HINIGARAN',
            'aliases' => ['HINIGARAN'],
            'psgc_code' => '0604511000',
            'legacy_psgc_code' => '064511000',
            'shape_id' => '30758251B50394625091243',
            'reference_area_ha' => 15271.718665,
        ],
        'Hinoba-An' => [
            'code' => 'HINOBA_AN',
            'aliases' => ['HINOBA_AN', 'HINOBAAN', 'HINOBA-AN'],
            'psgc_code' => '0604512000',
            'legacy_psgc_code' => '064512000',
            'shape_id' => '30758251B90502148254132',
            'reference_area_ha' => 40203.820970,
        ],
        'Ilog' => [
            'code' => 'ILOG',
            'aliases' => ['ILOG'],
            'psgc_code' => '0604513000',
            'legacy_psgc_code' => '064513000',
            'shape_id' => '30758251B19549547171132',
            'reference_area_ha' => 29403.235825,
        ],
        'Isabela' => [
            'code' => 'ISABELA',
            'aliases' => ['ISABELA'],
            'psgc_code' => '0604514000',
            'legacy_psgc_code' => '064514000',
            'shape_id' => '30758251B76125738133682',
            'reference_area_ha' => 19144.249356,
        ],
        'La Carlota City' => [
            'code' => 'LA_CARLOTA_CITY',
            'aliases' => ['LA_CARLOTA_CITY', 'LACARLOTACITY', 'LA-CARLOTA-CITY'],
            'psgc_code' => '0604516000',
            'legacy_psgc_code' => '064516000',
            'shape_id' => '30758251B4643469409429',
            'reference_area_ha' => 12707.879216,
        ],
        'La Castellana' => [
            'code' => 'LA_CASTELLANA',
            'aliases' => ['LA_CASTELLANA', 'LACASTELLANA', 'LA-CASTELLANA'],
            'psgc_code' => '0604517000',
            'legacy_psgc_code' => '064517000',
            'shape_id' => '30758251B45255679920724',
            'reference_area_ha' => 21583.519192,
        ],
        'Manapla' => [
            'code' => 'MANAPLA',
            'aliases' => ['MANAPLA'],
            'psgc_code' => '0604518000',
            'legacy_psgc_code' => '064518000',
            'shape_id' => '30758251B2817508515429',
            'reference_area_ha' => 9945.808282,
        ],
        'Moises Padilla' => [
            'code' => 'MOISES_PADILLA',
            'aliases' => ['MOISES_PADILLA', 'MOISESPADILLA', 'MOISES-PADILLA'],
            'psgc_code' => '0604519000',
            'legacy_psgc_code' => '064519000',
            'shape_id' => '30758251B86559237055177',
            'reference_area_ha' => 14059.833533,
        ],
        'Murcia' => [
            'code' => 'MURCIA',
            'aliases' => ['MURCIA'],
            'psgc_code' => '0604520000',
            'legacy_psgc_code' => '064520000',
            'shape_id' => '30758251B26190802664029',
            'reference_area_ha' => 27942.637566,
        ],
        'Pontevedra' => [
            'code' => 'PONTEVEDRA',
            'aliases' => ['PONTEVEDRA'],
            'psgc_code' => '0604521000',
            'legacy_psgc_code' => '064521000',
            'shape_id' => '30758251B40366129803792',
            'reference_area_ha' => 11155.622053,
        ],
        'Pulupandan' => [
            'code' => 'PULUPANDAN',
            'aliases' => ['PULUPANDAN'],
            'psgc_code' => '0604522000',
            'legacy_psgc_code' => '064522000',
            'shape_id' => '30758251B33327911617473',
            'reference_area_ha' => 1679.651141,
        ],
        'Sagay City' => [
            'code' => 'SAGAY_CITY',
            'aliases' => ['SAGAY_CITY', 'SAGAYCITY', 'SAGAY-CITY'],
            'psgc_code' => '0604523000',
            'legacy_psgc_code' => '064523000',
            'shape_id' => '30758251B74171926345191',
            'reference_area_ha' => 29235.896647,
        ],
        'Salvador Benedicto' => [
            'code' => 'SALVADOR_BENEDICTO',
            'aliases' => ['SALVADOR_BENEDICTO', 'SALVADORBENEDICTO', 'SALVADOR-BENEDICTO'],
            'psgc_code' => '0604532000',
            'legacy_psgc_code' => '064532000',
            'shape_id' => '30758251B86894559838043',
            'reference_area_ha' => 21767.307771,
        ],
        'San Carlos City' => [
            'code' => 'SAN_CARLOS_CITY',
            'aliases' => ['SAN_CARLOS_CITY', 'SANCARLOSCITY', 'SAN-CARLOS-CITY'],
            'psgc_code' => '0604524000',
            'legacy_psgc_code' => '064524000',
            'shape_id' => '30758251B8075335495854',
            'reference_area_ha' => 40775.872809,
        ],
        'San Enrique' => [
            'code' => 'SAN_ENRIQUE',
            'aliases' => ['SAN_ENRIQUE', 'SANENRIQUE', 'SAN-ENRIQUE'],
            'psgc_code' => '0604525000',
            'legacy_psgc_code' => '064525000',
            'shape_id' => '30758251B11825035117570',
            'reference_area_ha' => 2826.909798,
        ],
        'Silay City' => [
            'code' => 'SILAY_CITY',
            'aliases' => ['SILAY_CITY', 'SILAYCITY', 'SILAY-CITY'],
            'psgc_code' => '0604526000',
            'legacy_psgc_code' => '064526000',
            'shape_id' => '30758251B60701013344406',
            'reference_area_ha' => 20936.961842,
        ],
        'Toboso' => [
            'code' => 'TOBOSO',
            'aliases' => ['TOBOSO'],
            'psgc_code' => '0604529000',
            'legacy_psgc_code' => '064529000',
            'shape_id' => '30758251B45465879841482',
            'reference_area_ha' => 11800.603756,
        ],
        'Valladolid' => [
            'code' => 'VALLADOLID',
            'aliases' => ['VALLADOLID'],
            'psgc_code' => '0604530000',
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

        $this->command?->info('Ready: the 32 Negros Occidental planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

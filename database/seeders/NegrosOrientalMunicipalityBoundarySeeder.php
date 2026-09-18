<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Municipality planning references for Negros Oriental, part of the Negros Island Region.
 *
 * Four of these names also exist in other provinces: La Libertad in Zamboanga del
 * Norte, Valencia in Bukidnon, Santa Catalina in Ilocos Sur, and San Jose in several.
 * The features here were selected by testing each candidate's centroid against the
 * geoBoundaries ADM2 polygon for this province, so a same-named municipality
 * elsewhere cannot be picked up by accident.
 *
 * These are approximate planning boundaries from geoBoundaries, not cadastral or
 * survey-grade lines, and they require LGU or NAMRIA verification before official use.
 */
class NegrosOrientalMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/negros_oriental_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'ce8eb57cf05fe08ae8e76c176fd7f78832569b3a91a480f98d6c2b55ec027dbc';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Amlan' => [
            'code' => 'AMLAN',
            'aliases' => ['AMLAN'],
            'psgc_code' => '0704601000',
            'legacy_psgc_code' => '074601000',
            'shape_id' => '30758251B50636289749121',
            'reference_area_ha' => 5926.749558,
        ],
        'Ayungon' => [
            'code' => 'AYUNGON',
            'aliases' => ['AYUNGON'],
            'psgc_code' => '0704602000',
            'legacy_psgc_code' => '074602000',
            'shape_id' => '30758251B30231816662876',
            'reference_area_ha' => 24852.165533,
        ],
        'Bacong' => [
            'code' => 'BACONG',
            'aliases' => ['BACONG'],
            'psgc_code' => '0704603000',
            'legacy_psgc_code' => '074603000',
            'shape_id' => '30758251B375051523504',
            'reference_area_ha' => 4042.294731,
        ],
        'Bais City' => [
            'code' => 'BAIS_CITY',
            'aliases' => ['BAIS_CITY', 'BAISCITY', 'BAIS-CITY'],
            'psgc_code' => '0704604000',
            'legacy_psgc_code' => '074604000',
            'shape_id' => '30758251B97079733925928',
            'reference_area_ha' => 25248.938119,
        ],
        'Basay' => [
            'code' => 'BASAY',
            'aliases' => ['BASAY'],
            'psgc_code' => '0704605000',
            'legacy_psgc_code' => '074605000',
            'shape_id' => '30758251B60263736267026',
            'reference_area_ha' => 16811.145658,
        ],
        'Bindoy' => [
            'code' => 'BINDOY',
            'aliases' => ['BINDOY'],
            'psgc_code' => '0704607000',
            'legacy_psgc_code' => '074607000',
            'shape_id' => '30758251B7642209697633',
            'reference_area_ha' => 15653.732985,
        ],
        'Canlaon City' => [
            'code' => 'CANLAON_CITY',
            'aliases' => ['CANLAON_CITY', 'CANLAONCITY', 'CANLAON-CITY'],
            'psgc_code' => '0704608000',
            'legacy_psgc_code' => '074608000',
            'shape_id' => '30758251B22693356898691',
            'reference_area_ha' => 14751.859433,
        ],
        'City of Bayawan' => [
            'code' => 'BAYAWAN_CITY',
            'aliases' => ['BAYAWAN_CITY', 'BAYAWANCITY', 'BAYAWAN-CITY'],
            'workspace_name' => 'Bayawan City',
            'psgc_code' => '0704606000',
            'legacy_psgc_code' => '074606000',
            'shape_id' => '30758251B98971931409152',
            'reference_area_ha' => 69964.126810,
        ],
        'City of Guihulngan' => [
            'code' => 'GUIHULNGAN_CITY',
            'aliases' => ['GUIHULNGAN_CITY', 'GUIHULNGANCITY', 'GUIHULNGAN-CITY'],
            'workspace_name' => 'Guihulngan City',
            'psgc_code' => '0704611000',
            'legacy_psgc_code' => '074611000',
            'shape_id' => '30758251B60818257765105',
            'reference_area_ha' => 37502.314301,
        ],
        'City of Tanjay' => [
            'code' => 'TANJAY_CITY',
            'aliases' => ['TANJAY_CITY', 'TANJAYCITY', 'TANJAY-CITY'],
            'workspace_name' => 'Tanjay City',
            'psgc_code' => '0704621000',
            'legacy_psgc_code' => '074621000',
            'shape_id' => '30758251B79018653951628',
            'reference_area_ha' => 22841.921660,
        ],
        'Dauin' => [
            'code' => 'DAUIN',
            'aliases' => ['DAUIN'],
            'psgc_code' => '0704609000',
            'legacy_psgc_code' => '074609000',
            'shape_id' => '30758251B3670515091156',
            'reference_area_ha' => 8077.685876,
        ],
        'Dumaguete City' => [
            'code' => 'DUMAGUETE_CITY',
            'aliases' => ['DUMAGUETE_CITY', 'DUMAGUETECITY', 'DUMAGUETE-CITY'],
            'psgc_code' => '0704610000',
            'legacy_psgc_code' => '074610000',
            'shape_id' => '30758251B75296993492221',
            'reference_area_ha' => 3430.800820,
        ],
        'Jimalalud' => [
            'code' => 'JIMALALUD',
            'aliases' => ['JIMALALUD'],
            'psgc_code' => '0704612000',
            'legacy_psgc_code' => '074612000',
            'shape_id' => '30758251B35162320168665',
            'reference_area_ha' => 15476.719892,
        ],
        'La Libertad' => [
            'code' => 'LA_LIBERTAD',
            'aliases' => ['LA_LIBERTAD', 'LALIBERTAD', 'LA-LIBERTAD'],
            'psgc_code' => '0704613000',
            'legacy_psgc_code' => '074613000',
            'shape_id' => '30758251B96075835138209',
            'reference_area_ha' => 15148.227410,
        ],
        'Mabinay' => [
            'code' => 'MABINAY',
            'aliases' => ['MABINAY'],
            'psgc_code' => '0704614000',
            'legacy_psgc_code' => '074614000',
            'shape_id' => '30758251B10017268667222',
            'reference_area_ha' => 34614.837036,
        ],
        'Manjuyod' => [
            'code' => 'MANJUYOD',
            'aliases' => ['MANJUYOD'],
            'psgc_code' => '0704615000',
            'legacy_psgc_code' => '074615000',
            'shape_id' => '30758251B66064059312986',
            'reference_area_ha' => 12769.815476,
        ],
        'Pamplona' => [
            'code' => 'PAMPLONA',
            'aliases' => ['PAMPLONA'],
            'psgc_code' => '0704616000',
            'legacy_psgc_code' => '074616000',
            'shape_id' => '30758251B24092981342629',
            'reference_area_ha' => 22290.549506,
        ],
        'San Jose' => [
            // Tarlac already holds the workspace named San Jose, and both
            // `municipalities.name` and `municipalities.code` are unique across the
            // whole table, so this one is qualified by its province. See the
            // limitation recorded in database/seeders/data/README.md.
            'code' => 'SAN_JOSE_NEGROS_ORIENTAL',
            'aliases' => ['SAN_JOSE_NEGROS_ORIENTAL', 'SANJOSENEGROSORIENTAL', 'SAN-JOSE-NEGROS-ORIENTAL'],
            'workspace_name' => 'San Jose (Negros Oriental)',
            'psgc_code' => '0704617000',
            'legacy_psgc_code' => '074617000',
            'shape_id' => '30758251B66844761060701',
            'reference_area_ha' => 5179.321878,
        ],
        'Santa Catalina' => [
            'code' => 'SANTA_CATALINA',
            'aliases' => ['SANTA_CATALINA', 'SANTACATALINA', 'SANTA-CATALINA'],
            'psgc_code' => '0704618000',
            'legacy_psgc_code' => '074618000',
            'shape_id' => '30758251B88269025865066',
            'reference_area_ha' => 41438.112151,
        ],
        'Siaton' => [
            'code' => 'SIATON',
            'aliases' => ['SIATON'],
            'psgc_code' => '0704619000',
            'legacy_psgc_code' => '074619000',
            'shape_id' => '30758251B76231588734027',
            'reference_area_ha' => 42766.148779,
        ],
        'Sibulan' => [
            'code' => 'SIBULAN',
            'aliases' => ['SIBULAN'],
            'psgc_code' => '0704620000',
            'legacy_psgc_code' => '074620000',
            'shape_id' => '30758251B92406697043602',
            'reference_area_ha' => 15972.468123,
        ],
        'Tayasan' => [
            'code' => 'TAYASAN',
            'aliases' => ['TAYASAN'],
            'psgc_code' => '0704622000',
            'legacy_psgc_code' => '074622000',
            'shape_id' => '30758251B37133567842554',
            'reference_area_ha' => 17727.108023,
        ],
        'Valencia' => [
            'code' => 'VALENCIA',
            'aliases' => ['VALENCIA'],
            'psgc_code' => '0704623000',
            'legacy_psgc_code' => '074623000',
            'shape_id' => '30758251B45812920134288',
            'reference_area_ha' => 16280.599249,
        ],
        'Vallehermoso' => [
            'code' => 'VALLEHERMOSO',
            'aliases' => ['VALLEHERMOSO'],
            'psgc_code' => '0704624000',
            'legacy_psgc_code' => '074624000',
            'shape_id' => '30758251B9618582640277',
            'reference_area_ha' => 9274.314897,
        ],
        'Zamboanguita' => [
            'code' => 'ZAMBOANGUITA',
            'aliases' => ['ZAMBOANGUITA'],
            'psgc_code' => '0704625000',
            'legacy_psgc_code' => '074625000',
            'shape_id' => '30758251B7316515610413',
            'reference_area_ha' => 15343.284120,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Negros Oriental', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: the 25 Negros Oriental planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

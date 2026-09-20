<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Negros Island Region planning references; run explicitly after a backup.
 * Bacolod has a separate scope. Existing workspaces are never silently transferred.
 * Sources and PSGC transitions: docs/NEGROS_ISLAND_BOUNDARY_SOURCES.md.
 */
class NegrosOrientalMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/negros_oriental_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '707cdc20012ee7c5f0160c6e95c8e1a3e3c4ccafe4ed3f61cbe75d5e2fe3c9b3';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Amlan' => [
            'code' => 'AMLAN',
            'aliases' => ['AMLAN', '0704601000'],
            'psgc_code' => '1804601000',
            'legacy_psgc_code' => '074601000',
            'shape_id' => '30758251B50636289749121',
            'reference_area_ha' => 5926.749558,
        ],
        'Ayungon' => [
            'code' => 'AYUNGON',
            'aliases' => ['AYUNGON', '0704602000'],
            'psgc_code' => '1804602000',
            'legacy_psgc_code' => '074602000',
            'shape_id' => '30758251B30231816662876',
            'reference_area_ha' => 24852.165533,
        ],
        'Bacong' => [
            'code' => 'BACONG',
            'aliases' => ['BACONG', '0704603000'],
            'psgc_code' => '1804603000',
            'legacy_psgc_code' => '074603000',
            'shape_id' => '30758251B375051523504',
            'reference_area_ha' => 4042.294731,
        ],
        'Bais City' => [
            'code' => 'BAIS_CITY',
            'aliases' => ['BAIS_CITY', 'BAISCITY', 'BAIS-CITY', '0704604000'],
            'psgc_code' => '1804604000',
            'legacy_psgc_code' => '074604000',
            'shape_id' => '30758251B97079733925928',
            'reference_area_ha' => 25248.938119,
        ],
        'Basay' => [
            'code' => 'BASAY',
            'aliases' => ['BASAY', '0704605000'],
            'psgc_code' => '1804605000',
            'legacy_psgc_code' => '074605000',
            'shape_id' => '30758251B60263736267026',
            'reference_area_ha' => 16811.145658,
        ],
        'Bindoy' => [
            'code' => 'BINDOY',
            'aliases' => ['BINDOY', '0704607000'],
            'psgc_code' => '1804607000',
            'legacy_psgc_code' => '074607000',
            'shape_id' => '30758251B7642209697633',
            'reference_area_ha' => 15653.732985,
        ],
        'Canlaon City' => [
            'code' => 'CANLAON_CITY',
            'aliases' => ['CANLAON_CITY', 'CANLAONCITY', 'CANLAON-CITY', '0704608000'],
            'psgc_code' => '1804608000',
            'legacy_psgc_code' => '074608000',
            'shape_id' => '30758251B22693356898691',
            'reference_area_ha' => 14751.859433,
        ],
        'City of Bayawan' => [
            'code' => 'BAYAWAN_CITY',
            'aliases' => ['BAYAWAN_CITY', 'BAYAWANCITY', 'BAYAWAN-CITY', '0704606000'],
            'psgc_code' => '1804606000',
            'legacy_psgc_code' => '074606000',
            'shape_id' => '30758251B98971931409152',
            'reference_area_ha' => 69964.126810,
            'workspace_name' => 'Bayawan City',
        ],
        'City of Guihulngan' => [
            'code' => 'GUIHULNGAN_CITY',
            'aliases' => ['GUIHULNGAN_CITY', 'GUIHULNGANCITY', 'GUIHULNGAN-CITY', '0704611000'],
            'psgc_code' => '1804611000',
            'legacy_psgc_code' => '074611000',
            'shape_id' => '30758251B60818257765105',
            'reference_area_ha' => 37502.314301,
            'workspace_name' => 'Guihulngan City',
        ],
        'City of Tanjay' => [
            'code' => 'TANJAY_CITY',
            'aliases' => ['TANJAY_CITY', 'TANJAYCITY', 'TANJAY-CITY', '0704621000'],
            'psgc_code' => '1804621000',
            'legacy_psgc_code' => '074621000',
            'shape_id' => '30758251B79018653951628',
            'reference_area_ha' => 22841.921660,
            'workspace_name' => 'Tanjay City',
        ],
        'Dauin' => [
            'code' => 'DAUIN',
            'aliases' => ['DAUIN', '0704609000'],
            'psgc_code' => '1804609000',
            'legacy_psgc_code' => '074609000',
            'shape_id' => '30758251B3670515091156',
            'reference_area_ha' => 8077.685876,
        ],
        'Dumaguete City' => [
            'code' => 'DUMAGUETE_CITY',
            'aliases' => ['DUMAGUETE_CITY', 'DUMAGUETECITY', 'DUMAGUETE-CITY', '0704610000'],
            'psgc_code' => '1804610000',
            'legacy_psgc_code' => '074610000',
            'shape_id' => '30758251B75296993492221',
            'reference_area_ha' => 3430.800820,
        ],
        'Jimalalud' => [
            'code' => 'JIMALALUD',
            'aliases' => ['JIMALALUD', '0704612000'],
            'psgc_code' => '1804612000',
            'legacy_psgc_code' => '074612000',
            'shape_id' => '30758251B35162320168665',
            'reference_area_ha' => 15476.719892,
        ],
        'La Libertad' => [
            'code' => 'LA_LIBERTAD',
            'aliases' => ['LA_LIBERTAD', 'LALIBERTAD', 'LA-LIBERTAD', '0704613000'],
            'psgc_code' => '1804613000',
            'legacy_psgc_code' => '074613000',
            'shape_id' => '30758251B96075835138209',
            'reference_area_ha' => 15148.227410,
        ],
        'Mabinay' => [
            'code' => 'MABINAY',
            'aliases' => ['MABINAY', '0704614000'],
            'psgc_code' => '1804614000',
            'legacy_psgc_code' => '074614000',
            'shape_id' => '30758251B10017268667222',
            'reference_area_ha' => 34614.837036,
        ],
        'Manjuyod' => [
            'code' => 'MANJUYOD',
            'aliases' => ['MANJUYOD', '0704615000'],
            'psgc_code' => '1804615000',
            'legacy_psgc_code' => '074615000',
            'shape_id' => '30758251B66064059312986',
            'reference_area_ha' => 12769.815476,
        ],
        'Pamplona' => [
            'code' => 'PAMPLONA',
            'aliases' => ['PAMPLONA', '0704616000'],
            'psgc_code' => '1804616000',
            'legacy_psgc_code' => '074616000',
            'shape_id' => '30758251B24092981342629',
            'reference_area_ha' => 22290.549506,
        ],
        'San Jose' => [
            'code' => 'SAN_JOSE_NEGROS_ORIENTAL',
            'aliases' => ['SAN_JOSE_NEGROS_ORIENTAL', 'SANJOSENEGROSORIENTAL', 'SAN-JOSE-NEGROS-ORIENTAL', '0704617000'],
            'psgc_code' => '1804617000',
            'legacy_psgc_code' => '074617000',
            'shape_id' => '30758251B66844761060701',
            'reference_area_ha' => 5179.321878,
            'workspace_name' => 'San Jose (Negros Oriental)',
        ],
        'Santa Catalina' => [
            'code' => 'SANTA_CATALINA',
            'aliases' => ['SANTA_CATALINA', 'SANTACATALINA', 'SANTA-CATALINA', '0704618000'],
            'psgc_code' => '1804618000',
            'legacy_psgc_code' => '074618000',
            'shape_id' => '30758251B88269025865066',
            'reference_area_ha' => 41438.112151,
        ],
        'Siaton' => [
            'code' => 'SIATON',
            'aliases' => ['SIATON', '0704619000'],
            'psgc_code' => '1804619000',
            'legacy_psgc_code' => '074619000',
            'shape_id' => '30758251B76231588734027',
            'reference_area_ha' => 42766.148779,
        ],
        'Sibulan' => [
            'code' => 'SIBULAN',
            'aliases' => ['SIBULAN', '0704620000'],
            'psgc_code' => '1804620000',
            'legacy_psgc_code' => '074620000',
            'shape_id' => '30758251B92406697043602',
            'reference_area_ha' => 15972.468123,
        ],
        'Tayasan' => [
            'code' => 'TAYASAN',
            'aliases' => ['TAYASAN', '0704622000'],
            'psgc_code' => '1804622000',
            'legacy_psgc_code' => '074622000',
            'shape_id' => '30758251B37133567842554',
            'reference_area_ha' => 17727.108023,
        ],
        'Valencia' => [
            'code' => 'VALENCIA',
            'aliases' => ['VALENCIA', '0704623000'],
            'psgc_code' => '1804623000',
            'legacy_psgc_code' => '074623000',
            'shape_id' => '30758251B45812920134288',
            'reference_area_ha' => 16280.599249,
        ],
        'Vallehermoso' => [
            'code' => 'VALLEHERMOSO',
            'aliases' => ['VALLEHERMOSO', '0704624000'],
            'psgc_code' => '1804624000',
            'legacy_psgc_code' => '074624000',
            'shape_id' => '30758251B9618582640277',
            'reference_area_ha' => 9274.314897,
        ],
        'Zamboanguita' => [
            'code' => 'ZAMBOANGUITA',
            'aliases' => ['ZAMBOANGUITA', '0704625000'],
            'psgc_code' => '1804625000',
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

        $this->command?->info('Ready: 25 Negros Oriental planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

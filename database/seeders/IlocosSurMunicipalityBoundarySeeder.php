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
class IlocosSurMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/ilocos_sur_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '7fcd72995bb4e3da22f284462fb50281f231e2502898fbde024c629a6fe9d718';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Alilem' => [
            'code' => 'ALILEM',
            'aliases' => ['ALILEM'],
            'psgc_code' => '0102901000',
            'legacy_psgc_code' => '012901000',
            'shape_id' => '30758251B64135628542106',
            'reference_area_ha' => 11441.433248,
        ],
        'Banayoyo' => [
            'code' => 'BANAYOYO',
            'aliases' => ['BANAYOYO'],
            'psgc_code' => '0102902000',
            'legacy_psgc_code' => '012902000',
            'shape_id' => '30758251B72662209315304',
            'reference_area_ha' => 2509.398717,
        ],
        'Bantay' => [
            'code' => 'BANTAY',
            'aliases' => ['BANTAY'],
            'psgc_code' => '0102903000',
            'legacy_psgc_code' => '012903000',
            'shape_id' => '30758251B89028394199039',
            'reference_area_ha' => 7511.539412,
        ],
        'Burgos' => [
            'code' => 'BURGOS_ILOCOS_SUR',
            'aliases' => ['BURGOS_ILOCOS_SUR'],
            'psgc_code' => '0102904000',
            'legacy_psgc_code' => '012904000',
            'shape_id' => '30758251B35633474040270',
            'reference_area_ha' => 5092.764719,
            'workspace_name' => 'Burgos (Ilocos Sur)',
        ],
        'Cabugao' => [
            'code' => 'CABUGAO',
            'aliases' => ['CABUGAO'],
            'psgc_code' => '0102905000',
            'legacy_psgc_code' => '012905000',
            'shape_id' => '30758251B86667520668841',
            'reference_area_ha' => 11221.658858,
        ],
        'Caoayan' => [
            'code' => 'CAOAYAN',
            'aliases' => ['CAOAYAN'],
            'psgc_code' => '0102907000',
            'legacy_psgc_code' => '012907000',
            'shape_id' => '30758251B94372236669127',
            'reference_area_ha' => 1751.657184,
        ],
        'Cervantes' => [
            'code' => 'CERVANTES',
            'aliases' => ['CERVANTES'],
            'psgc_code' => '0102908000',
            'legacy_psgc_code' => '012908000',
            'shape_id' => '30758251B37414079527614',
            'reference_area_ha' => 24298.974322,
        ],
        'City of Candon' => [
            'code' => 'CANDON_CITY',
            'aliases' => ['CANDON_CITY'],
            'psgc_code' => '0102906000',
            'legacy_psgc_code' => '012906000',
            'shape_id' => '30758251B9776220250356',
            'reference_area_ha' => 7757.519282,
            'workspace_name' => 'Candon City',
        ],
        'City of Vigan' => [
            'code' => 'VIGAN_CITY',
            'aliases' => ['VIGAN_CITY'],
            'psgc_code' => '0102934000',
            'legacy_psgc_code' => '012934000',
            'shape_id' => '30758251B76648161873125',
            'reference_area_ha' => 2445.842637,
            'workspace_name' => 'Vigan City',
        ],
        'Galimuyod' => [
            'code' => 'GALIMUYOD',
            'aliases' => ['GALIMUYOD'],
            'psgc_code' => '0102909000',
            'legacy_psgc_code' => '012909000',
            'shape_id' => '30758251B74693062950388',
            'reference_area_ha' => 3411.987235,
        ],
        'Gregorio del Pilar' => [
            'code' => 'GREGORIO_DEL_PILAR',
            'aliases' => ['GREGORIO_DEL_PILAR'],
            'psgc_code' => '0102910000',
            'legacy_psgc_code' => '012910000',
            'shape_id' => '30758251B56010971079591',
            'reference_area_ha' => 5407.875132,
        ],
        'Lidlidda' => [
            'code' => 'LIDLIDDA',
            'aliases' => ['LIDLIDDA'],
            'psgc_code' => '0102911000',
            'legacy_psgc_code' => '012911000',
            'shape_id' => '30758251B93741779273567',
            'reference_area_ha' => 3414.928446,
        ],
        'Magsingal' => [
            'code' => 'MAGSINGAL',
            'aliases' => ['MAGSINGAL'],
            'psgc_code' => '0102912000',
            'legacy_psgc_code' => '012912000',
            'shape_id' => '30758251B48026148837928',
            'reference_area_ha' => 8242.057916,
        ],
        'Nagbukel' => [
            'code' => 'NAGBUKEL',
            'aliases' => ['NAGBUKEL'],
            'psgc_code' => '0102913000',
            'legacy_psgc_code' => '012913000',
            'shape_id' => '30758251B8709094769418',
            'reference_area_ha' => 4242.012526,
        ],
        'Narvacan' => [
            'code' => 'NARVACAN',
            'aliases' => ['NARVACAN'],
            'psgc_code' => '0102914000',
            'legacy_psgc_code' => '012914000',
            'shape_id' => '30758251B65708530039691',
            'reference_area_ha' => 10336.613439,
        ],
        'Quirino' => [
            'code' => 'QUIRINO_ILOCOS_SUR',
            'aliases' => ['QUIRINO_ILOCOS_SUR'],
            'psgc_code' => '0102915000',
            'legacy_psgc_code' => '012915000',
            'shape_id' => '30758251B76256934940324',
            'reference_area_ha' => 18241.741152,
            'workspace_name' => 'Quirino (Ilocos Sur)',
        ],
        'Salcedo' => [
            'code' => 'SALCEDO_ILOCOS_SUR',
            'aliases' => ['SALCEDO_ILOCOS_SUR'],
            'psgc_code' => '0102916000',
            'legacy_psgc_code' => '012916000',
            'shape_id' => '30758251B7667419388736',
            'reference_area_ha' => 8296.613350,
            'workspace_name' => 'Salcedo (Ilocos Sur)',
        ],
        'San Emilio' => [
            'code' => 'SAN_EMILIO',
            'aliases' => ['SAN_EMILIO'],
            'psgc_code' => '0102917000',
            'legacy_psgc_code' => '012917000',
            'shape_id' => '30758251B68036145563068',
            'reference_area_ha' => 15144.791390,
        ],
        'San Esteban' => [
            'code' => 'SAN_ESTEBAN',
            'aliases' => ['SAN_ESTEBAN'],
            'psgc_code' => '0102918000',
            'legacy_psgc_code' => '012918000',
            'shape_id' => '30758251B46730936238037',
            'reference_area_ha' => 1741.314393,
        ],
        'San Ildefonso' => [
            'code' => 'SAN_ILDEFONSO_ILOCOS_SUR',
            'aliases' => ['SAN_ILDEFONSO_ILOCOS_SUR'],
            'psgc_code' => '0102919000',
            'legacy_psgc_code' => '012919000',
            'shape_id' => '30758251B66443331789119',
            'reference_area_ha' => 1174.221069,
            'workspace_name' => 'San Ildefonso (Ilocos Sur)',
        ],
        'San Juan' => [
            'code' => 'SAN_JUAN_ILOCOS_SUR',
            'aliases' => ['SAN_JUAN_ILOCOS_SUR'],
            'psgc_code' => '0102920000',
            'legacy_psgc_code' => '012920000',
            'shape_id' => '30758251B22872212000715',
            'reference_area_ha' => 6847.490751,
            'workspace_name' => 'San Juan (Ilocos Sur)',
        ],
        'San Vicente' => [
            'code' => 'SAN_VICENTE_ILOCOS_SUR',
            'aliases' => ['SAN_VICENTE_ILOCOS_SUR'],
            'psgc_code' => '0102921000',
            'legacy_psgc_code' => '012921000',
            'shape_id' => '30758251B3589086876715',
            'reference_area_ha' => 1321.344121,
            'workspace_name' => 'San Vicente (Ilocos Sur)',
        ],
        'Santa' => [
            'code' => 'SANTA',
            'aliases' => ['SANTA'],
            'psgc_code' => '0102922000',
            'legacy_psgc_code' => '012922000',
            'shape_id' => '30758251B7974842779568',
            'reference_area_ha' => 5923.539640,
        ],
        'Santa Catalina' => [
            'code' => 'SANTA_CATALINA_ILOCOS_SUR',
            'aliases' => ['SANTA_CATALINA_ILOCOS_SUR'],
            'psgc_code' => '0102923000',
            'legacy_psgc_code' => '012923000',
            'shape_id' => '30758251B16983791159508',
            'reference_area_ha' => 894.733246,
            'workspace_name' => 'Santa Catalina (Ilocos Sur)',
        ],
        'Santa Cruz' => [
            'code' => 'SANTA_CRUZ_ILOCOS_SUR',
            'aliases' => ['SANTA_CRUZ_ILOCOS_SUR'],
            'psgc_code' => '0102924000',
            'legacy_psgc_code' => '012924000',
            'shape_id' => '30758251B48306075925177',
            'reference_area_ha' => 9195.472182,
            'workspace_name' => 'Santa Cruz (Ilocos Sur)',
        ],
        'Santa Lucia' => [
            'code' => 'SANTA_LUCIA',
            'aliases' => ['SANTA_LUCIA'],
            'psgc_code' => '0102925000',
            'legacy_psgc_code' => '012925000',
            'shape_id' => '30758251B50348981469821',
            'reference_area_ha' => 4422.021094,
        ],
        'Santa Maria' => [
            'code' => 'SANTA_MARIA_ILOCOS_SUR',
            'aliases' => ['SANTA_MARIA_ILOCOS_SUR'],
            'psgc_code' => '0102926000',
            'legacy_psgc_code' => '012926000',
            'shape_id' => '30758251B81485398953116',
            'reference_area_ha' => 5648.753627,
            'workspace_name' => 'Santa Maria (Ilocos Sur)',
        ],
        'Santiago' => [
            'code' => 'SANTIAGO_ILOCOS_SUR',
            'aliases' => ['SANTIAGO_ILOCOS_SUR'],
            'psgc_code' => '0102927000',
            'legacy_psgc_code' => '012927000',
            'shape_id' => '30758251B25270762862446',
            'reference_area_ha' => 4775.420854,
            'workspace_name' => 'Santiago (Ilocos Sur)',
        ],
        'Santo Domingo' => [
            'code' => 'SANTO_DOMINGO_ILOCOS_SUR',
            'aliases' => ['SANTO_DOMINGO_ILOCOS_SUR'],
            'psgc_code' => '0102928000',
            'legacy_psgc_code' => '012928000',
            'shape_id' => '30758251B23074202147270',
            'reference_area_ha' => 4856.792368,
            'workspace_name' => 'Santo Domingo (Ilocos Sur)',
        ],
        'Sigay' => [
            'code' => 'SIGAY',
            'aliases' => ['SIGAY'],
            'psgc_code' => '0102929000',
            'legacy_psgc_code' => '012929000',
            'shape_id' => '30758251B41796700485463',
            'reference_area_ha' => 9223.456555,
        ],
        'Sinait' => [
            'code' => 'SINAIT',
            'aliases' => ['SINAIT'],
            'psgc_code' => '0102930000',
            'legacy_psgc_code' => '012930000',
            'shape_id' => '30758251B31766600360488',
            'reference_area_ha' => 6886.471995,
        ],
        'Sugpon' => [
            'code' => 'SUGPON',
            'aliases' => ['SUGPON'],
            'psgc_code' => '0102931000',
            'legacy_psgc_code' => '012931000',
            'shape_id' => '30758251B59811260952399',
            'reference_area_ha' => 10632.606786,
        ],
        'Suyo' => [
            'code' => 'SUYO',
            'aliases' => ['SUYO'],
            'psgc_code' => '0102932000',
            'legacy_psgc_code' => '012932000',
            'shape_id' => '30758251B93318593456282',
            'reference_area_ha' => 16362.876569,
        ],
        'Tagudin' => [
            'code' => 'TAGUDIN',
            'aliases' => ['TAGUDIN'],
            'psgc_code' => '0102933000',
            'legacy_psgc_code' => '012933000',
            'shape_id' => '30758251B59352820583653',
            'reference_area_ha' => 5851.679239,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Ilocos Sur', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: the 34 Ilocos Sur planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

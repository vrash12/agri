<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Region II planning references; run explicitly after a database backup.
 *
 * Source identities and separate Santiago scope: docs/REGION_II_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class IsabelaMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/isabela_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'b38c062f0eff2001367a37a2dbf40182f10b479ac01fde90b6bcb2141ec702b3';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Alicia' => [
            'code' => 'ALICIA_ISABELA',
            'aliases' => ['ALICIA_ISABELA'],
            'psgc_code' => '0203101000',
            'legacy_psgc_code' => '023101000',
            'shape_id' => '30758251B60271002272881',
            'reference_area_ha' => 15842.486055,
            'workspace_name' => 'Alicia (Isabela)',
        ],
        'Angadanan' => [
            'code' => 'ANGADANAN',
            'aliases' => ['ANGADANAN'],
            'psgc_code' => '0203102000',
            'legacy_psgc_code' => '023102000',
            'shape_id' => '30758251B27132260441991',
            'reference_area_ha' => 19459.791491,
        ],
        'Aurora' => [
            'code' => 'AURORA_ISABELA',
            'aliases' => ['AURORA_ISABELA'],
            'psgc_code' => '0203103000',
            'legacy_psgc_code' => '023103000',
            'shape_id' => '30758251B51443667838341',
            'reference_area_ha' => 7477.159463,
            'workspace_name' => 'Aurora (Isabela)',
        ],
        'Benito Soliven' => [
            'code' => 'BENITO_SOLIVEN',
            'aliases' => ['BENITO_SOLIVEN'],
            'psgc_code' => '0203104000',
            'legacy_psgc_code' => '023104000',
            'shape_id' => '30758251B26223191466543',
            'reference_area_ha' => 17675.973458,
        ],
        'Burgos' => [
            'code' => 'BURGOS_ISABELA',
            'aliases' => ['BURGOS_ISABELA'],
            'psgc_code' => '0203105000',
            'legacy_psgc_code' => '023105000',
            'shape_id' => '30758251B93529403516197',
            'reference_area_ha' => 7727.786584,
            'workspace_name' => 'Burgos (Isabela)',
        ],
        'Cabagan' => [
            'code' => 'CABAGAN',
            'aliases' => ['CABAGAN'],
            'psgc_code' => '0203106000',
            'legacy_psgc_code' => '023106000',
            'shape_id' => '30758251B50421545545985',
            'reference_area_ha' => 30773.203332,
        ],
        'Cabatuan' => [
            'code' => 'CABATUAN_ISABELA',
            'aliases' => ['CABATUAN_ISABELA'],
            'psgc_code' => '0203107000',
            'legacy_psgc_code' => '023107000',
            'shape_id' => '30758251B5044075759859',
            'reference_area_ha' => 6392.629045,
            'workspace_name' => 'Cabatuan (Isabela)',
        ],
        'City of Cauayan' => [
            'code' => 'CAUAYAN_CITY_ISABELA',
            'aliases' => ['CAUAYAN_CITY_ISABELA'],
            'psgc_code' => '0203108000',
            'legacy_psgc_code' => '023108000',
            'shape_id' => '30758251B60223499645568',
            'reference_area_ha' => 33668.309536,
            'workspace_name' => 'Cauayan City (Isabela)',
        ],
        'Cordon' => [
            'code' => 'CORDON',
            'aliases' => ['CORDON'],
            'psgc_code' => '0203109000',
            'legacy_psgc_code' => '023109000',
            'shape_id' => '30758251B63397111137342',
            'reference_area_ha' => 22452.909789,
        ],
        'Dinapigue' => [
            'code' => 'DINAPIGUE',
            'aliases' => ['DINAPIGUE'],
            'psgc_code' => '0203110000',
            'legacy_psgc_code' => '023110000',
            'shape_id' => '30758251B31743185560611',
            'reference_area_ha' => 95307.522082,
        ],
        'Divilacan' => [
            'code' => 'DIVILACAN',
            'aliases' => ['DIVILACAN'],
            'psgc_code' => '0203111000',
            'legacy_psgc_code' => '023111000',
            'shape_id' => '30758251B81826872650546',
            'reference_area_ha' => 52855.143456,
        ],
        'Echague' => [
            'code' => 'ECHAGUE',
            'aliases' => ['ECHAGUE'],
            'psgc_code' => '0203112000',
            'legacy_psgc_code' => '023112000',
            'shape_id' => '30758251B53772032763488',
            'reference_area_ha' => 44385.626362,
        ],
        'Gamu' => [
            'code' => 'GAMU',
            'aliases' => ['GAMU'],
            'psgc_code' => '0203113000',
            'legacy_psgc_code' => '023113000',
            'shape_id' => '30758251B3499179815070',
            'reference_area_ha' => 9663.866894,
        ],
        'Ilagan City' => [
            'code' => 'ILAGAN_CITY',
            'aliases' => ['ILAGAN_CITY'],
            'psgc_code' => '0203114000',
            'legacy_psgc_code' => '023114000',
            'shape_id' => '30758251B40091866614257',
            'reference_area_ha' => 96243.289974,
        ],
        'Jones' => [
            'code' => 'JONES',
            'aliases' => ['JONES'],
            'psgc_code' => '0203115000',
            'legacy_psgc_code' => '023115000',
            'shape_id' => '30758251B4586276697259',
            'reference_area_ha' => 46516.824673,
        ],
        'Luna' => [
            'code' => 'LUNA_ISABELA',
            'aliases' => ['LUNA_ISABELA'],
            'psgc_code' => '0203116000',
            'legacy_psgc_code' => '023116000',
            'shape_id' => '30758251B75291685490703',
            'reference_area_ha' => 4560.320019,
            'workspace_name' => 'Luna (Isabela)',
        ],
        'Maconacon' => [
            'code' => 'MACONACON',
            'aliases' => ['MACONACON'],
            'psgc_code' => '0203117000',
            'legacy_psgc_code' => '023117000',
            'shape_id' => '30758251B92607820299430',
            'reference_area_ha' => 46422.268848,
        ],
        'Delfin Albano' => [
            'code' => 'DELFIN_ALBANO',
            'aliases' => ['DELFIN_ALBANO'],
            'psgc_code' => '0203118000',
            'legacy_psgc_code' => '023118000',
            'shape_id' => '30758251B20818666994539',
            'reference_area_ha' => 17789.178482,
        ],
        'Mallig' => [
            'code' => 'MALLIG',
            'aliases' => ['MALLIG'],
            'psgc_code' => '0203119000',
            'legacy_psgc_code' => '023119000',
            'shape_id' => '30758251B62291195909479',
            'reference_area_ha' => 11621.899660,
        ],
        'Naguilian' => [
            'code' => 'NAGUILIAN_ISABELA',
            'aliases' => ['NAGUILIAN_ISABELA'],
            'psgc_code' => '0203120000',
            'legacy_psgc_code' => '023120000',
            'shape_id' => '30758251B29614901400511',
            'reference_area_ha' => 16326.110252,
            'workspace_name' => 'Naguilian (Isabela)',
        ],
        'Palanan' => [
            'code' => 'PALANAN',
            'aliases' => ['PALANAN'],
            'psgc_code' => '0203121000',
            'legacy_psgc_code' => '023121000',
            'shape_id' => '30758251B42816846115964',
            'reference_area_ha' => 37438.517428,
        ],
        'Quezon' => [
            'code' => 'QUEZON_ISABELA',
            'aliases' => ['QUEZON_ISABELA'],
            'psgc_code' => '0203122000',
            'legacy_psgc_code' => '023122000',
            'shape_id' => '30758251B61919458942758',
            'reference_area_ha' => 21650.183570,
            'workspace_name' => 'Quezon (Isabela)',
        ],
        'Quirino' => [
            'code' => 'QUIRINO_ISABELA',
            'aliases' => ['QUIRINO_ISABELA'],
            'psgc_code' => '0203123000',
            'legacy_psgc_code' => '023123000',
            'shape_id' => '30758251B90473540234186',
            'reference_area_ha' => 12553.490523,
            'workspace_name' => 'Quirino (Isabela)',
        ],
        'Ramon' => [
            'code' => 'RAMON',
            'aliases' => ['RAMON'],
            'psgc_code' => '0203124000',
            'legacy_psgc_code' => '023124000',
            'shape_id' => '30758251B59808098475352',
            'reference_area_ha' => 12632.482277,
        ],
        'Reina Mercedes' => [
            'code' => 'REINA_MERCEDES',
            'aliases' => ['REINA_MERCEDES'],
            'psgc_code' => '0203125000',
            'legacy_psgc_code' => '023125000',
            'shape_id' => '30758251B79932991691017',
            'reference_area_ha' => 5161.994899,
        ],
        'Roxas' => [
            'code' => 'ROXAS_ISABELA',
            'aliases' => ['ROXAS_ISABELA'],
            'psgc_code' => '0203126000',
            'legacy_psgc_code' => '023126000',
            'shape_id' => '30758251B43997150317842',
            'reference_area_ha' => 11895.113137,
            'workspace_name' => 'Roxas (Isabela)',
        ],
        'San Agustin' => [
            'code' => 'SAN_AGUSTIN_ISABELA',
            'aliases' => ['SAN_AGUSTIN_ISABELA'],
            'psgc_code' => '0203127000',
            'legacy_psgc_code' => '023127000',
            'shape_id' => '30758251B75696282088707',
            'reference_area_ha' => 18833.896721,
            'workspace_name' => 'San Agustin (Isabela)',
        ],
        'San Guillermo' => [
            'code' => 'SAN_GUILLERMO',
            'aliases' => ['SAN_GUILLERMO'],
            'psgc_code' => '0203128000',
            'legacy_psgc_code' => '023128000',
            'shape_id' => '30758251B48222146671596',
            'reference_area_ha' => 38659.814410,
        ],
        'San Isidro' => [
            'code' => 'SAN_ISIDRO_ISABELA',
            'aliases' => ['SAN_ISIDRO_ISABELA'],
            'psgc_code' => '0203129000',
            'legacy_psgc_code' => '023129000',
            'shape_id' => '30758251B62946220983570',
            'reference_area_ha' => 6440.483571,
            'workspace_name' => 'San Isidro (Isabela)',
        ],
        'San Manuel' => [
            'code' => 'SAN_MANUEL_ISABELA',
            'aliases' => ['SAN_MANUEL_ISABELA'],
            'psgc_code' => '0203130000',
            'legacy_psgc_code' => '023130000',
            'shape_id' => '30758251B36577910172008',
            'reference_area_ha' => 10402.471537,
            'workspace_name' => 'San Manuel (Isabela)',
        ],
        'San Mariano' => [
            'code' => 'SAN_MARIANO',
            'aliases' => ['SAN_MARIANO'],
            'psgc_code' => '0203131000',
            'legacy_psgc_code' => '023131000',
            'shape_id' => '30758251B27665954981210',
            'reference_area_ha' => 139720.048508,
        ],
        'San Mateo' => [
            'code' => 'SAN_MATEO_ISABELA',
            'aliases' => ['SAN_MATEO_ISABELA'],
            'psgc_code' => '0203132000',
            'legacy_psgc_code' => '023132000',
            'shape_id' => '30758251B54536081891976',
            'reference_area_ha' => 11039.871927,
            'workspace_name' => 'San Mateo (Isabela)',
        ],
        'San Pablo' => [
            'code' => 'SAN_PABLO_ISABELA',
            'aliases' => ['SAN_PABLO_ISABELA'],
            'psgc_code' => '0203133000',
            'legacy_psgc_code' => '023133000',
            'shape_id' => '30758251B64058966445953',
            'reference_area_ha' => 45038.816061,
            'workspace_name' => 'San Pablo (Isabela)',
        ],
        'Santa Maria' => [
            'code' => 'SANTA_MARIA_ISABELA',
            'aliases' => ['SANTA_MARIA_ISABELA'],
            'psgc_code' => '0203134000',
            'legacy_psgc_code' => '023134000',
            'shape_id' => '30758251B83702648087854',
            'reference_area_ha' => 11896.076554,
            'workspace_name' => 'Santa Maria (Isabela)',
        ],
        'Santo Tomas' => [
            'code' => 'SANTO_TOMAS_ISABELA',
            'aliases' => ['SANTO_TOMAS_ISABELA'],
            'psgc_code' => '0203136000',
            'legacy_psgc_code' => '023136000',
            'shape_id' => '30758251B99361767942703',
            'reference_area_ha' => 7729.593879,
            'workspace_name' => 'Santo Tomas (Isabela)',
        ],
        'Tumauini' => [
            'code' => 'TUMAUINI',
            'aliases' => ['TUMAUINI'],
            'psgc_code' => '0203137000',
            'legacy_psgc_code' => '023137000',
            'shape_id' => '30758251B25937724211133',
            'reference_area_ha' => 40055.949958,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Isabela', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 36 Isabela planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

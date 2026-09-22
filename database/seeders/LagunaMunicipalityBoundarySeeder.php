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
class LagunaMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/laguna_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '1668da0ecf6c71e190643a8d11c85aa2435d75d053131f768bd8f5d471264d37';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Alaminos' => [
            'code' => 'ALAMINOS_LAGUNA',
            'aliases' => ['ALAMINOS_LAGUNA'],
            'psgc_code' => '0403401000',
            'legacy_psgc_code' => '043401000',
            'shape_id' => '30758251B86762521234938',
            'reference_area_ha' => 5964.874764,
            'workspace_name' => 'Alaminos (Laguna)',
        ],
        'Bay' => [
            'code' => 'BAY',
            'aliases' => ['BAY'],
            'psgc_code' => '0403402000',
            'legacy_psgc_code' => '043402000',
            'shape_id' => '30758251B641829746077',
            'reference_area_ha' => 3837.961627,
        ],
        'City of BiÃ±an' => [
            'code' => 'CITY_OF_BIAAN',
            'aliases' => ['CITY_OF_BIAAN'],
            'psgc_code' => '0403403000',
            'legacy_psgc_code' => '043403000',
            'shape_id' => '30758251B16723889149596',
            'reference_area_ha' => 3758.713395,
        ],
        'Cabuyao City' => [
            'code' => 'CABUYAO_CITY',
            'aliases' => ['CABUYAO_CITY'],
            'psgc_code' => '0403404000',
            'legacy_psgc_code' => '043404000',
            'shape_id' => '30758251B33555947912261',
            'reference_area_ha' => 4736.920351,
        ],
        'City of Calamba' => [
            'code' => 'CITY_OF_CALAMBA_LAGUNA',
            'aliases' => ['CITY_OF_CALAMBA_LAGUNA'],
            'psgc_code' => '0403405000',
            'legacy_psgc_code' => '043405000',
            'shape_id' => '30758251B25562993690796',
            'reference_area_ha' => 13931.872962,
            'workspace_name' => 'City of Calamba (Laguna)',
        ],
        'Calauan' => [
            'code' => 'CALAUAN',
            'aliases' => ['CALAUAN'],
            'psgc_code' => '0403406000',
            'legacy_psgc_code' => '043406000',
            'shape_id' => '30758251B84649155212336',
            'reference_area_ha' => 7496.667049,
        ],
        'Cavinti' => [
            'code' => 'CAVINTI',
            'aliases' => ['CAVINTI'],
            'psgc_code' => '0403407000',
            'legacy_psgc_code' => '043407000',
            'shape_id' => '30758251B82709121735753',
            'reference_area_ha' => 11673.727400,
        ],
        'Famy' => [
            'code' => 'FAMY',
            'aliases' => ['FAMY'],
            'psgc_code' => '0403408000',
            'legacy_psgc_code' => '043408000',
            'shape_id' => '30758251B75261766623391',
            'reference_area_ha' => 3341.000774,
        ],
        'Kalayaan' => [
            'code' => 'KALAYAAN_LAGUNA',
            'aliases' => ['KALAYAAN_LAGUNA'],
            'psgc_code' => '0403409000',
            'legacy_psgc_code' => '043409000',
            'shape_id' => '30758251B7830805264743',
            'reference_area_ha' => 5358.589340,
            'workspace_name' => 'Kalayaan (Laguna)',
        ],
        'Liliw' => [
            'code' => 'LILIW',
            'aliases' => ['LILIW'],
            'psgc_code' => '0403410000',
            'legacy_psgc_code' => '043410000',
            'shape_id' => '30758251B58026034412685',
            'reference_area_ha' => 4284.451371,
        ],
        'Los BaÃ±os' => [
            'code' => 'LOS_BAAOS',
            'aliases' => ['LOS_BAAOS'],
            'psgc_code' => '0403411000',
            'legacy_psgc_code' => '043411000',
            'shape_id' => '30758251B45670091737840',
            'reference_area_ha' => 5554.619304,
        ],
        'Luisiana' => [
            'code' => 'LUISIANA',
            'aliases' => ['LUISIANA'],
            'psgc_code' => '0403412000',
            'legacy_psgc_code' => '043412000',
            'shape_id' => '30758251B80791162648573',
            'reference_area_ha' => 7497.415865,
        ],
        'Lumban' => [
            'code' => 'LUMBAN',
            'aliases' => ['LUMBAN'],
            'psgc_code' => '0403413000',
            'legacy_psgc_code' => '043413000',
            'shape_id' => '30758251B19502788585364',
            'reference_area_ha' => 8318.149355,
        ],
        'Mabitac' => [
            'code' => 'MABITAC',
            'aliases' => ['MABITAC'],
            'psgc_code' => '0403414000',
            'legacy_psgc_code' => '043414000',
            'shape_id' => '30758251B40270166730947',
            'reference_area_ha' => 4767.440191,
        ],
        'Magdalena' => [
            'code' => 'MAGDALENA',
            'aliases' => ['MAGDALENA'],
            'psgc_code' => '0403415000',
            'legacy_psgc_code' => '043415000',
            'shape_id' => '30758251B37787171987907',
            'reference_area_ha' => 3262.519825,
        ],
        'Majayjay' => [
            'code' => 'MAJAYJAY',
            'aliases' => ['MAJAYJAY'],
            'psgc_code' => '0403416000',
            'legacy_psgc_code' => '043416000',
            'shape_id' => '30758251B95762646893125',
            'reference_area_ha' => 6025.977291,
        ],
        'Nagcarlan' => [
            'code' => 'NAGCARLAN',
            'aliases' => ['NAGCARLAN'],
            'psgc_code' => '0403417000',
            'legacy_psgc_code' => '043417000',
            'shape_id' => '30758251B18586490451466',
            'reference_area_ha' => 7544.748029,
        ],
        'Paete' => [
            'code' => 'PAETE',
            'aliases' => ['PAETE'],
            'psgc_code' => '0403418000',
            'legacy_psgc_code' => '043418000',
            'shape_id' => '30758251B55553660747763',
            'reference_area_ha' => 4846.623542,
        ],
        'Pagsanjan' => [
            'code' => 'PAGSANJAN',
            'aliases' => ['PAGSANJAN'],
            'psgc_code' => '0403419000',
            'legacy_psgc_code' => '043419000',
            'shape_id' => '30758251B62240018312192',
            'reference_area_ha' => 2820.091540,
        ],
        'Pakil' => [
            'code' => 'PAKIL',
            'aliases' => ['PAKIL'],
            'psgc_code' => '0403420000',
            'legacy_psgc_code' => '043420000',
            'shape_id' => '30758251B26528649252550',
            'reference_area_ha' => 4746.439257,
        ],
        'Pangil' => [
            'code' => 'PANGIL',
            'aliases' => ['PANGIL'],
            'psgc_code' => '0403421000',
            'legacy_psgc_code' => '043421000',
            'shape_id' => '30758251B63928376402493',
            'reference_area_ha' => 5017.469272,
        ],
        'Pila' => [
            'code' => 'PILA',
            'aliases' => ['PILA'],
            'psgc_code' => '0403422000',
            'legacy_psgc_code' => '043422000',
            'shape_id' => '30758251B49530658943400',
            'reference_area_ha' => 2563.185065,
        ],
        'Rizal' => [
            'code' => 'RIZAL_LAGUNA',
            'aliases' => ['RIZAL_LAGUNA'],
            'psgc_code' => '0403423000',
            'legacy_psgc_code' => '043423000',
            'shape_id' => '30758251B93953059217751',
            'reference_area_ha' => 2328.056329,
            'workspace_name' => 'Rizal (Laguna)',
        ],
        'San Pablo City' => [
            'code' => 'SAN_PABLO_CITY_LAGUNA',
            'aliases' => ['SAN_PABLO_CITY_LAGUNA'],
            'psgc_code' => '0403424000',
            'legacy_psgc_code' => '043424000',
            'shape_id' => '30758251B96921420214384',
            'reference_area_ha' => 18481.310776,
            'workspace_name' => 'San Pablo City (Laguna)',
        ],
        'City of San Pedro' => [
            'code' => 'CITY_OF_SAN_PEDRO',
            'aliases' => ['CITY_OF_SAN_PEDRO'],
            'psgc_code' => '0403425000',
            'legacy_psgc_code' => '043425000',
            'shape_id' => '30758251B17311985979646',
            'reference_area_ha' => 2405.918848,
        ],
        'Santa Cruz' => [
            'code' => 'SANTA_CRUZ_LAGUNA',
            'aliases' => ['SANTA_CRUZ_LAGUNA'],
            'psgc_code' => '0403426000',
            'legacy_psgc_code' => '043426000',
            'shape_id' => '30758251B85886628165841',
            'reference_area_ha' => 3721.317012,
            'workspace_name' => 'Santa Cruz (Laguna)',
        ],
        'Santa Maria' => [
            'code' => 'SANTA_MARIA_LAGUNA',
            'aliases' => ['SANTA_MARIA_LAGUNA'],
            'psgc_code' => '0403427000',
            'legacy_psgc_code' => '043427000',
            'shape_id' => '30758251B60359964634827',
            'reference_area_ha' => 10895.364684,
            'workspace_name' => 'Santa Maria (Laguna)',
        ],
        'City of Santa Rosa' => [
            'code' => 'CITY_OF_SANTA_ROSA_LAGUNA',
            'aliases' => ['CITY_OF_SANTA_ROSA_LAGUNA'],
            'psgc_code' => '0403428000',
            'legacy_psgc_code' => '043428000',
            'shape_id' => '30758251B88250453342652',
            'reference_area_ha' => 5617.147279,
            'workspace_name' => 'City of Santa Rosa (Laguna)',
        ],
        'Siniloan' => [
            'code' => 'SINILOAN',
            'aliases' => ['SINILOAN'],
            'psgc_code' => '0403429000',
            'legacy_psgc_code' => '043429000',
            'shape_id' => '30758251B87471395485830',
            'reference_area_ha' => 5453.541434,
        ],
        'Victoria' => [
            'code' => 'VICTORIA_LAGUNA',
            'aliases' => ['VICTORIA_LAGUNA'],
            'psgc_code' => '0403430000',
            'legacy_psgc_code' => '043430000',
            'shape_id' => '30758251B60553088936825',
            'reference_area_ha' => 2926.921222,
            'workspace_name' => 'Victoria (Laguna)',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Laguna', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 30 Laguna planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

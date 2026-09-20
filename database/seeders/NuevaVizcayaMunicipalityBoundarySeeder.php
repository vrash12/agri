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
class NuevaVizcayaMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/nueva_vizcaya_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '3cff819dfa81894bcd824a73ac2318195e338e84282f195b65661a0fd8b3456d';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Ambaguio' => [
            'code' => 'AMBAGUIO',
            'aliases' => ['AMBAGUIO'],
            'psgc_code' => '0205001000',
            'legacy_psgc_code' => '025001000',
            'shape_id' => '30758251B68049243059466',
            'reference_area_ha' => 17747.095974,
        ],
        'Aritao' => [
            'code' => 'ARITAO',
            'aliases' => ['ARITAO'],
            'psgc_code' => '0205002000',
            'legacy_psgc_code' => '025002000',
            'shape_id' => '30758251B32564665181621',
            'reference_area_ha' => 29198.983793,
        ],
        'Bagabag' => [
            'code' => 'BAGABAG',
            'aliases' => ['BAGABAG'],
            'psgc_code' => '0205003000',
            'legacy_psgc_code' => '025003000',
            'shape_id' => '30758251B74159720492186',
            'reference_area_ha' => 14683.275634,
        ],
        'Bambang' => [
            'code' => 'BAMBANG',
            'aliases' => ['BAMBANG'],
            'psgc_code' => '0205004000',
            'legacy_psgc_code' => '025004000',
            'shape_id' => '30758251B54478604625017',
            'reference_area_ha' => 22980.290947,
        ],
        'Bayombong' => [
            'code' => 'BAYOMBONG',
            'aliases' => ['BAYOMBONG'],
            'psgc_code' => '0205005000',
            'legacy_psgc_code' => '025005000',
            'shape_id' => '30758251B92407416311106',
            'reference_area_ha' => 14252.603450,
        ],
        'Diadi' => [
            'code' => 'DIADI',
            'aliases' => ['DIADI'],
            'psgc_code' => '0205006000',
            'legacy_psgc_code' => '025006000',
            'shape_id' => '30758251B56267666888598',
            'reference_area_ha' => 21066.626579,
        ],
        'Dupax del Norte' => [
            'code' => 'DUPAX_DEL_NORTE',
            'aliases' => ['DUPAX_DEL_NORTE'],
            'psgc_code' => '0205007000',
            'legacy_psgc_code' => '025007000',
            'shape_id' => '30758251B61086740814455',
            'reference_area_ha' => 29807.728124,
        ],
        'Dupax del Sur' => [
            'code' => 'DUPAX_DEL_SUR',
            'aliases' => ['DUPAX_DEL_SUR'],
            'psgc_code' => '0205008000',
            'legacy_psgc_code' => '025008000',
            'shape_id' => '30758251B45953975988302',
            'reference_area_ha' => 41228.705611,
        ],
        'Kasibu' => [
            'code' => 'KASIBU',
            'aliases' => ['KASIBU'],
            'psgc_code' => '0205009000',
            'legacy_psgc_code' => '025009000',
            'shape_id' => '30758251B8018524244624',
            'reference_area_ha' => 61642.137955,
        ],
        'Kayapa' => [
            'code' => 'KAYAPA',
            'aliases' => ['KAYAPA'],
            'psgc_code' => '0205010000',
            'legacy_psgc_code' => '025010000',
            'shape_id' => '30758251B81196974084886',
            'reference_area_ha' => 51751.397137,
        ],
        'Quezon' => [
            'code' => 'QUEZON_NUEVA_VIZCAYA',
            'aliases' => ['QUEZON_NUEVA_VIZCAYA'],
            'psgc_code' => '0205011000',
            'legacy_psgc_code' => '025011000',
            'shape_id' => '30758251B12322451453842',
            'reference_area_ha' => 21224.899230,
            'workspace_name' => 'Quezon (Nueva Vizcaya)',
        ],
        'Santa Fe' => [
            'code' => 'SANTA_FE_NUEVA_VIZCAYA',
            'aliases' => ['SANTA_FE_NUEVA_VIZCAYA'],
            'psgc_code' => '0205012000',
            'legacy_psgc_code' => '025012000',
            'shape_id' => '30758251B84438327849244',
            'reference_area_ha' => 24712.850491,
            'workspace_name' => 'Santa Fe (Nueva Vizcaya)',
        ],
        'Solano' => [
            'code' => 'SOLANO',
            'aliases' => ['SOLANO'],
            'psgc_code' => '0205013000',
            'legacy_psgc_code' => '025013000',
            'shape_id' => '30758251B33940729335702',
            'reference_area_ha' => 8850.671155,
        ],
        'Villaverde' => [
            'code' => 'VILLAVERDE',
            'aliases' => ['VILLAVERDE'],
            'psgc_code' => '0205014000',
            'legacy_psgc_code' => '025014000',
            'shape_id' => '30758251B74337145174086',
            'reference_area_ha' => 6781.588315,
        ],
        'Alfonso Castaneda' => [
            'code' => 'ALFONSO_CASTANEDA',
            'aliases' => ['ALFONSO_CASTANEDA'],
            'psgc_code' => '0205015000',
            'legacy_psgc_code' => '025015000',
            'shape_id' => '30758251B24689590094484',
            'reference_area_ha' => 46663.297043,
            'workspace_name' => 'Alfonso Castañeda',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Nueva Vizcaya', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 15 Nueva Vizcaya planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

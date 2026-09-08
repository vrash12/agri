<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

class BenguetMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/benguet_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '6b089afa0c6d70ad949eb5deb7ce10b7a8264596510f0f3087b2065ee3bad288';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'La Trinidad' => [
            'code' => 'LATRINIDAD',
            'aliases' => ['LATRINIDAD', 'LA_TRINIDAD', 'LA-TRINIDAD'],
            'psgc_code' => '1401110000',
            'legacy_psgc_code' => '141110000',
            'shape_id' => '30758251B70129135029645',
            'reference_area_ha' => 7042.025049,
        ],
        'Atok' => [
            'code' => 'ATOK',
            'aliases' => ['ATOK'],
            'psgc_code' => '1401101000',
            'legacy_psgc_code' => '141101000',
            'shape_id' => '30758251B63625349740918',
            'reference_area_ha' => 17849.992574,
        ],
        'Tublay' => [
            'code' => 'TUBLAY',
            'aliases' => ['TUBLAY'],
            'psgc_code' => '1401114000',
            'legacy_psgc_code' => '141114000',
            'shape_id' => '30758251B5924892224435',
            'reference_area_ha' => 7629.699492,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Benguet', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION, self::MUNICIPALITIES
        );

        $this->command?->info('Ready: La Trinidad, Atok, and Tublay planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

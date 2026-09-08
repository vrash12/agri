<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

class BenguetRemainingMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/benguet_remaining_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '9ca916bb897342b9b56ae57f10e0f4dd94572ed2e453a3bf7e827d4b45e6b1a7';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bakun' => [
            'code' => 'BAKUN',
            'aliases' => ['BAKUN'],
            'psgc_code' => '1401103000',
            'legacy_psgc_code' => '141103000',
            'shape_id' => '30758251B56433040620438',
            'reference_area_ha' => 31523.852867,
        ],
        'Bokod' => [
            'code' => 'BOKOD',
            'aliases' => ['BOKOD'],
            'psgc_code' => '1401104000',
            'legacy_psgc_code' => '141104000',
            'shape_id' => '30758251B72023971813507',
            'reference_area_ha' => 39200.038630,
        ],
        'Buguias' => [
            'code' => 'BUGUIAS',
            'aliases' => ['BUGUIAS'],
            'psgc_code' => '1401105000',
            'legacy_psgc_code' => '141105000',
            'shape_id' => '30758251B42417191393765',
            'reference_area_ha' => 17638.592402,
        ],
        'Itogon' => [
            'code' => 'ITOGON',
            'aliases' => ['ITOGON'],
            'psgc_code' => '1401106000',
            'legacy_psgc_code' => '141106000',
            'shape_id' => '30758251B42509136002589',
            'reference_area_ha' => 43815.593933,
        ],
        'Kabayan' => [
            'code' => 'KABAYAN',
            'aliases' => ['KABAYAN'],
            'psgc_code' => '1401107000',
            'legacy_psgc_code' => '141107000',
            'shape_id' => '30758251B34627250160857',
            'reference_area_ha' => 19017.825829,
        ],
        'Kapangan' => [
            'code' => 'KAPANGAN',
            'aliases' => ['KAPANGAN'],
            'psgc_code' => '1401108000',
            'legacy_psgc_code' => '141108000',
            'shape_id' => '30758251B17255873954553',
            'reference_area_ha' => 18096.918408,
        ],
        'Kibungan' => [
            'code' => 'KIBUNGAN',
            'aliases' => ['KIBUNGAN'],
            'psgc_code' => '1401109000',
            'legacy_psgc_code' => '141109000',
            'shape_id' => '30758251B24433463121222',
            'reference_area_ha' => 16711.283852,
        ],
        'Mankayan' => [
            'code' => 'MANKAYAN',
            'aliases' => ['MANKAYAN'],
            'psgc_code' => '1401111000',
            'legacy_psgc_code' => '141111000',
            'shape_id' => '30758251B68871031890947',
            'reference_area_ha' => 14610.126920,
        ],
        'Sablan' => [
            'code' => 'SABLAN',
            'aliases' => ['SABLAN'],
            'psgc_code' => '1401112000',
            'legacy_psgc_code' => '141112000',
            'shape_id' => '30758251B47648783241888',
            'reference_area_ha' => 10155.429773,
        ],
        'Tuba' => [
            'code' => 'TUBA',
            'aliases' => ['TUBA'],
            'psgc_code' => '1401113000',
            'legacy_psgc_code' => '141113000',
            'shape_id' => '30758251B14138125500475',
            'reference_area_ha' => 34194.260680,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Benguet', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION, self::MUNICIPALITIES
        );

        $this->command?->info('Ready: The ten remaining Benguet municipality planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

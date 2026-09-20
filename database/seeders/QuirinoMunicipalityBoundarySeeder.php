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
class QuirinoMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/quirino_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'ce80823232e03cb7e109ad662d079f293046486897934cd427f224327414c5bf';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Aglipay' => [
            'code' => 'AGLIPAY',
            'aliases' => ['AGLIPAY'],
            'psgc_code' => '0205701000',
            'legacy_psgc_code' => '025701000',
            'shape_id' => '30758251B83245264708562',
            'reference_area_ha' => 28030.642346,
        ],
        'Cabarroguis' => [
            'code' => 'CABARROGUIS',
            'aliases' => ['CABARROGUIS'],
            'psgc_code' => '0205702000',
            'legacy_psgc_code' => '025702000',
            'shape_id' => '30758251B72889341046868',
            'reference_area_ha' => 18908.284610,
        ],
        'Diffun' => [
            'code' => 'DIFFUN',
            'aliases' => ['DIFFUN'],
            'psgc_code' => '0205703000',
            'legacy_psgc_code' => '025703000',
            'shape_id' => '30758251B78593014826051',
            'reference_area_ha' => 28786.103700,
        ],
        'Maddela' => [
            'code' => 'MADDELA',
            'aliases' => ['MADDELA'],
            'psgc_code' => '0205704000',
            'legacy_psgc_code' => '025704000',
            'shape_id' => '30758251B21617081206800',
            'reference_area_ha' => 77062.251679,
        ],
        'Saguday' => [
            'code' => 'SAGUDAY',
            'aliases' => ['SAGUDAY'],
            'psgc_code' => '0205705000',
            'legacy_psgc_code' => '025705000',
            'shape_id' => '30758251B76917628973328',
            'reference_area_ha' => 5016.993116,
        ],
        'Nagtipunan' => [
            'code' => 'NAGTIPUNAN',
            'aliases' => ['NAGTIPUNAN'],
            'psgc_code' => '0205706000',
            'legacy_psgc_code' => '025706000',
            'shape_id' => '30758251B72907275700967',
            'reference_area_ha' => 118996.702835,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Quirino', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 6 Quirino planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

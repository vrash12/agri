<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Region III planning references; run explicitly after a database backup.
 *
 * Source identities and city supervision: docs/REGION_III_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class AuroraMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/aurora_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '6cfa78e556a476925069ecdfd52e9d8c2880413558b16d566d0bfa1c75a4db29';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Baler' => [
            'code' => 'BALER',
            'aliases' => ['BALER'],
            'psgc_code' => '0307701000',
            'legacy_psgc_code' => '037701000',
            'shape_id' => '30758251B90201741515096',
            'reference_area_ha' => 9959.587525,
        ],
        'Casiguran' => [
            'code' => 'CASIGURAN_AURORA',
            'aliases' => ['CASIGURAN_AURORA'],
            'psgc_code' => '0307702000',
            'legacy_psgc_code' => '037702000',
            'shape_id' => '30758251B87187135187970',
            'reference_area_ha' => 42011.503969,
            'workspace_name' => 'Casiguran (Aurora)',
        ],
        'Dilasag' => [
            'code' => 'DILASAG',
            'aliases' => ['DILASAG'],
            'psgc_code' => '0307703000',
            'legacy_psgc_code' => '037703000',
            'shape_id' => '30758251B50259762505671',
            'reference_area_ha' => 49548.013420,
        ],
        'Dinalungan' => [
            'code' => 'DINALUNGAN',
            'aliases' => ['DINALUNGAN'],
            'psgc_code' => '0307704000',
            'legacy_psgc_code' => '037704000',
            'shape_id' => '30758251B88004943266135',
            'reference_area_ha' => 32633.961071,
        ],
        'Dingalan' => [
            'code' => 'DINGALAN',
            'aliases' => ['DINGALAN'],
            'psgc_code' => '0307705000',
            'legacy_psgc_code' => '037705000',
            'shape_id' => '30758251B91173779963117',
            'reference_area_ha' => 35436.770599,
        ],
        'Dipaculao' => [
            'code' => 'DIPACULAO',
            'aliases' => ['DIPACULAO'],
            'psgc_code' => '0307706000',
            'legacy_psgc_code' => '037706000',
            'shape_id' => '30758251B11219690177973',
            'reference_area_ha' => 40065.934114,
        ],
        'Maria Aurora' => [
            'code' => 'MARIA_AURORA',
            'aliases' => ['MARIA_AURORA'],
            'psgc_code' => '0307707000',
            'legacy_psgc_code' => '037707000',
            'shape_id' => '30758251B2954727825602',
            'reference_area_ha' => 33553.577667,
        ],
        'San Luis' => [
            'code' => 'SAN_LUIS_AURORA',
            'aliases' => ['SAN_LUIS_AURORA'],
            'psgc_code' => '0307708000',
            'legacy_psgc_code' => '037708000',
            'shape_id' => '30758251B29730451964766',
            'reference_area_ha' => 59834.608554,
            'workspace_name' => 'San Luis (Aurora)',
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Aurora', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 8 Aurora planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

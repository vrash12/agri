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
class LaUnionMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/la_union_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'b32c5e1a410d10bdfd4c052dd759d3841ff964f66f7daea85e469d124770671c';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Agoo' => [
            'code' => 'AGOO',
            'aliases' => ['AGOO'],
            'psgc_code' => '0103301000',
            'legacy_psgc_code' => '013301000',
            'shape_id' => '30758251B44987687400589',
            'reference_area_ha' => 4814.597423,
        ],
        'Aringay' => [
            'code' => 'ARINGAY',
            'aliases' => ['ARINGAY'],
            'psgc_code' => '0103302000',
            'legacy_psgc_code' => '013302000',
            'shape_id' => '30758251B96546251931007',
            'reference_area_ha' => 9719.034315,
        ],
        'Bacnotan' => [
            'code' => 'BACNOTAN',
            'aliases' => ['BACNOTAN'],
            'psgc_code' => '0103303000',
            'legacy_psgc_code' => '013303000',
            'shape_id' => '30758251B79119480748241',
            'reference_area_ha' => 7640.350706,
        ],
        'Bagulin' => [
            'code' => 'BAGULIN',
            'aliases' => ['BAGULIN'],
            'psgc_code' => '0103304000',
            'legacy_psgc_code' => '013304000',
            'shape_id' => '30758251B85285313745351',
            'reference_area_ha' => 9057.080831,
        ],
        'Balaoan' => [
            'code' => 'BALAOAN',
            'aliases' => ['BALAOAN'],
            'psgc_code' => '0103305000',
            'legacy_psgc_code' => '013305000',
            'shape_id' => '30758251B45278202455383',
            'reference_area_ha' => 6202.548386,
        ],
        'Bangar' => [
            'code' => 'BANGAR',
            'aliases' => ['BANGAR'],
            'psgc_code' => '0103306000',
            'legacy_psgc_code' => '013306000',
            'shape_id' => '30758251B10509197504559',
            'reference_area_ha' => 3522.147226,
        ],
        'Bauang' => [
            'code' => 'BAUANG',
            'aliases' => ['BAUANG'],
            'psgc_code' => '0103307000',
            'legacy_psgc_code' => '013307000',
            'shape_id' => '30758251B39876538190612',
            'reference_area_ha' => 7580.636746,
        ],
        'Burgos' => [
            'code' => 'BURGOS_LA_UNION',
            'aliases' => ['BURGOS_LA_UNION'],
            'psgc_code' => '0103308000',
            'legacy_psgc_code' => '013308000',
            'shape_id' => '30758251B56891504947345',
            'reference_area_ha' => 3645.546852,
            'workspace_name' => 'Burgos (La Union)',
        ],
        'Caba' => [
            'code' => 'CABA',
            'aliases' => ['CABA'],
            'psgc_code' => '0103309000',
            'legacy_psgc_code' => '013309000',
            'shape_id' => '30758251B6644033388737',
            'reference_area_ha' => 4673.108868,
        ],
        'City of San Fernando' => [
            'code' => 'SAN_FERNANDO_CITY_LA_UNION',
            'aliases' => ['SAN_FERNANDO_CITY_LA_UNION'],
            'psgc_code' => '0103314000',
            'legacy_psgc_code' => '013314000',
            'shape_id' => '30758251B73043571821095',
            'reference_area_ha' => 9889.084852,
            'workspace_name' => 'San Fernando City (La Union)',
        ],
        'Luna' => [
            'code' => 'LUNA_LA_UNION',
            'aliases' => ['LUNA_LA_UNION'],
            'psgc_code' => '0103310000',
            'legacy_psgc_code' => '013310000',
            'shape_id' => '30758251B16717842706250',
            'reference_area_ha' => 4050.564979,
            'workspace_name' => 'Luna (La Union)',
        ],
        'Naguilian' => [
            'code' => 'NAGUILIAN_LA_UNION',
            'aliases' => ['NAGUILIAN_LA_UNION'],
            'psgc_code' => '0103311000',
            'legacy_psgc_code' => '013311000',
            'shape_id' => '30758251B47724455039703',
            'reference_area_ha' => 10244.518708,
            'workspace_name' => 'Naguilian (La Union)',
        ],
        'Pugo' => [
            'code' => 'PUGO',
            'aliases' => ['PUGO'],
            'psgc_code' => '0103312000',
            'legacy_psgc_code' => '013312000',
            'shape_id' => '30758251B7939243888170',
            'reference_area_ha' => 5207.680699,
        ],
        'Rosario' => [
            'code' => 'ROSARIO_LA_UNION',
            'aliases' => ['ROSARIO_LA_UNION'],
            'psgc_code' => '0103313000',
            'legacy_psgc_code' => '013313000',
            'shape_id' => '30758251B6500282829815',
            'reference_area_ha' => 7835.602667,
            'workspace_name' => 'Rosario (La Union)',
        ],
        'San Gabriel' => [
            'code' => 'SAN_GABRIEL',
            'aliases' => ['SAN_GABRIEL'],
            'psgc_code' => '0103315000',
            'legacy_psgc_code' => '013315000',
            'shape_id' => '30758251B9999882220346',
            'reference_area_ha' => 12179.984879,
        ],
        'San Juan' => [
            'code' => 'SAN_JUAN_LA_UNION',
            'aliases' => ['SAN_JUAN_LA_UNION'],
            'psgc_code' => '0103316000',
            'legacy_psgc_code' => '013316000',
            'shape_id' => '30758251B15811216691174',
            'reference_area_ha' => 5059.452679,
            'workspace_name' => 'San Juan (La Union)',
        ],
        'Santo Tomas' => [
            'code' => 'SANTO_TOMAS_LA_UNION',
            'aliases' => ['SANTO_TOMAS_LA_UNION'],
            'psgc_code' => '0103317000',
            'legacy_psgc_code' => '013317000',
            'shape_id' => '30758251B23413436311315',
            'reference_area_ha' => 4098.131270,
            'workspace_name' => 'Santo Tomas (La Union)',
        ],
        'Santol' => [
            'code' => 'SANTOL',
            'aliases' => ['SANTOL'],
            'psgc_code' => '0103318000',
            'legacy_psgc_code' => '013318000',
            'shape_id' => '30758251B51477832736818',
            'reference_area_ha' => 11417.040330,
        ],
        'Sudipen' => [
            'code' => 'SUDIPEN',
            'aliases' => ['SUDIPEN'],
            'psgc_code' => '0103319000',
            'legacy_psgc_code' => '013319000',
            'shape_id' => '30758251B50085603864193',
            'reference_area_ha' => 8986.288913,
        ],
        'Tubao' => [
            'code' => 'TUBAO',
            'aliases' => ['TUBAO'],
            'psgc_code' => '0103320000',
            'legacy_psgc_code' => '013320000',
            'shape_id' => '30758251B29046135341619',
            'reference_area_ha' => 5433.687621,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'La Union', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: the 20 La Union planning/reference geofences are active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

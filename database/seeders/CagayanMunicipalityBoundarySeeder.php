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
class CagayanMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/cagayan_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'b23d4ede17bc2910bbce9eb63056fea110d8123efec7d1e52172b469ea5110bc';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Abulug' => [
            'code' => 'ABULUG',
            'aliases' => ['ABULUG'],
            'psgc_code' => '0201501000',
            'legacy_psgc_code' => '021501000',
            'shape_id' => '30758251B36710233170574',
            'reference_area_ha' => 13266.820361,
        ],
        'Alcala' => [
            'code' => 'ALCALA_CAGAYAN',
            'aliases' => ['ALCALA_CAGAYAN'],
            'psgc_code' => '0201502000',
            'legacy_psgc_code' => '021502000',
            'shape_id' => '30758251B10617727048702',
            'reference_area_ha' => 18052.095846,
            'workspace_name' => 'Alcala (Cagayan)',
        ],
        'Allacapan' => [
            'code' => 'ALLACAPAN',
            'aliases' => ['ALLACAPAN'],
            'psgc_code' => '0201503000',
            'legacy_psgc_code' => '021503000',
            'shape_id' => '30758251B86595368402227',
            'reference_area_ha' => 23064.760890,
        ],
        'Amulung' => [
            'code' => 'AMULUNG',
            'aliases' => ['AMULUNG'],
            'psgc_code' => '0201504000',
            'legacy_psgc_code' => '021504000',
            'shape_id' => '30758251B10377684558144',
            'reference_area_ha' => 28758.734653,
        ],
        'Aparri' => [
            'code' => 'APARRI',
            'aliases' => ['APARRI'],
            'psgc_code' => '0201505000',
            'legacy_psgc_code' => '021505000',
            'shape_id' => '30758251B87752241622108',
            'reference_area_ha' => 26127.130316,
        ],
        'Baggao' => [
            'code' => 'BAGGAO',
            'aliases' => ['BAGGAO'],
            'psgc_code' => '0201506000',
            'legacy_psgc_code' => '021506000',
            'shape_id' => '30758251B17539135337219',
            'reference_area_ha' => 92744.434767,
        ],
        'Ballesteros' => [
            'code' => 'BALLESTEROS',
            'aliases' => ['BALLESTEROS'],
            'psgc_code' => '0201507000',
            'legacy_psgc_code' => '021507000',
            'shape_id' => '30758251B49973431010235',
            'reference_area_ha' => 12942.991914,
        ],
        'Buguey' => [
            'code' => 'BUGUEY',
            'aliases' => ['BUGUEY'],
            'psgc_code' => '0201508000',
            'legacy_psgc_code' => '021508000',
            'shape_id' => '30758251B28234662478026',
            'reference_area_ha' => 12882.789145,
        ],
        'Calayan' => [
            'code' => 'CALAYAN',
            'aliases' => ['CALAYAN'],
            'psgc_code' => '0201509000',
            'legacy_psgc_code' => '021509000',
            'shape_id' => '30758251B50105091710963',
            'reference_area_ha' => 50271.557906,
        ],
        'Camalaniugan' => [
            'code' => 'CAMALANIUGAN',
            'aliases' => ['CAMALANIUGAN'],
            'psgc_code' => '0201510000',
            'legacy_psgc_code' => '021510000',
            'shape_id' => '30758251B52901607273477',
            'reference_area_ha' => 8920.384130,
        ],
        'Claveria' => [
            'code' => 'CLAVERIA_CAGAYAN',
            'aliases' => ['CLAVERIA_CAGAYAN'],
            'psgc_code' => '0201511000',
            'legacy_psgc_code' => '021511000',
            'shape_id' => '30758251B2770524616489',
            'reference_area_ha' => 12894.301299,
            'workspace_name' => 'Claveria (Cagayan)',
        ],
        'Enrile' => [
            'code' => 'ENRILE',
            'aliases' => ['ENRILE'],
            'psgc_code' => '0201512000',
            'legacy_psgc_code' => '021512000',
            'shape_id' => '30758251B30056946994693',
            'reference_area_ha' => 15246.568180,
        ],
        'Gattaran' => [
            'code' => 'GATTARAN',
            'aliases' => ['GATTARAN'],
            'psgc_code' => '0201513000',
            'legacy_psgc_code' => '021513000',
            'shape_id' => '30758251B93060635103686',
            'reference_area_ha' => 63895.865387,
        ],
        'Gonzaga' => [
            'code' => 'GONZAGA',
            'aliases' => ['GONZAGA'],
            'psgc_code' => '0201514000',
            'legacy_psgc_code' => '021514000',
            'shape_id' => '30758251B98012473462325',
            'reference_area_ha' => 52745.675869,
        ],
        'Iguig' => [
            'code' => 'IGUIG',
            'aliases' => ['IGUIG'],
            'psgc_code' => '0201515000',
            'legacy_psgc_code' => '021515000',
            'shape_id' => '30758251B88140585742756',
            'reference_area_ha' => 7818.551936,
        ],
        'Lal-Lo' => [
            'code' => 'LAL_LO',
            'aliases' => ['LAL_LO'],
            'psgc_code' => '0201516000',
            'legacy_psgc_code' => '021516000',
            'shape_id' => '30758251B85734212591616',
            'reference_area_ha' => 64155.068913,
        ],
        'Lasam' => [
            'code' => 'LASAM',
            'aliases' => ['LASAM'],
            'psgc_code' => '0201517000',
            'legacy_psgc_code' => '021517000',
            'shape_id' => '30758251B73427064919579',
            'reference_area_ha' => 20696.445226,
        ],
        'Pamplona' => [
            'code' => 'PAMPLONA_CAGAYAN',
            'aliases' => ['PAMPLONA_CAGAYAN'],
            'psgc_code' => '0201518000',
            'legacy_psgc_code' => '021518000',
            'shape_id' => '30758251B95967075879397',
            'reference_area_ha' => 21308.244328,
            'workspace_name' => 'Pamplona (Cagayan)',
        ],
        'Peñablanca' => [
            'code' => 'PENABLANCA',
            'aliases' => ['PENABLANCA'],
            'psgc_code' => '0201519000',
            'legacy_psgc_code' => '021519000',
            'shape_id' => '30758251B24835314311291',
            'reference_area_ha' => 113885.815589,
        ],
        'Piat' => [
            'code' => 'PIAT',
            'aliases' => ['PIAT'],
            'psgc_code' => '0201520000',
            'legacy_psgc_code' => '021520000',
            'shape_id' => '30758251B70490734398893',
            'reference_area_ha' => 13202.980011,
        ],
        'Rizal' => [
            'code' => 'RIZAL_CAGAYAN',
            'aliases' => ['RIZAL_CAGAYAN'],
            'psgc_code' => '0201521000',
            'legacy_psgc_code' => '021521000',
            'shape_id' => '30758251B45096657077891',
            'reference_area_ha' => 34004.708754,
            'workspace_name' => 'Rizal (Cagayan)',
        ],
        'Sanchez-Mira' => [
            'code' => 'SANCHEZ_MIRA',
            'aliases' => ['SANCHEZ_MIRA'],
            'psgc_code' => '0201522000',
            'legacy_psgc_code' => '021522000',
            'shape_id' => '30758251B62706464581778',
            'reference_area_ha' => 13830.919560,
            'workspace_name' => 'Sanchez Mira',
        ],
        'Santa Ana' => [
            'code' => 'SANTA_ANA_CAGAYAN',
            'aliases' => ['SANTA_ANA_CAGAYAN'],
            'psgc_code' => '0201523000',
            'legacy_psgc_code' => '021523000',
            'shape_id' => '30758251B2806913705882',
            'reference_area_ha' => 43478.019335,
            'workspace_name' => 'Santa Ana (Cagayan)',
        ],
        'Santa Praxedes' => [
            'code' => 'SANTA_PRAXEDES',
            'aliases' => ['SANTA_PRAXEDES'],
            'psgc_code' => '0201524000',
            'legacy_psgc_code' => '021524000',
            'shape_id' => '30758251B96523663514621',
            'reference_area_ha' => 8627.006175,
        ],
        'Santa Teresita' => [
            'code' => 'SANTA_TERESITA_CAGAYAN',
            'aliases' => ['SANTA_TERESITA_CAGAYAN'],
            'psgc_code' => '0201525000',
            'legacy_psgc_code' => '021525000',
            'shape_id' => '30758251B86029010724112',
            'reference_area_ha' => 12757.227811,
            'workspace_name' => 'Santa Teresita (Cagayan)',
        ],
        'Santo Niño' => [
            'code' => 'SANTO_NINO_CAGAYAN',
            'aliases' => ['SANTO_NINO_CAGAYAN'],
            'psgc_code' => '0201526000',
            'legacy_psgc_code' => '021526000',
            'shape_id' => '30758251B40207314243633',
            'reference_area_ha' => 36126.160168,
            'workspace_name' => 'Santo Niño (Cagayan)',
        ],
        'Solana' => [
            'code' => 'SOLANA',
            'aliases' => ['SOLANA'],
            'psgc_code' => '0201527000',
            'legacy_psgc_code' => '021527000',
            'shape_id' => '30758251B34223591098012',
            'reference_area_ha' => 27293.175874,
        ],
        'Tuao' => [
            'code' => 'TUAO',
            'aliases' => ['TUAO'],
            'psgc_code' => '0201528000',
            'legacy_psgc_code' => '021528000',
            'shape_id' => '30758251B28856028744619',
            'reference_area_ha' => 18791.374183,
        ],
        'Tuguegarao City' => [
            'code' => 'TUGUEGARAO_CITY',
            'aliases' => ['TUGUEGARAO_CITY'],
            'psgc_code' => '0201529000',
            'legacy_psgc_code' => '021529000',
            'shape_id' => '30758251B86340482939237',
            'reference_area_ha' => 11959.776818,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Cagayan', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 29 Cagayan planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

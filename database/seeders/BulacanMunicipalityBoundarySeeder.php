<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

class BulacanMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/bulacan_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'd58ea603bba4cc74c86e9949df9f0dad3fba3c4b54695de326a3af4e62682a16';

    private const SOURCE_REVISION = '9469f09';

    /**
     * The province-level reference these municipality boundaries replace.
     *
     * A province polygon contains every municipality inside it, so both cannot stay active under
     * the overlap rule. Exactly this reference is archived, with an audit event, inside the same
     * transaction; re-run BulacanProvinceBoundarySeeder to restore the province-level view.
     */
    private const SUPERSEDED_BOUNDARY = 'Bulacan Province Planning Reference · geoBoundaries 2020';

    /**
     * Bulakan keeps the local LGU spelling because its source name is identical to the Bulacan
     * province workspace, and the three component cities follow the existing "Tarlac City" wording.
     */
    private const MUNICIPALITIES = [
        'Angat' => [
            'code' => 'ANGAT',
            'aliases' => ['ANGAT'],
            'psgc_code' => '0301401000',
            'legacy_psgc_code' => '031401000',
            'shape_id' => '30758251B11674695503139',
            'reference_area_ha' => 4940.828980,
        ],
        'Balagtas' => [
            'code' => 'BALAGTAS',
            'aliases' => ['BALAGTAS'],
            'psgc_code' => '0301402000',
            'legacy_psgc_code' => '031402000',
            'shape_id' => '30758251B61200223936905',
            'reference_area_ha' => 2148.062701,
        ],
        'Baliuag' => [
            'code' => 'BALIUAG',
            'aliases' => ['BALIUAG'],
            'psgc_code' => '0301403000',
            'legacy_psgc_code' => '031403000',
            'shape_id' => '30758251B80677147871069',
            'reference_area_ha' => 4575.989446,
        ],
        'Bocaue' => [
            'code' => 'BOCAUE',
            'aliases' => ['BOCAUE'],
            'psgc_code' => '0301404000',
            'legacy_psgc_code' => '031404000',
            'shape_id' => '30758251B34406453293214',
            'reference_area_ha' => 2635.251149,
        ],
        'Bulacan' => [
            'code' => 'BULAKAN',
            'aliases' => ['BULAKAN'],
            'workspace_name' => 'Bulakan',
            'psgc_code' => '0301405000',
            'legacy_psgc_code' => '031405000',
            'shape_id' => '30758251B88033392671893',
            'reference_area_ha' => 7233.712005,
        ],
        'Bustos' => [
            'code' => 'BUSTOS',
            'aliases' => ['BUSTOS'],
            'psgc_code' => '0301406000',
            'legacy_psgc_code' => '031406000',
            'shape_id' => '30758251B3009870248876',
            'reference_area_ha' => 3986.503138,
        ],
        'Calumpit' => [
            'code' => 'CALUMPIT',
            'aliases' => ['CALUMPIT'],
            'psgc_code' => '0301407000',
            'legacy_psgc_code' => '031407000',
            'shape_id' => '30758251B47768966638997',
            'reference_area_ha' => 4680.052201,
        ],
        'City of Malolos' => [
            'code' => 'MALOLOS_CITY',
            'aliases' => ['MALOLOS_CITY', 'MALOLOSCITY', 'MALOLOS-CITY', 'MALOLOS'],
            'workspace_name' => 'Malolos City',
            'psgc_code' => '0301410000',
            'legacy_psgc_code' => '031410000',
            'shape_id' => '30758251B20138524188329',
            'reference_area_ha' => 7083.791752,
        ],
        'City of Meycauayan' => [
            'code' => 'MEYCAUAYAN_CITY',
            'aliases' => ['MEYCAUAYAN_CITY', 'MEYCAUAYANCITY', 'MEYCAUAYAN-CITY', 'MEYCAUAYAN'],
            'workspace_name' => 'Meycauayan City',
            'psgc_code' => '0301412000',
            'legacy_psgc_code' => '031412000',
            'shape_id' => '30758251B3506482067844',
            'reference_area_ha' => 3178.264801,
        ],
        'City of San Jose del Monte' => [
            'code' => 'SAN_JOSE_DEL_MONTE_CITY',
            'aliases' => ['SAN_JOSE_DEL_MONTE_CITY', 'SANJOSEDELMONTECITY', 'SAN-JOSE-DEL-MONTE-CITY', 'SJDM'],
            'workspace_name' => 'San Jose del Monte City',
            'psgc_code' => '0301420000',
            'legacy_psgc_code' => '031420000',
            'shape_id' => '30758251B35007836972970',
            'reference_area_ha' => 10785.680259,
        ],
        'Doña Remedios Trinidad' => [
            'code' => 'DONA_REMEDIOS_TRINIDAD',
            'aliases' => ['DONA_REMEDIOS_TRINIDAD', 'DONAREMEDIOSTRINIDAD', 'DONA-REMEDIOS-TRINIDAD', 'DRT'],
            'psgc_code' => '0301424000',
            'legacy_psgc_code' => '031424000',
            'shape_id' => '30758251B33129254568465',
            'reference_area_ha' => 97549.082447,
        ],
        'Guiguinto' => [
            'code' => 'GUIGUINTO',
            'aliases' => ['GUIGUINTO'],
            'psgc_code' => '0301408000',
            'legacy_psgc_code' => '031408000',
            'shape_id' => '30758251B84945919751232',
            'reference_area_ha' => 2231.750386,
        ],
        'Hagonoy' => [
            'code' => 'HAGONOY',
            'aliases' => ['HAGONOY'],
            'psgc_code' => '0301409000',
            'legacy_psgc_code' => '031409000',
            'shape_id' => '30758251B93181595327102',
            'reference_area_ha' => 8339.439846,
        ],
        'Marilao' => [
            'code' => 'MARILAO',
            'aliases' => ['MARILAO'],
            'psgc_code' => '0301411000',
            'legacy_psgc_code' => '031411000',
            'shape_id' => '30758251B11566455240441',
            'reference_area_ha' => 2834.295875,
        ],
        'Norzagaray' => [
            'code' => 'NORZAGARAY',
            'aliases' => ['NORZAGARAY'],
            'psgc_code' => '0301413000',
            'legacy_psgc_code' => '031413000',
            'shape_id' => '30758251B28396415682182',
            'reference_area_ha' => 29738.026156,
        ],
        'Obando' => [
            'code' => 'OBANDO',
            'aliases' => ['OBANDO'],
            'psgc_code' => '0301414000',
            'legacy_psgc_code' => '031414000',
            'shape_id' => '30758251B34403699619291',
            'reference_area_ha' => 1620.758216,
        ],
        'Pandi' => [
            'code' => 'PANDI',
            'aliases' => ['PANDI'],
            'psgc_code' => '0301415000',
            'legacy_psgc_code' => '031415000',
            'shape_id' => '30758251B75610884117479',
            'reference_area_ha' => 5041.772126,
        ],
        'Paombong' => [
            'code' => 'PAOMBONG',
            'aliases' => ['PAOMBONG'],
            'psgc_code' => '0301416000',
            'legacy_psgc_code' => '031416000',
            'shape_id' => '30758251B84355932760435',
            'reference_area_ha' => 4528.503145,
        ],
        'Plaridel' => [
            'code' => 'PLARIDEL',
            'aliases' => ['PLARIDEL'],
            'psgc_code' => '0301417000',
            'legacy_psgc_code' => '031417000',
            'shape_id' => '30758251B39966399473469',
            'reference_area_ha' => 3574.236485,
        ],
        'Pulilan' => [
            'code' => 'PULILAN',
            'aliases' => ['PULILAN'],
            'psgc_code' => '0301418000',
            'legacy_psgc_code' => '031418000',
            'shape_id' => '30758251B69399459557735',
            'reference_area_ha' => 4264.445362,
        ],
        'San Ildefonso' => [
            'code' => 'SAN_ILDEFONSO',
            'aliases' => ['SAN_ILDEFONSO', 'SANILDEFONSO', 'SAN-ILDEFONSO'],
            'psgc_code' => '0301419000',
            'legacy_psgc_code' => '031419000',
            'shape_id' => '30758251B21292953325397',
            'reference_area_ha' => 16657.582365,
        ],
        'San Miguel' => [
            'code' => 'SAN_MIGUEL',
            'aliases' => ['SAN_MIGUEL', 'SANMIGUEL', 'SAN-MIGUEL'],
            'psgc_code' => '0301421000',
            'legacy_psgc_code' => '031421000',
            'shape_id' => '30758251B36193121708082',
            'reference_area_ha' => 23194.039833,
        ],
        'San Rafael' => [
            'code' => 'SAN_RAFAEL',
            'aliases' => ['SAN_RAFAEL', 'SANRAFAEL', 'SAN-RAFAEL'],
            'psgc_code' => '0301422000',
            'legacy_psgc_code' => '031422000',
            'shape_id' => '30758251B96236511516175',
            'reference_area_ha' => 10165.635513,
        ],
        'Santa Maria' => [
            'code' => 'SANTA_MARIA',
            'aliases' => ['SANTA_MARIA', 'SANTAMARIA', 'SANTA-MARIA'],
            'psgc_code' => '0301423000',
            'legacy_psgc_code' => '031423000',
            'shape_id' => '30758251B94770277751528',
            'reference_area_ha' => 7911.849411,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Bulacan', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES, self::SUPERSEDED_BOUNDARY
        );

        $this->command?->info('Ready: the 24 Bulacan city and municipality planning/reference geofences are active.');
        $this->command?->warn('Any active Bulacan province reference was archived; these approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

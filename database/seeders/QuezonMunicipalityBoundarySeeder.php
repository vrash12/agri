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
class QuezonMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/quezon_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'f55a6578b08ebafb311da6a555c26aca8754afe9b7defb839a74a4697dca4e71';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Agdangan' => [
            'code' => 'AGDANGAN',
            'aliases' => ['AGDANGAN'],
            'psgc_code' => '0405601000',
            'legacy_psgc_code' => '045601000',
            'shape_id' => '30758251B21979375590034',
            'reference_area_ha' => 3558.758147,
        ],
        'Alabat' => [
            'code' => 'ALABAT',
            'aliases' => ['ALABAT'],
            'psgc_code' => '0405602000',
            'legacy_psgc_code' => '045602000',
            'shape_id' => '30758251B17731917702935',
            'reference_area_ha' => 6041.180999,
        ],
        'Atimonan' => [
            'code' => 'ATIMONAN',
            'aliases' => ['ATIMONAN'],
            'psgc_code' => '0405603000',
            'legacy_psgc_code' => '045603000',
            'shape_id' => '30758251B55496023241087',
            'reference_area_ha' => 22129.925089,
        ],
        'Buenavista' => [
            'code' => 'BUENAVISTA_QUEZON',
            'aliases' => ['BUENAVISTA_QUEZON'],
            'psgc_code' => '0405605000',
            'legacy_psgc_code' => '045605000',
            'shape_id' => '30758251B56825818240160',
            'reference_area_ha' => 17134.365914,
            'workspace_name' => 'Buenavista (Quezon)',
        ],
        'Burdeos' => [
            'code' => 'BURDEOS',
            'aliases' => ['BURDEOS'],
            'psgc_code' => '0405606000',
            'legacy_psgc_code' => '045606000',
            'shape_id' => '30758251B95926954005559',
            'reference_area_ha' => 26502.722335,
        ],
        'Calauag' => [
            'code' => 'CALAUAG',
            'aliases' => ['CALAUAG'],
            'psgc_code' => '0405607000',
            'legacy_psgc_code' => '045607000',
            'shape_id' => '30758251B23417388477170',
            'reference_area_ha' => 31252.541817,
        ],
        'Candelaria' => [
            'code' => 'CANDELARIA_QUEZON',
            'aliases' => ['CANDELARIA_QUEZON'],
            'psgc_code' => '0405608000',
            'legacy_psgc_code' => '045608000',
            'shape_id' => '30758251B8297430856104',
            'reference_area_ha' => 13675.403751,
            'workspace_name' => 'Candelaria (Quezon)',
        ],
        'Catanauan' => [
            'code' => 'CATANAUAN',
            'aliases' => ['CATANAUAN'],
            'psgc_code' => '0405610000',
            'legacy_psgc_code' => '045610000',
            'shape_id' => '30758251B26905221956095',
            'reference_area_ha' => 25334.687925,
        ],
        'Dolores' => [
            'code' => 'DOLORES_QUEZON',
            'aliases' => ['DOLORES_QUEZON'],
            'psgc_code' => '0405615000',
            'legacy_psgc_code' => '045615000',
            'shape_id' => '30758251B18917837863618',
            'reference_area_ha' => 6596.861222,
            'workspace_name' => 'Dolores (Quezon)',
        ],
        'General Luna' => [
            'code' => 'GENERAL_LUNA_QUEZON',
            'aliases' => ['GENERAL_LUNA_QUEZON'],
            'psgc_code' => '0405616000',
            'legacy_psgc_code' => '045616000',
            'shape_id' => '30758251B37823444097828',
            'reference_area_ha' => 10006.465952,
            'workspace_name' => 'General Luna (Quezon)',
        ],
        'General Nakar' => [
            'code' => 'GENERAL_NAKAR',
            'aliases' => ['GENERAL_NAKAR'],
            'psgc_code' => '0405617000',
            'legacy_psgc_code' => '045617000',
            'shape_id' => '30758251B80195971051308',
            'reference_area_ha' => 132199.398321,
        ],
        'Guinayangan' => [
            'code' => 'GUINAYANGAN',
            'aliases' => ['GUINAYANGAN'],
            'psgc_code' => '0405618000',
            'legacy_psgc_code' => '045618000',
            'shape_id' => '30758251B18842789953948',
            'reference_area_ha' => 22572.428413,
        ],
        'Gumaca' => [
            'code' => 'GUMACA',
            'aliases' => ['GUMACA'],
            'psgc_code' => '0405619000',
            'legacy_psgc_code' => '045619000',
            'shape_id' => '30758251B73056824701687',
            'reference_area_ha' => 18527.515660,
        ],
        'Infanta' => [
            'code' => 'INFANTA_QUEZON',
            'aliases' => ['INFANTA_QUEZON'],
            'psgc_code' => '0405620000',
            'legacy_psgc_code' => '045620000',
            'shape_id' => '30758251B63604482475035',
            'reference_area_ha' => 17226.304956,
            'workspace_name' => 'Infanta (Quezon)',
        ],
        'Jomalig' => [
            'code' => 'JOMALIG',
            'aliases' => ['JOMALIG'],
            'psgc_code' => '0405621000',
            'legacy_psgc_code' => '045621000',
            'shape_id' => '30758251B10642262444602',
            'reference_area_ha' => 5186.239584,
        ],
        'Lopez' => [
            'code' => 'LOPEZ',
            'aliases' => ['LOPEZ'],
            'psgc_code' => '0405622000',
            'legacy_psgc_code' => '045622000',
            'shape_id' => '30758251B20509029806435',
            'reference_area_ha' => 37646.461917,
        ],
        'Lucban' => [
            'code' => 'LUCBAN',
            'aliases' => ['LUCBAN'],
            'psgc_code' => '0405623000',
            'legacy_psgc_code' => '045623000',
            'shape_id' => '30758251B44778470140505',
            'reference_area_ha' => 13824.728595,
        ],
        'Macalelon' => [
            'code' => 'MACALELON',
            'aliases' => ['MACALELON'],
            'psgc_code' => '0405625000',
            'legacy_psgc_code' => '045625000',
            'shape_id' => '30758251B15464702074966',
            'reference_area_ha' => 10401.208839,
        ],
        'Mauban' => [
            'code' => 'MAUBAN',
            'aliases' => ['MAUBAN'],
            'psgc_code' => '0405627000',
            'legacy_psgc_code' => '045627000',
            'shape_id' => '30758251B43842848901261',
            'reference_area_ha' => 40959.950757,
        ],
        'Mulanay' => [
            'code' => 'MULANAY',
            'aliases' => ['MULANAY'],
            'psgc_code' => '0405628000',
            'legacy_psgc_code' => '045628000',
            'shape_id' => '30758251B87111150812734',
            'reference_area_ha' => 27621.904146,
        ],
        'Padre Burgos' => [
            'code' => 'PADRE_BURGOS_QUEZON',
            'aliases' => ['PADRE_BURGOS_QUEZON'],
            'psgc_code' => '0405629000',
            'legacy_psgc_code' => '045629000',
            'shape_id' => '30758251B51252146080167',
            'reference_area_ha' => 7570.311787,
            'workspace_name' => 'Padre Burgos (Quezon)',
        ],
        'Pagbilao' => [
            'code' => 'PAGBILAO',
            'aliases' => ['PAGBILAO'],
            'psgc_code' => '0405630000',
            'legacy_psgc_code' => '045630000',
            'shape_id' => '30758251B2518509658018',
            'reference_area_ha' => 17521.994281,
        ],
        'Panukulan' => [
            'code' => 'PANUKULAN',
            'aliases' => ['PANUKULAN'],
            'psgc_code' => '0405631000',
            'legacy_psgc_code' => '045631000',
            'shape_id' => '30758251B73840124509325',
            'reference_area_ha' => 17965.954232,
        ],
        'Patnanungan' => [
            'code' => 'PATNANUNGAN',
            'aliases' => ['PATNANUNGAN'],
            'psgc_code' => '0405632000',
            'legacy_psgc_code' => '045632000',
            'shape_id' => '30758251B27789677173568',
            'reference_area_ha' => 9593.678994,
        ],
        'Perez' => [
            'code' => 'PEREZ',
            'aliases' => ['PEREZ'],
            'psgc_code' => '0405633000',
            'legacy_psgc_code' => '045633000',
            'shape_id' => '30758251B86160385363219',
            'reference_area_ha' => 5242.866830,
        ],
        'Pitogo' => [
            'code' => 'PITOGO_QUEZON',
            'aliases' => ['PITOGO_QUEZON'],
            'psgc_code' => '0405634000',
            'legacy_psgc_code' => '045634000',
            'shape_id' => '30758251B36558005022993',
            'reference_area_ha' => 7950.834912,
            'workspace_name' => 'Pitogo (Quezon)',
        ],
        'Plaridel' => [
            'code' => 'PLARIDEL_QUEZON',
            'aliases' => ['PLARIDEL_QUEZON'],
            'psgc_code' => '0405635000',
            'legacy_psgc_code' => '045635000',
            'shape_id' => '30758251B71605506872700',
            'reference_area_ha' => 1844.394501,
            'workspace_name' => 'Plaridel (Quezon)',
        ],
        'Polillo' => [
            'code' => 'POLILLO',
            'aliases' => ['POLILLO'],
            'psgc_code' => '0405636000',
            'legacy_psgc_code' => '045636000',
            'shape_id' => '30758251B72190087571376',
            'reference_area_ha' => 23503.403750,
        ],
        'Quezon' => [
            'code' => 'QUEZON_QUEZON',
            'aliases' => ['QUEZON_QUEZON'],
            'psgc_code' => '0405637000',
            'legacy_psgc_code' => '045637000',
            'shape_id' => '30758251B89004108151749',
            'reference_area_ha' => 7570.710163,
            'workspace_name' => 'Quezon (Quezon)',
        ],
        'Real' => [
            'code' => 'REAL',
            'aliases' => ['REAL'],
            'psgc_code' => '0405638000',
            'legacy_psgc_code' => '045638000',
            'shape_id' => '30758251B78685665231141',
            'reference_area_ha' => 35076.734371,
        ],
        'Sampaloc' => [
            'code' => 'SAMPALOC_QUEZON',
            'aliases' => ['SAMPALOC_QUEZON'],
            'psgc_code' => '0405639000',
            'legacy_psgc_code' => '045639000',
            'shape_id' => '30758251B60406679280817',
            'reference_area_ha' => 8135.654538,
            'workspace_name' => 'Sampaloc (Quezon)',
        ],
        'San Andres' => [
            'code' => 'SAN_ANDRES_QUEZON',
            'aliases' => ['SAN_ANDRES_QUEZON'],
            'psgc_code' => '0405640000',
            'legacy_psgc_code' => '045640000',
            'shape_id' => '30758251B69966193362953',
            'reference_area_ha' => 17553.026804,
            'workspace_name' => 'San Andres (Quezon)',
        ],
        'San Antonio' => [
            'code' => 'SAN_ANTONIO_QUEZON',
            'aliases' => ['SAN_ANTONIO_QUEZON'],
            'psgc_code' => '0405641000',
            'legacy_psgc_code' => '045641000',
            'shape_id' => '30758251B10052680344935',
            'reference_area_ha' => 6237.925368,
            'workspace_name' => 'San Antonio (Quezon)',
        ],
        'San Francisco' => [
            'code' => 'SAN_FRANCISCO_QUEZON',
            'aliases' => ['SAN_FRANCISCO_QUEZON'],
            'psgc_code' => '0405642000',
            'legacy_psgc_code' => '045642000',
            'shape_id' => '30758251B48560847084049',
            'reference_area_ha' => 31619.067525,
            'workspace_name' => 'San Francisco (Quezon)',
        ],
        'San Narciso' => [
            'code' => 'SAN_NARCISO_QUEZON',
            'aliases' => ['SAN_NARCISO_QUEZON'],
            'psgc_code' => '0405644000',
            'legacy_psgc_code' => '045644000',
            'shape_id' => '30758251B81535360880656',
            'reference_area_ha' => 24268.422359,
            'workspace_name' => 'San Narciso (Quezon)',
        ],
        'Sariaya' => [
            'code' => 'SARIAYA',
            'aliases' => ['SARIAYA'],
            'psgc_code' => '0405645000',
            'legacy_psgc_code' => '045645000',
            'shape_id' => '30758251B55152096669132',
            'reference_area_ha' => 21381.717487,
        ],
        'Tagkawayan' => [
            'code' => 'TAGKAWAYAN',
            'aliases' => ['TAGKAWAYAN'],
            'psgc_code' => '0405646000',
            'legacy_psgc_code' => '045646000',
            'shape_id' => '30758251B29801061309945',
            'reference_area_ha' => 55174.242000,
        ],
        'City of Tayabas' => [
            'code' => 'CITY_OF_TAYABAS',
            'aliases' => ['CITY_OF_TAYABAS'],
            'psgc_code' => '0405647000',
            'legacy_psgc_code' => '045647000',
            'shape_id' => '30758251B70245066805584',
            'reference_area_ha' => 22492.233071,
        ],
        'Tiaong' => [
            'code' => 'TIAONG',
            'aliases' => ['TIAONG'],
            'psgc_code' => '0405648000',
            'legacy_psgc_code' => '045648000',
            'shape_id' => '30758251B22989008298037',
            'reference_area_ha' => 11892.957919,
        ],
        'Unisan' => [
            'code' => 'UNISAN',
            'aliases' => ['UNISAN'],
            'psgc_code' => '0405649000',
            'legacy_psgc_code' => '045649000',
            'shape_id' => '30758251B81181163877224',
            'reference_area_ha' => 10742.562120,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Quezon', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 40 Quezon planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

<?php

namespace Database\Seeders;

use App\Support\ReferenceMunicipalityBoundaryImporter;
use Illuminate\Database\Seeder;

/**
 * Pinned Bicol planning references; run explicitly after a database backup.
 *
 * Source identities and separate Naga City scope: docs/BICOL_BOUNDARY_SOURCES.md.
 * No accounts or operational records are created. Existing boundaries are preserved.
 */
class AlbayMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/albay_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'bcd78f134acc25faffce8356f343dd2458783ff92df22b89faa65506e4ae4bdc';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Bacacay' => [
            'code' => 'BACACAY',
            'aliases' => ['BACACAY'],
            'psgc_code' => '0500501000',
            'legacy_psgc_code' => '050501000',
            'shape_id' => '30758251B91787985073572',
            'reference_area_ha' => 11890.737709,
        ],
        'Camalig' => [
            'code' => 'CAMALIG',
            'aliases' => ['CAMALIG'],
            'psgc_code' => '0500502000',
            'legacy_psgc_code' => '050502000',
            'shape_id' => '30758251B49910210330724',
            'reference_area_ha' => 13168.394307,
        ],
        'Daraga' => [
            'code' => 'DARAGA',
            'aliases' => ['DARAGA'],
            'psgc_code' => '0500503000',
            'legacy_psgc_code' => '050503000',
            'shape_id' => '30758251B85710407391035',
            'reference_area_ha' => 12404.621893,
        ],
        'Guinobatan' => [
            'code' => 'GUINOBATAN',
            'aliases' => ['GUINOBATAN'],
            'psgc_code' => '0500504000',
            'legacy_psgc_code' => '050504000',
            'shape_id' => '30758251B92622239064783',
            'reference_area_ha' => 17557.351924,
        ],
        'Jovellar' => [
            'code' => 'JOVELLAR',
            'aliases' => ['JOVELLAR'],
            'psgc_code' => '0500505000',
            'legacy_psgc_code' => '050505000',
            'shape_id' => '30758251B38370299499479',
            'reference_area_ha' => 8058.502586,
        ],
        'Legazpi City' => [
            'code' => 'LEGAZPI_CITY',
            'aliases' => ['LEGAZPI_CITY'],
            'psgc_code' => '0500506000',
            'legacy_psgc_code' => '050506000',
            'shape_id' => '30758251B45508344321255',
            'reference_area_ha' => 15783.005067,
        ],
        'Libon' => [
            'code' => 'LIBON',
            'aliases' => ['LIBON'],
            'psgc_code' => '0500507000',
            'legacy_psgc_code' => '050507000',
            'shape_id' => '30758251B33812388660229',
            'reference_area_ha' => 22372.312186,
        ],
        'City of Ligao' => [
            'code' => 'LIGAO_CITY',
            'aliases' => ['LIGAO_CITY'],
            'psgc_code' => '0500508000',
            'legacy_psgc_code' => '050508000',
            'shape_id' => '30758251B54991272307692',
            'reference_area_ha' => 24707.247271,
            'workspace_name' => 'Ligao City',
        ],
        'Malilipot' => [
            'code' => 'MALILIPOT',
            'aliases' => ['MALILIPOT'],
            'psgc_code' => '0500509000',
            'legacy_psgc_code' => '050509000',
            'shape_id' => '30758251B40560232053392',
            'reference_area_ha' => 4341.737854,
        ],
        'Malinao' => [
            'code' => 'MALINAO_ALBAY',
            'aliases' => ['MALINAO_ALBAY'],
            'psgc_code' => '0500510000',
            'legacy_psgc_code' => '050510000',
            'shape_id' => '30758251B79961363157368',
            'reference_area_ha' => 10934.733573,
            'workspace_name' => 'Malinao (Albay)',
        ],
        'Manito' => [
            'code' => 'MANITO',
            'aliases' => ['MANITO'],
            'psgc_code' => '0500511000',
            'legacy_psgc_code' => '050511000',
            'shape_id' => '30758251B52587531996198',
            'reference_area_ha' => 9421.039974,
        ],
        'Oas' => [
            'code' => 'OAS',
            'aliases' => ['OAS'],
            'psgc_code' => '0500512000',
            'legacy_psgc_code' => '050512000',
            'shape_id' => '30758251B22907415261768',
            'reference_area_ha' => 24459.608749,
        ],
        'Pio Duran' => [
            'code' => 'PIO_DURAN',
            'aliases' => ['PIO_DURAN'],
            'psgc_code' => '0500513000',
            'legacy_psgc_code' => '050513000',
            'shape_id' => '30758251B7916733720670',
            'reference_area_ha' => 14075.398292,
        ],
        'Polangui' => [
            'code' => 'POLANGUI',
            'aliases' => ['POLANGUI'],
            'psgc_code' => '0500514000',
            'legacy_psgc_code' => '050514000',
            'shape_id' => '30758251B28395288618687',
            'reference_area_ha' => 12319.703682,
        ],
        'Rapu-Rapu' => [
            'code' => 'RAPU_RAPU',
            'aliases' => ['RAPU_RAPU'],
            'psgc_code' => '0500515000',
            'legacy_psgc_code' => '050515000',
            'shape_id' => '30758251B43953309192552',
            'reference_area_ha' => 15449.071586,
        ],
        'Santo Domingo' => [
            'code' => 'SANTO_DOMINGO_ALBAY',
            'aliases' => ['SANTO_DOMINGO_ALBAY'],
            'psgc_code' => '0500516000',
            'legacy_psgc_code' => '050516000',
            'shape_id' => '30758251B82534297587726',
            'reference_area_ha' => 5478.365346,
            'workspace_name' => 'Santo Domingo (Albay)',
        ],
        'City of Tabaco' => [
            'code' => 'TABACO_CITY',
            'aliases' => ['TABACO_CITY'],
            'psgc_code' => '0500517000',
            'legacy_psgc_code' => '050517000',
            'shape_id' => '30758251B75861383716628',
            'reference_area_ha' => 11849.592317,
            'workspace_name' => 'Tabaco City',
        ],
        'Tiwi' => [
            'code' => 'TIWI',
            'aliases' => ['TIWI'],
            'psgc_code' => '0500518000',
            'legacy_psgc_code' => '050518000',
            'shape_id' => '30758251B71671176044973',
            'reference_area_ha' => 12684.563763,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Albay', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 18 Albay planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

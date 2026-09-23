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
class CamarinesSurMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/camarines_sur_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = 'aa708d2385628ee72d38eaf71855e3970c245b04ee956c7befecee46329893cf';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Baao' => [
            'code' => 'BAAO',
            'aliases' => ['BAAO'],
            'psgc_code' => '0501701000',
            'legacy_psgc_code' => '051701000',
            'shape_id' => '30758251B83742421641730',
            'reference_area_ha' => 10803.347762,
        ],
        'Balatan' => [
            'code' => 'BALATAN',
            'aliases' => ['BALATAN'],
            'psgc_code' => '0501702000',
            'legacy_psgc_code' => '051702000',
            'shape_id' => '30758251B20087067633208',
            'reference_area_ha' => 5798.290309,
        ],
        'Bato' => [
            'code' => 'BATO_CAMARINES_SUR',
            'aliases' => ['BATO_CAMARINES_SUR'],
            'psgc_code' => '0501703000',
            'legacy_psgc_code' => '051703000',
            'shape_id' => '30758251B25061359196597',
            'reference_area_ha' => 8366.167932,
            'workspace_name' => 'Bato (Camarines Sur)',
        ],
        'Bombon' => [
            'code' => 'BOMBON',
            'aliases' => ['BOMBON'],
            'psgc_code' => '0501704000',
            'legacy_psgc_code' => '051704000',
            'shape_id' => '30758251B14591039015578',
            'reference_area_ha' => 3010.965641,
        ],
        'Buhi' => [
            'code' => 'BUHI',
            'aliases' => ['BUHI'],
            'psgc_code' => '0501705000',
            'legacy_psgc_code' => '051705000',
            'shape_id' => '30758251B40011294763010',
            'reference_area_ha' => 20449.320615,
        ],
        'Bula' => [
            'code' => 'BULA',
            'aliases' => ['BULA'],
            'psgc_code' => '0501706000',
            'legacy_psgc_code' => '051706000',
            'shape_id' => '30758251B33307504465511',
            'reference_area_ha' => 16470.209696,
        ],
        'Cabusao' => [
            'code' => 'CABUSAO',
            'aliases' => ['CABUSAO'],
            'psgc_code' => '0501707000',
            'legacy_psgc_code' => '051707000',
            'shape_id' => '30758251B48434085268731',
            'reference_area_ha' => 3722.635007,
        ],
        'Calabanga' => [
            'code' => 'CALABANGA',
            'aliases' => ['CALABANGA'],
            'psgc_code' => '0501708000',
            'legacy_psgc_code' => '051708000',
            'shape_id' => '30758251B85876225109747',
            'reference_area_ha' => 15141.256820,
        ],
        'Camaligan' => [
            'code' => 'CAMALIGAN',
            'aliases' => ['CAMALIGAN'],
            'psgc_code' => '0501709000',
            'legacy_psgc_code' => '051709000',
            'shape_id' => '30758251B44874081828887',
            'reference_area_ha' => 616.772140,
        ],
        'Canaman' => [
            'code' => 'CANAMAN',
            'aliases' => ['CANAMAN'],
            'psgc_code' => '0501710000',
            'legacy_psgc_code' => '051710000',
            'shape_id' => '30758251B59508339277809',
            'reference_area_ha' => 4198.228692,
        ],
        'Caramoan' => [
            'code' => 'CARAMOAN',
            'aliases' => ['CARAMOAN'],
            'psgc_code' => '0501711000',
            'legacy_psgc_code' => '051711000',
            'shape_id' => '30758251B12412533731052',
            'reference_area_ha' => 27367.646646,
        ],
        'Del Gallego' => [
            'code' => 'DEL_GALLEGO',
            'aliases' => ['DEL_GALLEGO'],
            'psgc_code' => '0501712000',
            'legacy_psgc_code' => '051712000',
            'shape_id' => '30758251B11399000509804',
            'reference_area_ha' => 21368.472461,
        ],
        'Gainza' => [
            'code' => 'GAINZA',
            'aliases' => ['GAINZA'],
            'psgc_code' => '0501713000',
            'legacy_psgc_code' => '051713000',
            'shape_id' => '30758251B63624521547352',
            'reference_area_ha' => 1590.975523,
        ],
        'Garchitorena' => [
            'code' => 'GARCHITORENA',
            'aliases' => ['GARCHITORENA'],
            'psgc_code' => '0501714000',
            'legacy_psgc_code' => '051714000',
            'shape_id' => '30758251B40937413002129',
            'reference_area_ha' => 24500.065475,
        ],
        'Goa' => [
            'code' => 'GOA',
            'aliases' => ['GOA'],
            'psgc_code' => '0501715000',
            'legacy_psgc_code' => '051715000',
            'shape_id' => '30758251B64069082540255',
            'reference_area_ha' => 18611.114110,
        ],
        'Iriga City' => [
            'code' => 'IRIGA_CITY',
            'aliases' => ['IRIGA_CITY'],
            'psgc_code' => '0501716000',
            'legacy_psgc_code' => '051716000',
            'shape_id' => '30758251B92034423804037',
            'reference_area_ha' => 13915.701125,
        ],
        'Lagonoy' => [
            'code' => 'LAGONOY',
            'aliases' => ['LAGONOY'],
            'psgc_code' => '0501717000',
            'legacy_psgc_code' => '051717000',
            'shape_id' => '30758251B60102557901251',
            'reference_area_ha' => 38423.738447,
        ],
        'Libmanan' => [
            'code' => 'LIBMANAN',
            'aliases' => ['LIBMANAN'],
            'psgc_code' => '0501718000',
            'legacy_psgc_code' => '051718000',
            'shape_id' => '30758251B7660717026766',
            'reference_area_ha' => 36886.006528,
        ],
        'Lupi' => [
            'code' => 'LUPI',
            'aliases' => ['LUPI'],
            'psgc_code' => '0501719000',
            'legacy_psgc_code' => '051719000',
            'shape_id' => '30758251B13192443251748',
            'reference_area_ha' => 19850.072642,
        ],
        'Magarao' => [
            'code' => 'MAGARAO',
            'aliases' => ['MAGARAO'],
            'psgc_code' => '0501720000',
            'legacy_psgc_code' => '051720000',
            'shape_id' => '30758251B22935570216565',
            'reference_area_ha' => 3784.727396,
        ],
        'Milaor' => [
            'code' => 'MILAOR',
            'aliases' => ['MILAOR'],
            'psgc_code' => '0501721000',
            'legacy_psgc_code' => '051721000',
            'shape_id' => '30758251B47632906140247',
            'reference_area_ha' => 3286.383005,
        ],
        'Minalabac' => [
            'code' => 'MINALABAC',
            'aliases' => ['MINALABAC'],
            'psgc_code' => '0501722000',
            'legacy_psgc_code' => '051722000',
            'shape_id' => '30758251B9994709288668',
            'reference_area_ha' => 12722.086798,
        ],
        'Nabua' => [
            'code' => 'NABUA',
            'aliases' => ['NABUA'],
            'psgc_code' => '0501723000',
            'legacy_psgc_code' => '051723000',
            'shape_id' => '30758251B88883641582403',
            'reference_area_ha' => 8607.728707,
        ],
        'Ocampo' => [
            'code' => 'OCAMPO',
            'aliases' => ['OCAMPO'],
            'psgc_code' => '0501725000',
            'legacy_psgc_code' => '051725000',
            'shape_id' => '30758251B20065579392201',
            'reference_area_ha' => 11837.094303,
        ],
        'Pamplona' => [
            'code' => 'PAMPLONA_CAMARINES_SUR',
            'aliases' => ['PAMPLONA_CAMARINES_SUR'],
            'psgc_code' => '0501726000',
            'legacy_psgc_code' => '051726000',
            'shape_id' => '30758251B58646010264693',
            'reference_area_ha' => 6567.799896,
            'workspace_name' => 'Pamplona (Camarines Sur)',
        ],
        'Pasacao' => [
            'code' => 'PASACAO',
            'aliases' => ['PASACAO'],
            'psgc_code' => '0501727000',
            'legacy_psgc_code' => '051727000',
            'shape_id' => '30758251B74500883185148',
            'reference_area_ha' => 13428.761704,
        ],
        'Pili' => [
            'code' => 'PILI',
            'aliases' => ['PILI'],
            'psgc_code' => '0501728000',
            'legacy_psgc_code' => '051728000',
            'shape_id' => '30758251B53297443650612',
            'reference_area_ha' => 12358.632847,
        ],
        'Presentacion' => [
            'code' => 'PRESENTACION',
            'aliases' => ['PRESENTACION'],
            'psgc_code' => '0501729000',
            'legacy_psgc_code' => '051729000',
            'shape_id' => '30758251B19946568365659',
            'reference_area_ha' => 15456.016739,
        ],
        'Ragay' => [
            'code' => 'RAGAY',
            'aliases' => ['RAGAY'],
            'psgc_code' => '0501730000',
            'legacy_psgc_code' => '051730000',
            'shape_id' => '30758251B23999947934104',
            'reference_area_ha' => 37905.177679,
        ],
        'Sagñay' => [
            'code' => 'SAGNAY',
            'aliases' => ['SAGNAY'],
            'psgc_code' => '0501731000',
            'legacy_psgc_code' => '051731000',
            'shape_id' => '30758251B59561254174499',
            'reference_area_ha' => 10300.487064,
        ],
        'San Fernando' => [
            'code' => 'SAN_FERNANDO_CAMARINES_SUR',
            'aliases' => ['SAN_FERNANDO_CAMARINES_SUR'],
            'psgc_code' => '0501732000',
            'legacy_psgc_code' => '051732000',
            'shape_id' => '30758251B90301857682432',
            'reference_area_ha' => 8440.500799,
            'workspace_name' => 'San Fernando (Camarines Sur)',
        ],
        'San Jose' => [
            'code' => 'SAN_JOSE_CAMARINES_SUR',
            'aliases' => ['SAN_JOSE_CAMARINES_SUR'],
            'psgc_code' => '0501733000',
            'legacy_psgc_code' => '051733000',
            'shape_id' => '30758251B67944670665540',
            'reference_area_ha' => 5159.054487,
            'workspace_name' => 'San Jose (Camarines Sur)',
        ],
        'Sipocot' => [
            'code' => 'SIPOCOT',
            'aliases' => ['SIPOCOT'],
            'psgc_code' => '0501734000',
            'legacy_psgc_code' => '051734000',
            'shape_id' => '30758251B70291415391467',
            'reference_area_ha' => 25383.653731,
        ],
        'Siruma' => [
            'code' => 'SIRUMA',
            'aliases' => ['SIRUMA'],
            'psgc_code' => '0501735000',
            'legacy_psgc_code' => '051735000',
            'shape_id' => '30758251B38539874694447',
            'reference_area_ha' => 12912.960756,
        ],
        'Tigaon' => [
            'code' => 'TIGAON',
            'aliases' => ['TIGAON'],
            'psgc_code' => '0501736000',
            'legacy_psgc_code' => '051736000',
            'shape_id' => '30758251B83863346028510',
            'reference_area_ha' => 7790.205616,
        ],
        'Tinambac' => [
            'code' => 'TINAMBAC',
            'aliases' => ['TINAMBAC'],
            'psgc_code' => '0501737000',
            'legacy_psgc_code' => '051737000',
            'shape_id' => '30758251B38953781960130',
            'reference_area_ha' => 32909.569355,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Camarines Sur', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 36 Camarines Sur planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

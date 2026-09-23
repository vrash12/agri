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
class CamarinesNorteMunicipalityBoundarySeeder extends Seeder
{
    private const SOURCE_FILE = 'seeders/data/camarines_norte_municipality_reference_boundaries.geojson';

    private const SOURCE_CHECKSUM = '318cab4532cb40f8b4d59fd180c05a45485e871e36df00d0f50665b102ecef4c';

    private const SOURCE_REVISION = '9469f09';

    private const MUNICIPALITIES = [
        'Basud' => [
            'code' => 'BASUD',
            'aliases' => ['BASUD'],
            'psgc_code' => '0501601000',
            'legacy_psgc_code' => '051601000',
            'shape_id' => '30758251B50616374221449',
            'reference_area_ha' => 26556.223736,
        ],
        'Capalonga' => [
            'code' => 'CAPALONGA',
            'aliases' => ['CAPALONGA'],
            'psgc_code' => '0501602000',
            'legacy_psgc_code' => '051602000',
            'shape_id' => '30758251B37893084976776',
            'reference_area_ha' => 25416.269015,
        ],
        'Daet' => [
            'code' => 'DAET',
            'aliases' => ['DAET'],
            'psgc_code' => '0501603000',
            'legacy_psgc_code' => '051603000',
            'shape_id' => '30758251B92813581823724',
            'reference_area_ha' => 5023.014012,
        ],
        'San Lorenzo Ruiz' => [
            'code' => 'SAN_LORENZO_RUIZ',
            'aliases' => ['SAN_LORENZO_RUIZ'],
            'psgc_code' => '0501604000',
            'legacy_psgc_code' => '051604000',
            'shape_id' => '30758251B16172470654133',
            'reference_area_ha' => 8998.226292,
        ],
        'Jose Panganiban' => [
            'code' => 'JOSE_PANGANIBAN',
            'aliases' => ['JOSE_PANGANIBAN'],
            'psgc_code' => '0501605000',
            'legacy_psgc_code' => '051605000',
            'shape_id' => '30758251B31746504022332',
            'reference_area_ha' => 17631.369148,
        ],
        'Labo' => [
            'code' => 'LABO',
            'aliases' => ['LABO'],
            'psgc_code' => '0501606000',
            'legacy_psgc_code' => '051606000',
            'shape_id' => '30758251B4129960077068',
            'reference_area_ha' => 62870.699702,
        ],
        'Mercedes' => [
            'code' => 'MERCEDES_CAMARINES_NORTE',
            'aliases' => ['MERCEDES_CAMARINES_NORTE'],
            'psgc_code' => '0501607000',
            'legacy_psgc_code' => '051607000',
            'shape_id' => '30758251B91233781588772',
            'reference_area_ha' => 11802.949758,
            'workspace_name' => 'Mercedes (Camarines Norte)',
        ],
        'Paracale' => [
            'code' => 'PARACALE',
            'aliases' => ['PARACALE'],
            'psgc_code' => '0501608000',
            'legacy_psgc_code' => '051608000',
            'shape_id' => '30758251B75235300602273',
            'reference_area_ha' => 15741.888403,
        ],
        'San Vicente' => [
            'code' => 'SAN_VICENTE_CAMARINES_NORTE',
            'aliases' => ['SAN_VICENTE_CAMARINES_NORTE'],
            'psgc_code' => '0501609000',
            'legacy_psgc_code' => '051609000',
            'shape_id' => '30758251B44254062576901',
            'reference_area_ha' => 5201.363099,
            'workspace_name' => 'San Vicente (Camarines Norte)',
        ],
        'Santa Elena' => [
            'code' => 'SANTA_ELENA',
            'aliases' => ['SANTA_ELENA'],
            'psgc_code' => '0501610000',
            'legacy_psgc_code' => '051610000',
            'shape_id' => '30758251B78465254525498',
            'reference_area_ha' => 20297.650194,
        ],
        'Talisay' => [
            'code' => 'TALISAY_CAMARINES_NORTE',
            'aliases' => ['TALISAY_CAMARINES_NORTE'],
            'psgc_code' => '0501611000',
            'legacy_psgc_code' => '051611000',
            'shape_id' => '30758251B44537978372454',
            'reference_area_ha' => 3184.996996,
            'workspace_name' => 'Talisay (Camarines Norte)',
        ],
        'Vinzons' => [
            'code' => 'VINZONS',
            'aliases' => ['VINZONS'],
            'psgc_code' => '0501612000',
            'legacy_psgc_code' => '051612000',
            'shape_id' => '30758251B3136042153209',
            'reference_area_ha' => 9412.596841,
        ],
    ];

    public function run(): void
    {
        app(ReferenceMunicipalityBoundaryImporter::class)->import(
            'Camarines Norte', self::SOURCE_FILE, self::SOURCE_CHECKSUM, self::SOURCE_REVISION,
            self::MUNICIPALITIES
        );

        $this->command?->info('Ready: 12 Camarines Norte planning/reference geofence(s) active.');
        $this->command?->warn('These approximate boundaries require LGU/NAMRIA verification before official use.');
    }
}

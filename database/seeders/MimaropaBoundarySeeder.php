<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Explicit, all-or-nothing MIMAROPA planning references; never a default seeder. */
class MimaropaBoundarySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                MarinduqueMunicipalityBoundarySeeder::class,
                OccidentalMindoroMunicipalityBoundarySeeder::class,
                OrientalMindoroMunicipalityBoundarySeeder::class,
                PalawanMunicipalityBoundarySeeder::class,
                PuertoPrincesaCityBoundarySeeder::class,
                RomblonMunicipalityBoundarySeeder::class,
            ]);
        });
    }
}

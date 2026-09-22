<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Explicit, all-or-nothing CALABARZON planning references; never a default seeder. */
class CalabarzonBoundarySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                BatangasMunicipalityBoundarySeeder::class,
                CaviteMunicipalityBoundarySeeder::class,
                LagunaMunicipalityBoundarySeeder::class,
                LucenaCityBoundarySeeder::class,
                QuezonMunicipalityBoundarySeeder::class,
                RizalMunicipalityBoundarySeeder::class,
            ]);
        });
    }
}

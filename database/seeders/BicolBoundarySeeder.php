<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Explicit, all-or-nothing Bicol Region planning references; never a default seeder.
 *
 * Each province/city seeder is independently pinned and attributed. Keeping the
 * outer transaction here prevents a late source or ownership failure from leaving
 * only part of Region V active.
 */
class BicolBoundarySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                AlbayMunicipalityBoundarySeeder::class,
                CamarinesNorteMunicipalityBoundarySeeder::class,
                CamarinesSurMunicipalityBoundarySeeder::class,
                CatanduanesMunicipalityBoundarySeeder::class,
                MasbateMunicipalityBoundarySeeder::class,
                NagaCityBoundarySeeder::class,
                SorsogonMunicipalityBoundarySeeder::class,
            ]);
        });
    }
}

<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Database\Seeders\TarlacMunicipalityDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TarlacMunicipalityDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        User::create(['name' => 'Synthetic Import Owner', 'email' => 'import-owner@example.test',
            'password' => 'non-login-fixture', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true]);
        $province = Province::create(['name' => 'Tarlac', 'is_active' => true]);
        foreach (['ANAO' => 'Anao', 'CAMILING' => 'Camiling', 'CONCEPCION' => 'Concepcion',
            'PANIQUI' => 'Paniqui', 'RAMOS' => 'Ramos', 'TARLAC_CITY' => 'Tarlac City'] as $code => $name) {
            Municipality::create(['name' => $name, 'code' => $code, 'province' => 'Tarlac',
                'province_id' => $province->id, 'is_active' => true]);
        }
    }

    public function test_the_named_demo_seeder_is_scoped_balanced_and_idempotent(): void
    {
        $seeder = app(TarlacMunicipalityDemoSeeder::class);
        $seeder->run();
        $seeder->run();

        foreach (['ANAO', 'CAMILING', 'PANIQUI', 'RAMOS'] as $code) {
            $municipality = Municipality::query()->where('code', $code)->firstOrFail();

            $this->assertSame(
                10,
                Farmer::query()
                    ->where('municipality_id', $municipality->id)
                    ->where('ffrs', 'like', "DEMO-{$code}-FFRS-%")
                    ->count()
            );

            $assistance = RiceSeedDistribution::query()
                ->where('municipality_id', $municipality->id)
                ->where('lot_series', 'like', "DEMO-{$code}-2026-%");

            $this->assertSame(10, (clone $assistance)->count());
            $this->assertSame(
                5,
                (clone $assistance)
                    ->whereIn('input_category', RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES)
                    ->count()
            );
            $this->assertSame(
                5,
                (clone $assistance)
                    ->whereNotIn('input_category', RiceSeedDistribution::FISHERIES_INPUT_CATEGORIES)
                    ->count()
            );

            $this->assertSame(
                1,
                MunicipalityBoundary::query()
                    ->active()
                    ->where('municipality_id', $municipality->id)
                    ->count()
            );
        }

        foreach (['CONCEPCION', 'TARLAC_CITY'] as $code) {
            $municipality = Municipality::query()->where('code', $code)->firstOrFail();

            $this->assertSame(
                1,
                MunicipalityBoundary::query()
                    ->active()
                    ->where('municipality_id', $municipality->id)
                    ->count()
            );
            $this->assertSame(
                0,
                Farmer::query()
                    ->where('municipality_id', $municipality->id)
                    ->where('ffrs', 'like', "DEMO-{$code}-FFRS-%")
                    ->count()
            );
            $this->assertSame(
                0,
                RiceSeedDistribution::query()
                    ->where('municipality_id', $municipality->id)
                    ->where('lot_series', 'like', "DEMO-{$code}-2026-%")
                    ->count()
            );
        }
    }
}

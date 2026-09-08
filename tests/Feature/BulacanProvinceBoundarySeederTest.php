<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\User;
use Database\Seeders\BulacanProvinceBoundarySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BulacanProvinceBoundarySeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_seeder_activates_one_idempotent_bulacan_reference_boundary(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'bulacan-boundary-test@example.test'],
            [
                'name' => 'Boundary Test Super Admin',
                'password' => Hash::make('test-only-password'),
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        $seeder = app(BulacanProvinceBoundarySeeder::class);
        $seeder->run();
        $seeder->run();

        $municipality = Municipality::query()->where('code', 'BULACAN')->firstOrFail();
        $boundaries = MunicipalityBoundary::query()
            ->where('municipality_id', $municipality->id)
            ->where('name', 'Bulacan Province Planning Reference · geoBoundaries 2020');

        $this->assertSame(1, (clone $boundaries)->count());
        $this->assertSame(1, (clone $boundaries)->active()->count());
        $this->assertEqualsWithDelta(272543.5161, (float) $boundaries->firstOrFail()->area_ha, 0.1);
    }
}

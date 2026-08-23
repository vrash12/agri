<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\User;
use App\Services\WeatherForecastService;
use App\Support\MunicipalityAccess;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WeatherAdvisoryAccessTest extends TestCase
{
    public function test_a_municipal_user_cannot_switch_to_another_municipality(): void
    {
        $own = $this->municipality(31, 'Anao');
        $foreign = $this->municipality(32, 'Bamban');
        $user = $this->user(User::ROLE_MUNICIPAL_STAFF, $own);

        $this->mock(MunicipalityAccess::class, function ($mock) use ($own) {
            $mock->shouldReceive('choices')
                ->once()
                ->andReturn(new Collection([$own]));
        });
        $this->mock(WeatherForecastService::class, function ($mock) use ($own) {
            $mock->shouldReceive('forMunicipality')
                ->once()
                ->with($own)
                ->andReturn($this->unavailableForecast($own));
        });

        $this->actingAs($user)
            ->get(route('weather.index', ['municipality_id' => $foreign->id]))
            ->assertOk()
            ->assertSee('Anao, Tarlac')
            ->assertDontSee('Bamban, Tarlac');
    }

    public function test_a_provincial_user_can_choose_an_active_municipality(): void
    {
        $first = $this->municipality(41, 'Anao');
        $selected = $this->municipality(42, 'Bamban');
        $user = $this->user(User::ROLE_PROVINCIAL_STAFF);

        $this->mock(MunicipalityAccess::class, function ($mock) use ($first, $selected) {
            $mock->shouldReceive('choices')
                ->once()
                ->andReturn(new Collection([$first, $selected]));
        });
        $this->mock(WeatherForecastService::class, function ($mock) use ($selected) {
            $mock->shouldReceive('forMunicipality')
                ->once()
                ->with($selected)
                ->andReturn($this->unavailableForecast($selected));
        });

        $this->actingAs($user)
            ->get(route('weather.index', ['municipality_id' => $selected->id]))
            ->assertOk()
            ->assertSee('Bamban, Tarlac')
            ->assertSee('Provincial accounts may compare active municipalities');
    }

    private function municipality(int $id, string $name): Municipality
    {
        $municipality = new Municipality([
            'name' => $name,
            'province' => 'Tarlac',
            'is_active' => true,
        ]);
        $municipality->id = $id;
        $municipality->exists = true;

        return $municipality;
    }

    private function user(string $role, ?Municipality $municipality = null): User
    {
        $user = new User([
            'name' => 'Weather Access Tester',
            'email' => strtolower(str_replace('_', '-', $role)) . '@example.test',
            'role' => $role,
            'municipality_id' => $municipality?->id,
            'is_active' => true,
        ]);
        $user->id = 501;
        $user->exists = true;
        $user->setRelation('municipality', $municipality);

        return $user;
    }

    /** @return array<string, mixed> */
    private function unavailableForecast(Municipality $municipality): array
    {
        return [
            'available' => false,
            'is_stale' => false,
            'status_message' => 'Forecast unavailable for test.',
            'municipality' => ['id' => $municipality->id, 'name' => $municipality->name],
            'coordinates' => null,
            'timezone' => 'Asia/Manila',
            'fetched_at' => null,
            'provider' => 'Open-Meteo',
            'provider_url' => 'https://open-meteo.com/',
            'current' => [],
            'daily' => [],
            'hourly' => [],
            'summary' => [],
            'advisories' => [],
        ];
    }
}

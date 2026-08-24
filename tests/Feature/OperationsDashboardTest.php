<?php

namespace Tests\Feature;

use App\Models\AntiRabiesVaccination;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_and_mapping_workspace_use_the_municipal_scope(): void
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $ownMunicipality = $this->makeMunicipality('Dashboard Own '.$suffix, 'DO'.$suffix);
        $foreignMunicipality = $this->makeMunicipality('Dashboard Foreign '.$suffix, 'DF'.$suffix);

        $user = User::create([
            'name' => 'Dashboard Municipal User',
            'email' => 'dashboard-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $ownMunicipality->id,
            'is_active' => true,
        ]);

        $mappedFarmer = $this->makeFarmer(
            $ownMunicipality,
            'DashboardOwnMapped'.$suffix,
            'OWN-'.$suffix
        );
        $this->makeFarmer(
            $ownMunicipality,
            'DashboardOwnUnmapped'.$suffix,
            null
        );
        $foreignFarmer = $this->makeFarmer(
            $foreignMunicipality,
            'DashboardForeignFarmer'.$suffix,
            'FOREIGN-'.$suffix
        );

        $this->makePlot($mappedFarmer, 'Dashboard Own Parcel '.$suffix);
        $this->makePlot($foreignFarmer, 'Dashboard Foreign Parcel '.$suffix);

        RiceSeedDistribution::create([
            'municipality_id' => $ownMunicipality->id,
            'farmer_id' => $mappedFarmer->id,
            'last_name' => 'DashboardOwnRecipient'.$suffix,
            'first_name' => 'Operator',
            'kgs_received' => 25,
            'date_received' => now()->toDateString(),
            'seed_variety_claimed' => 'Dashboard Variety',
        ]);
        RiceSeedDistribution::create([
            'municipality_id' => $ownMunicipality->id,
            'farmer_id' => $mappedFarmer->id,
            'last_name' => 'DashboardOwnFisheries'.$suffix,
            'first_name' => 'Operator',
            'input_category' => 'fish_fingerlings',
            'seed_variety_claimed' => 'Dashboard Tilapia Fingerlings',
            'kgs_received' => 1500,
            'quantity_unit' => 'piece',
            'date_received' => now()->toDateString(),
        ]);
        RiceSeedDistribution::create([
            'municipality_id' => $foreignMunicipality->id,
            'farmer_id' => $foreignFarmer->id,
            'last_name' => 'DashboardForeignRecipient'.$suffix,
            'first_name' => 'Hidden',
            'kgs_received' => 50,
            'date_received' => now()->toDateString(),
        ]);

        $this->makeVaccination(
            $ownMunicipality,
            'Dashboard Own Pet '.$suffix
        );
        $this->makeVaccination(
            $foreignMunicipality,
            'Dashboard Foreign Pet '.$suffix
        );

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operations dashboard')
            ->assertSee('Parcel mapping coverage')
            ->assertSee('50.0%')
            ->assertSee('DashboardOwnRecipient'.$suffix)
            ->assertSee('DashboardOwnFisheries'.$suffix)
            ->assertSee('1,500 fingerlings issued')
            ->assertSee('Dashboard Own Parcel '.$suffix)
            ->assertSee('Dashboard Own Pet '.$suffix)
            ->assertDontSee('Municipality performance')
            ->assertDontSee('DashboardForeignRecipient'.$suffix)
            ->assertDontSee('Dashboard Foreign Parcel '.$suffix)
            ->assertDontSee('Dashboard Foreign Pet '.$suffix);

        $this->actingAs($user)
            ->get(route('farmers.index', [
                'q' => 'DashboardOwnMapped'.$suffix,
            ]))
            ->assertOk()
            ->assertSee('id="mapFarmerSearch"', false)
            ->assertSee('window.__farmerGeocodeUrl', false)
            ->assertSee('Draw boundary')
            ->assertSee('DashboardOwnMapped'.$suffix)
            ->assertDontSee('DashboardForeignFarmer'.$suffix);
    }

    public function test_super_admin_dashboard_compares_active_municipalities(): void
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $firstMunicipality = $this->makeMunicipality(
            'Province Alpha '.$suffix,
            'PA'.$suffix
        );
        $secondMunicipality = $this->makeMunicipality(
            'Province Beta '.$suffix,
            'PB'.$suffix
        );

        $superAdmin = User::create([
            'name' => 'Province Dashboard Administrator',
            'email' => 'province-dashboard-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'municipality_id' => null,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Province Alpha Head',
            'email' => 'province-head-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_HEAD,
            'municipality_id' => $firstMunicipality->id,
            'is_active' => true,
        ]);

        $mappedFarmer = $this->makeFarmer(
            $firstMunicipality,
            'ProvinceAlphaMapped'.$suffix,
            'ALPHA-'.$suffix
        );
        $this->makeFarmer(
            $firstMunicipality,
            'ProvinceAlphaUnmapped'.$suffix,
            null
        );
        $secondFarmer = $this->makeFarmer(
            $secondMunicipality,
            'ProvinceBetaFarmer'.$suffix,
            'BETA-'.$suffix
        );

        $this->makePlot($mappedFarmer, 'Province Alpha Parcel '.$suffix);

        RiceSeedDistribution::create([
            'municipality_id' => $firstMunicipality->id,
            'farmer_id' => $mappedFarmer->id,
            'last_name' => 'ProvinceAlphaRecipient'.$suffix,
            'first_name' => 'Operator',
            'kgs_received' => 75,
            'date_received' => now()->toDateString(),
        ]);
        RiceSeedDistribution::create([
            'municipality_id' => $secondMunicipality->id,
            'farmer_id' => $secondFarmer->id,
            'last_name' => 'ProvinceBetaRecipient'.$suffix,
            'first_name' => 'Operator',
            'kgs_received' => 25,
            'date_received' => now()->toDateString(),
        ]);

        $this->makeVaccination(
            $firstMunicipality,
            'Province Alpha Pet '.$suffix
        );

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Municipality performance')
            ->assertSee('Province Alpha '.$suffix)
            ->assertSee('Province Beta '.$suffix)
            ->assertSee('50.0%')
            ->assertSee('75.00 kg')
            ->assertSee('data-status="operational"', false)
            ->assertSee('data-status="missing_head"', false)
            ->assertSee(
                route('admins.index', [
                    'municipality_id' => $firstMunicipality->id,
                ]),
                false
            );
    }

    public function test_authenticated_geocoding_proxy_returns_coordinates(): void
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $municipality = $this->makeMunicipality(
            'Geocode Municipality '.$suffix,
            'GC'.$suffix
        );
        $user = User::create([
            'name' => 'Geocode User',
            'email' => 'geocode-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $municipality->id,
            'is_active' => true,
        ]);

        Http::fake([
            'https://nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '15.4861', 'lon' => '120.5899'],
            ]),
        ]);

        $this->actingAs($user)
            ->getJson(route('geocode', [
                'q' => 'Geocode Test '.$suffix,
            ]))
            ->assertOk()
            ->assertJson([
                'lat' => 15.4861,
                'lng' => 120.5899,
            ]);
    }

    private function makeMunicipality(string $name, string $code): Municipality
    {
        return Municipality::create([
            'name' => $name,
            'province' => 'Tarlac',
            'code' => substr($code, 0, 20),
            'is_active' => true,
        ]);
    }

    private function makeFarmer(
        Municipality $municipality,
        string $lastName,
        ?string $ffrs
    ): Farmer {
        return Farmer::create([
            'municipality_id' => $municipality->id,
            'last_name' => $lastName,
            'first_name' => 'Test',
            'ffrs' => $ffrs,
            'farm_location' => 'Test Barangay',
            'farm_municipality' => $municipality->name,
            'farm_province' => 'Tarlac',
            'farm_area_ha' => 1,
        ]);
    }

    private function makePlot(Farmer $farmer, string $name): FarmPlot
    {
        return FarmPlot::create([
            'farmer_id' => $farmer->id,
            'name' => $name,
            'polygon_json' => [
                ['lat' => 15.0, 'lng' => 120.0],
                ['lat' => 15.1, 'lng' => 120.0],
                ['lat' => 15.1, 'lng' => 120.1],
            ],
            'area_ha' => 1.25,
            'centroid_lat' => 15.066,
            'centroid_lng' => 120.033,
        ]);
    }

    private function makeVaccination(
        Municipality $municipality,
        string $petName
    ): AntiRabiesVaccination {
        return AntiRabiesVaccination::create([
            'municipality_id' => $municipality->id,
            'owner_name' => 'Dashboard Pet Owner',
            'barangay' => 'Test Barangay',
            'birthday' => '1990-01-01',
            'pet_type' => 'Dog',
            'pet_breed' => 'Aspin (Asong Pinoy)',
            'pet_name' => $petName,
            'vaccination_date' => now()->toDateString(),
        ]);
    }
}

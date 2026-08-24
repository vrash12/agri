<?php

namespace Tests\Feature;

use App\Models\AgriculturalMachinery;
use App\Models\AntiRabiesVaccination;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvincialVeterinaryAccessTest extends TestCase
{
    use DatabaseTransactions;

    private User $veterinaryUser;

    private Municipality $firstMunicipality;

    private Municipality $secondMunicipality;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = substr(str_replace('.', '', uniqid('', true)), -10);

        $this->firstMunicipality = Municipality::create([
            'name' => 'Vet First '.$suffix,
            'province' => 'Tarlac',
            'code' => 'VF'.substr($suffix, -8),
            'is_active' => true,
        ]);
        $this->secondMunicipality = Municipality::create([
            'name' => 'Vet Second '.$suffix,
            'province' => 'Tarlac',
            'code' => 'VS'.substr($suffix, -8),
            'is_active' => true,
        ]);
        $this->veterinaryUser = User::create([
            'name' => 'Provincial Veterinary Office',
            'email' => 'provincial-vet-'.$suffix.'@example.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_PROVINCIAL_VET,
            'municipality_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_veterinary_user_logs_in_directly_to_animal_health(): void
    {
        $this->post(route('login.attempt'), [
            'email' => $this->veterinaryUser->email,
            'password' => 'password123',
        ])
            ->assertRedirect(route('anti-rabies-vaccinations.index'));

        $this->assertAuthenticatedAs($this->veterinaryUser);
    }

    public function test_veterinary_user_sees_only_animal_health_navigation(): void
    {
        $this->actingAs($this->veterinaryUser)
            ->get(route('anti-rabies-vaccinations.index'))
            ->assertOk()
            ->assertSee('Veterinary Operations')
            ->assertSee('href="'.route('anti-rabies-vaccinations.index').'"', false)
            ->assertDontSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('href="'.route('farmers.index').'"', false)
            ->assertDontSee('href="'.route('rice-seed-distributions.index').'"', false)
            ->assertDontSee('href="'.route('backups.index').'"', false)
            ->assertDontSee('href="'.route('admins.index').'"', false);
    }

    public function test_veterinary_user_is_redirected_away_from_other_modules(): void
    {
        foreach ([
            route('dashboard'),
            route('farmers.index'),
            route('rice-seed-distributions.index'),
            route('farmers-cooperatives.index'),
            route('machinery-inventory.index'),
            route('backups.index'),
            route('admins.index'),
            route('audit-logs.index'),
            route('weather.index'),
        ] as $url) {
            $this->actingAs($this->veterinaryUser)
                ->get($url)
                ->assertRedirect(route('anti-rabies-vaccinations.index'))
                ->assertSessionHas('error');
        }

        $this->actingAs($this->veterinaryUser)
            ->getJson(route('geocode', ['q' => 'Tarlac']))
            ->assertForbidden()
            ->assertJsonPath('code', 'PROVINCIAL_VET_SCOPE_ONLY');
    }

    public function test_veterinary_policy_access_is_limited_to_animal_health(): void
    {
        $gate = Gate::forUser($this->veterinaryUser);

        $this->assertTrue($gate->allows('viewAny', AntiRabiesVaccination::class));
        $this->assertTrue($gate->allows('create', AntiRabiesVaccination::class));

        foreach ([
            Farmer::class,
            RiceSeedDistribution::class,
            FarmersCooperative::class,
            AgriculturalMachinery::class,
            BackupFile::class,
        ] as $modelClass) {
            $this->assertFalse($gate->allows('viewAny', $modelClass));
            $this->assertFalse($gate->allows('create', $modelClass));
        }

        $this->assertFalse($gate->allows('viewAny', User::class));
    }

    public function test_veterinary_user_can_record_services_for_any_municipality(): void
    {
        $this->actingAs($this->veterinaryUser)
            ->post(route('anti-rabies-vaccinations.store'), [
                'municipality_id' => $this->secondMunicipality->id,
                'owner_name' => 'Province-wide Livestock Raiser',
                'barangay' => 'San Roque',
                'pet_type' => 'Cattle',
                'service_type' => 'treatment',
                'service_name' => 'Wound treatment',
                'animal_count' => 2,
                'vaccination_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('anti-rabies-vaccinations.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('anti_rabies_vaccinations', [
            'municipality_id' => $this->secondMunicipality->id,
            'owner_name' => 'Province-wide Livestock Raiser',
            'service_type' => 'treatment',
            'animal_count' => 2,
        ]);
    }

    public function test_super_admin_can_create_a_veterinary_account(): void
    {
        $superAdmin = User::create([
            'name' => 'Veterinary Account Manager',
            'email' => 'vet-manager-'.uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $email = 'created-vet-'.uniqid().'@example.test';

        $this->actingAs($superAdmin)
            ->post(route('admins.store'), [
                'name' => 'Created Provincial Vet',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => User::ROLE_PROVINCIAL_VET,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admins.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => User::ROLE_PROVINCIAL_VET,
            'municipality_id' => null,
            'is_active' => 1,
        ]);
    }
}

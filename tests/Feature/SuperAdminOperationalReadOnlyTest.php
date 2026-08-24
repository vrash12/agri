<?php

namespace Tests\Feature;

use App\Models\AntiRabiesVaccination;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuperAdminOperationalReadOnlyTest extends TestCase
{
    use DatabaseTransactions;

    private Municipality $municipality;

    private User $superAdmin;

    private Farmer $farmer;

    private RiceSeedDistribution $distribution;

    private AntiRabiesVaccination $vaccination;

    private FarmersCooperative $cooperative;

    private BackupFile $backup;

    private FarmPlot $plot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $suffix = str_replace('.', '', uniqid('', true));

        $this->municipality = Municipality::create([
            'name' => 'Read Only Municipality '.$suffix,
            'province' => 'Tarlac',
            'code' => 'RO'.substr($suffix, -8),
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Read Only Super Admin',
            'email' => 'readonly-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->farmer = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'ReadOnly',
            'last_name' => 'Farmer',
        ]);
        $this->distribution = RiceSeedDistribution::create([
            'municipality_id' => $this->municipality->id,
            'farmer_id' => $this->farmer->id,
            'first_name' => 'ReadOnly',
            'last_name' => 'Farmer',
            'kgs_received' => 10,
            'date_received' => now()->toDateString(),
        ]);
        $this->vaccination = AntiRabiesVaccination::create([
            'municipality_id' => $this->municipality->id,
            'owner_name' => 'Read Only Owner',
            'barangay' => 'Poblacion Center',
            'birthday' => '1990-01-01',
            'pet_type' => 'Dog',
            'pet_breed' => 'Aspin (Asong Pinoy)',
            'pet_name' => 'Bantay',
            'vaccination_date' => now()->toDateString(),
        ]);
        $this->cooperative = FarmersCooperative::create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Read Only Cooperative '.$suffix,
        ]);
        $this->backup = BackupFile::create([
            'municipality_id' => $this->municipality->id,
            'disk' => 'local',
            'folder' => 'tests',
            'original_name' => 'readonly.txt',
            'stored_name' => 'readonly.txt',
            'path' => 'backups/tests/readonly.txt',
            'size' => 8,
            'mime' => 'text/plain',
            'uploaded_by' => $this->superAdmin->id,
        ]);
        Storage::disk('local')->put($this->backup->path, 'original');

        $this->plot = FarmPlot::create([
            'farmer_id' => $this->farmer->id,
            'name' => 'Read Only Plot',
            'polygon_json' => [
                ['lat' => 15.0, 'lng' => 120.0],
                ['lat' => 15.1, 'lng' => 120.0],
                ['lat' => 15.1, 'lng' => 120.1],
            ],
        ]);
    }

    public function test_super_admin_sees_operational_modules_as_read_only(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Find a module')
            ->assertSee('User Management')
            ->assertDontSee('data-label="Backup Folder"', false)
            ->assertDontSee('href="'.route('backups.index').'"', false);

        $this->actingAs($this->superAdmin)
            ->get(route('farmers.index'))
            ->assertOk()
            ->assertSee('Read-only oversight')
            ->assertDontSee('Add farmer');

        $this->actingAs($this->superAdmin)
            ->get(route('rice-seed-distributions.index'))
            ->assertOk()
            ->assertSee('Read-only oversight')
            ->assertDontSee(
                'href="'.route('rice-seed-distributions.create').'"',
                false
            );

        $this->actingAs($this->superAdmin)
            ->get(route('anti-rabies-vaccinations.index'))
            ->assertOk()
            ->assertSee('Read-only oversight')
            ->assertDontSee('Record service');

        $this->actingAs($this->superAdmin)
            ->get(route('farmers-cooperatives.index'))
            ->assertOk()
            ->assertSee('Read-only oversight')
            ->assertDontSee('New cooperative');

        $this->actingAs($this->superAdmin)
            ->get(route('admins.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admins.create'))
            ->assertOk();
    }

    public function test_super_admin_cannot_open_operational_input_pages(): void
    {
        $routes = [
            route('farmers.create'),
            route('farmers.import.form'),
            route('farmers.edit', $this->farmer),
            route('farm-plots.import.form'),
            route('rice-seed-distributions.create'),
            route('rice-seed-distributions.import.form'),
            route('rice-seed-distributions.edit', $this->distribution),
            route('anti-rabies-vaccinations.create'),
            route('anti-rabies-vaccinations.edit', $this->vaccination),
            route('farmers-cooperatives.create'),
            route('farmers-cooperatives.edit', $this->cooperative),
            route('farmers-cooperatives.assign-farmers', $this->cooperative),
            route('backups.index'),
            route('backups.preview', $this->backup),
            route('backups.download', $this->backup),
            route('backups.stream', $this->backup),
            route('backups.preview', [$this->backup, 'mode' => 'edit']),
        ];

        foreach ($routes as $route) {
            $this->actingAs($this->superAdmin)
                ->get($route)
                ->assertForbidden();
        }
    }

    public function test_super_admin_cannot_submit_operational_writes(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('farmers.store'), [
                'municipality_id' => $this->municipality->id,
                'first_name' => 'Blocked',
                'last_name' => 'Farmer',
            ])->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->put(route('farmers.update', $this->farmer), [
                'municipality_id' => $this->municipality->id,
                'first_name' => 'Changed',
                'last_name' => 'Farmer',
            ])->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->delete(route('farmers.destroy', $this->farmer))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post(route('farmers.plots.store', $this->farmer), [
                'name' => 'Blocked Plot',
                'polygon' => [],
            ])->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->put(route('farm-plots.update', $this->plot), ['name' => 'Changed'])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->delete(route('farm-plots.destroy', $this->plot))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post(route('rice-seed-distributions.store'), [])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->put(route('rice-seed-distributions.update', $this->distribution), [])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->delete(route('rice-seed-distributions.destroy', $this->distribution))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post(route('anti-rabies-vaccinations.store'), [])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->put(route('anti-rabies-vaccinations.update', $this->vaccination), [])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->delete(route('anti-rabies-vaccinations.destroy', $this->vaccination))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post(route('farmers-cooperatives.store'), [])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->put(route('farmers-cooperatives.update', $this->cooperative), [])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->put(route('farmers-cooperatives.save-assigned-farmers', $this->cooperative), [])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->delete(route('farmers-cooperatives.destroy', $this->cooperative))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post(route('backups.store'), [
                'files' => [UploadedFile::fake()->create('blocked.txt', 1)],
                'municipality_id' => $this->municipality->id,
            ])->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->post(route('backups.save', $this->backup), [
                'kind' => 'text',
                'content' => 'changed',
            ])->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->delete(route('backups.destroy', $this->backup))
            ->assertForbidden();

        $this->assertDatabaseHas('farmers', [
            'id' => $this->farmer->id,
            'first_name' => 'ReadOnly',
        ]);
        $this->assertDatabaseHas('farm_plots', ['id' => $this->plot->id]);
        $this->assertDatabaseHas('rice_seed_distributions', [
            'id' => $this->distribution->id,
        ]);
        $this->assertDatabaseHas('anti_rabies_vaccinations', [
            'id' => $this->vaccination->id,
        ]);
        $this->assertDatabaseHas('farmers_cooperatives', [
            'id' => $this->cooperative->id,
        ]);
        $this->assertDatabaseHas('backup_files', ['id' => $this->backup->id]);
        Storage::disk('local')->assertExists($this->backup->path);
    }

    public function test_super_admin_can_still_create_user_accounts(): void
    {
        $email = 'managed-user-'.uniqid().'@example.test';

        $this->actingAs($this->superAdmin)
            ->post(route('admins.store'), [
                'name' => 'Managed Provincial Staff',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => User::ROLE_PROVINCIAL_STAFF,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admins.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => User::ROLE_PROVINCIAL_STAFF,
            'is_active' => 1,
        ]);
    }

    public function test_backup_folder_remains_available_to_other_authorized_roles(): void
    {
        $provincialStaff = User::create([
            'name' => 'Backup Provincial Staff',
            'email' => 'backup-staff-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PROVINCIAL_STAFF,
            'is_active' => true,
        ]);

        $this->actingAs($provincialStaff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-label="Backup Folder"', false)
            ->assertSee('href="'.route('backups.index').'"', false);

        $this->actingAs($provincialStaff)
            ->get(route('backups.index'))
            ->assertOk();
    }
}

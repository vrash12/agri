<?php

namespace Tests\Feature;

use App\Models\AgriculturalMachinery;
use App\Models\AntiRabiesVaccination;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MunicipalitySeparationTest extends TestCase
{
    use DatabaseTransactions;

    private Municipality $firstMunicipality;

    private Municipality $secondMunicipality;

    private User $municipalUser;

    private User $provincialUser;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);

        $this->firstMunicipality = Municipality::create([
            'name' => 'First Municipality '.$suffix,
            'province' => 'Tarlac',
            'code' => 'F'.substr(md5($suffix), 0, 8),
            'is_active' => true,
        ]);
        $this->secondMunicipality = Municipality::create([
            'name' => 'Second Municipality '.$suffix,
            'province' => 'Tarlac',
            'code' => 'S'.substr(md5($suffix), 0, 8),
            'is_active' => true,
        ]);

        $this->municipalUser = $this->makeUser(
            User::ROLE_MUNICIPAL_STAFF,
            $this->firstMunicipality->id,
            'municipal-'.$suffix.'@example.test'
        );
        $this->provincialUser = $this->makeUser(
            User::ROLE_PROVINCIAL_STAFF,
            null,
            'provincial-'.$suffix.'@example.test'
        );
    }

    public function test_municipal_lists_only_show_owned_records(): void
    {
        $ownRice = RiceSeedDistribution::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'OwnRiceUnique',
            'first_name' => 'Owner',
            'kgs_received' => 10,
            'date_received' => now()->toDateString(),
        ]);
        RiceSeedDistribution::create([
            'municipality_id' => $this->secondMunicipality->id,
            'last_name' => 'ForeignRiceUnique',
            'first_name' => 'Owner',
            'kgs_received' => 20,
            'date_received' => now()->toDateString(),
        ]);

        $ownVaccination = $this->makeVaccination(
            $this->firstMunicipality->id,
            'OwnVaccinationUnique'
        );
        $foreignVaccination = $this->makeVaccination(
            $this->secondMunicipality->id,
            'ForeignVaccinationUnique'
        );

        $ownCooperative = FarmersCooperative::create([
            'municipality_id' => $this->firstMunicipality->id,
            'name' => 'Own Cooperative Unique',
        ]);
        $foreignCooperative = FarmersCooperative::create([
            'municipality_id' => $this->secondMunicipality->id,
            'name' => 'Foreign Cooperative Unique',
        ]);

        $ownBackup = $this->makeBackup(
            $this->firstMunicipality->id,
            'own-backup-unique.sql'
        );
        $foreignBackup = $this->makeBackup(
            $this->secondMunicipality->id,
            'foreign-backup-unique.sql'
        );

        $this->actingAs($this->municipalUser)
            ->get(route('rice-seed-distributions.index'))
            ->assertOk()
            ->assertSee($ownRice->last_name)
            ->assertDontSee('ForeignRiceUnique');

        $this->actingAs($this->municipalUser)
            ->get(route('anti-rabies-vaccinations.index'))
            ->assertOk()
            ->assertSee($ownVaccination->owner_name)
            ->assertDontSee($foreignVaccination->owner_name);

        $this->actingAs($this->municipalUser)
            ->get(route('farmers-cooperatives.index'))
            ->assertOk()
            ->assertSee($ownCooperative->name)
            ->assertDontSee($foreignCooperative->name);

        $this->actingAs($this->municipalUser)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee($ownBackup->original_name)
            ->assertDontSee($foreignBackup->original_name);
    }

    public function test_cross_municipality_record_actions_are_forbidden(): void
    {
        $foreignRice = RiceSeedDistribution::create([
            'municipality_id' => $this->secondMunicipality->id,
        ]);
        $foreignVaccination = $this->makeVaccination(
            $this->secondMunicipality->id,
            'Foreign Owner'
        );
        $foreignCooperative = FarmersCooperative::create([
            'municipality_id' => $this->secondMunicipality->id,
            'name' => 'Foreign Cooperative',
        ]);
        $foreignBackup = $this->makeBackup(
            $this->secondMunicipality->id,
            'foreign.sql'
        );

        $this->actingAs($this->municipalUser)
            ->get(route('rice-seed-distributions.edit', $foreignRice))
            ->assertForbidden();
        $this->actingAs($this->municipalUser)
            ->get(route('anti-rabies-vaccinations.edit', $foreignVaccination))
            ->assertForbidden();
        $this->actingAs($this->municipalUser)
            ->get(route('farmers-cooperatives.edit', $foreignCooperative))
            ->assertForbidden();
        $this->actingAs($this->municipalUser)
            ->get(route('backups.download', $foreignBackup))
            ->assertForbidden();
    }

    public function test_municipal_writes_are_automatically_owned(): void
    {
        Storage::fake('local');
        $farmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'Municipal',
            'first_name' => 'Farmer',
        ]);

        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), [
                'farmer_id' => $farmer->id,
                'seed_variety_claimed' => 'BIO ZARAP',
                'kgs_received' => 25,
                'date_received' => now()->toDateString(),
            ])
            ->assertRedirect(route('rice-seed-distributions.index'));

        $this->actingAs($this->municipalUser)
            ->post(route('anti-rabies-vaccinations.store'), [
                'owner_name' => 'Municipal Pet Owner',
                'barangay' => 'Poblacion  Center',
                'birthday' => '1990-01-01',
                'pet_type' => 'Dog',
                'pet_breed' => 'Aspin (Asong Pinoy)',
                'pet_name' => 'Bantay',
                'vaccination_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('anti-rabies-vaccinations.index'));

        $this->actingAs($this->municipalUser)
            ->post(route('farmers-cooperatives.store'), [
                'name' => 'Municipal Cooperative Write',
            ])
            ->assertRedirect();

        $this->actingAs($this->municipalUser)
            ->post(route('backups.store'), [
                'files' => [UploadedFile::fake()->create('backup.sql', 10)],
            ])
            ->assertRedirect(route('backups.index'));

        $this->assertDatabaseHas('rice_seed_distributions', [
            'farmer_id' => $farmer->id,
            'municipality_id' => $this->firstMunicipality->id,
        ]);
        $this->assertDatabaseHas('anti_rabies_vaccinations', [
            'owner_name' => 'Municipal Pet Owner',
            'pet_type' => 'Dog',
            'municipality_id' => $this->firstMunicipality->id,
        ]);
        $this->assertDatabaseHas('farmers_cooperatives', [
            'name' => 'Municipal Cooperative Write',
            'municipality_id' => $this->firstMunicipality->id,
        ]);
        $this->assertDatabaseHas('backup_files', [
            'original_name' => 'backup.sql',
            'municipality_id' => $this->firstMunicipality->id,
        ]);
    }

    public function test_distribution_accepts_fertilizer_and_custom_farm_inputs(): void
    {
        $farmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'Input',
            'first_name' => 'Recipient',
        ]);

        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), [
                'farmer_id' => $farmer->id,
                'input_category' => 'fertilizer',
                'seed_variety_claimed' => 'Custom Abono 16-20-0',
                'kgs_received' => 3,
                'quantity_unit' => 'sack',
                'input_notes' => 'For basal application',
                'date_received' => now()->toDateString(),
            ])
            ->assertRedirect(route('rice-seed-distributions.index'));

        $this->assertDatabaseHas('rice_seed_distributions', [
            'farmer_id' => $farmer->id,
            'municipality_id' => $this->firstMunicipality->id,
            'input_category' => 'fertilizer',
            'seed_variety_claimed' => 'Custom Abono 16-20-0',
            'kgs_received' => 3,
            'quantity_unit' => 'sack',
            'input_notes' => 'For basal application',
        ]);

        $this->actingAs($this->municipalUser)
            ->get(route('rice-seed-distributions.index', [
                'input_category' => 'fertilizer',
            ]))
            ->assertOk()
            ->assertSee('Custom Abono 16-20-0')
            ->assertSee('Fertilizer / Abono');
    }

    public function test_distribution_accepts_fingerlings_and_scopes_fisheries_to_the_municipality(): void
    {
        $farmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'Fisheries',
            'first_name' => 'Beneficiary',
        ]);

        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), [
                'farmer_id' => $farmer->id,
                'input_category' => 'fish_fingerlings',
                'seed_variety_claimed' => 'Tilapia fingerlings',
                'kgs_received' => 2500,
                'quantity_unit' => 'piece',
                'input_notes' => 'From the municipal hatchery',
                'date_received' => now()->toDateString(),
            ])
            ->assertRedirect(route('rice-seed-distributions.index'));

        $this->assertDatabaseHas('rice_seed_distributions', [
            'farmer_id' => $farmer->id,
            'municipality_id' => $this->firstMunicipality->id,
            'input_category' => 'fish_fingerlings',
            'seed_variety_claimed' => 'Tilapia fingerlings',
            'kgs_received' => 2500,
            'quantity_unit' => 'piece',
        ]);

        $this->actingAs($this->municipalUser)
            ->get(route('rice-seed-distributions.index', [
                'assistance_sector' => 'fisheries',
            ]))
            ->assertOk()
            ->assertSee('Tilapia fingerlings')
            ->assertSee('Fish fingerlings')
            ->assertSee('2,500')
            ->assertSee('Fisheries assistance');
    }

    public function test_farm_plots_are_scoped_to_the_farmer_municipality(): void
    {
        $ownFarmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'Own',
            'first_name' => 'Farmer',
        ]);
        $foreignFarmer = Farmer::create([
            'municipality_id' => $this->secondMunicipality->id,
            'last_name' => 'Foreign',
            'first_name' => 'Farmer',
        ]);
        $ownPlot = $this->makePlot($ownFarmer, 'Own Plot Unique');
        $foreignPlot = $this->makePlot($foreignFarmer, 'Foreign Plot Unique');

        $this->actingAs($this->municipalUser)
            ->getJson(route('farm-plots.all'))
            ->assertOk()
            ->assertJsonFragment(['id' => $ownPlot->id])
            ->assertJsonMissing(['id' => $foreignPlot->id]);

        $this->actingAs($this->municipalUser)
            ->getJson(route('farmers.plots.index', $foreignFarmer))
            ->assertForbidden();
        $this->actingAs($this->municipalUser)
            ->deleteJson(route('farm-plots.destroy', $foreignPlot))
            ->assertForbidden();

        $this->actingAs($this->municipalUser)
            ->postJson(route('farmers.plots.store', $ownFarmer), [
                'name' => 'New Secure Plot',
                'polygon' => [
                    ['lat' => 15.0, 'lng' => 120.0],
                    ['lat' => 15.1, 'lng' => 120.0],
                    ['lat' => 15.1, 'lng' => 120.1],
                ],
            ])
            ->assertCreated();
    }

    public function test_provincial_farmer_workspace_keeps_registry_and_map_in_one_municipality(): void
    {
        $matchingFarmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'WorkspaceMatch',
            'first_name' => 'Anao',
        ]);
        $otherFarmerInMunicipality = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'WorkspaceMapOnly',
            'first_name' => 'Anao',
        ]);
        $foreignFarmer = Farmer::create([
            'municipality_id' => $this->secondMunicipality->id,
            'last_name' => 'WorkspaceForeign',
            'first_name' => 'Bamban',
        ]);
        $ownPlot = $this->makePlot(
            $otherFarmerInMunicipality,
            'Workspace Own Boundary'
        );
        $foreignPlot = $this->makePlot(
            $foreignFarmer,
            'Workspace Foreign Boundary'
        );

        $this->actingAs($this->provincialUser)
            ->get(route('farmers.index', [
                'municipality_id' => $this->firstMunicipality->id,
                'q' => 'WorkspaceMatch',
            ]))
            ->assertOk()
            ->assertViewHas(
                'selectedMunicipality',
                fn ($municipality) => (int) $municipality->id
                    === (int) $this->firstMunicipality->id
            )
            ->assertViewHas('farmers', function ($farmers) use ($matchingFarmer) {
                return $farmers->count() === 1
                    && (int) $farmers->first()->id === (int) $matchingFarmer->id;
            })
            ->assertViewHas(
                'mapFarmers',
                function ($mapFarmers) use (
                    $matchingFarmer,
                    $otherFarmerInMunicipality,
                    $foreignFarmer
                ) {
                    $ids = $mapFarmers->pluck('id')->map(fn ($id) => (int) $id);

                    return $ids->contains((int) $matchingFarmer->id)
                        && $ids->contains((int) $otherFarmerInMunicipality->id)
                        && ! $ids->contains((int) $foreignFarmer->id);
                }
            );

        $this->actingAs($this->provincialUser)
            ->getJson(route('farm-plots.all', [
                'municipality_id' => $this->firstMunicipality->id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $ownPlot->id])
            ->assertJsonMissing(['id' => $foreignPlot->id]);
    }

    public function test_farmer_photos_and_id_cards_are_municipality_scoped(): void
    {
        Storage::fake('local');

        $ownFarmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'Photo',
            'first_name' => 'Owner',
        ]);
        $foreignFarmer = Farmer::create([
            'municipality_id' => $this->secondMunicipality->id,
            'last_name' => 'Photo',
            'first_name' => 'Foreign',
        ]);

        $this->actingAs($this->municipalUser)
            ->put(route('farmers.update', $ownFarmer), [
                '_record_version' => ConcurrentWrite::version($ownFarmer),
                'last_name' => $ownFarmer->last_name,
                'first_name' => $ownFarmer->first_name,
                'profile_photo' => UploadedFile::fake()
                    ->image('farmer.jpg', 320, 320),
            ])
            ->assertRedirect(route('farmers.index'))
            ->assertSessionHasNoErrors();

        $ownFarmer->refresh();
        $this->assertNotNull($ownFarmer->profile_photo_path);
        Storage::disk('local')->assertExists($ownFarmer->profile_photo_path);

        $this->actingAs($this->municipalUser)
            ->get(route('farmers.photo', $ownFarmer))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($this->municipalUser)
            ->get(route('farmers.id-card', $ownFarmer))
            ->assertOk()
            ->assertSee($ownFarmer->registry_id);

        $this->actingAs($this->municipalUser)
            ->get(route('farmers.photo', $foreignFarmer))
            ->assertForbidden();

        $this->actingAs($this->municipalUser)
            ->get(route('farmers.id-card', $foreignFarmer))
            ->assertForbidden();
    }

    public function test_kml_import_only_matches_farmers_in_the_selected_municipality(): void
    {
        $ownFarmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'ffrs' => 'KML-OWN-'.substr(md5(uniqid('', true)), 0, 8),
            'last_name' => 'KmlOwn',
            'first_name' => 'Farmer',
        ]);
        $foreignFarmer = Farmer::create([
            'municipality_id' => $this->secondMunicipality->id,
            'ffrs' => 'KML-FOREIGN-'.substr(md5(uniqid('', true)), 0, 8),
            'last_name' => 'KmlForeign',
            'first_name' => 'Farmer',
        ]);

        $kml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<kml xmlns="http://www.opengis.net/kml/2.2"><Document>'
            .$this->kmlPlacemark('Own Imported Plot', $ownFarmer->ffrs)
            .$this->kmlPlacemark('Foreign Imported Plot', $foreignFarmer->ffrs)
            .'</Document></kml>';

        $this->actingAs($this->municipalUser)
            ->post(route('farm-plots.import'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'plots.kml',
                    $kml
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'success',
                fn (string $message) => str_contains($message, 'Created: 1')
            );

        $this->assertDatabaseHas('farm_plots', [
            'farmer_id' => $ownFarmer->id,
        ]);
        $this->assertDatabaseMissing('farm_plots', [
            'farmer_id' => $foreignFarmer->id,
        ]);
    }

    public function test_provincial_users_must_choose_ownership_for_writes(): void
    {
        $this->actingAs($this->provincialUser)
            ->post(route('farmers-cooperatives.store'), [
                'name' => 'Missing Municipality Cooperative',
            ])
            ->assertSessionHasErrors('municipality_id');

        $this->actingAs($this->provincialUser)
            ->post(route('farmers-cooperatives.store'), [
                'municipality_id' => $this->secondMunicipality->id,
                'name' => 'Provincial Selected Cooperative',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('farmers_cooperatives', [
            'name' => 'Provincial Selected Cooperative',
            'municipality_id' => $this->secondMunicipality->id,
        ]);
    }

    public function test_machinery_inventory_is_scoped_and_assigns_municipal_ownership(): void
    {
        $ownFarmer = Farmer::create([
            'municipality_id' => $this->firstMunicipality->id,
            'last_name' => 'Machinery',
            'first_name' => 'Holder',
        ]);
        $foreignFarmer = Farmer::create([
            'municipality_id' => $this->secondMunicipality->id,
            'last_name' => 'Foreign',
            'first_name' => 'Holder',
        ]);

        AgriculturalMachinery::create([
            'municipality_id' => $this->secondMunicipality->id,
            'farmer_id' => $foreignFarmer->id,
            'asset_code' => 'FOREIGN-TRC-001',
            'name' => 'Foreign Tractor Unique',
            'category' => 'tractor',
            'condition_status' => 'good',
            'availability_status' => 'available',
        ]);

        $this->actingAs($this->municipalUser)
            ->post(route('machinery-inventory.store'), [
                'holder_type' => 'farmer',
                'holder_id' => $ownFarmer->id,
                'asset_code' => 'ANAO-TRC-001',
                'name' => 'Municipal Tractor Unique',
                'category' => 'tractor',
                'condition_status' => 'good',
                'availability_status' => 'available',
            ])
            ->assertRedirect(route('machinery-inventory.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('agricultural_machineries', [
            'municipality_id' => $this->firstMunicipality->id,
            'farmer_id' => $ownFarmer->id,
            'farmers_cooperative_id' => null,
            'asset_code' => 'ANAO-TRC-001',
        ]);

        $this->actingAs($this->municipalUser)
            ->get(route('machinery-inventory.index'))
            ->assertOk()
            ->assertSee('Municipal Tractor Unique')
            ->assertDontSee('Foreign Tractor Unique');
    }

    public function test_machinery_holder_must_belong_to_the_same_municipality(): void
    {
        $foreignCooperative = FarmersCooperative::create([
            'municipality_id' => $this->secondMunicipality->id,
            'name' => 'Foreign Machinery Cooperative',
        ]);

        $this->actingAs($this->municipalUser)
            ->post(route('machinery-inventory.store'), [
                'holder_type' => 'cooperative',
                'holder_id' => $foreignCooperative->id,
                'asset_code' => 'ANAO-HRV-001',
                'name' => 'Invalid Holder Harvester',
                'category' => 'combine_harvester',
                'condition_status' => 'good',
                'availability_status' => 'available',
            ])
            ->assertSessionHasErrors('holder_id');

        $this->assertDatabaseMissing('agricultural_machineries', [
            'asset_code' => 'ANAO-HRV-001',
        ]);
    }

    public function test_super_admin_can_view_but_cannot_write_machinery(): void
    {
        $superAdmin = $this->makeUser(
            User::ROLE_SUPER_ADMIN,
            null,
            'super-machinery-'.uniqid().'@example.test'
        );

        $this->actingAs($superAdmin)
            ->get(route('machinery-inventory.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('machinery-inventory.create'))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('machinery-inventory.store'), [
                'municipality_id' => $this->firstMunicipality->id,
            ])
            ->assertForbidden();
    }

    private function makeUser(
        string $role,
        ?int $municipalityId,
        string $email
    ): User {
        return User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'municipality_id' => $municipalityId,
            'is_active' => true,
        ]);
    }

    private function makeVaccination(
        int $municipalityId,
        string $ownerName
    ): AntiRabiesVaccination {
        return AntiRabiesVaccination::create([
            'municipality_id' => $municipalityId,
            'owner_name' => $ownerName,
            'barangay' => 'Poblacion  Center',
            'birthday' => '1990-01-01',
            'pet_type' => 'Dog',
            'pet_breed' => 'Aspin (Asong Pinoy)',
            'pet_name' => 'Bantay',
            'vaccination_date' => now()->toDateString(),
        ]);
    }

    private function makeBackup(
        int $municipalityId,
        string $name
    ): BackupFile {
        return BackupFile::create([
            'municipality_id' => $municipalityId,
            'disk' => 'local',
            'folder' => 'tests',
            'original_name' => $name,
            'stored_name' => $name,
            'path' => 'backups/tests/'.$name,
            'size' => 1,
            'mime' => 'text/plain',
            'uploaded_by' => $this->municipalUser->id,
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
        ]);
    }

    private function kmlPlacemark(string $name, string $ffrs): string
    {
        return '<Placemark><name>'.$name.'</name>'
            .'<ExtendedData><Data name="FFRS"><value>'.$ffrs
            .'</value></Data></ExtendedData>'
            .'<Polygon><outerBoundaryIs><LinearRing><coordinates>'
            .'120.0,15.0,0 120.1,15.0,0 120.1,15.1,0 120.0,15.0,0'
            .'</coordinates></LinearRing></outerBoundaryIs></Polygon>'
            .'</Placemark>';
    }
}

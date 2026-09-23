<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FarmerIdentifierWorkflowTest extends TestCase
{
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        // This suite must never use the owner's emptied local or production tables.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true,
            'cache.default' => 'array', 'session.driver' => 'array', 'services.google_maps.key' => '', 'services.google_maps.map_id' => '']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
        DB::table('provinces')->insert(['id' => 1, 'name' => 'Test province']);
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Own municipality', 'province' => 'Test province', 'province_id' => 1],
            ['id' => 2, 'name' => 'Other municipality', 'province' => 'Test province', 'province_id' => 1],
        ]);
        $this->staff = User::create(['name' => 'Registry staff', 'email' => 'identifier@example.test', 'password' => 'unused',
            'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => 1, 'is_active' => true]);
        $this->actingAs($this->staff);
    }

    public function test_creation_assigns_display_id_without_accepting_a_supplied_id_or_issuing_portal_access(): void
    {
        $this->post(route('farmers.store'), ['first_name' => 'New', 'last_name' => 'Farmer',
            'agri_gov_id' => 'AGRI-F-999999', 'id' => 999999])
            ->assertRedirect(route('farmers.index'))->assertSessionHasNoErrors();

        $farmer = Farmer::sole();
        $this->assertSame(1, $farmer->id);
        $this->assertSame(1, $farmer->municipality_id);
        $this->assertSame('AGRI-F-000001', $farmer->agri_gov_id);
        $this->assertSame(0, FarmerPortalAccount::count());
        $this->assertArrayNotHasKey('agri_gov_id', $farmer->getAttributes());
    }

    public function test_current_and_legacy_id_searches_find_only_the_exact_farmer_in_every_finder(): void
    {
        $farmer = $this->farmer(1, 'Selected');
        $this->farmer(1, 'Another');
        foreach ([$farmer->agri_gov_id, strtolower($farmer->agri_gov_id), ' '.$farmer->agri_gov_id.' ', $farmer->registry_id] as $identifier) {
            $directory = $this->get(route('farmers.index', ['q' => $identifier]))->assertOk();
            $this->assertSame([$farmer->id], $directory->viewData('farmers')->pluck('id')->all());
            $directory->assertSee($farmer->agri_gov_id);

            $this->getJson(route('farmers.lookup', ['q' => $identifier]))->assertOk()
                ->assertJsonCount(1, 'farmers')->assertJsonPath('farmers.0.id', $farmer->id)
                ->assertJsonPath('farmers.0.agri_gov_id', $farmer->agri_gov_id);

            $picker = $this->getJson(route('farmers.picker', ['q' => $identifier]))->assertOk()
                ->assertJsonCount(1, 'farmers')->assertJsonPath('farmers.0.value', (string) $farmer->id);
            $this->assertStringContainsString($farmer->agri_gov_id, $picker->json('farmers.0.label'));
        }
    }

    public function test_known_foreign_identifier_cannot_bypass_municipality_scope(): void
    {
        $this->farmer(1, 'Visible');
        $foreign = $this->farmer(2, 'Private');
        foreach ([$foreign->agri_gov_id, $foreign->registry_id] as $identifier) {
            $query = ['q' => $identifier, 'municipality_id' => 2];
            $directory = $this->get(route('farmers.index', $query))->assertOk();
            $this->assertSame(0, $directory->viewData('farmers')->total());
            $this->getJson(route('farmers.lookup', $query))->assertOk()->assertJsonCount(0, 'farmers');
            $this->getJson(route('farmers.picker', $query))->assertOk()->assertJsonCount(0, 'farmers');
        }
        $this->getJson(route('farmers.map-card', $foreign))->assertForbidden();
        $this->get(route('farmers.id-card', $foreign))->assertForbidden();
    }

    public function test_identifier_survives_profile_changes_and_linked_numeric_foreign_keys_are_preserved(): void
    {
        $farmer = $this->farmer(1, 'Original');
        DB::table('farm_plots')->insert(['farmer_id' => $farmer->id, 'name' => 'Parcel', 'area_ha' => 2]);
        DB::table('rice_seed_distributions')->insert(['farmer_id' => $farmer->id, 'municipality_id' => 1, 'kgs_received' => 10]);
        DB::table('farmer_portal_accounts')->insert(['farmer_id' => $farmer->id, 'municipality_id' => 1, 'login_id' => $farmer->agri_gov_id]);
        $portalBefore = FarmerPortalAccount::sole()->getAttributes();
        $identifier = $farmer->agri_gov_id;
        $publicToken = $farmer->public_map_token;

        $this->put(route('farmers.update', $farmer), ['first_name' => 'Renamed', 'last_name' => 'Farmer',
            '_record_version' => ConcurrentWrite::version($farmer->fresh()), 'agri_gov_id' => 'AGRI-F-999999'])
            ->assertRedirect(route('farmers.index'))->assertSessionHasNoErrors();

        $farmer->refresh();
        $this->assertSame($identifier, $farmer->agri_gov_id);
        $this->assertSame($publicToken, $farmer->public_map_token);
        $this->assertSame($farmer->id, $farmer->farmPlots()->sole()->farmer_id);
        $this->assertSame($farmer->id, $farmer->riceSeedDistributions()->sole()->farmer_id);
        $this->assertSame($portalBefore, FarmerPortalAccount::sole()->getAttributes());
    }

    public function test_linked_operational_searches_preserve_scope_when_using_the_display_id(): void
    {
        $own = $this->farmer(1, 'Own');
        $another = $this->farmer(1, 'Another');
        $foreign = $this->farmer(2, 'Foreign');
        foreach ([$own, $another, $foreign] as $farmer) {
            $scope = ['farmer_id' => $farmer->id, 'municipality_id' => $farmer->municipality_id];
            DB::table('harvest_records')->insert($scope + ['commodity' => 'corn', 'variety' => 'Harvest'.$farmer->id]);
            DB::table('agricultural_machineries')->insert($scope + ['asset_code' => 'ASSET'.$farmer->id, 'name' => 'Machine', 'category' => 'tractor']);
            DB::table('rice_seed_distributions')->insert($scope + ['first_name' => 'Release'.$farmer->id]);
        }

        foreach ([$own->agri_gov_id, $own->registry_id] as $identifier) {
            foreach (['harvest-records.index', 'machinery-inventory.index'] as $route) {
                $response = $this->get(route($route, ['q' => $identifier]))->assertOk();
                $this->assertSame([$own->id], $response->viewData('records')->pluck('farmer_id')->all());
            }
            $export = $this->get(route('rice-seed-distributions.export', ['q' => $identifier]))->assertOk()->streamedContent();
            $this->assertStringContainsString('Release'.$own->id, $export);
            $this->assertStringNotContainsString('Release'.$another->id, $export);
            $this->assertStringNotContainsString('Release'.$foreign->id, $export);
        }

        foreach ([$foreign->agri_gov_id, $foreign->registry_id] as $identifier) {
            foreach (['harvest-records.index', 'machinery-inventory.index'] as $route) {
                $response = $this->get(route($route, ['q' => $identifier]))->assertOk();
                $this->assertSame(0, $response->viewData('records')->total());
            }
            $export = $this->get(route('rice-seed-distributions.export', ['q' => $identifier]))->assertOk()->streamedContent();
            $this->assertStringNotContainsString('Release'.$foreign->id, $export);
        }
    }

    public function test_card_and_map_identifiers_match_without_adding_private_fields_to_lookup(): void
    {
        $farmer = $this->farmer(1, 'Map');
        $farmer->update(['date_of_birth' => '1970-01-01', 'contact_number' => 'private-contact', 'rsbsa_no' => 'private-rsbsa']);
        $body = $this->getJson(route('farmers.lookup', ['q' => $farmer->agri_gov_id]))
            ->assertOk()->assertJsonPath('farmers.0.agri_gov_id', $farmer->agri_gov_id)->json('farmers.0');
        foreach (['date_of_birth', 'contact_number', 'rsbsa_no', 'registry_id', 'public_map_token'] as $private) {
            $this->assertArrayNotHasKey($private, $body);
        }
        $this->getJson(route('farmers.map-card', $farmer))->assertOk()
            ->assertJsonPath('id', $farmer->id)->assertJsonPath('agri_gov_id', $farmer->agri_gov_id);
        $this->get(route('farmers.id-card', $farmer))->assertOk()->assertSee($farmer->agri_gov_id);
    }

    private function farmer(int $municipalityId, string $firstName): Farmer
    {
        return Farmer::create(['municipality_id' => $municipalityId, 'first_name' => $firstName,
            'last_name' => 'Farmer', 'farm_location' => 'Test barangay']);
    }

    private function schema(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->foreignId('province_id')->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach (['name', 'email', 'password', 'role'] as $field) {
                $table->string($field);
            }
            $table->foreignId('municipality_id')->nullable()->constrained();
            $table->foreignId('province_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained();
            $table->string('first_name');
            $table->string('last_name');
            foreach (['middle_name', 'ext_name', 'owner_name', 'rsbsa_no', 'ffrs', 'gender', 'contact_number', 'farm_location', 'farm_municipality', 'farm_province', 'ecosystem', 'ecosystem_source', 'profile_photo_path', 'public_map_token'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['is_arb', 'is_4ps', 'is_ip', 'is_pwd', 'is_sc', 'is_ofw'] as $field) {
                $table->boolean($field)->default(false);
            }
            $table->date('date_of_birth')->nullable();
            $table->decimal('farm_area_ha', 15, 4)->nullable();
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained();
            $table->string('name');
            $table->decimal('area_ha', 15, 4)->nullable();
            $table->timestamps();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained();
            $table->foreignId('farmer_id')->constrained();
            $table->string('quantity_unit')->nullable();
            $table->decimal('kgs_received', 15, 4)->nullable();
            $table->date('date_received')->nullable();
            foreach (['last_name', 'first_name', 'middle_name', 'ffrs', 'farm_location', 'seed_variety_claimed', 'input_notes'] as $field) {
                $table->string($field)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('farmers_cooperatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        (require database_path('migrations/2026_08_20_000200_create_agricultural_machineries_table.php'))->up();
        (require database_path('migrations/2026_09_19_000100_create_harvest_records_table.php'))->up();
        Schema::table('harvest_records', function (Blueprint $table) {
            $table->foreignId('rice_seed_distribution_id')->nullable()->constrained('rice_seed_distributions');
        });
        (require database_path('migrations/2026_09_20_000100_create_farmer_portal_accounts_table.php'))->up();
    }
}

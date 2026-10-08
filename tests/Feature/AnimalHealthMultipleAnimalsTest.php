<?php

namespace Tests\Feature;

use App\Models\AntiRabiesVaccination;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OperationsViewFixtures;
use Tests\Support\PresentationProvinceSchema;
use Tests\TestCase;

class AnimalHealthMultipleAnimalsTest extends TestCase
{
    use PresentationProvinceSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'session.driver' => 'array', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->createPresentationScope();
        Schema::create('anti_rabies_vaccinations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            foreach (['owner_name', 'barangay', 'pet_breed', 'pet_name', 'administered_by', 'dosage'] as $field) {
                $table->string($field, 120)->nullable();
            }
            $table->string('pet_type', 60);
            $table->string('pet_color', 80)->nullable();
            $table->string('service_type', 30);
            $table->string('service_name', 150);
            $table->unsignedInteger('animal_count');
            $table->string('administration_route', 60)->nullable();
            $table->string('diagnosis')->nullable();
            $table->text('treatment_notes')->nullable();
            $table->date('birthday')->nullable();
            $table->date('vaccination_date');
            $table->unsignedSmallInteger('vaccination_year')->nullable();
            $table->date('next_service_date')->nullable();
            $table->timestamps();
        });
        DB::table('municipalities')->insert(['id' => 2, 'name' => 'Other town', 'province' => 'Tarlac', 'province_id' => 1, 'code' => 'OTHER', 'is_active' => true]);
        DB::table('provinces')->insert(['id' => 2, 'name' => 'Other province', 'is_active' => true]);
        DB::table('municipalities')->insert(['id' => 3, 'name' => 'Outside province', 'province' => 'Other province', 'province_id' => 2, 'code' => 'OUTSIDE', 'is_active' => true]);
        $this->actingAs(OperationsViewFixtures::user());
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            foreach (['actor_name', 'actor_email', 'actor_role', 'event', 'module', 'auditable_type', 'auditable_id', 'description', 'ip_address', 'user_agent', 'request_method', 'request_url'] as $field) {
                $table->text($field)->nullable();
            }
            foreach (['old_values', 'new_values', 'metadata'] as $field) {
                $table->json($field)->nullable();
            }
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_two_dogs_and_one_cow_save_as_three_animals_in_two_service_records(): void
    {
        $this->post(route('anti-rabies-vaccinations.store'), $this->payload())->assertRedirect(route('anti-rabies-vaccinations.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('anti_rabies_vaccinations', 2);
        $this->assertDatabaseHas('anti_rabies_vaccinations', ['municipality_id' => 1, 'owner_name' => 'Sample Owner', 'pet_type' => 'Dog', 'animal_count' => 2, 'service_name' => 'Dog vaccine']);
        $this->assertDatabaseHas('anti_rabies_vaccinations', ['municipality_id' => 1, 'pet_type' => 'Cattle', 'animal_count' => 1, 'service_type' => 'vitamins', 'service_name' => 'Recorded cow supplement']);
        $this->assertSame(3, (int) AntiRabiesVaccination::sum('animal_count'));
        $this->assertSame(2, DB::table('audit_logs')->where('event', 'created')->where('auditable_type', AntiRabiesVaccination::class)->count());
    }

    public function test_one_invalid_row_saves_nothing_and_preserves_entered_rows(): void
    {
        $payload = $this->payload();
        $payload['animals'][1]['service_name'] = '';
        $this->from(route('anti-rabies-vaccinations.create'))->post(route('anti-rabies-vaccinations.store'), $payload)
            ->assertRedirect(route('anti-rabies-vaccinations.create'))->assertSessionHasErrors('animals.1.service_name')
            ->assertSessionHas('_old_input.animals.0.animal_count', 2);
        $this->assertDatabaseCount('anti_rabies_vaccinations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_later_persistence_failure_rolls_back_earlier_animal(): void
    {
        AntiRabiesVaccination::creating(function ($record): void {
            if ($record->pet_type === 'Cattle') {
                throw new \RuntimeException('Synthetic second row failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post(route('anti-rabies-vaccinations.store'), $this->payload());
            $this->fail('The synthetic persistence failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic second row failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('anti_rabies_vaccinations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unknown_species_negative_count_and_follow_up_before_own_date_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['animals'][0]['pet_type'] = 'Unlisted';
        $payload['animals'][0]['animal_count'] = 0;
        $payload['animals'][1]['next_service_date'] = '2026-01-01';
        $this->post(route('anti-rabies-vaccinations.store'), $payload)->assertSessionHasErrors(['animals.0.pet_type', 'animals.0.animal_count', 'animals.1.next_service_date']);
        $this->assertDatabaseCount('anti_rabies_vaccinations', 0);
    }

    public function test_empty_and_over_twenty_rows_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['animals'] = [];
        $this->post(route('anti-rabies-vaccinations.store'), $payload)->assertSessionHasErrors('animals');
        $payload['animals'] = array_fill(0, 21, $this->animal('Dog', 1));
        $this->post(route('anti-rabies-vaccinations.store'), $payload)->assertSessionHasErrors('animals');
        $this->assertDatabaseCount('anti_rabies_vaccinations', 0);
    }

    public function test_nested_ownership_and_unlisted_fields_cannot_be_injected(): void
    {
        $payload = $this->payload();
        $payload['animals'][0]['municipality_id'] = 2;
        $this->post(route('anti-rabies-vaccinations.store'), $payload)->assertSessionHasErrors('animals.0');
        $this->assertDatabaseCount('anti_rabies_vaccinations', 0);
    }

    public function test_municipal_staff_cannot_write_the_batch_to_another_town(): void
    {
        $this->post(route('anti-rabies-vaccinations.store'), $this->payload() + ['municipality_id' => 2])->assertSessionHasErrors('municipality_id');
        $this->assertDatabaseCount('anti_rabies_vaccinations', 0);
    }

    public function test_read_only_super_admin_and_guest_cannot_submit_batch(): void
    {
        $this->actingAs(OperationsViewFixtures::user(User::ROLE_SUPER_ADMIN));
        $this->post(route('anti-rabies-vaccinations.store'), $this->payload())->assertForbidden();
        auth()->logout();
        $this->post(route('anti-rabies-vaccinations.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('anti_rabies_vaccinations', 0);
    }

    public function test_provincial_vet_can_choose_only_an_authorized_municipality(): void
    {
        $this->actingAs(OperationsViewFixtures::user(User::ROLE_PROVINCIAL_VET));
        $this->post(route('anti-rabies-vaccinations.store'), $this->payload() + ['municipality_id' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('anti_rabies_vaccinations', ['municipality_id' => 2, 'pet_type' => 'Cattle']);
        $this->post(route('anti-rabies-vaccinations.store'), $this->payload() + ['municipality_id' => 3])->assertSessionHasErrors('municipality_id');
        $this->assertDatabaseCount('anti_rabies_vaccinations', 2);
    }

    public function test_legacy_flat_submission_and_individual_versioned_edit_remain_supported(): void
    {
        $data = ['owner_name' => 'Legacy Owner', 'barangay' => 'Sample Barangay', 'pet_type' => 'Dog', 'vaccination_date' => '2026-02-02'];
        $this->post(route('anti-rabies-vaccinations.store'), $data)->assertSessionHasNoErrors();
        $record = AntiRabiesVaccination::firstOrFail();
        $this->assertSame('Anti-rabies vaccine', $record->service_name);
        $this->assertSame(1, $record->animal_count);
        $this->put(route('anti-rabies-vaccinations.update', $record), $data + ['animal_count' => 2, '_record_version' => ConcurrentWrite::version($record)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('anti_rabies_vaccinations', ['id' => $record->id, 'animal_count' => 2]);
    }

    public function test_lookup_exposes_same_owner_species_and_counts_only_in_selected_scope(): void
    {
        $this->post(route('anti-rabies-vaccinations.store'), $this->payload())->assertSessionHasNoErrors();
        AntiRabiesVaccination::create($this->animal('Cat', 8) + ['municipality_id' => 2, 'owner_name' => 'Sample Owner', 'barangay' => 'Other Barangay']);
        $response = $this->getJson(route('anti-rabies-vaccinations.owner-lookup', ['name' => 'Sample Owner']));
        $response->assertOk()->assertJsonPath('pets.0.animal_count', 1)->assertJsonPath('pets.1.animal_count', 2);
        $this->assertCount(2, $response->json('pets'));
        $this->getJson(route('anti-rabies-vaccinations.owner-lookup', ['name' => 'Sample Owner', 'municipality_id' => 2]))->assertUnprocessable();
    }

    public function test_lookup_is_bounded_and_marks_partial_owner_history(): void
    {
        $records = [];
        for ($i = 0; $i < 201; $i++) {
            $records[] = $this->animal('Dog', 2) + ['municipality_id' => 1, 'owner_name' => 'Sample Owner', 'barangay' => 'Sample Barangay', 'pet_name' => 'Dog '.$i];
        }
        DB::table('anti_rabies_vaccinations')->insert($records);
        $response = $this->getJson(route('anti-rabies-vaccinations.owner-lookup', ['name' => 'Sample Owner']));
        $response->assertOk()->assertJsonPath('has_more', true)->assertJsonPath('pets.0.pet_name', 'Dog 200');
        $this->assertCount(200, $response->json('pets'));
    }

    public function test_create_form_and_validation_recovery_keep_independent_animal_fields(): void
    {
        $this->get(route('anti-rabies-vaccinations.create'))->assertOk()
            ->assertSee('name="animals[0][pet_type]"', false)->assertSee('id="animalRowTemplate"', false);
        $payload = $this->payload();
        $payload['animals'][1]['next_service_date'] = '2026-01-01';
        $this->from(route('anti-rabies-vaccinations.create'))->post(route('anti-rabies-vaccinations.store'), $payload)->assertSessionHasErrors('animals.1.next_service_date');
        $response = $this->get(route('anti-rabies-vaccinations.create'))->assertOk()
            ->assertSee('name="animals[1][pet_type]"', false)->assertSee('Recorded cow supplement');
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//input[@name="owner_name"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="animals[1][next_service_date]"][@aria-invalid="true"]/ancestor::details[@open]')->length);
    }

    private function payload(): array
    {
        return ['owner_name' => 'Sample Owner', 'barangay' => 'Sample Barangay', 'animals' => [
            $this->animal('Dog', 2),
            array_replace($this->animal('Cattle', 1), ['service_type' => 'vitamins', 'service_name' => 'Recorded cow supplement']),
        ]];
    }

    private function animal(string $species, int $count): array
    {
        return ['pet_type' => $species, 'animal_count' => $count, 'service_type' => 'vaccination', 'service_name' => 'Dog vaccine', 'vaccination_date' => '2026-02-02'];
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Modules\Assistant\Infrastructure\OfficeHelpAgent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

class AssistantRecordsTest extends TestCase
{
    use ProvinceScopeSchema;

    private User $staff;

    private Municipality $own;

    private Municipality $other;

    private Farmer $farmer;

    private Farmer $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 04:00:00', 'UTC'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'assistant.enabled' => true, 'ai.providers.openai.key' => '']);
        DB::purge('sqlite');
        $this->createSchema();
        (require database_path('migrations/2026_09_21_000100_add_region_supervision.php'))->up();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('visitor_mode')->default(false));
        Schema::table('farmers', function (Blueprint $t) {
            $t->decimal('farm_area_ha', 15, 4)->nullable();
            $t->string('contact_number')->nullable();
            $t->date('date_of_birth')->nullable();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->unsignedBigInteger('farmer_id')->nullable();
            $t->string('input_category')->nullable();
            $t->string('quantity_unit')->nullable();
            $t->decimal('kgs_received', 15, 3)->nullable();
            $t->date('date_received')->nullable();
        });
        Schema::create('harvest_records', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->unsignedBigInteger('farmer_id')->nullable();
            $t->integer('harvest_year')->nullable();
            $t->string('commodity')->nullable();
            $t->string('quantity_unit')->nullable();
            $t->decimal('quantity', 15, 3)->nullable();
        });
        Schema::create('parcel_crop_seasons', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->unsignedBigInteger('farm_plot_id');
            $t->integer('crop_year');
            $t->string('crop');
        });
        Schema::create('anti_rabies_vaccinations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->integer('vaccination_year')->nullable();
            $t->integer('animal_count')->nullable();
        });
        $region = Region::create(['name' => 'Synthetic region', 'code' => 'sample', 'is_active' => true]);
        $foreignRegion = Region::create(['name' => 'Foreign region', 'code' => 'foreign', 'is_active' => true]);
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Own province', 'region_id' => $region->id, 'is_active' => true]));
        $otherProvince = Province::withoutEvents(fn () => Province::create(['name' => 'Foreign province', 'region_id' => $foreignRegion->id, 'is_active' => true]));
        $this->own = $this->municipality($province, 'Own municipality');
        $this->other = $this->municipality($otherProvince, 'Foreign municipality');
        $this->staff = $this->account(User::ROLE_MUNICIPAL_STAFF);
        $this->farmer = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->own->id, 'first_name' => 'Juan', 'last_name' => 'Fixture', 'farm_area_ha' => 1.5, 'contact_number' => 'private-contact-fixture', 'date_of_birth' => '1954-02-13']));
        $this->foreign = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->other->id, 'first_name' => 'Juan', 'last_name' => 'ForeignSecret']));
        DB::table('farm_plots')->insert(['id' => 1, 'farmer_id' => $this->farmer->id, 'name' => 'Secret plot name', 'polygon_json' => '[]', 'area_ha' => 0.75, 'centroid_lat' => 15.5555555]);
        DB::table('parcel_crop_seasons')->insert(['municipality_id' => $this->own->id, 'farm_plot_id' => 1, 'crop_year' => 2026, 'crop' => 'corn']);
        Http::preventStrayRequests();
        OfficeHelpAgent::fake()->preventStrayPrompts();
    }

    public function test_municipal_summary_is_scoped_and_does_not_use_openai_or_allowance(): void
    {
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Summarize my farmers'))
            ->assertOk()->assertJsonPath('source', 'records')->assertSee('Registered farmers: 1')->assertSee('Saved parcels: 1')->assertSee('0.7500 ha')->assertDontSee('ForeignSecret');
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
    }

    public function test_province_region_and_owner_follow_existing_scope(): void
    {
        $province = Province::findOrFail($this->own->province_id);
        $city = Province::withoutEvents(fn () => Province::create(['name' => 'Separate city scope', 'region_id' => $province->region_id, 'is_active' => true]));
        $cityMunicipality = $this->municipality($city, 'Independent city');
        Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $cityMunicipality->id, 'first_name' => 'City']));
        foreach ([[User::ROLE_SUPER_ADMIN, 1], [User::ROLE_REGIONAL_HEAD, 2], [User::ROLE_SYSTEM_OWNER, 3]] as [$role, $count]) {
            $this->actingAs($this->account($role))->postJson(route('assistant.message'), $this->payload('Summarize my farmers'))->assertOk()->assertSee('Registered farmers: '.$count);
        }
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_farmer_search_and_summary_never_expose_contacts_birth_dates_tokens_or_geometry(): void
    {
        $this->release(['kgs_received' => 15, 'quantity_unit' => 'kg']);
        $this->harvest(['quantity' => 200, 'quantity_unit' => 'kg']);
        $search = $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Find farmer Juan'))->assertOk()->assertJsonCount(1, 'actions')->assertDontSee('ForeignSecret');
        $this->assertIsString($search->json('actions.0.choice'));
        $this->assertArrayNotHasKey('farmer_id', $search->json('actions.0'));
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $response = $this->postJson(route('assistant.message'), $this->payload('Show farmer summary', ['choice' => $search->json('actions.0.choice')]))
            ->assertOk()->assertSee('Juan Fixture')->assertSee('Corn')->assertSee('15.000 kg')->assertSee('200.000')->assertSee('1.50 ha')
            ->assertDontSee('private-contact-fixture')->assertDontSee('1954-02-13')->assertDontSee('15.5555555')->assertDontSee('Secret plot name');
        $this->assertLessThan(6000, mb_strlen($response->json('answer')));
        $audit = AuditLog::where('module', 'Office Assistant')->get()->toJson();
        $this->assertStringNotContainsString('Juan', $audit);
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_selected_farmer_ignores_related_records_linked_from_another_municipality(): void
    {
        $this->release(['municipality_id' => $this->other->id, 'farmer_id' => $this->farmer->id, 'kgs_received' => 7777]);
        $this->harvest(['municipality_id' => $this->other->id, 'farmer_id' => $this->farmer->id, 'quantity' => 8888]);
        DB::table('parcel_crop_seasons')->insert(['municipality_id' => $this->other->id, 'farm_plot_id' => 1, 'crop_year' => 2026, 'crop' => 'rice']);
        $this->actingAs($this->account(User::ROLE_SYSTEM_OWNER))->postJson(route('assistant.message'), $this->payload('Find farmer '.$this->farmer->agri_gov_id))
            ->assertOk()->assertSee('Corn')->assertSee('Registry office: Own municipality')->assertDontSee('Rice / Palay')->assertDontSee('7777')->assertDontSee('8888');
    }

    public function test_farmer_id_search_is_local_and_foreign_or_missing_ids_have_identical_failures(): void
    {
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Find farmer '.$this->farmer->agri_gov_id))->assertOk()->assertSee('Juan Fixture');
        $foreign = $this->postJson(route('assistant.message'), $this->payload('Find farmer '.$this->foreign->agri_gov_id))->assertNotFound();
        $missing = $this->postJson(route('assistant.message'), $this->payload('Find farmer AGRI-F-987654'))->assertNotFound();
        $this->assertSame($foreign->json('message'), $missing->json('message'));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_search_is_literal_bounded_and_does_not_enumerate_wildcards(): void
    {
        for ($i = 0; $i < 8; $i++) {
            Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->own->id, 'first_name' => 'Common', 'last_name' => 'Person'.$i]));
        }
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Find farmer Common'))->assertOk()->assertJsonCount(6, 'actions')->assertSee('first six matches');
        $this->postJson(route('assistant.message'), $this->payload('Find farmer %%'))->assertOk()->assertJsonCount(0, 'actions');
        $this->postJson(route('assistant.message'), $this->payload('Find farmer ForeignSecret'))->assertOk()->assertJsonCount(0, 'actions');
    }

    public function test_assistance_keeps_units_categories_dates_and_invalid_links_separate(): void
    {
        $this->release(['kgs_received' => 15, 'quantity_unit' => 'kg']);
        $this->release(['kgs_received' => 2, 'quantity_unit' => 'sack']);
        $this->release(['kgs_received' => 100, 'quantity_unit' => 'piece', 'input_category' => 'fish_fingerlings']);
        $this->release(['kgs_received' => 3, 'quantity_unit' => null]);
        $this->release(['kgs_received' => null, 'quantity_unit' => 'kg']);
        $this->release(['kgs_received' => 9000, 'date_received' => null]);
        $this->release(['kgs_received' => 8000, 'date_received' => '2025-01-01']);
        $this->release(['kgs_received' => 1, 'farmer_id' => $this->foreign->id]);
        $this->release(['kgs_received' => 7000, 'municipality_id' => $this->other->id, 'farmer_id' => $this->foreign->id]);
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Summarize assistance'))
            ->assertOk()->assertSee('Release records: 6')->assertSee('19.000 kg')->assertSee('2.000 sacks')->assertSee('100.000 pieces')
            ->assertSee('Unique linked beneficiaries: 1')->assertSee('farmer link: 1')->assertSee('needing review: 1')->assertSee('excluded from this year: 1')
            ->assertDontSee('7000')->assertDontSee('8000')->assertDontSee('9000');
    }

    public function test_harvests_use_stated_year_and_do_not_combine_units_or_missing_quantities(): void
    {
        $this->harvest(['quantity' => 200, 'quantity_unit' => 'kg']);
        $this->harvest(['quantity' => 2, 'quantity_unit' => 'sack']);
        $this->harvest(['quantity' => null]);
        $this->harvest(['quantity' => 9000, 'harvest_year' => null]);
        $this->harvest(['quantity' => 8000, 'harvest_year' => 2025]);
        $this->harvest(['quantity' => 7000, 'municipality_id' => $this->other->id]);
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Summarize harvests'))->assertOk()
            ->assertSee('Harvest records: 3')->assertSee('200.000')->assertSee('2.000 Sacks')->assertSee('classification: 1')->assertSee('year excluded: 1')
            ->assertDontSee('7000')->assertDontSee('8000')->assertDontSee('9000');
    }

    public function test_data_quality_review_is_local_scoped_and_links_existing_queues(): void
    {
        Farmer::withoutEvents(fn () => Farmer::create([
            'municipality_id' => $this->other->id,
            'first_name' => 'Foreign',
            'last_name' => 'Incomplete',
        ]));

        $response = $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('What needs attention in our registry?'))
            ->assertOk()
            ->assertJsonPath('source', 'records')
            ->assertJsonPath('report', 'data-quality')
            ->assertSee('Registered farmer profiles: 1')
            ->assertSee('Missing FFRS reference: 1')
            ->assertSee('Missing farm location: 1')
            ->assertSee('Farmers without saved parcels: 0')
            ->assertSee('Saved plots without boundary geometry: 1')
            ->assertSee('These are review queues, not fraud findings.')
            ->assertJsonPath('links.0.label', 'Review missing FFRS')
            ->assertJsonPath('links.1.label', 'Review missing locations');

        $this->assertCount(3, $response->json('links'));
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_data_quality_review_does_not_accept_historical_years(): void
    {
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Show data quality for 2025'))
            ->assertOk()
            ->assertJsonPath('source', 'clarification')
            ->assertSee('current registry records')
            ->assertSee('not a historical reporting year');

        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_ambiguous_attention_question_does_not_trigger_a_registry_report(): void
    {
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('What needs attention?'))
            ->assertOk()
            ->assertJsonPath('source', 'clarification')
            ->assertDontSee('Registered farmer profiles:');

        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_report_year_from_question_is_explicit_and_multiple_years_need_clarification(): void
    {
        $this->harvest(['quantity' => 150, 'harvest_year' => 2025]);
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Summarize harvests for 2025'))->assertOk()->assertJsonPath('year', 2025)->assertSee('150.000');
        $this->postJson(route('assistant.message'), $this->payload('Compare harvests 2025 and 2026'))->assertOk()->assertSee('Choose one reporting year');
    }

    public function test_legacy_modes_years_identifiers_and_transcripts_are_rejected(): void
    {
        $this->actingAs($this->staff);
        foreach ([['mode' => 'arbitrary'], ['year' => 2101], ['history' => [['role' => 'assistant', 'content' => 'Private farmer data']]], ['farmer_id' => -1]] as $invalid) {
            $this->postJson(route('assistant.message'), $this->payload('Summary', $invalid))->assertUnprocessable();
        }
        $this->postJson(route('assistant.message'), ['mode' => 'help', 'message' => 'How do I import seeds?', 'module' => 'farmers'])->assertUnprocessable();
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_veterinary_reports_are_animal_only_and_use_service_instances(): void
    {
        DB::table('anti_rabies_vaccinations')->insert([
            ['municipality_id' => $this->own->id, 'vaccination_year' => 2026, 'animal_count' => 3],
            ['municipality_id' => $this->own->id, 'vaccination_year' => 2026, 'animal_count' => null],
            ['municipality_id' => $this->other->id, 'vaccination_year' => 2026, 'animal_count' => 99],
        ]);
        $this->actingAs($this->account(User::ROLE_PROVINCIAL_VET))->postJson(route('assistant.message'), $this->payload('Workspace summary'))->assertOk()->assertSee('Service records: 2')->assertSee('instances: 4');
        DB::enableQueryLog();
        $this->postJson(route('assistant.message'), $this->payload('Summarize my farmers'))->assertOk()->assertJsonPath('source', 'clarification');
        $this->assertEmpty(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($sql) => str_contains($sql, 'from "farmers"')));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_visitors_inactive_users_and_disabled_feature_cannot_read_records(): void
    {
        $visitor = $this->account(User::ROLE_SUPER_ADMIN);
        $visitor->forceFill(['visitor_mode' => true, 'province_id' => null])->save();
        $this->actingAs($visitor)->postJson(route('assistant.message'), $this->payload('Summary'))->assertForbidden();
        $this->staff->update(['is_active' => false]);
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Summary'))->assertForbidden();
        $this->staff->update(['is_active' => true]);
        config(['assistant.enabled' => false]);
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Summary'))->assertStatus(503);
    }

    public function test_missing_module_schema_has_a_safe_failure(): void
    {
        Schema::drop('harvest_records');
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $this->payload('Summarize harvests'))->assertStatus(503)->assertDontSee('SQLSTATE')->assertDontSee('select');
    }

    private function payload(string $message, array $extra = []): array
    {
        return array_replace(['message' => $message, 'module' => 'farmers'], $extra);
    }

    private function account(string $role): User
    {
        return User::withoutEvents(fn () => User::create(['name' => 'Synthetic officer', 'email' => bin2hex(random_bytes(5)).'@example.test', 'password' => 'non-login-fixture', 'is_active' => true, 'role' => $role,
            'municipality_id' => $role === User::ROLE_MUNICIPAL_STAFF ? $this->own->id : null,
            'province_id' => in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_PROVINCIAL_VET], true) ? $this->own->province_id : null,
            'region_id' => $role === User::ROLE_REGIONAL_HEAD ? Province::findOrFail($this->own->province_id)->region_id : null]));
    }

    private function municipality(Province $province, string $name): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::create(['name' => $name, 'province' => $province->name, 'province_id' => $province->id, 'is_active' => true]));
    }

    private function release(array $extra): void
    {
        DB::table('rice_seed_distributions')->insert(array_replace(['municipality_id' => $this->own->id, 'farmer_id' => $this->farmer->id, 'input_category' => 'rice_seed', 'quantity_unit' => 'kg', 'kgs_received' => 1, 'date_received' => '2026-01-10'], $extra));
    }

    private function harvest(array $extra): void
    {
        DB::table('harvest_records')->insert(array_replace(['municipality_id' => $this->own->id, 'farmer_id' => $this->farmer->id, 'commodity' => 'corn', 'quantity_unit' => 'kg', 'quantity' => 1, 'harvest_year' => 2026], $extra));
    }
}

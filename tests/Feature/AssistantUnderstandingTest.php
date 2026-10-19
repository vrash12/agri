<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Modules\Assistant\Application\AssistantConversationContext;
use App\Modules\Assistant\Application\AssistantKnowledge;
use App\Modules\Assistant\Application\OfficeAssistant;
use App\Modules\Assistant\Infrastructure\OfficeHelpAgent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

/** Natural wording must retain the real endpoint's disclosure and scope gates. */
final class AssistantUnderstandingTest extends TestCase
{
    use ProvinceScopeSchema;

    private User $staff;

    private Municipality $own;

    private Farmer $farmer;

    private Farmer $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 04:00:00', 'UTC'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'assistant.enabled' => true,
            'assistant.daily_requests' => 100, 'assistant.monthly_requests' => 1000, 'assistant.user_daily_requests' => 20,
            'ai.providers.openai.key' => 'configured-for-fakes-only', 'ai.providers.openai.store' => false,
            'ai.providers.openai.url' => 'https://api.openai.com/v1']);
        DB::purge('sqlite');
        $this->createSchema();
        (require database_path('migrations/2026_09_21_000100_add_region_supervision.php'))->up();
        Schema::table('users', fn (Blueprint $table) => $table->boolean('visitor_mode')->default(false));
        Schema::table('farmers', function (Blueprint $table) {
            $table->decimal('farm_area_ha', 15, 4)->nullable();
            $table->string('contact_number')->nullable();
            $table->date('date_of_birth')->nullable();
        });
        $region = Region::create(['name' => 'Understanding region', 'code' => 'understanding', 'is_active' => true]);
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Own province', 'region_id' => $region->id, 'is_active' => true]));
        $foreignProvince = Province::withoutEvents(fn () => Province::create(['name' => 'Foreign province', 'region_id' => $region->id, 'is_active' => true]));
        $this->own = $this->municipality($province, 'Own municipality');
        $other = $this->municipality($foreignProvince, 'Foreign municipality');
        $this->staff = $this->account(User::ROLE_MUNICIPAL_STAFF);
        $this->farmer = $this->farmer($this->own, 'Juan', 'Fixture');
        $this->foreign = $this->farmer($other, 'Juan', 'ForeignSecret');
        $this->farmer($this->own, 'Kiko', 'Fixture');
        $this->farmer($this->own, 'May', 'English');
        Http::preventStrayRequests();
        OfficeHelpAgent::fake(fn (string $question) => $this->structuredHelp($question))->preventStrayPrompts();
        $this->actingAs($this->staff)->withSession(['understanding-fixture' => 'synthetic']);
    }

    #[DataProvider('farmerLookups')]
    public function test_polite_and_person_questions_return_scoped_opaque_farmer_choices(string $question, string $expectedName): void
    {
        $response = $this->ask($question)->assertOk()->assertJsonPath('source', 'records')
            ->assertJsonPath('report', 'farmer-search')->assertJsonCount(1, 'actions')
            ->assertSee($expectedName)->assertDontSee('ForeignSecret')->assertDontSee('private-contact-understanding');
        $choice = $response->json('actions.0.choice');
        $this->assertIsString($choice);
        $this->assertArrayNotHasKey('farmer_id', $response->json('actions.0'));
        $references = app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $choice, 'choice');
        $this->assertSame('farmer', $references['task']);
        $this->assertSame($this->own->id, Farmer::findOrFail($references['farmer_id'])->municipality_id);
        $this->assertArrayNotHasKey('search', $references);
        $this->assertArrayNotHasKey('message', $references);
        $this->assertLocalOnly();
    }

    public static function farmerLookups(): array
    {
        return [
            ['Please find farmer Juan Fixture', 'Juan Fixture'],
            ['Could you find farmer Juan Fixture?', 'Juan Fixture'],
            ['Paki hanapin ang magsasaka Juan Fixture', 'Juan Fixture'],
            ['Who is Juan Fixture?', 'Juan Fixture'],
            ['Sino si Juan Fixture?', 'Juan Fixture'],
            ['Ka Berto, who is Juan Fixture?', 'Juan Fixture'],
            ['Find farmer Kiko', 'Kiko Fixture'],
            ['Find farmer May English', 'May English'],
        ];
    }

    public function test_person_questions_cannot_find_or_directly_open_a_foreign_farmer(): void
    {
        $this->ask('Who is Juan ForeignSecret?')->assertOk()->assertJsonPath('report', 'farmer-search')
            ->assertJsonCount(0, 'actions')->assertSee('No matching farmer')->assertDontSee('ForeignSecret');
        $foreign = $this->ask('Please find farmer '.$this->foreign->agri_gov_id)->assertNotFound();
        $missing = $this->ask('Please find farmer AGRI-F-999999')->assertNotFound();
        $this->assertSame($foreign->json('message'), $missing->json('message'));
        $this->assertLocalOnly();
    }

    public function test_lookup_choice_reauthorizes_after_the_farmer_moves_outside_the_account_area(): void
    {
        $search = $this->ask('Who is Juan Fixture?')->assertOk()->assertJsonCount(1, 'actions');
        Farmer::withoutEvents(fn () => $this->farmer->update(['municipality_id' => $this->foreign->municipality_id]));
        $this->ask('Show the selected farmer', ['choice' => $search->json('actions.0.choice')])
            ->assertNotFound()->assertDontSee('private-contact-understanding');
        $this->assertLocalOnly();
    }

    #[DataProvider('unsafeRequests')]
    public function test_ambiguous_lookups_and_polite_mutations_never_fall_through_to_whole_office_reports(string $question): void
    {
        $context = app(AssistantConversationContext::class)->issue($this->staff, session()->getId(), ['task' => 'farmers', 'year' => 2025]);
        DB::enableQueryLog();
        $this->ask($question, ['context' => $context])->assertOk()->assertJsonPath('source', 'clarification')
            ->assertJsonPath('context', null)->assertDontSee('Registered farmers:')->assertDontSee('private-contact-understanding');
        $this->assertSame(0, $this->operationalQueries());
        $this->assertLocalOnly();
    }

    public static function unsafeRequests(): array
    {
        return [
            ['Who is Juan Fixture and show farmers?'], ['Who is Juan Fixture in Foreign municipality?'],
            ['Who is Juan Fixture address?'], ['Could you delete farmer Juan Fixture?'], ['Find farmer %'],
        ];
    }

    #[DataProvider('namedGuideQuestions')]
    public function test_named_guide_identity_describes_the_subject_without_switching_the_selected_appearance(string $question, string $name, string $selected, string $selectedName): void
    {
        DB::enableQueryLog();
        $named = $this->ask($question, ['guide' => $selected])->assertOk()->assertJsonPath('source', 'conversation')
            ->assertJsonPath('report', 'identity')->assertSee($name)->assertSee('OpenAI');
        $this->assertStringStartsWith(str_starts_with($question, 'Sino') ? 'Si '.$name : $name.' is', $named->json('answer'));
        $self = $this->ask('Who are you?', ['guide' => $selected])->assertOk();
        $this->assertStringContainsString('I’m '.$selectedName, $self->json('answer'));
        $this->assertSame(0, $this->operationalQueries());
        $this->assertLocalOnly();
    }

    public static function namedGuideQuestions(): array
    {
        return [
            ['Who is Ka Berto?', 'Ka Berto', 'kiko', 'Kiko'],
            ['Sino si Ka Kaloy?', 'Ka Kaloy', 'aling-maya', 'Aling Maya'],
            ['Who is Aling Maya?', 'Aling Maya', 'ka-kaloy', 'Ka Kaloy'],
            ['Who is Kiko?', 'Kiko', 'ka-berto', 'Ka Berto'],
        ];
    }

    public function test_named_guide_interlude_preserves_approved_help_for_a_natural_follow_up(): void
    {
        $help = $this->ask('What about SAVI?', ['module' => 'satellite'])->assertOk()->assertJsonPath('report', 'savi');
        $named = $this->ask('Who is Kiko?', ['guide' => 'ka-berto', 'context' => $help->json('context')])
            ->assertOk()->assertJsonPath('source', 'conversation')->assertJsonPath('context', $help->json('context'));
        $this->assertStringStartsWith('Kiko is', $named->json('answer'));
        $follow = $this->ask('Can you make it simpler?', ['context' => $named->json('context')])
            ->assertOk()->assertJsonPath('source', 'help')->assertJsonPath('report', 'savi');
        $references = app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $follow->json('context'));
        $this->assertSame('savi', $references['help_topic']);
        $this->assertSame('plain', $references['help_style']);
        OfficeHelpAgent::assertPromptedTimes(1);
        OfficeHelpAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === OfficeAssistant::TOPICS['savi']['question'] && empty($prompt->messages));
        Http::assertNothingSent();
    }

    public function test_veterinary_named_guide_description_remains_local_and_animal_health_scoped(): void
    {
        $this->actingAs($this->account(User::ROLE_PROVINCIAL_VET));
        DB::enableQueryLog();
        $this->ask('Who is Aling Maya?', ['guide' => 'kiko'])->assertOk()->assertJsonPath('source', 'conversation')
            ->assertSee('Aling Maya')->assertSee('limited to Animal Health')->assertDontSee('cooperatives');
        $this->assertSame(0, $this->operationalQueries());
        $this->assertLocalOnly();
    }

    #[DataProvider('restrictedVeterinaryQuestions')]
    public function test_natural_wording_cannot_expand_veterinary_record_or_help_access(string $question): void
    {
        $this->actingAs($this->account(User::ROLE_PROVINCIAL_VET));
        DB::enableQueryLog();
        $this->ask($question)->assertOk()->assertJsonPath('source', 'clarification')->assertSee('Animal Health');
        $this->assertSame(0, $this->operationalQueries());
        $this->assertLocalOnly();
    }

    public static function restrictedVeterinaryQuestions(): array
    {
        return [
            ['Could you find farmer Juan Fixture?'], ['Who is Juan Fixture?'],
            ['What is NDMI?'], ['What about SAVI?'], ['Explain EVI'], ['How do I use El Niño readiness?'],
        ];
    }

    #[DataProvider('canonicalHelpQuestions')]
    public function test_index_and_climate_help_send_only_the_matching_canonical_topic_to_the_fake_provider(string $question, string $topic, string $module): void
    {
        DB::enableQueryLog();
        $response = $this->ask($question, ['module' => $module])->assertOk()->assertJsonPath('source', 'help')
            ->assertJsonPath('report', $topic)->assertJsonPath('delivery', 'ai');
        $references = app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $response->json('context'));
        $this->assertSame($topic, $references['help_topic']);
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt) use ($question, $topic): bool {
            $this->assertSame(OfficeAssistant::TOPICS[$topic]['question'], $prompt->prompt);
            $this->assertEmpty($prompt->messages);
            foreach ([$question, 'Juan Fixture', 'ForeignSecret', 'private-contact-understanding'] as $private) {
                $this->assertStringNotContainsString($private, $prompt->agent->instructions());
            }

            return true;
        });
        OfficeHelpAgent::assertPromptedTimes(1);
        $this->assertSame(0, $this->operationalQueries());
        Http::assertNothingSent();
    }

    public static function canonicalHelpQuestions(): array
    {
        return [
            ['Ka Berto, explain NDMI for Juan Fixture', 'ndmi', 'satellite'],
            ['What about SAVI?', 'savi', 'satellite'], ['Explain EVI', 'evi', 'satellite'],
            ['What is NDMI compared to NDVI?', 'satellite-indices', 'satellite'],
            ['How do I use El Niño readiness?', 'climate-readiness', 'climate-risk'],
            ['Why is climate risk not assessed?', 'climate-readiness', 'climate-risk'],
            ['How do I use this page?', 'climate-readiness', 'climate-risk'],
        ];
    }

    private function structuredHelp(string $question): array
    {
        $topic = array_search($question, array_map(fn (array $entry) => $entry['question'], OfficeAssistant::TOPICS), true);
        $this->assertNotFalse($topic);
        $evidence = app(AssistantKnowledge::class)->retrieve(auth()->user(), $topic, $question);

        return ['answer' => 'Open the documented workspace and follow its authorized steps.', 'supported' => true,
            'citations' => [$evidence['passages'][0]['id']]];
    }

    private function assertLocalOnly(): void
    {
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
    }

    private function operationalQueries(): int
    {
        return count(array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql) => preg_match('/from "(?:farmers|farm_plots|rice_seed_distributions|harvest_records|parcel_crop_seasons|climate_risk_assessments|climate_risk_source_readings)"/i', $sql)));
    }

    private function ask(string $message, array $extra = []): TestResponse
    {
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());

        return $this->postJson(route('assistant.message'), array_replace(['message' => $message, 'module' => 'farmers'], $extra));
    }

    private function municipality(Province $province, string $name): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::create(['name' => $name, 'province' => $province->name,
            'province_id' => $province->id, 'is_active' => true]));
    }

    private function account(string $role): User
    {
        return User::withoutEvents(fn () => User::create(['name' => 'Synthetic understanding officer', 'email' => bin2hex(random_bytes(5)).'@example.test',
            'password' => 'non-login-fixture', 'is_active' => true, 'role' => $role,
            'municipality_id' => $role === User::ROLE_MUNICIPAL_STAFF ? $this->own->id : null,
            'province_id' => $role === User::ROLE_PROVINCIAL_VET ? $this->own->province_id : null]));
    }

    private function farmer(Municipality $municipality, string $first, string $last): Farmer
    {
        return Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $municipality->id, 'first_name' => $first,
            'last_name' => $last, 'farm_area_ha' => 1.5, 'contact_number' => 'private-contact-understanding', 'date_of_birth' => '1954-02-13']));
    }
}

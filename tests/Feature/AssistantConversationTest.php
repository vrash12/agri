<?php

namespace Tests\Feature;

use App\Ai\Agents\OfficeHelpAgent;
use App\Http\Middleware\SynchronizeAssistantRequests;
use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Support\AssistantConversationContext;
use App\Support\AssistantKnowledge;
use App\Support\OfficeAssistant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\EncryptedStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

/** Real office endpoint, isolated schema, normal session and fake-only provider. */
class AssistantConversationTest extends TestCase
{
    use ProvinceScopeSchema;

    private User $staff;

    private Municipality $own;

    private Municipality $other;

    private Farmer $farmer;

    private Farmer $foreign;

    private ?string $sessionDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 04:00:00', 'UTC'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'assistant.enabled' => true,
            'ai.providers.openai.key' => 'configured-for-fakes-only', 'ai.providers.openai.store' => false]);
        DB::purge('sqlite');
        $this->createSchema();
        (require database_path('migrations/2026_09_21_000100_add_region_supervision.php'))->up();
        Schema::table('users', fn (Blueprint $table) => $table->boolean('visitor_mode')->default(false));
        Schema::table('farmers', function (Blueprint $table) {
            $table->decimal('farm_area_ha', 15, 4)->nullable();
            $table->string('contact_number')->nullable();
            $table->date('date_of_birth')->nullable();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->string('input_category')->nullable();
            $table->string('quantity_unit')->nullable();
            $table->decimal('kgs_received', 15, 3)->nullable();
            $table->date('date_received')->nullable();
        });
        Schema::create('harvest_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->integer('harvest_year')->nullable();
            $table->string('commodity')->nullable();
            $table->string('quantity_unit')->nullable();
            $table->decimal('quantity', 15, 3)->nullable();
        });
        Schema::create('parcel_crop_seasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farm_plot_id');
            $table->integer('crop_year');
            $table->string('crop');
        });
        Schema::create('anti_rabies_vaccinations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->integer('vaccination_year')->nullable();
            $table->integer('animal_count')->nullable();
        });
        $region = Region::create(['name' => 'Synthetic region', 'code' => 'own', 'is_active' => true]);
        $foreignRegion = Region::create(['name' => 'Other region', 'code' => 'other', 'is_active' => true]);
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Own province', 'region_id' => $region->id, 'is_active' => true]));
        $foreignProvince = Province::withoutEvents(fn () => Province::create(['name' => 'Foreign province', 'region_id' => $foreignRegion->id, 'is_active' => true]));
        $this->own = $this->municipality($province, 'Own municipality');
        $this->other = $this->municipality($foreignProvince, 'Foreign municipality');
        $this->staff = $this->account();
        $this->farmer = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->own->id, 'first_name' => 'Juan', 'last_name' => 'Fixture',
            'farm_area_ha' => 1.5, 'contact_number' => 'private-contact-fixture', 'date_of_birth' => '1954-02-13']));
        $this->foreign = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->other->id, 'first_name' => 'Juan', 'last_name' => 'ForeignSecret']));
        DB::table('farm_plots')->insert(['id' => 1, 'farmer_id' => $this->farmer->id, 'name' => 'Secret plot name', 'polygon_json' => '[]', 'area_ha' => .75, 'centroid_lat' => 15.5555555]);
        DB::table('parcel_crop_seasons')->insert(['municipality_id' => $this->own->id, 'farm_plot_id' => 1, 'crop_year' => 2026, 'crop' => 'corn']);
        $this->release(15, 2025);
        $this->release(27, 2026);
        $this->harvest(200, 2025);
        $this->harvest(400, 2026);
        Http::preventStrayRequests();
        OfficeHelpAgent::fake(fn (string $prompt) => $this->structuredHelp($prompt))->preventStrayPrompts();
        $this->actingAs($this->staff)->withSession(['conversation-fixture' => 'synthetic']);
    }

    public function test_follow_up_reuses_the_year_without_a_form_or_provider_call(): void
    {
        $first = $this->ask('Show assistance for 2025')->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('year', 2025)->assertSee('15.000 kg')->assertDontSee('27.000');
        $this->assertNotNull(app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $first->json('context')));
        $this->ask('What about harvests for the same year?', ['context' => $first->json('context')])
            ->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('year', 2025)->assertSee('200.000')->assertDontSee('400.000');
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
    }

    #[DataProvider('localIntroductionCases')]
    public function test_introductions_greetings_and_technology_are_local_without_provider_or_record_reads(string $question, string $expected): void
    {
        config(['ai.providers.openai.key' => '']);
        DB::enableQueryLog();
        $response = $this->ask($question)->assertOk()->assertJsonPath('source', 'conversation')->assertJsonPath('report', 'identity')->assertSee($expected, false);
        $this->assertSame(0, $this->operationalQueries());
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
        $this->assertLessThanOrEqual(4096, strlen($response->json('context')));
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
        $audit = AuditLog::where('module', 'Office Assistant')->sole();
        $this->assertSame('conversation', $audit->metadata['source']);
        $this->assertStringNotContainsString($question, json_encode($audit->metadata));
    }

    public static function localIntroductionCases(): array
    {
        return [
            ['Who are you?', 'AgriLGU Assistant'], ['Hello!', 'How can I help with AgriLGU?'],
            ['What can you do?', 'AgriLGU features only'], ['What AI are you using?', 'Laravel AI SDK'],
            ['Are you ChatGPT?', 'OpenAI'], ['Sino ka?', 'Ako ang AgriLGU Assistant'],
            ['Kumusta!', 'Kumusta!'], ['Salamat!', 'Walang anuman!'],
        ];
    }

    public function test_local_technology_does_not_echo_unreviewed_model_configuration(): void
    {
        config(['assistant.model' => 'private-model-fixture@example.test']);
        $this->ask('What model do you use?')->assertOk()->assertJsonPath('source', 'conversation')
            ->assertSee('OpenAI')->assertDontSee('private-model-fixture@example.test');
        OfficeHelpAgent::assertNeverPrompted();
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
    }

    public function test_local_introduction_followup_explains_capabilities_without_provider_context(): void
    {
        $greeting = $this->ask('Hello!')->assertOk()->assertJsonPath('source', 'conversation');
        DB::enableQueryLog();
        $this->ask('Explain more.', ['context' => $greeting->json('context')])->assertOk()->assertJsonPath('source', 'conversation')
            ->assertSee('AgriLGU features only')->assertSee('no live browsing');
        $this->assertSame(0, $this->operationalQueries());
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_thanks_preserves_the_help_topic_without_forwarding_the_interlude(): void
    {
        $guide = $this->ask('How do I draw crops?')->assertOk();
        $thanks = $this->ask('Thanks!', ['context' => $guide->json('context')])->assertOk()->assertJsonPath('source', 'conversation');
        $this->assertSame($guide->json('context'), $thanks->json('context'));
        $this->ask('Give me an example', ['context' => $thanks->json('context')])->assertOk()->assertJsonPath('report', 'crop-drawing');
        OfficeHelpAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === OfficeAssistant::TOPICS['crop-drawing']['question']
            && empty($prompt->messages) && ! str_contains($prompt->agent->instructions(), 'Thanks!'));
        OfficeHelpAgent::assertPromptedTimes(2);
    }

    public function test_greeting_preserves_report_period_and_selection_without_inference(): void
    {
        $report = $this->ask('Show assistance for 2026')->assertOk()->assertJsonPath('year', 2026);
        $greeting = $this->ask('Hello!', ['context' => $report->json('context')])->assertOk()->assertJsonPath('source', 'conversation');
        $this->assertSame($report->json('context'), $greeting->json('context'));
        $this->ask('2025', ['context' => $greeting->json('context')])->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('year', 2025);
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_coverage_and_satellite_pages_render_distinct_safe_page_hints(): void
    {
        foreach (['assistance-coverage.index' => 'coverage', 'farm-plots.satellite.show' => 'satellite'] as $routeName => $module) {
            $request = Request::create('/synthetic-page');
            $route = (new Route('GET', '/synthetic-page', fn () => ''))->name($routeName);
            $request->setRouteResolver(fn () => $route);
            $this->app->instance('request', $request);
            $this->view('partials.office-assistant')->assertSee('data-module="'.$module.'"', false);
        }
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_help_followups_reuse_only_encrypted_topic_and_approved_style(): void
    {
        $response = $this->ask('How do I import rice seeds?')->assertOk()->assertJsonPath('report', 'seed-import');
        $first = app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $response->json('context'));
        $this->assertSame('seed-import', $first['help_topic']);
        $this->assertSame('plain', $first['help_style']);
        foreach (['Make it shorter.' => 'brief', 'Give an example.' => 'example', 'Step by step.' => 'steps', 'Explain more.' => 'more'] as $question => $style) {
            $response = $this->ask($question, ['context' => $response->json('context')])->assertOk()->assertJsonPath('source', 'help')->assertJsonPath('report', 'seed-import');
            $hint = app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $response->json('context'));
            $this->assertSame('seed-import', $hint['help_topic']);
            $this->assertSame($style, $hint['help_style']);
            $plainHint = Crypt::decryptString($response->json('context'));
            $this->assertStringNotContainsString($question, $plainHint);
            $this->assertStringNotContainsString('Open the documented workspace', $plainHint);
        }
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $this->assertSame(OfficeAssistant::TOPICS['seed-import']['question'], $prompt->prompt);
            $this->assertEmpty($prompt->messages);
            foreach (['Make it shorter', 'Give an example', 'Step by step', 'Explain more'] as $raw) {
                $this->assertStringNotContainsString($raw, $prompt->prompt.$prompt->agent->instructions());
            }

            return str_contains($prompt->agent->instructions(), 'documented constraints and reasons in more detail');
        });
        OfficeHelpAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->agent->instructions(), 'at most 70 words'));
        OfficeHelpAgent::assertPromptedTimes(5);
        $audit = AuditLog::where('module', 'Office Assistant')->get()->toJson();
        $this->assertStringNotContainsString('Make it shorter', $audit);
        $this->assertStringNotContainsString('Open the documented workspace', $audit);
    }

    #[DataProvider('pageHelpCases')]
    public function test_this_page_uses_allowlisted_module_relevance_without_reading_records(string $module, string $topic): void
    {
        DB::enableQueryLog();
        $this->ask('How do I use this page?', ['module' => $module])->assertOk()->assertJsonPath('source', 'help')->assertJsonPath('report', $topic);
        $this->assertSame(0, $this->operationalQueries());
        OfficeHelpAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === OfficeAssistant::TOPICS[$topic]['question'] && empty($prompt->messages));
    }

    public static function pageHelpCases(): array
    {
        return [
            ['dashboard', 'dashboard-overview'], ['farmers', 'farmer-registry'], ['crops', 'crop-drawing'],
            ['assistance', 'assistance-management'], ['harvests', 'harvest-entry'], ['animal-health', 'animals'],
            ['satellite', 'ndvi'], ['coverage', 'assistance-coverage'],
            ['cooperatives', 'cooperatives'], ['machinery', 'machinery'], ['accounts', 'access'],
            ['geofences', 'geofences'], ['backups', 'backups'], ['audit', 'audit'], ['other', 'help'],
        ];
    }

    #[DataProvider('expandedHelpCases')]
    public function test_additional_feature_questions_use_reviewed_canonical_guidance(string $question, string $topic): void
    {
        DB::enableQueryLog();
        $this->ask($question)->assertOk()->assertJsonPath('source', 'help')->assertJsonPath('report', $topic);
        $this->assertSame(0, $this->operationalQueries());
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt) use ($question, $topic): bool {
            $this->assertSame(OfficeAssistant::TOPICS[$topic]['question'], $prompt->prompt);
            $this->assertEmpty($prompt->messages);
            $this->assertStringNotContainsString($question, $prompt->agent->instructions());

            return true;
        });
    }

    public static function expandedHelpCases(): array
    {
        return [
            ['How do I read weather guidance?', 'weather'], ['Explain data quality checks.', 'data-quality'],
            ['What is AgriLGU?', 'system-overview'], ['Explain RAG.', 'ai-technology'],
            ['Do you send farmer records to OpenAI?', 'assistant-privacy'],
            ['How do I record fertilizer releases?', 'assistance-management'],
            ['How do I record fingerlings?', 'assistance-management'],
            ['Explain assistance coverage.', 'assistance-coverage'],
        ];
    }

    public function test_unrelated_general_questions_remain_local_without_raw_provider_prompts(): void
    {
        DB::enableQueryLog();
        foreach (['Explain photosynthesis.', 'Write a generic speech about bicycles.'] as $message) {
            $this->ask($message)->assertOk()->assertJsonPath('source', 'clarification');
        }
        $this->assertSame(0, $this->operationalQueries());
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_a_record_summary_cannot_become_provider_history_through_style_followups(): void
    {
        $summary = $this->ask('Show assistance for 2025')->assertOk()->assertJsonPath('source', 'records');
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (['Make it shorter.', 'Explain more.', 'Give an example.'] as $question) {
            $this->ask($question, ['context' => $summary->json('context')])->assertOk()->assertJsonPath('source', 'clarification')
                ->assertDontSee('15.000 kg')->assertDontSee('Juan Fixture');
        }
        $this->assertSame(0, $this->operationalQueries());
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_selected_farmer_survives_help_references_without_entering_provider_or_audit_text(): void
    {
        $selected = $this->ask('Find farmer '.$this->farmer->agri_gov_id)->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $help = $this->ask('How do I draw a crop area for Juan Fixture?', ['context' => $selected->json('context')])->assertOk();
        $follow = $this->ask('Give an example.', ['context' => $help->json('context')])->assertOk()->assertJsonPath('report', 'crop-drawing');
        $hint = app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $follow->json('context'));
        $this->assertSame($this->farmer->id, $hint['farmer_id']);
        $this->assertSame('crop-drawing', $hint['help_topic']);
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $providerText = $prompt->prompt.$prompt->agent->instructions().json_encode($prompt->messages);
            foreach (['Juan', 'Fixture', $this->farmer->agri_gov_id, 'Secret plot name', '15.000', '400.000', 'private-contact-fixture'] as $private) {
                $this->assertStringNotContainsString($private, $providerText);
            }

            return $prompt->prompt === OfficeAssistant::TOPICS['crop-drawing']['question'] && empty($prompt->messages)
                && str_contains($prompt->agent->instructions(), 'clearly hypothetical');
        });
        $this->assertSame(0, $this->operationalQueries());
        $this->ask('Show his parcels.', ['context' => $follow->json('context')])->assertOk()->assertJsonPath('source', 'records')->assertSee('Saved parcels: 1');
        $audit = AuditLog::where('module', 'Office Assistant')->get()->toJson();
        foreach (['Juan', $this->farmer->agri_gov_id, 'Give an example', 'Secret plot name'] as $private) {
            $this->assertStringNotContainsString($private, $audit);
        }
        OfficeHelpAgent::assertPromptedTimes(2);
    }

    public function test_veterinary_identity_and_assistant_docs_preserve_animal_health_boundary(): void
    {
        $vet = User::withoutEvents(fn () => User::create(['name' => 'Synthetic veterinary officer', 'email' => 'synthetic-vet@example.test',
            'password' => 'non-login-fixture', 'is_active' => true, 'role' => User::ROLE_PROVINCIAL_VET, 'province_id' => $this->own->province_id]));
        $this->actingAs($vet);
        DB::enableQueryLog();
        $this->ask('Who are you?')->assertOk()->assertJsonPath('source', 'conversation')->assertSee('limited to Animal Health');
        $this->ask('Do you send farmer data to OpenAI?')->assertOk()->assertJsonPath('source', 'help')->assertJsonPath('report', 'assistant-privacy');
        $this->ask('Explain Laravel AI SDK.')->assertOk()->assertJsonPath('source', 'help')->assertJsonPath('report', 'ai-technology');
        $this->ask('How do I draw a crop area?')->assertOk()->assertJsonPath('source', 'clarification');
        $this->ask('How do I use cooperatives?')->assertOk()->assertJsonPath('source', 'clarification');
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $this->assertStringContainsString('Animal Health only for operational workflows', $prompt->agent->instructions());
            $this->assertStringNotContainsString('"id":"crop-sections"', $prompt->agent->instructions());
            $this->assertStringNotContainsString('"id":"farmer-registry"', $prompt->agent->instructions());

            return in_array($prompt->prompt, [OfficeAssistant::TOPICS['assistant-privacy']['question'], OfficeAssistant::TOPICS['ai-technology']['question']], true)
                && empty($prompt->messages);
        });
        OfficeHelpAgent::assertPromptedTimes(2);
        $this->assertSame(0, $this->operationalQueries());
    }

    public function test_legacy_help_and_report_tokens_remain_readable_without_inventing_help_context(): void
    {
        $contexts = app(AssistantConversationContext::class);
        $legacyHelp = $contexts->issue($this->staff, session()->getId(), ['task' => 'help', 'year' => 2025]);
        $this->assertNotNull($contexts->read($this->staff, session()->getId(), $legacyHelp));
        $this->ask('Make it shorter.', ['context' => $legacyHelp])->assertOk()->assertJsonPath('source', 'clarification');
        $legacyReport = $contexts->issue($this->staff, session()->getId(), ['task' => 'assistance', 'year' => 2025]);
        $this->ask('What about harvests for the same year?', ['context' => $legacyReport])->assertOk()->assertJsonPath('source', 'records')
            ->assertJsonPath('year', 2025)->assertSee('200.000');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_invalid_encrypted_help_topic_or_style_resets_before_provider_or_record_queries(): void
    {
        $token = app(AssistantConversationContext::class)->issue($this->staff, session()->getId(), ['task' => 'help', 'year' => 2026, 'help_topic' => 'crop-drawing', 'help_style' => 'plain']);
        $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        DB::enableQueryLog();
        foreach (['help_topic' => 'private-unreviewed-topic', 'help_style' => 'raw-history'] as $field => $bad) {
            $modified = array_replace($payload, [$field => $bad]);
            $this->ask('Explain more.', ['context' => Crypt::encryptString(json_encode($modified, JSON_THROW_ON_ERROR))])
                ->assertOk()->assertJsonPath('source', 'clarification')->assertJsonPath('context', null);
        }
        $this->assertSame(0, $this->operationalQueries());
        OfficeHelpAgent::assertNeverPrompted();
        $this->assertNull(Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd')));
    }

    public function test_real_office_sign_in_and_csrf_session_can_read_a_scoped_report(): void
    {
        auth()->guard('web')->logout();
        $this->staff->forceFill(['password' => Hash::make('synthetic-login-only')])->save();
        $this->app->instance('env', 'local');
        $csrf = bin2hex(random_bytes(20));
        $this->withSession(['_token' => $csrf])->post(route('login.attempt'), [
            '_token' => $csrf, 'email' => $this->staff->email,
            'password' => 'synthetic-login-only', 'confidentiality_acknowledged' => '1',
        ])->assertRedirect(route('dashboard'))->assertCookie(config('session.cookie'))->assertCookie('XSRF-TOKEN');
        $this->assertAuthenticatedAs($this->staff, 'web');
        $this->withHeader('X-CSRF-TOKEN', session()->token())->ask('Summarize my farmers')
            ->assertOk()->assertJsonPath('source', 'records')->assertSee('Registered farmers: 1')->assertDontSee('ForeignSecret');
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_normal_office_sign_in_still_persists_an_encrypted_file_session(): void
    {
        auth()->guard('web')->logout();
        $this->useFileSession(false);
        $this->staff->forceFill(['password' => Hash::make('synthetic-login-only')])->save();
        $this->app->instance('env', 'local');
        $csrf = session()->token();
        $this->withCookie(config('session.cookie'), session()->getId())->post(route('login.attempt'), [
            '_token' => $csrf, 'email' => $this->staff->email,
            'password' => 'synthetic-login-only', 'confidentiality_acknowledged' => '1',
        ])->assertRedirect(route('dashboard'))->assertCookie(config('session.cookie'))->assertCookie('XSRF-TOKEN');
        $this->assertAuthenticatedAs($this->staff, 'web');
        $this->assertFileExists($this->sessionDirectory.'/'.session()->getId());
        touch($this->sessionDirectory.'/'.session()->getId(), now()->timestamp);
        $persisted = $this->independentSession();
        $persisted->start();
        $this->assertSame($this->staff->id, $persisted->get(auth()->guard('web')->getName()));
        $this->assertNotEmpty($persisted->token());
        $this->assertSame(now()->timestamp, $persisted->get('auth.last_activity_at'));
        $this->assertStringNotContainsString($this->staff->email, file_get_contents($this->sessionDirectory.'/'.session()->getId()));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_farmer_choices_are_opaque_and_follow_ups_distinguish_his_from_our_assistance(): void
    {
        $second = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->own->id, 'first_name' => 'Maria', 'last_name' => 'Fixture']));
        $this->release(42, 2025, $second->id);
        $search = $this->ask('Find farmer Juan for 2025')->assertOk()->assertJsonCount(1, 'actions')->assertDontSee('ForeignSecret');
        $choice = $search->json('actions.0.choice');
        $this->assertIsString($choice);
        $this->assertArrayNotHasKey('farmer_id', $search->json('actions.0'));
        $selected = $this->ask('Show this farmer', ['choice' => $choice])->assertOk()->assertJsonPath('year', 2025)->assertSee('Juan Fixture');
        $individual = $this->ask('Show his parcels and seeds received', ['context' => $selected->json('context')])
            ->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('year', 2025)->assertSee('0.7500 ha')->assertSee('15.000 kg')->assertDontSee('57.000');
        $this->ask('Summarize our assistance for the same year', ['context' => $individual->json('context')])
            ->assertOk()->assertJsonPath('source', 'records')->assertSee('57.000 kg');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_help_after_a_farmer_report_uses_only_the_canonical_provider_question(): void
    {
        $profile = $this->ask('Find farmer '.$this->farmer->agri_gov_id)->assertOk()->assertSee('Juan Fixture');
        $this->ask('How do I draw a corn area for Juan Fixture?', ['context' => $profile->json('context')])
            ->assertOk()->assertJsonPath('source', 'help');
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $this->assertSame(OfficeAssistant::TOPICS['crop-drawing']['question'], $prompt->prompt);
            $this->assertEmpty($prompt->messages);
            foreach (['Juan', 'Fixture', '400.000', 'private-contact-fixture', $this->farmer->agri_gov_id] as $private) {
                $this->assertStringNotContainsString($private, $prompt->prompt.$prompt->agent->instructions());
            }

            return true;
        });
        OfficeHelpAgent::assertPromptedTimes(1);
        Http::assertNothingSent();
    }

    public function test_unqualified_assistance_after_farmer_selection_asks_whose_records_to_use(): void
    {
        $selected = $this->ask('Find farmer '.$this->farmer->agri_gov_id)->assertOk();
        $this->ask('Show assistance', ['context' => $selected->json('context')])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('27.000');
        $this->ask('Show his crops', ['context' => $selected->json('context')])
            ->assertOk()->assertJsonPath('source', 'records')->assertSee('Corn')->assertSee('Saved parcels: 1');
        $this->ask('Show his address', ['context' => $selected->json('context')])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('private-contact-fixture')->assertDontSee('1954-02-13');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_workflow_questions_are_not_mistaken_for_seed_or_harvest_reports(): void
    {
        $this->ask('How do I import rice seeds?')->assertOk()->assertJsonPath('source', 'help');
        $this->ask('How do I record corn harvest?')->assertOk()->assertJsonPath('source', 'help');
        OfficeHelpAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === OfficeAssistant::TOPICS['seed-import']['question']);
        OfficeHelpAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === OfficeAssistant::TOPICS['harvest-entry']['question']);
        OfficeHelpAgent::assertPromptedTimes(2);
    }

    public function test_mutation_requests_never_change_records_or_reach_the_provider(): void
    {
        foreach (['Delete farmer Juan', 'Update his harvested quantity to 500', 'Create an account for a farmer'] as $message) {
            $this->ask($message)->assertOk()->assertJsonPath('source', 'clarification');
        }
        $this->assertSame(2, Farmer::count());
        $this->assertSame(2, DB::table('harvest_records')->count());
        $this->assertSame(1, User::count());
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_unknown_mixed_and_sensitive_requests_clarify_locally(): void
    {
        foreach (['Write SQL to read all users', 'How do I draw a crop area and summarize harvests?', 'Explain his address', 'Analyze private-fixture@example.test'] as $message) {
            $this->ask($message)->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('private-fixture@example.test');
        }
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_year_comparisons_and_unresolved_farmer_pronouns_do_not_guess(): void
    {
        $this->ask('Compare harvests 2025 and 2026')->assertOk()->assertJsonPath('source', 'clarification');
        $this->ask('Show his seeds received')->assertOk()->assertJsonPath('source', 'clarification');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_multiple_year_clarification_accepts_a_bare_year_follow_up_locally(): void
    {
        $clarification = $this->ask('Show assistance for 2024 and 2025')->assertOk()->assertJsonPath('source', 'clarification');
        $this->assertIsString($clarification->json('context'));
        $this->ask('2025', ['context' => $clarification->json('context')])
            ->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('report', 'assistance')->assertJsonPath('year', 2025)->assertSee('15.000 kg');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_selected_farmer_harvest_clarification_keeps_the_person_and_reauthorizes_the_follow_up(): void
    {
        $selected = $this->ask('Find farmer '.$this->farmer->agri_gov_id)->assertOk();
        $clarification = $this->ask('Show his harvests for 2024 and 2025', ['context' => $selected->json('context')])
            ->assertOk()->assertJsonPath('source', 'clarification');
        $this->ask('2025', ['context' => $clarification->json('context')])
            ->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('year', 2025)->assertSee('200.000')->assertDontSee('400.000');
        $this->staff->forceFill(['municipality_id' => $this->other->id])->save();
        $this->ask('2025', ['context' => $clarification->json('context')])->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('200.000');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_veterinary_access_question_uses_only_the_canonical_veterinary_context(): void
    {
        $vet = User::withoutEvents(fn () => User::create(['name' => 'Synthetic veterinary officer', 'email' => 'synthetic-vet@example.test',
            'password' => 'non-login-fixture', 'is_active' => true, 'role' => User::ROLE_PROVINCIAL_VET, 'province_id' => $this->own->province_id]));
        $this->actingAs($vet)->ask('What can my veterinary account access?')->assertOk()->assertJsonPath('source', 'help');
        OfficeHelpAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'veterinary')
            && str_contains($prompt->agent->instructions(), 'Current module: animal-health') && empty($prompt->messages));
    }

    public function test_retired_client_receives_a_top_level_reload_instruction(): void
    {
        $this->ask('Summarize my farmers', ['mode' => 'records', 'year' => 2026, 'history' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['mode', 'year', 'history']);
        $response = $this->ask('Summarize my farmers', ['farmer_id' => $this->farmer->id])->assertUnprocessable();
        $this->assertStringContainsString('reload', strtolower($response->json('message')));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_narrower_area_requests_are_not_silently_answered_as_the_whole_scope(): void
    {
        DB::enableQueryLog();
        $this->ask('Summarize farmers in Foreign municipality')->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('Registered farmers: 1');
        $this->assertEmpty(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($sql) => str_contains($sql, 'from "farmers"')));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_context_contains_only_bounded_encrypted_reference_hints_and_never_the_chat(): void
    {
        $response = $this->ask('Find farmer '.$this->farmer->agri_gov_id)->assertOk();
        $token = $response->json('context');
        $this->assertIsString($token);
        $this->assertLessThanOrEqual(4096, strlen($token));
        $contents = Crypt::decryptString($token);
        foreach (['Juan', 'Fixture', '400.000', 'private-contact-fixture', 'Secret plot name', 'Find farmer'] as $private) {
            $this->assertStringNotContainsString($private, $contents);
        }
        $this->assertNull(session('assistant.history'));
        $this->assertNull(session('assistant.conversation'));
        $audit = AuditLog::where('module', 'Office Assistant')->get()->toJson();
        $this->assertStringNotContainsString($token, $audit);
        $this->assertStringNotContainsString('Juan', $audit);
        $this->assertStringNotContainsString($this->farmer->agri_gov_id, $audit);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_tampered_or_expired_context_yields_a_safe_reset_without_provider_access(): void
    {
        $response = $this->ask('Show assistance for 2025')->assertOk();
        $token = $response->json('context');
        $this->ask('What about harvests for the same year?', ['context' => substr($token, 0, -2).'xx'])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('200.000');
        $this->travel(16)->minutes();
        $this->withSession(['auth.last_activity_at' => now()->timestamp]);
        $this->ask('What about harvests for the same year?', ['context' => $token])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('200.000');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_a_context_from_another_user_or_session_cannot_select_a_farmer(): void
    {
        $first = $this->ask('Find farmer '.$this->farmer->agri_gov_id)->assertOk();
        $token = $first->json('context');
        $this->actingAs($this->account());
        $this->ask('Show his seeds received', ['context' => $token])->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('Juan');
        $this->actingAs($this->staff);
        session()->regenerate();
        $this->ask('Show his seeds received', ['context' => $token])->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('Juan');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_a_scope_change_invalidates_context_before_related_records_are_read(): void
    {
        $first = $this->ask('Find farmer '.$this->farmer->agri_gov_id)->assertOk();
        $this->staff->forceFill(['municipality_id' => $this->other->id])->save();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->ask('Show his seeds received', ['context' => $first->json('context')])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertDontSee('Juan')->assertDontSee('15.000');
        $this->assertEmpty(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($sql) => preg_match('/from "(?:farmers|farm_plots|rice_seed_distributions)"/i', $sql)));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_selected_farmer_is_reauthorized_on_every_turn_with_identical_foreign_and_missing_failures(): void
    {
        $contexts = app(AssistantConversationContext::class);
        $sessionId = session()->getId();
        $foreign = $contexts->issue($this->staff, $sessionId, ['task' => 'farmer', 'year' => 2026, 'farmer_id' => $this->foreign->id]);
        $missing = $contexts->issue($this->staff, $sessionId, ['task' => 'farmer', 'year' => 2026, 'farmer_id' => 987654]);
        $foreignResponse = $this->ask('Show his seeds received', ['context' => $foreign])->assertNotFound();
        $missingResponse = $this->ask('Show his seeds received', ['context' => $missing])->assertNotFound();
        $this->assertSame($foreignResponse->json('message'), $missingResponse->json('message'));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_busy_chat_does_not_share_operational_user_or_record_locks(): void
    {
        $userKey = 'mutating-user:v1:'.hash('sha256', (string) $this->staff->id);
        $recordKey = 'mutating-record:v2:'.hash('sha256', Farmer::class.':'.$this->farmer->id);
        $operationalLocks = [Cache::lock($userKey, 60), Cache::lock($recordKey, 60)];
        foreach ($operationalLocks as $lock) {
            $this->assertTrue($lock->get());
        }
        try {
            $this->ask('How do I draw a crop area?')->assertOk()->assertJsonPath('source', 'help');
        } finally {
            foreach ($operationalLocks as $lock) {
                $lock->release();
            }
        }
        $chatLock = Cache::lock('assistant:inflight:v1:'.hash('sha256', (string) $this->staff->id), 60);
        $this->assertTrue($chatLock->get());
        try {
            $this->ask('Summarize my farmers')->assertStatus(409);
            $writeLock = Cache::lock($userKey, 60);
            $this->assertTrue($writeLock->get());
            $writeLock->release();
        } finally {
            $chatLock->release();
        }
        $this->ask('Summarize my farmers')->assertOk();
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_provider_failure_releases_chat_lock_and_local_reports_still_work(): void
    {
        OfficeHelpAgent::fake(fn () => throw new \RuntimeException('private-provider-error'));
        $this->ask('How do I draw a crop area?')->assertOk()->assertJsonPath('delivery', 'guide')->assertDontSee('private-provider-error');
        $this->ask('Summarize my farmers')->assertOk()->assertJsonPath('source', 'records');
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_a_long_help_reply_cannot_recreate_a_file_session_destroyed_by_concurrent_logout(): void
    {
        $this->useFileSession();
        $sessionId = session()->getId();
        OfficeHelpAgent::fake(function (string $prompt) use ($sessionId): array {
            $current = request()->session();
            $this->assertTrue($current->getHandler()->destroy($sessionId));
            $this->assertFileDoesNotExist($this->sessionDirectory.'/'.$sessionId);

            return $this->structuredHelp($prompt);
        });
        $this->ask('How do I draw a crop area?')->assertOk()->assertCookieMissing(config('session.cookie'))->assertCookieMissing('XSRF-TOKEN');
        $this->assertFileDoesNotExist($this->sessionDirectory.'/'.$sessionId);
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_a_long_help_reply_cannot_overwrite_new_auth_csrf_or_flash_session_state(): void
    {
        $this->useFileSession();
        OfficeHelpAgent::fake(function (string $prompt): array {
            $concurrent = $this->independentSession();
            $concurrent->start();
            $concurrent->forget([auth()->guard('web')->getName(), 'race-fixture']);
            $concurrent->put('_token', 'concurrent-synthetic-csrf');
            $concurrent->flash('status', 'Concurrent update completed.');
            $concurrent->save();
            touch($this->sessionDirectory.'/'.session()->getId(), now()->timestamp);

            return $this->structuredHelp($prompt);
        });
        $this->ask('How do I draw a crop area?')->assertOk()->assertCookieMissing(config('session.cookie'))->assertCookieMissing('XSRF-TOKEN');
        $persisted = $this->independentSession();
        $persisted->start();
        $this->assertNull($persisted->get(auth()->guard('web')->getName()));
        $this->assertNull($persisted->get('race-fixture'));
        $this->assertSame('concurrent-synthetic-csrf', $persisted->token());
        $this->assertSame('Concurrent update completed.', $persisted->get('status'));
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_report_query_count_and_payload_remain_bounded_as_the_registry_grows(): void
    {
        DB::enableQueryLog();
        $this->ask('Summarize my farmers')->assertOk();
        $smallQueries = $this->operationalQueries();
        $rows = [];
        for ($i = 0; $i < 800; $i++) {
            $rows[] = ['municipality_id' => $this->own->id, 'first_name' => 'Synthetic', 'last_name' => 'Farmer'.$i];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('farmers')->insert($chunk);
        }
        DB::flushQueryLog();
        $response = $this->ask('Summarize my farmers')->assertOk()->assertSee('Registered farmers: 801');
        $this->assertSame($smallQueries, $this->operationalQueries());
        $this->assertLessThanOrEqual(32 * 1024, strlen($response->getContent()));
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_request_coordination_failure_stops_the_provider_without_leaking_the_failure(): void
    {
        Log::spy();
        Cache::shouldReceive('lock')->once()->with('assistant:inflight:v1:'.hash('sha256', (string) $this->staff->id), 30)
            ->andThrow(new \RuntimeException('private-coordination-failure'));
        $this->ask('How do I draw a crop area?')->assertStatus(503)->assertDontSee('private-coordination-failure');
        Log::shouldHaveReceived('warning')->with('Assistant request coordination unavailable.', ['exception_type' => \RuntimeException::class])->once();
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_failed_early_session_save_fails_closed_and_releases_the_chat_lock(): void
    {
        Log::spy();
        $session = \Mockery::mock(Session::class);
        $session->shouldReceive('save')->once()->andThrow(new \RuntimeException('private-session-save-failure'));
        $request = Request::create('/assistant/message', 'POST');
        $route = (new Route(['POST'], '/assistant/message', fn () => null))->name('assistant.message');
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $this->staff);
        $request->setLaravelSession($session);
        $nextCalled = false;
        DB::enableQueryLog();
        $response = app(SynchronizeAssistantRequests::class)->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['answer' => 'This path must not run.']);
        });
        $this->assertSame(503, $response->getStatusCode());
        $this->assertFalse($nextCalled);
        $this->assertSame(true, $request->attributes->get('assistant_session_committed'));
        $this->assertStringNotContainsString('private-session-save-failure', $response->getContent());
        $this->assertEmpty(DB::getQueryLog());
        $lock = Cache::lock('assistant:inflight:v1:'.hash('sha256', (string) $this->staff->id), 30);
        $this->assertTrue($lock->get());
        $lock->release();
        Log::shouldHaveReceived('warning')->with('Assistant session activity could not be saved.', ['exception_type' => \RuntimeException::class])->once();
        OfficeHelpAgent::assertNeverPrompted();
    }

    private function operationalQueries(): int
    {
        return count(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($sql) => preg_match('/from "(?:farmers|farm_plots|rice_seed_distributions|harvest_records|parcel_crop_seasons)"/i', $sql)));
    }

    private function structuredHelp(string $question): array
    {
        $user = auth()->user() ?? $this->staff;
        $topic = array_search($question, array_map(fn (array $entry) => $entry['question'], OfficeAssistant::TOPICS), true);
        $evidence = app(AssistantKnowledge::class)->retrieve($user, $topic === false ? 'access' : $topic, $question);

        return ['answer' => 'Open the documented workspace and follow its authorized steps.', 'supported' => true,
            'citations' => [$evidence['passages'][0]['id']]];
    }

    private function useFileSession(bool $authenticated = true): void
    {
        $this->sessionDirectory = sys_get_temp_dir().'/agrilgu-assistant-sessions-'.bin2hex(random_bytes(12));
        mkdir($this->sessionDirectory, 0700);
        config(['session.driver' => 'file', 'session.files' => $this->sessionDirectory, 'session.encrypt' => true, 'session.lottery' => [0, 100]]);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        auth()->forgetGuards();
        session()->start();
        session()->put('race-fixture', 'old-request-state');
        if ($authenticated) {
            $this->actingAs($this->staff);
            session()->put(auth()->guard('web')->getName(), $this->staff->id);
        }
        session()->save();
        touch($this->sessionDirectory.'/'.session()->getId(), now()->timestamp);
    }

    private function independentSession(): EncryptedStore
    {
        return new EncryptedStore(session()->getName(), session()->getHandler(), app('encrypter'), session()->getId());
    }

    protected function tearDown(): void
    {
        if ($this->sessionDirectory !== null) {
            // Delete only direct files from this random, synthetic test directory.
            foreach (glob($this->sessionDirectory.'/*') as $file) {
                if (is_file($file) && dirname($file) === $this->sessionDirectory) {
                    unlink($file);
                }
            }
            rmdir($this->sessionDirectory);
        }
        parent::tearDown();
    }

    private function ask(string $message, array $extra = []): TestResponse
    {
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());

        return $this->postJson(route('assistant.message'), array_replace(['message' => $message, 'module' => 'farmers'], $extra));
    }

    private function account(): User
    {
        return User::withoutEvents(fn () => User::create(['name' => 'Synthetic officer', 'email' => bin2hex(random_bytes(5)).'@example.test',
            'password' => 'non-login-fixture', 'is_active' => true, 'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $this->own->id]));
    }

    private function municipality(Province $province, string $name): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::create(['name' => $name, 'province' => $province->name, 'province_id' => $province->id, 'is_active' => true]));
    }

    private function release(float $quantity, int $year, ?int $farmerId = null): void
    {
        DB::table('rice_seed_distributions')->insert(['municipality_id' => $this->own->id, 'farmer_id' => $farmerId ?? $this->farmer->id,
            'input_category' => 'rice_seed', 'quantity_unit' => 'kg', 'kgs_received' => $quantity, 'date_received' => "{$year}-01-10"]);
    }

    private function harvest(float $quantity, int $year): void
    {
        DB::table('harvest_records')->insert(['municipality_id' => $this->own->id, 'farmer_id' => $this->farmer->id,
            'commodity' => 'corn', 'quantity_unit' => 'kg', 'quantity' => $quantity, 'harvest_year' => $year]);
    }
}

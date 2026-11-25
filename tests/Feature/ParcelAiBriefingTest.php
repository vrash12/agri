<?php

namespace Tests\Feature;

use App\Exceptions\AssistantUnavailable;
use App\Http\Middleware\SynchronizeAssistantRequests;
use App\Models\AuditLog;
use App\Models\FarmerPortalAccount;
use App\Models\FarmPlot;
use App\Models\ParcelFieldAssessment;
use App\Models\User;
use App\Modules\Assistant\Application\ParcelCropAdvice;
use App\Modules\Assistant\Infrastructure\CropAdviceAgent;
use App\Modules\Climate\Application\ClimateAutomaticMonitor;
use App\Modules\Climate\Application\ClimateEvidenceContext;
use App\Modules\Climate\Application\ParcelBriefing;
use App\Modules\Climate\Application\ParcelStressMonitor;
use App\Modules\Climate\Application\ParcelSuitability;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Tests\TestCase;

/** Synthetic records and fake-only provider calls; no satellite/weather/OpenAI requests. */
class ParcelAiBriefingTest extends TestCase
{
    private const PRIVATE_STRINGS = ['Synthetic', 'Farmer', 'Ramos', 'Tarlac', 'Test parcel', '15.68', '120.62',
        'Private canal note', 'Private visit note', 'Private section name', 'office1@example.test', 'Foreign'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'array', 'services.google_maps.key' => '', 'session.idle_timeout' => 120,
            'assistant.enabled' => true, 'assistant.daily_requests' => 100, 'assistant.monthly_requests' => 1000,
            'assistant.user_daily_requests' => 20, 'ai.providers.openai.key' => 'configured-for-fakes-only',
            'ai.providers.openai.store' => false, 'ai.providers.openai.url' => 'https://api.openai.com/v1']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Http::preventStrayRequests();
        Http::fake();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
        $this->schema();
        $this->fixtures();
        CropAdviceAgent::fake(function (string $prompt): StructuredTextResponse {
            $output = ['answer' => "Check soil moisture and the next usable water supply.\n\nConfirm the cause on the farm with the agriculture office.",
                'supported' => true, 'citations' => ['agri-irrigation']];

            return new StructuredTextResponse($output, json_encode($output), new TextUsage(140, 40), new Meta('openai', config('assistant.model')));
        })->preventStrayPrompts();
    }

    public function test_missing_evidence_is_reported_as_unavailable_without_provider_calls(): void
    {
        $this->actingAs(User::findOrFail(1))->get($this->url())->assertOk()
            ->assertSee('Parcel summary · no AI used')->assertSee('No crop is recorded for the 2026 dry season.')
            ->assertSee('No field check is recorded for this season.')
            ->assertSee('No usable saved satellite pictures for this crop period.')
            ->assertSee('No current municipality weather or rainfall reading. Missing data is not zero rainfall.')
            ->assertSee('No harvest is recorded for this parcel. This does not mean nothing was harvested.')
            ->assertSee('Municipality-level, not measured at this parcel')
            ->assertDontSee('Ask AI for crop advice')
            ->assertViewHas('briefing', fn (array $b) => $b['categories']['crop'] === 'not_recorded'
                && $b['categories']['satellite_trend'] === 'unavailable' && $b['categories']['rainfall'] === 'unavailable'
                && count($b['unavailable']) === 5);
        CropAdviceAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_summary_uses_saved_crop_season_field_visit_follow_up_and_parcel_harvests_only(): void
    {
        $this->seedEvidence();
        $this->actingAs(User::findOrFail(1))->get($this->url())->assertOk()
            ->assertSee('Rice / Palay is recorded for the 2026 dry season.')
            ->assertSee('1 planted section: Rice / Palay')->assertSee(' ha.')
            ->assertSee('Checked Oct 19, 2026: Growing leaves; Wilting; Water supply is limited.')
            ->assertSee('Follow-up inspection was due Oct 18, 2026.')
            ->assertSee('2 harvests are recorded for this parcel')
            ->assertSee('2025 wet season · Rice / Palay · 42.5 Sacks · harvested Nov 2, 2025')
            ->assertSee('2024 dry season · Corn · quantity not recorded')
            ->assertDontSee('999 Metric tons')->assertDontSee('777 Sacks')
            ->assertSee('Wilting was reported.')->assertSee('Ask AI for crop advice')
            ->assertViewHas('briefing', fn (array $b) => $b['categories'] === ['crop' => 'rice', 'season' => 'dry',
                'growth_stage' => 'vegetative', 'condition' => 'wilting', 'water_supply' => 'limited',
                'satellite_trend' => 'unavailable', 'rainfall' => 'unavailable']);
        Http::assertNothingSent();
    }

    public function test_expired_field_check_and_old_parcel_context_are_unavailable_not_reassuring(): void
    {
        $this->assessment(['observed_on' => '2026-09-20', 'water_observed_on' => '2026-09-20']);
        $this->actingAs(User::findOrFail(1))->get($this->url())->assertOk()
            ->assertSee('is older than 14 days. Its crop stage, condition and water readings are treated as unavailable.')
            ->assertViewHas('briefing', fn (array $b) => $b['categories']['growth_stage'] === 'unknown'
                && $b['categories']['condition'] === 'unknown' && $b['categories']['water_supply'] === 'unknown'
                && $b['categories']['crop'] === 'not_recorded');
        DB::table('parcel_field_assessments')->update(['observed_on' => '2026-10-19', 'context_fingerprint' => str_repeat('a', 64)]);
        $this->get($this->url())->assertOk()->assertSee('was for an earlier parcel boundary or owner');
    }

    public function test_satellite_and_municipality_rainfall_categories_come_from_existing_engines(): void
    {
        $plot = FarmPlot::with('farmer')->findOrFail(1);
        $assessment = ['crop' => 'rice', 'observed_on' => '2026-10-19', 'context_current' => true, 'crop_stage' => 'vegetative',
            'planted_on' => '2026-09-01', 'field_condition' => 'normal', 'water_availability' => 'reliable',
            'water_source' => 'Field check', 'water_observed_on' => '2026-10-18'];
        $picture = fn (string $date, float $mean) => ['date' => $date, 'mean' => $mean, 'valid_pixels' => 80,
            'series_key' => 'verified', 'checked_at' => '2026-10-19T00:00:00+00:00', 'is_stale' => false];
        $chirps = ['status' => 'available', 'value' => 40, 'baseline_value' => 100, 'context_current' => true,
            'period_start' => '2026-09-18', 'period_end' => '2026-10-17', 'fetched_at' => now()->utc()->subHour()->toIso8601String(),
            'metadata' => ['coverage_complete' => true, 'days_available' => 30, 'baseline_years' => 5, 'baseline_years_requested' => 5]];
        $cases = [
            [[$picture('2026-10-10', .7), $picture('2026-10-18', .5)], 'falling', 'NDVI greenness fell (-0.200)'],
            [[$picture('2026-10-10', .5), $picture('2026-10-18', .52)], 'stable', 'stayed about the same'],
            [[$picture('2026-10-10', .4), $picture('2026-10-18', .6)], 'rising', 'NDVI greenness rose'],
            [[$picture('2026-10-18', .6)], 'unavailable', 'More pictures are needed'],
        ];
        $climate = app(ClimateAutomaticMonitor::class)->summarize(['chirps' => $chirps]);
        foreach ($cases as [$pictures, $trend, $text]) {
            $briefing = $this->compose($plot, $assessment, $climate, ['ndvi' => $pictures]);
            $this->assertSame($trend, $briefing['categories']['satellite_trend']);
            $this->assertStringContainsString($text, $briefing['items'][2]['text']);
            $this->assertSame('below_reference', $briefing['categories']['rainfall']);
            $this->assertSame('Municipality-level, not measured at this parcel', $briefing['items'][3]['scope']);
            $this->assertStringContainsString('60% below the 5-year reference', implode(' ', $briefing['items'][3]['lines']));
        }
        $incomplete = $this->compose($plot, $assessment, app(ClimateAutomaticMonitor::class)->summarize(['chirps' => array_replace($chirps,
            ['metadata' => array_replace($chirps['metadata'], ['baseline_years' => 2])])]), []);
        $this->assertSame('unavailable', $incomplete['categories']['rainfall']);
        $this->assertStringContainsString('incomplete for a comparison', $incomplete['items'][3]['text']);
        Http::assertNothingSent();
    }

    public function test_provider_prompt_contains_only_allowlisted_categories_and_cache_hit_makes_no_call(): void
    {
        $this->seedEvidence();
        $expected = "What general crop-care checks should agriculture-office staff consider for a field with these conditions?\n"
            ."Crop: Rice / Palay\nSeason: Dry season\nGrowth stage: Leaf growth\nObserved field condition: Wilting\n"
            ."Water supply: Limited\nSatellite greenness trend: Unavailable (no comparable saved pictures)\n"
            .'Municipality-level rainfall (area, not this field): Unavailable';
        $response = $this->actingAs(User::findOrFail(1))->postJson($this->adviceUrl(), ['year' => 2026, 'season' => 'dry'])->assertOk()
            ->assertJsonPath('delivery', 'ai')->assertJsonPath('sources.0.id', 'agri-irrigation')
            ->assertJsonStructure(['answer', 'sources' => [['id', 'title']], 'delivery']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        CropAdviceAgent::assertPrompted(function (AgentPrompt $prompt) use ($expected): bool {
            $this->assertSame($expected, $prompt->prompt);
            $this->assertEmpty($prompt->messages);
            $this->assertSame('openai', $prompt->provider->name());
            $instructions = $prompt->agent->instructions();
            $this->assertStringContainsString('"id":"agri-irrigation"', $instructions);
            $this->assertStringContainsString('"id":"agri-pest-management"', $instructions);
            foreach (self::PRIVATE_STRINGS as $private) {
                $this->assertStringNotContainsString($private, $prompt->prompt.$instructions, $private);
            }

            return true;
        });
        $this->postJson($this->adviceUrl(), ['year' => 2026, 'season' => 'dry'])->assertOk()->assertJsonPath('delivery', 'cache');
        CropAdviceAgent::assertPromptedTimes(1);
        $this->assertSame(1, $this->dailyRequests());
        $audits = AuditLog::where('module', 'Office Assistant')->orderBy('id')->get();
        $this->assertSame(['ai', 'cache'], $audits->pluck('metadata.delivery')->all());
        $this->assertSame(140, $audits[0]->metadata['provider_input_count']);
        $this->assertSame(0, $audits[1]->metadata['provider_input_count']);
        foreach (self::PRIVATE_STRINGS as $private) {
            $this->assertStringNotContainsString($private, $this->adviceCacheValues(), $private);
            $this->assertStringNotContainsString($private, json_encode($audits->pluck('metadata')), $private);
        }
        Http::assertNothingSent();
    }

    public function test_cache_key_is_shared_by_category_combination_across_parcels_and_offices(): void
    {
        $this->seedEvidence();
        $this->assessment(['farm_plot_id' => 2, 'farmer_id' => 2, 'municipality_id' => 2], 2);
        DB::table('parcel_crop_seasons')->insert(['municipality_id' => 2, 'farm_plot_id' => 2, 'crop_year' => 2026, 'season' => 'dry', 'crop' => 'rice']);
        $this->actingAs(User::findOrFail(1))->postJson($this->adviceUrl(), ['year' => 2026, 'season' => 'dry'])->assertJsonPath('delivery', 'ai');
        $this->actingAs(User::findOrFail(2))->postJson('/farm-plots/2/agronomy/advice', ['year' => 2026, 'season' => 'dry'])->assertJsonPath('delivery', 'cache');
        CropAdviceAgent::assertPromptedTimes(1);
    }

    public function test_budget_exhaustion_disabled_assistant_and_unrecorded_crop_show_safe_unavailable_states(): void
    {
        $this->actingAs(User::findOrFail(1))->postJson($this->adviceUrl())->assertStatus(422)
            ->assertJsonPath('message', 'Record this season’s crop, or a current field check, before asking for crop advice.');
        $this->seedEvidence();
        config(['assistant.daily_requests' => 0]);
        $this->postJson($this->adviceUrl())->assertStatus(429)->assertJsonStructure(['message']);
        config(['assistant.daily_requests' => 100, 'assistant.enabled' => false]);
        $this->postJson($this->adviceUrl())->assertStatus(503)->assertJsonPath('message', 'AI crop advice is not available. Ask your administrator to enable the Assistant.');
        $this->get($this->url())->assertOk()->assertDontSee('Ask AI for crop advice')
            ->assertSee('AI crop advice is not available for this account or office.');
        CropAdviceAgent::assertNeverPrompted();
        $this->assertSame(0, AuditLog::where('module', 'Office Assistant')->count());
    }

    public function test_provider_failure_falls_back_to_reviewed_passage_and_rejected_output_is_not_cached(): void
    {
        $this->seedEvidence();
        CropAdviceAgent::fake(fn () => ['answer' => 'Spray product X at 2 L per hectare, see https://example.test', 'supported' => true,
            'citations' => ['agri-irrigation']])->preventStrayPrompts();
        $this->actingAs(User::findOrFail(1))->postJson($this->adviceUrl())->assertOk()->assertJsonPath('delivery', 'guide')
            ->assertJsonPath('sources.0.id', 'agri-irrigation');
        $this->postJson($this->adviceUrl())->assertOk()->assertJsonPath('delivery', 'guide');
        CropAdviceAgent::assertPromptedTimes(2);
    }

    public function test_other_municipality_and_restricted_roles_are_denied_before_provider_access(): void
    {
        $this->seedEvidence();
        $this->actingAs(User::findOrFail(2))->getJson($this->url())->assertForbidden();
        $this->postJson($this->adviceUrl())->assertForbidden();
        foreach ([5, 7, 8, 9] as $id) {
            $this->actingAs(User::findOrFail($id))->postJson($this->adviceUrl())->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->postJson($this->adviceUrl())->assertUnauthorized();
        auth()->guard('farmer')->setUser(new FarmerPortalAccount(['farmer_id' => 1, 'municipality_id' => 1]));
        auth()->shouldUse('web');
        $this->postJson($this->adviceUrl())->assertUnauthorized();
        // Read-only oversight may read the parcel and receive general advice.
        $this->actingAs(User::findOrFail(3))->postJson($this->adviceUrl())->assertOk();
        CropAdviceAgent::assertPromptedTimes(1);
        Http::assertNothingSent();
    }

    public function test_route_is_throttled_and_uses_assistant_coordination_instead_of_write_locks(): void
    {
        $route = Route::getRoutes()->getByName('farm-plots.agronomy.advice');
        $this->assertContains('throttle:4,1,parcel-crop-advice', $route->gatherMiddleware());
        $this->assertContains(SynchronizeAssistantRequests::class, $route->gatherMiddleware());
        $this->assertContains('synchronized', $route->excludedMiddleware());
        $this->seedEvidence();
        $this->actingAs(User::findOrFail(1));
        for ($i = 0; $i < 4; $i++) {
            $this->postJson($this->adviceUrl())->assertOk();
        }
        $this->postJson($this->adviceUrl())->assertStatus(429);
        CropAdviceAgent::assertPromptedTimes(1);
    }

    public function test_missing_csrf_is_rejected_before_the_provider(): void
    {
        $this->seedEvidence();
        $this->app->instance('env', 'local');
        $this->actingAs(User::findOrFail(1))->postJson($this->adviceUrl())->assertStatus(419);
        CropAdviceAgent::assertNeverPrompted();
    }

    public function test_advice_service_rejects_values_outside_the_allowlist(): void
    {
        $advice = app(ParcelCropAdvice::class);
        $valid = ['crop' => 'rice', 'season' => 'dry', 'growth_stage' => 'unknown', 'condition' => 'unknown',
            'water_supply' => 'unknown', 'satellite_trend' => 'unavailable', 'rainfall' => 'unavailable'];
        $this->assertStringContainsString('Crop: Rice / Palay', $advice->question($valid));
        foreach ([['crop' => 'Synthetic Farmer'], ['season' => 'Ramos'], ['extra' => 'x']] as $change) {
            try {
                $advice->question(array_replace($valid, $change));
                $this->fail('Unexpected category accepted.');
            } catch (AssistantUnavailable $exception) {
                $this->assertSame(422, $exception->status);
            }
        }
        $this->assertSame(['pest-management', 'crop-nutrition'], $advice->topics(array_replace($valid, ['condition' => 'yellowing'])));
        $this->assertSame(['crop-rotation', 'soil-health'], $advice->topics(array_replace($valid, ['growth_stage' => 'harvested'])));
    }

    private function compose(FarmPlot $plot, array $assessment, array $climate, array $satellite): array
    {
        $stress = app(ParcelStressMonitor::class)->summarize($assessment, $climate, $satellite);

        return app(ParcelBriefing::class)->compose($plot, User::findOrFail(1), 2026, 'dry', ['assessment' => $assessment,
            'climate' => $climate, 'satellite' => $satellite, 'stress' => $stress,
            'suitability' => app(ParcelSuitability::class)->evaluate($assessment)]);
    }

    private function seedEvidence(): void
    {
        DB::table('parcel_crop_seasons')->insert(['municipality_id' => 1, 'farm_plot_id' => 1, 'crop_year' => 2026, 'season' => 'dry',
            'crop' => 'rice', 'planted_areas' => json_encode([['area_key' => '3f2c1b9e-1111-4a2b-9c3d-123456789abc', 'name' => 'Private section name',
                'crop' => 'rice', 'variety' => '', 'polygon' => [['lat' => 15.681, 'lng' => 120.625], ['lat' => 15.681, 'lng' => 120.628], ['lat' => 15.684, 'lng' => 120.628]]]])]);
        $this->assessment();
        DB::table('harvest_records')->insert([
            ['municipality_id' => 1, 'farmer_id' => 1, 'farm_plot_id' => 1, 'commodity' => 'rice', 'season' => 'wet', 'harvest_year' => 2025, 'date_harvested' => '2025-11-02', 'quantity' => 42.5, 'quantity_unit' => 'sack'],
            ['municipality_id' => 1, 'farmer_id' => 1, 'farm_plot_id' => 1, 'commodity' => 'corn', 'season' => 'dry', 'harvest_year' => 2024, 'date_harvested' => null, 'quantity' => null, 'quantity_unit' => null],
            ['municipality_id' => 1, 'farmer_id' => 3, 'farm_plot_id' => 1, 'commodity' => 'rice', 'season' => 'wet', 'harvest_year' => 2026, 'date_harvested' => null, 'quantity' => 777, 'quantity_unit' => 'sack'],
            ['municipality_id' => 2, 'farmer_id' => 2, 'farm_plot_id' => 2, 'commodity' => 'rice', 'season' => 'wet', 'harvest_year' => 2026, 'date_harvested' => null, 'quantity' => 999, 'quantity_unit' => 'ton'],
        ]);
    }

    private function assessment(array $changes = [], int $plotId = 1): void
    {
        $plot = FarmPlot::with('farmer')->findOrFail($plotId);
        $evidence = array_fill_keys(ParcelFieldAssessment::EVIDENCE_FIELDS, null);
        $evidence = array_replace($evidence, ['crop_stage' => 'vegetative', 'planted_on' => '2026-09-01', 'field_condition' => 'wilting',
            'water_availability' => 'limited', 'water_source' => 'Private canal note', 'water_observed_on' => $changes['water_observed_on'] ?? '2026-10-19',
            'next_inspection_on' => '2026-10-18', 'follow_up_status' => 'open', 'follow_up_notes' => 'Private visit note']);
        ParcelFieldAssessment::create(array_replace(['farm_plot_id' => $plot->id, 'farmer_id' => $plot->farmer_id,
            'municipality_id' => $plot->farmer->municipality_id, 'crop_year' => 2026, 'season' => 'dry', 'crop' => 'rice',
            'observed_on' => '2026-10-19', 'context_fingerprint' => ClimateEvidenceContext::plot($plot), 'evidence' => $evidence,
            'recorded_by' => 1], array_diff_key($changes, ['water_observed_on' => true])));
    }

    private function adviceCacheValues(): string
    {
        $store = cache()->getStore();
        $storage = (fn () => $this->storage)->call($store);
        $values = array_filter($storage, fn ($key) => str_starts_with($key, 'assistant:crop-advice:'), ARRAY_FILTER_USE_KEY);
        $this->assertNotEmpty($values);

        return json_encode($values);
    }

    private function dailyRequests(): int
    {
        return (int) cache()->get('assistant:requests:day:'.now()->utc()->format('Ymd'), 0);
    }

    private function url(): string
    {
        return '/farm-plots/1/agronomy';
    }

    private function adviceUrl(): string
    {
        return '/farm-plots/1/agronomy/advice';
    }

    private function schema(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active');
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->string('code');
            $table->unsignedBigInteger('province_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->boolean('visitor_mode')->default(false);
            $table->dateTime('evaluation_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farmer_id');
            $table->string('name');
            $table->json('polygon_json');
            $table->string('color');
            $table->decimal('area_ha', 15, 4);
            $table->timestamps();
        });
        Schema::create('parcel_crop_seasons', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farm_plot_id');
            $table->unsignedSmallInteger('crop_year');
            $table->string('season', 10);
            $table->string('crop', 30);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->json('planted_areas')->nullable();
            $table->timestamps();
        });
        Schema::create('harvest_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farmer_id')->nullable();
            $table->unsignedBigInteger('farm_plot_id')->nullable();
            $table->unsignedBigInteger('parcel_crop_season_id')->nullable();
            $table->string('planted_area_key', 64)->nullable();
            $table->unsignedBigInteger('rice_seed_distribution_id')->nullable();
            $table->string('commodity', 40);
            $table->string('variety', 120)->nullable();
            $table->string('season', 10)->nullable();
            $table->unsignedSmallInteger('harvest_year')->nullable();
            $table->date('date_harvested')->nullable();
            $table->decimal('area_harvested_ha', 12, 4)->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('quantity_unit', 20)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        (require database_path('migrations/2026_10_19_000400_create_parcel_field_assessments_table.php'))->up();
    }

    private function fixtures(): void
    {
        DB::table('regions')->insert(['id' => 1, 'name' => 'Synthetic Region', 'is_active' => true]);
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Tarlac', 'region_id' => 1], ['id' => 2, 'name' => 'Other', 'region_id' => null]]);
        DB::table('municipalities')->insert([
            ['id' => 1, 'name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => 1, 'code' => 'RAMOS'],
            ['id' => 2, 'name' => 'Foreign Municipality', 'province' => 'Other', 'province_id' => 2, 'code' => 'OTHER'],
        ]);
        foreach ([1 => [User::ROLE_MUNICIPAL_STAFF, 1, null], 2 => [User::ROLE_MUNICIPAL_STAFF, 2, null],
            3 => [User::ROLE_SUPER_ADMIN, null, 1], 5 => [User::ROLE_PROVINCIAL_VET, null, 1],
            7 => [User::ROLE_GIS_EVALUATOR, null, null], 8 => [User::ROLE_MUNICIPAL_STAFF, 1, null],
            9 => [User::ROLE_SUPER_ADMIN, null, null]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Synthetic office '.$id, 'email' => 'office'.$id.'@example.test',
                'password' => 'unused', 'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province,
                'visitor_mode' => $id === 9, 'evaluation_expires_at' => $id === 7 ? now()->addDay() : null, 'is_active' => $id !== 8]);
        }
        DB::table('farmers')->insert([
            ['id' => 1, 'municipality_id' => 1, 'first_name' => 'Synthetic', 'last_name' => 'Farmer'],
            ['id' => 2, 'municipality_id' => 2, 'first_name' => 'Foreign', 'last_name' => 'Farmer'],
            ['id' => 3, 'municipality_id' => 1, 'first_name' => 'Another', 'last_name' => 'Farmer'],
        ]);
        foreach ([1, 2] as $id) {
            FarmPlot::create(['farmer_id' => $id, 'name' => 'Test parcel '.$id, 'color' => '#123456', 'area_ha' => 1.2,
                'polygon_json' => [['lat' => 15.68, 'lng' => 120.62], ['lat' => 15.68, 'lng' => 120.63], ['lat' => 15.69, 'lng' => 120.63]]]);
        }
    }
}

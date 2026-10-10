<?php

namespace Tests\Feature;

use App\Ai\Agents\OfficeHelpAgent;
use App\Exceptions\AssistantUnavailable;
use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Support\AssistantKnowledge;
use App\Support\AuditTrail;
use App\Support\OfficeAssistant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

/** Retrieval/generation boundaries use synthetic records and fake-only provider calls. */
final class AssistantRagTest extends TestCase
{
    use ProvinceScopeSchema;

    private User $staff;

    private Municipality $municipality;

    private ?string $temporaryResources = null;

    private string $originalResources;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 04:00:00', 'UTC'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'assistant.enabled' => true,
            'assistant.user_daily_requests' => 20, 'ai.providers.openai.key' => 'configured-for-fakes-only',
            'ai.providers.openai.store' => false, 'ai.providers.openai.url' => 'https://api.openai.com/v1']);
        DB::purge('sqlite');
        $this->createSchema();
        Schema::table('users', fn (Blueprint $table) => $table->boolean('visitor_mode')->default(false));
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Synthetic province', 'is_active' => true]));
        $this->municipality = Municipality::withoutEvents(fn () => Municipality::create([
            'name' => 'Synthetic municipality', 'province' => $province->name, 'province_id' => $province->id, 'is_active' => true,
        ]));
        $this->staff = $this->user(User::ROLE_MUNICIPAL_STAFF);
        $this->originalResources = resource_path();
        Http::preventStrayRequests();
        OfficeHelpAgent::fake(fn (string $prompt) => $this->validOutput(auth()->user() ?? $this->staff, $this->topic($prompt)))->preventStrayPrompts();
    }

    public function test_the_normal_office_endpoint_returns_bounded_evidence_without_retrieving_or_sending_record_data(): void
    {
        $farmer = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->municipality->id,
            'first_name' => 'PrivateFixtureName', 'last_name' => 'NeverDisclose']));
        DB::enableQueryLog();
        $question = 'How do I draw a crop area for PrivateFixtureName NeverDisclose?';
        $response = $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => $question, 'module' => 'crops'])
            ->assertOk()->assertJsonPath('source', 'help')->assertJsonPath('delivery', 'ai')
            ->assertJsonStructure(['sources' => [['id', 'title']]])->assertDontSee('NeverDisclose');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertGreaterThan(0, count($response->json('sources')));
        $this->assertLessThanOrEqual(3, count($response->json('sources')));
        foreach ($response->json('sources') as $source) {
            $this->assertSame(['id', 'title'], array_keys($source));
        }
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt) use ($question, $farmer): bool {
            $this->assertSame(OfficeAssistant::TOPICS['crop-drawing']['question'], $prompt->prompt);
            $this->assertEmpty($prompt->messages);
            foreach ([$question, 'PrivateFixtureName', 'NeverDisclose', $farmer->agri_gov_id, $this->staff->email] as $private) {
                $this->assertStringNotContainsString($private, $prompt->prompt.$prompt->agent->instructions());
            }

            return true;
        });
        $operationalQueries = array_filter(array_column(DB::getQueryLog(), 'query'),
            fn ($sql) => preg_match('/from ["`]?(farmers|farm_plots|rice_seed_distributions)["`]?/i', $sql));
        $this->assertEmpty($operationalQueries);
        $audit = AuditLog::where('module', 'Office Assistant')->sole();
        $this->assertSame(0, $audit->metadata['provider_input_count']);
        $this->assertSame(0, $audit->metadata['provider_output_count']);
        $this->assertArrayNotHasKey('input_tokens', $audit->metadata);
        $this->assertArrayNotHasKey('output_tokens', $audit->metadata);
        $this->assertStringNotContainsString($question, $audit->toJson());
        $this->assertStringNotContainsString('PrivateFixtureName', $audit->toJson());
        Http::assertNothingSent();
    }

    public function test_audits_preserve_provider_usage_counts_and_cache_hits_record_zero_new_usage(): void
    {
        OfficeHelpAgent::fake(function (string $prompt): StructuredTextResponse {
            $output = $this->validOutput($this->staff, $this->topic($prompt));

            return new StructuredTextResponse($output, json_encode($output), new TextUsage(128, 29), new Meta('openai', config('assistant.model')));
        })->preventStrayPrompts();
        $payload = ['message' => 'How do I draw a crop area?', 'module' => 'crops'];
        $this->actingAs($this->staff)->postJson(route('assistant.message'), $payload)->assertOk()->assertJsonPath('delivery', 'ai');
        $this->postJson(route('assistant.message'), $payload)->assertOk()->assertJsonPath('delivery', 'cache');
        $audits = AuditLog::where('module', 'Office Assistant')->orderBy('id')->get();
        $this->assertCount(2, $audits);
        $this->assertSame(128, $audits[0]->metadata['provider_input_count']);
        $this->assertSame(29, $audits[0]->metadata['provider_output_count']);
        $this->assertSame('ai', $audits[0]->metadata['delivery']);
        $this->assertSame(0, $audits[1]->metadata['provider_input_count']);
        $this->assertSame(0, $audits[1]->metadata['provider_output_count']);
        $this->assertSame('cache', $audits[1]->metadata['delivery']);
        $this->assertSame(1, $this->dailyRequests());
        OfficeHelpAgent::assertPromptedTimes(1);
        // Count names avoid the existing global secret-key filter; actual token
        // references and secrets must remain excluded from persistent metadata.
        $this->assertSame(['provider_input_count' => 128, 'provider_output_count' => 29], AuditTrail::cleanValues([
            'provider_input_count' => 128, 'provider_output_count' => 29,
            'access_token' => 'synthetic-reference-only', 'context_token' => 'synthetic-context-only',
            'provider_secret' => 'synthetic-secret-only', 'password' => 'synthetic-password-only',
        ]));
    }

    public function test_repeated_verified_help_is_reused_across_same_role_users_without_disclosing_user_or_scope_data(): void
    {
        $first = $this->answer();
        $otherMunicipality = Municipality::withoutEvents(fn () => Municipality::create([
            'name' => 'Different synthetic municipality', 'province' => 'Synthetic province',
            'province_id' => $this->municipality->province_id, 'is_active' => true,
        ]));
        $secondStaff = $this->user(User::ROLE_MUNICIPAL_STAFF);
        User::withoutEvents(fn () => $secondStaff->forceFill(['municipality_id' => $otherMunicipality->id])->save());
        $second = $this->answer($secondStaff);
        $this->assertSame('ai', $first['delivery']);
        $this->assertSame('cache', $second['delivery']);
        $this->assertSame($first['answer'], $second['answer']);
        $this->assertSame($first['sources'], $second['sources']);
        $this->assertSame(0, $second['input_tokens']);
        $this->assertSame(0, $second['output_tokens']);
        $this->assertSame(1, $this->dailyRequests());
        $this->assertStringNotContainsString($secondStaff->email, json_encode($second));
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_cache_separates_roles_languages_topics_and_models(): void
    {
        $this->answer();
        $this->answer($this->user(User::ROLE_MUNICIPAL_HEAD));
        $this->answer(language: 'fil');
        $this->answer(topic: 'parcel-drawing');
        config(['assistant.model' => 'synthetic-other-model']);
        $this->answer();
        OfficeHelpAgent::assertPromptedTimes(5);
        $this->assertSame(5, $this->dailyRequests());
    }

    public function test_cached_help_can_be_used_without_consuming_another_provider_allowance(): void
    {
        config(['assistant.user_daily_requests' => 1]);
        $this->assertSame('ai', $this->answer()['delivery']);
        $this->assertSame('cache', $this->answer()['delivery']);
        $this->assertSame(1, $this->dailyRequests());
        try {
            $this->answer(topic: 'parcel-drawing');
            $this->fail('A different uncached topic must enforce the exhausted provider allowance.');
        } catch (AssistantUnavailable $exception) {
            $this->assertSame(429, $exception->status);
        }
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_cached_help_expires_instead_of_becoming_an_indefinite_provider_bypass(): void
    {
        $this->answer();
        $this->travel(11)->minutes();
        $this->assertSame('ai', $this->answer()['delivery']);
        OfficeHelpAgent::assertPromptedTimes(2);
        $this->assertSame(2, $this->dailyRequests());
    }

    public function test_generation_requires_a_strict_schema_with_citations_limited_to_current_evidence(): void
    {
        $evidence = app(AssistantKnowledge::class)->retrieve($this->staff, 'crop-drawing', OfficeAssistant::TOPICS['crop-drawing']['question']);
        $agent = new OfficeHelpAgent($this->staff->role, 'crops', $evidence['passages']);
        $this->assertTrue(Strict::isAppliedTo($agent));
        $schema = (new ObjectType($agent->schema(new JsonSchemaTypeFactory)))->toArray();
        $this->assertSame(['answer', 'supported', 'citations'], $schema['required']);
        $this->assertSame('string', $schema['properties']['answer']['type']);
        $this->assertSame(3000, $schema['properties']['answer']['maxLength']);
        $this->assertSame('boolean', $schema['properties']['supported']['type']);
        $this->assertSame(3, $schema['properties']['citations']['maxItems']);
        $this->assertSame(array_column($evidence['passages'], 'id'), $schema['properties']['citations']['items']['enum']);
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_cached_provider_payload_is_revalidated_before_it_can_be_displayed(): void
    {
        $store = Cache::store('array');
        $poisoned = ['answer' => 'INVALID OUTPUT <script>private-cache-fixture</script>',
            'supported' => true, 'citations' => ['source-not-in-current-evidence']];
        Cache::shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null) => str_starts_with($key, 'assistant:help:v1:') ? $poisoned : $store->get($key, $default));
        Cache::shouldReceive('lock')->andReturnUsing(fn (string $key, int $seconds) => $store->lock($key, $seconds));
        Cache::shouldReceive('put')->andReturnUsing(fn (string $key, mixed $value, mixed $ttl) => $store->put($key, $value, $ttl));
        $result = $this->answer();
        $this->assertSame('ai', $result['delivery']);
        $this->assertStringNotContainsString('INVALID OUTPUT', $result['answer']);
        $this->assertSame(1, $this->dailyRequests());
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_corpus_changes_invalidate_verified_answers_without_clearing_other_cache_namespaces(): void
    {
        $this->useIsolatedCorpus();
        Cache::put('synthetic-unrelated-counter', 17, 3600);
        $first = $this->answer();
        $files = glob($this->temporaryResources.'/assistant/knowledge/*.md');
        $this->assertNotEmpty($files);
        file_put_contents($files[0], "\nReference revision for synthetic evaluation.\n", FILE_APPEND);
        $second = $this->answer();
        $this->assertSame('ai', $second['delivery']);
        $this->assertNotSame($first['knowledge_version'], $second['knowledge_version']);
        $this->assertSame(17, Cache::get('synthetic-unrelated-counter'));
        OfficeHelpAgent::assertPromptedTimes(2);
    }

    public function test_missing_evidence_returns_no_answer_sources_and_never_spends_a_provider_request(): void
    {
        $this->useIsolatedCorpus(false);
        $result = $this->answer();
        $this->assertSame('unavailable', $result['delivery']);
        $this->assertSame([], $result['sources']);
        $this->assertNull($this->dailyRequests());
        $this->assertStringNotContainsString('Open the documented', $result['answer']);
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    #[DataProvider('invalidOutputs')]
    public function test_invalid_or_ungrounded_provider_output_uses_only_a_vetted_extract_and_is_never_cached(mixed $invalid): void
    {
        $evidence = app(AssistantKnowledge::class)->retrieve($this->staff, 'crop-drawing', OfficeAssistant::TOPICS['crop-drawing']['question']);
        if (is_array($invalid) && array_key_exists('citations', $invalid)) {
            $invalid['citations'] = array_map(fn ($id) => $id === ':valid' ? $evidence['passages'][0]['id'] : $id, $invalid['citations']);
        }
        OfficeHelpAgent::fake(fn () => $invalid)->preventStrayPrompts();
        $first = $this->answer();
        $second = $this->answer();
        $this->assertSame('guide', $first['delivery']);
        $this->assertSame('guide', $second['delivery']);
        $this->assertNotEmpty($first['sources']);
        $this->assertSame($first['answer'], $second['answer']);
        $this->assertStringNotContainsString('INVALID OUTPUT', $first['answer']);
        OfficeHelpAgent::assertPromptedTimes(2);
        $this->assertSame(2, $this->dailyRequests());
    }

    public static function invalidOutputs(): array
    {
        $base = ['answer' => 'INVALID OUTPUT', 'supported' => true, 'citations' => [':valid']];

        return [
            'unstructured text' => ['INVALID OUTPUT'],
            'invalid json' => ['{"answer":"INVALID OUTPUT"'],
            'no citations' => [array_replace($base, ['citations' => []])],
            'unknown citation' => [array_replace($base, ['citations' => ['not-in-retrieved-evidence']])],
            'unsupported' => [array_replace($base, ['supported' => false])],
            'boolean coercion forbidden' => [array_replace($base, ['supported' => 'true'])],
            'duplicate citations' => [array_replace($base, ['citations' => [':valid', ':valid']])],
            'too many citations' => [array_replace($base, ['citations' => [':valid', ':valid', ':valid', ':valid']])],
            'non-string citation' => [array_replace($base, ['citations' => [17]])],
            'empty answer' => [array_replace($base, ['answer' => ' '])],
            'oversized answer' => [array_replace($base, ['answer' => str_repeat('x', 3001)])],
            'HTML rejected' => [array_replace($base, ['answer' => '<img src=x onerror=alert(1)> INVALID OUTPUT'])],
            'URL rejected' => [array_replace($base, ['answer' => 'INVALID OUTPUT https://untrusted.example.test'])],
            'control characters rejected' => [array_replace($base, ['answer' => "INVALID\x00 OUTPUT"])],
            'identifier rejected' => [array_replace($base, ['answer' => 'INVALID OUTPUT AGRI-F-123456'])],
            'contact rejected' => [array_replace($base, ['answer' => 'INVALID OUTPUT fixture@example.test'])],
            'secret-shaped text rejected' => [array_replace($base, ['answer' => 'INVALID OUTPUT sk-fixture1234567890'])],
            'extra provider field rejected' => [array_replace($base, ['private_context' => 'INVALID OUTPUT'])],
        ];
    }

    public function test_a_real_corpus_source_outside_the_retrieved_topic_cannot_validate_an_answer(): void
    {
        $other = app(AssistantKnowledge::class)->retrieve($this->staff, 'animals', OfficeAssistant::TOPICS['animals']['question']);
        $current = app(AssistantKnowledge::class)->retrieve($this->staff, 'crop-drawing', OfficeAssistant::TOPICS['crop-drawing']['question']);
        $otherIds = array_diff(array_column($other['passages'], 'id'), array_column($current['passages'], 'id'));
        $this->assertNotEmpty($otherIds);
        OfficeHelpAgent::fake(fn () => ['answer' => 'INVALID OUTPUT', 'supported' => true, 'citations' => [array_values($otherIds)[0]]]);
        $result = $this->answer();
        $this->assertSame('guide', $result['delivery']);
        $this->assertStringNotContainsString('INVALID OUTPUT', $result['answer']);
    }

    public function test_provider_failure_returns_a_safe_local_reference_without_error_or_prompt_text(): void
    {
        Log::spy();
        OfficeHelpAgent::fake(fn () => throw new \RuntimeException('private-provider-response-fixture'));
        $result = $this->answer();
        $this->assertSame('guide', $result['delivery']);
        $this->assertNotEmpty($result['sources']);
        $this->assertStringNotContainsString('private-provider-response-fixture', json_encode($result));
        Log::shouldHaveReceived('warning')->with('Assistant response could not be generated.', ['exception_type' => \RuntimeException::class])->once();
        $this->assertSame(1, $this->dailyRequests());
    }

    public function test_provider_misconfiguration_is_not_hidden_by_a_warm_answer_cache(): void
    {
        $this->answer();
        foreach ([['assistant.enabled' => false], ['assistant.enabled' => true, 'ai.providers.openai.store' => true],
            ['ai.providers.openai.store' => false, 'ai.providers.openai.url' => 'https://untrusted.example.test'],
            ['ai.providers.openai.url' => 'https://api.openai.com/v1', 'ai.providers.openai.key' => '']] as $configuration) {
            config($configuration);
            try {
                $this->answer();
                $this->fail('Unsafe or disabled provider configuration must fail closed before cache delivery.');
            } catch (AssistantUnavailable $exception) {
                $this->assertSame(503, $exception->status);
            }
        }
        OfficeHelpAgent::assertPromptedTimes(1);
        $this->assertSame(1, $this->dailyRequests());
    }

    public function test_failed_cache_read_stops_paid_calls_instead_of_returning_an_unaccounted_fallback(): void
    {
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('private-cache-read-fixture'));
        try {
            $this->answer();
            $this->fail('Failed cache coordination must stop the provider.');
        } catch (AssistantUnavailable $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertStringNotContainsString('private-cache-read-fixture', $exception->getMessage());
        }
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_single_flight_contention_uses_local_evidence_without_reserving_or_calling_the_provider(): void
    {
        $lock = \Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(false);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('lock')->once()->andReturn($lock);
        Cache::shouldReceive('put')->never();
        $this->assertSame('guide', $this->answer()['delivery']);
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_inactive_or_disallowed_accounts_cannot_use_previously_cached_help(): void
    {
        $this->answer();
        $visitor = $this->user(User::ROLE_SUPER_ADMIN);
        $visitor->forceFill(['visitor_mode' => true])->save();
        $inactive = $this->user(User::ROLE_MUNICIPAL_STAFF);
        $inactive->forceFill(['is_active' => false])->save();
        foreach ([$visitor, $inactive, $this->user(User::ROLE_GIS_EVALUATOR)] as $user) {
            $this->actingAs($user)->postJson(route('assistant.message'), ['message' => 'How do I draw a crop area?', 'module' => 'crops'])->assertForbidden();
        }
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    public function test_veterinary_retrieval_contains_no_farmer_or_other_operational_modules(): void
    {
        $vet = $this->user(User::ROLE_PROVINCIAL_VET);
        $this->actingAs($vet)->postJson(route('assistant.message'), ['message' => 'What can my veterinary account access?', 'module' => 'accounts'])
            ->assertOk()->assertJsonPath('delivery', 'ai');
        OfficeHelpAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $instructions = $prompt->agent->instructions();
            $this->assertStringContainsString('Current module: animal-health', $instructions);
            $this->assertStringNotContainsString('"id":"crop-sections"', $instructions);
            $this->assertStringNotContainsString('"id":"farmer-registry-card"', $instructions);

            return true;
        });
        $this->actingAs($vet)->postJson(route('assistant.message'), ['message' => 'How do I draw a crop area?', 'module' => 'crops'])
            ->assertOk()->assertJsonPath('source', 'clarification');
        OfficeHelpAgent::assertPromptedTimes(1);
    }

    private function answer(?User $user = null, string $topic = 'crop-drawing', string $language = 'en'): array
    {
        return app(OfficeAssistant::class)->answerTopic($user ?? $this->staff, $topic, $language);
    }

    private function validOutput(User $user, string $topic): array
    {
        $question = $user->isProvincialVeterinaryOffice() && $topic !== 'animals'
            ? AssistantKnowledge::VETERINARY_QUESTION : OfficeAssistant::TOPICS[$topic]['question'];
        $evidence = app(AssistantKnowledge::class)->retrieve($user, $topic, $question);

        return ['answer' => 'Open the documented workspace and follow its authorized steps.', 'supported' => true,
            'citations' => [$evidence['passages'][0]['id']]];
    }

    private function topic(string $question): string
    {
        $topic = array_search($question, array_map(fn (array $entry) => $entry['question'], OfficeAssistant::TOPICS), true);

        return $topic === false ? 'access' : $topic;
    }

    private function dailyRequests(): ?int
    {
        return Cache::get('assistant:requests:day:'.now('UTC')->format('Ymd'));
    }

    private function useIsolatedCorpus(bool $copyDocuments = true): void
    {
        $this->temporaryResources = sys_get_temp_dir().'/agrilgu-rag-resources-'.bin2hex(random_bytes(12));
        mkdir($this->temporaryResources.'/assistant/knowledge', 0700, true);
        if ($copyDocuments) {
            foreach (glob($this->originalResources.'/assistant/knowledge/*.md') as $file) {
                copy($file, $this->temporaryResources.'/assistant/knowledge/'.basename($file));
            }
        }
        $this->app->instance('path.resources', $this->temporaryResources);
        $this->app->forgetInstance(AssistantKnowledge::class);
        $this->app->forgetInstance(OfficeAssistant::class);
    }

    protected function tearDown(): void
    {
        if ($this->temporaryResources !== null) {
            $this->app->instance('path.resources', $this->originalResources);
            $directory = $this->temporaryResources.'/assistant/knowledge';
            foreach (glob($directory.'/*.md') as $file) {
                if (is_file($file) && dirname($file) === $directory) {
                    unlink($file);
                }
            }
            rmdir($directory);
            rmdir($this->temporaryResources.'/assistant');
            rmdir($this->temporaryResources);
        }
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::withoutEvents(fn () => User::create(['name' => 'Synthetic office user',
            'email' => bin2hex(random_bytes(6)).'@example.test', 'password' => 'non-login-fixture', 'role' => $role,
            'municipality_id' => in_array($role, [User::ROLE_MUNICIPAL_HEAD, User::ROLE_MUNICIPAL_STAFF], true) ? $this->municipality->id : null,
            'province_id' => in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_PROVINCIAL_STAFF, User::ROLE_PROVINCIAL_VET], true) ? $this->municipality->province_id : null,
            'is_active' => true]));
    }
}

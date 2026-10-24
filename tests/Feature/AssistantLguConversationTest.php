<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Modules\Assistant\Application\AssistantConversationContext;
use App\Modules\Assistant\Infrastructure\OfficeHelpAgent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssistantLguConversationTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.enabled' => true, 'cache.default' => 'array', 'session.driver' => 'array', 'ai.providers.openai.key' => '']);
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Test province', 'is_active' => true]));
        $municipality = Municipality::withoutEvents(fn () => Municipality::create(['name' => 'Test LGU', 'province' => $province->name, 'province_id' => $province->id, 'is_active' => true]));
        $this->staff = User::withoutEvents(fn () => User::create(['name' => 'Test officer', 'email' => 'officer@example.test', 'password' => 'non-login-fixture',
            'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $municipality->id, 'is_active' => true]));
        OfficeHelpAgent::fake()->preventStrayPrompts();
        Http::preventStrayRequests();
    }

    #[DataProvider('reports')]
    public function test_real_endpoint_preserves_local_evidence_context_and_minimal_audit(string $message, string $report): void
    {
        $response = $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => $message, 'module' => 'dashboard'])
            ->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('report', $report)
            ->assertJsonPath('evidence.scope', 'Test LGU')->assertJsonStructure(['evidence' => ['checked_at', 'period', 'sources', 'limitations']]);
        $this->assertNotEmpty($response->json('evidence.sources'));
        $this->assertLessThanOrEqual(8, count($response->json('evidence.sources')));
        $this->assertLessThanOrEqual(6000, mb_strlen($response->json('answer')));
        $token = $response->json('context');
        $this->assertSame($report, app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $token)['task']);
        $audit = AuditLog::where('module', 'Office Assistant')->sole();
        $this->assertSame($report, $audit->metadata['report']);
        $this->assertStringNotContainsString($message, json_encode($audit->metadata));
        $this->assertStringNotContainsString('checked_at', json_encode($audit->metadata));
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public static function reports(): array
    {
        return [
            ['Summarize registry sections', 'registry-sources'], ['Show outside LGU records', 'outside-lgu'],
            ['Show delisted rows', 'delisted'], ['Summarize cooperatives', 'cooperatives'],
            ['Summarize machinery', 'machinery'], ['Show LGU office details', 'lgu-profile'],
            ['Summarize crops', 'crops'], ['Summarize our LGU records', 'summary'],
        ];
    }

    public function test_year_followup_reuses_only_validated_report_reference(): void
    {
        $first = $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => 'Show our crops for 2026', 'module' => 'dashboard'])->assertOk();
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $this->postJson(route('assistant.message'), ['message' => 'What about 2025?', 'module' => 'dashboard', 'context' => $first->json('context')])
            ->assertOk()->assertJsonPath('report', 'crops')->assertJsonPath('year', 2025)->assertJsonPath('evidence.period', 'Stated crop year 2025');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_unsupported_subset_neither_queries_operational_tables_nor_calls_provider(): void
    {
        DB::enableQueryLog();
        $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => 'Summarize machinery in Foreign LGU', 'module' => 'dashboard'])
            ->assertOk()->assertJsonPath('source', 'clarification');
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/from ["`](?:farmers|farm_plots|agricultural_machineries|farmers_cooperatives|farmer_registry_source_rows)["`]/i', $query['query']);
        }
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_inventory_year_followup_does_not_relabel_current_records_as_historical_data(): void
    {
        $first = $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => 'Summarize machinery', 'module' => 'dashboard'])
            ->assertOk()->assertJsonPath('report', 'machinery');
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ([['message' => 'Show machinery for 2025'], ['message' => 'What about 2025?', 'context' => $first->json('context')]] as $request) {
            $this->postJson(route('assistant.message'), $request + ['module' => 'dashboard'])
                ->assertOk()->assertJsonPath('source', 'clarification')->assertJsonMissingPath('evidence')
                ->assertSee('current saved records');
        }
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/from ["`](?:agricultural_machineries|farmers_cooperatives|farmer_registry_source_rows)["`]/i', $query['query']);
        }
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_veterinary_account_cannot_expand_scope_via_lgu_overview_or_new_task(): void
    {
        $this->staff->forceFill(['role' => User::ROLE_PROVINCIAL_VET, 'province_id' => $this->staff->municipality->province_id, 'municipality_id' => null])->saveQuietly();
        $this->staff->unsetRelation('municipality');
        $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => 'Summarize our LGU records', 'module' => 'dashboard'])
            ->assertOk()->assertJsonPath('report', 'animal-health')->assertJsonCount(1, 'evidence.sources');
        $this->postJson(route('assistant.message'), ['message' => 'Show delisted rows', 'module' => 'farmers'])
            ->assertOk()->assertJsonPath('source', 'clarification');
        OfficeHelpAgent::assertNeverPrompted();
    }
}

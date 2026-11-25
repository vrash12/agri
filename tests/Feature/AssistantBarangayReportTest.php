<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Modules\Assistant\Application\AssistantConversationContext;
use App\Modules\Assistant\Application\AssistantIntentResolver;
use App\Modules\Assistant\Infrastructure\OfficeHelpAgent;
use App\Modules\Reporting\Application\AssistantRecords;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Synthetic farm-location breakdowns: scope, grouping, period and disclosure limits. */
class AssistantBarangayReportTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private Municipality $own;

    private Municipality $sibling;

    private Municipality $foreign;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.enabled' => true, 'cache.default' => 'array', 'session.driver' => 'array', 'ai.providers.openai.key' => '']);
        $this->travelTo(now('UTC')->setDate(2026, 10, 24)->startOfDay());
        $region = Region::withoutEvents(fn () => Region::create(['name' => 'Synthetic region', 'code' => 'synthetic', 'is_active' => true]));
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Own province', 'region_id' => $region->id, 'is_active' => true]));
        $foreignProvince = Province::withoutEvents(fn () => Province::create(['name' => 'Other province', 'is_active' => true]));
        $this->own = $this->municipality('Own LGU', $province);
        $this->sibling = $this->municipality('Sibling LGU', $province);
        $this->foreign = $this->municipality('Foreign LGU', $foreignProvince);
        $this->staff = $this->user(User::ROLE_MUNICIPAL_STAFF);
        Http::preventStrayRequests();
        OfficeHelpAgent::fake()->preventStrayPrompts();
    }

    public function test_municipal_breakdown_groups_trimmed_case_insensitive_locations_and_separates_unrecorded(): void
    {
        $this->seedOwnRecords();
        $answer = $this->read()['answer'];

        foreach (['Own LGU’s 10 registered farmers are spread across 2 recorded farm locations. San Isidro has the most, with 3 farmers.',
            'By farm location, with 2026 assistance and harvests:',
            'No farm location recorded: 5 farmers, 1 with a saved parcel · 2026: 1 release to 1 farmer, no harvest records',
            '1. San Isidro: 3 farmers, 1 with a saved parcel · 2026: 2 releases to 1 farmer, 1 harvest record',
            '2. Poblacion: 2 farmers, 0 with a saved parcel · 2026: 1 release to 1 farmer, 1 harvest record',
            '2 releases received in 2026 have no farmer linked in the same municipality, so they aren’t placed by location.',
            '1 harvest record for 2026 has no farmer linked in the same municipality, so it isn’t placed by location.',
            'not an official barangay list', 'Registry counts are current'] as $expected) {
            $this->assertStringContainsString($expected, $answer);
        }
        // Equal names in another municipality and the foreign LGU never merge in.
        foreach (['ForeignBarangaySecret', 'Sibling', 'PrivateGiven', 'PrivateFamily', 'AGRI-F-', 'more location'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $answer);
        }
        $this->assertLessThan(strpos($answer, 'Poblacion:'), strpos($answer, 'San Isidro:'));
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_reporting_year_changes_assistance_and_harvests_but_not_the_current_registry(): void
    {
        $this->seedOwnRecords();
        $report = $this->read(2025);

        $this->assertStringContainsString('Own LGU’s 10 registered farmers', $report['answer']);
        $this->assertStringContainsString('San Isidro: 3 farmers, 1 with a saved parcel · 2025: 1 release to 1 farmer, 1 harvest record', $report['answer']);
        $this->assertStringContainsString('Poblacion: 2 farmers, 0 with a saved parcel · 2025: no releases, no harvest records', $report['answer']);
        $this->assertSame('Current registry locations plus 2025 releases and harvest records', $report['evidence']['period']);
        $this->assertSame(2025, $report['year']);
    }

    public function test_provincial_scope_labels_locations_by_municipality_and_excludes_other_provinces(): void
    {
        $this->seedOwnRecords();
        $this->farmer($this->sibling, 'San Isidro');
        $this->farmer($this->sibling, 'San Isidro');
        foreach ([User::ROLE_PROVINCIAL_STAFF, User::ROLE_SUPER_ADMIN] as $role) {
            $answer = app(AssistantRecords::class)->answerTask($this->user($role), 'barangays', 2026)['answer'];
            foreach (['12 registered farmers are spread across 3 recorded farm locations (each municipality counted separately)',
                'San Isidro (Own LGU): 3 farmers', 'San Isidro (Sibling LGU): 2 farmers', 'Poblacion (Own LGU): 2 farmers'] as $expected) {
                $this->assertStringContainsString($expected, $answer, $role);
            }
            $this->assertStringNotContainsString('ForeignBarangaySecret', $answer);
            $this->assertStringNotContainsString('Foreign LGU', $answer);
        }
    }

    public function test_output_is_bounded_to_ten_locations_plus_a_remainder_with_constant_queries(): void
    {
        $this->farmer($this->own, 'Alpha');
        $this->farmer($this->own, 'Bravo');
        // Warm account relations first so both measured reads are comparable.
        $this->read();
        DB::enableQueryLog();
        $this->read();
        $small = count(DB::getQueryLog());

        $this->farmer($this->own, 'Largest place');
        $this->farmer($this->own, 'Largest place');
        $this->farmer($this->own, "Long\nimported ".str_repeat('location text ', 15));
        for ($i = 1; $i <= 10; $i++) {
            $this->farmer($this->own, sprintf('Place %02d', $i));
        }
        DB::flushQueryLog();
        $report = $this->read();
        $this->assertSame($small, count(DB::getQueryLog()), 'Query count must not grow with the number of locations.');

        $lines = explode("\n", $report['answer']);
        $listed = array_values(array_map(fn (string $line) => (string) preg_replace('/^\d+\. /', '', $line),
            array_filter($lines, fn (string $line) => (bool) preg_match('/^\d+\. .+: \d+ farmers?, \d+ with a saved parcel/', $line))));
        $this->assertCount(10, $listed);
        $this->assertStringStartsWith('Largest place: 2 farmers', $listed[0]);
        $this->assertContains('4 more locations are not shown. Open the farmer registry to review every location.', $lines);
        $this->assertStringContainsString('spread across 14 recorded farm locations', $report['answer']);
        $this->assertStringNotContainsString('Place 07', $report['answer']);
        // Control characters in imported text cannot break the one-line format.
        $long = array_values(array_filter($listed, fn (string $line) => str_starts_with($line, 'Long imported location text')));
        $this->assertCount(1, $long);
        $this->assertLessThanOrEqual(80, mb_strlen(strstr($long[0], ': 1 farmer,', true)));
        foreach ($listed as $line) {
            $this->assertLessThanOrEqual(300, mb_strlen($line));
        }
        $this->assertLessThan(6000, mb_strlen($report['answer']));
    }

    public function test_evidence_and_links_are_fixed_application_routes(): void
    {
        $report = $this->read();
        $this->assertSame('barangays', $report['report']);
        $this->assertSame('records', $report['source']);
        $this->assertSame(['Farmer registry farm locations', 'Assistance register', 'Harvest register'], array_column($report['evidence']['sources'], 'title'));
        $this->assertSame('Own LGU', $report['evidence']['scope']);
        $this->assertSame(['Open farmer registry', 'Review missing locations', 'Open assistance register', 'Open harvest register'], array_column($report['links'], 'label'));
        $this->assertSame(route('farmers.index', ['quality' => 'missing_location']), $report['links'][1]['url']);
        foreach ([...$report['links'], ...$report['evidence']['sources']] as $link) {
            $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($link['url'], PHP_URL_HOST));
        }
        $this->assertSame([], $report['actions']);
    }

    public function test_missing_activity_tables_are_unavailable_not_zero(): void
    {
        $this->farmer($this->own, 'San Isidro');
        Schema::drop('harvest_records');
        $lines = explode("\n", $this->read()['answer']);
        $this->assertContains('Harvest records are unavailable in this installation, so no harvest count can be shown.', $lines);
        $this->assertContains('1. San Isidro: 1 farmer, 0 with a saved parcel · 2026: no releases', $lines);
        $this->assertStringNotContainsString('no harvest records', implode('
', $lines));
    }

    public function test_veterinary_visitor_and_inactive_accounts_are_denied(): void
    {
        foreach ([$this->user(User::ROLE_PROVINCIAL_VET), $this->user(User::ROLE_SUPER_ADMIN, ['visitor_mode' => true, 'province_id' => null]),
            $this->user(User::ROLE_MUNICIPAL_STAFF, ['is_active' => false])] as $user) {
            try {
                app(AssistantRecords::class)->answerTask($user, 'barangays', 2026);
                $this->fail('Unauthorized barangay report returned for '.$user->role);
            } catch (HttpException|AuthorizationException $exception) {
                $this->assertTrue(! $exception instanceof HttpException || $exception->getStatusCode() === 403);
            }
        }
    }

    public function test_endpoint_reply_supports_a_year_followup_and_static_next_steps(): void
    {
        $this->seedOwnRecords();
        $first = $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => 'Show farmers per barangay', 'module' => 'farmers'])
            ->assertOk()->assertJsonPath('source', 'records')->assertJsonPath('report', 'barangays')->assertJsonPath('year', 2026)
            ->assertJsonPath('evidence.scope', 'Own LGU')->assertSee('San Isidro: 3 farmers');
        $references = app(AssistantConversationContext::class)->read($this->staff, session()->getId(), $first->json('context'));
        $this->assertSame('barangays', $references['task']);
        $suggestions = $first->json('suggestions');
        $this->assertCount(3, $suggestions);
        foreach ($suggestions as $suggestion) {
            $this->assertNotSame('clarification', app(AssistantIntentResolver::class)->resolve($suggestion['message'], $references, 'farmers', 2026)['task'], $suggestion['message']);
            foreach (['San Isidro', 'Poblacion', 'Own LGU'] as $value) {
                $this->assertStringNotContainsString($value, json_encode($suggestion));
            }
        }

        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $this->postJson(route('assistant.message'), ['message' => 'What about 2025?', 'module' => 'farmers', 'context' => $first->json('context')])
            ->assertOk()->assertJsonPath('report', 'barangays')->assertJsonPath('year', 2025)
            ->assertSee('10 registered farmers')->assertSee('2025: 1 release');
        $this->postJson(route('assistant.message'), ['message' => 'Assistance by barangay 2025', 'module' => 'assistance'])
            ->assertOk()->assertJsonPath('report', 'barangays')->assertJsonPath('year', 2025);
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_named_barangay_and_veterinary_requests_clarify_without_reading_farmers(): void
    {
        $this->seedOwnRecords();
        DB::enableQueryLog();
        $this->actingAs($this->staff)->postJson(route('assistant.message'), ['message' => 'Show farmers in Barangay San Isidro', 'module' => 'farmers'])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertSee('farm-location (barangay) breakdown')->assertDontSee('registered farmer');
        $this->actingAs($this->user(User::ROLE_PROVINCIAL_VET))->postJson(route('assistant.message'), ['message' => 'Show farmers per barangay', 'module' => 'animal-health'])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertSee('Animal Health')->assertDontSee('San Isidro');
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/from ["`](?:farmers|farm_plots|rice_seed_distributions|harvest_records)["`]/i', $query['query']);
        }
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    private function seedOwnRecords(): void
    {
        $sanIsidro = $this->farmer($this->own, 'San Isidro');
        // Trimmed, case-insensitive grouping; the label is one recorded spelling.
        $this->farmer($this->own, '  San Isidro ');
        $this->farmer($this->own, 'san isidro');
        $poblacion = $this->farmer($this->own, 'Poblacion');
        $this->farmer($this->own, 'Poblacion');
        $unrecorded = $this->farmer($this->own, null);
        foreach (['', '   ', 'UNKNOWN', ' unknown '] as $blank) {
            $this->farmer($this->own, $blank);
        }
        $foreign = $this->farmer($this->foreign, 'ForeignBarangaySecret');
        $this->farmer($this->foreign, 'San Isidro');
        $this->plot($sanIsidro);
        $this->plot($sanIsidro);
        $this->plot($unrecorded);
        $this->plot($foreign);

        $this->release($this->own, $sanIsidro, '2026-02-01');
        $this->release($this->own, $sanIsidro, '2026-06-01');
        $this->release($this->own, $poblacion, '2026-03-01');
        $this->release($this->own, $unrecorded, '2026-04-01');
        $this->release($this->own, null, '2026-05-01');
        $this->release($this->own, $foreign, '2026-05-02');
        $this->release($this->own, $sanIsidro, '2025-07-01');
        $this->release($this->own, $sanIsidro, null);
        $this->release($this->foreign, $foreign, '2026-01-15');

        $this->harvest($this->own, $sanIsidro, 2026);
        $this->harvest($this->own, $poblacion, 2026);
        $this->harvest($this->own, $foreign, 2026);
        $this->harvest($this->own, $sanIsidro, 2025);
        $this->harvest($this->foreign, $foreign, 2026);
    }

    private function read(int $year = 2026): array
    {
        return app(AssistantRecords::class)->answerTask($this->staff, 'barangays', $year);
    }

    private function farmer(Municipality $municipality, ?string $location): int
    {
        return DB::table('farmers')->insertGetId(['municipality_id' => $municipality->id, 'first_name' => 'PrivateGiven',
            'last_name' => 'PrivateFamily'.++$this->sequence, 'contact_number' => 'PRIVATE-CONTACT', 'farm_location' => $location]);
    }

    private function plot(int $farmer): void
    {
        DB::table('farm_plots')->insert(['farmer_id' => $farmer, 'polygon_json' => '[]', 'area_ha' => 1.25]);
    }

    private function release(Municipality $municipality, ?int $farmer, ?string $date): void
    {
        DB::table('rice_seed_distributions')->insert(['municipality_id' => $municipality->id, 'farmer_id' => $farmer,
            'input_category' => 'rice_seed', 'quantity_unit' => 'kg', 'kgs_received' => 9876, 'date_received' => $date]);
    }

    private function harvest(Municipality $municipality, int $farmer, int $year): void
    {
        DB::table('harvest_records')->insert(['municipality_id' => $municipality->id, 'farmer_id' => $farmer,
            'harvest_year' => $year, 'commodity' => 'corn', 'quantity_unit' => 'kg', 'quantity' => 5432]);
    }

    private function municipality(string $name, Province $province): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::create(['name' => $name, 'province' => $province->name, 'province_id' => $province->id, 'is_active' => true]));
    }

    private function user(string $role, array $extra = []): User
    {
        return User::withoutEvents(fn () => User::create(array_replace([
            'name' => 'Synthetic reviewer', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'unused-test-value', 'role' => $role,
            'is_active' => true, 'visitor_mode' => false,
            'municipality_id' => in_array($role, [User::ROLE_MUNICIPAL_STAFF, User::ROLE_MUNICIPAL_HEAD], true) ? $this->own->id : null,
            'province_id' => in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_PROVINCIAL_STAFF, User::ROLE_PROVINCIAL_VET], true) ? $this->own->province_id : null,
            'region_id' => $role === User::ROLE_REGIONAL_HEAD ? Province::findOrFail($this->own->province_id)->region_id : null,
        ], $extra)));
    }
}

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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Answer-quality evaluation through the real office endpoint.
 *
 * Realistic English, Filipino and Taglish office questions run against one
 * synthetic municipality. Each case checks that the reply is the intended
 * local report (or an honest clarification) and that it opens with a plain
 * sentence answering the question. Provider calls are faked and must never
 * happen for records; no fixture value resembles real office data.
 */
class AssistantAnswerQualityTest extends TestCase
{
    use DatabaseTransactions;

    protected Municipality $own;

    protected Municipality $neighbor;

    protected Municipality $foreign;

    /** @var array<string,User> */
    protected array $accounts = [];

    protected int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.enabled' => true, 'cache.default' => 'array', 'session.driver' => 'array', 'ai.providers.openai.key' => '']);
        $this->travelTo(now('UTC')->setDate(2026, 10, 24)->setTime(2, 0));
        $region = Region::withoutEvents(fn () => Region::create(['name' => 'Sample Region', 'code' => 'sample-region', 'is_active' => true]));
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Sample Province', 'region_id' => $region->id, 'is_active' => true]));
        $farProvince = Province::withoutEvents(fn () => Province::create(['name' => 'Far Province', 'is_active' => true]));
        $this->own = $this->municipality('Sample Town', $province);
        $this->neighbor = $this->municipality('Neighbor Town', $province);
        $this->foreign = $this->municipality('Far Town', $farProvince);
        $this->accounts = [
            'staff' => $this->account(User::ROLE_MUNICIPAL_STAFF),
            'provincial' => $this->account(User::ROLE_PROVINCIAL_STAFF),
            'vet' => $this->account(User::ROLE_PROVINCIAL_VET),
        ];
        $this->seedRecords();
        Http::preventStrayRequests();
        OfficeHelpAgent::fake()->preventStrayPrompts();
    }

    /**
     * @return array<string,array{0:list<string>,1:string,2:string,3:list<string>,4?:string}>
     *                                                                                        turns, expected source, expected report, lead fragments, account
     */
    public static function questions(): array
    {
        return [
            'farmer count' => [['How many farmers do we have?'], 'records', 'farmers', ['Sample Town has 8 registered farmers. 4 of them (50.0%) have a saved parcel boundary.']],
            'farmer count Filipino' => [['Ilan ang magsasaka namin?'], 'records', 'farmers', ['Sample Town has 8 registered farmers.']],
            'registered farmers' => [['How many registered farmers are there?'], 'records', 'farmers', ['Sample Town has 8 registered farmers.']],
            'farmers without parcels' => [['How many farmers have no parcel?'], 'records', 'data-quality', ['4 of Sample Town’s 8 registered farmers (50.0%) have no saved parcel yet.']],
            'farmers without parcels Filipino' => [['Ilan ang magsasaka na walang parcel?'], 'records', 'data-quality', ['4 of Sample Town’s 8 registered farmers (50.0%) have no saved parcel yet.']],
            'missing FFRS' => [['Farmers missing FFRS'], 'records', 'data-quality', ['2 of Sample Town’s 8 registered farmers (25.0%) have no FFRS reference recorded.']],
            'missing farm location' => [['How many farmers have no farm location?'], 'records', 'data-quality', ['2 of Sample Town’s 8 registered farmers (25.0%) have no farm location recorded.']],
            'mapped farmers' => [['How many farmers have saved parcels?'], 'records', 'farmers', ['4 of them (50.0%) have a saved parcel boundary.']],
            'fertilizer this year' => [['How much fertilizer did we give out this year?'], 'records', 'assistance', ['In 2026, Sample Town gave out 6 sacks of fertilizer in 2 releases to 2 farmers.']],
            'fertilizer Taglish' => [['Ilan ang nabigyan ng fertilizer this year?'], 'records', 'assistance', ['6 sacks of fertilizer in 2 releases to 2 farmers.']],
            'seed recipients' => [['Who received seeds in 2025?'], 'records', 'assistance', ['In 2025, Sample Town gave out seeds in 2 releases to 2 farmers.']],
            'seeds Filipino' => [['Ilang binhi ang naipamigay natin ngayong taon?'], 'records', 'assistance', ['In 2026, Sample Town gave out seeds in 3 releases to 2 farmers.']],
            'fingerlings' => [['How many fingerlings did we release in 2026?'], 'records', 'assistance', ['In 2026, Sample Town gave out 500 pieces of fish fingerlings in 1 release to 1 farmer.']],
            'beneficiaries' => [['How many beneficiaries?'], 'records', 'assistance', ['4 farmers in Sample Town received assistance in 2026, through 6 releases.']],
            'farmers who received assistance' => [['How many farmers received assistance last year?'], 'records', 'assistance', ['3 farmers in Sample Town received assistance in 2025, through 3 releases.']],
            'assistance year' => [['Show assistance for 2026'], 'records', 'assistance', ['In 2026, Sample Town recorded 6 assistance releases, reaching 4 farmers.']],
            'harvest last year' => [['What did we harvest last year?'], 'records', 'harvests', ['In 2025, Sample Town recorded 2 harvest records, covering corn and rice / palay.']],
            'corn harvest' => [['corn harvest 2026'], 'records', 'harvests', ['In 2026, Sample Town harvested 800 kg of corn, from 2 harvest records.']],
            'rice harvest' => [['How much rice did we harvest in 2026?'], 'records', 'harvests', ['In 2026, Sample Town recorded 2 rice harvest records, saved in more than one unit, so the totals stay separate.']],
            'total harvest' => [['Total harvest this year'], 'records', 'harvests', ['In 2026, Sample Town recorded 4 harvest records']],
            'animals vaccinated' => [['How many animals were vaccinated in 2026?'], 'clarification', 'clarification', ['such as vaccinations only', '“Animal Health summary for 2026”']],
            'animal services' => [['How many animals did we serve this year?'], 'records', 'animal-health', ['In 2026, Sample Town recorded 3 Animal Health service records, covering 4 animal services in total.']],
            'animal summary' => [['Animal Health summary for 2026'], 'records', 'animal-health', ['3 Animal Health service records']],
            'assistance by barangay' => [['Show assistance by barangay'], 'records', 'barangays', ['Sample Town’s 8 registered farmers are spread across 3 recorded farm locations. San Isidro has the most, with 3 farmers.']],
            'farmers per barangay' => [['Farmers per barangay'], 'records', 'barangays', ['spread across 3 recorded farm locations']],
            'LGU summary' => [['Summary of our LGU'], 'records', 'summary', ['Here’s Sample Town at a glance: 8 registered farmers today, with 6 assistance releases and 4 harvest records recorded for 2026.']],
            'LGU records' => [['Summarize our LGU records'], 'records', 'summary', ['Here’s Sample Town at a glance']],
            'cooperatives and members' => [['How many cooperatives and members?'], 'records', 'cooperatives', ['Sample Town has 3 cooperatives on record, with 2 member farmers in total.']],
            'cooperative count' => [['How many cooperatives do we have?'], 'records', 'cooperatives', ['Sample Town has 3 cooperatives on record']],
            'machinery available' => [['Machinery available?'], 'records', 'machinery', ['Sample Town has 4 machinery assets on record: 2 available, 1 in use and 1 under maintenance.']],
            'available machinery question' => [['What machinery is available?'], 'records', 'machinery', ['2 available']],
            'data quality' => [['Data quality'], 'records', 'data-quality', ['Here is what needs attention in Sample Town’s registry of 8 farmer profiles: 4 without a saved parcel, 2 without an FFRS reference and 2 without a farm location.']],
            'registry attention' => [['What needs attention in our registry?'], 'records', 'data-quality', ['Here is what needs attention in Sample Town’s registry']],
            'crop records' => [['Show seasonal crops for 2026'], 'records', 'crops', ['In 2026, Sample Town has 3 seasonal crop records on 2 mapped parcels.']],
            'registry sections' => [['Summarize registry sections'], 'records', 'registry-sources', ['Sample Town’s imported farmer workbook has 6 retained rows: 3 active, 2 Outside LGU and 1 Delisted.']],
            'outside LGU' => [['How many farmers are outside LGU?'], 'records', 'outside-lgu', ['Sample Town’s imported farmer workbook has 2 Outside LGU rows.']],
            'LGU profile' => [['Show LGU profile setup'], 'records', 'lgu-profile', ['Your scope has 1 active LGU office. It doesn’t have a saved LGU profile yet.']],
            'farmer lookup' => [['Find farmer Prueba'], 'records', 'farmer-search', ['I found 1 matching farmer in Sample Town.']],
            'selected farmer' => [['Find farmer Prueba', '@choice'], 'records', 'farmer', ['Juan Prueba (AGRI-F-', 'is registered in Sample Town, Sample Province. They have 1 saved parcel covering 1.25 ha']],
            'selected farmer assistance' => [['Find farmer Prueba', '@choice', 'Show his assistance for 2026'], 'records', 'farmer-assistance', ['In 2026, Juan Prueba (AGRI-F-', ') received 2 assistance releases.']],
            'identity' => [['Who are you?'], 'conversation', 'identity', ['I’m Ka Berto']],
            'capabilities' => [['What can you do?'], 'conversation', 'identity', ['I can answer quick questions about your office records and explain how AgriLGU works.']],
            'named barangay' => [['Show farmers in Barangay San Isidro'], 'clarification', 'clarification', ['for example “Show farmers per barangay”']],
            'gender filter' => [['How many female farmers?'], 'clarification', 'clarification', ['for example “How many farmers have no parcel?”']],
            'comparison' => [['Compare harvests 2025 and 2026'], 'clarification', 'clarification', ['Year comparisons are not available']],
            'unrelated' => [['What is the capital of France?'], 'clarification', 'clarification', ['cannot answer unrelated questions']],
            'provincial farmer count' => [['How many farmers do we have?'], 'records', 'farmers', ['Sample Province has 9 registered farmers. 5 of them (55.6%) have a saved parcel boundary.'], 'provincial'],
            'veterinary animals' => [['How many animals did we serve this year?'], 'records', 'animal-health', ['In 2026, Sample Province recorded 3 Animal Health service records'], 'vet'],
            'veterinary farmer count' => [['How many farmers do we have?'], 'clarification', 'clarification', ['Animal Health'], 'vet'],
        ];
    }

    /** @param list<string> $turns */
    #[DataProvider('questions')]
    public function test_each_question_gets_a_direct_answer(array $turns, string $source, string $report, array $lead, string $account = 'staff'): void
    {
        $response = $this->converse($turns, $account)->assertOk()->assertJsonPath('source', $source)->assertJsonPath('report', $report);
        $answer = (string) $response->json('answer');
        foreach ($lead as $fragment) {
            $this->assertStringContainsString($fragment, $this->lead($answer), implode(' → ', $turns));
        }
        if ($source === 'records') {
            $lead = $this->lead($answer);
            // A plain opening sentence, not the old "Heading · scope" / "Label: value" form.
            $this->assertStringNotContainsString('Scope:', $answer);
            $this->assertStringNotContainsString(' · ', $lead);
            foreach (['Release records:', 'Unique linked beneficiaries', 'Registered farmers:', 'Service records:', 'Harvest records:',
                'Retained rows in this report', 'Asset records:', 'service instances', 'same-municipality farmer link:'] as $jargon) {
                $this->assertStringNotContainsString($jargon, $answer);
            }
            $this->assertStringEndsWith('.', $lead, 'Lead must be complete sentences.');
            $this->assertFalse($this->rendersAsAccidentalList($lead), 'The lead must not be re-rendered as a numbered list.');
            $this->assertLessThanOrEqual(1, substr_count($answer, 'Note:'));
        }
        foreach (['ForeignSecret', 'PRIVATE-', '777', '888', '99 '] as $hidden) {
            $this->assertStringNotContainsString($hidden, $answer);
        }
        $this->assertStringNotContainsString('ForeignSecret', $response->getContent());
        $this->assertLessThan(6000, mb_strlen($answer));
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_year_follow_up_keeps_the_focused_subject_and_never_widens(): void
    {
        $first = $this->converse(['How much fertilizer did we give out this year?'])->assertOk()->assertJsonPath('report', 'assistance');
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $this->postJson(route('assistant.message'), ['message' => 'What about 2025?', 'module' => 'dashboard', 'context' => $first->json('context')])
            ->assertOk()->assertJsonPath('report', 'assistance')->assertJsonPath('year', 2025)
            ->assertSee('In 2025, Sample Town gave out 3 sacks of fertilizer in 1 release to 1 farmer.')
            ->assertDontSee('Rice seed')->assertDontSee('Vegetable seed');
        $this->travel(61)->seconds();
        $corn = $this->converse(['corn harvest 2026'])->assertOk();
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $this->postJson(route('assistant.message'), ['message' => 'last year', 'module' => 'dashboard', 'context' => $corn->json('context')])
            ->assertOk()->assertJsonPath('year', 2025)->assertSee('harvested 500 kg of corn')->assertDontSee('cavans');
        OfficeHelpAgent::assertNeverPrompted();
    }

    public function test_focused_follow_up_chips_are_static_and_resolve_to_the_same_subject(): void
    {
        $resolver = app(AssistantIntentResolver::class);
        foreach (['How much fertilizer did we give out this year?' => 'input:fertilizer', 'Who received seeds in 2025?' => 'seed',
            'How many beneficiaries?' => 'beneficiaries', 'corn harvest 2026' => 'commodity:corn', 'Farmers missing FFRS' => 'missing-ffrs'] as $question => $focus) {
            $response = $this->converse([$question])->assertOk();
            $references = app(AssistantConversationContext::class)->read($this->accounts['staff'], session()->getId(), $response->json('context'));
            $this->assertSame($focus, $references['focus'] ?? null, $question);
            $suggestions = $response->json('suggestions');
            $this->assertNotEmpty($suggestions);
            foreach ($suggestions as $suggestion) {
                foreach (['Sample Town', 'Prueba', 'Ejemplo', 'San Isidro', '6 sacks'] as $private) {
                    $this->assertStringNotContainsString($private, json_encode($suggestion));
                }
                $intent = $resolver->resolve($suggestion['message'], $references, 'dashboard', 2026);
                $this->assertNotSame('clarification', $intent['task'], $suggestion['message']);
                if (str_starts_with($suggestion['label'], 'View ')) {
                    $this->assertSame($focus, $intent['focus'] ?? null, $suggestion['message']);
                    $this->assertSame((int) $response->json('year') - 1, $intent['year']);
                }
            }
            $this->travel(61)->seconds();
        }
    }

    public function test_focused_subjects_with_extra_filters_clarify_without_reading_records(): void
    {
        foreach (['How much fertilizer did we give out to female farmers in 2026?', 'Fertilizer and seeds given out in 2026',
            'Corn harvest in Poblacion 2026', 'How much fertilizer did we give out in March 2026?', 'Farmers without parcels in 2025',
            'How many farmers have no parcel in Barangay San Isidro?', 'How much hybrid rice seed did we release this year?',
            'Rice and corn harvest 2026', 'Corn harvest 2025 and 2026 compared'] as $question) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->converse([$question])->assertOk()->assertJsonPath('source', 'clarification');
            $this->assertDoesNotMatchRegularExpression('/\b(?:gave out|harvested|registered farmers)\b/', (string) $response->json('answer'), $question);
            foreach (DB::getQueryLog() as $query) {
                $this->assertDoesNotMatchRegularExpression('/from ["`](?:farmers|farm_plots|rice_seed_distributions|harvest_records)["`]/i', $query['query'], $question);
            }
            $this->travel(61)->seconds();
        }
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_recipient_questions_give_counts_not_names(): void
    {
        $response = $this->converse(['Who received seeds in 2025?'])->assertOk()->assertJsonPath('actions', []);
        foreach (['Juan', 'Maria', 'Prueba', 'Ejemplo', 'AGRI-F-'] as $name) {
            $this->assertStringNotContainsString($name, $response->getContent());
        }
        $this->assertStringContainsString('not names', $response->json('answer'));
    }

    public function test_selected_farmer_makes_an_unqualified_focused_question_ask_whose_records(): void
    {
        $selected = $this->converse(['Find farmer Prueba', '@choice'])->assertOk()->assertJsonPath('report', 'farmer');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $this->postJson(route('assistant.message'), ['message' => 'Show fertilizer releases for 2026', 'module' => 'dashboard', 'context' => $selected->json('context')])
            ->assertOk()->assertJsonPath('source', 'clarification')->assertJsonPath('context', null)->assertSee('selected farmer or your whole office area');
        $this->assertSame([], array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql) => (bool) preg_match('/from ["`]rice_seed_distributions["`]/i', $sql)));
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $this->postJson(route('assistant.message'), ['message' => 'Show our fertilizer releases for 2026', 'module' => 'dashboard', 'context' => $selected->json('context')])
            ->assertOk()->assertJsonPath('report', 'assistance')->assertSee('gave out 6 sacks of fertilizer');
    }

    public function test_veterinary_accounts_cannot_reach_focused_operational_reports(): void
    {
        DB::enableQueryLog();
        foreach (['How much fertilizer did we give out this year?', 'corn harvest 2026', 'How many farmers have no parcel?', 'How many beneficiaries?'] as $question) {
            DB::flushQueryLog();
            $this->converse([$question], 'vet')->assertOk()->assertJsonPath('source', 'clarification')->assertSee('Animal Health');
            $this->assertSame([], array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql) => (bool) preg_match('/from ["`](?:farmers|farm_plots|rice_seed_distributions|harvest_records)["`]/i', $sql)), $question);
            $this->travel(61)->seconds();
        }
    }

    public function test_tampered_or_mismatched_focus_fails_closed(): void
    {
        $user = $this->accounts['staff'];
        $contexts = app(AssistantConversationContext::class);
        // issue() drops a focus that does not belong to the task or vocabulary.
        foreach ([['assistance', 'commodity:corn'], ['harvests', 'input:fertilizer'], ['assistance', 'input:other_input'], ['farmers', 'unmapped'], ['assistance', 'anything']] as [$task, $focus]) {
            $read = $contexts->read($user, 'synthetic-session', $contexts->issue($user, 'synthetic-session', ['task' => $task, 'year' => 2026, 'focus' => $focus]));
            $this->assertArrayNotHasKey('focus', $read, $task.' '.$focus);
        }
        // A forged token carrying an invalid focus is rejected entirely.
        $now = now('UTC')->timestamp;
        $forged = Crypt::encryptString(json_encode(['version' => 1, 'type' => 'context', 'user' => $user->id, 'session' => hash('sha256', 'synthetic-session'),
            'scope' => $this->scopeHash($user), 'issued' => $now, 'expires' => $now + 900, 'task' => 'assistance', 'year' => 2026, 'farmer_id' => null, 'choices' => [], 'focus' => 'input:rice_seed;drop']));
        $this->assertNull($contexts->read($user, 'synthetic-session', $forged));
        // The record service re-validates any focus it receives.
        try {
            app(AssistantRecords::class)->answerTask($user, 'assistance', 2026, focus: 'commodity:corn');
            $this->fail('A mismatched focus was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    /** Mirrors the browser renderer's inline-sequence rule for a paragraph. */
    protected function rendersAsAccidentalList(string $paragraph): bool
    {
        preg_match_all('/(?:^|\s)([1-9]\d{0,2})[.)]\s+(?=\S)/u', $paragraph, $markers);
        $numbers = array_map('intval', $markers[1]);

        return count($numbers) >= 2 && $numbers === range(1, count($numbers));
    }

    protected function scopeHash(User $user): string
    {
        return hash('sha256', json_encode([$user->role, $user->region_id, $user->province_id, $user->municipality_id,
            $user->municipality?->province_id, (bool) $user->is_active, (bool) $user->visitor_mode], JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $turns */
    protected function converse(array $turns, string $account = 'staff'): TestResponse
    {
        $this->actingAs($this->accounts[$account]);
        $response = null;
        foreach ($turns as $turn) {
            $payload = ['message' => $turn, 'module' => 'dashboard'];
            if ($turn === '@choice') {
                $payload = ['message' => 'Continue with my choice.', 'module' => 'dashboard', 'choice' => $response?->json('actions.0.choice')];
            }
            if ($response !== null && is_string($response->json('context'))) {
                $payload['context'] = $response->json('context');
            }
            $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
            $response = $this->postJson(route('assistant.message'), $payload);
        }

        return $response;
    }

    /** The opening paragraph: everything before the first blank line. */
    protected function lead(string $answer): string
    {
        return explode("\n\n", str_replace("\r\n", "\n", $answer), 2)[0];
    }

    protected function seedRecords(): void
    {
        $f = [
            'juan' => $this->farmer($this->own, 'Juan', 'Prueba', 'FFRS-SYN-1', 'San Isidro'),
            'maria' => $this->farmer($this->own, 'Maria', 'Ejemplo', 'FFRS-SYN-2', 'San Isidro'),
            'pedro' => $this->farmer($this->own, 'Pedro', 'Muestra', null, 'Poblacion'),
            'ana' => $this->farmer($this->own, 'Ana', 'Halimbawa', 'FFRS-SYN-4', 'Poblacion'),
            'jose' => $this->farmer($this->own, 'Jose', 'Sampol', null, null),
            'rosa' => $this->farmer($this->own, 'Rosa', 'Modelo', 'FFRS-SYN-6', 'Bagumbayan'),
            'lito' => $this->farmer($this->own, 'Lito', 'Pagsubok', 'FFRS-SYN-7', 'San Isidro'),
            'nena' => $this->farmer($this->own, 'Nena', 'Huwaran', 'FFRS-SYN-8', 'UNKNOWN'),
        ];
        $neighbor = $this->farmer($this->neighbor, 'Neighbor', 'Fixture', 'FFRS-SYN-N1', 'Centro');
        $foreign = $this->farmer($this->foreign, 'Juan', 'ForeignSecret', 'FFRS-SYN-F1', 'ForeignSecret Barangay');

        $plots = [
            'juan' => $this->plot($f['juan'], 1.25, true),
            'maria' => $this->plot($f['maria'], 0.75, true),
            'pedro' => $this->plot($f['pedro'], 0.5, false),
            'rosa' => $this->plot($f['rosa'], 2.0, true),
        ];
        $this->plot($neighbor, 3.0, true);
        $this->plot($foreign, 9.0, true);

        foreach ([[$plots['juan'], 'dry', 'rice'], [$plots['juan'], 'wet', 'corn'], [$plots['maria'], 'dry', 'rice']] as [$plot, $season, $crop]) {
            DB::table('parcel_crop_seasons')->insert(['farm_plot_id' => $plot, 'municipality_id' => $this->own->id, 'crop_year' => 2026, 'season' => $season, 'crop' => $crop]);
        }

        $this->release($f['juan'], 'rice_seed', 'kg', 20, '2026-02-10');
        $this->release($f['maria'], 'rice_seed', 'kg', 15, '2026-02-11');
        $this->release($f['juan'], 'fertilizer', 'sack', 4, '2026-03-01');
        $this->release($f['pedro'], 'fertilizer', 'sack', 2, '2026-03-02');
        $this->release($f['rosa'], 'fish_fingerlings', 'piece', 500, '2026-05-05');
        $this->release(null, 'corn_seed', 'kg', 5, '2026-06-01');
        $this->release($f['juan'], 'rice_seed', 'kg', 10, '2025-02-10');
        $this->release($f['maria'], 'vegetable_seed', 'pack', 1, '2025-04-01');
        $this->release($f['ana'], 'fertilizer', 'sack', 3, '2025-05-01');
        $this->release($foreign, 'fertilizer', 'sack', 777, '2026-03-01', $this->foreign);

        $this->harvest($f['juan'], 'rice', 'cavan', 40, 2026);
        $this->harvest($f['maria'], 'rice', 'kg', 1200, 2026);
        $this->harvest($f['rosa'], 'corn', 'kg', 800, 2026);
        $this->harvest($f['pedro'], 'corn', 'kg', null, 2026);
        $this->harvest($f['juan'], 'rice', 'cavan', 35, 2025);
        $this->harvest($f['maria'], 'corn', 'kg', 500, 2025);
        $this->harvest($foreign, 'corn', 'kg', 888, 2026, $this->foreign);

        // A zero legacy count is read with the existing one-animal fallback.
        foreach ([[2026, 2], [2026, 1], [2026, 0], [2025, 5]] as [$year, $count]) {
            $this->animalService($this->own, $year, $count);
        }
        $this->animalService($this->foreign, 2026, 99);

        $alpha = $this->cooperative($this->own, 'Synthetic Alpha Cooperative');
        $beta = $this->cooperative($this->own, 'Synthetic Beta Cooperative');
        $this->cooperative($this->own, 'Synthetic Gamma Cooperative');
        foreach ([[$alpha, $f['juan']], [$alpha, $f['maria']], [$beta, $f['maria']]] as [$cooperative, $farmer]) {
            DB::table('cooperative_farmer')->insert(['farmers_cooperative_id' => $cooperative, 'farmer_id' => $farmer]);
        }

        $this->machine(['availability_status' => 'available']);
        $this->machine(['availability_status' => 'available', 'next_maintenance_date' => '2026-11-01']);
        $this->machine(['availability_status' => 'in_use']);
        $this->machine(['availability_status' => 'maintenance']);

        foreach ([['active', $f['juan']], ['active', $f['maria']], ['active', null], ['outside_lgu', $f['rosa']], ['outside_lgu', null], ['delisted', null]] as [$status, $farmer]) {
            $this->source($status, $farmer);
        }
    }

    protected function farmer(Municipality $municipality, string $first, string $last, ?string $ffrs, ?string $location): int
    {
        return DB::table('farmers')->insertGetId(['municipality_id' => $municipality->id, 'first_name' => $first, 'last_name' => $last,
            'ffrs' => $ffrs, 'farm_location' => $location, 'contact_number' => 'PRIVATE-CONTACT-'.++$this->sequence]);
    }

    protected function plot(int $farmer, float $area, bool $geometry): int
    {
        return DB::table('farm_plots')->insertGetId(['farmer_id' => $farmer, 'area_ha' => $area,
            'polygon_json' => $geometry ? '[[15.1,120.1],[15.1,120.2],[15.2,120.2]]' : '[]']);
    }

    protected function release(?int $farmer, string $category, string $unit, float $quantity, string $date, ?Municipality $municipality = null): void
    {
        DB::table('rice_seed_distributions')->insert(['municipality_id' => ($municipality ?? $this->own)->id, 'farmer_id' => $farmer,
            'input_category' => $category, 'quantity_unit' => $unit, 'kgs_received' => $quantity, 'date_received' => $date]);
    }

    protected function harvest(int $farmer, string $commodity, string $unit, ?float $quantity, int $year, ?Municipality $municipality = null): void
    {
        DB::table('harvest_records')->insert(['municipality_id' => ($municipality ?? $this->own)->id, 'farmer_id' => $farmer,
            'commodity' => $commodity, 'quantity_unit' => $unit, 'quantity' => $quantity, 'harvest_year' => $year]);
    }

    protected function animalService(Municipality $municipality, int $year, int $count): void
    {
        DB::table('anti_rabies_vaccinations')->insert(['municipality_id' => $municipality->id, 'owner_name' => 'PRIVATE-OWNER', 'barangay' => 'PRIVATE-BARANGAY',
            'pet_type' => 'Dog', 'vaccination_year' => $year, 'vaccination_date' => $year.'-03-01', 'animal_count' => $count]);
    }

    protected function cooperative(Municipality $municipality, string $name): int
    {
        return DB::table('farmers_cooperatives')->insertGetId(['municipality_id' => $municipality->id, 'name' => $name, 'contact_number' => 'PRIVATE-CONTACT']);
    }

    protected function machine(array $attributes): void
    {
        DB::table('agricultural_machineries')->insert(array_replace(['municipality_id' => $this->own->id, 'asset_code' => 'SYN-'.++$this->sequence,
            'name' => 'Synthetic asset', 'category' => 'tractor', 'availability_status' => 'available', 'condition_status' => 'good', 'notes' => 'PRIVATE-MACHINE-NOTES'], $attributes));
    }

    protected function source(string $status, ?int $farmer): void
    {
        DB::table('farmer_registry_source_rows')->insert(['municipality_id' => $this->own->id, 'farmer_id' => $farmer,
            'source_file_sha256' => str_repeat('b', 64), 'source_sheet' => 'Synthetic sheet', 'source_row' => ++$this->sequence,
            'record_status' => $status, 'payload' => json_encode(['CONTACT NUMBER' => 'PRIVATE-PAYLOAD'])]);
    }

    protected function municipality(string $name, Province $province): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::create(['name' => $name, 'province' => $province->name, 'province_id' => $province->id, 'is_active' => true]));
    }

    protected function account(string $role): User
    {
        return User::withoutEvents(fn () => User::create([
            'name' => 'Synthetic evaluator', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'unused-test-value', 'role' => $role,
            'is_active' => true, 'visitor_mode' => false,
            'municipality_id' => in_array($role, [User::ROLE_MUNICIPAL_STAFF, User::ROLE_MUNICIPAL_HEAD], true) ? $this->own->id : null,
            'province_id' => in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_PROVINCIAL_STAFF, User::ROLE_PROVINCIAL_VET], true) ? $this->own->province_id : null,
        ]));
    }
}

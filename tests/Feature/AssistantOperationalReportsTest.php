<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Modules\Assistant\Infrastructure\OfficeHelpAgent;
use App\Modules\Reporting\Application\AssistantRecords;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssistantOperationalReportsTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private Municipality $own;

    private Municipality $foreign;

    private Farmer $farmer;

    private Farmer $foreignFarmer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.enabled' => true, 'cache.default' => 'array', 'ai.providers.openai.key' => '']);
        $this->travelTo(now('UTC')->setDate(2026, 10, 24)->startOfDay());
        $region = Region::withoutEvents(fn () => Region::create(['name' => 'Synthetic region', 'code' => 'synthetic', 'is_active' => true]));
        $province = Province::withoutEvents(fn () => Province::create(['name' => 'Own province', 'region_id' => $region->id, 'is_active' => true]));
        $foreignProvince = Province::withoutEvents(fn () => Province::create(['name' => 'Other province', 'is_active' => true]));
        $this->own = Municipality::withoutEvents(fn () => Municipality::create(['name' => 'Own LGU', 'province' => $province->name, 'province_id' => $province->id, 'is_active' => true]));
        $this->foreign = Municipality::withoutEvents(fn () => Municipality::create(['name' => 'Foreign LGU', 'province' => $foreignProvince->name, 'province_id' => $foreignProvince->id, 'is_active' => true]));
        $this->staff = $this->user(User::ROLE_MUNICIPAL_STAFF);
        $this->farmer = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->own->id, 'first_name' => 'Own', 'last_name' => 'Farmer']));
        $this->foreignFarmer = Farmer::withoutEvents(fn () => Farmer::create(['municipality_id' => $this->foreign->id, 'first_name' => 'ForeignSecret', 'last_name' => 'Farmer']));
        Http::preventStrayRequests();
        OfficeHelpAgent::fake()->preventStrayPrompts();
    }

    public function test_source_rows_are_counted_separately_from_people_and_never_leak_payloads(): void
    {
        $this->source('active', $this->own->id, $this->farmer->id);
        $this->source('outside_lgu', $this->own->id, $this->farmer->id);
        $this->source('outside_lgu', $this->own->id, $this->farmer->id);
        $this->source('outside_lgu', $this->own->id, $this->foreignFarmer->id);
        $this->source('delisted', $this->own->id, $this->farmer->id);
        $this->source('unexpected', $this->own->id);
        $this->source('outside_lgu', $this->foreign->id, $this->foreignFarmer->id);
        $report = $this->read('registry-sources');
        foreach (['workbook has 6 retained rows: 1 active, 3 Outside LGU and 1 Delisted.', '1 row has a status I don’t recognize',
            'Of the 3 Outside LGU rows, 2 are linked to a farmer registered here (1 distinct farmer), and 1 is not linked.', 'not people'] as $expected) {
            $this->assertStringContainsString($expected, $report['answer']);
        }
        $this->assertStringNotContainsString('PRIVATE-PAYLOAD', json_encode($report));
        $this->assertStringNotContainsString('ForeignSecret', json_encode($report));
        $this->assertCount(2, $report['links']);
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_individual_workbook_reports_apply_the_requested_status(): void
    {
        $this->source('outside_lgu', $this->own->id, $this->farmer->id);
        $this->source('delisted', $this->own->id, $this->farmer->id);
        $this->source('delisted', $this->own->id);
        $outside = $this->read('outside-lgu');
        $delisted = $this->read('delisted');
        $this->assertStringContainsString('workbook has 1 Outside LGU row. Of those, 1 is linked to a farmer registered here', $outside['answer']);
        $this->assertStringNotContainsString('Delisted', $outside['answer']);
        $this->assertStringContainsString('workbook has 2 Delisted rows.', $delisted['answer']);
        $this->assertStringNotContainsString('Outside LGU', $delisted['answer']);
        $this->assertStringNotContainsString('distinct farmer', $delisted['answer']);
        $this->assertSame([], $delisted['actions']);
        $this->assertStringEndsWith('/farmers/registry-sources/delisted', $delisted['links'][0]['url']);
    }

    public function test_cooperatives_only_count_distinct_valid_same_lgu_members(): void
    {
        $one = $this->cooperative($this->own->id);
        $two = $this->cooperative($this->own->id);
        $bad = $this->cooperative($this->own->id);
        $foreign = $this->cooperative($this->foreign->id);
        foreach ([[$one, $this->farmer->id], [$two, $this->farmer->id], [$bad, $this->foreignFarmer->id], [$foreign, $this->foreignFarmer->id]] as [$cooperative, $farmer]) {
            DB::table('cooperative_farmer')->insert(['farmers_cooperative_id' => $cooperative, 'farmer_id' => $farmer]);
        }
        $report = $this->read('cooperatives');
        foreach (['Own LGU has 3 cooperatives on record, with 1 member farmer in total.', '2 cooperatives have at least one member registered here; 1 has none yet.', 'counted once'] as $expected) {
            $this->assertStringContainsString($expected, $report['answer']);
        }
        $this->assertStringNotContainsString('PRIVATE-CONTACT', json_encode($report));
    }

    public function test_machinery_uses_saved_status_and_shared_maintenance_rule_without_private_notes(): void
    {
        $this->machine(['next_maintenance_date' => '2026-11-23']);
        $this->machine(['availability_status' => 'in_use', 'next_maintenance_date' => '2026-11-24']);
        $this->machine(['availability_status' => 'maintenance']);
        $this->machine(['condition_status' => 'unserviceable', 'availability_status' => 'inactive']);
        $this->machine(['availability_status' => 'unexpected']);
        $this->machine(['municipality_id' => $this->foreign->id]);
        $report = $this->read('machinery');
        foreach (['Own LGU has 5 machinery assets on record: 1 available, 1 in use, 1 under maintenance and 1 inactive.', '1 asset has an availability status that needs review.', '3 assets need maintenance attention'] as $expected) {
            $this->assertStringContainsString($expected, $report['answer']);
        }
        $this->assertStringNotContainsString('PRIVATE-MACHINE-NOTES', json_encode($report));
    }

    public function test_crops_count_parcel_seasons_without_inventing_hectares_and_reject_cross_lgu_links(): void
    {
        $ownPlot = $this->plot($this->farmer->id);
        $foreignPlot = $this->plot($this->foreignFarmer->id);
        $this->crop($ownPlot, $this->own->id, 'dry', 'rice');
        $this->crop($ownPlot, $this->own->id, 'wet', 'corn');
        $this->crop($foreignPlot, $this->own->id, 'dry', 'vegetables');
        $this->crop($foreignPlot, $this->foreign->id, 'wet', 'fruit');
        $this->crop($ownPlot, $this->own->id, 'dry', 'legumes', 2025);
        $report = $this->read('crops');
        foreach (['In 2026, Own LGU has 2 seasonal crop records on 1 mapped parcel.', '1 record was left out because the parcel link is missing or belongs to another LGU.', 'Rice / Palay: 1 parcel-season', 'Corn: 1 parcel-season', 'not individual planted sections'] as $expected) {
            $this->assertStringContainsString($expected, $report['answer']);
        }
        foreach (['Vegetables:', 'Fruit:', 'Legumes:', 'PRIVATE-CROP-NOTES', '9999'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($report));
        }
        $this->assertSame('Stated crop year 2026', $report['evidence']['period']);
    }

    public function test_lgu_profiles_only_count_active_scoped_offices_and_do_not_claim_tls_ready(): void
    {
        foreach ([$this->own, $this->foreign] as $municipality) {
            DB::table('municipality_profiles')->insert(['municipality_id' => $municipality->id, 'office_name' => 'Synthetic office', 'logo_path' => 'PRIVATE-LOGO-PATH', 'email' => 'PRIVATE-OFFICE-EMAIL', 'signatory_name' => 'Synthetic signer', 'signatory_title' => 'Head']);
            DB::table('municipality_websites')->insert(['municipality_id' => $municipality->id, 'slug' => 'synthetic-'.$municipality->id, 'headline' => 'Synthetic office website', 'is_published' => true]);
        }
        $report = $this->read('lgu-profile');
        foreach (['Your scope has 1 active LGU office. It has a saved LGU profile.', 'Profiles with an LGU logo: 1', 'signatory name and title: 1', 'Published LGU pages: 1', 'doesn’t confirm'] as $expected) {
            $this->assertStringContainsString($expected, $report['answer']);
        }
        $this->assertStringNotContainsString('PRIVATE-', json_encode($report));
    }

    public function test_missing_optional_table_is_unavailable_never_an_invented_zero(): void
    {
        Schema::drop('farmer_registry_source_rows');
        $report = $this->read('outside-lgu');
        $this->assertStringContainsString('unavailable', $report['answer']);
        $this->assertStringNotContainsString(' 0 ', $report['answer']);
        $this->assertStringNotContainsString('has 0', $report['answer']);
        $this->assertStringContainsString('unavailable', $report['evidence']['sources'][0]['detail']);
    }

    public function test_new_reports_enforce_veterinary_visitor_and_inactive_scope_restrictions(): void
    {
        foreach (['registry-sources', 'outside-lgu', 'delisted', 'cooperatives', 'machinery', 'lgu-profile', 'crops'] as $task) {
            foreach ([$this->user(User::ROLE_PROVINCIAL_VET), $this->user(User::ROLE_SUPER_ADMIN, ['visitor_mode' => true, 'province_id' => null]), $this->user(User::ROLE_MUNICIPAL_STAFF, ['is_active' => false])] as $user) {
                try {
                    app(AssistantRecords::class)->answerTask($user, $task, 2026);
                    $this->fail('Unauthorized report returned: '.$task);
                } catch (AuthorizationException|HttpException $exception) {
                    $this->assertTrue($exception instanceof AuthorizationException || $exception->getStatusCode() === 403);
                }
            }
        }
    }

    public function test_provincial_regional_and_owner_new_reports_use_existing_scope(): void
    {
        $this->source('delisted', $this->own->id);
        $this->source('delisted', $this->foreign->id);
        foreach ([[User::ROLE_SUPER_ADMIN, 1], [User::ROLE_REGIONAL_HEAD, 1], [User::ROLE_SYSTEM_OWNER, 2]] as [$role, $expected]) {
            $report = app(AssistantRecords::class)->answerTask($this->user($role), 'delisted', 2026);
            $this->assertStringContainsString('workbook has '.$expected.' Delisted row', $report['answer']);
        }
    }

    public function test_record_evidence_is_current_scoped_and_uses_fixed_internal_sources(): void
    {
        foreach (['farmers', 'assistance', 'harvests', 'animal-health', 'registry-sources', 'outside-lgu', 'delisted', 'cooperatives', 'machinery', 'lgu-profile', 'crops', 'summary'] as $topic) {
            $report = $this->read($topic);
            $this->assertSame(now('UTC')->toIso8601String(), $report['evidence']['checked_at']);
            $this->assertSame('Own LGU', $report['evidence']['scope']);
            $this->assertNotEmpty($report['evidence']['period']);
            $this->assertNotEmpty($report['evidence']['sources']);
            $this->assertLessThanOrEqual(8, count($report['evidence']['sources']));
            $this->assertLessThanOrEqual(6, count($report['evidence']['limitations']));
            foreach ($report['evidence']['sources'] as $source) {
                $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($source['url'], PHP_URL_HOST));
            }
            $this->assertLessThan(6000, mb_strlen($report['answer']));
        }
        OfficeHelpAgent::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_repeated_questions_read_newly_saved_data_instead_of_a_stale_cached_answer(): void
    {
        $this->assertStringContainsString('workbook has 0 Delisted rows.', $this->read('delisted')['answer']);
        $this->source('delisted', $this->own->id);
        $this->assertStringContainsString('workbook has 1 Delisted row.', $this->read('delisted')['answer']);
    }

    public function test_large_breakdowns_are_bounded_and_overview_remains_displayable(): void
    {
        foreach (array_keys(RiceSeedDistribution::INPUT_CATEGORY_LABELS) as $category) {
            foreach (array_keys(RiceSeedDistribution::QUANTITY_UNIT_LABELS) as $unit) {
                DB::table('rice_seed_distributions')->insert(['municipality_id' => $this->own->id, 'farmer_id' => $this->farmer->id,
                    'input_category' => $category, 'quantity_unit' => $unit, 'kgs_received' => 999999, 'date_received' => '2026-01-01']);
            }
        }
        foreach (array_keys(HarvestRecord::COMMODITY_LABELS) as $commodity) {
            foreach (array_keys(HarvestRecord::QUANTITY_UNIT_LABELS) as $unit) {
                DB::table('harvest_records')->insert(['municipality_id' => $this->own->id, 'farmer_id' => $this->farmer->id,
                    'harvest_year' => 2026, 'commodity' => $commodity, 'quantity_unit' => $unit, 'quantity' => 999999]);
            }
        }
        $this->own->withoutEvents(fn () => $this->own->update(['name' => str_repeat('Synthetic ', 18)]));
        // The overview is one line per module; the dedicated reports carry the bounded breakdowns.
        $report = $this->read('summary');
        $this->assertStringNotContainsString('999', $report['answer']);
        $this->assertLessThan(2000, mb_strlen($report['answer']));
        $this->assertCount(8, $report['evidence']['sources']);
        $assistance = $this->read('assistance');
        $this->assertStringContainsString('first 10 quantity groups', $assistance['answer']);
        $this->assertSame(10, preg_match_all('/^\d+\. /m', $assistance['answer']));
        $this->assertLessThan(6000, mb_strlen($assistance['answer']));
        $harvests = $this->read('harvests');
        $this->assertStringContainsString('first 10 commodity/unit groups', $harvests['answer']);
        $this->assertSame(10, preg_match_all('/^\d+\. /m', $harvests['answer']));
        $this->assertLessThan(6000, mb_strlen($harvests['answer']));
    }

    private function read(string $task): array
    {
        return app(AssistantRecords::class)->answerTask($this->staff, $task, 2026);
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

    private function source(string $status, int $municipality, ?int $farmer = null): void
    {
        DB::table('farmer_registry_source_rows')->insert(['municipality_id' => $municipality, 'farmer_id' => $farmer,
            'source_file_sha256' => str_repeat('a', 64), 'source_sheet' => 'Synthetic sheet', 'source_row' => DB::table('farmer_registry_source_rows')->count() + 2,
            'record_status' => $status, 'payload' => json_encode(['CONTACT NUMBER' => 'PRIVATE-PAYLOAD'])]);
    }

    private function cooperative(int $municipality): int
    {
        return DB::table('farmers_cooperatives')->insertGetId(['municipality_id' => $municipality, 'name' => 'Synthetic cooperative', 'contact_number' => 'PRIVATE-CONTACT']);
    }

    private function machine(array $attributes = []): void
    {
        DB::table('agricultural_machineries')->insert(array_replace(['municipality_id' => $this->own->id, 'asset_code' => 'SYNTHETIC-'.DB::table('agricultural_machineries')->count(),
            'name' => 'Synthetic asset', 'category' => 'tractor', 'availability_status' => 'available', 'condition_status' => 'good', 'notes' => 'PRIVATE-MACHINE-NOTES'], $attributes));
    }

    private function plot(int $farmer): int
    {
        return DB::table('farm_plots')->insertGetId(['farmer_id' => $farmer, 'polygon_json' => '[]', 'area_ha' => 9999]);
    }

    private function crop(int $plot, int $municipality, string $season, string $crop, int $year = 2026): void
    {
        DB::table('parcel_crop_seasons')->insert(['farm_plot_id' => $plot, 'municipality_id' => $municipality, 'crop_year' => $year, 'season' => $season, 'crop' => $crop, 'notes' => 'PRIVATE-CROP-NOTES']);
    }
}

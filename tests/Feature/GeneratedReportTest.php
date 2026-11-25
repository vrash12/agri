<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\GeneratedReport;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Modules\Reporting\Application\PeriodReports;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * The in-app period report archive and the comparison download.
 *
 * What matters: figures stay inside the account's area, a stored report is
 * reopened only by an account that can still see every municipality it covers,
 * the monthly run is idempotent, and reports hold aggregates — never names.
 */
class GeneratedReportTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $home;

    private Municipality $sibling;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(9, 0));
        $this->home = $this->municipality('Report Home');
        $this->sibling = $this->municipality('Report Sibling');
    }

    public function test_municipal_staff_generate_an_own_area_report_with_period_figures_only(): void
    {
        $staff = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->home);
        $served = $this->farmer($this->home, 'Uniquely Namedperson');
        $this->farmer($this->home, 'Second Local');
        $foreign = $this->farmer($this->sibling, 'Foreign Grower');

        $this->release($this->home, $served, '2026-10-05', 'rice_seed', 'kg', 40);
        $this->release($this->home, $served, '2026-10-20', 'fertilizer', 'bag', 3);
        $this->release($this->home, $served, '2026-09-30', 'rice_seed', 'kg', 99); // previous month
        $this->release($this->sibling, $foreign, '2026-10-10', 'rice_seed', 'kg', 500);
        $this->harvest($this->home, 'corn', 'ton', 2.5, '2026-10-12');
        $this->harvest($this->home, 'corn', 'kg', 300, '2026-10-13');
        $this->harvest($this->home, 'rice', 'sack', 80, null);
        DB::table('anti_rabies_vaccinations')->insert([
            ['municipality_id' => $this->home->id, 'owner_name' => 'Synthetic Owner', 'barangay' => 'Synthetic Barangay', 'pet_type' => 'Dog', 'animal_count' => 4, 'service_type' => 'vaccination', 'service_name' => 'Synthetic vaccine', 'vaccination_date' => '2026-10-02'],
            ['municipality_id' => $this->sibling->id, 'owner_name' => 'Synthetic Owner', 'barangay' => 'Synthetic Barangay', 'pet_type' => 'Dog', 'animal_count' => 9, 'service_type' => 'vaccination', 'service_name' => 'Synthetic vaccine', 'vaccination_date' => '2026-10-02'],
        ]);

        $response = $this->actingAs($staff)->post(route('reports.store'), [
            'period_type' => 'month', 'year' => 2026, 'month' => 10,
            // A municipal account cannot widen the report by naming another office.
            'municipality_id' => $this->sibling->id,
        ]);
        $response->assertSessionHasErrors('municipality_id');
        $this->assertSame(0, GeneratedReport::query()->count());

        $response = $this->actingAs($staff)->post(route('reports.store'), ['period_type' => 'month', 'year' => 2026, 'month' => 10]);
        $report = GeneratedReport::query()->sole();
        $response->assertRedirect(route('reports.show', $report));

        $this->assertSame('municipality', $report->scope_type);
        $this->assertSame([$this->home->id], $report->municipality_ids);
        $totals = $report->summary['totals'];
        $this->assertSame(2, $totals['registry']['farmers']);
        $this->assertSame(2, $totals['assistance']['releases']);
        $this->assertSame(1, $totals['assistance']['beneficiaries']);
        $this->assertSame(40.0, (float) $totals['assistance']['categories']['rice_seed']['quantities']['kg']);
        $this->assertSame(3.0, (float) $totals['assistance']['categories']['fertilizer']['quantities']['bag']);
        // Tons and kilograms of corn stay separate; the undated rice is counted, not placed.
        $this->assertSame(2.5, (float) $totals['harvests']['production']['corn|ton']['quantity']);
        $this->assertSame(300.0, (float) $totals['harvests']['production']['corn|kg']['quantity']);
        $this->assertSame(1, $totals['harvests']['undated']);
        $this->assertSame(1, $totals['animal_health']['services']);
        $this->assertSame(4, $totals['animal_health']['animals']);
        $this->assertSame([], $report->summary['breakdown']);
        $this->assertFalse($report->summary['partial']);

        $this->assertStringNotContainsString('Namedperson', json_encode($report->summary));
        $this->actingAs($staff)->get(route('reports.show', $report))->assertOk()->assertSee('Report Home', false)->assertDontSee('Namedperson');
        $download = $this->actingAs($staff)->get(route('reports.download', $report));
        $download->assertOk();
        $csv = $download->streamedContent();
        $this->assertStringContainsString('Registered farmers', $csv);
        $this->assertStringNotContainsString('Namedperson', $csv);
        $this->assertTrue(AuditLog::query()->where('event', 'generated')->where('module', 'Reports')->exists());
    }

    public function test_a_report_is_reopened_only_by_accounts_that_see_every_covered_municipality(): void
    {
        $provincial = $this->user(User::ROLE_SUPER_ADMIN, null);
        $homeStaff = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->home);
        $siblingStaff = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->sibling);
        $this->farmer($this->home, 'Area One');
        $this->farmer($this->sibling, 'Area Two');

        $this->actingAs($provincial)->post(route('reports.store'), ['period_type' => 'quarter', 'year' => 2026, 'quarter' => 3])->assertRedirect();
        $province = GeneratedReport::query()->where('scope_type', 'province')->sole();
        $this->assertCount(2, $province->summary['breakdown']);
        $this->assertNotContains('rank', array_keys($province->summary['breakdown'][0]));

        $this->actingAs($provincial)->post(route('reports.store'), ['period_type' => 'year', 'year' => 2026, 'municipality_id' => $this->sibling->id])->assertRedirect();
        $siblingReport = GeneratedReport::query()->where('scope_type', 'municipality')->sole();
        $this->assertTrue($siblingReport->summary['partial']);

        $this->actingAs($homeStaff)->get(route('reports.show', $province))->assertForbidden();
        $this->actingAs($homeStaff)->get(route('reports.show', $siblingReport))->assertForbidden();
        $this->actingAs($homeStaff)->get(route('reports.download', $siblingReport))->assertForbidden();
        $this->actingAs($homeStaff)->get(route('reports.index'))->assertOk()->assertDontSee($siblingReport->title);
        $this->actingAs($siblingStaff)->get(route('reports.index'))->assertOk()->assertSee($siblingReport->title)->assertDontSee($province->title);
        $this->actingAs($provincial)->get(route('reports.show', $siblingReport))->assertOk();
    }

    public function test_future_periods_and_restricted_roles_are_refused(): void
    {
        $staff = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->home);
        $this->actingAs($staff)->post(route('reports.store'), ['period_type' => 'month', 'year' => 2026, 'month' => 12])
            ->assertSessionHasErrors('year');

        $vet = $this->user(User::ROLE_PROVINCIAL_VET, null);
        $this->actingAs($vet)->get(route('reports.index'))->assertRedirect(route('anti-rabies-vaccinations.index'));
        $this->assertSame(0, GeneratedReport::query()->count());
    }

    public function test_the_monthly_run_archives_each_office_and_province_once(): void
    {
        $this->farmer($this->home, 'Monthly One');
        $before = GeneratedReport::query()->count();

        $this->assertSame(0, Artisan::call('reports:generate-monthly'));
        $created = GeneratedReport::query()->where('source', GeneratedReport::SOURCE_SCHEDULED)->count();
        $this->assertGreaterThanOrEqual(3, $created - $before);
        $homeReport = GeneratedReport::query()->where('scope_type', 'municipality')->where('municipality_id', $this->home->id)->sole();
        $this->assertSame('2026-10-01', $homeReport->period_start->toDateString());
        $this->assertSame('2026-10-31', $homeReport->period_end->toDateString());
        $this->assertSame(1, $homeReport->summary['totals']['registry']['farmers']);

        $this->assertSame(0, Artisan::call('reports:generate-monthly'));
        $this->assertSame($created, GeneratedReport::query()->where('source', GeneratedReport::SOURCE_SCHEDULED)->count());
        $this->assertNotSame(0, Artisan::call('reports:generate-monthly', ['--month' => '2026-11']));
    }

    public function test_comparison_export_matches_scope_and_requires_two_municipalities(): void
    {
        $provincial = $this->user(User::ROLE_SUPER_ADMIN, null);
        $staff = $this->user(User::ROLE_MUNICIPAL_STAFF, $this->home);
        $this->farmer($this->home, 'Export Person');

        $response = $this->actingAs($provincial)->get(route('dashboard.comparison.export', ['report_year' => 2026]));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Report Home', $csv);
        $this->assertStringContainsString('Assistance reach 2026', $csv);
        $this->assertStringNotContainsString('Export Person', $csv);
        $this->assertTrue(AuditLog::query()->where('event', 'exported')->where('module', 'Dashboard')->exists());

        $this->actingAs($staff)->get(route('dashboard.comparison.export'))->assertNotFound();
    }

    public function test_registration_counts_use_local_month_boundaries_for_utc_timestamps(): void
    {
        config(['app.display_timezone' => 'Asia/Manila']);
        foreach (['2026-09-30 15:59:59', '2026-09-30 16:00:00', '2026-10-31 15:59:59', '2026-10-31 16:00:00'] as $created) {
            $farmer = $this->farmer($this->home, 'Boundary Person');
            DB::table('farmers')->where('id', $farmer->id)->update(['created_at' => $created]);
        }
        $service = app(PeriodReports::class);
        $report = $service->generate($this->user(User::ROLE_MUNICIPAL_STAFF, $this->home), 'month', 2026, 10, null);
        $this->assertSame(2, $report->summary['totals']['registry']['new_farmers']);
    }

    public function test_scheduled_reports_skip_inactive_provinces(): void
    {
        DB::table('provinces')->where('id', $this->home->province_id)->update(['is_active' => false]);
        $this->assertSame(0, Artisan::call('reports:generate-monthly'));
        $this->assertSame(0, GeneratedReport::query()->where('municipality_id', $this->home->id)->count());
    }

    private function municipality(string $label): Municipality
    {
        $suffix = str_replace('.', '', uniqid('', true));

        return Municipality::create([
            'name' => $label.' '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'GR'.substr($suffix, -8),
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?Municipality $municipality): User
    {
        return User::create([
            'name' => 'Report '.$role,
            'email' => $role.'-'.str_replace('.', '', uniqid('', true)).'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'municipality_id' => $municipality?->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);
    }

    private function farmer(Municipality $municipality, string $name): Farmer
    {
        [$first, $last] = explode(' ', $name, 2);

        return Farmer::create(['municipality_id' => $municipality->id, 'first_name' => $first, 'last_name' => $last, 'farm_location' => 'Synthetic Barangay']);
    }

    private function release(Municipality $municipality, Farmer $farmer, string $date, string $category, string $unit, float $quantity): void
    {
        RiceSeedDistribution::create([
            'municipality_id' => $municipality->id, 'farmer_id' => $farmer->id, 'input_category' => $category,
            'quantity_unit' => $unit, 'kgs_received' => $quantity, 'date_received' => $date,
            'last_name' => $farmer->last_name, 'first_name' => $farmer->first_name,
        ]);
    }

    private function harvest(Municipality $municipality, string $commodity, string $unit, float $quantity, ?string $date): void
    {
        HarvestRecord::create([
            'municipality_id' => $municipality->id, 'commodity' => $commodity, 'harvest_year' => 2026,
            'date_harvested' => $date, 'quantity' => $quantity, 'quantity_unit' => $unit,
        ]);
    }
}

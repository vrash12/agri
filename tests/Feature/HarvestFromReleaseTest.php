<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\DashboardMetrics;
use App\Support\HarvestFromRelease;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * The Rice Seed Distribution Sheet's production section, projected into a harvest.
 *
 * Staff already fill this section in on paper, so asking them to enter the same
 * harvest a second time to make it appear on a chart would be the surest way to have
 * it never entered at all. Saving the release writes the harvest record.
 *
 * The behaviour worth pinning is that this stays a projection and never becomes a
 * second, drifting copy: one release owns at most one harvest record, editing the
 * release moves that record, and clearing the production section takes it away
 * rather than leaving a figure the sheet no longer claims.
 */
class HarvestFromReleaseTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $municipality;

    private User $staff;

    private Farmer $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));

        $this->municipality = Municipality::create([
            'name' => 'Harvest Test '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'HV'.substr($suffix, -8),
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Harvest Staff',
            'email' => 'harvest-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);

        $this->farmer = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Harvest',
            'last_name' => 'Recipient',
            'farm_location' => 'Barangay Uno',
        ]);
    }

    public function test_a_recorded_harvest_becomes_a_harvest_record(): void
    {
        $release = $this->release([
            'harvest_season' => 'dry',
            'harvest_year' => 2026,
            'total_production_bags' => 120,
            'avg_weight_per_bag_kg' => 50,
            'avg_area_harvested_ha' => 1.75,
            'seed_variety_planted' => 'NSIC Rc 222',
        ]);

        $record = app(HarvestFromRelease::class)->sync($release);

        $this->assertNotNull($record);
        $this->assertSame('rice', $record->commodity);
        $this->assertSame(2026, $record->harvest_year);
        $this->assertSame('dry', $record->season);
        $this->assertSame('NSIC Rc 222', $record->variety);
        $this->assertSame($this->municipality->id, $record->municipality_id);
        $this->assertSame($this->farmer->id, $record->farmer_id);

        // Bags, because bags are what the sheet counts. Multiplying by an average bag
        // weight would turn an observation into an estimate without saying so.
        $this->assertSame('120.000', (string) $record->quantity);
        $this->assertSame('bag', $record->quantity_unit);

        // The bag weight is not lost; it is recorded as the estimate it is.
        $this->assertStringContainsString('50.00 kg', $record->notes);
        $this->assertStringContainsString('estimate', $record->notes);
    }

    public function test_a_release_with_no_harvest_yet_projects_nothing(): void
    {
        // A planted variety is a plan, not a harvest. Projecting it would put a zero
        // into the production chart for a field nobody has cut.
        $release = $this->release(['seed_variety_planted' => 'NSIC Rc 222', 'harvest_year' => 2026]);

        $this->assertNull(app(HarvestFromRelease::class)->sync($release));
        $this->assertSame(0, HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->count());
    }

    public function test_editing_the_release_moves_the_same_record_rather_than_adding_another(): void
    {
        $release = $this->release([
            'harvest_season' => 'dry', 'harvest_year' => 2026, 'total_production_bags' => 120,
        ]);

        $sync = app(HarvestFromRelease::class);
        $first = $sync->sync($release);

        $release->update(['total_production_bags' => 145, 'harvest_season' => 'wet']);
        $second = $sync->sync($release->fresh());

        $this->assertSame($first->id, $second->id, 'A second harvest record was created for one release.');
        $this->assertSame('145.000', (string) $second->quantity);
        $this->assertSame('wet', $second->season);
        $this->assertSame(1, HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->count());
    }

    public function test_clearing_the_production_section_removes_the_record(): void
    {
        $release = $this->release(['harvest_year' => 2026, 'total_production_bags' => 120]);
        $sync = app(HarvestFromRelease::class);
        $sync->sync($release);

        $this->assertSame(1, HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->count());

        // The sheet no longer claims this harvest, so neither should the chart.
        $release->update(['total_production_bags' => null]);
        $this->assertNull($sync->sync($release->fresh()));
        $this->assertSame(0, HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->count());
    }

    public function test_saving_a_release_through_the_form_projects_the_harvest(): void
    {
        $this->actingAs($this->staff)
            ->post(route('rice-seed-distributions.store'), [
                'farmer_id' => $this->farmer->id,
                'input_category' => 'rice_seed',
                'quantity_unit' => 'kg',
                'kgs_received' => 40,
                'seed_variety_claimed' => 'LP 937',
                'date_received' => '2026-01-20',
                'harvest_season' => 'dry',
                'harvest_year' => 2026,
                'total_production_bags' => 95,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rice-seed-distributions.index'));

        $release = RiceSeedDistribution::query()->where('farmer_id', $this->farmer->id)->latest('id')->firstOrFail();
        $record = HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->firstOrFail();

        $this->assertSame('95.000', (string) $record->quantity);
        $this->assertSame(2026, $record->harvest_year);
    }

    public function test_deleting_the_release_takes_its_harvest_with_it(): void
    {
        $release = $this->release(['harvest_year' => 2026, 'total_production_bags' => 120]);
        app(HarvestFromRelease::class)->sync($release);

        $this->actingAs($this->staff)
            ->delete(route('rice-seed-distributions.destroy', $release))
            ->assertRedirect();

        $this->assertSame(
            0,
            HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->count(),
            'A harvest outlived the release it was projected from.'
        );
    }

    public function test_a_harvest_entered_directly_is_untouched_by_release_syncing(): void
    {
        // Commodities no seed sheet covers are entered on their own and must not be
        // disturbed by anything happening to a rice release.
        $standalone = HarvestRecord::create([
            'municipality_id' => $this->municipality->id,
            'commodity' => 'corn',
            'harvest_year' => 2026,
            'quantity' => 300,
            'quantity_unit' => 'sack',
        ]);

        $release = $this->release(['harvest_year' => 2026, 'total_production_bags' => 120]);
        app(HarvestFromRelease::class)->sync($release);

        $standalone->refresh();
        $this->assertSame('corn', $standalone->commodity);
        $this->assertSame('300.000', (string) $standalone->quantity);
        $this->assertNull($standalone->rice_seed_distribution_id);
    }

    public function test_the_projected_harvest_reaches_the_production_chart(): void
    {
        // The point of the projection: a figure written on the seed sheet shows up in
        // the production trend without anybody entering it a second time.
        $release = $this->release(['harvest_year' => 2026, 'total_production_bags' => 120]);
        app(HarvestFromRelease::class)->sync($release);

        $metric = app(DashboardMetrics::class)->productionByCommodity($this->staff);

        $this->assertSame(['2026'], $metric['labels']);
        $this->assertCount(1, $metric['series']);
        $this->assertSame('Rice / Palay', $metric['series'][0]['name']);
        $this->assertSame('Bags', $metric['series'][0]['unit']);
        $this->assertSame([120.0], $metric['series'][0]['values']);
    }

    public function test_the_backfill_command_projects_production_recorded_before_the_wiring_existed(): void
    {
        // These rows are what the register looked like before saving a release wrote a
        // harvest. Nobody is going to re-open them, so the command has to reach them.
        $reported = $this->release(['harvest_year' => 2025, 'total_production_bags' => 88]);
        $planOnly = $this->release(['harvest_year' => 2025, 'seed_variety_planted' => 'NSIC Rc 160']);

        $this->artisan('harvests:backfill-from-releases', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(
            0,
            HarvestRecord::query()->where('rice_seed_distribution_id', $reported->id)->count(),
            'A dry run wrote a harvest record.'
        );

        $this->artisan('harvests:backfill-from-releases')->assertSuccessful();
        $this->assertSame(1, HarvestRecord::query()->where('rice_seed_distribution_id', $reported->id)->count());
        $this->assertSame(0, HarvestRecord::query()->where('rice_seed_distribution_id', $planOnly->id)->count());

        // Run twice, because it will be: once on deployment and again by someone who
        // is not sure whether the first run took.
        $this->artisan('harvests:backfill-from-releases')->assertSuccessful();
        $this->assertSame(1, HarvestRecord::query()->where('rice_seed_distribution_id', $reported->id)->count());
    }

    public function test_the_production_section_opens_for_a_release_that_already_reports_one(): void
    {
        // A collapsed section reads as an empty one. Someone checking a figure they
        // entered last season should see it without hunting for the toggle.
        //
        // Season and year alone, deliberately: the bag and area fields were already
        // enough to expand the section, and these two were not, so a release whose
        // harvest period had been recorded looked as though nothing had been.
        $release = $this->release(['harvest_season' => 'wet', 'harvest_year' => 2025]);

        $filled = $this->actingAs($this->staff)
            ->get(route('rice-seed-distributions.edit', $release))
            ->assertOk()
            ->assertSee('what the production report reads', false)
            ->getContent();

        $this->assertTrue($this->productionSectionIsOpen($filled), 'The recorded harvest was hidden behind a collapsed section.');

        $blank = $this->actingAs($this->staff)
            ->get(route('rice-seed-distributions.edit', $this->release([])))
            ->assertOk()
            ->getContent();

        $this->assertFalse(
            $this->productionSectionIsOpen($blank),
            'The production section was expanded on a release with nothing to show in it.'
        );
    }

    /**
     * Whether the rendered production section carries the open attribute.
     *
     * Read off the tag itself rather than by searching for a literal string, because
     * Blade leaves whatever whitespace the conditional put there and a substring check
     * would quietly pass either way.
     */
    private function productionSectionIsOpen(string $html): bool
    {
        $this->assertSame(
            1,
            preg_match('/<details[^>]*id="riceProductionMonitoring"[^>]*>/', $html, $tag),
            'The production section was not rendered at all.'
        );

        return (bool) preg_match('/\sopen[\s>]/', $tag[0]);
    }

    public function test_the_spreadsheet_import_projects_the_production_columns_it_carries(): void
    {
        // The workbook has the production columns in it, so an import is a bulk way of
        // recording harvest. Wiring only the form would leave those figures sitting in
        // the register, entered and unreported.
        $path = $this->workbook([
            'FFRS RSBSA Number' => '11-11-11-111-000001',
            'Farmer Last Name' => 'Recipient',
            'Farmer First Name' => 'Harvest',
            'Seed Variety Claimed' => 'NSIC Rc 216',
            'Seed Variety Planted' => 'NSIC Rc 216',
            'Total Production (no. of bags) - for all variety(ies)' => 64,
            'Average Weight per Bag (kg) - for all variety(ies)' => 50,
            'Average Area Harvested (ha)' => 1.25,
        ]);

        $this->actingAs($this->staff)
            ->post(route('rice-seed-distributions.import'), [
                'file' => new UploadedFile($path, 'nrp.xlsx', null, null, true),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rice-seed-distributions.index'));

        $release = RiceSeedDistribution::query()
            ->where('municipality_id', $this->municipality->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(64, $release->total_production_bags);

        // The workbook has no harvest-year column, so the import has nothing to report
        // the figure in and must not invent one. The bags stay on the release until a
        // year is entered, at which point saving projects them.
        $this->assertNull($release->harvest_year);
        $this->assertSame(0, HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->count());

        $release->update(['harvest_year' => 2025]);
        app(HarvestFromRelease::class)->sync($release->fresh());

        $record = HarvestRecord::query()->where('rice_seed_distribution_id', $release->id)->firstOrFail();
        $this->assertSame('64.000', (string) $record->quantity);
        $this->assertSame('1.2500', (string) $record->area_harvested_ha);
    }

    /**
     * A one-row NRP workbook, removed again when the test finishes.
     *
     * @param  array<string, mixed>  $row
     */
    private function workbook(array $row): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('NRP DISTRIBUTION');
        $sheet->fromArray([array_keys($row), array_values($row)], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'nrp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function release(array $attributes = []): RiceSeedDistribution
    {
        return RiceSeedDistribution::create(array_merge([
            'municipality_id' => $this->municipality->id,
            'farmer_id' => $this->farmer->id,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'kgs_received' => 40,
            'date_received' => '2026-01-20',
        ], $attributes));
    }
}

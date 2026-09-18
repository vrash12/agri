<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\ConcurrentWrite;
use App\Support\HarvestFromRelease;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * Recording a harvest directly, for the commodities no seed sheet covers.
 *
 * The production report could already read harvests; until this module there was no
 * way to write one for corn, vegetables, fisheries or livestock. What is worth
 * pinning here is the isolation (a harvest must not name another municipality's
 * farmer) and the rule that a harvest projected from an assistance release is changed
 * at that release and nowhere else.
 */
class HarvestRecordModuleTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $municipality;

    private Municipality $sibling;

    private User $staff;

    private Farmer $farmer;

    private Farmer $foreignFarmer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = $this->makeMunicipality('Harvest Home');
        $this->sibling = $this->makeMunicipality('Harvest Next Door');

        $suffix = str_replace('.', '', uniqid('', true));

        $this->staff = User::create([
            'name' => 'Harvest Clerk',
            'email' => 'clerk-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);

        $this->farmer = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Local',
            'last_name' => 'Grower',
            'farm_location' => 'Barangay Uno',
        ]);

        $this->foreignFarmer = Farmer::create([
            'municipality_id' => $this->sibling->id,
            'first_name' => 'Elsewhere',
            'last_name' => 'Grower',
            'farm_location' => 'Barangay Dos',
        ]);
    }

    public function test_a_harvest_can_be_recorded_for_a_commodity_no_seed_sheet_covers(): void
    {
        $this->actingAs($this->staff)
            ->post(route('harvest-records.store'), [
                'commodity' => 'corn',
                'variety' => 'IPB Var 6',
                'quantity' => 420.5,
                'quantity_unit' => 'sack',
                'harvest_year' => 2026,
                'season' => 'dry',
                'farmer_id' => $this->farmer->id,
                'area_harvested_ha' => 2.5,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record = HarvestRecord::query()->where('commodity', 'corn')->latest('id')->firstOrFail();

        $this->assertSame('420.500', (string) $record->quantity);
        $this->assertSame('sack', $record->quantity_unit);
        $this->assertSame($this->municipality->id, $record->municipality_id);
        $this->assertSame($this->staff->id, $record->recorded_by);
        $this->assertFalse($record->isProjected());
    }

    public function test_the_owning_municipality_comes_from_the_farmer_not_from_the_form(): void
    {
        // Posting another municipality's id must not move the record there. A harvest
        // owned by one office while naming another's farmer would be visible to an
        // office the farmer does not belong to.
        $this->actingAs($this->staff)
            ->post(route('harvest-records.store'), [
                'commodity' => 'rice',
                'quantity' => 100,
                'quantity_unit' => 'kg',
                'harvest_year' => 2026,
                'farmer_id' => $this->farmer->id,
                'municipality_id' => $this->sibling->id,
            ])
            ->assertSessionHasNoErrors();

        $record = HarvestRecord::query()->where('farmer_id', $this->farmer->id)->latest('id')->firstOrFail();

        $this->assertSame($this->municipality->id, $record->municipality_id);
    }

    public function test_a_farmer_from_another_municipality_is_refused(): void
    {
        $this->actingAs($this->staff)
            ->post(route('harvest-records.store'), [
                'commodity' => 'rice',
                'quantity' => 100,
                'quantity_unit' => 'kg',
                'harvest_year' => 2026,
                'farmer_id' => $this->foreignFarmer->id,
            ])
            ->assertSessionHasErrors('farmer_id');

        $this->assertSame(0, HarvestRecord::query()->where('farmer_id', $this->foreignFarmer->id)->count());
    }

    public function test_a_parcel_belonging_to_a_different_farmer_is_refused(): void
    {
        $otherFarmer = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Second',
            'last_name' => 'Grower',
            'farm_location' => 'Barangay Tres',
        ]);

        $plot = FarmPlot::create([
            'farmer_id' => $otherFarmer->id,
            'name' => 'Not this farmer\'s parcel',
            'polygon_json' => [
                ['lat' => 15.64, 'lng' => 120.61],
                ['lat' => 15.65, 'lng' => 120.61],
                ['lat' => 15.65, 'lng' => 120.62],
                ['lat' => 15.64, 'lng' => 120.62],
            ],
            'area_ha' => 1.0,
            'centroid_lat' => 15.645,
            'centroid_lng' => 120.615,
        ]);

        $this->actingAs($this->staff)
            ->post(route('harvest-records.store'), [
                'commodity' => 'rice',
                'quantity' => 100,
                'quantity_unit' => 'kg',
                'harvest_year' => 2026,
                'farmer_id' => $this->farmer->id,
                'farm_plot_id' => $plot->id,
            ])
            ->assertSessionHasErrors('farm_plot_id');
    }

    public function test_a_harvest_with_no_farmer_is_allowed_and_owned_by_the_account(): void
    {
        // A municipality reporting its corn for a season has a real figure and no
        // single farmer to attach it to. Refusing it would push the number into a
        // spreadsheet the system never sees.
        $this->actingAs($this->staff)
            ->post(route('harvest-records.store'), [
                'commodity' => 'fish',
                'quantity' => 1500,
                'quantity_unit' => 'kg',
                'harvest_year' => 2026,
            ])
            ->assertSessionHasNoErrors();

        $record = HarvestRecord::query()->where('commodity', 'fish')->latest('id')->firstOrFail();

        $this->assertNull($record->farmer_id);
        $this->assertSame($this->municipality->id, $record->municipality_id);
    }

    public function test_a_harvest_date_outside_the_reported_year_is_refused(): void
    {
        $this->actingAs($this->staff)
            ->post(route('harvest-records.store'), [
                'commodity' => 'rice',
                'quantity' => 100,
                'quantity_unit' => 'kg',
                'harvest_year' => 2026,
                'date_harvested' => '2023-05-01',
            ])
            ->assertSessionHasErrors('date_harvested');
    }

    public function test_a_projected_harvest_cannot_be_edited_or_deleted_here(): void
    {
        $projected = $this->projectedHarvest();

        // Not merely a hidden button: the policy refuses, so the route refuses.
        $this->assertFalse($this->staff->can('update', $projected));
        $this->assertFalse($this->staff->can('delete', $projected));
        $this->assertTrue($this->staff->can('view', $projected));

        $this->actingAs($this->staff)
            ->get(route('harvest-records.edit', $projected))
            ->assertRedirect(route('harvest-records.index'))
            ->assertSessionHas('error');

        $this->actingAs($this->staff)
            ->put(route('harvest-records.update', $projected), [
                'commodity' => 'corn',
                'quantity' => 1,
                'quantity_unit' => 'kg',
                'harvest_year' => 2026,
            ])
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->delete(route('harvest-records.destroy', $projected))
            ->assertRedirect(route('harvest-records.index'));

        $projected->refresh();
        $this->assertSame('rice', $projected->commodity);
        $this->assertSame('120.000', (string) $projected->quantity);
    }

    public function test_the_list_shows_a_projected_harvest_and_links_to_its_release(): void
    {
        $projected = $this->projectedHarvest();

        $this->actingAs($this->staff)
            ->get(route('harvest-records.index'))
            ->assertOk()
            ->assertSee('Assistance release')
            ->assertSee(route('rice-seed-distributions.edit', $projected->rice_seed_distribution_id), false)
            ->assertDontSee(route('harvest-records.edit', $projected), false);
    }

    public function test_a_directly_entered_harvest_can_be_edited_and_deleted(): void
    {
        $record = $this->directHarvest();

        $this->actingAs($this->staff)
            ->get(route('harvest-records.edit', $record))
            ->assertOk();

        $this->actingAs($this->staff)
            ->put(route('harvest-records.update', $record), [
                'commodity' => 'corn',
                'quantity' => 500,
                'quantity_unit' => 'sack',
                'harvest_year' => 2026,
                '_record_version' => ConcurrentWrite::version($record->fresh()),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record->refresh();
        $this->assertSame('500.000', (string) $record->quantity);

        $this->actingAs($this->staff)
            ->delete(route('harvest-records.destroy', $record))
            ->assertRedirect();

        $this->assertNull(HarvestRecord::query()->find($record->id));
    }

    public function test_a_save_built_on_a_stale_version_is_refused(): void
    {
        // Two clerks with the same record open. The second save must not silently
        // discard the first, which is why the form carries a version at all.
        $record = $this->directHarvest();
        $stale = ConcurrentWrite::version($record->fresh());

        $record->update(['quantity' => 111]);

        $this->actingAs($this->staff)
            ->from(route('harvest-records.edit', $record))
            ->put(route('harvest-records.update', $record), [
                'commodity' => 'vegetable',
                'quantity' => 999,
                'quantity_unit' => 'kg',
                'harvest_year' => 2026,
                '_record_version' => $stale,
            ])
            ->assertSessionHasErrors();

        $this->assertSame('111.000', (string) $record->fresh()->quantity);
    }

    public function test_a_sibling_municipality_can_neither_see_nor_touch_the_record(): void
    {
        $record = $this->directHarvest();

        $neighbour = User::create([
            'name' => 'Neighbour Clerk',
            'email' => 'neighbour-'.str_replace('.', '', uniqid('', true)).'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->sibling->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);

        $this->actingAs($neighbour)
            ->get(route('harvest-records.index'))
            ->assertOk()
            ->assertDontSee('Kangkong marker');

        $this->actingAs($neighbour)
            ->get(route('harvest-records.edit', $record))
            ->assertForbidden();

        $this->actingAs($neighbour)
            ->delete(route('harvest-records.destroy', $record))
            ->assertForbidden();

        $this->assertNotNull(HarvestRecord::query()->find($record->id));
    }

    public function test_the_export_is_audited_with_its_filters_and_row_count(): void
    {
        $this->directHarvest();

        $response = $this->actingAs($this->staff)
            ->get(route('harvest-records.export', ['commodity' => 'vegetable']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $audit = \App\Models\AuditLog::query()
            ->where('module', 'Harvest records')
            ->where('event', 'exported')
            ->latest('id')
            ->firstOrFail();

        $metadata = is_array($audit->metadata) ? $audit->metadata : json_decode((string) $audit->metadata, true);

        $this->assertSame(1, $metadata['row_count']);
        $this->assertSame('vegetable', $metadata['filters']['commodity']);
    }

    private function projectedHarvest(): HarvestRecord
    {
        $release = RiceSeedDistribution::create([
            'municipality_id' => $this->municipality->id,
            'farmer_id' => $this->farmer->id,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'kgs_received' => 40,
            'date_received' => '2026-01-20',
            'harvest_year' => 2026,
            'total_production_bags' => 120,
        ]);

        return app(HarvestFromRelease::class)->sync($release);
    }

    private function directHarvest(): HarvestRecord
    {
        return HarvestRecord::create([
            'municipality_id' => $this->municipality->id,
            'farmer_id' => $this->farmer->id,
            'commodity' => 'vegetable',
            'variety' => 'Kangkong marker',
            'harvest_year' => 2026,
            'quantity' => 300,
            'quantity_unit' => 'kg',
            'recorded_by' => $this->staff->id,
        ]);
    }

    private function makeMunicipality(string $label): Municipality
    {
        $suffix = str_replace('.', '', uniqid('', true));

        return Municipality::create([
            'name' => $label.' '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'HR'.substr($suffix, -8),
            'is_active' => true,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\DuplicateClaimCheck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * One entitlement should be issued once.
 *
 * Nothing in the register used to notice when it was issued twice: two staff working
 * the same queue, or the same person on two days, could record the same farmer
 * receiving rice seed twice for one planting.
 *
 * The behaviour these tests pin is deliberately a warning and not a refusal. A second
 * release is sometimes correct — a replanting after a typhoon, a delivery split
 * because the truck was short, two genuinely different seasons — and a system that
 * refuses those teaches staff to work around it, which leaves the office worse off
 * than no check at all. What must hold is that the officer is told, that they have to
 * say they checked, and that saying so is recorded.
 */
class DuplicateClaimCheckTest extends TestCase
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
            'name' => 'Repeat Claim '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'RC'.substr($suffix, -8),
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Claim Staff',
            'email' => 'claims-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);

        $this->farmer = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Repeat',
            'last_name' => 'Recipient',
            'farm_location' => 'Barangay Uno',
        ]);
    }

    public function test_a_second_release_of_the_same_input_is_warned_about_and_not_saved_yet(): void
    {
        $this->release(['date_received' => '2026-01-20', 'kgs_received' => 40]);

        $this->actingAs($this->staff)
            ->post(route('rice-seed-distributions.store'), $this->payload(['date_received' => '2026-02-01']))
            ->assertSessionHas('repeat_claim_warning')
            ->assertRedirect();

        // The warning has to stop the write, or it is only decoration.
        $this->assertSame(1, $this->releaseCount(), 'The repeat release was saved despite the warning.');

        $warning = session('repeat_claim_warning');
        $this->assertStringContainsString('Rice seed', $warning);
        $this->assertStringContainsString('20 January 2026', $warning, 'The warning must name the earlier release, not just assert one exists.');
    }

    public function test_confirming_saves_the_release_and_records_who_confirmed_it(): void
    {
        $this->release(['date_received' => '2026-01-20']);

        $this->actingAs($this->staff)
            ->post(route('rice-seed-distributions.store'), $this->payload([
                'date_received' => '2026-02-01',
                'confirm_repeat_claim' => 1,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rice-seed-distributions.index'));

        $this->assertSame(2, $this->releaseCount());

        // "Who issued a second claim, and when" has to be answerable afterwards.
        $entry = AuditLog::query()->where('event', 'repeat_claim_confirmed')->latest('id')->first();
        $this->assertNotNull($entry, 'Confirming a repeat claim recorded nothing.');
        $this->assertSame((string) $this->staff->id, (string) $entry->user_id);
    }

    public function test_a_different_input_to_the_same_farmer_is_not_a_repeat(): void
    {
        $this->release(['input_category' => 'rice_seed', 'date_received' => '2026-01-20']);

        $this->actingAs($this->staff)
            ->post(route('rice-seed-distributions.store'), $this->payload([
                'input_category' => 'fertilizer',
                'date_received' => '2026-02-01',
            ]))
            ->assertSessionMissing('repeat_claim_warning');

        $this->assertSame(2, $this->releaseCount());
    }

    public function test_the_same_input_to_a_different_farmer_is_not_a_repeat(): void
    {
        $other = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Other', 'last_name' => 'Farmer', 'farm_location' => 'Barangay Dos',
        ]);
        $this->release(['date_received' => '2026-01-20']);

        $this->actingAs($this->staff)
            ->post(route('rice-seed-distributions.store'), $this->payload([
                'farmer_id' => $other->id,
                'date_received' => '2026-02-01',
            ]))
            ->assertSessionMissing('repeat_claim_warning');
    }

    public function test_a_release_outside_the_window_is_a_new_season_and_not_a_repeat(): void
    {
        // The default window is one cropping season. A release either side of it is
        // the next planting, which is exactly what the programme intends.
        config(['assistance.duplicate_claim_window_days' => 180]);
        $this->release(['date_received' => '2025-01-20']);

        $check = app(DuplicateClaimCheck::class);

        $this->assertNull(
            $check->warningFor($this->farmer->id, 'rice_seed', '2026-01-20'),
            'A release a year later was treated as a repeat.'
        );
        $this->assertNotNull(
            $check->warningFor($this->farmer->id, 'rice_seed', '2025-03-01'),
            'A release six weeks later was not flagged.'
        );
    }

    public function test_a_stated_season_decides_instead_of_the_window(): void
    {
        // A season the office wrote down beats any guess based on dates. These two
        // releases are 20 months apart but belong to the same stated season, so they
        // are a repeat even though the window would never have caught them.
        $this->release([
            'date_received' => '2025-01-20',
            'harvest_season' => 'dry',
            'harvest_year' => 2026,
        ]);

        $warning = app(DuplicateClaimCheck::class)
            ->warningFor($this->farmer->id, 'rice_seed', '2026-09-20', 'dry', 2026);

        $this->assertNotNull($warning, 'Two releases for the same stated season were not flagged.');
    }

    public function test_editing_a_release_does_not_flag_it_against_itself(): void
    {
        $release = $this->release(['date_received' => '2026-01-20']);

        $this->assertNull(
            app(DuplicateClaimCheck::class)->warningFor(
                $this->farmer->id, 'rice_seed', '2026-01-20', null, null, $release->id
            ),
            'A release was reported as a duplicate of itself.'
        );
    }

    public function test_the_review_list_shows_repeats_already_in_the_register_and_stays_in_scope(): void
    {
        $this->release(['date_received' => '2026-01-20']);
        $this->release(['date_received' => '2026-02-01']);

        // Another municipality's repeat must not appear in this account's review list.
        $foreignMunicipality = Municipality::create([
            'name' => 'Foreign Claim '.uniqid(),
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'FC'.substr(uniqid(), -8),
            'is_active' => true,
        ]);
        $foreignFarmer = Farmer::create([
            'municipality_id' => $foreignMunicipality->id,
            'first_name' => 'Foreign', 'last_name' => 'Recipient', 'farm_location' => 'Elsewhere',
        ]);
        foreach (['2026-01-20', '2026-02-01'] as $date) {
            RiceSeedDistribution::create([
                'municipality_id' => $foreignMunicipality->id,
                'farmer_id' => $foreignFarmer->id,
                'input_category' => 'rice_seed',
                'quantity_unit' => 'kg',
                'kgs_received' => 40,
                'date_received' => $date,
            ]);
        }

        $repeats = app(DuplicateClaimCheck::class)->existingRepeats($this->staff);
        $farmerIds = $repeats->pluck('farmer_id')->map(fn ($id) => (int) $id);

        $this->assertTrue($farmerIds->contains($this->farmer->id));
        $this->assertFalse(
            $farmerIds->contains($foreignFarmer->id),
            'The review list leaked a repeat from another municipality.'
        );

        $row = $repeats->firstWhere('farmer_id', $this->farmer->id);
        $this->assertSame(2, (int) $row->releases);
    }

    private function releaseCount(): int
    {
        return RiceSeedDistribution::query()
            ->where('municipality_id', $this->municipality->id)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function release(array $overrides = []): RiceSeedDistribution
    {
        return RiceSeedDistribution::create(array_merge([
            'municipality_id' => $this->municipality->id,
            'farmer_id' => $this->farmer->id,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'kgs_received' => 40,
            'date_received' => '2026-01-20',
            'last_name' => $this->farmer->last_name,
            'first_name' => $this->farmer->first_name,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'farmer_id' => $this->farmer->id,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'kgs_received' => 40,
            'seed_variety_claimed' => 'LP 937',
            'date_received' => '2026-02-01',
        ], $overrides);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\RiceDistributionBatch;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * Rice Seed Distribution Sheet: ownership, the legacy releases it has to keep
 * working with, the one derived total, and the printable export.
 */
class RiceSeedDistributionSheetTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $ownMunicipality;

    private Municipality $foreignMunicipality;

    private Municipality $otherProvinceMunicipality;

    private User $municipalUser;

    private User $provincialUser;

    private User $superAdmin;

    private Farmer $ownFarmer;

    private RiceDistributionBatch $ownBatch;

    private RiceDistributionBatch $foreignBatch;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));

        $otherProvince = Province::query()->firstOrCreate(
            ['name' => 'Sheet Test Province '.$suffix],
            ['is_active' => true]
        );

        $this->ownMunicipality = $this->makeMunicipality(
            'Sheet Own '.$suffix,
            $this->supervisingProvinceId(),
            'SO'.substr($suffix, -8)
        );
        $this->foreignMunicipality = $this->makeMunicipality(
            'Sheet Foreign '.$suffix,
            $this->supervisingProvinceId(),
            'SF'.substr($suffix, -8)
        );
        $this->otherProvinceMunicipality = $this->makeMunicipality(
            'Sheet Outside '.$suffix,
            (int) $otherProvince->id,
            'SX'.substr($suffix, -8)
        );

        $this->municipalUser = $this->makeUser(
            User::ROLE_MUNICIPAL_STAFF,
            'sheet-municipal-'.$suffix.'@example.test',
            $this->ownMunicipality->id,
            $this->supervisingProvinceId()
        );
        $this->provincialUser = $this->makeUser(
            User::ROLE_PROVINCIAL_STAFF,
            'sheet-provincial-'.$suffix.'@example.test',
            null,
            $this->supervisingProvinceId()
        );
        $this->superAdmin = $this->makeUser(
            User::ROLE_SUPER_ADMIN,
            'sheet-superadmin-'.$suffix.'@example.test',
            null,
            $this->supervisingProvinceId()
        );

        $this->ownFarmer = Farmer::create([
            'municipality_id' => $this->ownMunicipality->id,
            'first_name' => 'Sheet',
            'last_name' => 'Recipient',
            'ffrs' => 'FFRS-'.substr($suffix, -8),
            'farm_location' => 'Barangay Uno',
            'farm_area_ha' => 2.5,
        ]);

        $this->ownBatch = $this->makeBatch($this->ownMunicipality->id, 'RCEF Own '.$suffix);
        $this->foreignBatch = $this->makeBatch($this->foreignMunicipality->id, 'RCEF Foreign '.$suffix);
    }

    public function test_municipal_account_creates_a_sheet_for_its_own_municipality_only(): void
    {
        $this->actingAs($this->municipalUser)
            ->post(route('rice-distribution-batches.store'), [
                'reference' => 'RCEF Municipal Create',
                'planting_season' => 'dry',
                'planting_year' => 2025,
                // A submitted municipality is never trusted for a municipal account.
                'municipality_id' => $this->foreignMunicipality->id,
            ])
            ->assertSessionHasErrors('municipality_id');

        $this->actingAs($this->municipalUser)
            ->post(route('rice-distribution-batches.store'), [
                'reference' => 'RCEF Municipal Create',
                'planting_season' => 'DS',
                'planting_year' => 2025,
                'harvest_season' => 'Wet season',
                'harvest_year' => 2025,
                'default_seed_bag_kg' => 20,
            ])
            ->assertRedirect(route('rice-distribution-batches.index'));

        $batch = RiceDistributionBatch::query()
            ->where('reference', 'RCEF Municipal Create')
            ->firstOrFail();

        $this->assertSame($this->ownMunicipality->id, $batch->municipality_id);
        $this->assertSame('dry', $batch->planting_season);
        $this->assertSame('wet', $batch->harvest_season);
        $this->assertSame('2025 DS', $batch->plantingSeasonHeading());
        $this->assertSame($this->municipalUser->id, $batch->created_by);

        $this->assertTrue(
            AuditLog::query()
                ->where('event', 'created')
                ->where('auditable_id', (string) $batch->id)
                ->where('module', 'Assistance distributions')
                ->exists(),
            'Creating a distribution sheet should be audited.'
        );
    }

    public function test_municipal_account_cannot_reach_another_municipalitys_sheet(): void
    {
        $this->actingAs($this->municipalUser)
            ->get(route('rice-distribution-batches.edit', $this->foreignBatch))
            ->assertForbidden();

        $this->actingAs($this->municipalUser)
            ->get(route('rice-distribution-batches.sheet', $this->foreignBatch))
            ->assertForbidden();

        $this->actingAs($this->municipalUser)
            ->get(route('rice-distribution-batches.export', $this->foreignBatch))
            ->assertForbidden();

        $this->actingAs($this->municipalUser)
            ->put(route('rice-distribution-batches.update', $this->foreignBatch), [
                'reference' => 'Hijacked',
                'planting_season' => 'dry',
                'planting_year' => 2025,
                '_record_version' => ConcurrentWrite::version($this->foreignBatch),
            ])
            ->assertForbidden();

        $this->actingAs($this->municipalUser)
            ->delete(route('rice-distribution-batches.destroy', $this->foreignBatch))
            ->assertForbidden();

        $this->assertSame(
            $this->foreignMunicipality->id,
            $this->foreignBatch->fresh()->municipality_id
        );
    }

    public function test_municipal_sheet_list_hides_other_municipalities(): void
    {
        $this->skipWithoutSheetViews('rice_seed_distributions.batches.index');

        $response = $this->actingAs($this->municipalUser)
            ->get(route('rice-distribution-batches.index'))
            ->assertOk();

        $references = collect($response->viewData('batches')->items())
            ->pluck('reference')
            ->all();

        $this->assertContains($this->ownBatch->reference, $references);
        $this->assertNotContains($this->foreignBatch->reference, $references);
    }

    public function test_provincial_account_must_choose_a_municipality_inside_its_province(): void
    {
        $this->actingAs($this->provincialUser)
            ->post(route('rice-distribution-batches.store'), [
                'reference' => 'RCEF Provincial',
                'planting_season' => 'dry',
                'planting_year' => 2025,
            ])
            ->assertSessionHasErrors('municipality_id');

        $this->actingAs($this->provincialUser)
            ->post(route('rice-distribution-batches.store'), [
                'reference' => 'RCEF Provincial',
                'planting_season' => 'dry',
                'planting_year' => 2025,
                'municipality_id' => $this->otherProvinceMunicipality->id,
            ])
            ->assertSessionHasErrors('municipality_id');

        $this->actingAs($this->provincialUser)
            ->post(route('rice-distribution-batches.store'), [
                'reference' => 'RCEF Provincial',
                'planting_season' => 'dry',
                'planting_year' => 2025,
                'municipality_id' => $this->foreignMunicipality->id,
            ])
            ->assertRedirect(route('rice-distribution-batches.index'));

        $this->assertSame(
            $this->foreignMunicipality->id,
            RiceDistributionBatch::query()
                ->where('reference', 'RCEF Provincial')
                ->value('municipality_id')
        );
    }

    public function test_super_admin_oversight_of_sheets_stays_read_only(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('rice-distribution-batches.store'), [
                'reference' => 'Super Admin Sheet',
                'planting_season' => 'dry',
                'planting_year' => 2025,
                'municipality_id' => $this->ownMunicipality->id,
            ])
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->put(route('rice-distribution-batches.update', $this->ownBatch), [
                'reference' => 'Super Admin Edit',
                'planting_season' => 'dry',
                'planting_year' => 2025,
                '_record_version' => ConcurrentWrite::version($this->ownBatch),
            ])
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->delete(route('rice-distribution-batches.destroy', $this->ownBatch))
            ->assertForbidden();

        $this->assertDatabaseMissing('rice_distribution_batches', [
            'reference' => 'Super Admin Sheet',
        ]);

        // Read-only oversight still includes downloading the sheet for review.
        $this->actingAs($this->superAdmin)
            ->get(route('rice-distribution-batches.export', $this->ownBatch))
            ->assertOk();
    }

    public function test_provincial_veterinary_account_cannot_open_sheets(): void
    {
        $vet = $this->makeUser(
            User::ROLE_PROVINCIAL_VET,
            'sheet-vet-'.uniqid('', true).'@example.test',
            null,
            $this->supervisingProvinceId()
        );

        $this->actingAs($vet)
            ->get(route('rice-distribution-batches.index'))
            ->assertRedirect(route('anti-rabies-vaccinations.index'));

        $this->actingAs($vet)
            ->post(route('rice-distribution-batches.store'), [
                'reference' => 'Vet Sheet',
                'planting_season' => 'dry',
                'planting_year' => 2025,
            ])
            ->assertRedirect(route('anti-rabies-vaccinations.index'));

        $this->assertDatabaseMissing('rice_distribution_batches', ['reference' => 'Vet Sheet']);
    }

    public function test_releases_without_a_batch_remain_editable(): void
    {
        // A release imported before sheets existed: no batch, and a seed class the
        // form has never offered.
        $legacy = RiceSeedDistribution::create([
            'municipality_id' => $this->ownMunicipality->id,
            'farmer_id' => $this->ownFarmer->id,
            'first_name' => 'Sheet',
            'last_name' => 'Recipient',
            'seed_variety_claimed' => 'LP 937',
            'seed_class' => 'Registered',
            'crop_establishment' => 'Direct seeded',
            'kgs_received' => 40,
            'date_received' => '2025-01-15',
        ]);

        // The edit form renders a record read back from the database, so the
        // version token has to be taken from the persisted row.
        $legacy = $legacy->fresh();

        $this->assertNull($legacy->batch_id);

        $this->actingAs($this->municipalUser)
            ->put(route('rice-seed-distributions.update', $legacy), $this->releasePayload([
                'seed_class' => 'Registered',
                'crop_establishment' => 'Direct seeded',
                'kgs_received' => 45,
                '_record_version' => ConcurrentWrite::version($legacy),
            ]))
            ->assertRedirect(route('rice-seed-distributions.index'))
            ->assertSessionHasNoErrors();

        $legacy->refresh();

        $this->assertNull($legacy->batch_id, 'An unbatched release must stay unbatched.');
        $this->assertSame('Registered', $legacy->seed_class);
        $this->assertSame('Direct seeded', $legacy->crop_establishment);
        $this->assertSame('45.00', (string) $legacy->kgs_received);
    }

    public function test_registered_seed_class_is_accepted_on_new_releases(): void
    {
        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'seed_class' => 'Registered',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rice-seed-distributions.index'));

        $this->assertSame(
            'Registered',
            RiceSeedDistribution::query()
                ->where('farmer_id', $this->ownFarmer->id)
                ->latest('id')
                ->value('seed_class')
        );
    }

    public function test_an_unknown_seed_class_is_still_rejected(): void
    {
        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'seed_class' => 'Definitely Not A Seed Class',
            ]))
            ->assertSessionHasErrors('seed_class');
    }

    public function test_total_kilograms_are_derived_from_bags_and_bag_weight(): void
    {
        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'seed_bags' => 5,
                'seed_bag_kg' => 40,
                // Deliberately inconsistent: bags x bag weight is the source of truth.
                'kgs_received' => 1,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rice-seed-distributions.index'));

        $release = RiceSeedDistribution::query()
            ->where('farmer_id', $this->ownFarmer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('200.00', (string) $release->kgs_received);
        $this->assertSame(5, $release->seed_bags);
        $this->assertSame('40.00', (string) $release->seed_bag_kg);
    }

    public function test_bag_weight_does_not_derive_kilograms_for_a_release_counted_in_another_unit(): void
    {
        // Bag weight is stated in kilograms. Deriving from it for a release counted
        // in pieces would write a weight under a unit that is not a weight, and
        // every later total would read it as one.
        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'input_category' => 'fish_fingerlings',
                'quantity_unit' => 'piece',
                'seed_bags' => 5,
                'seed_bag_kg' => 40,
                'kgs_received' => 5000,
            ]))
            ->assertSessionHasNoErrors();

        $release = RiceSeedDistribution::query()
            ->where('farmer_id', $this->ownFarmer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('piece', $release->quantity_unit);
        $this->assertSame('5000.00', (string) $release->kgs_received, 'Bags x bag weight overwrote a piece count.');
    }

    public function test_a_release_counted_in_another_unit_cannot_be_added_to_a_sheet(): void
    {
        $batch = $this->makeBatch($this->ownMunicipality->id, 'UNIT-GUARD-'.uniqid());

        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'batch_id' => $batch->id,
                'input_category' => 'fish_fingerlings',
                'quantity_unit' => 'piece',
                'kgs_received' => 5000,
            ]))
            ->assertSessionHasErrors('batch_id');

        $this->assertSame(0, RiceSeedDistribution::query()->where('batch_id', $batch->id)->count());
    }

    public function test_a_kilogram_release_is_still_accepted_on_a_sheet(): void
    {
        $batch = $this->makeBatch($this->ownMunicipality->id, 'UNIT-OK-'.uniqid());

        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'batch_id' => $batch->id,
                'seed_bags' => 5,
                'seed_bag_kg' => 40,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, RiceSeedDistribution::query()->where('batch_id', $batch->id)->count());
    }

    public function test_the_printed_kilogram_total_leaves_out_releases_counted_in_another_unit(): void
    {
        // Written straight to the table: the form now refuses this, but a sheet must
        // still print an honest total over a row that predates the guard.
        $batch = $this->makeBatch($this->ownMunicipality->id, 'UNIT-TOTAL-'.uniqid());

        RiceSeedDistribution::create($this->releasePayload([
            'batch_id' => $batch->id,
            'municipality_id' => $this->ownMunicipality->id,
            'kgs_received' => 40,
        ]));
        RiceSeedDistribution::create($this->releasePayload([
            'batch_id' => $batch->id,
            'municipality_id' => $this->ownMunicipality->id,
            'input_category' => 'fish_fingerlings',
            'quantity_unit' => 'piece',
            'kgs_received' => 5000,
        ]));

        $sheet = app(\App\Support\RiceSeedDistributionSheet::class);
        $totals = $sheet->totals($batch->fresh());

        $this->assertSame(40.0, $totals['kgs_received'], '5,000 pieces were added to a kilogram total.');

        // The row says what it is, so the column still adds up to its own total.
        $rows = $sheet->presentRows($batch->fresh(), $sheet->releases($batch->fresh())->get());
        $printed = collect($rows)->pluck('kgs_received')->all();
        $this->assertContains('40.00', $printed);
        $this->assertTrue(
            (bool) collect($printed)->contains(fn ($value) => str_contains((string) $value, 'pieces')),
            'A release counted in pieces printed as a bare number under a kilogram heading.'
        );
    }

    public function test_consent_defaults_to_unrecorded_and_only_accepts_known_states(): void
    {
        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload())
            ->assertSessionHasNoErrors();

        $release = RiceSeedDistribution::query()
            ->where('farmer_id', $this->ownFarmer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(RiceSeedDistribution::CONSENT_UNRECORDED, $release->consent_status);

        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'consent_status' => 'maybe',
            ]))
            ->assertSessionHasErrors('consent_status');
    }

    public function test_a_release_cannot_join_another_municipalitys_sheet(): void
    {
        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'batch_id' => $this->foreignBatch->id,
            ]))
            ->assertSessionHasErrors('batch_id');

        $this->assertSame(
            0,
            RiceSeedDistribution::query()
                ->where('batch_id', $this->foreignBatch->id)
                ->count()
        );

        $this->actingAs($this->municipalUser)
            ->post(route('rice-seed-distributions.store'), $this->releasePayload([
                'batch_id' => $this->ownBatch->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            1,
            RiceSeedDistribution::query()
                ->where('batch_id', $this->ownBatch->id)
                ->count()
        );
    }

    public function test_a_municipal_account_cannot_rewrite_another_municipalitys_release(): void
    {
        $foreignFarmer = Farmer::create([
            'municipality_id' => $this->foreignMunicipality->id,
            'first_name' => 'Foreign',
            'last_name' => 'Recipient',
        ]);
        $foreignRelease = RiceSeedDistribution::create([
            'municipality_id' => $this->foreignMunicipality->id,
            'farmer_id' => $foreignFarmer->id,
            'first_name' => 'Foreign',
            'last_name' => 'Recipient',
            'kgs_received' => 10,
            'date_received' => '2025-02-01',
        ]);

        $this->actingAs($this->municipalUser)
            ->put(route('rice-seed-distributions.update', $foreignRelease), $this->releasePayload([
                '_record_version' => ConcurrentWrite::version($foreignRelease),
            ]))
            ->assertForbidden();

        $foreignRelease->refresh();

        $this->assertSame($this->foreignMunicipality->id, $foreignRelease->municipality_id);
        $this->assertSame($foreignFarmer->id, $foreignRelease->farmer_id);
    }

    public function test_exported_sheet_uses_stored_season_headings_and_guards_formula_values(): void
    {
        $formulaFarmer = Farmer::create([
            'municipality_id' => $this->ownMunicipality->id,
            'first_name' => 'Juan',
            'last_name' => '=2+5',
            'farm_location' => 'Barangay Dos',
        ]);

        RiceSeedDistribution::create([
            'municipality_id' => $this->ownMunicipality->id,
            'batch_id' => $this->ownBatch->id,
            'farmer_id' => $formulaFarmer->id,
            'first_name' => 'Juan',
            'last_name' => '=2+5',
            'farm_location' => 'Barangay Dos',
            'seed_variety_claimed' => 'LP 937',
            'seed_bags' => 3,
            'seed_bag_kg' => 20,
            'kgs_received' => 60,
            'claimed_area_ha' => 1.5,
            'harvest_season' => 'wet',
            'harvest_year' => 2025,
            'date_received' => '2025-01-20',
        ]);

        $response = $this->actingAs($this->municipalUser)
            ->get(route('rice-distribution-batches.export', $this->ownBatch))
            ->assertOk();

        $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $cells = collect($spreadsheet->getActiveSheet()->toArray(null, true, false, false))
            ->flatten()
            ->map(fn ($value) => (string) $value)
            ->all();
        $spreadsheet->disconnectWorksheets();

        $this->assertContains('2025 DS SEED DISTRIBUTION', $cells);
        $this->assertContains('2026 WS PRODUCTION MONITORING', $cells);
        $this->assertContains('Signature of recipient', $cells);
        // The leading apostrophe keeps a farmer's name inert when the sheet is
        // opened in Excel.
        $this->assertContains("'=2+5", $cells);
        $this->assertNotContains('=2+5', $cells);
        $this->assertContains('60.00', $cells);
        $this->assertContains('TOTAL', $cells);

        $this->assertTrue(
            AuditLog::query()
                ->where('event', 'exported')
                ->where('module', 'Assistance distributions')
                ->where('auditable_id', (string) $this->ownBatch->id)
                ->exists(),
            'Exporting a distribution sheet should be audited.'
        );
    }

    public function test_on_screen_rows_and_totals_cover_every_sheet_column(): void
    {
        foreach ([['Alvarez', 2, 25], ['Zamora', 3, 25]] as [$lastName, $bags, $bagKg]) {
            RiceSeedDistribution::create([
                'municipality_id' => $this->ownMunicipality->id,
                'batch_id' => $this->ownBatch->id,
                'farmer_id' => $this->ownFarmer->id,
                'first_name' => 'Sheet',
                'last_name' => $lastName,
                'seed_bags' => $bags,
                'seed_bag_kg' => $bagKg,
                'kgs_received' => $bags * $bagKg,
                'claimed_area_ha' => 1,
                'date_received' => '2025-01-20',
            ]);
        }

        // A release in another municipality must never reach this sheet.
        RiceSeedDistribution::create([
            'municipality_id' => $this->foreignMunicipality->id,
            'batch_id' => $this->foreignBatch->id,
            'first_name' => 'Foreign',
            'last_name' => 'Recipient',
            'kgs_received' => 999,
            'date_received' => '2025-01-20',
        ]);

        $sheet = app(\App\Support\RiceSeedDistributionSheet::class);
        $columns = $sheet->columns($this->ownBatch);
        $rows = $sheet->presentRows(
            $this->ownBatch,
            $sheet->releases($this->ownBatch)->get()
        );

        $this->assertCount(2, $rows);
        $this->assertSame('1', $rows[0]['sequence']);
        $this->assertSame('Alvarez', $rows[0]['last_name']);
        $this->assertSame('Zamora', $rows[1]['last_name']);
        $this->assertSame('Not recorded', $rows[0]['consent_status']);
        $this->assertSame('', $rows[0]['signature']);

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $this->assertArrayHasKey($column['key'], $row);
            }
        }

        $totals = $sheet->totals($this->ownBatch);

        $this->assertSame(5.0, $totals['seed_bags']);
        $this->assertSame(125.0, $totals['kgs_received']);
        $this->assertSame(2.0, $totals['claimed_area_ha']);
        $this->assertSame('125.00', $sheet->totalsRow($totals, $columns)['kgs_received']);
        $this->assertSame('TOTAL', $sheet->totalsRow($totals, $columns)['sequence']);
    }

    public function test_a_sheet_holding_releases_cannot_be_deleted(): void
    {
        RiceSeedDistribution::create([
            'municipality_id' => $this->ownMunicipality->id,
            'batch_id' => $this->ownBatch->id,
            'farmer_id' => $this->ownFarmer->id,
            'first_name' => 'Sheet',
            'last_name' => 'Recipient',
            'kgs_received' => 20,
            'date_received' => '2025-01-20',
        ]);

        $this->actingAs($this->municipalUser)
            ->delete(route('rice-distribution-batches.destroy', $this->ownBatch))
            ->assertSessionHasErrors('batch');

        $this->assertDatabaseHas('rice_distribution_batches', ['id' => $this->ownBatch->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function releasePayload(array $overrides = []): array
    {
        return array_merge([
            'farmer_id' => $this->ownFarmer->id,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'seed_variety_claimed' => 'LP 937',
            'kgs_received' => 40,
            'date_received' => '2025-01-20',
        ], $overrides);
    }

    private function makeMunicipality(string $name, int $provinceId, string $code): Municipality
    {
        return Municipality::create([
            'name' => $name,
            'province' => 'Tarlac',
            'province_id' => $provinceId,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(
        string $role,
        string $email,
        ?int $municipalityId,
        int $provinceId
    ): User {
        return User::create([
            'name' => 'Sheet Test User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'municipality_id' => $municipalityId,
            'province_id' => $provinceId,
            'is_active' => true,
        ]);
    }

    private function makeBatch(int $municipalityId, string $reference): RiceDistributionBatch
    {
        return RiceDistributionBatch::create([
            'municipality_id' => $municipalityId,
            'reference' => $reference,
            'planting_season' => 'dry',
            'planting_year' => 2025,
            'harvest_season' => 'wet',
            'harvest_year' => 2026,
            'default_seed_bag_kg' => 20,
        ]);
    }

    /**
     * The sheet screens belong to the interface work in this branch. Until they
     * land, the checks that need a rendered page are skipped rather than made to
     * pass against a stand-in template.
     */
    private function skipWithoutSheetViews(string $view): void
    {
        if (! View::exists($view)) {
            $this->markTestSkipped('The '.$view.' template has not been added yet.');
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Modules\Agriculture\Application\HarvestFromRelease;
use App\Modules\Agriculture\Application\HarvestWorkbook;
use App\Modules\Farmers\Application\FarmerIdentifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * Bulk Excel import of directly entered harvests.
 *
 * What is pinned here: the import accepts exactly what the single-record form accepts,
 * it is all-or-nothing, a farmer reference can only reach a farmer in the target
 * municipality (and fails the same way whether or not one exists elsewhere), a repeat
 * upload adds nothing, and the accounts that cannot write harvests cannot import them.
 * All names and identifiers are synthetic.
 */
class HarvestImportTest extends TestCase
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

        $this->municipality = $this->makeMunicipality('Import Home');
        $this->sibling = $this->makeMunicipality('Import Next Door');

        $this->staff = $this->makeUser(User::ROLE_MUNICIPAL_STAFF, $this->municipality->id);

        $this->farmer = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Synthetic',
            'last_name' => 'Importgrower',
            'ffrs' => '99-01-01-001-000101',
            'rsbsa_no' => 'RSBSA-SYN-0101',
            'farm_location' => 'Barangay Uno',
        ]);

        $this->foreignFarmer = Farmer::create([
            'municipality_id' => $this->sibling->id,
            'first_name' => 'Synthetic',
            'last_name' => 'Elsewhere',
            'ffrs' => '99-02-02-002-000202',
            'farm_location' => 'Barangay Dos',
        ]);
    }

    public function test_non_rice_harvests_are_imported_for_the_account_municipality(): void
    {
        $path = $this->workbook([
            2 => [
                'Farmer ID' => FarmerIdentifier::format($this->farmer->id),
                'Commodity' => 'corn',
                'Variety' => 'IPB Var 6',
                'Season' => 'dry',
                'Harvest Year' => 2026,
                'Date Harvested' => ExcelDate::stringToExcel('2026-03-10'),
                'Area Harvested (ha)' => 2.5,
                'Quantity' => 420.5,
                'Unit' => 'sack',
                'Notes' => 'Synthetic import note',
            ],
            // Row 3 is left blank on purpose and must be skipped, not rejected.
            4 => [
                'Farmer ID' => '99-01-01-001-000101',
                'Commodity' => 'vegetable',
                'Harvest Year' => 2026,
                'Date Harvested' => '2026-02-01',
                'Quantity' => 300,
                'Unit' => 'kg',
            ],
            5 => [
                'Farmer ID' => 'rsbsa-syn-0101',
                'Commodity' => 'fish',
                'Harvest Year' => 2026,
                'Quantity' => 1500,
                'Unit' => 'piece',
            ],
            6 => [
                'Commodity' => 'rootcrop',
                'Harvest Year' => 2025,
                'Quantity' => 12,
                'Unit' => 'ton',
            ],
        ]);

        $this->upload($this->staff, $path)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('harvest-records.index'))
            ->assertSessionHas('success', 'Import done. Created: 4, Already recorded: 0, Repeated in file: 0.');

        $records = HarvestRecord::query()->where('municipality_id', $this->municipality->id)->orderBy('id')->get();
        $this->assertCount(4, $records);

        $corn = $records->firstWhere('commodity', 'corn');
        $this->assertSame($this->farmer->id, $corn->farmer_id);
        $this->assertSame('420.500', (string) $corn->quantity);
        $this->assertSame('2.5000', (string) $corn->area_harvested_ha);
        $this->assertSame('sack', $corn->quantity_unit);
        $this->assertSame('dry', $corn->season);
        $this->assertSame('2026-03-10', $corn->date_harvested->format('Y-m-d'));
        $this->assertSame($this->staff->id, $corn->recorded_by);
        $this->assertNull($corn->farm_plot_id);
        $this->assertFalse($corn->isProjected());
        if (Schema::hasColumn('harvest_records', 'parcel_crop_season_id')) {
            $this->assertNull($corn->parcel_crop_season_id);
            $this->assertNull($corn->planted_area_key);
        }

        // Resolved by FFRS and, case-insensitively, by RSBSA number.
        $this->assertSame($this->farmer->id, $records->firstWhere('commodity', 'vegetable')->farmer_id);
        $this->assertSame('2026-02-01', $records->firstWhere('commodity', 'vegetable')->date_harvested->format('Y-m-d'));
        $this->assertSame($this->farmer->id, $records->firstWhere('commodity', 'fish')->farmer_id);
        $this->assertNull($records->firstWhere('commodity', 'rootcrop')->farmer_id);

        $audit = AuditLog::query()
            ->where('module', 'Harvest records')
            ->where('event', 'imported')
            ->latest('id')
            ->firstOrFail();
        $metadata = is_array($audit->metadata) ? $audit->metadata : json_decode((string) $audit->metadata, true);

        $this->assertSame(4, $metadata['created']);
        $this->assertSame(4, $metadata['rows']);
        $this->assertSame($this->municipality->id, (int) $audit->municipality_id);

        // Counts only: no farmer identifiers, names or notes in the summary.
        $recorded = json_encode([$audit->description, $metadata, $audit->old_values, $audit->new_values]);
        foreach (['99-01-01-001-000101', 'RSBSA-SYN-0101', 'Importgrower', 'Synthetic import note'] as $private) {
            $this->assertStringNotContainsStringIgnoringCase($private, $recorded);
        }
    }

    public function test_one_invalid_row_rejects_the_whole_workbook(): void
    {
        $valid = ['Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => 10, 'Unit' => 'sack'];

        $path = $this->workbook([
            2 => $valid,
            3 => ['Commodity' => 'Durian'] + $valid,
            4 => ['Quantity' => 'plenty'] + $valid,
            5 => ['Date Harvested' => '03/04/2026'] + $valid,
            6 => ['Date Harvested' => '2023-05-01'] + $valid,
            7 => ['Unit' => null] + $valid,
        ]);

        $this->upload($this->staff, $path)->assertSessionHasErrors('file');

        $lines = $this->fileErrors();
        $this->assertStringStartsWith('No harvests were imported.', $lines[0]);
        $this->assertContains('Row 3: Choose one of the listed commodities.', $lines);
        $this->assertContains('Row 4: The Quantity must be a number.', $lines);
        $this->assertContains('Row 5: Date Harvested must be an Excel date or written as YYYY-MM-DD.', $lines);
        $this->assertContains('Row 6: The harvest date is in 2023 but this harvest is reported under 2026.', $lines);
        $this->assertContains('Row 7: Choose the unit the harvest was counted in.', $lines);
        $this->assertFalse(collect($lines)->contains(fn (string $line) => str_starts_with($line, 'Row 2:')));

        // The valid row was not saved either.
        $this->assertSame(0, HarvestRecord::query()->where('municipality_id', $this->municipality->id)->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'Harvest records')->where('event', 'imported')->count());
    }

    public function test_long_error_lists_are_truncated(): void
    {
        $rows = [];
        for ($row = 2; $row <= 26; $row++) {
            $rows[$row] = ['Commodity' => 'Durian', 'Harvest Year' => 2026, 'Quantity' => 1, 'Unit' => 'kg'];
        }

        $this->upload($this->staff, $this->workbook($rows))->assertSessionHasErrors('file');

        $lines = $this->fileErrors();
        $this->assertCount(22, $lines);
        $this->assertSame('And 5 more rows with problems.', end($lines));
    }

    public function test_farmers_outside_the_target_municipality_are_row_errors_without_revealing_them(): void
    {
        $base = ['Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => 10, 'Unit' => 'sack'];

        $path = $this->workbook([
            2 => ['Farmer ID' => FarmerIdentifier::format($this->foreignFarmer->id)] + $base,
            3 => ['Farmer ID' => '99-02-02-002-000202'] + $base,
            4 => ['Farmer ID' => 'AGRI-F-999999'] + $base,
            5 => ['Farmer ID' => FarmerIdentifier::format($this->farmer->id)] + $base,
        ]);

        $this->upload($this->staff, $path)->assertSessionHasErrors('file');

        $lines = $this->fileErrors();
        $message = 'No farmer registered in this municipality has this Farmer ID. '
            .'Use the AgriLGU ID, FFRS or RSBSA number of a farmer registered here, or leave Farmer ID blank.';

        // Another municipality's farmer and a farmer who does not exist read the same.
        $this->assertContains('Row 2: '.$message, $lines);
        $this->assertContains('Row 3: '.$message, $lines);
        $this->assertContains('Row 4: '.$message, $lines);
        $this->assertFalse(collect($lines)->contains(fn (string $line) => str_starts_with($line, 'Row 5:')));

        $this->assertSame(0, HarvestRecord::query()->count());
    }

    public function test_a_reference_matching_two_farmers_is_ambiguous(): void
    {
        Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Synthetic',
            'last_name' => 'Lookalike',
            'rsbsa_no' => '99-01-01-001-000101',
            'farm_location' => 'Barangay Tres',
        ]);

        $path = $this->workbook([
            2 => ['Farmer ID' => '99-01-01-001-000101', 'Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => 10, 'Unit' => 'sack'],
        ]);

        $this->upload($this->staff, $path)->assertSessionHasErrors('file');

        $this->assertContains(
            'Row 2: This Farmer ID matches more than one farmer in this municipality. Use the farmer\'s AgriLGU ID (AGRI-F-…) instead.',
            $this->fileErrors()
        );
        $this->assertSame(0, HarvestRecord::query()->count());
    }

    public function test_municipal_staff_cannot_import_into_another_municipality(): void
    {
        $path = $this->workbook([
            2 => ['Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => 10, 'Unit' => 'sack'],
        ]);

        $this->upload($this->staff, $path, ['municipality_id' => $this->sibling->id])
            ->assertSessionHasErrors('municipality_id');

        $this->assertSame(0, HarvestRecord::query()->count());
    }

    public function test_reimporting_the_same_workbook_is_a_no_op(): void
    {
        // A projected harvest with the same figures is not a direct entry and must not
        // make the imported row look already recorded.
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
        app(HarvestFromRelease::class)->sync($release);

        $rice = ['Farmer ID' => FarmerIdentifier::format($this->farmer->id), 'Commodity' => 'rice', 'Harvest Year' => 2026, 'Quantity' => 120, 'Unit' => 'bag'];
        $corn = ['Commodity' => 'corn', 'Season' => 'wet', 'Harvest Year' => 2026, 'Date Harvested' => '2026-09-15', 'Quantity' => 88.25, 'Unit' => 'cavan'];

        $path = $this->workbook([2 => $rice, 3 => $corn, 4 => $corn]);

        $this->upload($this->staff, $path)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import done. Created: 2, Already recorded: 0, Repeated in file: 1.');

        $this->upload($this->staff, $path)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import done. Created: 0, Already recorded: 3, Repeated in file: 0.');

        $this->assertSame(2, HarvestRecord::query()->where('municipality_id', $this->municipality->id)->whereNull('rice_seed_distribution_id')->count());
        $this->assertSame(1, HarvestRecord::query()->whereNotNull('rice_seed_distribution_id')->count());
    }

    public function test_codes_and_labels_are_accepted_in_any_case_and_spacing(): void
    {
        $headings = ['  farmer id', 'COMMODITY', 'variety', 'season', 'harvest  year', 'date harvested', 'area harvested(ha)', 'quantity', 'UNIT', 'notes'];

        $path = $this->workbook([
            2 => ['Commodity' => 'CORN', 'Season' => 'Dry season', 'Harvest Year' => 2026, 'Quantity' => 5, 'Unit' => 'KILOGRAMS (KG)'],
            3 => ['Commodity' => 'Root Crops', 'Season' => 'WET', 'Harvest Year' => 2026, 'Quantity' => 6, 'Unit' => 'metric tons'],
            4 => ['Commodity' => 'Fish/Aquaculture', 'Season' => 'wet season', 'Harvest Year' => '2026', 'Quantity' => '7', 'Unit' => 'Pieces'],
            5 => ['Commodity' => 'livestock', 'Harvest Year' => 2026, 'Quantity' => 8, 'Unit' => 'piece'],
        ], $headings);

        $this->upload($this->staff, $path)->assertSessionHasNoErrors()->assertRedirect(route('harvest-records.index'));

        $saved = HarvestRecord::query()
            ->where('municipality_id', $this->municipality->id)
            ->orderBy('quantity')
            ->get(['commodity', 'season', 'quantity_unit'])
            ->map(fn (HarvestRecord $record) => [$record->commodity, $record->season, $record->quantity_unit])
            ->all();

        $this->assertSame([
            ['corn', 'dry', 'kg'],
            ['rootcrop', 'wet', 'ton'],
            ['fish', 'wet', 'piece'],
            ['livestock', null, 'piece'],
        ], $saved);
    }

    public function test_formula_cells_import_the_result_the_workbook_saved(): void
    {
        $path = $this->workbook([
            2 => ['Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => '=12*5', 'Unit' => 'sack'],
        ]);

        $this->upload($this->staff, $path)->assertSessionHasNoErrors();

        $this->assertSame('60.000', (string) HarvestRecord::query()->sole()->quantity);
    }

    public function test_a_workbook_over_the_row_limit_is_refused(): void
    {
        $rows = [];
        for ($row = 2; $row <= HarvestWorkbook::MAX_ROWS + 2; $row++) {
            $rows[$row] = ['Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => $row, 'Unit' => 'kg'];
        }

        $this->upload($this->staff, $this->workbook($rows))->assertSessionHasErrors('file');

        $this->assertStringContainsString('more than 2,000 harvest rows', $this->fileErrors()[0]);
        $this->assertSame(0, HarvestRecord::query()->count());
    }

    public function test_a_missing_required_column_or_unreadable_file_is_refused(): void
    {
        $path = $this->workbook(
            [2 => ['Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => 1]],
            ['Commodity', 'Harvest Year', 'Quantity']
        );

        $this->upload($this->staff, $path)->assertSessionHasErrors('file');
        $this->assertStringContainsString('missing the column Unit', $this->fileErrors()[0]);

        $this->actingAs($this->staff)
            ->post(route('harvest-records.import'), [
                'file' => UploadedFile::fake()->createWithContent('harvests.xlsx', 'This is not an Excel workbook.'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, HarvestRecord::query()->count());
    }

    public function test_read_only_and_veterinary_accounts_cannot_import(): void
    {
        $path = $this->workbook([
            2 => ['Commodity' => 'corn', 'Harvest Year' => 2026, 'Quantity' => 10, 'Unit' => 'sack'],
        ]);

        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN, null);
        $this->assertFalse($superAdmin->can('import', HarvestRecord::class));

        $this->actingAs($superAdmin)->get(route('harvest-records.import.form'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('harvest-records.import.template'))->assertForbidden();
        $this->upload($superAdmin, $path, ['municipality_id' => $this->municipality->id])->assertForbidden();
        $this->actingAs($superAdmin)
            ->get(route('harvest-records.index'))
            ->assertOk()
            ->assertDontSee(route('harvest-records.import.form'), false);

        $veterinary = $this->makeUser(User::ROLE_PROVINCIAL_VET, null);
        $this->assertFalse($veterinary->can('import', HarvestRecord::class));

        $this->actingAs($veterinary)
            ->get(route('harvest-records.import.form'))
            ->assertRedirect(route('anti-rabies-vaccinations.index'));
        $this->upload($veterinary, $path, ['municipality_id' => $this->municipality->id])
            ->assertRedirect(route('anti-rabies-vaccinations.index'));

        $this->assertSame(0, HarvestRecord::query()->count());

        $this->actingAs($this->staff)
            ->get(route('harvest-records.index'))
            ->assertOk()
            ->assertSee(route('harvest-records.import.form'), false);
    }

    public function test_provincial_staff_must_choose_an_authorized_municipality(): void
    {
        $provincial = $this->makeUser(User::ROLE_PROVINCIAL_STAFF, null);
        $otherProvince = Municipality::create([
            'name' => 'Import Other Province '.str_replace('.', '', uniqid('', true)),
            'province' => 'Pampanga',
            'province_id' => $this->supervisingProvinceId('Pampanga'),
            'code' => 'HI'.substr(str_replace('.', '', uniqid('', true)), -8),
            'is_active' => true,
        ]);

        $path = $this->workbook([
            2 => ['Farmer ID' => '99-01-01-001-000101', 'Commodity' => 'vegetable', 'Harvest Year' => 2026, 'Quantity' => 40, 'Unit' => 'kg'],
        ]);

        $this->actingAs($provincial)
            ->get(route('harvest-records.import.form'))
            ->assertOk()
            ->assertSee('name="municipality_id"', false);

        $this->upload($provincial, $path)->assertSessionHasErrors('municipality_id');
        $this->upload($provincial, $path, ['municipality_id' => $otherProvince->id])->assertSessionHasErrors('municipality_id');
        $this->assertSame(0, HarvestRecord::query()->count());

        // Choosing the sibling makes the farmer reference foreign, so the row fails.
        $this->upload($provincial, $path, ['municipality_id' => $this->sibling->id])->assertSessionHasErrors('file');
        $this->assertSame(0, HarvestRecord::query()->count());

        $this->upload($provincial, $path, ['municipality_id' => $this->municipality->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('harvest-records.index'));

        $record = HarvestRecord::query()->sole();
        $this->assertSame($this->municipality->id, $record->municipality_id);
        $this->assertSame($this->farmer->id, $record->farmer_id);
        $this->assertSame($provincial->id, $record->recorded_by);
    }

    public function test_municipal_staff_import_into_their_own_municipality_without_choosing_one(): void
    {
        $this->actingAs($this->staff)
            ->get(route('harvest-records.import.form'))
            ->assertOk()
            ->assertSee($this->municipality->name)
            ->assertSee(route('harvest-records.import.template'), false)
            ->assertDontSee('name="municipality_id"', false);
    }

    public function test_the_template_downloads_privately_with_headings_and_allowed_values(): void
    {
        $response = $this->actingAs($this->staff)->get(route('harvest-records.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('harvest-records-import-template.xlsx', (string) $response->headers->get('Content-Disposition'));

        $file = $response->baseResponse->getFile()->getPathname();
        $spreadsheet = IOFactory::load($file);

        try {
            $harvests = $spreadsheet->getSheetByName(HarvestWorkbook::SHEET);
            $this->assertNotNull($harvests);
            $this->assertSame(
                [array_keys(HarvestWorkbook::COLUMNS)],
                $harvests->rangeToArray('A1:J1', null, false, false)
            );
            // Headings only: an example row would be imported as a real harvest.
            $this->assertSame(1, $harvests->getHighestDataRow());

            $allowed = $spreadsheet->getSheetByName(HarvestWorkbook::VALUES_SHEET);
            $this->assertNotNull($allowed);
            $values = collect($allowed->toArray(null, false, false))->flatten()->filter()->all();
            foreach (['rootcrop', 'Root crops', 'wet', 'Wet season', 'cavan', 'Cavans'] as $expected) {
                $this->assertContains($expected, $values);
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($file);
        }
    }

    /**
     * @return array<int, string>
     */
    private function fileErrors(): array
    {
        return session('errors')->getBag('default')->get('file');
    }

    private function upload(User $user, string $path, array $extra = []): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('harvest-records.import.form'))
            ->post(route('harvest-records.import'), $extra + [
                'file' => new UploadedFile($path, 'harvests.xlsx', null, null, true),
            ]);
    }

    /**
     * A synthetic harvest workbook, removed again when the test finishes.
     *
     * @param  array<int, array<string, mixed>>  $rows  Excel row number => heading => value
     * @param  array<int, string>|null  $headings
     */
    private function workbook(array $rows, ?array $headings = null): string
    {
        $canonical = array_keys(HarvestWorkbook::COLUMNS);
        $headings ??= $canonical;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(HarvestWorkbook::SHEET);
        $sheet->fromArray([$headings], null, 'A1');

        foreach ($rows as $rowNumber => $values) {
            foreach ($headings as $index => $heading) {
                // Exact heading first; restyled headings fall back to the template
                // heading in the same position.
                $value = $values[$heading] ?? $values[$canonical[$index] ?? ''] ?? null;
                if ($value !== null) {
                    $sheet->setCellValue([$index + 1, $rowNumber], $value);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'harvest_import_test').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    private function makeUser(string $role, ?int $municipalityId): User
    {
        return User::create([
            'name' => 'Synthetic '.$role,
            'email' => $role.'-'.str_replace('.', '', uniqid('', true)).'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'municipality_id' => $municipalityId,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);
    }

    private function makeMunicipality(string $label): Municipality
    {
        $suffix = str_replace('.', '', uniqid('', true));

        return Municipality::create([
            'name' => $label.' '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'HI'.substr($suffix, -8),
            'is_active' => true,
        ]);
    }
}

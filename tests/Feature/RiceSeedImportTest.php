<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HarvestFromRelease;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\OperationsViewFixtures;
use Tests\Support\PresentationProvinceSchema;
use Tests\TestCase;

class RiceSeedImportTest extends TestCase
{
    use PresentationProvinceSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->createPresentationScope();

        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('ffrs')->nullable();
            $table->string('rsbsa_no')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farmer_id')->nullable();
            foreach (['seed_variety_claimed', 'seed_variety_planted', 'lot_series', 'date_of_sowing_label'] as $field) {
                $table->string($field, 120)->nullable();
            }
            foreach (['last_name', 'first_name', 'middle_name', 'ffrs', 'farm_location', 'farm_province', 'farm_municipality', 'ecosystem', 'ecosystem_source'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['ext_name', 'contact_number', 'crop_establishment'] as $field) {
                $table->string($field, 50)->nullable();
            }
            $table->string('gender', 30)->nullable();
            $table->string('seed_class', 80)->nullable();
            $table->string('input_category', 40);
            $table->string('quantity_unit', 20);
            $table->string('consent_status', 20);
            $table->date('date_of_birth')->nullable();
            $table->date('date_received')->nullable();
            foreach (['claimed_area_ha', 'claimed_seeds_kg', 'kgs_received', 'avg_area_harvested_ha', 'farm_area_ha'] as $field) {
                $table->decimal($field, 10, 2)->nullable();
            }
            $table->integer('avg_weight_per_bag_kg')->nullable();
            $table->integer('total_production_bags')->nullable();
            $table->timestamps();
        });
        $this->mock(HarvestFromRelease::class)->shouldReceive('sync')->andReturnNull();
        $this->actingAs(OperationsViewFixtures::user());
    }

    public function test_valid_workbook_imports_and_reimport_updates_without_duplicates(): void
    {
        $lot = str_repeat('L', 120);
        $this->upload([['TEST-001', $lot, 40]])->assertRedirect(route('rice-seed-distributions.index'))->assertSessionHasNoErrors();
        $this->upload([['TEST-001', $lot, 80]])->assertRedirect(route('rice-seed-distributions.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('rice_seed_distributions', 1);
        $this->assertDatabaseHas('rice_seed_distributions', ['municipality_id' => 1, 'ffrs' => 'TEST-001', 'lot_series' => $lot, 'kgs_received' => 80]);
    }

    public function test_long_lot_series_gives_excel_row_error_and_rolls_back_earlier_rows(): void
    {
        $this->upload([['TEST-001', 'LOT-A', 40], ['TEST-002', str_repeat('L', 121), 40]])
            ->assertRedirect(route('rice-seed-distributions.import.form'))
            ->assertSessionHasErrors(['file' => 'Excel row 3: Lot Series exceeds 120 characters. Correct this cell and upload the workbook again. No rows were imported or updated.']);

        $this->assertDatabaseCount('rice_seed_distributions', 0);
    }

    public function test_rejected_reimport_preserves_existing_release(): void
    {
        $this->upload([['TEST-001', 'LOT-A', 40]])->assertSessionHasNoErrors();
        $this->upload([['TEST-001', 'LOT-A', 80], ['TEST-002', str_repeat('L', 121), 40]])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('rice_seed_distributions', 1);
        $this->assertDatabaseHas('rice_seed_distributions', ['ffrs' => 'TEST-001', 'kgs_received' => 40]);
    }

    public function test_multibyte_lot_series_uses_character_length_and_never_echoes_cell_values(): void
    {
        $this->upload([['TEST-001', str_repeat('ñ', 120), 40]])->assertSessionHasNoErrors();
        $privateCell = str_repeat('ñ', 121);
        $this->upload([['TEST-002', $privateCell, 40]])->assertSessionHasErrors('file');

        $this->assertStringNotContainsString($privateCell, session('errors')->first('file'));
        $this->assertDatabaseCount('rice_seed_distributions', 1);
    }

    public function test_farmer_matching_cannot_link_another_municipality(): void
    {
        DB::table('farmers')->insert(['id' => 10, 'municipality_id' => 2, 'ffrs' => 'TEST-001', 'first_name' => 'Outside', 'last_name' => 'Scope']);
        $this->upload([['TEST-001', 'LOT-A', 40]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rice_seed_distributions', ['municipality_id' => 1, 'farmer_id' => null, 'first_name' => 'Sample']);
        $this->assertDatabaseMissing('rice_seed_distributions', ['farmer_id' => 10]);
    }

    public function test_read_only_super_admin_cannot_import(): void
    {
        $this->actingAs(OperationsViewFixtures::user(User::ROLE_SUPER_ADMIN));
        $this->upload([['TEST-001', 'LOT-A', 40]])->assertForbidden();
        $this->assertDatabaseCount('rice_seed_distributions', 0);
    }

    public function test_municipal_staff_cannot_redirect_import_to_another_municipality(): void
    {
        $this->upload([['TEST-001', 'LOT-A', 40]], 2)->assertSessionHasErrors('municipality_id');
        $this->assertDatabaseCount('rice_seed_distributions', 0);
    }

    public function test_matching_farmer_in_selected_municipality_is_linked(): void
    {
        DB::table('farmers')->insert(['id' => 11, 'municipality_id' => 1, 'ffrs' => 'TEST-001', 'first_name' => 'Linked', 'last_name' => 'Farmer']);
        $this->upload([['TEST-001', 'LOT-A', 40]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rice_seed_distributions', ['municipality_id' => 1, 'farmer_id' => 11, 'first_name' => 'Linked']);
    }

    public function test_invalid_workbook_returns_helpful_error_without_saving_rows(): void
    {
        $file = UploadedFile::fake()->createWithContent('invalid.xlsx', 'This is not an Excel workbook.');
        $this->from(route('rice-seed-distributions.import.form'))
            ->post(route('rice-seed-distributions.import'), ['file' => $file])
            ->assertRedirect(route('rice-seed-distributions.import.form'))
            ->assertSessionHasErrors(['file' => 'The workbook could not be read. Open it in Excel, save a new .xlsx copy, and upload it again. No rows were imported or updated.']);
        $this->assertDatabaseCount('rice_seed_distributions', 0);
    }

    /** @param array<int, array{string, string, int}> $rows */
    private function upload(array $rows, ?int $municipalityId = null): \Illuminate\Testing\TestResponse
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('NRP DISTRIBUTION');
        $sheet->fromArray(['FFRS RSBSA Number', 'Lot Series', 'Claimed seeds (kg)', 'Farmer First Name', 'Farmer Last Name'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray([...$row, 'Sample', 'Farmer'], null, 'A'.($index + 2));
        }
        $path = tempnam(sys_get_temp_dir(), 'rice-import-');
        try {
            (new Xlsx($workbook))->save($path);
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            return $this->from(route('rice-seed-distributions.import.form'))->post(route('rice-seed-distributions.import'), ['file' => $file, 'municipality_id' => $municipalityId]);
        } finally {
            $workbook->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * What has to be true of a file that leaves this system.
 *
 * Two things. It must be recorded, because once a copy of the register is on
 * somebody's laptop the system has no further say in where it goes and the audit
 * trail is the only evidence it left at all. And it must not carry anything that runs
 * when the file is opened — a name typed into a registry field is not a formula.
 */
class ExportSafetyTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $municipality;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));

        $this->municipality = Municipality::create([
            'name' => 'Export Test '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'EX'.substr($suffix, -8),
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Export Staff',
            'email' => 'export-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);
    }

    public function test_exporting_the_assistance_register_is_recorded_with_its_filters(): void
    {
        // This file carries every recipient's date of birth, gender and six
        // eligibility flags. Taking a copy of it is an event worth being able to
        // account for afterwards.
        $farmer = $this->farmer();
        RiceSeedDistribution::create([
            'municipality_id' => $this->municipality->id,
            'farmer_id' => $farmer->id,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'kgs_received' => 40,
            'date_received' => '2025-03-01',
        ]);

        $before = AuditLog::query()->where('event', 'exported')->count();

        $this->actingAs($this->staff)
            ->get(route('rice-seed-distributions.export', ['gender' => 'Female']))
            ->assertOk();

        $entry = AuditLog::query()->where('event', 'exported')
            ->where('module', 'Assistance distributions')
            ->latest('id')->first();

        $this->assertSame($before + 1, AuditLog::query()->where('event', 'exported')->count());
        $this->assertNotNull($entry, 'Exporting the assistance register recorded nothing.');

        $metadata = is_string($entry->metadata) ? json_decode($entry->metadata, true) : (array) $entry->metadata;
        $metadata = $metadata['metadata'] ?? $metadata;

        // "Who exported the register" and "who exported the female recipients" are
        // different events; the row count alone cannot tell them apart.
        $this->assertSame('Female', data_get($metadata, 'filters.gender'));
        $this->assertTrue((bool) data_get($metadata, 'includes_personal_data'));
    }

    public function test_the_assistance_export_records_only_the_filters_that_were_used(): void
    {
        $this->actingAs($this->staff)
            ->get(route('rice-seed-distributions.export'))
            ->assertOk();

        $entry = AuditLog::query()->where('event', 'exported')
            ->where('module', 'Assistance distributions')
            ->latest('id')->firstOrFail();

        $metadata = is_string($entry->metadata) ? json_decode($entry->metadata, true) : (array) $entry->metadata;
        $filters = data_get($metadata, 'metadata.filters', data_get($metadata, 'filters', []));

        // An unfiltered export should not read as if a dozen blank filters were set.
        $this->assertSame([], array_filter((array) $filters, fn ($v) => $v !== null && $v !== ''));
    }

    public function test_the_assistance_export_says_which_municipality_each_release_belonged_to(): void
    {
        // Barangay names repeat across municipalities, so a provincial export without
        // this column produces rows that cannot be told apart.
        $farmer = $this->farmer();
        RiceSeedDistribution::create([
            'municipality_id' => $this->municipality->id,
            'farmer_id' => $farmer->id,
            'input_category' => 'rice_seed',
            'quantity_unit' => 'kg',
            'kgs_received' => 40,
            'date_received' => '2025-03-01',
            'last_name' => $farmer->last_name,
            'first_name' => $farmer->first_name,
            'farm_location' => 'Barangay Uno',
            'farm_municipality' => $this->municipality->name,
            'farm_province' => 'Tarlac',
        ]);

        $csv = $this->actingAs($this->staff)
            ->get(route('rice-seed-distributions.export'))
            ->assertOk()
            ->streamedContent();

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $headings = $rows[0];

        $this->assertContains('Municipality', $headings);
        $this->assertContains('Province', $headings);

        $index = array_search('Municipality', $headings, true);
        $this->assertSame($this->municipality->name, $rows[1][$index]);
    }

    public function test_exporting_a_cooperative_membership_is_recorded(): void
    {
        $cooperative = $this->cooperative();

        $this->actingAs($this->staff)
            ->get(route('farmers-cooperatives.export-excel', $cooperative))
            ->assertOk();

        $this->assertTrue(
            AuditLog::query()->where('event', 'exported')->where('module', 'Cooperatives')->exists(),
            'Exporting a cooperative membership recorded nothing.'
        );
    }

    public function test_a_name_that_looks_like_a_formula_leaves_the_workbook_as_text(): void
    {
        // A registry field is free text. If someone types this into a farmer's name,
        // the exported sheet must show those characters, not run them.
        $cooperative = $this->cooperative();
        $hostile = $this->farmer(['last_name' => '=1+1', 'first_name' => '@SUM(A1:A9)']);
        $cooperative->farmers()->attach($hostile->id);

        $response = $this->actingAs($this->staff)
            ->get(route('farmers-cooperatives.export-excel', $cooperative))
            ->assertOk();

        // The workbook is returned as a file download, not a stream.
        $path = tempnam(sys_get_temp_dir(), 'coop').'.xlsx';
        $file = $response->baseResponse->getFile();
        copy($file->getPathname(), $path);

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $found = [];
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $value = (string) $cell->getValue();
                    if ($value !== '') {
                        $found[] = $value;
                    }
                    $this->assertNotSame(
                        'f',
                        $cell->getDataType(),
                        'A cell was written as a formula: '.$value
                    );
                }
            }

            $joined = implode('|', $found);
            $this->assertStringContainsString('1+1', $joined, 'The hostile value never reached the sheet.');
        } finally {
            @unlink($path);
        }
    }

    private function cooperative(): FarmersCooperative
    {
        return FarmersCooperative::create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Export Cooperative '.uniqid(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function farmer(array $attributes = []): Farmer
    {
        return Farmer::create(array_merge([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Export',
            'last_name' => 'Recipient',
            'gender' => 'Female',
            'date_of_birth' => '1980-05-05',
            'farm_location' => 'Barangay Uno',
        ], $attributes));
    }
}

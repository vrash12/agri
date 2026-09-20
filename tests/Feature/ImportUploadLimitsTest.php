<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Support\ProvinceScopedFixtures;
use Tests\TestCase;

/**
 * Bounds on the file imports.
 *
 * Parsing a workbook or a survey KML is the most expensive thing an account can ask
 * this application to do, and the parsing is done by a third-party library whose
 * published advisories include CPU and memory exhaustion from small, deliberately
 * crafted files. An unbounded upload on a shared host is therefore not merely
 * untidy: one authorised member of staff, or one stolen session, can take the office
 * offline without exploiting anything.
 *
 * The Backup Folder and the boundary importer were already bounded. These three were
 * not, which is what these tests hold in place.
 */
class ImportUploadLimitsTest extends TestCase
{
    use DatabaseTransactions, ProvinceScopedFixtures;

    private Municipality $municipality;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));

        $this->municipality = Municipality::create([
            'name' => 'Import Limits '.$suffix,
            'province' => 'Tarlac',
            'province_id' => $this->supervisingProvinceId(),
            'code' => 'IL'.substr($suffix, -8),
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Import Clerk',
            'email' => 'import-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->municipality->id,
            'province_id' => $this->supervisingProvinceId(),
            'is_active' => true,
        ]);
    }

    public function test_an_oversized_workbook_is_refused_by_the_farmer_import(): void
    {
        $this->actingAs($this->staff)
            ->post(route('farmers.import'), [
                'file' => UploadedFile::fake()->create('registry.xlsx', 11 * 1024),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(
            0,
            Farmer::query()->where('municipality_id', $this->municipality->id)->count(),
            'An oversized workbook was parsed far enough to create records.'
        );
    }

    public function test_an_oversized_workbook_is_refused_by_the_assistance_import(): void
    {
        $this->actingAs($this->staff)
            ->post(route('rice-seed-distributions.import'), [
                'file' => UploadedFile::fake()->create('nrp.xlsx', 11 * 1024),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_an_oversized_survey_file_is_refused_by_the_parcel_import(): void
    {
        // Larger than the workbook cap on purpose: the real Ramos parcel survey is
        // 6.6 MB, so this one has to admit a bigger municipality than that.
        $this->actingAs($this->staff)
            ->post(route('farm-plots.import'), [
                'municipality_id' => $this->municipality->id,
                'file' => UploadedFile::fake()->create('parcels.kml', 26 * 1024),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_a_survey_file_the_size_of_a_real_municipality_is_still_accepted(): void
    {
        // The cap has to bound the parser without refusing the office's own data.
        // A 7 MB KML is larger than the real Ramos survey and must not be rejected
        // for its size; it may fail later for its contents, which is a different
        // message on a different field.
        $response = $this->actingAs($this->staff)
            ->post(route('farm-plots.import'), [
                'municipality_id' => $this->municipality->id,
                'file' => UploadedFile::fake()->create('parcels.kml', 7 * 1024),
            ]);

        $errors = session('errors');

        $this->assertFalse(
            $errors && $errors->has('file') && str_contains((string) $errors->first('file'), 'greater than'),
            'A survey file smaller than a real municipality was rejected for its size.'
        );
    }

    public function test_every_import_endpoint_is_rate_limited(): void
    {
        // The cap bounds one upload; the throttle bounds how many can be sent. Both
        // are needed, because the library's exhaustion advisories are about small
        // crafted files rather than large ones.
        $expected = [
            'farmers.import',
            'rice-seed-distributions.import',
            'farm-plots.import',
            'municipality-boundaries.import',
        ];

        foreach ($expected as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "The route {$name} no longer exists.");

            $throttled = collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware)
                    && str_contains(strtolower($middleware), 'throttle'));

            $this->assertTrue($throttled, "The import route {$name} is not rate limited.");
        }
    }

    public function test_the_upload_rules_check_size_before_reading_the_file(): void
    {
        // Order matters: `bail` plus a size rule ahead of the content check means an
        // oversized upload is refused before anything inspects its bytes.
        foreach ([
            'app/Http/Controllers/FarmerController.php',
            'app/Http/Controllers/RiceSeedDistributionController.php',
            'app/Http/Controllers/FarmPlotController.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertMatchesRegularExpression(
                "/'bail',\s*'required',\s*'file',\s*'max:\d+',\s*'mimes:/",
                $source,
                $file.' no longer bounds its upload before inspecting it.'
            );
        }
    }
}

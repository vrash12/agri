<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\PresentationProvinceSchema;
use Tests\TestCase;

class FarmerWorkspacePresentationTest extends TestCase
{
    use PresentationProvinceSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'services.google_maps.key' => '', 'services.google_maps.map_id' => '']);
        DB::purge('sqlite');
        $this->createPresentationScope();
    }

    public function test_directory_has_five_columns_and_preserves_direct_record_actions(): void
    {
        $data = $this->fixture(User::ROLE_MUNICIPAL_STAFF);
        $view = $this->view('farmers.index', $data);
        $xpath = $this->xpath((string) $view);

        $this->assertSame(5, $xpath->query('//table[@id="farmersTable"]/thead/tr/th')->length);
        $this->assertSame(1, $xpath->query('//details[@id="farmerMapWorkspace" and @open]')->length);
        $this->assertSame(1, $xpath->query('//details[@id="farmerInsights" and not(@open)]')->length);
        $view->assertSee('Details &amp; history', false)->assertSee('Digital ID')->assertSee('Edit profile')->assertSee('Open parcel map');
        $view->assertSee('Gender breakdown')->assertSee('farmersMapModule')
            ->assertSee('parcelSatelliteModal')->assertSee('Satellite field check')
            ->assertSee('All parcels with saved crops')->assertSee('Filter parcels by saved crop')
            ->assertSee('White outlines mark parcel boundaries.');
    }

    public function test_registry_insights_are_readable_bars_with_counts_without_a_table_or_chart_loader(): void
    {
        $data = $this->fixture(User::ROLE_MUNICIPAL_STAFF);
        $data['totalFarmers'] = 10;
        $data['genderStats'] = ['Female' => 3, 'Male' => 7];
        $data['locationStats'] = ['North' => 3, 'South' => 2, 'East' => 1, 'West' => 1, 'Hillside' => 1, 'Riverside' => 1];
        $view = $this->view('farmers.index', $data);
        $html = (string) $view;
        $xpath = $this->xpath($html);
        $section = '//*[@id="farmerInsights"]';

        $this->assertSame(0, $xpath->query($section.'//table | '.$section.'//canvas')->length);
        $this->assertSame(2, $xpath->query($section.'//figure[@aria-labelledby]')->length);
        $this->assertSame(7, $xpath->query($section.'//figure/dl/div[@data-insight-row]')->length);
        $this->assertSame(1, $xpath->query($section.'//details[not(@open)]/dl/div[@data-insight-row]')->length);
        $view->assertSee('10</strong> matching farmers', false)->assertSee('30.0%')->assertSee('70.0%')
            ->assertSee('Location 6')->assertSee('1</strong> profile in other or unrecorded locations', false)
            ->assertSee('width:30.000%', false)->assertSee('css/farmer-registry-insights.css');
        $view->assertDontSee('genderChart')->assertDontSee('__genderStats')->assertDontSee('showCharts');
    }

    public function test_insights_explain_empty_results_and_missing_breakdowns_without_nan_or_false_percentages(): void
    {
        $data = $this->fixture(User::ROLE_MUNICIPAL_STAFF);
        $data['totalFarmers'] = 0;
        $data['genderStats'] = [];
        $data['locationStats'] = [];
        $view = $this->view('farmers.index', $data);
        $xpath = $this->xpath((string) $view);
        $this->assertSame(0, $xpath->query('//*[@id="farmerInsights"]//figure')->length);
        $view->assertSee('No matching farmer profiles');
        $emptyInsights = $xpath->query('//*[@id="farmerInsights"]')->item(0)->textContent;
        $this->assertStringNotContainsString('NaN', $emptyInsights);
        $this->assertStringNotContainsString('INF', $emptyInsights);

        $data['totalFarmers'] = 4;
        $data['locationStats'] = ['' => 1, 'UNKNOWN' => 3];
        $view = $this->view('farmers.index', $data);
        $view->assertSee('No gender figures are available')->assertSee('No recorded farm locations are available')
            ->assertSee('4</strong> profiles in other or unrecorded locations', false);
    }

    public function test_insights_escape_labels_and_keep_small_shares_and_location_counts_bounded(): void
    {
        $data = $this->fixture(User::ROLE_MUNICIPAL_STAFF);
        $data['totalFarmers'] = 10000;
        $data['genderStats'] = ['' => 1, '<script>bad()</script>' => 9999];
        $data['locationStats'] = collect(range(1, 12))->mapWithKeys(fn ($i) => ['Long farm location '.$i => 1])->all();
        $view = $this->view('farmers.index', $data);
        $xpath = $this->xpath((string) $view);
        $this->assertSame(10, $xpath->query('//*[@aria-labelledby="registryLocationHeading"]//*[@data-insight-row]')->length);
        $view->assertSee('Not recorded')->assertSee('&lt;0.1%', false)->assertSee('width:0.010%', false)
            ->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false)
            ->assertSee('9,990</strong> profiles in other or unrecorded locations', false);
    }

    public function test_super_admin_directory_keeps_read_only_actions(): void
    {
        $view = $this->view('farmers.index', $this->fixture(User::ROLE_SUPER_ADMIN));
        $view->assertSee('Read-only oversight')->assertSee('Digital ID')->assertSee('Details &amp; history', false);
        $view->assertDontSee('Edit profile')->assertDontSee('Delete profile')->assertDontSee('Add farmer')->assertDontSee('Import workbook');
    }

    public function test_parcel_charts_wait_for_a_selected_farmer_and_use_local_assets_for_staff_and_oversight(): void
    {
        foreach ([User::ROLE_MUNICIPAL_STAFF, User::ROLE_SUPER_ADMIN] as $role) {
            $view = $this->view('farmers.index', $this->fixture($role));
            $html = (string) $view;
            $xpath = $this->xpath($html);

            $this->assertSame(1, $xpath->query('//*[@id="farmerParcelStats"]')->length);
            $this->assertSame(1, $xpath->query('//*[@data-parcel-stats-content and @hidden]')->length);
            $this->assertSame(1, $xpath->query('//*[@data-parcel-stats-status and @role="status"]')->length);
            $this->assertSame(1, $xpath->query('//button[@data-parcel-stats-retry and @type="button" and @hidden]')->length);
            $view->assertSee('Choose a farmer to see their saved parcels and crops.')
                ->assertSee('Land by parcel')->assertSee('Crops on these parcels')
                ->assertSee('css/farmer-parcel-stats.css')->assertSee('js/farmer-parcel-stats.js');
            $this->assertLessThan(strpos($html, 'js/farmers-maps.js'), strpos($html, 'js/farmer-parcel-stats.js'));
        }
    }

    public function test_profile_navigation_keeps_the_farmer_municipality_when_returning_to_registry_or_map(): void
    {
        $data = $this->fixture(User::ROLE_SYSTEM_OWNER);
        $view = $this->view('farmers.partials.workspace-nav', ['workspaceMunicipality' => $data['selectedMunicipality']]);
        $xpath = $this->xpath((string) $view);
        $directory = route('farmers.index', ['municipality_id' => 1]);

        $this->assertSame($directory.'#farmerDirectory', $xpath->query('//a[@data-workspace-target="registry"]')->item(0)->getAttribute('href'));
        $this->assertSame($directory.'#farmersMapModule', $xpath->query('//a[@data-workspace-target="map"]')->item(0)->getAttribute('href'));
        $view->assertSee('Change municipality');
    }

    public function test_parcel_workspace_prioritizes_saved_parcels_and_keeps_optional_details_accessible(): void
    {
        $view = $this->view('farmers.index', $this->fixture(User::ROLE_MUNICIPAL_STAFF));
        $html = (string) $view;
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//input[@id="mapFarmerSearch" and @aria-describedby="mapPickerHelp"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="mapPickerHelp" and @role="status"]')->length);
        $this->assertSame(1, $xpath->query('//section[@id="farmerSavedParcels" and @tabindex="-1"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="plotList" and @aria-busy="false"]')->length);
        $this->assertSame(1, $xpath->query('//details[not(@open)]//*[@id="farmerParcelStats"]')->length);
        $this->assertLessThan(strpos($html, 'id="selOwnerName"'), strpos($html, 'id="plotList"'));
        $view->assertSee('Open farmer')->assertSee('Saved parcels')->assertSee('Choose a farmer to see their parcels')
            ->assertSee('Map unavailable? Open the farmer registry')->assertSee('Show this period')
            ->assertSee('does not mean the land is unplanted');
        $view->assertDontSee('No saved plots yet');
    }

    public function test_required_farmer_fields_are_visible_and_errors_reveal_optional_details(): void
    {
        $data = $this->fixture(User::ROLE_PROVINCIAL_STAFF);
        $data['record'] = new Farmer;
        $view = $this->view('farmers.create', $data);
        $xpath = $this->xpath((string) $view);
        $this->assertSame(0, $xpath->query('//details[not(@open)]//*[@required]')->length);
        $this->assertSame(3, $xpath->query('//input[@required] | //select[@required]')->length);

        $data['errors'] = (new ViewErrorBag)->put('default', new MessageBag(['contact_number' => 'Use a shorter contact number.']));
        $view = $this->view('farmers.create', $data);
        $xpath = $this->xpath((string) $view);
        $this->assertSame(1, $xpath->query('//details[@open]//input[@id="contact_number" and @aria-invalid="true"]')->length);
        $view->assertSee('contact_number-error')->assertSee('Use a shorter contact number.');
    }

    public function test_edit_keeps_existing_optional_values_and_record_version(): void
    {
        $data = $this->fixture(User::ROLE_MUNICIPAL_STAFF);
        $data['record'] = $data['mapFarmers']->first();
        $data['record']->contact_number = 'DEMO-CONTACT';
        $data['record']->exists = true;
        $view = $this->view('farmers.edit', $data);
        $xpath = $this->xpath((string) $view);
        $this->assertSame(1, $xpath->query('//details[@open]//input[@name="contact_number" and @value="DEMO-CONTACT"]')->length);
        $view->assertSee('_record_version')->assertSee('Save changes');
    }

    private function fixture(string $role): array
    {
        $municipality = new Municipality(['name' => 'Sample Municipality', 'province' => 'Tarlac', 'is_active' => true]);
        $municipality->id = 1;
        $user = new User(['name' => 'Preview Staff', 'role' => $role, 'municipality_id' => 1, 'is_active' => true]);
        $user->id = 1;
        $user->setRelation('municipality', $municipality);
        $this->actingAs($user);
        $farmer = new Farmer(['first_name' => 'Demo', 'last_name' => 'Grower', 'municipality_id' => 1, 'farm_location' => 'Sample Barangay', 'farm_municipality' => 'Sample Municipality', 'farm_area_ha' => 2, 'updated_at' => '2026-09-07 00:00:00']);
        $farmer->id = 1;
        $farmer->setRelation('municipality', $municipality);

        return [
            'errors' => new ViewErrorBag,
            'farmers' => new LengthAwarePaginator([$farmer], 1, 25), 'mapFarmers' => collect([$farmer]),
            'perPage' => 25, 'totalFarmers' => 1, 'locationCount' => 1, 'mappingCoverage' => 0,
            'mappedFarmers' => 0, 'totalPlots' => 0, 'totalKgs' => 0, 'missingFfrs' => 1,
            'selectedMunicipality' => $municipality, 'municipalities' => collect([$municipality]),
            'mapFarmerCount' => 1, 'mapMappedFarmerCount' => 0, 'mapPlotCount' => 0, 'mapAreaHa' => 0,
            'mapMunicipalityBoundaries' => collect(), 'canChooseMunicipality' => $user->canAccessAllMunicipalities(),
            'genderStats' => ['Unspecified' => 1], 'locationStats' => ['Sample Barangay' => 1],
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}

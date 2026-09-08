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
use Tests\TestCase;

class FarmerWorkspacePresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'services.google_maps.key' => '', 'services.google_maps.map_id' => '']);
        DB::purge('sqlite');
    }

    public function test_directory_has_five_columns_and_preserves_direct_record_actions(): void
    {
        $data = $this->fixture(User::ROLE_MUNICIPAL_STAFF);
        $view = $this->view('farmers.index', $data);
        $xpath = $this->xpath((string) $view);

        $this->assertSame(5, $xpath->query('//table[@id="farmersTable"]/thead/tr/th')->length);
        $this->assertSame(1, $xpath->query('//details[@id="farmerMapWorkspace" and not(@open)]')->length);
        $this->assertSame(1, $xpath->query('//details[@id="farmerInsights" and not(@open)]')->length);
        $view->assertSee('Details &amp; history', false)->assertSee('Digital ID')->assertSee('Edit profile')->assertSee('Open parcel map');
        $view->assertSee('Registry figures for the current filters')->assertSee('farmersMapModule');
    }

    public function test_super_admin_directory_keeps_read_only_actions(): void
    {
        $view = $this->view('farmers.index', $this->fixture(User::ROLE_SUPER_ADMIN));
        $view->assertSee('Read-only oversight')->assertSee('Digital ID')->assertSee('Details &amp; history', false);
        $view->assertDontSee('Edit profile')->assertDontSee('Delete profile')->assertDontSee('Add farmer')->assertDontSee('Import workbook');
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

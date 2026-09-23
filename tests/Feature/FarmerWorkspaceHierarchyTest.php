<?php

namespace Tests\Feature;

use App\Http\Controllers\FarmerController;
use App\Http\Controllers\FarmPlotController;
use App\Models\User;
use App\Support\FarmerWorkspace;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FarmerWorkspaceHierarchyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never inherit an external database URL for the isolated test schema.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'services.google_maps.key' => '',
            'services.google_maps.map_id' => '',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        view()->share('errors', new ViewErrorBag);
        $this->schema();

        DB::table('regions')->insert([['id' => 1, 'name' => 'Region Alpha'], ['id' => 2, 'name' => 'Region Beta']]);
        DB::table('provinces')->insert([
            ['id' => 1, 'name' => 'Province Alpha', 'region_id' => 1],
            ['id' => 2, 'name' => 'Independent City', 'region_id' => 1],
            ['id' => 3, 'name' => 'Province Beta', 'region_id' => 2],
            ['id' => 4, 'name' => 'Unassigned Province', 'region_id' => null],
            ['id' => 5, 'name' => 'Province Gamma', 'region_id' => 1],
        ]);
        for ($id = 1; $id <= 6; $id++) {
            $provinceId = $id === 6 ? 1 : $id;
            DB::table('municipalities')->insert([
                'id' => $id, 'name' => 'Town '.$id, 'province' => 'Province '.$provinceId, 'province_id' => $provinceId,
            ]);
            DB::table('farmers')->insert([
                'id' => $id, 'municipality_id' => $id, 'first_name' => 'Farmer '.$id,
                'last_name' => 'Grower', 'farm_location' => 'Barangay '.$id, 'gender' => 'Female',
            ]);
            DB::table('rice_seed_distributions')->insert([
                'farmer_id' => $id, 'municipality_id' => $id, 'quantity_unit' => 'kg', 'kgs_received' => 10,
            ]);
            DB::table('farm_plots')->insert([
                'id' => $id, 'farmer_id' => $id, 'name' => 'Parcel '.$id, 'area_ha' => 1,
                'polygon_json' => '[]', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('municipality_boundaries')->insert([
                'id' => $id, 'municipality_id' => $id, 'name' => 'Boundary '.$id, 'status' => 'active',
                'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[[120, 15], [121, 15], [121, 16], [120, 15]]]]),
                'area_ha' => 1, 'centroid_lat' => 15.3, 'centroid_lng' => 120.7,
            ]);
        }
    }

    public function test_owner_immediately_sees_all_active_records_and_can_narrow_by_region_or_province(): void
    {
        $owner = $this->user(User::ROLE_SYSTEM_OWNER);
        foreach ([
            [[], [1, 2, 3, 4, 5, 6], 'All supervised provinces'],
            [['region_id' => '1'], [1, 2, 5, 6], 'Region Alpha'],
            [['region_id' => '1', 'province_id' => '1'], [1, 6], 'Province Alpha'],
            [['province_id' => '2'], [2], 'Independent City'],
            [['region_id' => 'unassigned'], [4], 'Region not assigned'],
        ] as [$input, $expectedIds, $name]) {
            $view = $this->index($owner, $input);
            $this->assertSame('farmers.index', $view->name());
            $data = $view->getData();
            $this->assertSame($expectedIds, $data['workspaceMunicipalityIds']);
            $this->assertEqualsCanonicalizing($expectedIds, $data['farmers']->pluck('id')->all());
            $this->assertSame(count($expectedIds), $data['totalFarmers']);
            $this->assertSame(count($expectedIds), $data['mapFarmerCount']);
            $this->assertSame(count($expectedIds), $data['mapPlotCount']);
            $this->assertEquals(count($expectedIds) * 10, $data['totalKgs']);
            $this->assertSame($expectedIds, $data['mapMunicipalityBoundaries']->pluck('municipality_id')->all());
            $this->assertSame($name, $data['workspaceName']);
            $this->assertNull($data['selectedMunicipality']);
        }
    }

    public function test_region_choices_include_separate_city_scopes_and_province_filter_limits_municipalities(): void
    {
        $owner = $this->user(User::ROLE_SYSTEM_OWNER);
        $data = $this->resolve($owner, ['region_id' => '1']);
        $this->assertSame([2, 1, 5], $data['workspaceProvinces']->pluck('id')->all());
        $this->assertSame([1, 2, 5, 6], $data['municipalities']->pluck('id')->all());
        $this->assertInstanceOf(Collection::class, $data['municipalities']);
        $this->assertSame(['region_id' => '1'], $data['workspaceParameters']);

        $data = $this->resolve($owner, ['province_id' => '1']);
        $this->assertSame('1', $data['workspaceRegionId']);
        $this->assertSame([1, 6], $data['municipalities']->pluck('id')->all());
        $this->assertSame([1, 6], $data['workspaceMunicipalityIds']);
        $this->assertSame(['region_id' => '1', 'province_id' => 1], $data['workspaceParameters']);

        $data = $this->resolve($owner, ['municipality_id' => '1']);
        $this->assertSame(1, $data['selectedMunicipality']->id);
        $this->assertSame([1], $data['workspaceMunicipalityIds']);
        $this->assertSame([1, 6], $data['municipalities']->pluck('id')->all(), 'Other municipalities remain available when changing the optional filter.');
    }

    public function test_municipality_bookmarks_restore_the_hierarchy_and_unassigned_regions_remain_reachable(): void
    {
        $data = $this->resolve($this->user(User::ROLE_SYSTEM_OWNER), ['municipality_id' => '4']);
        $this->assertSame(4, $data['selectedMunicipality']->id);
        $this->assertSame('unassigned', $data['workspaceRegionId']);
        $this->assertSame('Region not assigned', $data['workspaceRegionName']);
        $this->assertSame([4], $data['workspaceMunicipalityIds']);
        $this->assertSame(['region_id' => 'unassigned', 'municipality_id' => 4], $data['workspaceParameters']);
    }

    public function test_assigned_roles_immediately_see_only_their_authorized_records(): void
    {
        foreach ([
            [$this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]), [1, 2, 5, 6]],
            [$this->user(User::ROLE_SUPER_ADMIN, ['province_id' => 1]), [1, 6]],
            [$this->user(User::ROLE_PROVINCIAL_STAFF, ['province_id' => 1]), [1, 6]],
            [$this->user(User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 1]), [1]],
        ] as [$user, $expectedIds]) {
            $data = $this->index($user)->getData();
            $this->assertSame($expectedIds, $data['workspaceMunicipalityIds']);
            $this->assertEqualsCanonicalizing($expectedIds, $data['farmers']->pluck('id')->all());
            $this->assertSame($expectedIds, $data['mapMunicipalityBoundaries']->pluck('municipality_id')->all());
        }
        $regional = $this->resolve($this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]));
        $this->assertSame(['1'], $regional['workspaceRegions']->pluck('id')->all());
        $this->assertSame([], $regional['workspaceParameters']);
        $municipal = $this->index($this->user(User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 1]), ['municipality_id' => '3'])->getData();
        $this->assertSame([1], $municipal['workspaceMunicipalityIds']);
        $this->assertSame([1], $municipal['farmers']->pluck('id')->all());
    }

    public function test_filters_cannot_expand_an_assigned_scope(): void
    {
        $regional = $this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]);
        $provincial = $this->user(User::ROLE_SUPER_ADMIN, ['province_id' => 1]);
        foreach ([
            [$regional, ['region_id' => '2']], [$regional, ['province_id' => '3']],
            [$regional, ['municipality_id' => '3']], [$provincial, ['municipality_id' => '3']],
            [$provincial, ['region_id' => '1', 'municipality_id' => '5']], [$provincial, ['province_id' => '5']],
        ] as [$user, $input]) {
            $this->assertRejected($user, $input);
        }
    }

    public function test_inactive_municipalities_and_provinces_are_excluded_from_default_records_and_map(): void
    {
        DB::table('municipalities')->where('id', 3)->update(['is_active' => false]);
        DB::table('provinces')->where('id', 1)->update(['is_active' => false]);
        $data = $this->index($this->user(User::ROLE_SYSTEM_OWNER))->getData();
        $this->assertSame([2, 4, 5], $data['workspaceMunicipalityIds']);
        $this->assertEqualsCanonicalizing([2, 4, 5], $data['farmers']->pluck('id')->all());
        $this->assertSame([2, 4, 5], $data['mapMunicipalityBoundaries']->pluck('municipality_id')->all());
    }

    public function test_offices_without_active_municipalities_render_the_empty_registry_and_map(): void
    {
        DB::table('municipalities')->update(['is_active' => false]);
        foreach ([
            $this->user(User::ROLE_SYSTEM_OWNER),
            $this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]),
            $this->user(User::ROLE_SUPER_ADMIN, ['province_id' => 1]),
        ] as $user) {
            $view = $this->index($user);
            $this->assertSame('farmers.index', $view->name());
            $data = $view->getData();
            $this->assertNull($data['selectedMunicipality']);
            $this->assertSame([], $data['workspaceMunicipalityIds']);
            $this->assertCount(0, $data['municipalities']);
            $this->assertSame(0, $data['farmers']->total());
            $this->assertSame(0, $data['mapFarmerCount']);
            $this->assertCount(0, $data['mapMunicipalityBoundaries']);
            $html = $view->render();
            $xpath = $this->xpath($html);
            $this->assertSame(1, $xpath->query('//table[@id="farmersTable"]')->length);
            $this->assertSame(1, $xpath->query('//details[@id="farmerMapWorkspace" and @open]')->length);
            $this->assertStringContainsString('No municipalities are available', $html);
            $this->assertRejected($user, ['municipality_id' => '1']);
            $this->assertRejected($user, ['province_id' => '1']);
            $this->assertRejected($user, ['region_id' => '2']);
        }
    }

    public function test_invalid_mismatched_and_inactive_choices_are_rejected(): void
    {
        $owner = $this->user(User::ROLE_SYSTEM_OWNER);
        foreach ([
            ['region_id' => ['1']], ['region_id' => '999'], ['province_id' => 'bad'],
            ['municipality_id' => '999'], ['municipality_id' => ['1']], ['municipality_id' => '0'],
            ['region_id' => '2', 'province_id' => '1'], ['province_id' => '2', 'municipality_id' => '1'],
            ['region_id' => '1', 'municipality_id' => '3'],
        ] as $input) {
            $this->assertRejected($owner, $input);
        }
        DB::table('municipalities')->where('id', 3)->update(['is_active' => false]);
        $this->assertRejected($owner, ['municipality_id' => '3']);
        DB::table('provinces')->where('id', 1)->update(['is_active' => false]);
        $this->assertRejected($owner, ['province_id' => '1']);
    }

    public function test_every_role_renders_the_directory_and_open_map_without_a_required_chooser(): void
    {
        foreach ([
            [$this->user(User::ROLE_SYSTEM_OWNER), 'workspaceRegion'],
            [$this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]), 'workspaceProvince'],
            [$this->user(User::ROLE_SUPER_ADMIN, ['province_id' => 1]), 'workspaceMunicipality'],
            [$this->user(User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 1]), null],
        ] as [$user, $selector]) {
            $xpath = $this->xpath($this->index($user)->render());
            $this->assertSame(1, $xpath->query('//table[@id="farmersTable"]')->length);
            $this->assertSame(1, $xpath->query('//details[@id="farmerMapWorkspace" and @open]')->length);
            $this->assertSame(0, $xpath->query('//section[@data-farmer-workspace-control]//select[@required or @disabled]')->length);
            if ($selector) {
                $this->assertSame(1, $xpath->query('//select[@id="'.$selector.'"]/option[@value=""]')->length);
            } else {
                $this->assertSame(0, $xpath->query('//section[@data-farmer-workspace-control]//select')->length);
            }
        }
    }

    public function test_overview_uses_the_region_and_municipality_controls_above_the_visible_directory(): void
    {
        $xpath = $this->xpath($this->index($this->user(User::ROLE_SYSTEM_OWNER))->render());

        $this->assertSame(1, $xpath->query('//h2[normalize-space()="Choose a municipality"]')->length);
        $this->assertSame(1, $xpath->query('//button[normalize-space()="Show municipalities"]')->length);
        $this->assertSame(1, $xpath->query('//button[normalize-space()="View farmers"]')->length);
        $this->assertSame(1, $xpath->query('//table[@id="farmersTable"]')->length);
        $this->assertSame(1, $xpath->query('//details[@id="farmerMapWorkspace" and @open]')->length);
    }

    public function test_scope_forms_preserve_registry_filters_and_clear_stale_children(): void
    {
        $input = ['region_id' => '1', 'province_id' => '1', 'municipality_id' => '1', 'q' => 'Rice Grower', 'quality' => 'missing_ffrs'];
        $xpath = $this->xpath($this->index($this->user(User::ROLE_SYSTEM_OWNER), $input)->render());
        $forms = $xpath->query('//form[@data-workspace-scope-form]');
        $this->assertGreaterThanOrEqual(2, $forms->length);
        foreach ($forms as $form) {
            $this->assertSame(1, $xpath->query('.//input[@name="q" and @value="Rice Grower"]', $form)->length);
            $this->assertSame(1, $xpath->query('.//input[@name="quality" and @value="missing_ffrs"]', $form)->length);
        }
        $this->assertSame(0, $xpath->query('//form[.//select[@id="workspaceRegion"]]//*[@name="province_id" or @name="municipality_id"]')->length);
        $this->assertSame(0, $xpath->query('//form[.//select[@id="workspaceProvince"]]//*[@name="municipality_id"]')->length);
        $this->assertSame(1, $xpath->query('//select[@id="workspaceMunicipality"]//option[@value="6"]')->length);
        $this->assertSame(0, $xpath->query('//select[@id="workspaceMunicipality"]//option[@value="2" or @value="3" or @value="4" or @value="5"]')->length);
    }

    public function test_map_urls_preserve_optional_geography_without_registry_only_filters(): void
    {
        $owner = $this->user(User::ROLE_SYSTEM_OWNER);
        foreach ([['region_id' => '1'], ['region_id' => '1', 'province_id' => '1'], ['municipality_id' => '1']] as $input) {
            $view = $this->index($owner, $input + ['q' => 'No match', 'gender' => 'Male', 'quality' => 'missing_ffrs']);
            $parameters = array_map('strval', $view->getData()['workspaceParameters']);
            $html = $view->render();
            foreach (['__allFarmPlotsUrl', '__farmerLookupUrl'] as $variable) {
                $this->assertSame(1, preg_match('/window\.'.$variable.'\s*=\s*("(?:\\\\.|[^"\\\\])*")\s*;/', $html, $match));
                $url = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
                $this->assertSame($parameters, $query);
            }
        }
    }

    public function test_lookup_and_parcels_match_the_optional_geography_and_authorized_scope(): void
    {
        foreach ([
            [$this->user(User::ROLE_SYSTEM_OWNER), [], [1, 2, 3, 4, 5, 6]],
            [$this->user(User::ROLE_SYSTEM_OWNER), ['region_id' => '1'], [1, 2, 5, 6]],
            [$this->user(User::ROLE_SYSTEM_OWNER), ['province_id' => '1'], [1, 6]],
            [$this->user(User::ROLE_SYSTEM_OWNER), ['municipality_id' => '1'], [1]],
            [$this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]), [], [1, 2, 5, 6]],
            [$this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]), ['province_id' => '1'], [1, 6]],
            [$this->user(User::ROLE_SUPER_ADMIN, ['province_id' => 1]), [], [1, 6]],
            [$this->user(User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 1]), ['municipality_id' => '3'], [1]],
        ] as [$user, $input, $expectedIds]) {
            $request = $this->request($user, $input + ['q' => 'Farmer']);
            $lookup = app(FarmerController::class)->lookup($request, app(FarmerWorkspace::class))->getData(true);
            $this->assertEqualsCanonicalizing($expectedIds, array_column($lookup['farmers'], 'id'));
            $plots = app(FarmPlotController::class)->all($request, app(FarmerWorkspace::class))->getData(true);
            $this->assertEqualsCanonicalizing($expectedIds, array_column($plots['plots'], 'farmer_id'));
        }
    }

    public function test_registry_search_does_not_reduce_the_geographic_map_scope(): void
    {
        $data = $this->index($this->user(User::ROLE_SYSTEM_OWNER), ['region_id' => '1', 'q' => 'Farmer 1'])->getData();
        $this->assertSame([1], $data['farmers']->pluck('id')->all());
        $this->assertSame(1, $data['totalFarmers']);
        $this->assertSame(4, $data['mapFarmerCount']);
        $this->assertSame(4, $data['mapPlotCount']);
        $this->assertSame([1, 2, 5, 6], $data['mapMunicipalityBoundaries']->pluck('municipality_id')->all());
    }

    public function test_parcel_payload_cap_reports_the_total_within_the_selected_region(): void
    {
        config(['map.max_plots_per_request' => 2]);
        $request = $this->request($this->user(User::ROLE_SYSTEM_OWNER), ['region_id' => '1']);
        $data = app(FarmPlotController::class)->all($request, app(FarmerWorkspace::class))->getData(true);
        $this->assertSame(4, $data['total']);
        $this->assertSame(2, $data['returned']);
        $this->assertSame(2, $data['limit']);
        $this->assertTrue($data['truncated']);
        $this->assertSame([6, 5], array_column($data['plots'], 'farmer_id'));

        $request = $this->request($this->user(User::ROLE_SYSTEM_OWNER), ['province_id' => '1']);
        $data = app(FarmPlotController::class)->all($request, app(FarmerWorkspace::class))->getData(true);
        $this->assertSame(2, $data['total']);
        $this->assertSame(2, $data['returned']);
        $this->assertFalse($data['truncated']);
        $this->assertSame([6, 1], array_column($data['plots'], 'farmer_id'));
    }

    public function test_boundary_payload_cap_discloses_partial_outlines_without_reducing_registry_totals(): void
    {
        config(['map.max_boundaries_per_request' => 2]);
        $view = $this->index($this->user(User::ROLE_SYSTEM_OWNER), ['region_id' => '1']);
        $data = $view->getData();
        $this->assertSame(4, $data['mapBoundaryTotal']);
        $this->assertSame([1, 2], $data['mapMunicipalityBoundaries']->pluck('municipality_id')->all());
        $this->assertSame(4, $data['totalFarmers']);
        $this->assertSame(4, $data['mapFarmerCount']);
        $this->assertSame(4, $data['mapPlotCount']);
        $xpath = $this->xpath($view->render());
        $this->assertSame(1, $xpath->query('//p[@role="status" and contains(., "Showing 2 of 4 municipality outlines")]')->length);
    }

    private function schema(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->unsignedBigInteger('province_id');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('farmers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            foreach (['first_name', 'last_name', 'middle_name', 'ext_name', 'owner_name', 'ffrs', 'rsbsa_no', 'gender', 'farm_location', 'farm_municipality', 'farm_province', 'profile_photo_path', 'contact_number'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('farm_area_ha', 15, 4)->nullable();
            $table->timestamps();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('farmer_id');
            $table->string('quantity_unit')->nullable();
            $table->decimal('kgs_received', 15, 4)->nullable();
            $table->date('date_received')->nullable();
        });
        Schema::create('farm_plots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farmer_id');
            $table->string('name');
            $table->string('color')->nullable();
            $table->json('polygon_json');
            $table->decimal('area_ha', 15, 4)->nullable();
            $table->decimal('centroid_lat', 12, 8)->nullable();
            $table->decimal('centroid_lng', 12, 8)->nullable();
            $table->timestamps();
        });
        Schema::create('municipality_boundaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('municipality_id');
            $table->string('name');
            $table->string('status');
            $table->json('geojson');
            $table->string('color')->nullable();
            $table->decimal('fill_opacity', 3, 2)->nullable();
            $table->decimal('area_ha', 15, 4)->nullable();
            $table->decimal('centroid_lat', 12, 8)->nullable();
            $table->decimal('centroid_lng', 12, 8)->nullable();
        });
    }

    private function user(string $role, array $scope = []): User
    {
        $user = new User(['name' => 'Test Office', 'role' => $role, 'is_active' => true] + $scope);
        $user->id = 1;

        return $user;
    }

    private function request(User $user, array $input): Request
    {
        $this->actingAs($user);
        $request = Request::create('/farmers', 'GET', $input);
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);

        return $request;
    }

    private function index(User $user, array $input = [])
    {
        return app(FarmerController::class)->index($this->request($user, $input), app(FarmerWorkspace::class));
    }

    private function resolve(User $user, array $input = []): array
    {
        return app(FarmerWorkspace::class)->resolve($this->request($user, $input), $user);
    }

    private function assertRejected(User $user, array $input): void
    {
        try {
            $this->resolve($user, $input);
            $this->fail('Expected invalid workspace to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
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

<?php

namespace Tests\Feature;

use App\Http\Controllers\FarmerController;
use App\Models\User;
use App\Support\FarmerWorkspace;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FarmerWorkspaceHierarchyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
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
        DB::table('regions')->insert([['id' => 1, 'name' => 'Region Alpha'], ['id' => 2, 'name' => 'Region Beta']]);
        DB::table('provinces')->insert([
            ['id' => 1, 'name' => 'Province Alpha', 'region_id' => 1],
            ['id' => 2, 'name' => 'Independent City', 'region_id' => 1],
            ['id' => 3, 'name' => 'Province Beta', 'region_id' => 2],
            ['id' => 4, 'name' => 'Unassigned Province', 'region_id' => null],
        ]);
        for ($id = 1; $id <= 4; $id++) {
            DB::table('municipalities')->insert(['id' => $id, 'name' => 'Town '.$id, 'province' => 'Province '.$id, 'province_id' => $id]);
        }
    }

    public function test_owner_sees_only_region_first_and_no_operational_queries_before_municipality_selection(): void
    {
        $user = $this->user(User::ROLE_SYSTEM_OWNER);
        foreach ([[], ['region_id' => '1'], ['region_id' => '1', 'province_id' => '1']] as $step => $parameters) {
            $request = $this->request($user, $parameters);
            // Operational tables deliberately do not exist: querying any fails.
            $view = app(FarmerController::class)->index($request, app(FarmerWorkspace::class));
            $this->assertSame('farmers.workspace', $view->name());
            $html = $view->render();
            $xpath = $this->xpath($html);
            $this->assertSame(1, $xpath->query('//select[@id="workspaceRegion"]')->length);
            $this->assertSame(0, $xpath->query('//select[@id="workspaceProvince"]')->length);
            $this->assertSame($step >= 1 ? 1 : 0, $xpath->query('//select[@id="workspaceMunicipality"]')->length);
            $this->assertStringNotContainsString('window.__municipalityGeofences', $html);
            $this->assertStringNotContainsString('id="farmersTable"', $html);
        }
    }

    public function test_region_choices_include_separate_city_scopes_and_all_municipalities_in_the_region(): void
    {
        $user = $this->user(User::ROLE_SYSTEM_OWNER);
        $data = $this->resolve($user, ['region_id' => '1']);
        $this->assertSame([2, 1], $data['workspaceProvinces']->pluck('id')->all());
        $this->assertSame([1, 2], $data['municipalities']->pluck('id')->all());
        $data = $this->resolve($user, ['province_id' => '2']);
        $this->assertSame('1', $data['workspaceRegionId']);
        $this->assertSame([1, 2], $data['municipalities']->pluck('id')->all());
        $this->assertNull($data['selectedMunicipality']);
        $data = $this->resolve($user, ['region_id' => '1', 'municipality_id' => '2']);
        $this->assertSame(2, $data['selectedMunicipality']->id);
    }

    public function test_municipality_bookmarks_restore_the_hierarchy_and_unassigned_regions_remain_reachable(): void
    {
        $user = $this->user(User::ROLE_SYSTEM_OWNER);
        $data = $this->resolve($user, ['municipality_id' => '4']);
        $this->assertSame(4, $data['selectedMunicipality']->id);
        $this->assertSame('unassigned', $data['workspaceRegionId']);
        $this->assertSame('Region not assigned', $data['workspaceRegionName']);
    }

    public function test_assigned_roles_skip_fixed_steps_and_cannot_browse_foreign_regions(): void
    {
        $regional = $this->user(User::ROLE_REGIONAL_HEAD, ['region_id' => 1]);
        $data = $this->resolve($regional);
        $this->assertSame('1', $data['workspaceRegionId']);
        $this->assertSame(['1'], $data['workspaceRegions']->pluck('id')->all());
        $this->assertSame([1, 2], $data['municipalities']->pluck('id')->all());
        $provincial = $this->user(User::ROLE_SUPER_ADMIN, ['province_id' => 1]);
        $data = $this->resolve($provincial);
        $this->assertSame(1, $data['workspaceProvince']->id);
        $this->assertSame([1], $data['municipalities']->pluck('id')->all());
        $municipal = $this->user(User::ROLE_MUNICIPAL_STAFF, ['municipality_id' => 1]);
        $this->assertSame(1, $this->resolve($municipal, ['municipality_id' => '3'])['selectedMunicipality']->id);

        foreach ([[$regional, ['region_id' => '2']], [$regional, ['province_id' => '3']], [$provincial, ['municipality_id' => '3']]] as [$user, $input]) {
            $this->assertRejected($user, $input);
        }
    }

    public function test_invalid_mismatched_and_inactive_choices_are_rejected(): void
    {
        $owner = $this->user(User::ROLE_SYSTEM_OWNER);
        foreach ([['region_id' => ['1']], ['province_id' => 'bad'], ['municipality_id' => '999'], ['region_id' => '2', 'province_id' => '1'], ['province_id' => '2', 'municipality_id' => '1'], ['region_id' => '1', 'municipality_id' => '3']] as $input) {
            $this->assertRejected($owner, $input);
        }
        DB::table('municipalities')->where('id', 3)->update(['is_active' => false]);
        $this->assertRejected($owner, ['municipality_id' => '3']);
        DB::table('provinces')->where('id', 1)->update(['is_active' => false]);
        $this->assertRejected($owner, ['province_id' => '1']);
    }

    public function test_stage_forms_preserve_registry_filters_and_do_not_submit_stale_children(): void
    {
        $user = $this->user(User::ROLE_SYSTEM_OWNER);
        $request = $this->request($user, ['region_id' => '1', 'q' => 'Rice Grower', 'quality' => 'missing_ffrs']);
        $view = app(FarmerController::class)->index($request, app(FarmerWorkspace::class));
        $xpath = $this->xpath($view->render());
        $this->assertGreaterThanOrEqual(1, $xpath->query('//form/input[@name="q" and @value="Rice Grower"]')->length);
        $this->assertSame(0, $xpath->query('//form[.//select[@id="workspaceRegion"]]//*[@name="province_id"]')->length);
        $this->assertSame(0, $xpath->query('//select[@id="workspaceProvince"]')->length);
        $this->assertSame(0, $xpath->query('//form[.//select[@id="workspaceRegion"]]//*[@name="municipality_id"]')->length);
        $this->assertSame(0, $xpath->query('//select[@disabled]')->length, 'Every visible step also works without JavaScript');
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

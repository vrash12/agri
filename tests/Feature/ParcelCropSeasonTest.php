<?php

namespace Tests\Feature;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParcelCropSeasonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
        $this->fixtures();
    }

    public function test_staff_can_record_and_correct_a_season_without_changing_parcel_colors(): void
    {
        $plotBefore = FarmPlot::findOrFail(1)->getAttributes();
        $this->actingAs(User::find(1))->post($this->editUrl(), $this->payload() + ['municipality_id' => 2])
            ->assertRedirect();
        $record = ParcelCropSeason::firstOrFail();
        $this->assertSame(1, $record->municipality_id);
        $this->assertSame('rice', $record->crop);
        $this->assertSame($plotBefore, FarmPlot::findOrFail(1)->getAttributes());
        $version = ConcurrentWrite::version($record);
        $this->post($this->editUrl(), array_replace($this->payload(), ['crop' => 'corn', '_record_version' => $version]))->assertRedirect();
        $this->assertSame('corn', $record->fresh()->crop);
        $this->assertDatabaseHas('audit_logs', ['module' => 'Seasonal parcel crops', 'event' => 'created', 'municipality_id' => 1]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'Seasonal parcel crops', 'event' => 'updated', 'municipality_id' => 1]);
        $this->get($this->editUrl().'?year=2026&season=dry')->assertOk()->assertSee('Corn')->assertSee('Save seasonal crop');
    }

    public function test_stale_first_entry_and_stale_update_are_rejected(): void
    {
        $this->actingAs(User::find(1))->postJson($this->editUrl(), $this->payload())->assertRedirect();
        $this->postJson($this->editUrl(), array_replace($this->payload(), ['crop' => 'corn']))
            ->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $version = ConcurrentWrite::version(ParcelCropSeason::firstOrFail());
        $this->postJson($this->editUrl(), array_replace($this->payload(), ['crop' => 'corn', '_record_version' => $version]))->assertRedirect();
        $this->postJson($this->editUrl(), array_replace($this->payload(), ['crop' => 'fruit', '_record_version' => $version]))
            ->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $this->assertSame(1, ParcelCropSeason::count());
        $this->assertSame('corn', ParcelCropSeason::first()->crop);
    }

    public function test_layer_is_scoped_exactly_to_the_period_and_requested_parcels(): void
    {
        ParcelCropSeason::insert([
            ['farm_plot_id' => 1, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'dry', 'crop' => 'rice'],
            ['farm_plot_id' => 1, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'wet', 'crop' => 'corn'],
            ['farm_plot_id' => 2, 'municipality_id' => 2, 'crop_year' => 2026, 'season' => 'dry', 'crop' => 'vegetables'],
        ]);
        $this->actingAs(User::find(1))->getJson($this->layerUrl([1, 2, 3]))->assertOk()
            ->assertJsonCount(2, 'records')->assertJsonPath('records.0.crop', 'rice')
            ->assertJsonPath('records.1.crop', 'not_recorded')->assertJsonMissing(['plot_id' => 2]);
        $this->getJson(route('farm-plots.crop-layer', ['year' => 2026, 'season' => 'wet', 'plot_ids' => [1]]))
            ->assertOk()->assertJsonPath('records.0.crop', 'corn');
        $this->getJson(route('farm-plots.crop-layer', ['year' => 2025, 'season' => 'dry', 'plot_ids' => [1]]))
            ->assertOk()->assertJsonPath('records.0.crop', 'not_recorded');
    }

    public function test_oversight_can_view_crops_but_cannot_write_and_vets_cannot_access(): void
    {
        foreach ([3, 4] as $id) {
            $this->actingAs(User::find($id))->get($this->editUrl())->assertOk()->assertDontSee('Save seasonal crop')->assertSee('read-only oversight');
            $this->postJson($this->editUrl(), $this->payload())->assertForbidden();
        }
        $this->actingAs(User::find(5))->getJson($this->layerUrl([1]))->assertForbidden();
        $this->postJson($this->editUrl(), $this->payload())->assertForbidden();
        $this->actingAs(User::find(2))->getJson($this->editUrl())->assertForbidden();
        $this->postJson($this->editUrl(), $this->payload())->assertForbidden();
    }

    public function test_layer_province_and_owner_scope_and_year_validation(): void
    {
        $this->actingAs(User::find(3))->getJson($this->layerUrl([1, 2, 3]))->assertOk()->assertJsonCount(2, 'records');
        $this->actingAs(User::find(4))->getJson($this->layerUrl([1, 2, 3]))->assertOk()->assertJsonCount(3, 'records');
        $this->getJson(route('farm-plots.crop-layer', ['year' => 1800, 'season' => 'dry', 'plot_ids' => [1]]))->assertUnprocessable();
        $this->getJson(route('farm-plots.crop-layer', ['year' => 2026, 'season' => 'invalid', 'plot_ids' => [1]]))->assertUnprocessable();
        $this->getJson($this->layerUrl(range(1, 201)))->assertUnprocessable();
        $this->getJson($this->layerUrl([1, 1]))->assertUnprocessable();
    }

    public function test_unknown_crop_and_missing_version_do_not_write(): void
    {
        $this->actingAs(User::find(1))->postJson($this->editUrl(), array_replace($this->payload(), ['crop' => '<script>']))
            ->assertUnprocessable()->assertJsonValidationErrors('crop');
        $this->postJson($this->editUrl(), array_replace($this->payload(), ['_record_version' => '']))->assertUnprocessable();
        $this->postJson($this->editUrl(), array_replace($this->payload(), ['crop_year' => 1800]))->assertUnprocessable();
        $this->assertSame(0, ParcelCropSeason::count());
    }

    public function test_inactive_accounts_and_inconsistent_parent_ownership_fail_closed(): void
    {
        $user = User::findOrFail(1);
        $user->is_active = false;
        $user->save();
        $this->actingAs($user)->getJson($this->layerUrl([1]))->assertForbidden();
        $this->postJson($this->editUrl(), $this->payload())->assertForbidden();
        $this->actingAs(User::findOrFail(6));
        ParcelCropSeason::create(['farm_plot_id' => 1, 'municipality_id' => 2, 'crop_year' => 2026, 'season' => 'dry', 'crop' => 'rice']);
        $this->getJson($this->layerUrl([1]))->assertOk()->assertJsonPath('records.0.crop', 'not_recorded');
        $this->postJson($this->editUrl(), $this->payload())->assertForbidden();
    }

    public function test_staff_can_record_a_second_season_or_clear_a_classification_explicitly(): void
    {
        $this->actingAs(User::findOrFail(6))->postJson($this->editUrl(), $this->payload())->assertRedirect();
        $this->postJson($this->editUrl(), array_replace($this->payload(), ['season' => 'wet', 'crop' => 'mixed']))->assertRedirect();
        $this->assertSame(2, ParcelCropSeason::count());
        $record = ParcelCropSeason::where('season', 'dry')->firstOrFail();
        $this->postJson($this->editUrl(), array_replace($this->payload(), ['crop' => 'not_recorded', '_record_version' => ConcurrentWrite::version($record)]))->assertRedirect();
        $this->getJson($this->layerUrl([1]))->assertOk()->assertJsonPath('records.0.crop', 'not_recorded')
            ->assertJsonPath('records.0.color', '#8A9299');
        $this->assertSame('mixed', ParcelCropSeason::where('season', 'wet')->firstOrFail()->crop);
    }

    public function test_migration_unique_period_cascade_and_reversal(): void
    {
        ParcelCropSeason::create(['farm_plot_id' => 1, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'dry', 'crop' => 'mixed']);
        $failed = false;
        try {
            ParcelCropSeason::create(['farm_plot_id' => 1, 'municipality_id' => 1, 'crop_year' => 2026, 'season' => 'dry', 'crop' => 'rice']);
        } catch (\Illuminate\Database\QueryException $e) {
            $failed = true;
        }
        $this->assertTrue($failed);
        FarmPlot::findOrFail(1)->delete();
        $this->assertSame(0, ParcelCropSeason::count());
        (require database_path('migrations/2026_09_19_000100_create_parcel_crop_seasons_table.php'))->down();
        $this->assertFalse(Schema::hasTable('parcel_crop_seasons'));
        $this->assertTrue(Schema::hasTable('farm_plots'));
    }

    public function test_missing_parent_is_excluded_and_guests_cannot_read_the_layer(): void
    {
        $this->getJson($this->layerUrl([1]))->assertUnauthorized();
        DB::table('farmers')->where('id', 1)->delete();
        $this->actingAs(User::findOrFail(4))->getJson($this->layerUrl([1, 2, 3]))
            ->assertOk()->assertJsonCount(1, 'records')->assertJsonPath('records.0.plot_id', 2);
        $this->getJson($this->editUrl())->assertNotFound();
    }

    private function editUrl(): string
    {
        return route('farm-plots.seasonal-crops.edit', 1);
    }

    private function layerUrl(array $ids): string
    {
        return route('farm-plots.crop-layer', ['year' => 2026, 'season' => 'dry', 'plot_ids' => $ids]);
    }

    private function payload(): array
    {
        return ['crop_year' => 2026, 'season' => 'dry', 'crop' => 'rice', 'notes' => 'Field visit confirmed rice.', '_record_version' => 'new'];
    }

    private function fixtures(): void
    {
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Home', 'is_active' => true], ['id' => 2, 'name' => 'Other', 'is_active' => true]]);
        DB::table('municipalities')->insert([['id' => 1, 'name' => 'Own Municipality', 'province_id' => 1], ['id' => 2, 'name' => 'Other Municipality', 'province_id' => 2]]);
        foreach ([1 => [User::ROLE_MUNICIPAL_STAFF, 1, null], 2 => [User::ROLE_MUNICIPAL_STAFF, 2, null],
            3 => [User::ROLE_SUPER_ADMIN, null, 1], 4 => [User::ROLE_SYSTEM_OWNER, null, null], 5 => [User::ROLE_PROVINCIAL_VET, null, 1], 6 => [User::ROLE_PROVINCIAL_STAFF, null, 1]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Test Staff', 'email' => 'test'.$id.'@example.test', 'password' => 'unused', 'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province]);
        }
        DB::table('farmers')->insert([['id' => 1, 'municipality_id' => 1, 'first_name' => 'Sample', 'last_name' => 'Farmer'], ['id' => 2, 'municipality_id' => 2, 'first_name' => 'Other', 'last_name' => 'Farmer']]);
        foreach ([1 => 1, 2 => 2, 3 => 1] as $id => $farmer) {
            DB::table('farm_plots')->insert(['id' => $id, 'farmer_id' => $farmer, 'name' => 'Parcel '.$id, 'polygon_json' => '[]', 'color' => '#123456', 'area_ha' => 1.2]);
        }
    }

    private function schema(): void
    {
        Schema::create('provinces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('province')->nullable();
            $t->unsignedBigInteger('province_id');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->string('role');
            $t->unsignedBigInteger('municipality_id')->nullable();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('farmers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('first_name');
            $t->string('last_name');
            $t->timestamps();
        });
        Schema::create('farm_plots', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('farmer_id');
            $t->string('name');
            $t->string('color');
            $t->json('polygon_json');
            $t->decimal('area_ha', 15, 4);
            $t->timestamps();
        });
        (require database_path('migrations/2026_09_19_000100_create_parcel_crop_seasons_table.php'))->up();
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
    }
}

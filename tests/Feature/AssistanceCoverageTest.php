<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssistanceCoverage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssistanceCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
        $this->fixtures();
    }

    public function test_exact_planting_period_reference_and_municipality_totals(): void
    {
        $this->release(1, 1, 1, 40);
        $this->release(1, 1, 1, 20);
        $this->release(1, 2, 1, 10, ['quantity_unit' => 'piece']);
        $this->release(1, 1, 4, 999); // Wet planting, despite a dry harvest.
        $this->release(2, 3, 2, 5);
        $this->release(3, 4, 3, 900); // Foreign province with the same reference.
        $response = $this->actingAs(User::find(2))->get($this->url(['year' => 2026, 'season' => 'dry', 'reference' => 'NRP']));
        $response->assertOk()->assertViewHas('summary', fn ($summary) => $summary['releases'] === 4 && $summary['beneficiaries'] === 3);
        $rows = $response->viewData('rows');
        $a = collect($rows)->firstWhere('id', 1);
        $this->assertSame(3, $a['releases']);
        $this->assertSame(2, $a['beneficiaries']);
        $this->assertSame([['unit' => 'kg', 'quantity' => 60.0], ['unit' => 'pieces', 'quantity' => 10.0]], $a['quantities']);
        $this->assertSame([1, 2, 6], array_column($rows, 'id'));
        $this->assertSame(0, collect($rows)->firstWhere('id', 6)['releases']);
        $this->get($this->url(['year' => 2026, 'season' => 'wet']))->assertViewHas('summary', fn ($s) => $s['releases'] === 1);
        $this->get($this->url(['year' => 2025, 'season' => 'dry']))->assertViewHas('summary', fn ($s) => $s['releases'] === 0);
        $this->get($this->url(['reference' => 'NR']))->assertViewHas('summary', fn ($s) => $s['releases'] === 0);
        DB::table('rice_distribution_batches')->where('id', 1)->update(['reference' => '0']);
        $this->get($this->url(['reference' => '0']))->assertViewHas('summary', fn ($s) => $s['releases'] === 3);
    }

    public function test_unknown_periods_are_visible_without_inference_from_dates_or_harvest(): void
    {
        $this->release(1, 1, null, 10, ['harvest_season' => 'dry', 'harvest_year' => 2026, 'date_received' => '2026-03-01']);
        $this->release(1, 1, 3, 20); // Cross-municipality sheet is not a valid period/program link.
        $this->release(1, 1, 1, 30, ['input_category' => 'fish_feed']); // Invalid rice-sheet linkage.
        $this->release(1, 1, 5, 40); // Incomplete sheet period.
        $this->actingAs(User::find(1))->get($this->url())->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['releases'] === 4 && $s['unclassified'] === 4);
        $this->get($this->url(['season' => 'dry', 'year' => 2026]))
            ->assertViewHas('summary', fn ($s) => $s['releases'] === 0 && $s['unclassified'] === 4);
        $this->get($this->url(['season' => 'unrecorded', 'year' => 2026]))
            ->assertViewHas('filters', fn ($f) => $f['year'] === null)
            ->assertViewHas('summary', fn ($s) => $s['releases'] === 4);
        $this->get($this->url(['reference' => 'NRP']))->assertViewHas('summary', fn ($s) => $s['releases'] === 1);
        $this->get($this->url(['category' => 'fish_feed']))->assertViewHas('summary', fn ($s) => $s['releases'] === 1);
    }

    public function test_bad_farmer_links_and_incomplete_quantities_do_not_inflate_beneficiaries_or_units(): void
    {
        $this->release(1, null, 1, null);
        $this->release(1, 4, 1, 20, ['quantity_unit' => null]);
        $this->release(1, 999, 1, -1);
        $this->release(1, 1, 1, 0);
        $response = $this->actingAs(User::find(1))->get($this->url());
        $response->assertOk()->assertViewHas('summary', fn ($s) => $s['releases'] === 4 && $s['beneficiaries'] === 1 && $s['unlinked'] === 3 && $s['incomplete_quantities'] === 3);
        $this->assertSame([['unit' => 'kg', 'quantity' => 0.0]], $response->viewData('rows')[0]['quantities']);
    }

    public function test_role_scope_is_applied_before_rows_reference_choices_and_geometry(): void
    {
        DB::table('rice_distribution_batches')->where('id', 3)->update(['reference' => 'FOREIGN-SECRET']);
        $this->release(1, 1, 1, 10);
        $this->release(2, 3, 2, 20);
        $this->release(3, 4, 3, 30);
        foreach ([1 => [1], 2 => [1, 2, 6], 6 => [1, 2, 6], 7 => [1]] as $user => $expected) {
            $response = $this->actingAs(User::find($user))->get($this->url());
            $response->assertOk()->assertDontSee('FOREIGN-SECRET')->assertDontSee('Foreign municipality');
            $this->assertSame($expected, array_column($response->viewData('rows'), 'id'));
            $this->get($this->url(['province_id' => 2]))->assertNotFound();
            $this->get($this->url(['municipality_id' => 3]))->assertNotFound();
        }
        $this->actingAs(User::find(1))->get($this->url(['municipality_id' => 2]))->assertNotFound();
        $this->actingAs(User::find(3))->get($this->url(['province_id' => 2]))->assertOk()->assertSee('FOREIGN-SECRET');
        $this->actingAs(User::find(2))->getJson($this->geometry([1, 3, 4, 5]))->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.id', 1);
    }

    public function test_guests_vets_inactive_and_unassigned_accounts_cannot_access(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
        $this->getJson($this->geometry([1]))->assertUnauthorized();
        $this->actingAs(User::find(4))->getJson($this->url())->assertForbidden();
        $this->getJson($this->geometry([1]))->assertForbidden();
        DB::table('users')->where('id', 1)->update(['is_active' => false]);
        $this->actingAs(User::find(1))->getJson($this->url())->assertStatus(403);
        DB::table('users')->where('id', 2)->update(['province_id' => null]);
        $this->actingAs(User::find(2))->getJson($this->url())->assertStatus(403);
    }

    public function test_filters_and_geometry_requests_are_validated_and_bounded(): void
    {
        $this->actingAs(User::find(1));
        foreach ([['season' => 'dry'], ['season' => 'rainy'], ['year' => 1800], ['category' => 'fake'], ['reference' => str_repeat('a', 121)]] as $filters) {
            $this->getJson($this->url($filters))->assertUnprocessable();
        }
        $this->getJson($this->geometry(range(1, 11)))->assertUnprocessable();
        $this->getJson($this->geometry([1, 1]))->assertUnprocessable();
        $this->getJson($this->geometry([]))->assertUnprocessable();
    }

    public function test_missing_duplicate_and_oversized_boundaries_keep_their_table_rows(): void
    {
        $this->release(6, null, null, 12);
        $this->boundary(2, 7);
        DB::table('municipality_boundaries')->where('municipality_id', 1)->update(['vertex_count' => 10001]);
        $response = $this->actingAs(User::find(2))->get($this->url());
        $response->assertOk();
        $this->assertSame('ambiguous', collect($response->viewData('rows'))->firstWhere('id', 2)['boundary']);
        $this->assertSame('missing', collect($response->viewData('rows'))->firstWhere('id', 6)['boundary']);
        $this->getJson($this->geometry([1, 2, 6]))->assertOk()->assertJsonCount(0, 'features')->assertJsonPath('omitted_ids.0', 1);
    }

    public function test_map_endpoint_returns_geometry_only_and_never_writes_or_exposes_farmer_identity(): void
    {
        $before = DB::table('municipality_boundaries')->orderBy('id')->get()->toJson();
        $response = $this->actingAs(User::find(1))->getJson($this->geometry([1, 2, 3]));
        $response->assertOk()->assertJsonCount(1, 'features')->assertJsonMissing(['name' => 'Private farmer']);
        $feature = $response->json('features.0');
        $this->assertSame(['type', 'id', 'properties', 'geometry'], array_keys($feature));
        $this->assertSame(['municipality_id' => 1], $feature['properties']);
        $this->assertSame($before, DB::table('municipality_boundaries')->orderBy('id')->get()->toJson());
    }

    public function test_report_uses_a_fixed_number_of_queries_as_municipalities_grow(): void
    {
        $service = app(AssistanceCoverage::class);
        $user = User::find(3);
        $filters = ['category' => '', 'reference' => '', 'year' => null, 'season' => 'all'];
        $counts = [];
        foreach ([1, 40] as $size) {
            if ($size > 1) {
                for ($i = 10; $i < 50; $i++) {
                    DB::table('municipalities')->insert(['id' => $i, 'name' => 'Area '.$i, 'province_id' => 1]);
                }
            }
            $municipalities = $service->municipalities($user, 1)->get(['id', 'name']);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->report($user, $municipalities, $filters);
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(7, $counts[1]);
    }

    private function url(array $filters = []): string
    {
        return route('assistance-coverage.index', $filters);
    }

    private function geometry(array $ids): string
    {
        return route('assistance-coverage.boundaries', ['municipality_ids' => $ids]);
    }

    private function release(int $municipality, ?int $farmer, ?int $batch, ?float $quantity, array $extra = []): void
    {
        DB::table('rice_seed_distributions')->insert(array_replace(['municipality_id' => $municipality, 'farmer_id' => $farmer, 'batch_id' => $batch, 'input_category' => 'rice_seed', 'quantity_unit' => 'kg', 'kgs_received' => $quantity], $extra));
    }

    private function boundary(int $municipality, int $id): void
    {
        DB::table('municipality_boundaries')->insert(['id' => $id, 'municipality_id' => $municipality, 'status' => 'active', 'vertex_count' => 5,
            'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[[120, 15], [120.1, 15], [120.1, 15.1], [120, 15.1], [120, 15]]]])]);
    }

    private function fixtures(): void
    {
        DB::table('provinces')->insert([['id' => 1, 'name' => 'Home province', 'is_active' => true], ['id' => 2, 'name' => 'Other province', 'is_active' => true], ['id' => 3, 'name' => 'Inactive province', 'is_active' => false]]);
        foreach ([1 => ['A municipality', 1, true], 2 => ['B municipality', 1, true], 3 => ['Foreign municipality', 2, true], 4 => ['Inactive municipality', 1, false], 5 => ['Inactive province area', 3, true], 6 => ['C municipality', 1, true]] as $id => [$name, $province, $active]) {
            DB::table('municipalities')->insert(['id' => $id, 'name' => $name, 'province_id' => $province, 'is_active' => $active]);
        }
        foreach ([1 => ['municipal_staff', 1, null], 2 => ['super_admin', null, 1], 3 => ['system_owner', null, null], 4 => ['provincial_vet', null, 1], 5 => ['municipal_staff', 3, null], 6 => ['provincial_staff', null, 1], 7 => ['municipal_head', 1, null]] as $id => [$role, $municipality, $province]) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Test staff', 'email' => 'coverage'.$id.'@example.test', 'password' => 'unused', 'role' => $role, 'municipality_id' => $municipality, 'province_id' => $province]);
        }
        foreach ([1 => 1, 2 => 1, 3 => 2, 4 => 3] as $id => $municipality) {
            DB::table('farmers')->insert(['id' => $id, 'municipality_id' => $municipality, 'first_name' => 'Private farmer']);
        }
        foreach ([1 => [1, 'dry', 2026], 2 => [2, 'dry', 2026], 3 => [3, 'dry', 2026], 4 => [1, 'wet', 2026], 5 => [1, null, null]] as $id => [$municipality, $season, $year]) {
            DB::table('rice_distribution_batches')->insert(['id' => $id, 'municipality_id' => $municipality, 'reference' => 'NRP', 'planting_season' => $season, 'planting_year' => $year, 'harvest_season' => 'dry', 'harvest_year' => 2025]);
        }
        foreach ([1, 2, 3, 4, 5] as $id) {
            $this->boundary($id, $id);
        }
    }

    private function schema(): void
    {
        Schema::create('provinces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
        });
        Schema::create('municipalities', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('province')->nullable();
            $t->unsignedBigInteger('province_id');
            $t->boolean('is_active')->default(true);
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
        });
        Schema::create('rice_distribution_batches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('reference');
            $t->string('planting_season')->nullable();
            $t->integer('planting_year')->nullable();
            $t->string('harvest_season')->nullable();
            $t->integer('harvest_year')->nullable();
        });
        Schema::create('rice_seed_distributions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id')->nullable();
            $t->unsignedBigInteger('farmer_id')->nullable();
            $t->unsignedBigInteger('batch_id')->nullable();
            $t->string('input_category')->nullable();
            $t->string('quantity_unit')->nullable();
            $t->decimal('kgs_received', 12, 2)->nullable();
            $t->date('date_received')->nullable();
            $t->string('harvest_season')->nullable();
            $t->integer('harvest_year')->nullable();
        });
        Schema::create('municipality_boundaries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('municipality_id');
            $t->string('status');
            $t->json('geojson');
            $t->unsignedInteger('vertex_count')->nullable();
        });
    }
}

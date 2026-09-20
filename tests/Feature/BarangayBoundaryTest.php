<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Support\BarangayBoundaryReferences;
use App\Support\GeoGeometry;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

class BarangayBoundaryTest extends TestCase
{
    use ProvinceScopeSchema;

    private Municipality $ramos;

    private Municipality $other;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $tarlac = Province::create(['name' => 'Tarlac', 'is_active' => true]);
        $other = Province::create(['name' => 'Other Province', 'is_active' => true]);
        $this->ramos = Municipality::create(['name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => $tarlac->id, 'code' => 'RAM', 'is_active' => true]);
        // Same display name and legacy province text must not expose Ramos references.
        $this->other = Municipality::create(['name' => 'Ramos', 'province' => 'Tarlac', 'province_id' => $other->id, 'code' => 'OTHER', 'is_active' => true]);
    }

    public function test_ramos_returns_all_nine_named_references_with_no_database_writes(): void
    {
        $this->actingAs($this->account(User::ROLE_MUNICIPAL_STAFF, $this->ramos));
        $response = $this->getJson($this->url($this->ramos))->assertOk()
            ->assertJsonPath('available', true)->assertJsonCount(9, 'features')
            ->assertJsonPath('municipality_id', $this->ramos->id);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $names = array_column(array_column($response->json('features'), 'properties'), 'name');
        $this->assertSame(['Coral-Iloco', 'Guiteb', 'Pance', 'Poblacion Center', 'Poblacion North', 'Poblacion South', 'San Juan', 'San Raymundo', 'Toledo'], $names);
        $this->assertDatabaseCount('municipality_boundaries', 0);
        $this->assertDatabaseCount('farm_plots', 0);
        $this->assertLessThan(15000, strlen($response->getContent()));
    }

    public function test_scoped_agriculture_roles_can_view_but_other_provinces_cannot(): void
    {
        foreach ([User::ROLE_MUNICIPAL_HEAD, User::ROLE_PROVINCIAL_STAFF, User::ROLE_SUPER_ADMIN, User::ROLE_SYSTEM_OWNER] as $role) {
            $this->actingAs($this->account($role, $this->ramos))->getJson($this->url($this->ramos))->assertOk();
        }
        foreach ([User::ROLE_MUNICIPAL_STAFF, User::ROLE_SUPER_ADMIN] as $role) {
            $this->actingAs($this->account($role, $this->other))->getJson($this->url($this->ramos))->assertNotFound();
        }
    }

    public function test_same_province_municipality_cannot_request_ramos(): void
    {
        $anao = Municipality::create(['name' => 'Anao', 'province_id' => $this->ramos->province_id, 'is_active' => true]);
        $this->actingAs($this->account(User::ROLE_MUNICIPAL_STAFF, $anao))
            ->getJson($this->url($this->ramos))->assertNotFound();
    }

    public function test_guests_veterinary_and_inactive_accounts_are_denied(): void
    {
        $this->getJson($this->url($this->ramos))->assertUnauthorized();
        $this->actingAs($this->account(User::ROLE_PROVINCIAL_VET, $this->ramos))
            ->getJson($this->url($this->ramos))->assertForbidden();
        $user = $this->account(User::ROLE_SUPER_ADMIN, $this->ramos);
        $user->update(['is_active' => false]);
        $this->actingAs($user)->getJson($this->url($this->ramos))->assertForbidden();
    }

    public function test_inactive_municipality_or_province_is_not_exposed_to_owner(): void
    {
        $this->actingAs($this->account(User::ROLE_SYSTEM_OWNER, $this->ramos));
        $this->ramos->update(['is_active' => false]);
        $this->getJson($this->url($this->ramos))->assertNotFound();
        $this->ramos->update(['is_active' => true]);
        $this->ramos->supervisingProvince->update(['is_active' => false]);
        $this->getJson($this->url($this->ramos))->assertNotFound();
    }

    public function test_unsupported_municipality_and_invalid_inputs_fail_safely(): void
    {
        $this->actingAs($this->account(User::ROLE_SYSTEM_OWNER, $this->ramos));
        $this->getJson($this->url($this->other))->assertOk()->assertJsonPath('available', false)->assertJsonCount(0, 'features');
        $this->getJson(route('municipality-boundaries.barangays'))->assertUnprocessable();
        $this->getJson(route('municipality-boundaries.barangays', ['municipality_id' => '../data']))->assertUnprocessable();
        $this->getJson(route('municipality-boundaries.barangays', ['municipality_id' => 999999]))->assertNotFound();
    }

    public function test_workspace_advertises_only_authorized_reference_ids_without_geometry(): void
    {
        $user = $this->account(User::ROLE_MUNICIPAL_STAFF, $this->ramos);
        $this->actingAs($user)->get(route('municipality-boundaries.index'))->assertOk()
            ->assertSee('Barangay boundaries')->assertSee('js/barangay-boundaries.js')
            ->assertDontSee('Coral-Iloco')->assertViewHas('barangayMunicipalityIds', [$this->ramos->id]);
        $this->assertSame([], app(BarangayBoundaryReferences::class)->availableMunicipalityIds($this->account(User::ROLE_SUPER_ADMIN, $this->other)));
    }

    public function test_pinned_polygons_and_psgc_codes_are_valid(): void
    {
        $data = app(BarangayBoundaryReferences::class)->forMunicipality($this->ramos);
        $geometry = app(GeoGeometry::class);
        foreach ($data['features'] as $index => $feature) {
            $this->assertSame('030691200'.($index + 1), $feature['properties']['psgc']);
            $prepared = $geometry->prepare($feature['geometry']);
            $this->assertGreaterThan(0, $geometry->areaHectares($prepared));
            $this->assertSame('Polygon', $feature['geometry']['type']);
            foreach ($feature['geometry']['coordinates'] as $ring) {
                $this->assertSame($ring[0], end($ring));
                foreach ($ring as [$lng, $lat]) {
                    $this->assertGreaterThan(120.60, $lng);
                    $this->assertLessThan(120.67, $lng);
                    $this->assertGreaterThan(15.64, $lat);
                    $this->assertLessThan(15.71, $lat);
                }
            }
        }
    }

    public function test_reference_failure_returns_a_generic_retryable_message(): void
    {
        $this->mock(BarangayBoundaryReferences::class, function ($mock) {
            $mock->shouldReceive('forMunicipality')->once()->andThrow(new \RuntimeException('Internal file details'));
        });
        $this->actingAs($this->account(User::ROLE_MUNICIPAL_STAFF, $this->ramos))
            ->getJson($this->url($this->ramos))->assertStatus(503)
            ->assertJsonPath('message', 'Barangay boundaries are temporarily unavailable. Please try again later.')
            ->assertDontSee('Internal file details');
    }

    private function account(string $role, Municipality $municipality): User
    {
        return User::create(['name' => 'Test account', 'email' => uniqid().'@example.test', 'password' => 'unused-test-hash',
            'role' => $role, 'is_active' => true, 'province_id' => $municipality->province_id,
            'municipality_id' => in_array($role, [User::ROLE_MUNICIPAL_STAFF, User::ROLE_MUNICIPAL_HEAD], true) ? $municipality->id : null]);
    }

    private function url(Municipality $municipality): string
    {
        return route('municipality-boundaries.barangays', ['municipality_id' => $municipality->id]);
    }
}

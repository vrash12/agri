<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\BulacanProvinceBoundarySeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

class BulacanProvinceBoundarySeederTest extends TestCase
{
    use ProvinceScopeSchema;

    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->createSchema();
        $this->province = Province::create(['name' => 'Bulacan', 'is_active' => true]);
        User::withoutEvents(fn () => User::create([
            'name' => 'Boundary Test Owner', 'email' => 'boundary-owner@example.test',
            'password' => 'test-disabled', 'role' => User::ROLE_SYSTEM_OWNER, 'is_active' => true,
        ]));
    }

    public function test_the_seeder_activates_one_idempotent_bulacan_reference_boundary(): void
    {
        app(BulacanProvinceBoundarySeeder::class)->run();
        $auditCount = AuditLog::count();
        app(BulacanProvinceBoundarySeeder::class)->run();

        $municipality = Municipality::where('code', 'BULACAN')->sole();
        $boundary = MunicipalityBoundary::where('municipality_id', $municipality->id)->sole();
        $this->assertTrue($boundary->isActive());
        $this->assertEqualsWithDelta(272543.5161, (float) $boundary->area_ha, 0.1);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame($this->province->id, AuditLog::where('event', 'imported')->sole()->province_id);
    }

    public function test_it_reuses_the_legacy_workspace_without_changing_its_code_or_assignment(): void
    {
        $municipality = $this->workspace('BUL');
        $before = $municipality->fresh()->getAttributes();
        app(BulacanProvinceBoundarySeeder::class)->run();

        $this->assertSame(1, Municipality::count());
        $this->assertSame($before, $municipality->fresh()->getAttributes());
        $this->assertSame($municipality->id, MunicipalityBoundary::sole()->municipality_id);
        $this->assertSame($municipality->id, AuditLog::where('event', 'imported')->sole()->municipality_id);
    }

    public function test_it_rejects_ambiguous_workspaces_without_creating_a_boundary(): void
    {
        $this->workspace('BUL');
        $this->workspace('BULACAN');
        $this->assertRejected('Multiple Bulacan workspaces');
        $this->assertSame(2, Municipality::count());
    }

    public function test_it_rejects_an_inactive_or_foreign_province_workspace(): void
    {
        $municipality = $this->workspace('BUL');
        $municipality->update(['is_active' => false]);
        $this->assertRejected('inactive');
        $foreign = Province::create(['name' => 'Tarlac', 'is_active' => true]);
        $municipality->update(['is_active' => true, 'province_id' => $foreign->id]);
        $this->assertRejected('different province');
    }

    public function test_it_rolls_back_workspace_creation_if_the_boundary_overlaps(): void
    {
        app(BulacanProvinceBoundarySeeder::class)->run();
        $original = MunicipalityBoundary::sole();
        $municipality = Municipality::sole();
        DB::table('municipalities')->where('id', $municipality->id)->update(['name' => 'Existing workspace', 'code' => 'EXISTING']);

        $this->assertRejected('conflicts', 1);
        $this->assertSame(1, Municipality::count());
        $this->assertSame($original->getAttributes(), $original->fresh()->getAttributes());
    }

    private function workspace(string $code): Municipality
    {
        return Municipality::create(['name' => 'Bulacan', 'code' => $code, 'province' => 'Bulacan', 'province_id' => $this->province->id, 'is_active' => true]);
    }

    private function assertRejected(string $message, int $boundaryCount = 0): void
    {
        try {
            app(BulacanProvinceBoundarySeeder::class)->run();
            $this->fail('The unsafe import should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
        $this->assertSame($boundaryCount, MunicipalityBoundary::count());
    }
}

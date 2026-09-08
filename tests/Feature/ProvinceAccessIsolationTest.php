<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use App\Support\ReferenceBoundaryAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

class ProvinceAccessIsolationTest extends TestCase
{
    use ProvinceScopeSchema;

    private Province $tarlac;

    private Province $benguet;

    private Municipality $anao;

    private Municipality $atok;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::clear();
        $this->createSchema();
        $this->tarlac = Province::create(['name' => 'Tarlac', 'is_active' => true]);
        $this->benguet = Province::create(['name' => 'Benguet', 'is_active' => true]);
        $this->anao = Municipality::create(['name' => 'Anao', 'code' => 'ANAO', 'province' => 'Tarlac', 'province_id' => $this->tarlac->id, 'is_active' => true]);
        $this->atok = Municipality::create(['name' => 'Atok', 'code' => 'ATOK', 'province' => 'Benguet', 'province_id' => $this->benguet->id, 'is_active' => true]);
        $this->owner = $this->account(User::ROLE_SYSTEM_OWNER);
        $this->admin = $this->account(User::ROLE_SUPER_ADMIN, $this->tarlac->id);
    }

    public function test_scope_is_enforced_for_lists_policies_and_requested_write_targets(): void
    {
        $local = Farmer::withoutEvents(fn () => Farmer::create(['first_name' => 'Local', 'municipality_id' => $this->anao->id]));
        $foreign = Farmer::withoutEvents(fn () => Farmer::create(['first_name' => 'Foreign', 'municipality_id' => $this->atok->id]));
        $access = app(MunicipalityAccess::class);
        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_PROVINCIAL_STAFF, User::ROLE_PROVINCIAL_VET] as $role) {
            $account = $this->account($role, $this->tarlac->id);
            $this->assertSame([$this->anao->id], $access->choices($account)->pluck('id')->all());
            $this->assertSame([$local->id], $access->scope(Farmer::query(), $account)->pluck('id')->all());
            $this->assertFalse($account->canAccessMunicipality($this->atok->id));
            try {
                $access->resolveForWrite($account, $this->atok->id);
                $this->fail('A foreign province write target was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('municipality_id', $exception->errors());
            }
        }
        $this->assertTrue($this->admin->can('view', $local));
        $this->assertFalse($this->admin->can('view', $foreign));
        $this->assertTrue($this->owner->can('view', $foreign));
        $this->assertFalse($this->owner->can('create', Farmer::class));
        $this->assertFalse($this->owner->can('viewAny', BackupFile::class));
        $municipal = $this->account(User::ROLE_MUNICIPAL_STAFF, null, $this->anao->id);
        $this->assertSame([$this->anao->id], $access->choices($municipal)->pluck('id')->all());
        $this->assertFalse($municipal->can('view', $foreign));
    }

    public function test_geofence_reads_exports_and_mutations_reject_the_other_province(): void
    {
        $boundary = $this->actingAs($this->owner)->postJson(route('municipality-boundaries.store'), $this->boundaryPayload($this->atok))
            ->assertCreated()->json('boundary.id');
        $record = MunicipalityBoundary::findOrFail($boundary);
        $original = $record->getAttributes();
        $this->actingAs($this->admin)->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->atok->id]))->assertNotFound();
        $this->get(route('municipality-boundaries.snapshot-base', $record))->assertForbidden();
        $this->postJson(route('municipality-boundaries.snapshot-exported', $record))->assertForbidden();
        $this->putJson(route('municipality-boundaries.update', $record), ['name' => 'Forbidden', '_record_version' => ConcurrentWrite::version($record)])->assertForbidden();
        $this->postJson(route('municipality-boundaries.activate', $record), ['replace_confirmed' => true, '_record_version' => ConcurrentWrite::version($record)])->assertForbidden();
        $this->postJson(route('municipality-boundaries.archive', $record), ['archive_confirmed' => true, '_record_version' => ConcurrentWrite::version($record)])->assertForbidden();
        $this->postJson(route('municipality-boundaries.store'), $this->boundaryPayload($this->atok))->assertUnprocessable();
        $this->assertSame($original, $record->fresh()->getAttributes());
        $this->get(route('municipality-boundaries.index'))->assertOk()->assertSee('Anao')->assertDontSee('Atok');
    }

    public function test_inactive_or_unassigned_province_blocks_existing_session_and_sign_in(): void
    {
        $this->admin->forceFill(['province_id' => null])->saveQuietly();
        $this->actingAs($this->admin)->getJson(route('municipality-boundaries.index'))->assertForbidden();
        $this->assertSame([], app(MunicipalityAccess::class)->choices($this->admin)->all());
        auth()->logout();
        $this->post(route('login.attempt'), ['email' => $this->admin->email, 'password' => 'test-only-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->admin->forceFill(['province_id' => $this->tarlac->id])->saveQuietly();
        $this->tarlac->update(['is_active' => false]);
        $this->actingAs($this->admin)->getJson(route('municipality-boundaries.index'))->assertForbidden();
    }

    public function test_setup_preserves_owner_password_and_is_repeatable_without_creating_operational_data(): void
    {
        $this->owner->forceFill(['role' => User::ROLE_SUPER_ADMIN])->saveQuietly();
        $this->admin->deleteQuietly();
        $staff = $this->account(User::ROLE_PROVINCIAL_STAFF);
        $password = $this->owner->password;
        $options = ['owner' => $this->owner->id, '--staff-province' => 'Tarlac', '--admin-province' => ['Benguet', 'Tarlac']];
        $this->artisan('province-access:setup', $options)->assertExitCode(0);
        $this->assertSame($password, $this->owner->fresh()->password);
        $this->assertSame(User::ROLE_SYSTEM_OWNER, $this->owner->fresh()->role);
        $this->assertSame($this->tarlac->id, $staff->fresh()->province_id);
        $admins = User::query()->where('role', User::ROLE_SUPER_ADMIN)->get();
        $this->assertCount(2, $admins);
        $this->assertTrue($admins->every(fn ($user) => ! $user->is_active && $user->province_id !== null));
        $audits = AuditLog::count();
        $versions = User::orderBy('id')->get()->map->getAttributes()->all();
        $this->artisan('province-access:setup', $options)->assertExitCode(0);
        $this->assertSame($versions, User::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($audits, AuditLog::count());
        $this->assertSame(0, Farmer::count());
    }

    public function test_import_attribution_cannot_use_a_super_admin_from_another_province(): void
    {
        $this->owner->forceFill(['is_active' => false])->saveQuietly();
        $this->assertSame($this->admin->id, ReferenceBoundaryAccess::actor('Tarlac')->id);
        $this->expectException(\RuntimeException::class);
        ReferenceBoundaryAccess::actor('Benguet');
    }

    private function account(string $role, ?int $provinceId = null, ?int $municipalityId = null): User
    {
        return User::withoutEvents(fn () => User::create([
            'name' => 'Scope test', 'email' => uniqid('scope-').'@example.test', 'role' => $role,
            'province_id' => $provinceId, 'municipality_id' => $municipalityId, 'is_active' => true,
            'password' => Hash::make('test-only-password'),
        ]));
    }

    private function boundaryPayload(Municipality $municipality): array
    {
        return ['municipality_id' => $municipality->id, 'name' => 'Test boundary', 'color' => '#236344', 'status' => 'active',
            'geojson' => ['type' => 'Polygon', 'coordinates' => [[[120.6, 16.5], [120.61, 16.5], [120.61, 16.51], [120.6, 16.51], [120.6, 16.5]]]],
        ];
    }
}

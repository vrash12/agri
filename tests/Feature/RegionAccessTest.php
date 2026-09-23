<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Support\AssistanceCoverage;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use App\Support\RegionSupervision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ProvinceScopeSchema;
use Tests\TestCase;

class RegionAccessTest extends TestCase
{
    use ProvinceScopeSchema;

    private User $owner;

    private User $regional;

    private User $provincial;

    private Province $province;

    private Province $city;

    private Province $foreign;

    private Region $region;

    private Region $otherRegion;

    private Municipality $municipality;

    private Municipality $cityMunicipality;

    private Municipality $foreignMunicipality;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::clear();
        Http::fake(['*' => Http::response('', 200)]);
        $this->createSchema();
        (require database_path('migrations/2026_09_21_000100_add_region_supervision.php'))->up();
        $this->region = Region::create(['code' => 'sample', 'name' => 'Sample region', 'is_active' => true]);
        $this->otherRegion = Region::create(['code' => 'other', 'name' => 'Other region', 'is_active' => true]);
        $this->province = Province::create(['name' => 'Example province', 'region_id' => $this->region->id, 'is_active' => true]);
        $this->city = Province::create(['name' => 'Independent city', 'region_id' => $this->region->id, 'is_active' => true]);
        $this->foreign = Province::create(['name' => 'Foreign province', 'region_id' => $this->otherRegion->id, 'is_active' => true]);
        $this->municipality = $this->municipality($this->province);
        $this->cityMunicipality = $this->municipality($this->city);
        $this->foreignMunicipality = $this->municipality($this->foreign);
        $this->owner = $this->account(User::ROLE_SYSTEM_OWNER, 'Owner');
        $this->regional = $this->account(User::ROLE_REGIONAL_HEAD, 'Regional', null, $this->region->id);
        $this->provincial = $this->account(User::ROLE_SUPER_ADMIN, 'Provincial', $this->province->id);
    }

    public function test_regional_reads_include_own_city_but_never_other_regions(): void
    {
        $access = app(MunicipalityAccess::class);
        $farmer = Farmer::withoutEvents(fn () => Farmer::create(['first_name' => 'Own', 'municipality_id' => $this->municipality->id]));
        $foreign = Farmer::withoutEvents(fn () => Farmer::create(['first_name' => 'Foreign', 'municipality_id' => $this->foreignMunicipality->id]));
        $this->assertEqualsCanonicalizing([$this->municipality->id, $this->cityMunicipality->id], $access->choices($this->regional)->modelKeys());
        $this->assertSame([$farmer->id], $access->scope(Farmer::query(), $this->regional)->pluck('id')->all());
        $this->assertTrue($this->regional->can('view', $farmer));
        $this->assertFalse($this->regional->can('view', $foreign));
        $this->assertTrue($this->regional->canAccessProvince($this->city->id));
        $this->assertFalse($this->provincial->canAccessMunicipality($this->cityMunicipality->id));
        $this->assertEqualsCanonicalizing([$this->province->id, $this->city->id], app(AssistanceCoverage::class)->provinces($this->regional)->modelKeys());
        $this->actingAs($this->regional)->getJson(route('municipality-boundaries.data', ['municipality_id' => $this->foreignMunicipality->id]))->assertNotFound();
    }

    public function test_operational_writes_geofence_changes_and_backups_remain_forbidden(): void
    {
        foreach ([Farmer::class, MunicipalityBoundary::class] as $model) {
            $this->assertFalse($this->regional->can('create', $model));
            $this->assertFalse($this->regional->can('import', $model));
        }
        $this->assertFalse($this->regional->can('viewAny', BackupFile::class));
        $this->actingAs($this->regional)->postJson(route('municipality-boundaries.store'), [])->assertForbidden();
        $this->postJson(route('farmers.store'), [])->assertForbidden();
    }

    public function test_directory_filters_statistics_and_account_choices_are_scoped(): void
    {
        $foreign = $this->account(User::ROLE_SUPER_ADMIN, 'Foreign manager', $this->foreign->id);
        $this->account(User::ROLE_REGIONAL_HEAD, 'Peer', null, $this->region->id);
        $this->actingAs($this->regional)->get(route('admins.index'))->assertOk()
            ->assertSee('Provincial')->assertDontSee('Foreign manager')->assertDontSee('Foreign province')
            ->assertDontSee('Peer')->assertViewHas('stats', fn ($stats) => $stats['total'] === 2);
        $this->get(route('admins.index', ['province_id' => $this->foreign->id, 'q' => $foreign->name]))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 0);
        $this->get(route('admins.create'))->assertOk()->assertSee('value="super_admin"', false)
            ->assertDontSee('value="regional_head"', false)->assertDontSee('Foreign province');
    }

    public function test_regional_head_can_manage_provincial_accounts_but_cannot_promote_or_move_them_outside(): void
    {
        $payload = $this->payload(['province_id' => $this->city->id]);
        $this->actingAs($this->regional)->post(route('admins.store'), $payload)->assertRedirect(route('admins.index'));
        $created = User::where('email', $payload['email'])->sole();
        $this->assertSame($this->city->id, $created->province_id);
        $this->assertNull($created->region_id);
        $this->assertTrue(Hash::check($payload['password'], $created->password));
        $audit = AuditLog::where('auditable_type', User::class)->where('auditable_id', $created->id)->latest('id')->firstOrFail();
        $this->assertSame($this->regional->id, $audit->user_id);
        $this->assertArrayNotHasKey('password', $audit->new_values);
        foreach ([User::ROLE_SYSTEM_OWNER, User::ROLE_REGIONAL_HEAD] as $role) {
            $this->postJson(route('admins.store'), $this->payload(['role' => $role, 'region_id' => $this->region->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->postJson(route('admins.store'), $this->payload(['province_id' => $this->foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('province_id');
        $this->putJson(route('admins.update', $created), $this->payload(['email' => $created->email,
            'province_id' => $this->foreign->id, '_record_version' => ConcurrentWrite::version($created)]))
            ->assertUnprocessable()->assertJsonValidationErrors('province_id');
        $this->assertSame($this->city->id, $created->fresh()->province_id);
    }

    public function test_owner_manages_regional_assignment_and_others_cannot_manage_regional_accounts(): void
    {
        $payload = $this->payload(['role' => User::ROLE_REGIONAL_HEAD, 'region_id' => $this->otherRegion->id]);
        $this->actingAs($this->owner)->post(route('admins.store'), $payload)->assertRedirect(route('admins.index'));
        $created = User::where('email', $payload['email'])->sole();
        $this->assertNull($created->province_id);
        $this->assertSame($this->otherRegion->id, $created->region_id);
        $this->assertFalse($this->regional->can('update', $created));
        $this->assertFalse($this->provincial->can('update', $this->regional));
        $this->actingAs($this->provincial)->get(route('admins.edit', $this->regional))->assertForbidden();
        $this->actingAs($this->regional)->delete(route('admins.destroy', $created))->assertForbidden();
        $this->actingAs($this->owner)->get(route('admins.create'))->assertOk()->assertSee('value="regional_head"', false);
    }

    public function test_self_edits_cannot_change_privileges_and_stale_writes_fail(): void
    {
        $before = $this->regional->only(['role', 'region_id', 'province_id', 'municipality_id', 'is_active']);
        $data = $this->payload(['email' => $this->regional->email, 'role' => User::ROLE_SYSTEM_OWNER,
            'region_id' => $this->otherRegion->id, 'is_active' => false, '_record_version' => ConcurrentWrite::version($this->regional)]);
        $this->actingAs($this->regional)->put(route('admins.update', $this->regional), $data)->assertRedirect(route('admins.index'));
        $this->assertSame($before, $this->regional->fresh()->only(array_keys($before)));
        $this->delete(route('admins.destroy', $this->regional))->assertForbidden();
        $data = $this->payload(['email' => $this->provincial->email, 'province_id' => $this->province->id,
            '_record_version' => ConcurrentWrite::version($this->provincial)]);
        $this->provincial->update(['name' => 'Concurrent edit']);
        $this->putJson(route('admins.update', $this->provincial), $data)->assertUnprocessable()->assertJsonValidationErrors('_record_version');
    }

    public function test_missing_inactive_or_mixed_scope_fails_closed(): void
    {
        foreach ([['region_id' => null], ['province_id' => $this->province->id], ['municipality_id' => $this->municipality->id]] as $invalid) {
            $user = $this->regional->replicate()->forceFill($invalid);
            $this->assertFalse($user->hasUsableScope());
            $this->assertCount(0, app(MunicipalityAccess::class)->choices($user));
        }
        $this->region->update(['is_active' => false]);
        $this->assertFalse($this->regional->hasUsableScope());
        $this->actingAs($this->regional)->get(route('admins.index'))->assertForbidden();
    }

    public function test_login_accepts_regional_role_and_rejects_deactivated_region(): void
    {
        $this->post(route('login'), ['email' => $this->regional->email, 'password' => 'Fixture-Access-Only-2026', 'confidentiality_acknowledged' => '1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->regional);
        $this->post(route('logout'));
        $this->region->update(['is_active' => false]);
        $this->post(route('login'), ['email' => $this->regional->email, 'password' => 'Fixture-Access-Only-2026', 'confidentiality_acknowledged' => '1'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_region_audit_directory_does_not_expose_global_or_other_region_events(): void
    {
        foreach ([['Own region event', $this->province->id], ['Other region event', $this->foreign->id], ['Owner-only event', null]] as [$description, $id]) {
            AuditLog::create(['event' => 'created', 'module' => 'Fixture', 'description' => $description, 'province_id' => $id]);
        }
        $this->actingAs($this->regional)->get(route('audit-logs.index'))->assertOk()->assertSee('Own region event')
            ->assertDontSee('Other region event')->assertDontSee('Owner-only event');
    }

    public function test_explicit_configuration_is_atomic_idempotent_and_preserves_city_scope(): void
    {
        foreach (RegionSupervision::GROUPS as $group) {
            foreach ([...$group['provinces'], ...$group['cities']] as $name) {
                Province::create(['name' => $name, 'is_active' => true]);
            }
        }
        $before = User::all()->toJson();
        $service = app(RegionSupervision::class);
        $this->assertCount(4, $service->configure($this->owner));
        $auditCount = AuditLog::count();
        $state = Province::all()->toJson();
        $service->configure($this->owner);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame($state, Province::all()->toJson());
        $this->assertSame($before, User::all()->toJson());
        $this->assertSame(23, Province::whereIn('region_id', Region::whereIn('code', array_keys(RegionSupervision::GROUPS))->select('id'))->count());
        $this->assertNotSame(Province::where('name', 'Bacolod City')->sole()->id, Province::where('name', 'Negros Occidental')->sole()->id);
    }

    public function test_late_configuration_conflict_rolls_back_prior_assignments(): void
    {
        foreach (RegionSupervision::GROUPS as $group) {
            foreach ([...$group['provinces'], ...$group['cities']] as $name) {
                Province::create(['name' => $name, 'is_active' => true, 'region_id' => $name === 'Bacolod City' ? $this->otherRegion->id : null]);
            }
        }
        $before = Province::all()->toJson();
        try {
            app(RegionSupervision::class)->configure($this->owner);
            $this->fail('Conflicting membership must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('differently assigned', $exception->getMessage());
        }
        $this->assertSame($before, Province::all()->toJson());
        $this->assertSame(2, Region::count());
    }

    public function test_calabarzon_can_be_configured_independently_without_changing_other_regions(): void
    {
        foreach ([...RegionSupervision::CALABARZON['provinces'], ...RegionSupervision::CALABARZON['cities']] as $name) {
            Province::create(['name' => $name, 'is_active' => true]);
        }
        $existing = $this->province->fresh()->getAttributes();
        $users = User::all()->toJson();
        $service = app(RegionSupervision::class);
        $regions = $service->configure($this->owner, 'region4a');
        $this->assertCount(1, $regions);
        $this->assertSame('region4a', $regions->sole()->code);
        $this->assertCount(6, $regions->sole()->provinces);
        $this->assertSame($existing, $this->province->fresh()->getAttributes());
        $this->assertSame($users, User::all()->toJson());
        $audits = AuditLog::count();
        $service->configure($this->owner, 'region4a');
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_calabarzon_conflict_rolls_back_and_unknown_region_is_rejected(): void
    {
        foreach ([...RegionSupervision::CALABARZON['provinces'], ...RegionSupervision::CALABARZON['cities']] as $name) {
            Province::create(['name' => $name, 'is_active' => true,
                'region_id' => $name === 'Lucena City' ? $this->otherRegion->id : null]);
        }
        $before = Province::all()->toJson();
        foreach (['region4a', 'unknown-region'] as $code) {
            try {
                app(RegionSupervision::class)->configure($this->owner, $code);
                $this->fail('Invalid configuration must fail.');
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $this->assertSame($before, Province::all()->toJson());
            $this->assertSame(2, Region::count());
        }
    }

    public function test_mimaropa_can_be_configured_independently_without_changing_other_regions(): void
    {
        foreach ([...RegionSupervision::MIMAROPA['provinces'], ...RegionSupervision::MIMAROPA['cities']] as $name) {
            Province::create(['name' => $name, 'is_active' => true]);
        }
        $existing = $this->province->fresh()->getAttributes();
        $users = User::all()->toJson();
        $service = app(RegionSupervision::class);
        $regions = $service->configure($this->owner, 'mimaropa');
        $this->assertCount(1, $regions);
        $this->assertSame('mimaropa', $regions->sole()->code);
        $this->assertCount(6, $regions->sole()->provinces);
        $this->assertSame($existing, $this->province->fresh()->getAttributes());
        $this->assertSame($users, User::all()->toJson());
        $audits = AuditLog::count();
        $service->configure($this->owner, 'mimaropa');
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_mimaropa_conflict_rolls_back_and_unknown_region_is_rejected(): void
    {
        foreach ([...RegionSupervision::MIMAROPA['provinces'], ...RegionSupervision::MIMAROPA['cities']] as $name) {
            Province::create(['name' => $name, 'is_active' => true,
                'region_id' => $name === 'Puerto Princesa City' ? $this->otherRegion->id : null]);
        }
        $before = Province::all()->toJson();
        foreach (['mimaropa', 'unknown-region'] as $code) {
            try {
                app(RegionSupervision::class)->configure($this->owner, $code);
                $this->fail('Invalid configuration must fail.');
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $this->assertSame($before, Province::all()->toJson());
            $this->assertSame(2, Region::count());
        }
    }

    public function test_bicol_can_be_configured_independently_without_changing_other_regions(): void
    {
        foreach ([...RegionSupervision::BICOL['provinces'], ...RegionSupervision::BICOL['cities']] as $name) {
            Province::create(['name' => $name, 'is_active' => true]);
        }
        $existing = $this->province->fresh()->getAttributes();
        $users = User::all()->toJson();
        $service = app(RegionSupervision::class);
        $regions = $service->configure($this->owner, 'region5');
        $this->assertCount(1, $regions);
        $this->assertSame('region5', $regions->sole()->code);
        $this->assertSame(7, $regions->sole()->provinces()->count());
        $this->assertSame($existing, $this->province->fresh()->getAttributes());
        $this->assertSame($users, User::all()->toJson());
        $audits = AuditLog::count();
        $service->configure($this->owner, 'region5');
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_bicol_conflict_rolls_back_and_unknown_region_is_rejected(): void
    {
        foreach ([...RegionSupervision::BICOL['provinces'], ...RegionSupervision::BICOL['cities']] as $name) {
            Province::create(['name' => $name, 'is_active' => true,
                'region_id' => $name === 'Naga City' ? $this->otherRegion->id : null]);
        }
        $before = Province::all()->toJson();
        foreach (['region5', 'unknown-region'] as $code) {
            try {
                app(RegionSupervision::class)->configure($this->owner, $code);
                $this->fail('Invalid configuration must fail.');
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $this->assertSame($before, Province::all()->toJson());
            $this->assertSame(2, Region::count());
        }
    }

    public function test_migration_rollback_disables_regional_identities(): void
    {
        (require database_path('migrations/2026_09_21_000100_add_region_supervision.php'))->down();
        $this->assertFalse(Schema::hasTable('regions'));
        $this->assertFalse(Schema::hasColumn('users', 'region_id'));
        $this->assertFalse($this->regional->fresh()->is_active);
    }

    private function municipality(Province $province): Municipality
    {
        return Municipality::withoutEvents(fn () => Municipality::create(['name' => $province->name.' municipality', 'province' => $province->name,
            'province_id' => $province->id, 'is_active' => true]));
    }

    private function account(string $role, string $name, ?int $provinceId = null, ?int $regionId = null): User
    {
        return User::withoutEvents(fn () => User::create(['name' => $name, 'email' => str_replace(' ', '-', strtolower($name)).'@example.test',
            'password' => Hash::make('Fixture-Access-Only-2026'), 'role' => $role, 'province_id' => $provinceId,
            'region_id' => $regionId, 'is_active' => true])->refresh());
    }

    private function payload(array $values = []): array
    {
        return array_merge(['name' => 'New fixture account', 'email' => uniqid('fixture-').'@example.test', 'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'Fixture-Access-Only-2026', 'password_confirmation' => 'Fixture-Access-Only-2026', 'is_active' => true], $values);
    }
}

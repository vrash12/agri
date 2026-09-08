<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProvinceUserManagementTest extends TestCase
{
    private Province $tarlac;

    private Province $benguet;

    private Municipality $anao;

    private Municipality $atok;

    private User $owner;

    private User $admin;

    private User $foreignAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Cache::clear();
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('province');
            $table->foreignId('province_id')->nullable()->constrained();
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->foreignId('province_id')->nullable()->constrained();
            $table->foreignId('municipality_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_19_000300_create_audit_logs_table.php'))->up();
        Schema::table('audit_logs', fn (Blueprint $table) => $table->unsignedBigInteger('province_id')->nullable());
        $this->tarlac = Province::create(['name' => 'Tarlac', 'is_active' => true]);
        $this->benguet = Province::create(['name' => 'Benguet', 'is_active' => true]);
        $this->anao = Municipality::create(['name' => 'Anao', 'province' => 'Tarlac', 'province_id' => $this->tarlac->id, 'is_active' => true]);
        $this->atok = Municipality::create(['name' => 'Atok', 'province' => 'Benguet', 'province_id' => $this->benguet->id, 'is_active' => true]);
        $this->owner = $this->account(User::ROLE_SYSTEM_OWNER, 'Owner');
        $this->admin = $this->account(User::ROLE_SUPER_ADMIN, 'Tarlac Admin', $this->tarlac->id);
        $this->foreignAdmin = $this->account(User::ROLE_SUPER_ADMIN, 'Benguet Admin', $this->benguet->id);
    }

    public function test_super_admin_directory_statistics_and_options_are_limited_to_own_province(): void
    {
        $this->account(User::ROLE_PROVINCIAL_STAFF, 'Tarlac Staff', $this->tarlac->id);
        $this->account(User::ROLE_MUNICIPAL_STAFF, 'Anao Staff', null, $this->anao->id);
        $this->account(User::ROLE_PROVINCIAL_STAFF, 'Foreign Provincial', $this->benguet->id);
        $this->account(User::ROLE_MUNICIPAL_STAFF, 'Foreign Municipal', null, $this->atok->id);
        $this->actingAs($this->admin)->get(route('admins.index'))->assertOk()
            ->assertSee('Tarlac Staff')->assertSee('Anao Staff')->assertDontSee('Foreign Provincial')->assertDontSee('Foreign Municipal')->assertDontSee('Benguet Admin')->assertDontSee('Atok')
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 3 && $stats['active'] === 3)
            ->assertViewHas('municipalities', fn ($options) => $options->pluck('id')->all() === [$this->anao->id]);
        $this->actingAs($this->admin)->get(route('admins.index', ['municipality_id' => $this->atok->id]))->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 0);
        $this->actingAs($this->admin)->get(route('admins.create'))->assertOk()->assertDontSee('Benguet')->assertDontSee('value="super_admin"', false);
    }

    public function test_owner_can_create_province_super_admin_and_filter_global_directory(): void
    {
        $payload = $this->payload(['role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->benguet->id]);
        $this->actingAs($this->owner)->post(route('admins.store'), $payload)->assertRedirect(route('admins.index'));
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->benguet->id, 'municipality_id' => null]);
        $this->actingAs($this->owner)->get(route('admins.index', ['province_id' => $this->benguet->id]))->assertOk()->assertSee('Benguet Admin')->assertDontSee('Tarlac Admin');
        $this->actingAs($this->owner)->get(route('admins.create'))->assertOk()->assertSee('value="super_admin"', false)->assertSee('Benguet')->assertSee('Tarlac')->assertDontSee('value="system_owner"', false);
        $audit = AuditLog::where('auditable_type', User::class)->where('auditable_id', User::where('email', $payload['email'])->value('id'))->latest('id')->firstOrFail();
        $this->assertSame($this->owner->id, $audit->user_id);
        $this->assertArrayNotHasKey('password', $audit->new_values);
    }

    public function test_super_admin_cannot_create_global_roles_or_assign_foreign_scopes(): void
    {
        foreach ([User::ROLE_SYSTEM_OWNER, User::ROLE_SUPER_ADMIN] as $role) {
            $this->actingAs($this->admin)->postJson(route('admins.store'), $this->payload(['role' => $role, 'province_id' => $this->tarlac->id]))->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->actingAs($this->admin)->postJson(route('admins.store'), $this->payload(['province_id' => $this->benguet->id]))->assertUnprocessable()->assertJsonValidationErrors('province_id');
        $this->actingAs($this->admin)->postJson(route('admins.store'), $this->payload(['role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $this->atok->id]))->assertUnprocessable()->assertJsonValidationErrors('municipality_id');
        $this->assertDatabaseCount('users', 3);
    }

    public function test_scoped_admin_can_create_provincial_vet_and_municipal_staff(): void
    {
        $this->actingAs($this->admin)->post(route('admins.store'), $this->payload(['role' => User::ROLE_PROVINCIAL_VET]))->assertRedirect(route('admins.index'));
        $this->assertDatabaseHas('users', ['role' => User::ROLE_PROVINCIAL_VET, 'province_id' => $this->tarlac->id, 'municipality_id' => null]);
        $this->actingAs($this->admin)->post(route('admins.store'), $this->payload(['role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $this->anao->id, 'province_id' => $this->benguet->id]))->assertRedirect(route('admins.index'));
        $this->assertDatabaseHas('users', ['role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $this->anao->id, 'province_id' => null]);
    }

    public function test_protected_and_foreign_accounts_cannot_be_opened_changed_or_deleted_by_super_admin(): void
    {
        $foreignStaff = $this->account(User::ROLE_MUNICIPAL_STAFF, 'Foreign Staff', null, $this->atok->id);
        $sameProvinceAdmin = $this->account(User::ROLE_SUPER_ADMIN, 'Other Admin', $this->tarlac->id);
        foreach ([$this->owner, $this->foreignAdmin, $sameProvinceAdmin, $foreignStaff] as $account) {
            $this->actingAs($this->admin)->get(route('admins.edit', $account))->assertForbidden();
            $this->actingAs($this->admin)->putJson(route('admins.update', $account), $this->payload(['_record_version' => ConcurrentWrite::version($account)]))->assertForbidden();
            $this->actingAs($this->admin)->delete(route('admins.destroy', $account))->assertForbidden();
        }
        $this->assertDatabaseCount('users', 5);
    }

    public function test_owner_and_super_admin_can_only_update_profile_on_their_own_account(): void
    {
        foreach ([$this->owner, $this->admin] as $account) {
            $original = $account->only(['role', 'province_id', 'municipality_id', 'is_active']);
            $payload = $this->payload(['name' => 'Updated Profile', 'email' => $account->email, 'role' => User::ROLE_MUNICIPAL_STAFF, 'province_id' => $this->benguet->id, 'municipality_id' => $this->atok->id, 'is_active' => false, '_record_version' => ConcurrentWrite::version($account)]);
            $this->actingAs($account)->put(route('admins.update', $account), $payload)->assertRedirect(route('admins.index'));
            $this->assertSame($original, $account->fresh()->only(array_keys($original)));
            $this->assertSame('Updated Profile', $account->fresh()->name);
            $this->actingAs($account)->delete(route('admins.destroy', $account))->assertForbidden();
        }
        $otherOwner = $this->account(User::ROLE_SYSTEM_OWNER, 'Other Owner');
        $this->actingAs($this->owner)->get(route('admins.edit', $otherOwner))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('admins.destroy', $otherOwner))->assertForbidden();
        $this->actingAs($this->owner)->postJson(route('admins.store'), $this->payload(['role' => User::ROLE_SYSTEM_OWNER]))->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_owner_must_set_password_when_activating_an_inactive_super_admin(): void
    {
        $this->foreignAdmin->update(['is_active' => false]);
        $this->foreignAdmin->refresh();
        $payload = $this->payload(['email' => $this->foreignAdmin->email, 'role' => User::ROLE_SUPER_ADMIN, 'province_id' => $this->benguet->id, '_record_version' => ConcurrentWrite::version($this->foreignAdmin)]);
        unset($payload['password'], $payload['password_confirmation']);
        $this->actingAs($this->owner)->putJson(route('admins.update', $this->foreignAdmin), $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertFalse($this->foreignAdmin->fresh()->is_active);
        $payload['password'] = $payload['password_confirmation'] = 'New-Test-Password';
        $this->actingAs($this->owner)->put(route('admins.update', $this->foreignAdmin), $payload)->assertRedirect(route('admins.index'));
        $this->assertTrue($this->foreignAdmin->fresh()->is_active);
        $this->assertTrue(Hash::check('New-Test-Password', $this->foreignAdmin->fresh()->password));
    }

    public function test_invalid_or_inactive_assignments_are_rejected_and_active_head_is_unique(): void
    {
        $this->actingAs($this->owner)->postJson(route('admins.store'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('province_id');
        $this->benguet->update(['is_active' => false]);
        $this->actingAs($this->owner)->postJson(route('admins.store'), $this->payload(['province_id' => $this->benguet->id]))->assertUnprocessable()->assertJsonValidationErrors('province_id');
        $this->actingAs($this->owner)->postJson(route('admins.store'), $this->payload(['role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $this->atok->id]))->assertUnprocessable()->assertJsonValidationErrors('municipality_id');
        $this->account(User::ROLE_MUNICIPAL_HEAD, 'Existing Head', null, $this->anao->id);
        $this->actingAs($this->admin)->postJson(route('admins.store'), $this->payload(['role' => User::ROLE_MUNICIPAL_HEAD, 'municipality_id' => $this->anao->id]))->assertUnprocessable()->assertJsonValidationErrors('municipality_id');
    }

    public function test_municipal_head_management_stays_in_its_own_municipality(): void
    {
        $head = $this->account(User::ROLE_MUNICIPAL_HEAD, 'Head', null, $this->anao->id);
        $foreignStaff = $this->account(User::ROLE_MUNICIPAL_STAFF, 'Foreign Staff', null, $this->atok->id);
        $this->actingAs($head)->post(route('admins.store'), $this->payload(['role' => User::ROLE_SUPER_ADMIN, 'municipality_id' => $this->atok->id, 'province_id' => $this->benguet->id]))->assertRedirect(route('admins.index'));
        $this->assertDatabaseHas('users', ['name' => 'New Test Account', 'role' => User::ROLE_MUNICIPAL_STAFF, 'municipality_id' => $this->anao->id, 'province_id' => null]);
        $this->actingAs($head)->get(route('admins.index'))->assertOk()->assertDontSee('Foreign Staff')->assertDontSee('Atok');
        $this->actingAs($head)->delete(route('admins.destroy', $foreignStaff))->assertForbidden();
    }

    public function test_edit_requires_fresh_version_and_cannot_move_account_to_foreign_province(): void
    {
        $staff = $this->account(User::ROLE_PROVINCIAL_STAFF, 'Own Staff', $this->tarlac->id);
        $payload = $this->payload(['email' => $staff->email, 'province_id' => $this->benguet->id, '_record_version' => ConcurrentWrite::version($staff)]);
        $this->actingAs($this->admin)->putJson(route('admins.update', $staff), $payload)->assertUnprocessable()->assertJsonValidationErrors('province_id');
        $payload['province_id'] = $this->tarlac->id;
        $staff->update(['name' => 'Concurrent Edit']);
        $this->actingAs($this->admin)->putJson(route('admins.update', $staff), $payload)->assertUnprocessable()->assertJsonValidationErrors('_record_version');
        $this->assertSame('Concurrent Edit', $staff->fresh()->name);
    }

    public function test_delete_rechecks_ownership_after_obtaining_record_lock(): void
    {
        $staff = $this->account(User::ROLE_PROVINCIAL_STAFF, 'Moving Staff', $this->tarlac->id);
        $foreignProvinceId = $this->benguet->id;
        $reads = 0;
        User::retrieved(function (User $current) use ($staff, $foreignProvinceId, &$reads): void {
            if ($current->id === $staff->id && ++$reads === 2) {
                $current->newModelQuery()->whereKey($current->id)->update(['province_id' => $foreignProvinceId]);
                $current->province_id = $foreignProvinceId;
            }
        });
        $this->actingAs($this->admin)->delete(route('admins.destroy', $staff))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    private function account(string $role, string $name, ?int $provinceId = null, ?int $municipalityId = null): User
    {
        return User::create(['name' => $name, 'email' => str_replace(' ', '-', strtolower($name)).'@example.test', 'password' => Hash::make('Test-Password-Only'), 'role' => $role, 'province_id' => $provinceId, 'municipality_id' => $municipalityId, 'is_active' => true])->refresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'New Test Account', 'email' => uniqid('account-').'@example.test', 'role' => User::ROLE_PROVINCIAL_STAFF, 'password' => 'Test-Password-Only', 'password_confirmation' => 'Test-Password-Only', 'is_active' => true], $overrides);
    }
}

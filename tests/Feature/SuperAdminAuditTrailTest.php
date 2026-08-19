<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminAuditTrailTest extends TestCase
{
    use DatabaseTransactions;

    private Municipality $municipality;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));
        $this->municipality = Municipality::create([
            'name' => 'Audit Municipality '.$suffix,
            'province' => 'Tarlac',
            'code' => 'AU'.substr($suffix, -8),
            'is_active' => true,
        ]);
        $this->superAdmin = $this->makeUser(
            User::ROLE_SUPER_ADMIN,
            null,
            'audit-admin-'.$suffix.'@example.test',
            'Audit Super Admin'
        );
    }

    public function test_only_super_admin_can_open_or_export_the_audit_trail(): void
    {
        $log = AuditLog::query()->create([
            'event' => 'created',
            'module' => 'Farmers',
            'description' => 'Test audit event.',
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit trail')
            ->assertSee('Export filtered CSV')
            ->assertSee('Test audit event.');

        $this->actingAs($this->superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-label="Audit Trail"', false)
            ->assertSee('Review audit trail');

        foreach ([User::ROLE_PROVINCIAL_STAFF, User::ROLE_MUNICIPAL_HEAD, User::ROLE_MUNICIPAL_STAFF] as $role) {
            $user = $this->makeUser(
                $role,
                str_starts_with($role, 'municipal_') ? $this->municipality->id : null,
                $role.'-'.uniqid().'@example.test',
                'Unauthorized Audit User'
            );

            $this->actingAs($user)->get(route('audit-logs.index'))->assertForbidden();
            $this->actingAs($user)->get(route('audit-logs.show', $log))->assertForbidden();
            $this->actingAs($user)->get(route('audit-logs.export'))->assertForbidden();
        }
    }

    public function test_model_changes_capture_actor_scope_and_before_after_values(): void
    {
        $staff = $this->makeUser(
            User::ROLE_MUNICIPAL_STAFF,
            $this->municipality->id,
            'audit-staff-'.uniqid().'@example.test',
            'Audit Municipal Staff'
        );
        $this->actingAs($staff);

        $farmer = Farmer::create([
            'municipality_id' => $this->municipality->id,
            'first_name' => 'Original',
            'last_name' => 'Farmer',
        ]);

        $created = AuditLog::query()
            ->where('event', 'created')
            ->where('auditable_type', Farmer::class)
            ->where('auditable_id', (string) $farmer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($staff->id, $created->user_id);
        $this->assertSame($this->municipality->id, $created->municipality_id);
        $this->assertSame('Farmers', $created->module);
        $this->assertSame('Original', $created->new_values['first_name']);
        $this->assertArrayNotHasKey('public_map_token', $created->new_values);

        $farmer->update(['first_name' => 'Updated']);
        $updated = AuditLog::query()
            ->where('event', 'updated')
            ->where('auditable_type', Farmer::class)
            ->where('auditable_id', (string) $farmer->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Original', $updated->old_values['first_name']);
        $this->assertSame('Updated', $updated->new_values['first_name']);

        $farmer->delete();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'deleted',
            'module' => 'Farmers',
            'auditable_type' => Farmer::class,
            'auditable_id' => (string) $farmer->id,
            'user_id' => $staff->id,
        ]);
    }

    public function test_authentication_events_are_recorded_without_credentials(): void
    {
        $email = 'login-audit-'.uniqid().'@example.test';
        $user = $this->makeUser(
            User::ROLE_PROVINCIAL_STAFF,
            null,
            $email,
            'Login Audit User'
        );

        $this->post(route('login.attempt'), [
            'email' => $email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login',
            'module' => 'Authentication',
            'user_id' => $user->id,
            'actor_email' => $email,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'logout',
            'module' => 'Authentication',
            'user_id' => $user->id,
        ]);

        $this->post(route('login.attempt'), [
            'email' => $email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');

        $failed = AuditLog::query()
            ->where('event', 'login_failed')
            ->where('actor_email', $email)
            ->latest('id')
            ->firstOrFail();

        $serialized = json_encode($failed->toArray());
        $this->assertStringNotContainsString('incorrect-password', $serialized);
    }

    public function test_password_changes_are_not_stored_in_before_or_after_values(): void
    {
        $target = $this->makeUser(
            User::ROLE_PROVINCIAL_STAFF,
            null,
            'password-audit-'.uniqid().'@example.test',
            'Password Audit Target'
        );
        $this->actingAs($this->superAdmin);

        $newHash = Hash::make('new-secret-password');
        $target->update(['password' => $newHash]);

        $log = AuditLog::query()
            ->where('event', 'updated')
            ->where('auditable_type', User::class)
            ->where('auditable_id', (string) $target->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertSame(['password'], $log->metadata['protected_fields_changed']);
        $this->assertStringNotContainsString($newHash, json_encode($log->toArray()));
    }

    public function test_filters_and_csv_export_use_the_same_scope(): void
    {
        AuditLog::query()->create([
            'event' => 'updated',
            'module' => 'Farmers',
            'description' => 'Matching audit marker.',
            'actor_name' => $this->superAdmin->name,
            'actor_email' => $this->superAdmin->email,
            'user_id' => $this->superAdmin->id,
        ]);
        AuditLog::query()->create([
            'event' => 'deleted',
            'module' => 'Vaccinations',
            'description' => 'Excluded audit marker.',
        ]);

        $filters = ['event' => 'updated', 'module' => 'Farmers'];

        $this->actingAs($this->superAdmin)
            ->get(route('audit-logs.index', $filters))
            ->assertOk()
            ->assertSee('Matching audit marker.')
            ->assertDontSee('Excluded audit marker.');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('audit-logs.export', $filters))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Matching audit marker.', $csv);
        $this->assertStringNotContainsString('Excluded audit marker.', $csv);
    }

    private function makeUser(
        string $role,
        ?int $municipalityId,
        string $email,
        string $name
    ): User {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'municipality_id' => $municipalityId,
            'is_active' => true,
        ]);
    }
}

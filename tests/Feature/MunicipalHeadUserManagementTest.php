<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\User;
use App\Support\ConcurrentWrite;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MunicipalHeadUserManagementTest extends TestCase
{
    use DatabaseTransactions;

    private Municipality $managedMunicipality;

    private Municipality $otherMunicipality;

    private User $municipalHead;

    private User $ownStaff;

    private User $foreignStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('', true));

        $this->managedMunicipality = $this->makeMunicipality(
            'Managed Municipality '.$suffix,
            'MH'.$suffix
        );
        $this->otherMunicipality = $this->makeMunicipality(
            'Other Municipality '.$suffix,
            'OT'.$suffix
        );

        $this->municipalHead = $this->makeUser(
            User::ROLE_MUNICIPAL_HEAD,
            $this->managedMunicipality->id,
            'head-'.$suffix.'@example.test',
            'Managed Head '.$suffix
        );
        $this->ownStaff = $this->makeUser(
            User::ROLE_MUNICIPAL_STAFF,
            $this->managedMunicipality->id,
            'own-staff-'.$suffix.'@example.test',
            'Visible Own Staff '.$suffix
        );
        $this->foreignStaff = $this->makeUser(
            User::ROLE_MUNICIPAL_STAFF,
            $this->otherMunicipality->id,
            'foreign-staff-'.$suffix.'@example.test',
            'Hidden Foreign Staff '.$suffix
        );
    }

    public function test_municipal_head_sees_only_staff_from_own_municipality(): void
    {
        $otherHead = $this->makeUser(
            User::ROLE_MUNICIPAL_HEAD,
            $this->managedMunicipality->id,
            'other-head-'.uniqid().'@example.test',
            'Hidden Municipal Head'
        );

        $this->actingAs($this->municipalHead)
            ->get(route('admins.index'))
            ->assertOk()
            ->assertSee('Municipal Staff Management')
            ->assertSee($this->ownStaff->name)
            ->assertDontSee($this->foreignStaff->name)
            ->assertDontSee($otherHead->name);
    }

    public function test_municipal_head_can_create_staff_only_for_own_municipality(): void
    {
        $email = 'created-staff-'.uniqid().'@example.test';

        $this->actingAs($this->municipalHead)
            ->post(route('admins.store'), [
                'name' => 'Created Municipal Staff',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => User::ROLE_MUNICIPAL_STAFF,
                'municipality_id' => $this->otherMunicipality->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admins.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->managedMunicipality->id,
            'is_active' => 1,
        ]);

        $this->actingAs($this->municipalHead)
            ->post(route('admins.store'), [
                'name' => 'Attempted Provincial User',
                'email' => 'escalation-'.uniqid().'@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => User::ROLE_PROVINCIAL_STAFF,
                'municipality_id' => $this->managedMunicipality->id,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_municipal_head_can_update_and_delete_own_staff(): void
    {
        $updatedEmail = 'updated-own-staff-'.uniqid().'@example.test';

        $this->actingAs($this->municipalHead)
            ->put(route('admins.update', $this->ownStaff), [
                '_record_version' => ConcurrentWrite::version($this->ownStaff),
                'name' => 'Updated Own Staff',
                'email' => $updatedEmail,
                'role' => User::ROLE_MUNICIPAL_STAFF,
                'municipality_id' => $this->otherMunicipality->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admins.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $this->ownStaff->id,
            'name' => 'Updated Own Staff',
            'email' => $updatedEmail,
            'role' => User::ROLE_MUNICIPAL_STAFF,
            'municipality_id' => $this->managedMunicipality->id,
        ]);

        $this->actingAs($this->municipalHead)
            ->delete(route('admins.destroy', $this->ownStaff))
            ->assertRedirect(route('admins.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $this->ownStaff->id,
        ]);
    }

    public function test_municipal_head_cannot_manage_foreign_staff_or_privileged_accounts(): void
    {
        $otherHead = $this->makeUser(
            User::ROLE_MUNICIPAL_HEAD,
            $this->managedMunicipality->id,
            'protected-head-'.uniqid().'@example.test',
            'Protected Municipal Head'
        );

        $this->actingAs($this->municipalHead)
            ->get(route('admins.edit', $this->foreignStaff))
            ->assertForbidden();

        $this->actingAs($this->municipalHead)
            ->put(route('admins.update', $this->foreignStaff), [
                'name' => 'Unauthorized Update',
                'email' => $this->foreignStaff->email,
                'role' => User::ROLE_MUNICIPAL_STAFF,
                'municipality_id' => $this->managedMunicipality->id,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($this->municipalHead)
            ->delete(route('admins.destroy', $this->foreignStaff))
            ->assertForbidden();

        $this->actingAs($this->municipalHead)
            ->get(route('admins.edit', $otherHead))
            ->assertForbidden();
    }

    public function test_ordinary_staff_cannot_open_user_management(): void
    {
        $this->actingAs($this->ownStaff)
            ->get(route('admins.index'))
            ->assertForbidden();
    }

    private function makeMunicipality(string $name, string $code): Municipality
    {
        return Municipality::create([
            'name' => $name,
            'province' => 'Tarlac',
            'code' => substr($code, 0, 20),
            'is_active' => true,
        ]);
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

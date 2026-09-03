<?php

namespace Tests\Unit;

use App\Models\MunicipalityBoundary;
use App\Models\User;
use App\Policies\MunicipalityBoundaryPolicy;
use PHPUnit\Framework\TestCase;

class MunicipalityBoundaryPolicyTest extends TestCase
{
    public function test_only_active_super_admin_can_modify_boundaries(): void
    {
        $policy = new MunicipalityBoundaryPolicy();
        $boundary = new MunicipalityBoundary(['municipality_id' => 10]);

        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $provincial = $this->user(User::ROLE_PROVINCIAL_STAFF);
        $municipal = $this->user(User::ROLE_MUNICIPAL_STAFF, 10);

        $this->assertTrue($policy->create($superAdmin));
        $this->assertTrue($policy->update($superAdmin, $boundary));
        $this->assertTrue($policy->activate($superAdmin, $boundary));
        $this->assertFalse($policy->create($provincial));
        $this->assertFalse($policy->update($municipal, $boundary));
    }

    public function test_municipal_users_can_view_only_their_boundary(): void
    {
        $policy = new MunicipalityBoundaryPolicy();
        $own = new MunicipalityBoundary(['municipality_id' => 10]);
        $foreign = new MunicipalityBoundary(['municipality_id' => 11]);
        $municipal = $this->user(User::ROLE_MUNICIPAL_STAFF, 10);

        $this->assertTrue($policy->view($municipal, $own));
        $this->assertFalse($policy->view($municipal, $foreign));
    }

    private function user(string $role, ?int $municipalityId = null): User
    {
        return new User([
            'role' => $role,
            'municipality_id' => $municipalityId,
            'is_active' => true,
        ]);
    }
}

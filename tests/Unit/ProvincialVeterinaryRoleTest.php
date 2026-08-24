<?php

namespace Tests\Unit;

use App\Models\AntiRabiesVaccination;
use App\Models\User;
use App\Policies\AntiRabiesVaccinationPolicy;
use App\Policies\FarmerPolicy;
use App\Policies\RiceSeedDistributionPolicy;
use PHPUnit\Framework\TestCase;

class ProvincialVeterinaryRoleTest extends TestCase
{
    public function test_role_has_province_wide_animal_health_write_access(): void
    {
        $user = new User([
            'role' => User::ROLE_PROVINCIAL_VET,
            'is_active' => true,
        ]);
        $record = new AntiRabiesVaccination([
            'municipality_id' => 99,
        ]);
        $policy = new AntiRabiesVaccinationPolicy();

        $this->assertTrue($user->isProvincialVeterinaryOffice());
        $this->assertTrue($user->canAccessAllMunicipalities());
        $this->assertFalse($user->requiresMunicipality());
        $this->assertFalse($user->canManageOperationalData());
        $this->assertNull($policy->before($user));
        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->view($user, $record));
        $this->assertTrue($policy->update($user, $record));
        $this->assertTrue($policy->delete($user, $record));
    }

    public function test_role_is_denied_by_other_operational_policies(): void
    {
        $user = new User([
            'role' => User::ROLE_PROVINCIAL_VET,
            'is_active' => true,
        ]);

        $this->assertFalse((new FarmerPolicy())->before($user));
        $this->assertFalse(
            (new RiceSeedDistributionPolicy())->before($user)
        );
    }
}

<?php

namespace App\Policies;

use App\Models\MunicipalityProfile;
use App\Models\User;

class MunicipalityProfilePolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasUsableScope() && ! $user->isVisitor()
            && ! $user->isGisEvaluator() && ! $user->isProvincialVeterinaryOffice() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MunicipalityProfile $profile): bool
    {
        return $user->canAccessMunicipality($profile->municipality_id);
    }

    public function update(User $user, MunicipalityProfile $profile): bool
    {
        return ($user->isSystemOwner() || $user->isSuperAdmin() || $user->isMunicipalHead())
            && $this->view($user, $profile);
    }
}

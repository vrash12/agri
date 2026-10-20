<?php

namespace App\Policies;

use App\Models\MunicipalityProfile;
use App\Models\MunicipalityWebsite;
use App\Models\User;

class MunicipalityWebsitePolicy
{
    public function view(User $user, MunicipalityWebsite $website): bool
    {
        return $user->can('view', $this->profile($website));
    }

    public function update(User $user, MunicipalityWebsite $website): bool
    {
        return $user->can('update', $this->profile($website));
    }

    private function profile(MunicipalityWebsite $website): MunicipalityProfile
    {
        $profile = new MunicipalityProfile;
        $profile->municipality_id = $website->municipality_id;

        return $profile;
    }
}

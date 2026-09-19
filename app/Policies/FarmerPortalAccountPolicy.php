<?php

namespace App\Policies;

use App\Models\FarmerPortalAccount;

class FarmerPortalAccountPolicy
{
    public function view(FarmerPortalAccount $actor, FarmerPortalAccount $account): bool
    {
        return $actor->id === $account->id && $actor->farmer_id === $account->farmer_id
            && $actor->municipality_id === $account->municipality_id && $account->hasUsableScope();
    }
}

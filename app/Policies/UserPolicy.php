<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        if (! $user->isActive() || ! $user->hasAnyRole(User::ROLES)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isMunicipalHead() && $user->municipality_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, User $account): bool
    {
        return $this->canManageMunicipalAccount($user, $account);
    }

    public function update(User $user, User $account): bool
    {
        return $this->canManageMunicipalAccount($user, $account);
    }

    public function delete(User $user, User $account): bool
    {
        return $this->canManageMunicipalAccount($user, $account);
    }

    private function canManageMunicipalAccount(
        User $user,
        User $account
    ): bool {
        return $user->isMunicipalHead()
            && $user->municipality_id !== null
            && $account->isMunicipalStaff()
            && (int) $account->municipality_id === (int) $user->municipality_id;
    }
}

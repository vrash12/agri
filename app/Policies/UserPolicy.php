<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isActive() && $user->hasAnyRole(User::ROLES) && $user->hasUsableScope()
            ? null
            : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->isSystemOwner() || $user->isSuperAdmin() || $user->isMunicipalHead();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, User $account): bool
    {
        return $this->update($user, $account);
    }

    public function update(User $user, User $account): bool
    {
        if ($user->is($account) && ($user->isSystemOwner() || $user->isSuperAdmin())) {
            return true;
        }

        return $this->canManageAccount($user, $account);
    }

    public function delete(User $user, User $account): bool
    {
        return ! $user->is($account) && $this->canManageAccount($user, $account);
    }

    private function canManageAccount(
        User $user,
        User $account
    ): bool {
        if ($account->isSystemOwner()) {
            return false;
        }

        if ($user->isSystemOwner()) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            if ($account->isSuperAdmin()) {
                return false;
            }

            return $account->requiresProvince()
                ? $user->canAccessProvince($account->province_id)
                : ($account->isMunicipalUser() && $user->canAccessMunicipality($account->municipality_id));
        }

        return $user->isMunicipalHead()
            && $account->isMunicipalStaff()
            && $user->canAccessMunicipality($account->municipality_id);
    }
}

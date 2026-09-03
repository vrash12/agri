<?php

namespace App\Policies;

use App\Models\MunicipalityBoundary;
use App\Models\User;

class MunicipalityBoundaryPolicy
{
    public function before(User $user): ?bool
    {
        if (! $user->isActive() || ! $user->hasAnyRole(User::ROLES)) {
            return false;
        }

        if ($user->isProvincialVeterinaryOffice()) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canAccessAllMunicipalities() || $user->municipality_id !== null;
    }

    public function view(User $user, MunicipalityBoundary $boundary): bool
    {
        return $user->canAccessMunicipality($boundary->municipality_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function import(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, MunicipalityBoundary $boundary): bool
    {
        return $user->isSuperAdmin();
    }

    public function activate(User $user, MunicipalityBoundary $boundary): bool
    {
        return $user->isSuperAdmin();
    }

    public function archive(User $user, MunicipalityBoundary $boundary): bool
    {
        return $user->isSuperAdmin();
    }
}

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
        return $user->hasUsableScope();
    }

    public function view(User $user, MunicipalityBoundary $boundary): bool
    {
        return $user->canAccessMunicipality($boundary->municipality_id);
    }

    public function create(User $user): bool
    {
        return $user->canOverseeSystem() && $user->hasUsableScope();
    }

    public function import(User $user): bool
    {
        return $this->create($user);
    }

    public function update(User $user, MunicipalityBoundary $boundary): bool
    {
        return $this->create($user) && $this->view($user, $boundary);
    }

    public function activate(User $user, MunicipalityBoundary $boundary): bool
    {
        return $this->update($user, $boundary);
    }

    public function archive(User $user, MunicipalityBoundary $boundary): bool
    {
        return $this->update($user, $boundary);
    }
}

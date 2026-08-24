<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesMunicipalityRecords
{
    public function before(User $user): ?bool
    {
        if (! $user->isActive() || ! $user->hasAnyRole(User::ROLES)) {
            return false;
        }

        if (
            $user->isProvincialVeterinaryOffice()
            && ! $this->allowsProvincialVeterinaryOffice()
        ) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasUsableScope($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageModule($user)
            && $this->hasUsableScope($user);
    }

    public function import(User $user): bool
    {
        return $this->canManageModule($user)
            && $this->hasUsableScope($user);
    }

    public function export(User $user, ?Model $record = null): bool
    {
        return $record
            ? $this->view($user, $record)
            : $this->hasUsableScope($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $user->canAccessMunicipality(
            $this->municipalityId($record)
        );
    }

    public function update(User $user, Model $record): bool
    {
        return $this->canManageModule($user)
            && $this->view($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->canManageModule($user)
            && $this->view($user, $record);
    }

    protected function municipalityId(Model $record): ?int
    {
        $value = $record->getAttribute('municipality_id');

        return $value === null ? null : (int) $value;
    }

    private function hasUsableScope(User $user): bool
    {
        return $user->canAccessAllMunicipalities()
            || $user->municipality_id !== null;
    }

    private function canManageModule(User $user): bool
    {
        return $user->canManageOperationalData()
            || ($user->isProvincialVeterinaryOffice()
                && $this->allowsProvincialVeterinaryOffice());
    }

    protected function allowsProvincialVeterinaryOffice(): bool
    {
        return false;
    }
}

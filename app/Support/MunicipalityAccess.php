<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MunicipalityAccess
{
    public function scope(Builder $query, User $user, ?string $qualifiedColumn = null): Builder
    {
        if (! $user->hasUsableScope()) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isSystemOwner()) {
            return $query;
        }
        $column = $qualifiedColumn ?: $query->getModel()->qualifyColumn('municipality_id');

        return $query->whereIn($column, $this->scopeMunicipalities(Municipality::query(), $user)->select('municipalities.id'));
    }

    public function scopeMunicipalities(Builder $query, User $user): Builder
    {
        if (! $user->hasUsableScope()) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isSystemOwner()) {
            return $query;
        }
        if ($user->requiresProvince()) {
            return $query->where('municipalities.province_id', $user->province_id);
        }

        return $query->whereKey($user->municipality_id);
    }

    public function applyOptionalFilter(Builder $query, User $user, mixed $requestedMunicipalityId, ?string $qualifiedColumn = null): Builder
    {
        $this->scope($query, $user, $qualifiedColumn);
        if (! $user->canAccessAllMunicipalities() || $requestedMunicipalityId === null || $requestedMunicipalityId === '') {
            return $query;
        }
        $municipalityId = $this->validateActiveMunicipality($user, $requestedMunicipalityId);

        return $query->where($qualifiedColumn ?: $query->getModel()->qualifyColumn('municipality_id'), $municipalityId);
    }

    public function resolveForWrite(User $user, mixed $requestedMunicipalityId = null): int
    {
        if (! $user->hasUsableScope()) {
            throw ValidationException::withMessages(['municipality_id' => 'Your account needs an active province or municipality assignment.']);
        }
        if ($user->canAccessAllMunicipalities()) {
            return $this->validateActiveMunicipality($user, $requestedMunicipalityId);
        }
        if ($requestedMunicipalityId !== null && $requestedMunicipalityId !== '' && (int) $requestedMunicipalityId !== $user->municipality_id) {
            throw ValidationException::withMessages(['municipality_id' => 'You cannot save records for another municipality.']);
        }

        return $this->validateActiveMunicipality($user, $user->municipality_id);
    }

    public function choices(User $user): Collection
    {
        return $this->scopeMunicipalities(Municipality::query()->active(), $user)
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active())
            ->orderBy('name')->get(['id', 'name', 'province', 'province_id']);
    }

    private function validateActiveMunicipality(User $user, mixed $municipalityId): int
    {
        if (! filter_var($municipalityId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw ValidationException::withMessages(['municipality_id' => 'Please select an active municipality.']);
        }
        $municipalityId = (int) $municipalityId;
        $available = $this->scopeMunicipalities(Municipality::query()->active(), $user)->whereKey($municipalityId)
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active())->exists();
        if (! $available) {
            throw ValidationException::withMessages(['municipality_id' => 'The selected municipality is unavailable.']);
        }

        return $municipalityId;
    }
}

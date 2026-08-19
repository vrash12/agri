<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MunicipalityAccess
{
    public function scope(
        Builder $query,
        User $user,
        ?string $qualifiedColumn = null
    ): Builder {
        if ($user->canAccessAllMunicipalities()) {
            return $query;
        }

        $municipalityId = $this->assignedMunicipalityId($user);
        $column = $qualifiedColumn
            ?: $query->getModel()->qualifyColumn('municipality_id');

        return $query->where($column, $municipalityId);
    }

    public function applyOptionalFilter(
        Builder $query,
        User $user,
        mixed $requestedMunicipalityId,
        ?string $qualifiedColumn = null
    ): Builder {
        $this->scope($query, $user, $qualifiedColumn);

        if (! $user->canAccessAllMunicipalities()) {
            return $query;
        }

        if ($requestedMunicipalityId === null || $requestedMunicipalityId === '') {
            return $query;
        }

        $municipalityId = $this->validateActiveMunicipality(
            $requestedMunicipalityId
        );
        $column = $qualifiedColumn
            ?: $query->getModel()->qualifyColumn('municipality_id');

        return $query->where($column, $municipalityId);
    }

    public function resolveForWrite(
        User $user,
        mixed $requestedMunicipalityId = null
    ): int {
        if ($user->canAccessAllMunicipalities()) {
            return $this->validateActiveMunicipality(
                $requestedMunicipalityId
            );
        }

        $municipalityId = $this->assignedMunicipalityId($user);

        if (
            $requestedMunicipalityId !== null
            && $requestedMunicipalityId !== ''
            && (int) $requestedMunicipalityId !== $municipalityId
        ) {
            throw ValidationException::withMessages([
                'municipality_id' => 'You cannot save records for another municipality.',
            ]);
        }

        return $municipalityId;
    }

    public function choices(User $user): Collection
    {
        $query = Municipality::query()
            ->active()
            ->orderBy('name');

        if (! $user->canAccessAllMunicipalities()) {
            $query->whereKey($this->assignedMunicipalityId($user));
        }

        return $query->get(['id', 'name', 'province']);
    }

    private function assignedMunicipalityId(User $user): int
    {
        if (! $user->municipality_id) {
            throw ValidationException::withMessages([
                'municipality_id' => 'Your account is not assigned to a municipality.',
            ]);
        }

        return $this->validateActiveMunicipality($user->municipality_id);
    }

    private function validateActiveMunicipality(
        mixed $municipalityId
    ): int {
        if (
            $municipalityId === null
            || $municipalityId === ''
            || ! filter_var(
                $municipalityId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
        ) {
            throw ValidationException::withMessages([
                'municipality_id' => 'Please select an active municipality.',
            ]);
        }

        $municipalityId = (int) $municipalityId;
        $exists = Municipality::query()
            ->active()
            ->whereKey($municipalityId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'municipality_id' => 'The selected municipality is unavailable.',
            ]);
        }

        return $municipalityId;
    }
}

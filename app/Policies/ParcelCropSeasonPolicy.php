<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesMunicipalityRecords;
use Illuminate\Database\Eloquent\Model;

class ParcelCropSeasonPolicy
{
    use AuthorizesMunicipalityRecords;

    public function view(User $user, Model $record): bool
    {
        return $user->canAccessMunicipality($record->municipality_id)
            && $record->plot && $user->can('view', $record->plot)
            && (int) $record->plot->farmer?->municipality_id === (int) $record->municipality_id;
    }
}

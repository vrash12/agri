<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesMunicipalityRecords;
use Illuminate\Database\Eloquent\Model;

class BackupFilePolicy
{
    use AuthorizesMunicipalityRecords {
        viewAny as private viewAnyWithinMunicipality;
        view as private viewWithinMunicipality;
        export as private exportWithinMunicipality;
    }

    public function viewAny(User $user): bool
    {
        return ! $user->isSuperAdmin()
            && $this->viewAnyWithinMunicipality($user);
    }

    public function view(User $user, Model $record): bool
    {
        return ! $user->isSuperAdmin()
            && $this->viewWithinMunicipality($user, $record);
    }

    public function export(User $user, ?Model $record = null): bool
    {
        return ! $user->isSuperAdmin()
            && $this->exportWithinMunicipality($user, $record);
    }
}

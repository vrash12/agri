<?php

namespace App\Policies;

use App\Models\HarvestRecord;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMunicipalityRecords;
use Illuminate\Database\Eloquent\Model;

/**
 * Municipality isolation for harvest records, plus one rule of its own.
 *
 * A harvest projected from an assistance release is owned by that release. The
 * release is where its figures are entered, and saving the release rewrites the
 * projected record from them. Allowing the harvest to be edited here as well would
 * mean the next save of the release silently discards the edit — the operator would
 * see their change accepted and then find it gone, with nothing to explain why.
 *
 * So a projected record is readable here and changed at its source. The interface
 * says so and links to the release; this policy is what makes that true rather than
 * merely suggested, because hidden buttons are not enforcement.
 */
class HarvestRecordPolicy
{
    use AuthorizesMunicipalityRecords;

    public function update(User $user, Model $record): bool
    {
        return ! $this->isProjected($record)
            && $this->authorizesManagement($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return ! $this->isProjected($record)
            && $this->authorizesManagement($user, $record);
    }

    private function isProjected(Model $record): bool
    {
        return $record instanceof HarvestRecord && $record->isProjected();
    }

    /**
     * The management check the shared concern applies, reused rather than restated so
     * the role and scope rules cannot drift from every other module's.
     */
    private function authorizesManagement(User $user, Model $record): bool
    {
        return $user->canManageOperationalData() && $this->view($user, $record);
    }
}
